<?php

declare(strict_types=1);

namespace MulerTech\Database\Debug;

use MulerTech\Database\Cache\CacheManager;

/**
 * Cache monitoring dashboard
 * @package MulerTech\Database\Debug
 * @author Sébastien Muler
 */
class CacheDashboard
{
    /**
     * @var CacheManager
     */
    private readonly CacheManager $cacheManager;

    /**
     * @var array<string, mixed>
     */
    private array $thresholds;

    /**
     * @param CacheManager|null $cacheManager
     * @param array<string, mixed> $thresholds
     */
    public function __construct(
        ?CacheManager $cacheManager = null,
        array $thresholds = []
    ) {
        $this->cacheManager = $cacheManager ?? CacheManager::getInstance();
        $this->thresholds = array_merge([
                                            'hit_rate_warning' => 0.5,
                                            'hit_rate_critical' => 0.3,
                                            'eviction_rate_warning' => 0.1,
                                            'eviction_rate_critical' => 0.2,
                                            'memory_usage_warning' => 0.7,
                                            'memory_usage_critical' => 0.9,
                                        ], $thresholds);
    }

    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        $stats = $this->cacheManager->getStats();
        $health = $this->cacheManager->getHealthCheck();

        return [
            'summary' => $this->generateSummary($stats),
            'details' => $this->generateDetailedStats($stats),
            'health' => $health,
            'recommendations' => $this->generateRecommendations($stats, $health),
            'graphs' => $this->generateGraphData($stats),
            'alerts' => $this->generateAlerts($stats),
        ];
    }

    /**
     * @return string
     */
    public function renderHtml(): string
    {
        $data = $this->render();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cache Dashboard - MulerTech Database</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                .container { max-width: 1200px; margin: 0 auto; }
                .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .metric { display: inline-block; margin: 10px 20px; }
                .metric-value { font-size: 24px; font-weight: bold; }
                .metric-label { color: #666; font-size: 14px; }
                .status { padding: 5px 10px; border-radius: 4px; color: white; font-weight: bold; }
                .status-healthy { background: #4CAF50; }
                .status-warning { background: #FF9800; }
                .status-critical { background: #F44336; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f0f0f0; font-weight: bold; }
                .progress { width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden; }
                .progress-bar { height: 100%; background: #4CAF50; transition: width 0.3s; }
                .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
                .alert-warning { background: #FFF3CD; color: #856404; border: 1px solid #FFEEBA; }
                .alert-critical { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }
            </style>
        </head>
        <body>
        <div class="container">
            <h1>Cache Dashboard</h1>

            <!-- Summary Card -->
            <div class="card">
                <h2>Summary</h2>
                <div class="status status-<?= strtolower($data['health']['status']) ?>">
                    <?= ucfirst($data['health']['status']) ?>
                </div>

                <?php foreach ($data['summary'] as $key => $value): ?>
                    <div class="metric">
                        <div class="metric-value"><?= $this->formatValue($value) ?></div>
                        <div class="metric-label"><?= $this->formatLabel($key) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Alerts -->
            <?php if (!empty($data['alerts'])): ?>
                <div class="card">
                    <h2>Alerts</h2>
                    <?php foreach ($data['alerts'] as $alert): ?>
                        <div class="alert alert-<?= $alert['level'] ?>">
                            <strong><?= $alert['cache'] ?>:</strong> <?= $alert['message'] ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Cache Details -->
            <div class="card">
                <h2>Cache Details</h2>
                <table>
                    <thead>
                    <tr>
                        <th>Cache</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Hits</th>
                        <th>Misses</th>
                        <th>Hit Rate</th>
                        <th>Evictions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data['details'] as $cache): ?>
                        <tr>
                            <td><?= $cache['name'] ?></td>
                            <td><?= $cache['type'] ?></td>
                            <td><?= number_format($cache['size']) ?></td>
                            <td><?= number_format($cache['hits']) ?></td>
                            <td><?= number_format($cache['misses']) ?></td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?= $cache['hit_rate'] ?>%"></div>
                                </div>
                                <?= number_format($cache['hit_rate'], 1) ?>%
                            </td>
                            <td><?= number_format($cache['evictions']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recommendations -->
            <?php if (!empty($data['recommendations'])): ?>
                <div class="card">
                    <h2>Recommendations</h2>
                    <ul>
                        <?php foreach ($data['recommendations'] as $rec): ?>
                            <li><?= $rec ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Auto-refresh -->
            <script>
                setTimeout(() => location.reload(), 30000); // Refresh every 30 seconds
            </script>
        </div>
        </body>
        </html>
        <?php

        $output = ob_get_clean();
        return $output !== false ? $output : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function renderJson(): array
    {
        return $this->render();
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function generateSummary(array $stats): array
    {
        $global = $stats['_global'] ?? [];

        return [
            'total_requests' => $global['total_requests'] ?? 0,
            'global_hit_rate' => round(($global['global_hit_rate'] ?? 0) * 100, 1) . '%',
            'total_cached_items' => $global['total_cached_items'] ?? 0,
            'memory_usage' => $this->formatBytes($global['memory_usage_estimate'] ?? 0),
            'total_evictions' => $global['total_evictions'] ?? 0,
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<array<string, mixed>>
     */
    private function generateDetailedStats(array $stats): array
    {
        $details = [];

        foreach ($stats as $name => $cacheStats) {
            if ($name === '_global') {
                continue;
            }

            $details[] = [
                'name' => $name,
                'type' => $cacheStats['type'] ?? 'unknown',
                'size' => $cacheStats['size'] ?? 0,
                'hits' => $cacheStats['hits'] ?? 0,
                'misses' => $cacheStats['misses'] ?? 0,
                'hit_rate' => round(($cacheStats['hitRate'] ?? 0) * 100, 1),
                'evictions' => $cacheStats['evictions'] ?? 0,
                'config' => $cacheStats['config'] ?? [],
            ];
        }

        // Sort by hit rate (ascending) to show problematic caches first
        usort($details, fn ($a, $b) => $a['hit_rate'] <=> $b['hit_rate']);

        return $details;
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $health
     * @return array<string>
     */
    private function generateRecommendations(array $stats, array $health): array
    {
        $recommendations = $health['recommendations'] ?? [];

        // Add custom recommendations based on stats
        foreach ($stats as $name => $cacheStats) {
            if ($name === '_global') {
                continue;
            }

            // Check for specific patterns
            if (isset($cacheStats['type'])) {
                switch ($cacheStats['type']) {
                    case 'memory':
                        if (($cacheStats['evictions'] ?? 0) > 100) {
                            $recommendations[] = "Consider increasing max_size for '$name' cache to reduce evictions";
                        }
                        break;

                    case 'resultset':
                        if (($cacheStats['hitRate'] ?? 1) < 0.3) {
                            $recommendations[] = "ResultSet cache '$name' has low hit rate - review query patterns";
                        }
                        break;

                    case 'metadata':
                        if (($cacheStats['size'] ?? 0) === 0) {
                            $recommendations[] = "Metadata cache is empty - ensure entities are being hydrated";
                        }
                        break;
                }
            }
        }

        return array_unique($recommendations);
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private function generateGraphData(array $stats): array
    {
        $labels = [];
        $hitRates = [];
        $sizes = [];

        foreach ($stats as $name => $cacheStats) {
            if ($name === '_global') {
                continue;
            }

            $labels[] = $name;
            $hitRates[] = round(($cacheStats['hitRate'] ?? 0) * 100, 1);
            $sizes[] = $cacheStats['size'] ?? 0;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Hit Rate (%)',
                    'data' => $hitRates,
                ],
                [
                    'label' => 'Cache Size',
                    'data' => $sizes,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @return array<array<string, string>>
     */
    private function generateAlerts(array $stats): array
    {
        $alerts = [];

        foreach ($stats as $name => $cacheStats) {
            if ($name === '_global') {
                continue;
            }

            $hitRate = $cacheStats['hitRate'] ?? 1;

            // Check hit rate
            if ($hitRate < $this->thresholds['hit_rate_critical']) {
                $alerts[] = [
                    'level' => 'critical',
                    'cache' => $name,
                    'message' => 'Critical: Hit rate is ' . round($hitRate * 100, 1) . '%',
                ];
            } elseif ($hitRate < $this->thresholds['hit_rate_warning']) {
                $alerts[] = [
                    'level' => 'warning',
                    'cache' => $name,
                    'message' => 'Warning: Hit rate is ' . round($hitRate * 100, 1) . '%',
                ];
            }

            // Check eviction rate
            if (isset($cacheStats['evictions']) && isset($cacheStats['size']) && $cacheStats['size'] > 0) {
                $evictionRate = $cacheStats['evictions'] / ($cacheStats['size'] + $cacheStats['evictions']);

                if ($evictionRate > $this->thresholds['eviction_rate_critical']) {
                    $alerts[] = [
                        'level' => 'critical',
                        'cache' => $name,
                        'message' => 'Critical: High eviction rate (' . round($evictionRate * 100, 1) . '%)',
                    ];
                } elseif ($evictionRate > $this->thresholds['eviction_rate_warning']) {
                    $alerts[] = [
                        'level' => 'warning',
                        'cache' => $name,
                        'message' => 'Warning: Moderate eviction rate (' . round($evictionRate * 100, 1) . '%)',
                    ];
                }
            }
        }

        return $alerts;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function formatValue(mixed $value): string
    {
        if (is_numeric($value) && $value > 1000) {
            return number_format((float)$value);
        }

        return (string) $value;
    }

    /**
     * @param string $key
     * @return string
     */
    private function formatLabel(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /**
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
