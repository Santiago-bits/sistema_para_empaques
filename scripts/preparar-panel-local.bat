@echo off
rem Prepara el Panel General local (una sola vez): crea la base galpon_panel, el archivo .env.panel y las tablas.
rem Requiere MySQL de XAMPP encendido.
cd /d "%~dp0.."
set MYSQL="C:\xampp\mysql\bin\mysql.exe"
if not exist %MYSQL% (
    echo No se encontro %MYSQL%. Crea la base "galpon_panel" desde phpMyAdmin y volve a ejecutar.
) else (
    %MYSQL% -uroot -e "CREATE DATABASE IF NOT EXISTS galpon_panel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" || goto :error
)
if not exist ".env.panel" copy ".env.panel.example" ".env.panel" >nul
set APP_ENV=panel
php artisan key:generate --force || goto :error
php artisan migrate --seed --force || goto :error
echo.
echo Listo. Inicia el panel con scripts\iniciar-panel.bat y entra a http://127.0.0.1:8001
echo La primera vez te pide crear el usuario Super Administrador.
pause
exit /b 0

:error
echo.
echo Algo fallo. Revisa que MySQL de XAMPP este encendido.
pause
exit /b 1
