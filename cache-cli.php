#!/usr/bin/env php
<?php

declare(strict_types=1);

use MulerTech\Database\Command\CacheCommands;

// Autoloader
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

$autoloaderFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    fwrite(STDERR, "Autoloader not found. Please run 'composer install'.\n");
    exit(1);
}

// Parse command line arguments
$args = array_slice($argv, 1);
$command = $args[0] ?? 'help';
$params = array_slice($args, 1);

// Initialize cache commands
$cacheCommands = new CacheCommands();

// Execute command
try {
    switch ($command) {
        case 'clear':
            $cacheName = $params[0] ?? null;
            exit($cacheCommands->clear($cacheName));

        case 'stats':
            $format = $params[0] ?? 'table';
            exit($cacheCommands->stats($format));

        case 'invalidate':
            if (empty($params[0])) {
                fwrite(STDERR, "Error: Missing target for invalidation\n");
                fwrite(STDERR, "Usage: cache invalidate <target> [type]\n");
                exit(1);
            }
            $target = $params[0];
            $type = $params[1] ?? 'table';
            exit($cacheCommands->invalidate($target, $type));

        case 'warmup':
            $cacheName = $params[0] ?? null;
            exit($cacheCommands->warmup($cacheName));

        case 'health':
            exit($cacheCommands->health());

        case 'monitor':
            exit($cacheCommands->monitor());

        case 'help':
        default:
            showHelp();
            exit(0);
    }
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Show help message
 */
function showHelp(): void
{
    $help = <<<HELP
MulerTech Database Cache Management Tool

Usage:
  cache <command> [options]

Commands:
  clear [cache]              Clear all caches or specific cache
                            Examples:
                              cache clear          # Clear all caches
                              cache clear all      # Clear all caches
                              cache clear metadata # Clear metadata cache

  stats [format]            Display cache statistics
                            Formats: table (default), json, csv
                            Examples:
                              cache stats
                              cache stats json
                              cache stats csv

  invalidate <target> [type] Invalidate cache by target
                            Types: table (default), tables, pattern
                            Examples:
                              cache invalidate users
                              cache invalidate users,products tables
                              cache invalidate "temp:*" pattern

  warmup [cache]            Warm up caches
                            Examples:
                              cache warmup         # Warm up all caches
                              cache warmup metadata

  health                    Display cache health check
                            Shows status, issues, and recommendations

  monitor                   Start interactive cache monitor
                            Real-time display of cache statistics
                            Press Ctrl+C to exit

  help                      Show this help message

Examples:
  # Clear all caches
  ./cache clear

  # View statistics in JSON format
  ./cache stats json

  # Invalidate cache for users table
  ./cache invalidate users

  # Check cache health
  ./cache health

  # Monitor cache in real-time
  ./cache monitor

HELP;

    echo $help;
}