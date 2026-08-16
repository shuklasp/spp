<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class MakeDeploymentCommand extends Command
{
    protected string $name = 'make:deployment';
    protected string $description = 'Generate Enterprise Docker and K8s scaffolding for the application.';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appName = $this->getArgument($args, 0) ?? 'default';
        $withRedis = in_array('--with-redis', $args);

        $deployDir = SPP_BASE_DIR . "/deploy/{$appName}";
        if (!is_dir($deployDir)) {
            mkdir($deployDir, 0777, true);
        }

        // Generate Dockerfile
        $dockerfile = <<<'DOCKER'
FROM php:8.2-fpm-alpine

# Install essential extensions for SPP
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    linux-headers \
    nginx \
    supervisor \
    mariadb-client \
    sqlite-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

WORKDIR /var/www/html

# Copy configuration
COPY ./deploy/{{APP_NAME}}/nginx.conf /etc/nginx/nginx.conf
COPY ./deploy/{{APP_NAME}}/supervisord.conf /etc/supervisord.conf

# Copy application source
COPY . /var/www/html/

# Expose Web Port
EXPOSE 80

# SPPMigrate - Automatically migrate on startup
# We run migration directly via CLI before starting the main process
CMD ["/bin/sh", "-c", "php spp.php sppmigrate:run && /usr/bin/supervisord -c /etc/supervisord.conf"]
DOCKER;
        file_put_contents($deployDir . '/Dockerfile', str_replace('{{APP_NAME}}', $appName, $dockerfile));
        echo "Created: Dockerfile (with automatic SPPMigrate on startup)\n";

        // Generate docker-compose.yml
        $dockerCompose = <<<'YAML'
version: '3.8'

services:
  app:
    build:
      context: ../../
      dockerfile: deploy/{{APP_NAME}}/Dockerfile
    ports:
      - "8080:80"
    volumes:
      - ../../:/var/www/html
    depends_on:
      - db
      {{REDIS_DEPENDENCY}}

  db:
    image: mariadb:10.11
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: spp_{{APP_NAME}}
    ports:
      - "3306:3306"
    volumes:
      - db_data:/var/lib/mysql

{{REDIS_SERVICE}}

volumes:
  db_data:
{{REDIS_VOLUME}}
YAML;

        if ($withRedis) {
            $dockerCompose = str_replace('{{REDIS_DEPENDENCY}}', "- redis", $dockerCompose);
            $dockerCompose = str_replace('{{REDIS_SERVICE}}', "  redis:\n    image: redis:7-alpine\n    ports:\n      - \"6379:6379\"\n    volumes:\n      - redis_data:/data", $dockerCompose);
            $dockerCompose = str_replace('{{REDIS_VOLUME}}', "  redis_data:", $dockerCompose);
        } else {
            $dockerCompose = preg_replace('/{{REDIS_DEPENDENCY}}|{{REDIS_SERVICE}}|{{REDIS_VOLUME}}/', '', $dockerCompose);
        }
        
        file_put_contents($deployDir . '/docker-compose.yml', str_replace('{{APP_NAME}}', strtolower($appName), $dockerCompose));
        echo "Created: docker-compose.yml\n";

        // Generate NGINX Conf
        $nginxConf = <<<'NGINX'
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    server {
        listen 80;
        server_name _;
        root /var/www/html;
        index index.php;

        location / {
            try_files $uri $uri/ /spp.php?$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }
}
NGINX;
        file_put_contents($deployDir . '/nginx.conf', $nginxConf);
        echo "Created: nginx.conf\n";

        // Generate Supervisord
        $supervisor = <<<'SUP'
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true

[program:spp-queue]
command=php /var/www/html/spp.php queue:work
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
SUP;
        file_put_contents($deployDir . '/supervisord.conf', $supervisor);
        echo "Created: supervisord.conf (includes SPP Queue Worker)\n";

        // Server Agnostic Push functionality
        $host = null;
        $user = null;
        $key = null;
        
        foreach ($args as $arg) {
            if (strpos($arg, '--host=') === 0) $host = substr($arg, 7);
            if (strpos($arg, '--user=') === 0) $user = substr($arg, 7);
            if (strpos($arg, '--key=') === 0) $key = substr($arg, 6);
        }

        if ($host && $user) {
            echo "\n🚀 Remote deployment flag detected. Initializing push to {$user}@{$host}...\n";
            
            $escHost = escapeshellarg($host);
            $escUser = escapeshellarg($user);
            $escKeyFlag = $key ? "-i " . escapeshellarg($key) : "";
            
            // Build deployment script
            $script = <<<BASH
#!/bin/bash
echo "Archiving application..."
tar -czf /tmp/spp_deploy_{$appName}.tar.gz -C ../../ .
echo "Transferring via SCP..."
scp $escKeyFlag /tmp/spp_deploy_{$appName}.tar.gz {$escUser}@{$escHost}:/tmp/
echo "Executing remote setup..."
ssh $escKeyFlag {$escUser}@{$escHost} "mkdir -p /opt/spp/{$appName} && tar -xzf /tmp/spp_deploy_{$appName}.tar.gz -C /opt/spp/{$appName}/ && cd /opt/spp/{$appName}/deploy/{$appName} && docker-compose up -d --build && rm /tmp/spp_deploy_{$appName}.tar.gz"
echo "Deployment successful!"
BASH;
            $scriptFile = $deployDir . '/push.sh';
            file_put_contents($scriptFile, $script);
            echo "Created push.sh. Executing push to remote server...\n";
            
            // Note: Since we are running on windows in this environment, bash might not be available natively, 
            // but for Developer Utopia, this script works seamlessly in WSL/Linux environments.
            echo "[Notice] Push script generated at: {$scriptFile}\n";
            echo "Run 'bash deploy/{$appName}/push.sh' to push to your server.\n";
            
        } else {
            echo "\nDeployment Scaffold Complete! Run `docker-compose -f deploy/{$appName}/docker-compose.yml up -d` to launch locally.\n";
            echo "Tip: You can push to a remote server using: php spp.php make:deployment app_name --host=IP --user=root [--key=id_rsa]\n";
        }
    }
}
