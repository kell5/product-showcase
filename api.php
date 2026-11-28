<?php
/**
 * 产品管理 API
 * 数据以 JSON 文件形式存储在服务器 data/ 目录
 * 图片以文件形式存储在 uploads/ 目录
 *
 * 首次部署请在服务器执行：php tools/set-admin-password.php
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// ─────────── CORS ───────────
// 后台页面与接口同源，默认不需要跨域。
// 若确实需要被其他域名调用，把域名加进下面数组（例：'https://www.lk-mcu.online'）。
$ALLOWED_ORIGINS = [];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$corsOk = ($origin !== '' && in_array($origin, $ALLOWED_ORIGINS, true));

if ($corsOk) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code($corsOk ? 200 : 403);
    exit;
}

// ─────────── 配置 ───────────
define('DATA_DIR', __DIR__ . '/data');
define('PRODUCTS_FILE', DATA_DIR . '/products.json');
// 机密数据统一用 .php 存储：被浏览器直接访问时 PHP 会执行它并输出空内容，
// 不会像 .json/.hash 那样把哈希和 token 原样吐出来。
define('PASSWORD_FILE', DATA_DIR . '/password.php');
define('TOKENS_FILE', DATA_DIR . '/tokens.php');
define('LIMIT_FILE', DATA_DIR . '/login-attempts.php');
define('LEGACY_PASSWORD_FILE', DATA_DIR . '/password.hash');
define('MESSAGES_FILE', DATA_DIR . '/messages.json');
define('MSG_LIMIT_FILE', DATA_DIR . '/msg-limit.php');
define('UPLOAD_DIR', __DIR__ . '/uploads');

// AI 助手：配置（含 API Key）、用量、问答缓存都用 .php 存，避免被直接下载
define('AI_CONFIG_FILE', DATA_DIR . '/ai-config.php');
define('AI_USAGE_FILE', DATA_DIR . '/ai-usage.php');
define('AI_CACHE_FILE', DATA_DIR . '/ai-cache.php');
define('TOPICS_DATA_FILE', __DIR__ . '/js/thesis-topics.data.js');

define('AI_MAX_QUESTION', 200);      // 单次提问长度上限
define('AI_CONTEXT_TOPICS', 8);      // 送给模型的题目条数
define('AI_TIMEOUT', 40);            // 云端上游超时（秒）
define('AI_TIMEOUT_LOCAL', 150);     // 自建模型超时（CPU 推理慢，要放宽）
define('AI_MIN_INTERVAL', 3);        // 同一 IP 两次提问的最小间隔
define('AI_CACHE_TTL', 604800);      // 问答缓存有效期 7 天
define('AI_CACHE_MAX', 400);         // 缓存条目上限

define('MSG_MAX_KEEP', 500);        // 最多保留多少条留言
define('MSG_NAME_MAX', 20);         // 昵称长度上限
define('MSG_BODY_MAX', 500);        // 留言长度上限
define('MSG_INTERVAL', 20);         // 同一 IP 两次留言最小间隔（秒）
define('MSG_HOURLY', 5);            // 同一 IP 每小时最多留言数

define('TOKEN_TTL', 86400);                // 登录有效期 24 小时
define('MAX_LOGIN_FAILS', 5);              // 窗口内允许的失败次数
define('LOGIN_WINDOW', 600);               // 失败计数窗口 10 分钟
define('LOGIN_BLOCK', 900);                // 触发后封禁 15 分钟
define('MAX_UPLOAD_BYTES', 20 * 1024 * 1024);

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// ─────────── JSON 文件读写 ───────────
function readJson($file, $default = []) {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/** 加锁写入，避免并发请求互相覆盖。写不进去只返回 false，不抛 warning。 */
function writeJson($file, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/**
 * 读取机密数据（文件内容形如 `<?php return [...];`）。
 * 直接访问该文件时 PHP 只会执行并返回，不产生任何输出，因此不会泄漏。
 */
function readSecret($file, $default = null) {
    if (!file_exists($file)) return $default;
    // 改了文件内容后清掉 opcache，避免读到缓存里的旧值
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    $value = @include $file;
    return $value === false ? $default : $value;
}

function writeSecret($file, $value) {
    $code = "<?php\n// 本文件由 api.php 生成，请勿手动编辑，也不要放进 Git\nreturn " . var_export($value, true) . ";\n";
    $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $code, LOCK_EX) === false) return false;
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    @chmod($file, 0644);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    return true;
}

// ─────────── 通用工具 ───────────
function getProducts() {
    return readJson(PRODUCTS_FILE, []);
}

function saveProducts($products) {
    return writeJson(PRODUCTS_FILE, array_values($products));
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function generateId() {
    return bin2hex(random_bytes(8));
}

function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function textLength($s) {
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

// ─────────── 密码 ───────────
function getStoredPassword() {
    $hash = readSecret(PASSWORD_FILE);
    if (is_string($hash) && trim($hash) !== '') return trim($hash);

    // 兜底：迁移还没成功（例如 data/ 目录不可写）时继续读旧文件，
    // 保证后台至少还能登录，不会因为迁移失败而整体不可用。
    if (file_exists(LEGACY_PASSWORD_FILE)) {
        $legacy = trim((string) @file_get_contents(LEGACY_PASSWORD_FILE));
        if ($legacy !== '') return $legacy;
    }
    return null;
}

/**
 * 校验密码。
 * 返回 true 通过 / false 不通过 / null 表示后台密码尚未初始化。
 * 兼容旧的 sha256 哈希，校验通过后自动升级为 bcrypt。
 */
function verifyPassword($pwd) {
    $stored = getStoredPassword();
    if ($stored === null) return null;

    if (preg_match('/^[a-f0-9]{64}$/i', $stored)) {
        $ok = hash_equals(strtolower($stored), hash('sha256', $pwd));
        if ($ok) {
            setPassword($pwd);
        }
        return $ok;
    }
    return password_verify($pwd, $stored);
}

function setPassword($pwd) {
    return writeSecret(PASSWORD_FILE, password_hash($pwd, PASSWORD_DEFAULT));
}

// ─────────── 登录限流 ───────────
function checkLoginLimit() {
    $ip = clientIp();
    $now = time();
    $store = readSecret(LIMIT_FILE, []);
    if (!is_array($store)) $store = [];
    $entry = $store[$ip] ?? null;

    if (is_array($entry) && ($entry['blocked_until'] ?? 0) > $now) {
        $wait = (int) ceil(($entry['blocked_until'] - $now) / 60);
        respond(['success' => false, 'message' => "尝试次数过多，请 {$wait} 分钟后再试"], 429);
    }
}

function recordLoginFail() {
    $ip = clientIp();
    $now = time();
    $store = readSecret(LIMIT_FILE, []);
    if (!is_array($store)) $store = [];

    // 清理过期记录，避免文件无限增长
    foreach ($store as $k => $v) {
        if ($now - ($v['first'] ?? 0) > LOGIN_WINDOW && ($v['blocked_until'] ?? 0) < $now) {
            unset($store[$k]);
        }
    }

    $entry = $store[$ip] ?? ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    if ($now - ($entry['first'] ?? 0) > LOGIN_WINDOW) {
        $entry = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    }
    $entry['count'] = ($entry['count'] ?? 0) + 1;
    if ($entry['count'] >= MAX_LOGIN_FAILS) {
        $entry['blocked_until'] = $now + LOGIN_BLOCK;
        $entry['count'] = 0;
        $entry['first'] = $now;
    }
    $store[$ip] = $entry;
    writeSecret(LIMIT_FILE, $store);
}

function clearLoginFail() {
    $ip = clientIp();
    $store = readSecret(LIMIT_FILE, []);
    if (!is_array($store)) $store = [];
    if (isset($store[$ip])) {
        unset($store[$ip]);
        writeSecret(LIMIT_FILE, $store);
    }
}

// ─────────── Token（支持多端同时登录） ───────────
function readTokens() {
    $now = time();
    $tokens = readSecret(TOKENS_FILE, []);
    if (!is_array($tokens)) $tokens = [];
    return array_values(array_filter($tokens, function ($t) use ($now) {
        return isset($t['hash'], $t['expires']) && $t['expires'] > $now;
    }));
}

function issueToken() {
    $token = bin2hex(random_bytes(32));
    $tokens = readTokens();
    $tokens[] = ['hash' => hash('sha256', $token), 'expires' => time() + TOKEN_TTL];
    if (count($tokens) > 20) {
        $tokens = array_slice($tokens, -20);
    }
    writeSecret(TOKENS_FILE, $tokens);
    return $token;
}

function verifyToken($token) {
    if (!is_string($token) || strlen($token) !== 64) return false;
    $target = hash('sha256', $token);
    foreach (readTokens() as $t) {
        if (hash_equals($t['hash'], $target)) return true;
    }
    return false;
}

function revokeToken($token) {
    if (!is_string($token) || strlen($token) !== 64) return;
    $target = hash('sha256', $token);
    $tokens = array_values(array_filter(readTokens(), function ($t) use ($target) {
        return !hash_equals($t['hash'], $target);
    }));
    writeSecret(TOKENS_FILE, $tokens);
}

function bearerToken() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization' && stripos($value, 'bearer ') === 0) {
            return trim(substr($value, 7));
        }
    }
    return '';
}

