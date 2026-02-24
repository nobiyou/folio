# 将 Folio 主题推送到 GitHub

## 一、在 GitHub 上新建仓库

1. 登录 [GitHub](https://github.com)，点击右上角 **+** → **New repository**。
2. **Repository name**：例如 `folio` 或 `folio-theme`。
3. **Description**（可选）：例如 `WordPress theme for designers and creators`。
4. 选择 **Public**。
5. **不要**勾选 “Add a README file”、“Add .gitignore”、“Choose a license”（保持空仓库）。
6. 点击 **Create repository**。
7. 记下仓库地址，例如：
   - HTTPS: `https://github.com/你的用户名/folio.git`
   - SSH: `git@github.com:你的用户名/folio.git`

## 二、本地执行（二选一）

### 方式 A：用脚本（推荐）

在主题根目录双击运行：

```
git-init-and-push.bat
```

按提示输入上一步的仓库地址，脚本会：初始化仓库（若未初始化）、添加文件、提交、添加远程并推送到 `main`。

### 方式 B：手动命令

在主题根目录打开命令行（PowerShell 或 CMD），执行：

```bash
# 若尚未初始化
git init

# 添加所有文件（.gitignore 会排除 node_modules 等）
git add .
git commit -m "Folio theme initial commit"

# 添加远程（替换为你的仓库地址）
git remote add origin https://github.com/你的用户名/folio.git

# 推送到 main
git branch -M main
git push -u origin main
```

## 三、首次推送若失败

- **HTTPS 推送**：可能提示登录。可使用 [GitHub CLI](https://cli.github.com/) 执行 `gh auth login`，或使用 Personal Access Token 作为密码。
- **SSH 推送**：若使用 `git@github.com:...`，需先配置 [SSH 密钥](https://docs.github.com/cn/authentication/connecting-to-github-with-ssh)。

## 四、之后更新代码

```bash
git add .
git commit -m "描述本次修改"
git push
```

完成以上步骤后，主题代码就会在 GitHub 上形成独立仓库并可继续协作或发布。
