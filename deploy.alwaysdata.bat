@echo off
setlocal

if "%~1"=="login-only" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_login_only.ps1"
    exit /b %errorlevel%
)
if "%~1"=="admin-settings" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_admin_settings_only.ps1"
    exit /b %errorlevel%
)
if "%~1"=="auth-notifications" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_auth_notifications_patch.ps1"
    exit /b %errorlevel%
)
if "%~1"=="google-choice" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_google_choice_fix.ps1"
    exit /b %errorlevel%
)
if "%~1"=="nav-pgsql" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_nav_and_pgsql_fix.ps1"
    exit /b %errorlevel%
)
if "%~1"=="pgsql-prepare" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_pgsql_prepare_fix.ps1"
    exit /b %errorlevel%
)
if "%~1"=="prod-env" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_production_env_google.ps1"
    exit /b %errorlevel%
)
if "%~1"=="header-only" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_header_only.ps1"
    exit /b %errorlevel%
)
if "%~1"=="recent-fixes" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_recent_fixes.ps1"
    exit /b %errorlevel%
)
if "%~1"=="seller-workspace" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_recent_fixes.ps1"
    exit /b %errorlevel%
)
if "%~1"=="notifications-slots" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_notifications_slots.ps1"
    exit /b %errorlevel%
)
if "%~1"=="mobile-fix" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_mobile_fix.ps1"
    exit /b %errorlevel%
)
if "%~1"=="flat-ui" (
    powershell -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_flat_ui_cleanup.ps1"
    exit /b %errorlevel%
)
if "%~1"=="deploy" (
    call "%~dp0deploy_to_alwaysdata.bat"
    exit /b %errorlevel%
)

:: Default: Run the full deployment script
call "%~dp0deploy_to_alwaysdata.bat" %*