function requireAuth() {
    if (!verifyToken(bearerToken())) {
        respond(['success' => false, 'message' => '未授权，请重新登录'], 401);
    }
}

// ─────────── 上传相关 ───────────
/** 按真实文件内容识别图片类型，返回扩展名；非图片返回 null */
function imageExt($path) {
    $map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
    ];
    // IMAGETYPE_WEBP 需要 PHP 7.1+，低版本直接不支持 webp
    if (defined('IMAGETYPE_WEBP')) {
        $map[IMAGETYPE_WEBP] = 'webp';
    }
    $info = @getimagesize($path);
    if (!$info || !isset($info[2])) return null;
    return $map[$info[2]] ?? null;
}

/** 只允许删除 uploads/ 目录内的文件，防止路径穿越 */
function safeUnlinkUpload($relative) {
    $root = realpath(UPLOAD_DIR);
    if ($root === false) return;
    $target = realpath(UPLOAD_DIR . '/' . basename($relative));
    if ($target === false) return;
    if (strpos($target, $root . DIRECTORY_SEPARATOR) !== 0) return;
    if (is_file($target)) @unlink($target);
}

// ─────────── 留言 ───────────
function getMessages() {
    $list = readJson(MESSAGES_FILE, []);
    return is_array($list) ? $list : [];
}

function saveMessages($list) {
    return writeJson(MESSAGES_FILE, array_values($list));
}

function clipText($s, $max) {
    $s = (string) $s;
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}

/** 明显的垃圾留言：堆链接 */
function looksLikeSpam($text) {
    return preg_match_all('#(https?://|www\.)#i', $text) >= 3;
}

/**
 * 留言频率限制。返回 null 表示通过，返回字符串表示应拒绝并给出提示。
 * 只在这里记录 IP 的哈希，不写进留言数据本身。
 */
function messageRateLimit() {
    $now = time();
    $key = substr(hash('sha256', clientIp() . '|guestbook'), 0, 16);
    $store = readSecret(MSG_LIMIT_FILE, []);
    if (!is_array($store)) $store = [];

    $entry = $store[$key] ?? ['last' => 0, 'times' => []];
    $times = array_filter((array) ($entry['times'] ?? []), function ($t) use ($now) {
        return $now - (int) $t < 3600;
    });
    $times = array_values($times);

    $last = (int) ($entry['last'] ?? 0);
    if ($last > 0 && $now - $last < MSG_INTERVAL) {
        return '留言太快了，请 ' . (MSG_INTERVAL - ($now - $last)) . ' 秒后再提交';
    }
    if (count($times) >= MSG_HOURLY) {
        return '这段时间留言有点多，请稍后再试';
    }

    $times[] = $now;
    $store[$key] = ['last' => $now, 'times' => $times];

    // 清理一天前的记录，避免文件无限增长
    foreach ($store as $k => $v) {
        $raw = array_filter((array) ($v['times'] ?? []), 'is_numeric');
        if (!$raw || $now - max(array_map('intval', $raw)) > 86400) {
            unset($store[$k]);
        }
    }

    writeSecret(MSG_LIMIT_FILE, $store);
    return null;
}

/** 对外只暴露展示需要的字段 */
function publicMessage($m) {
    return [
        'id'        => $m['id'] ?? '',
        'name'      => $m['name'] ?? '',
        'content'   => $m['content'] ?? '',
        'createdAt' => $m['createdAt'] ?? '',
    ];
}

// ─────────── 旧格式机密文件迁移 ───────────
/**
 * 旧版本把密码哈希、token、登录计数存成 .hash / .json，
 * 这些文件能被浏览器直接下载。这里在每次请求时检查一次，
 * 搬到 .php 存储（访问时只执行不输出）并删除旧文件。
 * 迁移完成后这段逻辑没有任何副作用。
 */
function migrateLegacySecrets() {
    try {
        // 密码哈希：data/password.hash -> data/password.php
        if (!file_exists(PASSWORD_FILE) && file_exists(LEGACY_PASSWORD_FILE)) {
            $legacy = trim((string) @file_get_contents(LEGACY_PASSWORD_FILE));
            if ($legacy !== '') {
                writeSecret(PASSWORD_FILE, $legacy);
            }
        }
        // 只有新文件确实写成功，才删除可被下载的旧文件
        if (file_exists(PASSWORD_FILE) && file_exists(LEGACY_PASSWORD_FILE)) {
            @unlink(LEGACY_PASSWORD_FILE);
        }

        // token 与登录计数：data/*.json -> data/*.php
        foreach (['tokens', 'login-attempts'] as $name) {
            $old = DATA_DIR . '/' . $name . '.json';
            $new = DATA_DIR . '/' . $name . '.php';
            if (!file_exists($new) && file_exists($old)) {
                $data = readJson($old, []);
                if (is_array($data) && $data) {
                    writeSecret($new, $data);
                }
            }
            if (file_exists($new) && file_exists($old)) {
                @unlink($old);
            }
        }
    } catch (Throwable $e) {
        // 迁移是尽力而为，失败绝不能影响接口本身
    }
}

migrateLegacySecrets();

// ─────────── AI 站内助手 ───────────
/**
 * 思路：先把问题在本地选题库 / 项目里做关键词检索（带 idf 加权），
 * 只把命中的 8 条题目交给模型改写。这样既不用把 699 条全塞进 prompt
 * （省 token、响应快），模型也没有编造题目的空间。
 */

function aiModels() {
    return [
        // 云端（智谱开放平台，OpenAI 兼容）
        'glm-4.7-flash', 'glm-4.5-flash', 'glm-4-flash', 'glm-4-air', 'glm-4.6',
        // 自建（llama.cpp / Ollama / vLLM 的模型名，按你实际拉取的写）
        'qwen3-1.7b', 'qwen3-0.6b', 'qwen2.5-1.5b-instruct', 'local-model',
    ];
}

/**
 * 接口地址白名单。
 * https 任意域名放行；http 只允许本机与内网段 —— 自建模型基本都跑在
 * 127.0.0.1:xxxx/v1，用 https 会多一层没必要的证书麻烦，但公网 http 不能放。
 */
function aiUrlAllowed($url) {
    $url = trim((string) $url);
    if (!preg_match('#^(https?)://([a-z0-9\.\-]+)(:[0-9]{1,5})?(/[a-z0-9\.\-/_]*)?$#i', $url, $m)) {
        return false;
    }
    if (strtolower($m[1]) === 'https') return true;
    $host = strtolower($m[2]);
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '0.0.0.0') return true;
    if (preg_match('/^10\./', $host)) return true;
    if (preg_match('/^192\.168\./', $host)) return true;
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)) return true;
    return false;
}

/** 是否指向本机/内网自建服务（决定超时与是否强制要 Key） */
function aiIsLocalUrl($url) {
    if (!preg_match('#^https?://([a-z0-9\.\-]+)#i', trim((string) $url), $m)) return false;
    $host = strtolower($m[1]);
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '0.0.0.0') return true;
    return (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $host);
}

/**
 * 智谱 GLM-4.5 及以上默认开「思考模式」，思考 token 计入 max_tokens。
 * 结果就是：max_tokens=900 时思考能吃掉 800+，message.content 直接是空字符串，
 * 前端只看到「模型返回了空内容」。这个站只让它组织语言，不需要它推理，
 * 所以对这些模型显式关掉思考。
 */
function aiNeedsNoThinking($conf) {
    $host = strtolower((string) parse_url((string) $conf['base_url'], PHP_URL_HOST));
    if ($host === '') return false;
    if (strpos($host, 'bigmodel.cn') === false && strpos($host, 'zhipu') === false) return false;
    return (bool) preg_match('/^glm-(4\.[5-9]|5|[6-9]\d?)/', strtolower((string) $conf['model']));
}

function aiLower($s) {
    return function_exists('mb_strtolower') ? mb_strtolower((string) $s, 'UTF-8') : strtolower((string) $s);
}

function aiConfig() {
    $defaults = [
        'enabled'     => false,
        'api_key'     => '',
        'model'       => 'glm-4.7-flash',
        'base_url'    => 'https://open.bigmodel.cn/api/paas/v4',
        'per_ip'      => 30,
        'per_total'   => 800,
        'temperature' => 0.6,
        'max_tokens'  => 900,
        // 备用通道：主通道（云端）挂了/超限时自动改走自建模型
        'fallback_enabled' => false,
        'fallback_url'     => '',
        'fallback_key'     => '',
        'fallback_model'   => '',
    ];
    $saved = readSecret(AI_CONFIG_FILE, []);
    if (!is_array($saved)) $saved = [];
    $cfg = array_merge($defaults, $saved);

    // 环境变量优先：不想把 Key 落在文件里时，可以在站点配置里设 ZHIPU_API_KEY
    $envKey = getenv('ZHIPU_API_KEY');
    if ($envKey === false || $envKey === '') $envKey = getenv('GLM_API_KEY');
    if ($envKey !== false && trim((string) $envKey) !== '') $cfg['api_key'] = trim((string) $envKey);

    $cfg['api_key']     = trim((string) $cfg['api_key']);
    $cfg['per_ip']      = max(1, min(200, (int) $cfg['per_ip']));
    $cfg['per_total']   = max(10, min(20000, (int) $cfg['per_total']));
    $cfg['temperature'] = max(0, min(1.2, (float) $cfg['temperature']));
    $cfg['max_tokens']  = max(120, min(2000, (int) $cfg['max_tokens']));
    return $cfg;
}

