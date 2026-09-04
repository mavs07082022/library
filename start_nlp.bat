@echo off
echo ========================================
echo 📚 Starting NLP Service
echo ========================================

cd /d "C:\xampp\htdocs\lib\python"

echo Checking Python...
python --version
if errorlevel 1 (
    echo ❌ Python not found in PATH
    echo Please install Python and add it to PATH
    pause
    exit /b 1
)

echo Checking requirements...
pip show sentence-transformers >nul 2>&1
if errorlevel 1 (
    echo 📦 Installing required packages...
    pip install -r requirements.txt
)

echo.
echo Starting NLP Service in background...
start /MIN python app.py

echo.
echo Waiting for service to start...
timeout /t 5 /nobreak >nul

echo Checking service status...
curl -s http://localhost:5000/health
if errorlevel 1 (
    echo ⚠️ Service may still be starting...
    echo Check the Python window for progress
) else (
    echo ✅ Service is running!
)

echo.
echo ========================================
echo NLP Service started successfully!
echo Service URL: http://localhost:5000
echo Health Check: http://localhost:5000/health
echo ========================================
pause