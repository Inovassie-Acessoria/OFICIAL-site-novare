@echo off
setlocal
set "BASE=%~dp0"
echo ============================================
echo   Novare Brindes - Stack de DEV (MariaDB + PHP portateis)
echo ============================================

REM Inicia o MariaDB portatil so se ainda nao estiver rodando
tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
if errorlevel 1 (
    echo Iniciando MariaDB em 127.0.0.1:3306 ...
    start "MariaDB Novare" "%BASE%tools\mariadb\bin\mysqld.exe" --datadir="%BASE%tools\mariadb\data" --port=3306 --bind-address=127.0.0.1
    timeout /t 3 /nobreak >nul
) else (
    echo MariaDB ja esta em execucao.
)

echo Iniciando servidor PHP em http://localhost:8000  (CTRL+C para parar)
echo.
"%BASE%tools\php\php.exe" -c "%BASE%tools\php\php.ini" -S localhost:8000 -t "%BASE%public" "%BASE%public\router.php"
endlocal