function aiReady($cfg) {
    if (empty($cfg['enabled'])) return false;
    if (trim((string) $cfg['api_key']) !== '') return true;
    // 指向本机自建服务时允许不带 Key（llama-server 默认不校验）
    return aiIsLocalUrl((string) $cfg['base_url']);
}

/** 取出可用的备用通道配置，不可用返回 null */
function aiFallbackConfig($cfg) {
    if (empty($cfg['fallback_enabled'])) return null;
    $url   = trim((string) $cfg['fallback_url']);
    $model = trim((string) $cfg['fallback_model']);
    if ($url === '' || $model === '' || !aiUrlAllowed($url)) return null;

    // 备用通道往往是同一个厂商的另一个模型（比如 4.7 被限流时退到 4-flash），
    // 这时不用让用户再填一遍 Key。本地服务没设 --api-key 时多带一个头也无害。
    $fbKey = trim((string) $cfg['fallback_key']);
    if ($fbKey === '') $fbKey = trim((string) $cfg['api_key']);

    return [
        'base_url'    => rtrim($url, '/'),
        'api_key'     => $fbKey,
        'model'       => $model,
        'temperature' => $cfg['temperature'],
        // 小模型话多就慢，备用通道强制压短
        'max_tokens'  => min(500, (int) $cfg['max_tokens']),
    ];
}

function aiIpKey() {
    return substr(hash('sha256', clientIp() . '|ai-assistant'), 0, 16);
}

function aiMaskKey($key) {
    $key = (string) $key;
    $len = strlen($key);
    if ($len <= 8) return $len ? str_repeat('*', $len) : '';
    return substr($key, 0, 4) . str_repeat('*', 8) . substr($key, -4);
}

/** 当日用量（跨天自动归零，历史汇总保留 14 天） */
function aiUsage() {
    $today = date('Y-m-d');
    $u = readSecret(AI_USAGE_FILE, []);
    if (!is_array($u)) $u = [];
    $history = (isset($u['history']) && is_array($u['history'])) ? $u['history'] : [];
    $base = [
        'day'     => $today,
        'total'   => 0,
        'tokens'  => 0,
        'hits'    => 0,
        'ips'     => [],
        'last'    => 0,
        'allTime' => (int) ($u['allTime'] ?? 0),
        'history' => $history,
    ];
    if (($u['day'] ?? '') !== $today) return $base;
    return array_merge($base, $u);
}

function aiUsageBump($tokens, $cached) {
    $today = date('Y-m-d');
    $u = readSecret(AI_USAGE_FILE, []);
    if (!is_array($u)) $u = [];

    if (($u['day'] ?? '') !== $today) {
        $history = (isset($u['history']) && is_array($u['history'])) ? $u['history'] : [];
        if (!empty($u['day'])) {
            $history[$u['day']] = ['total' => (int) ($u['total'] ?? 0), 'tokens' => (int) ($u['tokens'] ?? 0)];
        }
        if (count($history) > 14) $history = array_slice($history, -14, null, true);
        $u = [
            'day' => $today, 'total' => 0, 'tokens' => 0, 'hits' => 0, 'ips' => [],
            'allTime' => (int) ($u['allTime'] ?? 0), 'history' => $history,
        ];
    }

    $u['total']   = (int) ($u['total'] ?? 0) + 1;
    $u['allTime'] = (int) ($u['allTime'] ?? 0) + 1;
    $u['tokens']  = (int) ($u['tokens'] ?? 0) + max(0, (int) $tokens);
    if ($cached) $u['hits'] = (int) ($u['hits'] ?? 0) + 1;
    $k = aiIpKey();
    if (!isset($u['ips']) || !is_array($u['ips'])) $u['ips'] = [];
    $entry = $u['ips'][$k] ?? ['n' => 0, 't' => 0];
    if (is_int($entry) || is_numeric($entry)) $entry = ['n' => (int) $entry, 't' => 0];
    $entry['n'] = (int) ($entry['n'] ?? 0) + 1;
    $entry['t'] = time();
    $u['ips'][$k] = $entry;
    $u['last'] = time();
    writeSecret(AI_USAGE_FILE, $u);
}

/** 当前 IP 的用量与上次提问时间（兼容早期只存整数的格式） */
function aiIpUsed($u) {
    $entry = $u['ips'][aiIpKey()] ?? ['n' => 0, 't' => 0];
    if (is_int($entry) || is_numeric($entry)) $entry = ['n' => (int) $entry, 't' => 0];
    return ['n' => (int) ($entry['n'] ?? 0), 't' => (int) ($entry['t'] ?? 0)];
}

/** 返回 null 表示放行，返回字符串表示应当拒答 */
function aiQuotaError($cfg) {
    $u = aiUsage();
    $now = time();
    $me = aiIpUsed($u);
    $used = $me['n'];

    // 最小间隔只看自己的上次提问，别人刚问过不该影响你
    $gap = AI_MIN_INTERVAL - ($now - $me['t']);
    if ($used > 0 && $me['t'] > 0 && $gap > 0) {
        return '问得有点快，请 ' . $gap . ' 秒后再发';
    }
    if ($used >= (int) $cfg['per_ip']) {
        return '今天的 ' . (int) $cfg['per_ip'] . ' 次提问已经用完了，可以明天再来，或直接在留言板提问';
    }
    if ((int) ($u['total'] ?? 0) >= (int) $cfg['per_total']) {
        return '今天助手的总次数已用完，请晚些再试，或在留言板提问';
    }
    return null;
}

function aiPublicUsage() {
    $cfg = aiConfig();
    $u = aiUsage();
    return [
        'today'   => (int) ($u['total'] ?? 0),
        'tokens'  => (int) ($u['tokens'] ?? 0),
        'hits'    => (int) ($u['hits'] ?? 0),
        'allTime' => (int) ($u['allTime'] ?? 0),
        'limit'   => (int) $cfg['per_total'],
        'history' => (isset($u['history']) && is_array($u['history'])) ? $u['history'] : [],
    ];
}

function aiCacheGet($key) {
    $store = readSecret(AI_CACHE_FILE, []);
    if (!is_array($store) || !isset($store['items'][$key])) return null;
    $item = $store['items'][$key];
    if (!is_array($item) || (time() - (int) ($item['t'] ?? 0)) > AI_CACHE_TTL) return null;
    $answer = (string) ($item['a'] ?? '');
    return $answer === '' ? null : ['answer' => $answer];
}

function aiCacheSet($key, $answer) {
    $store = readSecret(AI_CACHE_FILE, []);
    if (!is_array($store)) $store = [];
    $items = (isset($store['items']) && is_array($store['items'])) ? $store['items'] : [];

    $now = time();
    foreach ($items as $k => $v) {
        if (($now - (int) ($v['t'] ?? 0)) > AI_CACHE_TTL) unset($items[$k]);
    }
    $items[$key] = ['a' => $answer, 't' => $now];
    if (count($items) > AI_CACHE_MAX) {
        uasort($items, function ($a, $b) { return (int) ($a['t'] ?? 0) - (int) ($b['t'] ?? 0); });
        $items = array_slice($items, -AI_CACHE_MAX, null, true);
    }
    writeSecret(AI_CACHE_FILE, ['items' => $items]);
}

/** 把 js/thesis-topics.data.js 里的 JSON 取出来，建一个扁平索引 */
function loadTopicIndex() {
    static $index = null;
    if ($index !== null) return $index;
    $index = [];

    if (!is_file(TOPICS_DATA_FILE)) return $index;
    $raw = @file_get_contents(TOPICS_DATA_FILE);
    if ($raw === false || $raw === '') return $index;

    // 去掉可能存在的 BOM
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    if ($start === false || $end === false || $end <= $start) return $index;

    $data = json_decode(substr($raw, $start, $end - $start + 1), true);
    if (!is_array($data) || empty($data['categories']) || !is_array($data['categories'])) return $index;

    foreach ($data['categories'] as $cat) {
        $catName = (string) ($cat['name'] ?? '');
        $topics = isset($cat['topics']) && is_array($cat['topics']) ? $cat['topics'] : [];
        foreach ($topics as $t) {
            $title = (string) ($t['title'] ?? '');
            if ($title === '') continue;
            $stack = (string) ($t['stack'] ?? '');
            $hl = isset($t['highlights']) && is_array($t['highlights']) ? implode(' ', $t['highlights']) : '';
            $index[] = [
                'no'       => (int) ($t['no'] ?? 0),
                'title'    => $title,
                'stack'    => $stack,
                'hl'       => $hl,
                'category' => $catName,
                'titleLow' => aiLower($title),
                'stackLow' => aiLower($stack),
                'hlLow'    => aiLower($hl),
                'catLow'   => aiLower($catName),
                'hay'      => aiLower($title . ' ' . $stack . ' ' . $hl . ' ' . $catName),
            ];
        }
    }
    return $index;
}

