#!/usr/bin/env bash
#
# 在阿里云 ECS（2核2G）上部署一个本地大模型，供网站的 AI 助手调用。
#
#   用法：sudo bash setup.sh            # 默认 Qwen3-0.6B（最稳，约占 500MB 内存）
#         sudo bash setup.sh 1.7b       # Qwen3-1.7B（效果更好，需要 2G swap 兜底）
#         sudo bash setup.sh 1.7b --no-build   # 已经编译过，只换模型
#
# 装完会打印：接口地址（http://127.0.0.1:8080/v1）、模型名、API Key
# 把这三样填进网站后台的「AI 助手」即可。
#
set -euo pipefail

MODEL_TAG="${1:-0.6b}"
SKIP_BUILD=0
for a in "$@"; do [ "$a" = "--no-build" ] && SKIP_BUILD=1; done

APP_DIR=/opt/local-llm
SRC_DIR="$APP_DIR/llama.cpp"
BIN="$SRC_DIR/build/bin/llama-server"
MODEL_DIR="$APP_DIR/models"
SERVICE=local-llm
PORT=8080
KEY_FILE="$APP_DIR/api-key.txt"

log()  { printf '\033[36m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[33m[!]\033[0m %s\n' "$*"; }
die()  { printf '\033[31m[x]\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "请用 root 运行：sudo bash $0"

# ── 0. 硬件体检 ────────────────────────────────────────────────
MEM_MB=$(awk '/MemTotal/{print int($2/1024)}' /proc/meminfo)
SWAP_MB=$(awk '/SwapTotal/{print int($2/1024)}' /proc/meminfo)
CORES=$(nproc)
log "内存 ${MEM_MB}MB / swap ${SWAP_MB}MB / ${CORES} 核"

if [ "$MEM_MB" -lt 1800 ]; then
  warn "内存不足 1.8G，建议先升级配置，否则模型容易把自己 OOM 掉"
fi
if [ "$CORES" -lt 2 ]; then
  warn "只有 ${CORES} 核，生成速度会比较慢"
fi

# ── 1. 加 swap（2G 以内内存必须）────────────────────────────────
if [ "$SWAP_MB" -lt 1024 ]; then
  log "创建 2G swap 文件（跑模型时兜底，防止 OOM 杀掉 nginx/php）"
  if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
  fi
  swapon /swapfile 2>/dev/null || true
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
  sysctl -w vm.swappiness=20 >/dev/null 2>&1 || true
else
  log "已有 swap ${SWAP_MB}MB，跳过"
fi

# ── 2. 编译 llama.cpp ────────────────────────────────────────
mkdir -p "$APP_DIR" "$MODEL_DIR"

if [ "$SKIP_BUILD" -eq 0 ] && [ ! -x "$BIN" ]; then
  log "安装编译依赖"
  apt-get update -qq
  apt-get install -y -qq build-essential cmake curl git ca-certificates >/dev/null

  if [ ! -d "$SRC_DIR" ]; then
    log "拉取 llama.cpp（浅克隆，省空间）"
    git clone --depth 1 https://github.com/ggml-org/llama.cpp.git "$SRC_DIR" \
      || die "拉取失败，可试试先 export https_proxy=... 再重跑"
  fi

  log "编译 llama-server（${CORES} 线程，约 5-10 分钟，别中断）"
  cmake -S "$SRC_DIR" -B "$SRC_DIR/build" \
        -DCMAKE_BUILD_TYPE=Release \
        -DGGML_NATIVE=ON \
        -DLLAMA_CURL=OFF >/dev/null
  cmake --build "$SRC_DIR/build" --config Release -j"$CORES" --target llama-server >/dev/null
  [ -x "$BIN" ] || die "编译完成但没找到 $BIN"
  log "编译完成"
else
  [ -x "$BIN" ] || die "没有编译产物，去掉 --no-build 重跑"
  log "已有可执行文件，跳过编译"
fi

# ── 3. 下载模型 ──────────────────────────────────────────────
case "$MODEL_TAG" in
  0.6b) MODEL_NAME="qwen3-0.6b"; FILE="Qwen3-0.6B-Q4_K_M.gguf" ;;
  1.7b) MODEL_NAME="qwen3-1.7b"; FILE="Qwen3-1.7B-Q4_K_M.gguf" ;;
  *)    die "只支持 0.6b 或 1.7b" ;;
esac

