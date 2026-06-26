# NAME
`make:deployment` - Generate Enterprise Docker and Kubernetes scaffolding for the application

# SYNOPSIS
`php spp.php make:deployment [app_name] [--with-redis] [--host=IP] [--user=user] [--key=id_rsa]`

# PURPOSE
The `make:deployment` command builds out an exhaustive containerized deployment scaffold allowing an SPP app to run in enterprise environments. It generates customized NGINX, PHP-FPM, MariaDB, and optional Redis orchestration configs via Docker Compose, as well as providing automated SSH-based remote push capabilities.

# OPTIONS AVAILABLE
- `[app_name]` (string): The target app to deploy. Defaults to `default`.
- `--with-redis` (flag): Dynamically injects a Redis container dependency into the `docker-compose.yml` for high-performance session and cache handling.
- `--host=<IP>` (string): The remote server IP for automated SCP deployment.
- `--user=<username>` (string): The SSH user for the remote server.
- `--key=<key_path>` (string): The path to the SSH private key used for remote deployment.

# UNDER THE HOOD ACTIVITY
Executing this command generates a `deploy/{app_name}` directory at the root of the project. It explicitly builds out four critical infrastructural files:
1. `Dockerfile`: Pulls `php:8.2-fpm-alpine`, installs core extensions (PDO, MySQL, SQLite, OPcache, Redis), copies configs, and sets a custom `CMD` that forcibly triggers `php spp.php sppmigrate:run` before spawning `supervisord` to ensure the database schema is built dynamically on boot.
2. `docker-compose.yml`: Scaffolds a multi-container network. The `app` service is bound to port 8080 mapping to 80 internally. A MariaDB `db` service is generated with persistent volumes. If `--with-redis` is true, string replacements dynamically inject a `redis:7-alpine` service.
3. `nginx.conf`: Hardcodes an optimized NGINX config explicitly proxying `.php` files to local port 9000 using `fastcgi`.
4. `supervisord.conf`: Creates a process monitor config that simultaneously runs `php-fpm`, `nginx`, and a background worker running `php spp.php queue:work` indefinitely.

If `--host` and `--user` are defined, it alters behavior entirely, compiling a `push.sh` bash script. This script automatically archives the entire repo using `tar -czf`, sends it via `scp`, extracts it on the remote server into `/opt/spp/{app_name}`, and triggers a headless `docker-compose up -d --build`.

# EXAMPLES
**1. Local containerization with Redis:**
```bash
php spp.php make:deployment dashboard --with-redis
```

**2. Direct Push to Production Server:**
```bash
php spp.php make:deployment api --host=192.168.1.100 --user=root --key=~/.ssh/id_rsa
```
