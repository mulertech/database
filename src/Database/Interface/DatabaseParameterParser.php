<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use RuntimeException;

/**
 * Handles parsing of database connection parameters from various sources
 */
class DatabaseParameterParser implements DatabaseParameterParserInterface
{
    public const string DATABASE_URL = 'DATABASE_URL';

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function parseParameters(array $parameters = []): array
    {
        if (!empty($parameters[self::DATABASE_URL])) {
            return $this->parseDatabaseUrl($parameters[self::DATABASE_URL]);
        }

        return $this->parseEnvironmentVariables($parameters);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDatabaseUrl(mixed $url): array
    {
        if (!is_string($url)) {
            throw new RuntimeException('DATABASE_URL must be a string');
        }

        $parsedUrl = parse_url($url);
        if ($parsedUrl === false) {
            throw new RuntimeException('Invalid DATABASE_URL format.');
        }

        // Decode URL components
        array_walk($parsedUrl, static function (&$urlPart) {
            if (is_string($urlPart)) {
                $urlPart = urldecode($urlPart);
            }
        });

        $parsedParams = $parsedUrl;

        if (isset($parsedParams['path'])) {
            $parsedParams['dbname'] = substr((string)$parsedParams['path'], 1);
        }

        if (isset($parsedParams['query'])) {
            parse_str((string)$parsedParams['query'], $parsedQuery);
            $parsedParams = array_merge($parsedParams, $parsedQuery);
        }

        return array_combine(
            array_map('strval', array_keys($parsedParams)),
            array_values($parsedParams)
        );
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function parseEnvironmentVariables(array $parameters = []): array
    {
        $envMappings = [
            'DATABASE_SCHEME' => 'scheme',
            'DATABASE_HOST' => 'host',
            'DATABASE_PORT' => 'port',
            'DATABASE_USER' => 'user',
            'DATABASE_PASS' => 'pass',
            'DATABASE_PATH' => 'path',
            'DATABASE_QUERY' => 'query',
            'DATABASE_FRAGMENT' => 'fragment',
        ];

        foreach ($envMappings as $envKey => $paramKey) {
            $value = getenv($envKey);
            if ($value !== false) {
                $parameters[$paramKey] = ($paramKey === 'port') ? (int)$value : $value;
            }
        }

        return $this->processSpecialParameters($parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function processSpecialParameters(array $parameters): array
    {
        // Special handling for database name from path
        if (isset($parameters['path']) && (is_string($parameters['path']) || is_numeric($parameters['path']))) {
            $parameters['dbname'] = substr((string)$parameters['path'], 1);
        }

        // Parse query string
        if (isset($parameters['query']) && (is_string($parameters['query']) || is_numeric($parameters['query']))) {
            parse_str((string)$parameters['query'], $parsedQuery);
            // Ensure all keys are strings
            foreach ($parsedQuery as $key => $value) {
                $parameters[(string)$key] = $value;
            }
        }

        return $parameters;
    }
}