/** 口语/同义词映射：把学生常说的话映射到题目里真实出现的词 */
function aiSynonyms() {
    return [
        '浇花' => '灌溉', '浇水' => '灌溉', '养花' => '灌溉', '种植' => '温室', '大棚' => '温室',
        '温度' => '温湿', '湿度' => '温湿', '恒温' => '温控', '测温' => '温度',
        '刷脸' => '人脸', '摄像头' => '视觉', '图像' => '视觉', '拍照' => '摄像头',
        '门锁' => '门禁', '指纹锁' => '门禁', '钥匙' => '门禁',
        '小车' => '智能车', '循迹' => '智能车', '避障' => '智能车',
        '机械手' => '机械臂', '舵机' => '机械臂',
        '播报' => '语音', '音频' => '语音', '录音' => '语音',
        '心率' => '心率', '血压' => '血压', '血氧' => '血氧', '跌倒' => '跌倒', '老人' => '养老',
        '猫' => '宠物', '狗' => '宠物', '喂食' => '宠物', '喂养' => '宠物',
        '车位' => '停车', '停车场' => '停车', '取件' => '快递', '快递柜' => '快递',
        '水质' => '水质', '空气' => '空气', 'pm2.5' => '粉尘', '烟雾' => '烟雾', '燃气' => '燃气',
        '库存' => '仓储', '仓库' => '仓储', '物流' => '物流',
        '考勤' => '考勤', '签到' => '考勤', '点名' => '考勤',
        '小程序' => '小程序', 'app' => 'app', '网页' => 'web', '后台' => '管理',
        '太阳能' => '光伏', '电池' => '锂电池', '充电' => '充电',
        '无线' => '无线', '蓝牙' => '蓝牙', '组网' => '组网',
        '简单' => '基础', '容易' => '基础', '入门' => '基础', '难' => '综合',
    ];
}

/**
 * 先把「帮我」「有没有」「题目」这类口语词剔掉。
 * 不剔的话，2-gram 会把「有没」「有和」这种碎片也当成检索词，把泛问搅浑。
 */
function aiStripFiller($q) {
    $fillers = [
        '我只有', '我手上', '我这边', '帮我', '帮忙', '请你', '请问', '你好', '谢谢', '麻烦',
        '一下', '几个', '一些', '那些', '这些', '那种', '这类', '相关', '方面', '题目', '选题',
        '推荐', '有没有', '有什么', '什么', '哪个', '怎么', '如何', '可以', '想要', '我想',
        '需要', '应该', '适合', '能不能', '两周', '一周', '时间', '做完', '做的', '做个',
        '课程设计', '毕设', '毕业设计', '大作业', '简单点', '简单', '容易', '入门', '一点',
        '有点', '比较',
    ];
    return str_replace($fillers, ' ', (string) $q);
}

/** 中文没有空格，用 2-gram + 同义词 + 英文/型号词拼出检索词 */
function aiTerms($query) {
    $q = aiLower(trim(aiStripFiller((string) $query)));
    if ($q === '') return [];

    $terms = [];

    // 同义词先按短语匹配，权重高一些
    foreach (aiSynonyms() as $word => $canon) {
        if ($word !== '' && strpos($q, $word) !== false) {
            $terms[$canon] = max($terms[$canon] ?? 0, 2.2);
        }
    }

    // 英文与型号：stm32、esp32、k210、51、pid、wifi、4g、lora、rfid……
    if (preg_match_all('/[a-z][a-z0-9\.\+#\-]{1,20}|\d{2,4}/', $q, $m)) {
        foreach (array_unique($m[0]) as $w) {
            if (strlen($w) < 2) continue;
            $terms[$w] = max($terms[$w] ?? 0, 2.0);
        }
    }

    // 中文 2-gram
    if (preg_match_all('/[\x{4e00}-\x{9fa5}]+/u', $q, $m)) {
        foreach ($m[0] as $run) {
            $len = function_exists('mb_strlen') ? mb_strlen($run, 'UTF-8') : strlen($run);
            if ($len < 2) continue;
            if ($len <= 5) {
                $terms[$run] = max($terms[$run] ?? 0, 3.0);
            }
            for ($i = 0; $i + 1 < $len; $i++) {
                $bg = function_exists('mb_substr') ? mb_substr($run, $i, 2, 'UTF-8') : substr($run, $i, 2);
                if ($bg === '') continue;
                $terms[$bg] = max($terms[$bg] ?? 0, 1.4);
            }
        }
    }

    // 这些词在几乎所有标题里都有，命中也没有区分度
    $stop = [
        '基于', '设计', '实现', '系统', '的', '与', '和', '一个', '题目', '作品', '项目',
        '帮我', '推荐', '一下', '有没有', '什么', '哪个', '怎么', '如何', '可以', '想要',
        '我想', '需要', '应该', '适合', '请问', '你好', '谢谢', '做一', '做的', '能不',
        '不能', '能两', '周做', '个題', '个题', '选的', '个方', '方向',
    ];
    foreach ($stop as $w) unset($terms[$w]);

    return $terms;
}

/**
 * 检索题目。idf 加权：命中率超过六成的词（单片机、显示、报警）权重自动降低，
 * 避免「帮我找个单片机的题目」这种泛问把库里的高频词全捞出来。
 */
function aiSearchTopics($query, $limit = 8) {
    $topics = loadTopicIndex();
    $n = count($topics);
    if (!$n) return [];

    $terms = aiTerms($query);
    if (!$terms) return [];

    $df = [];
    foreach ($terms as $term => $w) {
        $count = 0;
        foreach ($topics as $t) {
            if (strpos($t['hay'], $term) !== false) $count++;
        }
        $df[$term] = $count;
    }

    $weighted = [];
    foreach ($terms as $term => $w) {
        $d = max(1, $df[$term]);
        if ($d === 0) continue;                       // 库里没有这个词，不参与打分
        $idf = log(1 + $n / $d);
        $weighted[$term] = $w * max(0.35, min(4.5, $idf));
    }
    if (!$weighted) return [];

    $qNorm = aiLower(preg_replace('/\s+/u', '', (string) $query));

    // 查询里明确有两个概念（有空格分隔）时，要求题目同时命中两个，
    // 否则「ESP32 门禁」会把只沾了 ESP32 的题目全捞进来。
    $need = (preg_match('/\s/u', trim((string) $query)) && count($weighted) >= 2) ? 2 : 1;

    $scored = [];
    foreach ($topics as $t) {
        $score = 0.0;
        $matched = 0;
        foreach ($weighted as $term => $w) {
            $sub = 0.0;
            if (strpos($t['titleLow'], $term) !== false) $sub += 9 * $w;
            if (strpos($t['stackLow'], $term) !== false) $sub += 3.5 * $w;
            if (strpos($t['hlLow'], $term) !== false) $sub += 2.5 * $w;
            if (strpos($t['catLow'], $term) !== false) $sub += 2 * $w;
            if ($sub > 0) {
                $matched++;
                $score += $sub;
            }
        }
        if ($score <= 0 || $matched < $need) continue;
        // 整句命中标题：说明用户几乎是照着题目问的
        $qLen = function_exists('mb_strlen') ? mb_strlen($qNorm, 'UTF-8') : strlen($qNorm);
        if ($qNorm !== '' && $qLen >= 5 && strpos(str_replace(' ', '', $t['titleLow']), $qNorm) !== false) {
            $score += 25;
        }
        $scored[] = ['t' => $t, 's' => $score];
    }
    if (!$scored) return [];

    usort($scored, function ($a, $b) {
        if ($a['s'] == $b['s']) return 0;
        return ($a['s'] < $b['s']) ? 1 : -1;
    });

    // 只沾到一个高频词的尾部结果直接丢掉，宁可少给几条
    $floor = $scored[0]['s'] * 0.35;
    $scored = array_values(array_filter($scored, function ($row) use ($floor) {
        return $row['s'] >= $floor;
    }));

    $out = [];
    $seen = [];
    foreach ($scored as $row) {
        $key = preg_replace('/\s+/u', '', $row['t']['titleLow']);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = [
            'no'         => $row['t']['no'],
            'title'      => $row['t']['title'],
            'stack'      => $row['t']['stack'],
            'highlights' => $row['t']['hl'],
            'category'   => $row['t']['category'],
            'score'      => round($row['s'], 1),
        ];
        if (count($out) >= $limit) break;
    }
    return $out;
}

