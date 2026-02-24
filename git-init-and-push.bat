@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"

echo ================================
echo Folio 主题 - 初始化并推送到 GitHub
echo ================================
echo.

if not exist .git (
    echo [1/4] 初始化 Git 仓库...
    git init
    if errorlevel 1 (echo 错误: git init 失败 & pause & exit /b 1)
    echo 已创建 .git
) else (
    echo [1/4] 已是 Git 仓库，跳过 init
)

echo.
echo [2/4] 添加文件（.gitignore 已排除 node_modules 等）...
git add .
if errorlevel 1 (echo 错误: git add 失败 & pause & exit /b 1)

echo.
echo [3/4] 提交...
git commit -m "Folio theme initial commit"
if errorlevel 1 (
    echo 提示: 若无变更或已提交过，可忽略上述错误
    echo 直接执行下一步：添加远程并推送
)

echo.
echo [4/4] 远程与推送
echo.
echo 请先在 GitHub 上新建一个空仓库（不要勾选 README/.gitignore），
echo 记下仓库地址，例如：https://github.com/你的用户名/folio.git
echo.
set /p REMOTE_URL=请输入 GitHub 仓库地址（留空则跳过）: 
if "%REMOTE_URL%"=="" (
    echo 未输入地址。请稍后手动执行：
    echo   git remote add origin https://github.com/你的用户名/folio.git
    echo   git branch -M main
    echo   git push -u origin main
    goto :done
)

git remote remove origin 2>nul
git remote add origin "%REMOTE_URL%"
git branch -M main
echo 正在推送到 origin main ...
git push -u origin main
if errorlevel 1 (
    echo 推送失败。若需登录，请使用 GitHub CLI 或配置 SSH/HTTPS 凭据后重试。
) else (
    echo 推送完成。
)

:done
echo.
echo ================================
pause
