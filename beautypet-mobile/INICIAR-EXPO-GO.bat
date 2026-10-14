@echo off
cd /d "%~dp0"

echo ========================================
echo BEAUTY PET - EXPO GO
echo ========================================
echo.

if not exist node_modules (
  echo Instalando dependencias pela primeira vez...
  npm.cmd install
  if errorlevel 1 (
    echo.
    echo Nao foi possivel instalar as dependencias.
    echo Confira sua internet e se o Node.js esta instalado.
    pause
    exit /b 1
  )
)

echo.
echo Iniciando Expo com cache limpo...
npx.cmd expo start -c
pause