/** 站内项目页（写死，数量少且稳定，比每次抓 HTML 靠谱） */
function aiSiteProjects() {
    return [
        [
            'title' => '智能电机控制板 MotoDrive',
            'url'   => 'product-motor-ctrl.html',
            'stack' => 'STM32G474 / 双 H 桥 / 编码器 / 中文 OLED',
            'desc'  => '在售板卡。正反转、编码器调速、固定输出、惯性减速、定时反转五种模式，PWM 1–5 kHz 可调，'
                     . '监测 500V / 48V / 5V 三路电压，兼容双 PWM、双 IO、PWM+方向三种 H 桥驱动，带急停保护、双路自动泄放与参数掉电保存。',
        ],
        [
            'title' => '多协议联网开发板',
            'url'   => 'product-esp32-board.html',
            'stack' => 'ESP32 / Wi-Fi + BLE / TF 卡 / MQTT',
            'desc'  => '在售板卡。Wi-Fi 与 BLE 双模，板载 TF 卡槽做本地存储，多路独立按键，IO 全引出，'
                     . '支持 MQTT / HTTP / WebSocket 等协议，Type-C 单线完成供电、烧录与串口日志。',
        ],
        [
            'title' => '舵机定时控制板 ServoTimer',
            'url'   => 'product-servo-ctrl.html',
            'stack' => 'CH32V003 / RTC / 锂电池管理 / 低功耗',
            'desc'  => '在售板卡。按预设时间自动驱动舵机，板载 RTC 独立走时与锂电池充放电管理，'
                     . '舵机电源独立通断，适合喂食器、定时开合、展示装置等长期值守场景，整板原理图随板提供。',
        ],
        [
            'title' => 'LightMesh 灯联网',
            'url'   => 'product-lightmesh.html',
            'stack' => 'ESP32-S3 / BLE Mesh / MQTT / Flutter / FastAPI / OTA',
            'desc'  => '设备联网系统，含 Mesh 灯控、门铃、云端设备影子与 OTA 升级，配套 Flutter App 与后端。',
        ],
        [
            'title' => '可视化智能门铃',
            'url'   => 'product-lightmesh-doorbell.html',
            'stack' => 'ESP32 / 摄像头 / MQTT / App',
            'desc'  => '按键触发拍照并上传，App 端实时查看画面并处理访客请求。',
        ],
        [
            'title' => '体感操控机械臂',
            'url'   => 'product-gesture-arm.html',
            'stack' => 'STM32 / MPU6050 / NRF24L01 / SG90 舵机',
            'desc'  => '体感手套采集六轴姿态，2.4G 无线传到机械臂，驱动多路舵机完成抬升、旋转与夹取。',
        ],
        [
            'title' => '嵌入式音频终端',
            'url'   => 'product-sound-core.html',
            'stack' => 'STM32 / 音频解码 / LCD 显示',
            'desc'  => '音频播放与控制终端，整合解码、显示和人机交互，用于教学演示与状态播报。',
        ],
        [
            'title' => '智能视觉追踪臂',
            'url'   => 'product-vision-tracker.html',
            'stack' => 'K210 / OpenMV / 云台 / 串口',
            'desc'  => 'K210 端侧识别目标并输出坐标，经串口驱动云台与机械臂实时跟随。',
        ],
    ];
}

function aiSearchProjects($query, $limit = 2) {
    $terms = aiTerms($query);
    if (!$terms) return [];

    // 这 5 个项目页全都写着 STM32，只靠通用主控词命中就返回，会出现
    // 「问温湿度、却推体感机械臂」这种驴唇不对马嘴的卡片。
    // 所以除通用词外，必须再命中至少一个内容词才算相关。
    $generic = ['stm32', 'esp32', 'k210', 'arduino', 'stm8', 'gd32', '单片机', '嵌入式', '微控制器', 'linux'];

    $out = [];
    foreach (aiSiteProjects() as $p) {
        $hay = aiLower($p['title'] . ' ' . $p['desc'] . ' ' . $p['stack']);
        $score = 0.0;
        $contentHit = false;
        foreach ($terms as $term => $w) {
            if ($term === '') continue;
            if (strpos($hay, $term) === false) continue;
            if (strpos(aiLower($p['title']), $term) !== false) $score += 6 * $w;
            else $score += 2 * $w;
            if (!in_array($term, $generic, true)) $contentHit = true;
        }
        if ($score > 0 && $contentHit) {
            $p['score'] = $score;
            $out[] = $p;
        }
    }
    usort($out, function ($a, $b) {
        if ($a['score'] == $b['score']) return 0;
        return ($a['score'] < $b['score']) ? 1 : -1;
    });
    return array_slice($out, 0, $limit);
}

/**
 * 纯寒暄 / 能力询问直接本地应答，不烧 token。
 * 之前「你好」也会走一遍检索 + 模型，既慢又答得机械（「暂未检索到与你好相关的题目」）。
 */
function aiGreetingReply($q) {
    $t = aiLower(trim((string) $q));
    $t = preg_replace('/[\s\x{3000}，。！？、,.!?~～]+/u', '', $t);
    if ($t === '' || textLength($t) > 8) return null;
    $greets = [
        '你好', '您好', '你好呀', '您好呀', '在吗', '在不在',
        'hi', 'hello', 'hey', '嗨', '哈喽', '哈啰',
        '早上好', '中午好', '下午好', '晚上好',
        '测试', 'test', '你是谁', '你能做什么', '你会什么', '怎么用',
    ];
    if (!in_array($t, $greets, true)) return null;
    return "你好，我是励磁实验室的站内助手，只根据站里的项目页和 699 个选题回答。\n\n"
        . "可以问：机器人 / 边缘 AI 项目的实现思路、手上主控适合做什么方向，或者直接让我从选题库里挑几条。";
}

function aiSystemPrompt() {
    return "你是一支硬件团队「励磁实验室」（lk-mcu.online）的站内助手。这支团队做机器人、边缘 AI 与智能硬件，"
        . "覆盖 STM32 / ESP32 / K210 全链路开发，并把项目整理成 AI 教程与创客开发实例。\n"
        . "你主要服务准备毕业设计、课程设计的本科生，也回答关于团队项目的技术问题。\n"
        . "你只能依据下面给出的站内信息回答，不要编造站外事实、价格、排名、就业数据或别人的项目。\n"
        . "回答要求：\n"
        . "1. 中文，先给结论再给理由，总长度控制在 350 字以内。\n"
        . "2. 推荐题目时从【题目】里挑 2-4 条，说清主控、关键模块和大概难度，句末用 [编号] 标注来源，例如 [2]。\n"
        . "3. 站内没有相关内容时，直接说「站内暂时没有这块内容」，然后给 1-2 条下一步建议（去哪个页面看、搜什么词、留言问什么）。\n"
        . "4. 需要列点时用短横线，不要用表格，不要输出标题层级。\n"
        . "5. 不要说「根据资料」「根据上下文」这类话，直接回答即可。\n";
}

function aiBuildMessages($question, $topics, $projects, $history) {
    $ctx = "【站点概况】一支做机器人、边缘 AI 与智能硬件的工程团队，覆盖 STM32 / ESP32 / K210 全链路自研，"
        . "同时输出 AI 教程与创客开发实例；"
        . "在售板卡三块：智能电机控制板（STM32G474）、多协议联网开发板（ESP32）、舵机定时控制板（CH32V003），原理图与固件齐全；"
        . "站内收录 699 个嵌入式/单片机/物联网毕业设计题目，分 16 个方向"
        . "（智慧农业、环境监测、医疗健康、智能家居、智慧交通、安防门禁、新能源电源、机器人、工业控制、智慧校园、宠物硬件、仪器仪表、娱乐文体、信息系统、AI 视觉等）；"
        . "另有 8 个项目详情页和一个留言板。题目库页面：thesis-topics.html，支持按方向筛选与关键词搜索；"
        . "板卡汇总在首页 index.html#boards。\n";

    if ($projects) {
        $ctx .= "\n【项目】\n";
        foreach ($projects as $i => $p) {
            $ctx .= '[P' . ($i + 1) . '] ' . $p['title'] . '（' . $p['url'] . '）技术栈：' . $p['stack'] . '；' . $p['desc'] . "\n";
        }
    }

    if ($topics) {
        $ctx .= "\n【题目】\n";
        foreach ($topics as $i => $t) {
            $ctx .= '[' . ($i + 1) . '] ' . $t['title']
                . '｜方向：' . $t['category']
                . '｜技术栈：' . $t['stack']
                . ($t['highlights'] !== '' ? '｜亮点：' . $t['highlights'] : '')
                . "\n";
        }
    } else {
        $ctx .= "\n【题目】本次没有检索到相关题目。\n";
    }

    $messages = [['role' => 'system', 'content' => aiSystemPrompt()]];

    if (is_array($history)) {
        foreach ($history as $h) {
            if (!is_array($h)) continue;
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($h['content'] ?? ''));
            if ($content === '') continue;
            $messages[] = ['role' => $role, 'content' => clipText($content, 500)];
        }
    }

    $messages[] = ['role' => 'user', 'content' => $ctx . "\n【用户问题】" . $question];
    return $messages;
}

/** 只保留最近 4 轮对话，且长度受限，防止有人用 history 塞 prompt injection */
function aiCleanHistory($raw) {
    if (!is_array($raw)) return [];
    $out = [];
    foreach (array_slice($raw, -6) as $h) {
        if (!is_array($h)) continue;
        $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $content = trim((string) ($h['content'] ?? ''));
        if ($content === '') continue;
        $out[] = ['role' => $role, 'content' => clipText($content, 400)];
    }
    return $out;
}

