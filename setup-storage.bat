@echo off
setlocal
cd /d "%~dp0"

if not exist "storage\app\public" mkdir "storage\app\public"

if exist "public\storage" (
  rmdir "public\storage" 2>nul
  if exist "public\storage" (
    echo Could not remove public\storage. Delete it manually, then run this script again.
    exit /b 1
  )
)

mklink /J "public\storage" "%~dp0storage\app\public"
if errorlevel 1 (
  echo mklink failed. Try: Right-click CMD -^> Run as administrator, then run this script again.
  exit /b 1
)

echo public\storage is now linked to storage\app\public
exit /b 0
