# SPP Post-Deployment Cache Warmup Script
$ErrorActionPreference = "Stop"
Write-Host "Starting SPP Post-Deployment Cache Warmup..."
php spp.php cache:warmup --app=lekhak
php spp.php cache:warmup --app=default
Write-Host "Cache warmup completed successfully."
