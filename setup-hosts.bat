@echo off
:: Run as Administrator once so http://appayelectronic.test resolves in the browser.
findstr /C:"appayelectronic.test" C:\Windows\System32\drivers\etc\hosts >nul
if %errorlevel%==0 (
  echo appayelectronic.test is already in your hosts file.
  exit /b 0
)
echo 127.0.0.1      appayelectronic.test     #laragon magic!>> C:\Windows\System32\drivers\etc\hosts
if %errorlevel%==0 (
  echo Added appayelectronic.test to hosts.
) else (
  echo Failed. Right-click this file and choose "Run as administrator".
  exit /b 1
)
