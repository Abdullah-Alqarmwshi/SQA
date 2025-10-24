@echo off
title ClassConnect - MySQL Quick Fix
color 0A

echo.
echo 
echo                                                             
echo            CLASSCONNECT MYSQL QUICK FIX                     
echo                                                             
echo 
echo.
echo This tool will help you fix the MySQL "shutdown unexpectedly" error.
echo.
echo Choose a method:
echo.
echo [1] Change MySQL port to 3307 (Recommended)
echo [2] Reset MySQL data folder (If method 1 fails)
echo [3] Open INSTALLATION_GUIDE.txt
echo [4] Exit
echo.
set /p choice="Enter your choice (1-4): "

if "%choice%"=="1" goto port_change
if "%choice%"=="2" goto data_reset
if "%choice%"=="3" goto open_guide
if "%choice%"=="4" goto end

:port_change
echo.
echo 
echo   METHOD 1: Changing MySQL Port
echo 
echo.
echo Step 1: Creating backup of my.ini...
copy "C:\xampp\mysql\bin\my.ini" "C:\xampp\mysql\bin\my.ini.backup" >nul 2>&1
if errorlevel 1 (
    echo ERROR: Could not create backup. Make sure XAMPP is installed in C:\xampp
    pause
    goto menu
)
echo  Backup created

echo.
echo Step 2: Changing port from 3306 to 3307...
powershell -Command "(Get-Content 'C:\xampp\mysql\bin\my.ini') -replace 'port=3306', 'port=3307' | Set-Content 'C:\xampp\mysql\bin\my.ini'"
echo  Port changed

echo.
echo Step 3: Updating database config...
powershell -Command "(Get-Content 'C:\xampp\htdocs\mywebsite\config\database.php') -replace \"define\('DB_PORT', '3306'\)\", \"define('DB_PORT', '3307')\" | Set-Content 'C:\xampp\htdocs\mywebsite\config\database.php'"
echo  Config updated

echo.
echo 
echo   DONE! MySQL port changed to 3307
echo 
echo.
echo NEXT STEPS:
echo 1. Open XAMPP Control Panel
echo 2. Start MySQL (it should start successfully now)
echo 3. Start Apache
echo 4. Go to: http://localhost/mywebsite/setup.php
echo.
pause
goto end

:data_reset
echo.
echo 
echo   METHOD 2: Reset MySQL Data
echo 
echo.
echo  WARNING: This will delete all existing databases!
echo.
set /p confirm="Are you sure? Type YES to continue: "
if not "%confirm%"=="YES" (
    echo Operation cancelled.
    pause
    goto menu
)

echo.
echo Step 1: Stopping MySQL processes...
taskkill /F /IM mysqld.exe >nul 2>&1

echo Step 2: Renaming current data folder...
if exist "C:\xampp\mysql\data" (
    rename "C:\xampp\mysql\data" "data_old_%date:~-4,4%%date:~-10,2%%date:~-7,2%"
    echo  Old data backed up
) else (
    echo ! Data folder not found
)

echo.
echo Step 3: Copying fresh data from backup...
if exist "C:\xampp\mysql\backup" (
    xcopy "C:\xampp\mysql\backup" "C:\xampp\mysql\data" /E /I /H >nul
    echo  Fresh data copied
) else (
    echo ERROR: Backup folder not found!
    echo Please reinstall XAMPP or restore from another backup.
    pause
    goto menu
)

echo.
echo 
echo   DONE! MySQL data reset complete
echo 
echo.
echo NEXT STEPS:
echo 1. Open XAMPP Control Panel
echo 2. Start MySQL
echo 3. Start Apache  
echo 4. Go to: http://localhost/mywebsite/setup.php
echo.
pause
goto end

:open_guide
start "" "INSTALLATION_GUIDE.txt"
goto end

:menu
cls
goto start

:end
exit
