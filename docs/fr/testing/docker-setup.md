# Configuration Docker pour Tests

Guide pour configurer un environnement de test complet avec Docker pour MulerTech Database.

## Table des Matières
- [Configuration de base](#configuration-de-base)
- [Services de test](#services-de-test)
- [Environnements isolés](#environnements-isolés)
- [Scripts d'automatisation](#scripts-dautomatisation)
- [Intégration CI/CD](#intégration-cicd)
- [Debugging et monitoring](#debugging-et-monitoring)

## Configuration de base

### Docker Compose pour tests

```yaml
# docker-compose.test.yml
version: '3.8'

services:
  mysql-test:
    image: mysql:8.0
    container_name: mulertech_mysql_test
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: mulertech_test
      MYSQL_USER: test_user
      MYSQL_PASSWORD: test_password
    ports:
      - "3307:3306"
    volumes:
      - mysql_test_data:/var/lib/mysql
      - ./docker/mysql/test-init.sql:/docker-entrypoint-initdb.d/init.sql
    command: --default-authentication-plugin=mysql_native_password
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10

  mysql-integration:
    image: mysql:8.0
    container_name: mulertech_mysql_integration
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: mulertech_integration
      MYSQL_USER: integration_user
      MYSQL_PASSWORD: integration_password
    ports:
      - "3308:3306"
    volumes:
      - ./docker/mysql/integration-init.sql:/docker-entrypoint-initdb.d/init.sql
    command: --default-authentication-plugin=mysql_native_password
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10

  redis-test:
    image: redis:7-alpine
    container_name: mulertech_redis_test
    restart: unless-stopped
    ports:
      - "6380:6379"
    command: redis-server --appendonly yes
    volumes:
      - redis_test_data:/data

  php-test:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.test
    container_name: mulertech_php_test
    working_dir: /app
    volumes:
      - .:/app
      - ./docker/php/php-test.ini:/usr/local/etc/php/conf.d/test.ini
    depends_on:
      mysql-test:
        condition: service_healthy
      mysql-integration:
        condition: service_healthy
      redis-test:
        condition: service_started
    environment:
      - DB_HOST=mysql-test
      - DB_PORT=3306
      - DB_DATABASE=mulertech_test
      - DB_USERNAME=test_user
      - DB_PASSWORD=test_password
      - DB_INTEGRATION_HOST=mysql-integration
      - DB_INTEGRATION_DATABASE=mulertech_integration
      - DB_INTEGRATION_USERNAME=integration_user
      - DB_INTEGRATION_PASSWORD=integration_password
      - REDIS_HOST=redis-test
      - REDIS_PORT=6379
      - APP_ENV=testing

volumes:
  mysql_test_data:
  redis_test_data:
```

### Dockerfile pour tests

```dockerfile
# docker/php/Dockerfile.test
FROM php:8.2-cli

# Installation des extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        intl \
        gd \
        pcntl \
        sockets

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installation de Xdebug pour la couverture de code
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configuration Xdebug pour les tests
RUN echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Utilisateur non-root pour la sécurité
RUN useradd -m -s /bin/bash testuser
USER testuser

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
```

### Configuration PHP pour tests

```ini
; docker/php/php-test.ini
memory_limit = 512M
max_execution_time = 300
error_reporting = E_ALL
display_errors = On
log_errors = On

; Configuration pour les tests
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000

; Configuration pour Xdebug
xdebug.mode = coverage,debug
xdebug.client_host = host.docker.internal
xdebug.client_port = 9003
xdebug.start_with_request = trigger
```

## Services de test

### Script d'initialisation MySQL pour tests

```sql
-- docker/mysql/test-init.sql
CREATE DATABASE IF NOT EXISTS mulertech_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS mulertech_test_backup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Utilisateur pour les tests
CREATE USER IF NOT EXISTS 'test_user'@'%' IDENTIFIED BY 'test_password';
GRANT ALL PRIVILEGES ON mulertech_test.* TO 'test_user'@'%';
GRANT ALL PRIVILEGES ON mulertech_test_backup.* TO 'test_user'@'%';

-- Utilisateur en lecture seule pour certains tests
CREATE USER IF NOT EXISTS 'readonly_user'@'%' IDENTIFIED BY 'readonly_password';
GRANT SELECT ON mulertech_test.* TO 'readonly_user'@'%';

FLUSH PRIVILEGES;

-- Configuration optimisée pour les tests
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
SET GLOBAL sync_binlog = 0;
SET GLOBAL innodb_buffer_pool_size = 128M;
```

### Service de test manager

```php
<?php

declare(strict_types=1);

namespace Tests\Docker;

use Exception;

class DockerTestManager
{
    private string $composeFile;
    private array $services;

    public function __construct(string $composeFile = 'docker-compose.test.yml')
    {
        $this->composeFile = $composeFile;
        $this->services = ['mysql-test', 'mysql-integration', 'redis-test'];
    }

    public function startServices(): void
    {
        echo "🚀 Démarrage des services de test...\n";
        
        $this->executeCommand("docker-compose -f {$this->composeFile} up -d");
        $this->waitForServices();
        
        echo "✅ Services démarrés avec succès\n";
    }

    public function stopServices(): void
    {
        echo "🛑 Arrêt des services de test...\n";
        
        $this->executeCommand("docker-compose -f {$this->composeFile} down");
        
        echo "✅ Services arrêtés\n";
    }

    public function resetServices(): void
    {
        echo "🔄 Réinitialisation des services...\n";
        
        $this->stopServices();
        $this->executeCommand("docker-compose -f {$this->composeFile} down -v");
        $this->startServices();
        
        echo "✅ Services réinitialisés\n";
    }

    public function getServiceStatus(): array
    {
        $status = [];
        
        foreach ($this->services as $service) {
            $output = $this->executeCommand(
                "docker-compose -f {$this->composeFile} ps -q {$service}",
                false
            );
            
            if (!empty(trim($output))) {
                $containerId = trim($output);
                $inspectOutput = $this->executeCommand(
                    "docker inspect --format='{{.State.Health.Status}}' {$containerId}",
                    false
                );
                
                $status[$service] = [
                    'running' => true,
                    'healthy' => trim($inspectOutput) === 'healthy'
                ];
            } else {
                $status[$service] = ['running' => false, 'healthy' => false];
            }
        }
        
        return $status;
    }

    private function waitForServices(): void
    {
        echo "⏳ Attente que les services soient prêts...\n";
        
        $maxAttempts = 60; // 60 secondes max
        $attempt = 0;
        
        do {
            sleep(1);
            $attempt++;
            
            $allHealthy = true;
            $status = $this->getServiceStatus();
            
            foreach ($status as $service => $serviceStatus) {
                if (!$serviceStatus['running'] || !$serviceStatus['healthy']) {
                    $allHealthy = false;
                    break;
                }
            }
            
            if ($allHealthy) {
                echo "✅ Tous les services sont prêts\n";
                return;
            }
            
            if ($attempt % 10 === 0) {
                echo "⏳ Attente... ({$attempt}s)\n";
            }
            
        } while ($attempt < $maxAttempts);
        
        throw new Exception("Les services n'ont pas démarré dans les temps");
    }

    private function executeCommand(string $command, bool $throwOnError = true): string
    {
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 && $throwOnError) {
            throw new Exception("Erreur d'exécution de la commande: {$command}");
        }
        
        return implode("\n", $output);
    }

    public function executeSqlInContainer(string $service, string $sql): string
    {
        $command = "docker-compose -f {$this->composeFile} exec -T {$service} mysql -u root -proot_password -e \"{$sql}\"";
        return $this->executeCommand($command);
    }

    public function backupDatabase(string $database): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "backup_{$database}_{$timestamp}.sql";
        
        $command = "docker-compose -f {$this->composeFile} exec -T mysql-test mysqldump -u root -proot_password {$database} > {$backupFile}";
        $this->executeCommand($command);
        
        return $backupFile;
    }

    public function restoreDatabase(string $database, string $backupFile): void
    {
        $command = "docker-compose -f {$this->composeFile} exec -T mysql-test mysql -u root -proot_password {$database} < {$backupFile}";
        $this->executeCommand($command);
    }
}
```

## Environnements isolés

### Test avec isolation complète

```php
<?php

declare(strict_types=1);

namespace Tests\Docker;

use PHPUnit\Framework\TestCase;
use MulerTech\Database\EntityManager;
use MulerTech\Database\Configuration\Configuration;

class IsolatedDockerTest extends TestCase
{
    private static DockerTestManager $dockerManager;
    private static bool $servicesStarted = false;

    public static function setUpBeforeClass(): void
    {
        self::$dockerManager = new DockerTestManager();
        
        if (!self::$servicesStarted) {
            self::$dockerManager->startServices();
            self::$servicesStarted = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$servicesStarted) {
            self::$dockerManager->stopServices();
            self::$servicesStarted = false;
        }
    }

    protected function setUp(): void
    {
        // Vérifier que les services sont prêts
        $status = self::$dockerManager->getServiceStatus();
        
        foreach ($status as $service => $serviceStatus) {
            if (!$serviceStatus['running'] || !$serviceStatus['healthy']) {
                $this->markTestSkipped("Service {$service} not ready");
            }
        }
    }

    public function testDatabaseConnection(): void
    {
        $config = new Configuration([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3307, // Port Docker
            'database' => 'mulertech_test',
            'username' => 'test_user',
            'password' => 'test_password',
        ]);

        $entityManager = new EntityManager($config);
        $connection = $entityManager->getConnection();

        $this->assertTrue($connection->ping());
        
        $result = $connection->query('SELECT 1 as test')->fetch();
        $this->assertEquals(1, $result['test']);
    }

    public function testIsolatedTestExecution(): void
    {
        // Chaque test est exécuté dans un environnement isolé
        $containerId = $this->createIsolatedContainer();
        
        try {
            $result = $this->executeInContainer($containerId, [
                'php', 'vendor/bin/phpunit', 'tests/Unit/EntityTest.php'
            ]);
            
            $this->assertStringContains('OK', $result);
            
        } finally {
            $this->removeContainer($containerId);
        }
    }

    private function createIsolatedContainer(): string
    {
        $command = "docker run -d --rm " .
                  "--network mulertech_test_network " .
                  "-v " . getcwd() . ":/app " .
                  "-w /app " .
                  "mulertech/php-test:latest " .
                  "tail -f /dev/null";
        
        $output = [];
        exec($command, $output);
        
        return trim($output[0]);
    }

    private function executeInContainer(string $containerId, array $command): string
    {
        $cmd = "docker exec {$containerId} " . implode(' ', $command);
        
        $output = [];
        exec($cmd, $output);
        
        return implode("\n", $output);
    }

    private function removeContainer(string $containerId): void
    {
        exec("docker stop {$containerId}");
    }
}
```

### Configuration par environnement

```yaml
# docker-compose.test-unit.yml - Tests unitaires rapides
version: '3.8'
services:
  mysql-unit:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: unit_test
      MYSQL_USER: unit_user
      MYSQL_PASSWORD: unit_pass
    ports:
      - "3310:3306"
    tmpfs:
      - /var/lib/mysql  # Base en mémoire pour rapidité
    command: --innodb-flush-log-at-trx-commit=0 --sync-binlog=0

---
# docker-compose.test-integration.yml - Tests d'intégration
version: '3.8'
services:
  mysql-integration:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: integration_test
      MYSQL_USER: integration_user
      MYSQL_PASSWORD: integration_pass
    ports:
      - "3311:3306"
    volumes:
      - mysql_integration_data:/var/lib/mysql
    command: --innodb-buffer-pool-size=256M

  redis-integration:
    image: redis:7-alpine
    ports:
      - "6381:6379"

volumes:
  mysql_integration_data:

---
# docker-compose.test-performance.yml - Tests de performance
version: '3.8'
services:
  mysql-perf:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: perf_test
      MYSQL_USER: perf_user
      MYSQL_PASSWORD: perf_pass
    ports:
      - "3312:3306"
    volumes:
      - mysql_perf_data:/var/lib/mysql
    command: --innodb-buffer-pool-size=1G --innodb-log-file-size=256M
    deploy:
      resources:
        limits:
          memory: 2G
        reservations:
          memory: 1G

volumes:
  mysql_perf_data:
```

## Scripts d'automatisation

### Script principal de test

```bash
#!/bin/bash
# scripts/test-docker.sh

set -e

# Configuration
COMPOSE_FILE="docker-compose.test.yml"
TEST_TYPE="${1:-all}"
COVERAGE="${2:-false}"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Fonctions
start_services() {
    echo_info "Démarrage des services Docker..."
    docker-compose -f $COMPOSE_FILE up -d
    
    echo_info "Attente que les services soient prêts..."
    timeout 60 bash -c 'until docker-compose -f '$COMPOSE_FILE' exec mysql-test mysqladmin ping -h localhost --silent; do sleep 1; done'
    
    echo_info "Services prêts ✅"
}

stop_services() {
    echo_info "Arrêt des services..."
    docker-compose -f $COMPOSE_FILE down
}

cleanup() {
    echo_info "Nettoyage..."
    docker-compose -f $COMPOSE_FILE down -v
    docker system prune -f
}

run_unit_tests() {
    echo_info "Exécution des tests unitaires..."
    
    local cmd="docker-compose -f $COMPOSE_FILE exec -T php-test php vendor/bin/phpunit tests/Unit"
    
    if [ "$COVERAGE" = "true" ]; then
        cmd="$cmd --coverage-html coverage/unit --coverage-clover coverage/unit/clover.xml"
    fi
    
    eval $cmd
}

run_integration_tests() {
    echo_info "Exécution des tests d'intégration..."
    
    local cmd="docker-compose -f $COMPOSE_FILE exec -T php-test php vendor/bin/phpunit tests/Integration"
    
    if [ "$COVERAGE" = "true" ]; then
        cmd="$cmd --coverage-html coverage/integration --coverage-clover coverage/integration/clover.xml"
    fi
    
    eval $cmd
}

run_performance_tests() {
    echo_info "Exécution des tests de performance..."
    
    docker-compose -f $COMPOSE_FILE exec -T php-test php vendor/bin/phpunit tests/Performance --group=performance
}

run_all_tests() {
    echo_info "Exécution de tous les tests..."
    
    local cmd="docker-compose -f $COMPOSE_FILE exec -T php-test php vendor/bin/phpunit"
    
    if [ "$COVERAGE" = "true" ]; then
        cmd="$cmd --coverage-html coverage/all --coverage-clover coverage/all/clover.xml"
    fi
    
    eval $cmd
}

# Gestion des signaux pour nettoyage
trap cleanup EXIT

# Vérifications préliminaires
if ! command -v docker-compose &> /dev/null; then
    echo_error "docker-compose n'est pas installé"
    exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
    echo_error "Fichier $COMPOSE_FILE non trouvé"
    exit 1
fi

# Démarrage des services
start_services

# Exécution des tests selon le type
case $TEST_TYPE in
    "unit")
        run_unit_tests
        ;;
    "integration")
        run_integration_tests
        ;;
    "performance")
        run_performance_tests
        ;;
    "all")
        run_all_tests
        ;;
    *)
        echo_error "Type de test invalide: $TEST_TYPE"
        echo "Types disponibles: unit, integration, performance, all"
        exit 1
        ;;
esac

echo_info "Tests terminés avec succès ✅"
```

### Makefile pour simplifier les commandes

```makefile
# Makefile
.PHONY: test test-unit test-integration test-performance test-coverage docker-start docker-stop docker-reset

# Variables
DOCKER_COMPOSE = docker-compose -f docker-compose.test.yml
PHP_CONTAINER = php-test

# Tests
test:
	@./scripts/test-docker.sh all

test-unit:
	@./scripts/test-docker.sh unit

test-integration:
	@./scripts/test-docker.sh integration

test-performance:
	@./scripts/test-docker.sh performance

test-coverage:
	@./scripts/test-docker.sh all true

# Docker
docker-start:
	$(DOCKER_COMPOSE) up -d
	@echo "Attente que les services soient prêts..."
	@timeout 60 bash -c 'until $(DOCKER_COMPOSE) exec mysql-test mysqladmin ping -h localhost --silent; do sleep 1; done'
	@echo "Services prêts ✅"

docker-stop:
	$(DOCKER_COMPOSE) down

docker-reset:
	$(DOCKER_COMPOSE) down -v
	$(DOCKER_COMPOSE) up -d

# Utilitaires
docker-logs:
	$(DOCKER_COMPOSE) logs -f

docker-shell:
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bash

docker-mysql:
	$(DOCKER_COMPOSE) exec mysql-test mysql -u root -proot_password mulertech_test

# Installation
install:
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer install

# Linting et analyse
lint:
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/phpcs src tests

analyze:
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/phpstan analyze

# Nettoyage
clean:
	$(DOCKER_COMPOSE) down -v
	docker system prune -f
	rm -rf coverage/

# Aide
help:
	@echo "Commandes disponibles:"
	@echo "  make test              - Tous les tests"
	@echo "  make test-unit         - Tests unitaires"
	@echo "  make test-integration  - Tests d'intégration"
	@echo "  make test-performance  - Tests de performance"
	@echo "  make test-coverage     - Tests avec couverture"
	@echo "  make docker-start      - Démarrer les services"
	@echo "  make docker-stop       - Arrêter les services"
	@echo "  make docker-reset      - Réinitialiser les services"
	@echo "  make docker-shell      - Shell dans le container PHP"
	@echo "  make clean             - Nettoyer complètement"
```

## Intégration CI/CD

### GitHub Actions avec Docker

```yaml
# .github/workflows/test-docker.yml
name: Tests Docker

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: [8.1, 8.2, 8.3]
        mysql-version: [8.0, 5.7]

    steps:
    - name: Checkout code
      uses: actions/checkout@v3

    - name: Set up Docker Buildx
      uses: docker/setup-buildx-action@v2

    - name: Build test image
      run: |
        docker build -t mulertech/php-test:${{ matrix.php-version }} \
          --build-arg PHP_VERSION=${{ matrix.php-version }} \
          -f docker/php/Dockerfile.test .

    - name: Start services
      run: |
        export MYSQL_VERSION=${{ matrix.mysql-version }}
        export PHP_VERSION=${{ matrix.php-version }}
        docker-compose -f docker-compose.test.yml up -d

    - name: Wait for services
      run: |
        timeout 60 bash -c 'until docker-compose -f docker-compose.test.yml exec -T mysql-test mysqladmin ping -h localhost --silent; do sleep 1; done'

    - name: Install dependencies
      run: |
        docker-compose -f docker-compose.test.yml exec -T php-test composer install --no-progress --prefer-dist

    - name: Run unit tests
      run: |
        docker-compose -f docker-compose.test.yml exec -T php-test \
          php vendor/bin/phpunit tests/Unit --coverage-clover=coverage.xml

    - name: Run integration tests
      run: |
        docker-compose -f docker-compose.test.yml exec -T php-test \
          php vendor/bin/phpunit tests/Integration

    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
        flags: unittests
        name: codecov-umbrella

    - name: Cleanup
      if: always()
      run: |
        docker-compose -f docker-compose.test.yml down -v
```

## Debugging et monitoring

### Configuration de debugging

```yaml
# docker-compose.debug.yml
version: '3.8'

services:
  php-debug:
    build:
      context: .
      dockerfile: docker/php/Dockerfile.debug
    volumes:
      - .:/app
      - ./docker/php/xdebug.ini:/usr/local/etc/php/conf.d/xdebug.ini
    environment:
      - XDEBUG_CONFIG=client_host=host.docker.internal
      - PHP_IDE_CONFIG=serverName=docker
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

### Monitoring des tests

```php
<?php
// tests/Support/TestMonitor.php

declare(strict_types=1);

namespace Tests\Support;

class TestMonitor
{
    private array $metrics = [];
    private float $startTime;

    public function startMonitoring(): void
    {
        $this->startTime = microtime(true);
        $this->metrics = [
            'memory_start' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => 0,
            'queries_executed' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
        ];
    }

    public function stopMonitoring(): array
    {
        $this->metrics['execution_time'] = microtime(true) - $this->startTime;
        $this->metrics['memory_end'] = memory_get_usage(true);
        $this->metrics['peak_memory'] = memory_get_peak_usage(true);
        
        return $this->metrics;
    }

    public function recordQuery(): void
    {
        $this->metrics['queries_executed']++;
    }

    public function recordCacheHit(): void
    {
        $this->metrics['cache_hits']++;
    }

    public function recordCacheMiss(): void
    {
        $this->metrics['cache_misses']++;
    }

    public function generateReport(): string
    {
        $report = "=== Test Performance Report ===\n";
        $report .= "Execution Time: " . number_format($this->metrics['execution_time'], 4) . "s\n";
        $report .= "Memory Usage: " . $this->formatBytes($this->metrics['memory_end'] - $this->metrics['memory_start']) . "\n";
        $report .= "Peak Memory: " . $this->formatBytes($this->metrics['peak_memory']) . "\n";
        $report .= "Queries Executed: " . $this->metrics['queries_executed'] . "\n";
        $report .= "Cache Hits: " . $this->metrics['cache_hits'] . "\n";
        $report .= "Cache Misses: " . $this->metrics['cache_misses'] . "\n";
        
        return $report;
    }

    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        
        while ($size >= 1024 && $unit < 3) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }
}
```

---

**Voir aussi :**
- [Tests unitaires](unit-tests.md)
- [Tests d'intégration](integration-tests.md)
- [Utilitaires de test](test-utilities.md)
