@echo off
echo ================================
echo Folio Theme - Production Build
echo ================================
echo.

echo [1/2] Building Tailwind CSS...
call npm run build

if %errorlevel% neq 0 (
    echo.
    echo ERROR: Build failed!
    pause
    exit /b 1
)

echo.
echo [2/2] Build completed successfully!
echo.
echo Generated file: assets/css/tailwind.css
echo File size: 
dir assets\css\tailwind.css | find "tailwind.css"
echo.
echo ================================
echo Ready for production deployment
echo ================================
echo.
echo Next steps:
echo 1. Upload the theme to your server
echo 2. Make sure assets/css/tailwind.css is included
echo 3. No need to upload node_modules or config files
echo.
pause
