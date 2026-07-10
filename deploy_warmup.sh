#!/usr/bin/env bash
set -euo pipefail
echo "Starting SPP Post-Deployment Cache Warmup..."
php spp.php cache:warmup --app=lekhak
php spp.php cache:warmup --app=default
echo "Cache warmup completed successfully."