GGUF="$MODEL_DIR/$FILE"
if [ ! -s "$GGUF" ]; then
  log "下载模型 $FILE（约 $( [ "$MODEL_TAG" = 0.6b ] && echo 378 || echo 1056 )MB）"
  # 国内走魔搭（实测可断点续传），失败再试 HuggingFace
  for URL in \
    "https://modelscope.cn/models/unsloth/Qwen3-$(echo "$MODEL_TAG" | tr 'a-z' 'A-Z')-GGUF/resolve/master/$FILE" \
    "https://huggingface.co/unsloth/Qwen3-$(echo "$MODEL_TAG" | tr 'a-z' 'A-Z')-GGUF/resolve/main/$FILE"
  do
    log "  try $URL"
    if curl -fL --retry 3 --retry-delay 2 -C - --connect-timeout 15 -o "$GGUF" "$URL"; then
      break
    fi
    rm -f "$GGUF"
  done
  [ -s "$GGUF" ] || die "模型下载失败。手动把 $FILE 放到 $MODEL_DIR 后，重跑：sudo bash $0 $MODEL_TAG --no-build"
fi
ls -lh "$GGUF" | awk '{print "  模型体积：" $5}'

# ── 4. API Key ──────────────────────────────────────────────
if [ ! -s "$KEY_FILE" ]; then
  head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32 > "$KEY_FILE"
fi
API_KEY=$(cat "$KEY_FILE")

# ── 5. systemd 服务 ─────────────────────────────────────────
RUN_USER=www
id "$RUN_USER" >/dev/null 2>&1 || RUN_USER=root
chown -R "$RUN_USER":"$RUN_USER" "$APP_DIR" 2>/dev/null || true
chmod 600 "$KEY_FILE"

# 老版本 llama-server 没有 --api-key，检测后再决定加不加
API_KEY_ARG=""
if "$BIN" --help 2>&1 | grep -q -- '--api-key'; then
  API_KEY_ARG="--api-key $API_KEY"
else
  warn "这个版本的 llama-server 不支持 --api-key，靠只监听 127.0.0.1 保护"
  API_KEY=""
fi

log "写 systemd 服务（只监听 127.0.0.1:$PORT，外网访问不到）"
cat > /etc/systemd/system/$SERVICE.service <<EOF
[Unit]
Description=Local LLM (llama.cpp server) for site assistant
After=network.target

[Service]
Type=simple
User=$RUN_USER
WorkingDirectory=$APP_DIR
ExecStart=$BIN -m $GGUF --host 127.0.0.1 --port $PORT \\
  -c 2048 -t $CORES --parallel 1 --jinja $API_KEY_ARG
Restart=always
RestartSec=5
# 内存兜底：真被挤爆时优先杀它，别把 nginx/php 带下水
MemoryMax=1600M
OOMScoreAdjust=500
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable $SERVICE >/dev/null 2>&1 || true
systemctl restart $SERVICE

# ── 6. 自检 ─────────────────────────────────────────────────
log "等待模型加载（首次约 5-20 秒）"
OK=0
for i in $(seq 1 40); do
  if curl -sf --max-time 3 "http://127.0.0.1:$PORT/health" >/dev/null 2>&1; then OK=1; break; fi
  sleep 1
done

if [ "$OK" -eq 1 ]; then
  log "服务已就绪，做一次真实问答"
  curl -s --max-time 120 "http://127.0.0.1:$PORT/v1/chat/completions" \
    -H 'Content-Type: application/json' \
    ${API_KEY:+-H "Authorization: Bearer $API_KEY"} \
    -d "{\"model\":\"$MODEL_NAME\",\"messages\":[{\"role\":\"user\",\"content\":\"说三个字：你好呀\"}],\"max_tokens\":32}" \
    | head -c 500
  echo
else
  warn "健康检查没通过，看日志：journalctl -u $SERVICE -n 50 --no-pager"
fi

cat <<EOF

──────────────────────────────────────────────
 接入参数（填进网站后台 → AI 助手）

 接口地址   http://127.0.0.1:$PORT/v1
 模型名     $MODEL_NAME
 API Key     ${API_KEY:-（这个版本不需要）}

 建议：把上面三项填到「备用通道」，
       主通道继续用免费的 glm-4.7-flash，云端挂了自动降级到本地。

 常用命令
   journalctl -u $SERVICE -f        实时日志
   systemctl restart $SERVICE       重启
   free -h                          看内存
   systemctl stop $SERVICE          停掉（不用了就停，省内存）

 换模型：sudo bash $0 1.7b --no-build
──────────────────────────────────────────────
EOF
