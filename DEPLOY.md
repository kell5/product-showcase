# 🚀 自动部署指南

## 整体流程

```
你写代码 → git push → GitHub → GitHub Actions → 自动同步到服务器
```

换电脑只需要：`git clone` → 写代码 → `git push`，**零配置部署**。

---

## 第一步：开通服务器 SSH 访问 ⚠️ 必须先做

当前 SSH 端口 22 被阿里云安全组挡住了，需要先放行：

1. 登录 [阿里云控制台](https://ecs.console.aliyun.com/)
2. 找到你的 ECS 实例（`114.55.208.72`）
3. 点击「安全组」→ 「配置规则」→ 「入方向」→ 「添加安全组规则」
4. 设置：
   - 协议类型：**SSH（22）**
   - 授权对象：**0.0.0.0/0**
   - 优先级：**100**
5. 保存后等 1-2 分钟

验证：在终端执行 `ssh -i ~/.ssh/lk_mcu_sftp.pem root@114.55.208.72`

---

## 第二步：在 GitHub 创建仓库并推送

1. 打开 [github.com](https://github.com)，登录你的账号
2. 右上角 `+` → **New repository**
3. 仓库名：`product-showcase`（或你喜欢的名字）
4. 选 **Public** 或 **Private** 都可以
5. **不要勾选** README、.gitignore、license
6. 点击 **Create repository**

7. **建议先改一下 git 邮箱为你的 GitHub 邮箱**（让提交记录显示你的名字）：
   ```bash
   git config user.email "你的GitHub邮箱@example.com"
   git config user.name "你的GitHub用户名"
   ```

8. 推送代码：
   ```bash
   git remote add origin https://github.com/你的用户名/product-showcase.git
   git push -u origin main
   ```

---

## 第三步：配置 GitHub Secrets（存服务器登录信息）

代码推送成功后，去 GitHub 仓库页面设置自动部署的密钥：

1. 打开你的仓库 → **Settings** → **Secrets and variables** → **Actions**
2. 点击 **New repository secret**，添加以下 3 个：

| Secret 名称 | 值 | 说明 |
|---|---|---|
| `SSH_HOST` | `114.55.208.72` | 服务器 IP |
| `SSH_USER` | `root` | SSH 登录用户名 |
| `SSH_PRIVATE_KEY` | 你的私钥内容（见下方） | SSH 密钥 |

> 选填：`SSH_PORT` — 如果你改了 SSH 端口，填端口号（默认 22 不需要填）

### 获取 SSH_PRIVATE_KEY 的内容

在电脑上执行：
```bash
cat /c/Users/user/.ssh/lk_mcu_sftp.pem
```

把输出的内容（从 `-----BEGIN RSA PRIVATE KEY-----` 到 `-----END RSA PRIVATE KEY-----`）完整复制，粘贴到 GitHub 的 Secret 中。

---

## 第四步：测试自动部署

修改任意文件（比如 `index.html`），然后：
```bash
git add .
git commit -m "测试自动部署"
git push
```

推送后，去 GitHub 仓库的 **Actions** 选项卡，就能看到部署任务在运行。绿色 ✅ 表示部署成功。

以后每次 `git push` 到 `main` 分支，都会自动部署到服务器。

---

## 🖥 换电脑工作流程

新电脑上只需要 3 步，不用配任何 SFTP：

```bash
# 1. 安装 Git
# 2. 克隆代码
git clone https://github.com/你的用户名/product-showcase.git
cd product-showcase

# 3. 愉快的写代码，改完后推送
git add .
git commit -m "改了些东西"
git push
```

> ⚠️ **重要**：第一次在新电脑上 push 可能需要配置 GitHub 认证。
> GitHub 从 2021 年起不再支持密码认证，推荐用以下方式：

### 方式 A：Personal Access Token（推荐）
1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. 勾选 `repo` 权限，生成 token（例如 `ghp_xxxxxxxxxxxx`）
3. 推送时用户名填你的 GitHub 用户名，密码填这个 token

### 方式 B：GitHub CLI
```bash
winget install GitHub.cli    # Windows
gh auth login                 # 登录
# 之后 push 就不用输密码了
```

---

## 对比：SFTP vs Git 自动部署

| 项目 | 旧方案（SFTP） | ✅ 新方案（Git Actions） |
|---|---|---|
| 换电脑配置 | 重装 VS Code 插件、配密钥路径 | **只需 git clone** |
| 部署速度 | 手动拖文件，容易漏 | 自动推送，全量同步 |
| 版本管理 | ❌ 没有 | ✅ 有 Git 历史 |
| 多人协作 | ❌ 不支持 | ✅ 支持 |
| 回滚 | ❌ 手动替换文件 | ✅ `git revert` |
| 安全性 | 密钥在本机 | 密钥存在 GitHub（加密） |

---

## 疑难解答

**Q: GitHub Actions 运行失败怎么办？**
A: 去仓库 Actions 标签页查看报错日志。常见原因：
- SSH 端口未开放 → 检查阿里云安全组
- SSH_PRIVATE_KEY 格式不对 → 确认包含完整的 `-----BEGIN...END-----`

**Q: 图片文件很大，GitHub 能存吗？**
A: 单个文件 < 50MB 没问题。你现有的图片最大 5MB，OK 的。

**Q: 服务器上的 data/products.json 会被覆盖吗？**
A: 不会，`.gitignore` 已经排除了 `data/products.json`，它只存在于服务器上。
