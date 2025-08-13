# Déploiement en Production - MulerTech Database

Ce guide couvre le déploiement sécurisé et optimisé de MulerTech Database ORM en environnement de production.

## 📋 Table des matières

- [Prérequis de production](#prérequis-de-production)
- [Configuration sécurisée](#configuration-sécurisée)
- [Variables d'environnement](#variables-denvironnement)
- [Optimisations serveur](#optimisations-serveur)
- [Monitoring et logs](#monitoring-et-logs)
- [Sauvegarde et récupération](#sauvegarde-et-récupération)
- [Mise à jour et maintenance](#mise-à-jour-et-maintenance)
- [Sécurité](#sécurité)
- [Checklist de déploiement](#checklist-de-déploiement)

## Prérequis de production

### Serveur

**Configuration minimale recommandée :**
```
CPU: 2 cœurs (4+ recommandés)
RAM: 4 GB (8+ recommandés)
Stockage: SSD 50+ GB
Réseau: 100 Mbps+
```

**Système d'exploitation :**
```bash
# Ubuntu 24.04 LTS (recommandé)
# CentOS 10+ / RHEL 10+
# Debian 13+
```

### Logiciels requis

```bash
# PHP 8.1+ avec extensions
sudo apt update
sudo apt install -y php8.1-fpm php8.1-cli php8.1-mysql php8.1-pgsql \
    php8.1-mbstring php8.1-xml php8.1-curl php8.1-json php8.1-opcache \
    php8.1-zip php8.1-gd php8.1-intl php8.1-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Serveur web (Nginx recommandé)
sudo apt install -y nginx

# Base de données
# MySQL 8.0+
sudo apt install -y mysql-server-8.0

# Ou PostgreSQL 13+
sudo apt install -y postgresql-13 postgresql-client-13
```

## Configuration sécurisée

### Configuration PHP pour production

**`/etc/php/8.1/fpm/php.ini`**
```ini
; Sécurité
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Performance
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.save_comments = 1

; Limites
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 64M
upload_max_filesize = 64M

; Sessions sécurisées
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"
session.use_strict_mode = 1
```

### Configuration Nginx

**`/etc/nginx/sites-available/app`**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com;
    
    root /var/www/html/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE+AESGCM:ECDHE+AES256:ECDHE+AES128:!aNULL:!MD5:!DSS;
    ssl_prefer_server_ciphers on;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'" always;
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
    
    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(vendor|tests|storage/logs) {
        deny all;
    }
    
    # Try files
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

## Variables d'environnement

### Production .env

```bash
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_TIMEZONE=UTC

# Base de données
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=app_production
DB_USER=app_user
DB_PASSWORD=your_secure_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Configuration SSL pour la DB
DB_SSL_MODE=required
DB_SSL_CERT=/path/to/client-cert.pem
DB_SSL_KEY=/path/to/client-key.pem
DB_SSL_CA=/path/to/ca-cert.pem

# Cache
CACHE_DRIVER=redis
CACHE_PREFIX=app_prod_
CACHE_TTL=3600

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password
REDIS_DATABASE=0

# Logs
LOG_LEVEL=warning
LOG_CHANNEL=daily
LOG_MAX_FILES=14

# Security
APP_SECRET=your_very_long_and_random_secret_key_here
ENCRYPTION_KEY=your_encryption_key_32_chars_long

# Email (si utilisé)
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls

# Monitoring
SENTRY_DSN=your_sentry_dsn
NEW_RELIC_LICENSE_KEY=your_newrelic_key
```

### Sécurisation des variables

```bash
# Créer un utilisateur dédié
sudo useradd -r -s /bin/false appuser

# Permissions restrictives sur .env
sudo chown appuser:appuser /var/www/html/.env
sudo chmod 600 /var/www/html/.env

# Alternative: utiliser des variables système
# /etc/environment ou /etc/systemd/system/app.service
```

## Optimisations serveur

### Configuration MySQL

**`/etc/mysql/mysql.conf.d/mysqld.cnf`**
```ini
[mysqld]
# Performance
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Connections
max_connections = 200
max_user_connections = 180

# Query cache (MySQL 5.7 uniquement)
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

# Logs
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Security
bind-address = 127.0.0.1
local_infile = 0
```

### Configuration Redis

**`/etc/redis/redis.conf`**
```bash
# Security
bind 127.0.0.1
requirepass your_redis_password
protected-mode yes

# Memory
maxmemory 1gb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Performance
tcp-keepalive 300
timeout 0
```

### Optimisations système

```bash
# Limites de fichiers
echo "* soft nofile 65535" >> /etc/security/limits.conf
echo "* hard nofile 65535" >> /etc/security/limits.conf

# Optimisations réseau
echo "net.core.rmem_max = 16777216" >> /etc/sysctl.conf
echo "net.core.wmem_max = 16777216" >> /etc/sysctl.conf
echo "net.ipv4.tcp_rmem = 4096 65536 16777216" >> /etc/sysctl.conf
echo "net.ipv4.tcp_wmem = 4096 65536 16777216" >> /etc/sysctl.conf

sysctl -p
```

## Monitoring et logs

### Configuration des logs

**`config/logging.php`**
```php
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'syslog'],
        ],
        
        'daily' => [
            'driver' => 'daily',
            'path' => '/var/log/app/app.log',
            'level' => env('LOG_LEVEL', 'warning'),
            'days' => 14,
            'permission' => 0644,
        ],
        
        'syslog' => [
            'driver' => 'syslog',
            'level' => 'error',
        ],
        
        'database' => [
            'driver' => 'daily',
            'path' => '/var/log/app/database.log',
            'level' => 'debug',
            'days' => 7,
        ],
    ],
];
```

### Monitoring avec Prometheus

**`docker-compose.monitoring.yml`**
```yaml
version: '3.8'

services:
  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/etc/prometheus/console_libraries'
      - '--web.console.templates=/etc/prometheus/consoles'

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    volumes:
      - grafana_data:/var/lib/grafana
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin

  mysql_exporter:
    image: prom/mysqld-exporter:latest
    environment:
      - DATA_SOURCE_NAME=monitoring_user:password@(mysql:3306)/
    ports:
      - "9104:9104"

volumes:
  prometheus_data:
  grafana_data:
```

### Scripts de monitoring

**`scripts/health-check.php`**
```php
<?php

declare(strict_types=1);

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Database\MySQLDriver;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class HealthChecker
{
    private EmEngine $em;
    private array $checks = [];

    public function __construct(EmEngine $em)
    {
        $this->em = $em;
    }

    public function runAllChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemory(),
            'timestamp' => time(),
            'status' => $this->getOverallStatus()
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $result = $this->em->createQueryBuilder()
                              ->select('1 as test')
                              ->getQuery()
                              ->getSingleScalarResult();
            
            return [
                'status' => 'healthy',
                'response_time' => $this->measureDbResponseTime(),
                'connections' => $this->getConnectionCount()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkCache(): array
    {
        // Vérification du cache Redis/Memcached
        try {
            $key = 'health_check_' . time();
            $cache = $this->em->getCache();
            
            $cache->set($key, 'test', 60);
            $value = $cache->get($key);
            $cache->delete($key);
            
            return [
                'status' => $value === 'test' ? 'healthy' : 'degraded'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $percent = ($free / $total) * 100;
        
        return [
            'status' => $percent > 10 ? 'healthy' : 'warning',
            'free_space_percent' => round($percent, 2),
            'free_bytes' => $free
        ];
    }

    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = ini_get('memory_limit');
        
        return [
            'status' => 'healthy',
            'current_usage' => $usage,
            'peak_usage' => $peak,
            'limit' => $limit
        ];
    }

    private function measureDbResponseTime(): float
    {
        $start = microtime(true);
        $this->em->createQueryBuilder()
                 ->select('1')
                 ->getQuery()
                 ->getSingleScalarResult();
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function getConnectionCount(): int
    {
        try {
            return (int) $this->em->createQueryBuilder()
                                  ->select('COUNT(*)')
                                  ->from('information_schema.processlist')
                                  ->getQuery()
                                  ->getSingleScalarResult();
        } catch (\Exception) {
            return 0;
        }
    }

    private function getOverallStatus(): string
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'unhealthy') {
                return 'unhealthy';
            }
        }
        return 'healthy';
    }
}

// Point d'entrée pour monitoring externe
if (php_sapi_name() === 'cli' || isset($_GET['check'])) {
    $driver = new MySQLDriver(
        $_ENV['DB_HOST'],
        $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    
    $em = new EmEngine($driver);
    $checker = new HealthChecker($em);
    
    header('Content-Type: application/json');
    echo json_encode($checker->runAllChecks(), JSON_PRETTY_PRINT);
}
```

## Sauvegarde et récupération

### Scripts de sauvegarde automatisée

**`scripts/backup.sh`**
```bash
#!/bin/bash

# Configuration
DB_NAME="app_production"
DB_USER="backup_user"
DB_PASSWORD="backup_password"
BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Création du répertoire de sauvegarde
mkdir -p "$BACKUP_DIR"

# Sauvegarde de la base de données
mysqldump \
    --user="$DB_USER" \
    --password="$DB_PASSWORD" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --compress \
    "$DB_NAME" > "$BACKUP_DIR/backup_${DATE}.sql"

# Compression
gzip "$BACKUP_DIR/backup_${DATE}.sql"

# Nettoyage des anciennes sauvegardes
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete

# Vérification de l'intégrité
if [ -f "$BACKUP_DIR/backup_${DATE}.sql.gz" ]; then
    echo "Backup completed successfully: backup_${DATE}.sql.gz"
    
    # Upload vers stockage externe (S3, etc.)
    # aws s3 cp "$BACKUP_DIR/backup_${DATE}.sql.gz" s3://your-backup-bucket/
else
    echo "Backup failed!" >&2
    exit 1
fi
```

**Crontab configuration:**
```bash
# Sauvegarde quotidienne à 2h du matin
0 2 * * * /var/www/html/scripts/backup.sh

# Sauvegarde hebdomadaire complète le dimanche à 1h
0 1 * * 0 /var/www/html/scripts/full-backup.sh
```

### Plan de récupération

**`scripts/restore.sh`**
```bash
#!/bin/bash

BACKUP_FILE="$1"
DB_NAME="app_production"
DB_USER="root"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.sql.gz>"
    exit 1
fi

# Vérification de l'existence du fichier
if [ ! -f "$BACKUP_FILE" ]; then
    echo "Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Confirmation
read -p "Are you sure you want to restore $DB_NAME from $BACKUP_FILE? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "Restore cancelled."
    exit 0
fi

# Arrêt de l'application
sudo systemctl stop nginx
sudo systemctl stop php8.1-fpm

# Sauvegarde avant restauration
mysqldump --user="$DB_USER" -p "$DB_NAME" > "/tmp/backup_before_restore_$(date +%Y%m%d_%H%M%S).sql"

# Restauration
echo "Restoring database..."
gunzip -c "$BACKUP_FILE" | mysql --user="$DB_USER" -p "$DB_NAME"

if [ $? -eq 0 ]; then
    echo "Database restored successfully"
    
    # Redémarrage des services
    sudo systemctl start php8.1-fpm
    sudo systemctl start nginx
    
    echo "Services restarted"
else
    echo "Restore failed!" >&2
    exit 1
fi
```

## Mise à jour et maintenance

### Processus de déploiement

**`scripts/deploy.sh`**
```bash
#!/bin/bash

set -e

# Configuration
APP_DIR="/var/www/html"
BRANCH="main"
BACKUP_DIR="/var/backups/deployments"

echo "Starting deployment..."

# Sauvegarde avant déploiement
mkdir -p "$BACKUP_DIR"
sudo cp -r "$APP_DIR" "$BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S)"

# Mode maintenance
sudo touch "$APP_DIR/maintenance.html"

# Arrêt des services worker/queue si applicable
# sudo systemctl stop app-worker

cd "$APP_DIR"

# Mise à jour du code
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

# Installation des dépendances
composer install --no-dev --optimize-autoloader --no-interaction

# Migrations de base de données
php bin/console migrations:migrate --no-interaction

# Nettoyage du cache
php bin/console cache:clear

# Optimisations
composer dump-autoload --optimize

# Permissions
sudo chown -R www-data:www-data storage/ bootstrap/cache/
sudo chmod -R 775 storage/ bootstrap/cache/

# Redémarrage des services
sudo systemctl reload php8.1-fpm
sudo systemctl reload nginx

# Redémarrage des workers
# sudo systemctl start app-worker

# Suppression du mode maintenance
sudo rm -f "$APP_DIR/maintenance.html"

echo "Deployment completed successfully!"

# Vérification de santé
sleep 5
curl -f http://localhost/health-check || {
    echo "Health check failed! Rolling back..."
    # Logique de rollback ici
    exit 1
}

echo "Health check passed!"
```

## Sécurité

### Durcissement du serveur

```bash
# Mise à jour du système
sudo apt update && sudo apt upgrade -y

# Installation de fail2ban
sudo apt install -y fail2ban

# Configuration fail2ban
sudo tee /etc/fail2ban/jail.local << EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/error.log
EOF

sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# Configuration du pare-feu
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw --force enable

# Désactivation de services inutiles
sudo systemctl disable apache2 2>/dev/null || true
sudo systemctl stop apache2 2>/dev/null || true
```

### Certificats SSL

```bash
# Installation de Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtention du certificat
sudo certbot --nginx -d your-domain.com

# Renouvellement automatique
echo "0 12 * * * /usr/bin/certbot renew --quiet" | sudo crontab -
```

## Checklist de déploiement

### Pré-déploiement

- [ ] Tests passent en local
- [ ] Code reviewé et approuvé
- [ ] Variables d'environnement configurées
- [ ] Sauvegarde de la base de données effectuée
- [ ] Plan de rollback préparé
- [ ] Équipe informée du déploiement

### Déploiement

- [ ] Mode maintenance activé
- [ ] Code déployé sur le serveur
- [ ] Migrations exécutées
- [ ] Cache vidé et régénéré
- [ ] Permissions vérifiées
- [ ] Services redémarrés

### Post-déploiement

- [ ] Health checks passent
- [ ] Fonctionnalités critiques testées
- [ ] Logs vérifiés (absence d'erreurs)
- [ ] Performance monitoring activé
- [ ] Mode maintenance désactivé
- [ ] Équipe informée du succès

### Rollback (si nécessaire)

- [ ] Code restauré depuis backup
- [ ] Base de données restaurée
- [ ] Cache vidé
- [ ] Services redémarrés
- [ ] Vérification fonctionnelle
- [ ] Post-mortem planifié

---

Ce guide fournit une base solide pour déployer MulerTech Database ORM en production de manière sécurisée et performante. Adaptez les configurations selon vos besoins spécifiques et votre infrastructure.
