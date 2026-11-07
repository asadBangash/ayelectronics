@echo off
:: Run this file as Administrator (right-click -> Run as administrator)
set ENTRY=127.0.0.1      appayelectronics.test    #laragon magic!
findstr /C:"appayelectronics.test" %SystemRoot%\System32\drivers\etc\hosts >nul
if %errorlevel%==0 (
    echo hosts already contains appayelectronics.test
) else (
    echo %ENTRY%>> %SystemRoot%\System32\drivers\etc\hosts
    echo Added appayelectronics.test to hosts.
)
ipconfig /flushdns >nul
echo Done. Open http://appayelectronics.test in your browser.
pause
