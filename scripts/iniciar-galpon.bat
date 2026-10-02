@echo off
rem Inicia el sistema del galpón en esta PC: http://localhost:8000
rem Requiere MySQL de XAMPP encendido (XAMPP Control Panel -> MySQL -> Start).
cd /d "%~dp0.."
echo Galpon de Empaque en http://localhost:8000  (cerrar esta ventana para detenerlo)
start "" http://localhost:8000
php artisan serve --host=localhost --port=8000