function aiHttpPost($url, $headers, $body, $timeout) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'lk-mcu-site/1.0',
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) return [0, '', $err !== '' ? $err : '网络请求失败'];
        return [$code, (string) $raw, ''];
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'timeout'       => $timeout,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return [0, '', '网络请求失败（服务器未启用 curl）'];
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return [$code, (string) $raw, ''];
}

/** 返回 [ok, text, error, tokens]；$conf 不为空时用它替代主配置（走备用通道） */
function aiCallModel($cfg, $messages, $maxTokens = null, $conf = null) {
    $conf = $conf === null ? $cfg : $conf;
    $url = rtrim((string) $conf['base_url'], '/') . '/chat/completions';
    $payload = [
        'model'       => $conf['model'],
        'messages'    => $messages,
        'temperature' => (float) $conf['temperature'],
        'max_tokens'  => $maxTokens === null ? (int) $conf['max_tokens'] : (int) $maxTokens,
        'stream'      => false,
    ];
    // 关掉思考模式：否则思考 token 会把 max_tokens 吃光，content 返回空字符串
    if (aiNeedsNoThinking($conf)) {
        $payload['thinking'] = ['type' => 'disabled'];
    }
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) return [false, '', '请求体编码失败', 0];
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    // 自建服务可能不配 Key，此时不要发一个空的 Authorization
    if (trim((string) $conf['api_key']) !== '') {
        $headers[] = 'Authorization: Bearer ' . $conf['api_key'];
    }

    $timeout = aiIsLocalUrl((string) $conf['base_url']) ? AI_TIMEOUT_LOCAL : AI_TIMEOUT;
    list($code, $raw, $err) = aiHttpPost($url, $headers, $body, $timeout);
    if ($err !== '') return [false, '', $err, 0];

    $json = json_decode($raw, true);
    if ($code >= 200 && $code < 300 && is_array($json)) {
        $choice = $json['choices'][0] ?? [];
        $msg    = $choice['message'] ?? [];
        $text   = (string) ($msg['content'] ?? '');
        $reason = (string) ($msg['reasoning_content'] ?? '');
        $finish = (string) ($choice['finish_reason'] ?? '');
        $tokens = (int) ($json['usage']['total_tokens'] ?? 0);
        $rt     = (int) ($json['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0);

        if (trim($text) !== '') return [true, trim($text), '', $tokens];

        // content 空：优先怀疑思考模式吃光了额度，这比「空内容」有用得多
        if (trim($reason) !== '' || $rt > 0) {
            return [false, '', '模型把 ' . max($rt, 1) . ' 个 token 全用在思考上了，没留下正文。'
                . '后台把模型换成 glm-4-flash，或把 max_tokens 调到 1500 以上再试', $tokens];
        }
        if ($finish === 'length') {
            return [false, '', '回答被 max_tokens 截断，请把上限调大（当前 '
                . ($maxTokens === null ? (int) $conf['max_tokens'] : (int) $maxTokens) . '）', $tokens];
        }
        if ($finish === 'sensitive' || $finish === 'content_filter') {
            return [false, '', '这个问题被模型的内容安全策略拦下了，换个问法试试', $tokens];
        }
        return [false, '', '模型返回了空内容（finish_reason=' . ($finish !== '' ? $finish : 'unknown') . '）', $tokens];
    }

    $detail = '';
    if (is_array($json)) {
        $detail = (string) ($json['error']['message'] ?? ($json['message'] ?? ''));
    }
    if ($detail === '') $detail = '接口返回 HTTP ' . $code;

    if ($code === 401) $detail = 'API Key 无效或已过期';
    elseif ($code === 429) $detail = '上游模型排队或限流了，稍等几秒再试';
    elseif ($code === 404) $detail = '模型名不存在（' . $conf['model'] . '），请在后台换一个';
    elseif ($code === 0) $detail = '连不上接口地址（服务没启动或端口不对）';
    elseif ($code >= 500) $detail = '模型服务暂时不可用，请稍后再试';

    return [false, '', $detail, 0];
}

/**
 * 主通道优先，失败自动降级到自建备用通道。
 * 返回 [ok, text, error, tokens, via]，via 为 'primary' | 'fallback'
 */
function aiCallWithFallback($cfg, $messages) {
    // 主通道也可能就是本地 Qwen3，同样要关思考链，否则 900 token 全被它吃掉
    $primaryMsgs = aiIsLocalUrl((string) $cfg['base_url']) ? aiNoThink($messages) : $messages;
    list($ok, $text, $err, $tokens) = aiCallModel($cfg, $primaryMsgs);
    if ($ok) return [true, aiStripThink($text), '', $tokens, 'primary'];

    $fb = aiFallbackConfig($cfg);
    if ($fb === null) return [false, '', $err, 0, 'primary'];

    // 本地这类小模型（Qwen3 系）默认会先输出思考链，900 token 全被它吃掉，
    // 用户看到的反而是一个「...」。加 /no_think 关掉，再兜底清理残留。
    list($ok2, $text2, $err2, $tok2) = aiCallModel($cfg, aiNoThink($messages), null, $fb);
    if ($ok2) return [true, aiStripThink($text2), '', $tok2, 'fallback'];

    return [false, '', $err . '；本地备用也没起来：' . $err2, 0, 'primary'];
}

/** 给 system 消息补一个 /no_think（Qwen3 系认这个开关） */
function aiNoThink($messages) {
    foreach ($messages as $i => $m) {
        if (($m['role'] ?? '') === 'system') {
            $messages[$i]['content'] = rtrim((string) $m['content']) . "\n/no_think";
            return $messages;
        }
    }
    array_unshift($messages, ['role' => 'system', 'content' => '/no_think']);
    return $messages;
}

/** 清掉  thinking...<｜end▁of▁thinking｜> 残留，避免前端直接把思考过程显示给用户 */
function aiStripThink($text) {
    $text = preg_replace('/\s*<think\b[^>]*>.*?<\/think>\s*/su', '', (string) $text);
    return trim((string) $text);
}

// ─────────── 路由 ───────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // 登录验证
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $pwd = (string) ($input['password'] ?? '');

        checkLoginLimit();

        if ($pwd === '') {
            respond(['success' => false, 'message' => '请输入密码'], 400);
        }

        $result = verifyPassword($pwd);
        if ($result === null) {
            respond([
                'success' => false,
                'message' => '后台密码未初始化，请在服务器执行：php tools/set-admin-password.php',
            ], 503);
        }
        if ($result === false) {
            recordLoginFail();
            respond(['success' => false, 'message' => '密码错误'], 401);
        }

        clearLoginFail();
        respond(['success' => true, 'token' => issueToken(), 'expiresIn' => TOKEN_TTL]);
        break;

    // 退出登录（吊销当前 token）
    case 'logout':
        revokeToken(bearerToken());
        respond(['success' => true]);
        break;

    // 验证 token
    case 'verify':
        if (verifyToken(bearerToken())) {
            respond(['valid' => true]);
        }
        respond(['valid' => false], 401);
        break;

    // 获取产品列表（公开）
    case 'list':
        respond(['products' => getProducts()]);
        break;

    // 添加产品（需认证）
    case 'add':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $product = buildProduct($input);
        if ($product['name'] === '') {
            respond(['success' => false, 'message' => '产品名称不能为空'], 400);
        }
        $product['id'] = generateId();
        $product['createdAt'] = date('c');
        $product['updatedAt'] = date('c');
        $products = getProducts();
        array_unshift($products, $product);
        saveProducts($products);
        respond(['success' => true, 'product' => $product]);
        break;

    // 更新产品（需认证）
    case 'update':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = $input['id'] ?? '';
        $products = getProducts();
        $found = false;
        foreach ($products as &$p) {
            if ($p['id'] === $id) {
                $updated = buildProduct($input);
                $updated['id'] = $id;
                $updated['createdAt'] = $p['createdAt'] ?? date('c');
                $updated['updatedAt'] = date('c');
                // 保留已有图片（如果前端未修改）
                if (empty($updated['images']) && !empty($p['images'])) {
                    $updated['images'] = $p['images'];
                }
                $p = $updated;
                $found = true;
                break;
            }
        }
        unset($p);
        if (!$found) respond(['success' => false, 'message' => '产品不存在'], 404);
        saveProducts($products);
        respond(['success' => true]);
        break;

    // 删除产品（需认证）
    case 'delete':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = $input['id'] ?? '';
        $products = getProducts();
        foreach ($products as $p) {
            if ($p['id'] === $id && !empty($p['images'])) {
                foreach ($p['images'] as $img) {
                    safeUnlinkUpload($img);
                }
            }
        }
        $newProducts = array_values(array_filter($products, function ($p) use ($id) {
            return $p['id'] !== $id;
        }));
        saveProducts($newProducts);
        respond(['success' => true]);
        break;

    // 上传图片（需认证）
    case 'upload':
        requireAuth();
        if (empty($_FILES['image'])) {
            respond(['success' => false, 'message' => '未收到文件'], 400);
        }
        $file = $_FILES['image'];
        if (!empty($file['error'])) {
            respond(['success' => false, 'message' => '文件上传失败（错误码 ' . (int) $file['error'] . '）'], 400);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            respond(['success' => false, 'message' => '非法的上传请求'], 400);
        }
        if ($file['size'] > MAX_UPLOAD_BYTES) {
            respond(['success' => false, 'message' => '图片不能超过 20MB'], 400);
        }
        // 用文件真实内容判断类型，不信任客户端传来的 MIME / 文件名
        $ext = imageExt($file['tmp_name']);
        if ($ext === null) {
            respond(['success' => false, 'message' => '仅支持 JPG / PNG / GIF / WEBP 图片'], 400);
        }
        $filename = generateId() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
            respond(['success' => true, 'url' => 'uploads/' . $filename]);
        }
        respond(['success' => false, 'message' => '上传失败'], 500);
        break;

    // 修改密码（需认证）
    case 'change_password':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $oldPwd = (string) ($input['oldPassword'] ?? '');
        $newPwd = (string) ($input['newPassword'] ?? '');

        if (textLength($newPwd) < 8) {
            respond(['success' => false, 'message' => '新密码至少 8 位'], 400);
        }
        if (verifyPassword($oldPwd) !== true) {
            recordLoginFail();
            respond(['success' => false, 'message' => '当前密码错误'], 400);
        }
        setPassword($newPwd);
        // 改密后让所有旧会话失效
        writeSecret(TOKENS_FILE, []);
        respond(['success' => true, 'relogin' => true]);
        break;

    // 导出数据
    case 'export':
        requireAuth();
        respond(['products' => getProducts()]);
        break;

    // 导入数据（需认证）
    case 'import':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $data = $input['products'] ?? [];
        if (!is_array($data)) respond(['success' => false, 'message' => '格式错误'], 400);
        saveProducts($data);
        respond(['success' => true, 'count' => count($data)]);
        break;

    // 清空数据（需认证）
    case 'clear':
        requireAuth();
        saveProducts([]);
        respond(['success' => true]);
        break;

    // 获取留言（公开，最多 50 条，新的在前）
    case 'list_messages':
        $all = getMessages();
        $out = [];
        foreach (array_slice($all, 0, 50) as $m) {
            $out[] = publicMessage($m);
        }
        respond(['success' => true, 'messages' => $out, 'total' => count($all)]);
        break;

    // 提交留言（公开，带频率限制与蜜罐）
    case 'add_message':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        // 蜜罐字段：真人看不到，机器人会填。填了就假装成功但不入库。
        if (!empty($input['website'])) {
            respond(['success' => true, 'message' => '留言已提交']);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $body = trim((string) ($input['content'] ?? ''));
        $name = clipText(preg_replace('/\s+/u', ' ', $name), MSG_NAME_MAX);
        $body = clipText($body, MSG_BODY_MAX);

        if ($name === '') {
            respond(['success' => false, 'message' => '请填写昵称'], 400);
        }
        if (textLength($body) < 2) {
            respond(['success' => false, 'message' => '留言内容太短了'], 400);
        }
        if (looksLikeSpam($body)) {
            respond(['success' => false, 'message' => '留言内容包含过多链接，已拒绝'], 400);
        }

        $limited = messageRateLimit();
        if ($limited !== null) {
            respond(['success' => false, 'message' => $limited], 429);
        }

        $message = [
            'id'        => generateId(),
            'name'      => $name,
            'content'   => $body,
            'createdAt' => date('c'),
        ];

        $messages = getMessages();
        array_unshift($messages, $message);
        if (count($messages) > MSG_MAX_KEEP) {
            $messages = array_slice($messages, 0, MSG_MAX_KEEP);
        }
        if (!saveMessages($messages)) {
            respond(['success' => false, 'message' => '留言保存失败，请稍后再试'], 500);
        }
        respond(['success' => true, 'message' => publicMessage($message)]);
        break;

    // 删除留言（需认证）
    case 'delete_message':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = (string) ($input['id'] ?? '');
        if ($id === '') {
            respond(['success' => false, 'message' => '缺少留言 ID'], 400);
        }
        $messages = getMessages();
        $left = array_values(array_filter($messages, function ($m) use ($id) {
            return ($m['id'] ?? '') !== $id;
        }));
        if (count($left) === count($messages)) {
            respond(['success' => false, 'message' => '留言不存在'], 404);
        }
        saveMessages($left);
        respond(['success' => true, 'deleted' => $id]);
        break;

    // ─────────── AI 站内助手 ───────────

    // 助手状态（公开）：前端据此决定是否渲染悬浮入口
    case 'ai_status':
        $cfg = aiConfig();
        $usage = aiUsage();
        $used = aiIpUsed($usage)['n'];
        respond([
            'success' => true,
            'enabled' => aiReady($cfg),
            'model'   => $cfg['model'],
            'perIp'   => (int) $cfg['per_ip'],
            'used'    => $used,
            'left'    => max(0, (int) $cfg['per_ip'] - $used),
            'presets' => [
                '我只有 STM32 和几个常用传感器，帮我挑个两周能做完的题目',
                '有没有和智能家居、语音控制相关的题目？',
                'LightMesh 灯联网的 OTA 是怎么做的？',
                '答辩时老师一般会问什么？怎么讲工作量？',
            ],
        ]);
        break;

    // 提问（公开，带限流与缓存）
    case 'ai_chat':
        // 自建模型 CPU 推理慢，别被 PHP 默认 30 秒执行上限掐断
        @set_time_limit(AI_TIMEOUT_LOCAL + 30);
        $cfg = aiConfig();
        if (!aiReady($cfg)) {
            respond(['success' => false, 'message' => '站内助手还没有开启'], 503);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $question = trim((string) ($input['question'] ?? ''));
        $question = preg_replace('/\s+/u', ' ', $question);
        if ($question === '') {
            respond(['success' => false, 'message' => '请先输入问题'], 400);
        }
        if (textLength($question) > AI_MAX_QUESTION) {
            $question = clipText($question, AI_MAX_QUESTION);
        }

        $limited = aiQuotaError($cfg);
        if ($limited !== null) {
            respond(['success' => false, 'message' => $limited], 429);
        }

        // 寒暄不值得花一次模型调用
        $greeting = aiGreetingReply($question);
        if ($greeting !== null) {
            aiUsageBump(0, false);
            respond([
                'success' => true,
                'answer'  => $greeting,
                'sources' => [],
                'via'     => 'local',
            ]);
        }

        $history = aiCleanHistory($input['history'] ?? []);

        // 追问很短（「那第二个呢」）时把上一轮问题带上一起检索，否则什么也搜不到
        $searchQuery = $question;
        if (count(aiTerms($question)) < 2) {
            foreach (array_reverse($history) as $h) {
                if ($h['role'] === 'user') {
                    $searchQuery = $h['content'] . ' ' . $question;
                    break;
                }
            }
        }

        $topics = aiSearchTopics($searchQuery, AI_CONTEXT_TOPICS);
        $projects = aiSearchProjects($searchQuery, 2);

        $sources = [];
        foreach ($projects as $i => $p) {
            $sources[] = [
                'cite'  => 'P' . ($i + 1),
                'type'  => 'project',
                'title' => $p['title'],
                'url'   => $p['url'],
                'meta'  => $p['stack'],
            ];
        }
        foreach ($topics as $i => $t) {
            $sources[] = [
                'cite'  => (string) ($i + 1),
                'type'  => 'topic',
                'title' => $t['title'],
                'url'   => 'thesis-topics.html?q=' . rawurlencode($t['title']),
                'meta'  => $t['category'] . ' · ' . $t['no'],
            ];
        }

        $nos = [];
        foreach ($topics as $t) $nos[] = $t['no'];
        $cacheKey = hash('sha256', $cfg['model'] . '|' . aiLower($question) . '|' . implode(',', $nos));

        $cached = aiCacheGet($cacheKey);
        if ($cached !== null) {
            aiUsageBump(0, true);
            respond([
                'success' => true,
                'answer'  => $cached['answer'],
                'sources' => $sources,
                'cached'  => true,
            ]);
        }

        list($ok, $answer, $error, $tokens, $via) = aiCallWithFallback(
            $cfg,
            aiBuildMessages($question, $topics, $projects, $history)
        );

        if (!$ok) {
            respond(['success' => false, 'message' => $error], 502);
        }

        aiUsageBump($tokens, false);
        aiCacheSet($cacheKey, $answer);

        respond([
            'success' => true,
            'answer'  => $answer,
            'sources' => $sources,
            'tokens'  => $tokens,
            'via'     => $via,
        ]);
        break;

    // 读取助手配置（需认证，Key 只返回掩码）
    case 'get_ai_config':
        requireAuth();
        $cfg = aiConfig();
        $raw = readSecret(AI_CONFIG_FILE, []);
        $savedKey = is_array($raw) ? trim((string) ($raw['api_key'] ?? '')) : '';
        $savedFb  = is_array($raw) ? trim((string) ($raw['fallback_key'] ?? '')) : '';
        respond([
            'success'     => true,
            'enabled'     => (bool) $cfg['enabled'],
            'model'       => $cfg['model'],
            'baseUrl'     => $cfg['base_url'],
            'perIp'       => (int) $cfg['per_ip'],
            'perTotal'    => (int) $cfg['per_total'],
            'temperature' => (float) $cfg['temperature'],
            'maxTokens'   => (int) $cfg['max_tokens'],
            'hasKey'      => $savedKey !== '',
            'keyMask'     => aiMaskKey($savedKey),
            'keyFromEnv'  => $savedKey === '' && trim((string) $cfg['api_key']) !== '',
            'models'      => aiModels(),
            'usage'       => aiPublicUsage(),
            'fallbackEnabled' => (bool) $cfg['fallback_enabled'],
            'fallbackUrl'     => (string) $cfg['fallback_url'],
            'fallbackModel'   => (string) $cfg['fallback_model'],
            'fallbackHasKey'  => $savedFb !== '',
            'fallbackKeyMask' => aiMaskKey($savedFb),
        ]);
        break;

    // 保存助手配置（需认证）
    case 'set_ai_config':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $cur = readSecret(AI_CONFIG_FILE, []);
        if (!is_array($cur)) $cur = [];

        $model = trim((string) ($input['model'] ?? ($cur['model'] ?? 'glm-4.7-flash')));
        if (!preg_match('/^[A-Za-z0-9._:\-]{3,60}$/', $model)) {
            respond(['success' => false, 'message' => '模型名格式不正确'], 400);
        }

        $baseUrl = trim((string) ($input['baseUrl'] ?? ($cur['base_url'] ?? 'https://open.bigmodel.cn/api/paas/v4')));
        if (!aiUrlAllowed($baseUrl)) {
            respond(['success' => false, 'message' => '接口地址不合法：云端要 https://，自建只允许 http://127.0.0.1 或内网地址'], 400);
        }
        $isLocal = aiIsLocalUrl($baseUrl);

        $key = trim((string) ($input['apiKey'] ?? ''));
        if ($key === '') {
            $key = trim((string) ($cur['api_key'] ?? ''));
        } elseif (!preg_match('/^[A-Za-z0-9._\-]{8,200}$/', $key)) {
            respond(['success' => false, 'message' => 'API Key 看起来不对（只允许字母数字和 . _ -，至少 8 位）'], 400);
        }

        $enabled = !empty($input['enabled']);
        if ($enabled && $key === '' && !$isLocal) {
            respond(['success' => false, 'message' => '先填 API Key 再启用'], 400);
        }

        // ── 备用通道（自建模型）──
        $fbEnabled = !empty($input['fallbackEnabled']);
        $fbUrl   = trim((string) ($input['fallbackUrl'] ?? ($cur['fallback_url'] ?? '')));
        $fbModel = trim((string) ($input['fallbackModel'] ?? ($cur['fallback_model'] ?? '')));
        $fbKey   = trim((string) ($input['fallbackKey'] ?? ''));
        if ($fbKey === '') {
            $fbKey = trim((string) ($cur['fallback_key'] ?? ''));
        } elseif (!preg_match('/^[A-Za-z0-9._\-]{8,200}$/', $fbKey)) {
            respond(['success' => false, 'message' => '备用通道的 Key 格式不对'], 400);
        }
        if ($fbEnabled) {
            if ($fbUrl === '' || $fbModel === '') {
                respond(['success' => false, 'message' => '启用备用通道前，要填自建服务的地址和模型名'], 400);
            }
            if (!aiUrlAllowed($fbUrl)) {
                respond(['success' => false, 'message' => '备用地址不合法：只能是 http://127.0.0.1:端口/v1 或内网地址'], 400);
            }
            if (!preg_match('/^[A-Za-z0-9._:\-]{3,60}$/', $fbModel)) {
                respond(['success' => false, 'message' => '备用模型名格式不正确'], 400);
            }
        }

        $next = [
            'enabled'     => $enabled,
            'api_key'     => $key,
            'model'       => $model,
            'base_url'    => rtrim($baseUrl, '/'),
            'per_ip'      => max(1, min(200, (int) ($input['perIp'] ?? 30))),
            'per_total'   => max(10, min(20000, (int) ($input['perTotal'] ?? 800))),
            'temperature' => max(0, min(1.2, (float) ($input['temperature'] ?? 0.6))),
            'max_tokens'  => max(120, min(2000, (int) ($input['maxTokens'] ?? 900))),
            'fallback_enabled' => $fbEnabled,
            'fallback_url'     => rtrim($fbUrl, '/'),
            'fallback_key'     => $fbKey,
            'fallback_model'   => $fbModel,
            'updatedAt'   => date('c'),
        ];

        if (!writeSecret(AI_CONFIG_FILE, $next)) {
            respond(['success' => false, 'message' => '保存失败：data/ 目录不可写，请检查属主与权限'], 500);
        }
        respond([
            'success'  => true,
            'enabled'  => $next['enabled'],
            'model'    => $next['model'],
            'fallback' => $next['fallback_enabled'],
        ]);
        break;

    // 测试连通性（需认证）
    case 'ai_test':
        requireAuth();
        $cfg = aiConfig();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $useFb = !empty($input['useFallback']);
        if ($useFb) {
            $fb = [
                'base_url'    => trim((string) ($input['fallbackUrl'] ?? ($cfg['fallback_url'] ?? ''))),
                'api_key'     => trim((string) ($input['fallbackKey'] ?? '')) ?: trim((string) ($cfg['fallback_key'] ?? '')),
                'model'       => trim((string) ($input['fallbackModel'] ?? ($cfg['fallback_model'] ?? ''))),
                'temperature' => $cfg['temperature'],
                'max_tokens'  => 120,
            ];
            if ($fb['base_url'] === '' || $fb['model'] === '') {
                respond(['success' => false, 'message' => '备用通道还没填地址或模型名'], 400);
            }
            if (!aiUrlAllowed($fb['base_url'])) {
                respond(['success' => false, 'message' => '地址不合法'], 400);
            }
            $cfg = array_merge($cfg, $fb);
        } else {
            if (trim((string) ($input['apiKey'] ?? '')) !== '') {
                $cfg['api_key'] = trim((string) $input['apiKey']);
            }
            if (trim((string) ($input['baseUrl'] ?? '')) !== '') {
                if (!aiUrlAllowed((string) $input['baseUrl'])) {
                    respond(['success' => false, 'message' => '地址不合法'], 400);
                }
                $cfg['base_url'] = rtrim(trim((string) $input['baseUrl']), '/');
            }
            $m = trim((string) ($input['model'] ?? ''));
            if ($m !== '' && preg_match('/^[A-Za-z0-9._:\-]{3,60}$/', $m)) {
                $cfg['model'] = $m;
            }
            if (trim((string) $cfg['api_key']) === '' && !aiIsLocalUrl((string) $cfg['base_url'])) {
                respond(['success' => false, 'message' => '还没有填 API Key'], 400);
            }
        }

        @set_time_limit(AI_TIMEOUT_LOCAL + 30);

        $start = microtime(true);
        list($ok, $text, $error, $tokens) = aiCallModel(
            $cfg,
            [
                ['role' => 'system', 'content' => '你是连通性测试助手，只回复 OK 两个字母。'],
                ['role' => 'user', 'content' => 'ping'],
            ],
            16
        );
        $ms = (int) round((microtime(true) - $start) * 1000);

        if (!$ok) {
            respond(['success' => false, 'message' => $error, 'model' => $cfg['model'], 'ms' => $ms], 502);
        }
        respond([
            'success'  => true,
            'message'  => '连接成功',
            'reply'    => clipText(aiStripThink($text), 60),
            'model'    => $cfg['model'],
            'ms'       => $ms,
            'tokens'   => $tokens,
        ]);
        break;

    // 获取 Hero 图片（公开）
    case 'get_hero':
        $hero = readJson(DATA_DIR . '/hero.json', []);
        respond(['success' => true, 'images' => $hero['images'] ?? []]);
        break;

    // 保存 Hero 图片（需认证）
    case 'set_hero':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        writeJson(DATA_DIR . '/hero.json', ['images' => $input['images'] ?? []]);
        respond(['success' => true]);
        break;

    default:
        respond([
            'message' => 'LICI LAB API',
            'actions' => ['list', 'login', 'logout', 'verify', 'add', 'update', 'delete', 'upload', 'get_hero', 'set_hero', 'list_messages', 'add_message', 'delete_message', 'ai_status', 'ai_chat', 'get_ai_config', 'set_ai_config', 'ai_test'],
        ]);
}

// ─────────── 辅助函数 ───────────
function buildProduct($input) {
    return [
        'name'        => trim($input['name'] ?? ''),
        'subtitle'    => trim($input['subtitle'] ?? ''),
        'category'    => trim($input['category'] ?? '其他'),
        'tags'        => is_array($input['tags'] ?? null) ? array_slice($input['tags'], 0, 20) : [],
        'description' => trim($input['description'] ?? ''),
        'specs'       => trim($input['specs'] ?? ''),
        'level'       => in_array($input['level'] ?? '', ['featured', 'normal'], true) ? $input['level'] : 'normal',
        'images'      => is_array($input['images'] ?? null) ? array_slice($input['images'], 0, 10) : [],
    ];
}
