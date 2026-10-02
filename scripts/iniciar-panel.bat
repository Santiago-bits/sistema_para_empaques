@echo off
rem Inicia el Panel General en esta PC: http://127.0.0.1:8001 (usa el archivo .env.panel)
rem La primera vez ejecutar antes scripts\preparar-panel-local.bat
cd /d "%~dp0.."
if not exist ".env.panel" (
    echo Falta .env.panel: ejecuta primero scripts\preparar-panel-local.bat
    pause
    exit /b 1
)
set APP_ENV=panel
echo Panel General en http://127.0.0.1:8001  (cerrar esta ventana para detenerlo)
start "" http://127.0.0.1:8001
php artisan serve --host=127.0.0.1 --port=8001
