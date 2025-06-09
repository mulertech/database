<?php

declare(strict_types=1);

namespace MulerTech\Database\Command;

use MulerTech\Database\Cache\CacheManager;
use MulerTech\Database\Debug\CacheDashboard;

/**
 * CLI commands for cache management
 * @package MulerTech\Database\Command
 * @author Sébastien Muler
 */
class CacheCommands
{
    /**
     * @var CacheManager
     */
    private readonly CacheManager $cacheManager;

    /**
     * @var CacheDashboard
     */
    private readonly CacheDashboard $dashboard;

    /**
     * @param CacheManager|null $cacheManager
     */
    public function __construct(?CacheManager $cacheManager = null)
    {
        $this->cacheManager = $cacheManager ?? CacheManager::getInstance();
        $this->dashboard = new CacheDashboard($this->cacheManager);
    }

    /**
     * Clear all caches or specific cache
     *
     * @param string|null $cacheName
     * @return int
     */
    public function clear(?string $cacheName = null): int
    {
        try {
            if ($cacheName === null || $cacheName === 'all') {
                $this->cacheManager->clearAll();
                $this->output("✓ All caches cleared successfully");
            } else {
                $cache = $this->cacheManager->getCache($cacheName);
                if ($cache === null) {
                    $this->error("Cache '$cacheName' not found");
                    $this->output("Available caches: " . implode(', ', $this->getAvailableCaches()));
                    return 1;
                }

                $cache->clear();
                $this->output("✓ Cache '$cacheName' cleared successfully");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to clear cache: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display cache statistics
     *
     * @param string|null $format
     * @return int
     */
    public function stats(?string $format = 'table'): int
    {
        try {
            $stats = $this->cacheManager->getStats();

            switch ($format) {
                case 'json':
                    $json = json_encode($stats, JSON_PRETTY_PRINT);
                    if ($json !== false) {
                        $this->output($json);
                    } else {
                        $this->error("Erreur d'encodage JSON des statistiques.");
                        return 1;
                    }
                    break;

                case 'csv':
                    $this->outputCsv($stats);
                    break;

                case 'table':
                default:
                    $this->outputTable($stats);
                    break;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to get stats: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Invalidate cache by table or pattern
     *
     * @param string $target
     * @param string $type
     * @return int
     */
    public function invalidate(string $target, string $type = 'table'): int
    {
        try {
            switch ($type) {
                case 'table':
                    $this->cacheManager->invalidateTable($target);
                    $this->output("✓ Invalidated cache for table: $target");
                    break;

                case 'tables':
                    $tables = explode(',', $target);
                    $this->cacheManager->invalidateTables($tables);
                    $this->output("✓ Invalidated cache for tables: " . implode(', ', $tables));
                    break;

                case 'pattern':
                    $this->cacheManager->invalidate($target);
                    $this->output("✓ Invalidated cache matching pattern: $target");
                    break;

                default:
                    $this->error("Unknown invalidation type: $type");
                    $this->output("Valid types: table, tables, pattern");
                    return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to invalidate cache: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Warm up caches
     *
     * @param string|null $cacheName
     * @return int
     */
    public function warmup(?string $cacheName = null): int
    {
        try {
            $this->output("Warming up caches...");

            $this->cacheManager->warmUp($cacheName);

            if ($cacheName) {
                $this->output("✓ Cache '$cacheName' warmed up successfully");
            } else {
                $this->output("✓ All caches warmed up successfully");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to warm up cache: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display cache health check
     *
     * @return int
     */
    public function health(): int
    {
        try {
            $health = $this->cacheManager->getHealthCheck();

            // Display status with color
            $statusColor = match($health['status']) {
                'healthy' => "\033[32m", // Green
                'warning' => "\033[33m", // Yellow
                'critical' => "\033[31m", // Red
                default => "\033[0m",
            };

            $this->output($statusColor . "Status: " . strtoupper($health['status']) . "\033[0m");

            if (!empty($health['message'])) {
                $this->output($health['message']);
            }

            if (!empty($health['issues'])) {
                $this->output("\nIssues:");
                foreach ($health['issues'] as $issue) {
                    $this->output("  ⚠ " . $issue);
                }
            }

            if (!empty($health['recommendations'])) {
                $this->output("\nRecommendations:");
                foreach ($health['recommendations'] as $rec) {
                    $this->output("  → " . $rec);
                }
            }

            return $health['status'] === 'critical' ? 1 : 0;
        } catch (\Exception $e) {
            $this->error("Failed to check health: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Start interactive cache monitor
     *
     * @return int
     */
    public function monitor(): int
    {
        $this->output("Cache Monitor - Press Ctrl+C to exit\n");

        $running = true;
        try {
            while ($running) {
                // Clear screen
                $this->clearScreen();

                // Display dashboard
                $data = $this->dashboard->render();

                // Show summary
                $this->output("=== Cache Monitor ===");
                $this->output("Status: " . $data['health']['status']);
                $this->output("");

                // Show metrics
                foreach ($data['summary'] as $key => $value) {
                    $this->output(str_pad($this->formatLabel($key) . ':', 20) . $value);
                }

                // Show cache details
                $this->output("\n=== Cache Details ===");
                $this->outputTable(['_details' => $data['details']]);

                // Show alerts if any
                if (!empty($data['alerts'])) {
                    $this->output("\n=== Alerts ===");
                    foreach ($data['alerts'] as $alert) {
                        $this->output("[{$alert['level']}] {$alert['cache']}: {$alert['message']}");
                    }
                }

                // Wait 5 seconds before refresh
                sleep(5);

                // Check if the user wants to exit
                if (connection_aborted()) {
                    $running = false;
                }
            }
        } catch (\Exception $e) {
            $this->error("\nMonitor stopped: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Get list of available caches
     *
     * @return array<string>
     */
    private function getAvailableCaches(): array
    {
        $stats = $this->cacheManager->getStats();
        $caches = [];

        foreach ($stats as $name => $data) {
            if ($name !== '_global') {
                $caches[] = $name;
            }
        }

        return $caches;
    }

    /**
     * Output table format
     *
     * @param array<string, mixed> $stats
     * @return void
     */
    private function outputTable(array $stats): void
    {
        // Global stats
        if (isset($stats['_global'])) {
            $this->output("=== Global Statistics ===");
            foreach ($stats['_global'] as $key => $value) {
                $this->output(str_pad($this->formatLabel($key) . ':', 30) . $this->formatValue($value));
            }
            $this->output("");
        }

        // Individual cache stats
        $this->output("=== Cache Statistics ===");
        $this->output(str_pad("Cache", 15) .
                      str_pad("Type", 12) .
                      str_pad("Size", 10) .
                      str_pad("Hits", 10) .
                      str_pad("Misses", 10) .
                      str_pad("Hit Rate", 10) .
                      "Evictions");
        $this->output(str_repeat("-", 80));

        foreach ($stats as $name => $data) {
            if ($name === '_global' || $name === '_details') {
                continue;
            }

            $this->output(
                str_pad($name, 15) .
                str_pad($data['type'] ?? 'N/A', 12) .
                str_pad((string)($data['size'] ?? 0), 10) .
                str_pad((string)($data['hits'] ?? 0), 10) .
                str_pad((string)($data['misses'] ?? 0), 10) .
                str_pad(number_format($data['hitRate'] ?? 0, 1) . '%', 10) .
                ($data['evictions'] ?? 0)
            );
        }
    }

    /**
     * Output CSV format
     *
     * @param array<string, mixed> $stats
     * @return void
     */
    private function outputCsv(array $stats): void
    {
        // Header
        $this->output("cache,type,size,hits,misses,hit_rate,evictions");

        // Data
        foreach ($stats as $name => $data) {
            if ($name === '_global') {
                continue;
            }

            $this->output(implode(',', [
                $name,
                $data['type'] ?? 'N/A',
                $data['size'] ?? 0,
                $data['hits'] ?? 0,
                $data['misses'] ?? 0,
                number_format($data['hitRate'] ?? 0, 2),
                $data['evictions'] ?? 0,
            ]));
        }
    }

    /**
     * Format label for display
     *
     * @param string $key
     * @return string
     */
    private function formatLabel(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Format value for display
     *
     * @param mixed $value
     * @return string
     */
    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_float($value)) {
            return number_format($value, 2);
        }

        if (is_numeric($value) && $value > 1000) {
            return number_format((float)$value);
        }

        return (string) $value;
    }

    /**
     * Clear terminal screen
     *
     * @return void
     */
    private function clearScreen(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }
    }

    /**
     * Output message
     *
     * @param string $message
     * @return void
     */
    private function output(string $message): void
    {
        echo $message . PHP_EOL;
    }

    /**
     * Output error message
     *
     * @param string $message
     * @return void
     */
    private function error(string $message): void
    {
        fwrite(STDERR, "\033[31m✗ " . $message . "\033[0m" . PHP_EOL);
    }
}
