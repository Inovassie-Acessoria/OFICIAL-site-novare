@echo off
setlocal enabledelayedexpansion
echo ============================================
echo   Novare Brindes - Preview local
echo ============================================

set "PHP_BIN="

REM 1) PHP disponivel no PATH?
where php >nul 2>nul
if %errorlevel%==0 (
    set "PHP_BIN=php"
    goto :run
)

REM 2) Instalacao padrao do XAMPP
if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
    goto :run
)

REM 3) Laragon (pega a versao instalada)
for /d %%D in ("C:\laragon\bin\php\php-*") do set "PHP_BIN=%%D\php.exe"
if defined PHP_BIN if exist "!PHP_BIN!" goto :run

REM 4) PHP portatil dentro do projeto (tools\php)
if exist "%~dp0tools\php\php.exe" (
    set "PHP_BIN=%~dp0tools\php\php.exe"
    goto :run
)

echo.
echo [ERRO] PHP 8.x nao encontrado.
echo Instale uma das opcoes abaixo e rode este script de novo:
echo   - XAMPP   : https://www.apachefriends.org
echo   - Laragon : https://laragon.org
echo   - PHP puro: https://windows.php.net/download (descompacte em tools\php)
echo Veja a secao "Preview local" do README.md.
echo.
pause
exit /b 1

:run
echo Usando PHP: !PHP_BIN!
echo Iniciando em http://localhost:8000   (CTRL+C para parar)
echo.
"!PHP_BIN!" -S localhost:8000 -t public
endlocal
