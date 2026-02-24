@echo off
chcp 65001 >nul
cd /d "%~dp0"

echo ================================
echo 合并到 main 并推送到 GitHub
echo ================================
echo.

if not exist .git (
    echo 错误: 当前目录不是 Git 仓库。
    pause
    exit /b 1
)

set CURRENT_BRANCH=
for /f "tokens=*" %%a in ('git rev-parse --abbrev-ref HEAD 2^>nul') do set CURRENT_BRANCH=%%a
echo 当前分支: %CURRENT_BRANCH%
echo.

if "%CURRENT_BRANCH%"=="main" (
    echo 已在 main 分支。先提交未提交的更改，再推送。
    git add .
    git status -s
    set /p DO_COMMIT=是否有未提交更改需要提交？[y/N]: 
    if /i "%DO_COMMIT%"=="y" (
        set /p COMMIT_MSG=请输入提交说明: 
        if "%COMMIT_MSG%"=="" set COMMIT_MSG=Update
        git commit -m "%COMMIT_MSG%"
    )
    echo.
    echo 推送到 origin main ...
    git push -u origin main
    goto :done
)

echo 将把分支 "%CURRENT_BRANCH%" 合并到 main 并推送。
echo.
set /p CONFIRM=继续？[Y/n]: 
if /i "%CONFIRM%"=="n" exit /b 0

echo.
echo [1/4] 提交当前分支的未保存更改...
git add .
git status -s
set /p COMMIT_MSG=提交说明（直接回车则使用 "Merge branch into main"）: 
if "%COMMIT_MSG%"=="" set COMMIT_MSG=Merge branch into main
git commit -m "%COMMIT_MSG%" 2>nul || echo 无新更改或已提交

echo.
echo [2/4] 切换到 main...
git checkout main
if errorlevel 1 (
    echo main 不存在，创建并切换...
    git checkout -b main
)

echo.
echo [3/4] 合并 %CURRENT_BRANCH% 到 main...
git merge %CURRENT_BRANCH% -m "Merge branch into main"
if errorlevel 1 (
    echo 合并冲突！请手动解决后执行: git add . ^& git commit ^& git push
    pause
    exit /b 1
)

echo.
echo [4/4] 推送到 GitHub...
git push -u origin main
if errorlevel 1 (
    echo 推送失败，请检查网络或 GitHub 凭据。
) else (
    echo 完成。main 已推送到 https://github.com/nobiyou/folio
)

:done
echo.
pause
