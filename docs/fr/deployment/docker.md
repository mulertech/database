# Conteneurisation Docker - MulerTech Database

Ce guide couvre la conteneurisation de MulerTech Database ORM avec Docker pour le développement, les tests et la production.

## 📋 Table des matières

- [Configuration de base](#configuration-de-base)
- [Images Docker](#images-docker)
- [Docker Compose](#docker-compose)
- [Environnements multiples](#environnements-multiples)
- [Optimisations production](#optimisations-production)
- [Monitoring et logs](#monitoring-et-logs)
- [Sécurité des conteneurs](#sécurité-des-conteneurs)
- [CI/CD avec Docker](#cicd-avec-docker)

## Configuration de base

### Structure du projet

```
project/
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   ├── php.ini
│   │   └── www.conf
│   ├── nginx/
│   │   ├── Dockerfile
│   │   └── default.conf
│   └── mysql/
│       ├── Dockerfile
│       └── init.sql
├── docker-compose.yml
├── docker-compose.override.yml
├── docker-compose.prod.yml
└── .dockerignore
```

### .dockerignore

```dockerignore
# Git
.git
.gitignore

# Documentation
README.md
docs/

# Tests
tests/
phpunit.xml
.phpunit.result.cache

# Development files
.env.local
.env.test
storage/logs/*
!storage/logs/.gitkeep

# Dependencies (will be installed in container)
vendor/
node_modules/

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db
```

## Images Docker

### Image PHP-FPM

**`docker/php/Dockerfile`**
```dockerfile
FROM php:8.2-fpm-alpine

# Métadonnées
LABEL maintainer="Sébastien Muler <muler@example.com>"
LABEL version="1.0"
LABEL description="PHP-FPM container for MulerTech Database"

# Installation des dépendances système
RUN apk add --no-cache \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Installation de Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuration PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Configuration du répertoire de travail
WORKDIR /var/www/html

# Copie des fichiers Composer
COPY composer.json composer.lock ./

# Installation des dépendances (cache layer)
RUN composer install --no-dev --no-scripts --no-autoloader --optimize-autoloader

# Copie du code source
COPY . .

# Finalisation de l'installation Composer
RUN composer dump-autoload --optimize

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Configuration de l'utilisateur
USER www-data

# Port exposé
EXPOSE 9000

# Commande par défaut
CMD ["php-fpm"]
```

**`docker/php/php.ini`**
```ini
[PHP]
; Performance
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; Uploads
post_max_size = 64M
upload_max_filesize = 64M
max_file_uploads = 20

; Error reporting
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /proc/self/fd/2

; Opcache
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.save_comments = 1

; Session
session.save_handler = redis
session.save_path = "tcp://redis:6379"
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"

; Date
date.timezone = UTC
```

### Image Nginx

**`docker/nginx/Dockerfile`**
```dockerfile
FROM nginx:1.24-alpine

# Métadonnées
LABEL maintainer="Sébastien Muler <muler@example.com>"
LABEL version="1.0"
LABEL description="Nginx container for MulerTech Database"

# Copie de la configuration
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Création du répertoire pour les logs
RUN mkdir -p /var/log/nginx

# Port exposé
EXPOSE 80 443

# Commande par défaut
CMD ["nginx", "-g", "daemon off;"]
```

**`docker/nginx/default.conf`**
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    # Logs
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Cache des fichiers statiques
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Traitement PHP
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        
        # Timeouts
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
        fastcgi_connect_timeout 300;
    }

    # Blocage des fichiers sensibles
    location ~ /\. {
        deny all;
    }

    location ~ /(vendor|tests|storage/logs) {
        deny all;
    }

    # Fallback pour les routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Health check
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}
```

## Docker Compose

### Configuration de développement

**`docker-compose.yml`**
```yaml
version: '3.8'

services:
  # Application PHP
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: development
    container_name: mulertech_php
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - ./storage/logs:/var/www/html/storage/logs
    environment:
      - APP_ENV=development
      - APP_DEBUG=true
    networks:
      - mulertech_network
    depends_on:
      - mysql
      - redis

  # Serveur web Nginx
  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    container_name: mulertech_nginx
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html:ro
      - ./storage/logs/nginx:/var/log/nginx
    networks:
      - mulertech_network
    depends_on:
      - php

  # Base de données MySQL
  mysql:
    image: mysql:8.0
    container_name: mulertech_mysql
    restart: unless-stopped
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: mulertech_db
      MYSQL_USER: mulertech_user
      MYSQL_PASSWORD: mulertech_password
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/my.cnf:ro
    networks:
      - mulertech_network
    command: --default-authentication-plugin=mysql_native_password

  # Cache Redis
  redis:
    image: redis:7-alpine
    container_name: mulertech_redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
      - ./docker/redis/redis.conf:/etc/redis/redis.conf:ro
    networks:
      - mulertech_network
    command: redis-server /etc/redis/redis.conf

  # PhpMyAdmin (développement uniquement)
  phpmyadmin:
    image: phpmyadmin:latest
    container_name: mulertech_phpmyadmin
    restart: unless-stopped
    ports:
      - "8081:80"
    environment:
      PMA_HOST: mysql
      PMA_USER: mulertech_user
      PMA_PASSWORD: mulertech_password
    networks:
      - mulertech_network
    depends_on:
      - mysql
    profiles:
      - development

  # Redis Commander (développement uniquement)
  redis-commander:
    image: rediscommander/redis-commander:latest
    container_name: mulertech_redis_commander
    restart: unless-stopped
    ports:
      - "8082:8081"
    environment:
      REDIS_HOSTS: local:redis:6379
    networks:
      - mulertech_network
    depends_on:
      - redis
    profiles:
      - development

volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local

networks:
  mulertech_network:
    driver: bridge
```

### Override pour développement

**`docker-compose.override.yml`**
```yaml
version: '3.8'

services:
  php:
    build:
      target: development
    volumes:
      - .:/var/www/html
      - ./docker/php/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini
    environment:
      - XDEBUG_MODE=develop,debug
      - XDEBUG_CONFIG=client_host=host.docker.internal
    profiles:
      - development

  nginx:
    volumes:
      - ./docker/nginx/dev.conf:/etc/nginx/conf.d/default.conf

  mysql:
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: dev_password
    profiles:
      - development
```

## Environnements multiples

### Configuration de test

**`docker-compose.test.yml`**
```yaml
version: '3.8'

services:
  php-test:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: test
    environment:
      - APP_ENV=testing
      - APP_DEBUG=false
      - DB_HOST=mysql-test
      - DB_DATABASE=mulertech_test
    volumes:
      - .:/var/www/html
    networks:
      - test_network
    depends_on:
      - mysql-test

  mysql-test:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: test_password
      MYSQL_DATABASE: mulertech_test
      MYSQL_USER: test_user
      MYSQL_PASSWORD: test_password
    volumes:
      - test_mysql_data:/var/lib/mysql
    networks:
      - test_network
    tmpfs:
      - /var/lib/mysql:noexec,nosuid,size=512m

volumes:
  test_mysql_data:

networks:
  test_network:
    driver: bridge
```

### Configuration de production

**`docker-compose.prod.yml`**
```yaml
version: '3.8'

services:
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: production
    restart: always
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - app_storage:/var/www/html/storage
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '1'
          memory: 512M
        reservations:
          cpus: '0.5'
          memory: 256M
    networks:
      - production_network
    secrets:
      - db_password
      - app_secret

  nginx:
    build:
      context: .
      dockerfile: docker/nginx/Dockerfile
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - app_storage:/var/www/html/storage:ro
      - ./docker/nginx/ssl:/etc/ssl/certs:ro
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '0.5'
          memory: 128M
    networks:
      - production_network

  mysql:
    image: mysql:8.0
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/mysql_root_password
      MYSQL_DATABASE: mulertech_prod
      MYSQL_USER: mulertech_prod
      MYSQL_PASSWORD_FILE: /run/secrets/mysql_password
    volumes:
      - mysql_prod_data:/var/lib/mysql
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G
    networks:
      - production_network
    secrets:
      - mysql_root_password
      - mysql_password

secrets:
  db_password:
    external: true
  app_secret:
    external: true
  mysql_root_password:
    external: true
  mysql_password:
    external: true

volumes:
  app_storage:
  mysql_prod_data:

networks:
  production_network:
    driver: overlay
    attachable: true
```

## Optimisations production

### Multi-stage Dockerfile

**`docker/php/Dockerfile.multistage`**
```dockerfile
# Stage 1: Dépendances et build
FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Stage 2: Développement
FROM base AS development

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-dev.ini

CMD ["php-fpm"]

# Stage 3: Test
FROM base AS test

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

COPY docker/php/php-test.ini /usr/local/etc/php/conf.d/99-test.ini

CMD ["php-fpm"]

# Stage 4: Production
FROM base AS production

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --optimize-autoloader

COPY . .
RUN composer dump-autoloader --optimize --classmap-authoritative

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-prod.ini

# Suppression des fichiers inutiles en production
RUN rm -rf \
    tests/ \
    docs/ \
    .git/ \
    docker/ \
    README.md \
    phpunit.xml

# Permissions optimisées
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage

USER www-data

CMD ["php-fpm"]
```

### Configuration de santé

**`docker/healthcheck.sh`**
```bash
#!/bin/sh

# Health check pour conteneur PHP
if [ "$1" = "php" ]; then
    php -r "
    try {
        \$pdo = new PDO('mysql:host=mysql;dbname=mulertech_db', 'mulertech_user', 'mulertech_password');
        echo 'Database connection: OK\n';
        exit(0);
    } catch (Exception \$e) {
        echo 'Database connection: FAILED\n';
        exit(1);
    }
    "
fi

# Health check pour conteneur Nginx
if [ "$1" = "nginx" ]; then
    wget --no-verbose --tries=1 --spider http://localhost/health || exit 1
fi

# Health check pour conteneur MySQL
if [ "$1" = "mysql" ]; then
    mysqladmin ping -h localhost -u mulertech_user -pmulertech_password || exit 1
fi
```

## Monitoring et logs

### Configuration de monitoring

**`docker-compose.monitoring.yml`**
```yaml
version: '3.8'

services:
  # Prometheus pour les métriques
  prometheus:
    image: prom/prometheus:latest
    container_name: prometheus
    restart: unless-stopped
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml:ro
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/etc/prometheus/console_libraries'
      - '--web.console.templates=/etc/prometheus/consoles'
    networks:
      - monitoring

  # Grafana pour les dashboards
  grafana:
    image: grafana/grafana:latest
    container_name: grafana
    restart: unless-stopped
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
    volumes:
      - grafana_data:/var/lib/grafana
      - ./monitoring/grafana/dashboards:/etc/grafana/provisioning/dashboards:ro
      - ./monitoring/grafana/datasources:/etc/grafana/provisioning/datasources:ro
    networks:
      - monitoring

  # Loki pour les logs
  loki:
    image: grafana/loki:latest
    container_name: loki
    restart: unless-stopped
    ports:
      - "3100:3100"
    volumes:
      - ./monitoring/loki.yml:/etc/loki/local-config.yaml:ro
      - loki_data:/loki
    command: -config.file=/etc/loki/local-config.yaml
    networks:
      - monitoring

  # Promtail pour collecter les logs
  promtail:
    image: grafana/promtail:latest
    container_name: promtail
    restart: unless-stopped
    volumes:
      - ./monitoring/promtail.yml:/etc/promtail/config.yml:ro
      - /var/log:/var/log:ro
      - ./storage/logs:/var/app/logs:ro
    command: -config.file=/etc/promtail/config.yml
    networks:
      - monitoring

volumes:
  prometheus_data:
  grafana_data:
  loki_data:

networks:
  monitoring:
    driver: bridge
```

## Sécurité des conteneurs

### Bonnes pratiques de sécurité

```dockerfile
# Utilisation d'un utilisateur non-root
FROM php:8.2-fpm-alpine

# Création d'un utilisateur dédié
RUN addgroup -g 1001 appgroup && \
    adduser -D -s /bin/sh -u 1001 -G appgroup appuser

# Installation sécurisée des packages
RUN apk add --no-cache --update \
    package1 \
    package2 \
    && rm -rf /var/cache/apk/*

# Suppression de packages inutiles après installation
RUN apk del .build-deps

# Permissions restrictives
COPY --chown=appuser:appgroup . /var/www/html
RUN chmod -R 755 /var/www/html

# Utilisation de l'utilisateur non-root
USER appuser

# Port non-privilégié
EXPOSE 8080

# Pas de shell dans l'image finale
RUN rm -rf /bin/sh /bin/bash
```

### Scan de sécurité

**`scripts/security-scan.sh`**
```bash
#!/bin/bash

# Scan de vulnérabilités avec Trivy
echo "Scanning Docker images for vulnerabilities..."

# Scan de l'image PHP
trivy image --severity HIGH,CRITICAL mulertech/php:latest

# Scan de l'image Nginx
trivy image --severity HIGH,CRITICAL mulertech/nginx:latest

# Scan des dépendances Composer
trivy fs --severity HIGH,CRITICAL .

echo "Security scan completed!"
```

## CI/CD avec Docker

### GitHub Actions

**`.github/workflows/docker.yml`**
```yaml
name: Docker Build and Push

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build-and-test:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
    - name: Checkout repository
      uses: actions/checkout@v4

    - name: Set up Docker Buildx
      uses: docker/setup-buildx-action@v3

    - name: Log in to Container Registry
      if: github.event_name != 'pull_request'
      uses: docker/login-action@v3
      with:
        registry: ${{ env.REGISTRY }}
        username: ${{ github.actor }}
        password: ${{ secrets.GITHUB_TOKEN }}

    - name: Extract metadata
      id: meta
      uses: docker/metadata-action@v5
      with:
        images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
        tags: |
          type=ref,event=branch
          type=ref,event=pr
          type=sha

    - name: Build and push Docker image
      uses: docker/build-push-action@v5
      with:
        context: .
        file: docker/php/Dockerfile
        push: ${{ github.event_name != 'pull_request' }}
        tags: ${{ steps.meta.outputs.tags }}
        labels: ${{ steps.meta.outputs.labels }}
        cache-from: type=gha
        cache-to: type=gha,mode=max

    - name: Run tests in container
      run: |
        docker-compose -f docker-compose.test.yml up -d
        docker-compose -f docker-compose.test.yml exec -T php-test composer test
        docker-compose -f docker-compose.test.yml down

    - name: Security scan
      uses: aquasecurity/trivy-action@master
      with:
        image-ref: ${{ steps.meta.outputs.tags }}
        format: 'sarif'
        output: 'trivy-results.sarif'

    - name: Upload Trivy scan results
      uses: github/codeql-action/upload-sarif@v3
      if: always()
      with:
        sarif_file: 'trivy-results.sarif'
```

### Scripts utilitaires

**`scripts/docker-utils.sh`**
```bash
#!/bin/bash

# Fonction d'aide
usage() {
    echo "Usage: $0 {start|stop|restart|build|test|logs|clean}"
    echo ""
    echo "Commands:"
    echo "  start   - Start all services"
    echo "  stop    - Stop all services"
    echo "  restart - Restart all services"
    echo "  build   - Build all images"
    echo "  test    - Run tests in containers"
    echo "  logs    - Show logs from all services"
    echo "  clean   - Clean up unused containers and images"
}

# Démarrage des services
start() {
    echo "Starting services..."
    docker-compose up -d
    echo "Services started successfully!"
}

# Arrêt des services
stop() {
    echo "Stopping services..."
    docker-compose down
    echo "Services stopped successfully!"
}

# Redémarrage des services
restart() {
    echo "Restarting services..."
    docker-compose restart
    echo "Services restarted successfully!"
}

# Construction des images
build() {
    echo "Building images..."
    docker-compose build --no-cache
    echo "Images built successfully!"
}

# Exécution des tests
test() {
    echo "Running tests..."
    docker-compose -f docker-compose.test.yml up --build --abort-on-container-exit
    docker-compose -f docker-compose.test.yml down
}

# Affichage des logs
logs() {
    docker-compose logs -f
}

# Nettoyage
clean() {
    echo "Cleaning up..."
    docker system prune -f
    docker volume prune -f
    echo "Cleanup completed!"
}

# Traitement des arguments
case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    build)
        build
        ;;
    test)
        test
        ;;
    logs)
        logs
        ;;
    clean)
        clean
        ;;
    *)
        usage
        exit 1
        ;;
esac
```

---

Cette documentation Docker fournit une approche complète pour conteneuriser MulerTech Database ORM, du développement à la production, avec des optimisations de sécurité et de performance.
