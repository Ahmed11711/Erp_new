@echo off
REM ============================================================
REM  Laravel production optimization — run on the server after deploy
REM  Usage: cd backend && scripts\optimize-for-production.bat
REM ============================================================

cd /d "%~dp0.."

echo [1/6] Composer autoload optimization...
call composer install --no-dev --optimize-autoloader --no-interaction
if errorlevel 1 exit /b 1

echo [2/6] Clearing old caches...
call php artisan optimize:clear

echo [3/6] Running migrations...
call php artisan migrate --force --no-interaction

echo [4/6] Caching config, routes, views, events...
call php artisan config:cache
call php artisan route:cache
call php artisan view:cache
call php artisan event:cache

echo [5/6] Final optimize...
call php artisan optimize

echo [6/6] Done.
echo.
echo IMPORTANT: Ensure .env on server has:
echo   APP_ENV=production
echo   APP_DEBUG=false
echo   LOG_LEVEL=error
echo   CACHE_DRIVER=file  (or redis if available)
echo   QUEUE_CONNECTION=database  (not sync)
echo.
echo For frontend: cd front-end ^&^& npm ci ^&^& ng build --configuration production
