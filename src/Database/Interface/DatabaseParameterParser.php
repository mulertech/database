<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use RuntimeException;

/**
 * Handles parsing and population of database parameters from environment variables and URLs
 */
class DatabaseParameterParser
{
    public const string DATABASE_URL = 'DATABASE_URL';

    /**
     * Parse DATABASE_URL or individual environment variables
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function populateParameters(array $parameters = []): array
    {
        if (!empty($parameters[self::DATABASE_URL])) {
            return $this->parseFromUrl($parameters[self::DATABASE_URL]);
        }

        return $this->populateFromEnvironment($parameters);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFromUrl(mixed $url): array
    {
        if (!is_string($url)) {
            throw new RuntimeException('DATABASE_URL must be a string');
        }

        $parsedUrl = parse_url($url);
        if ($parsedUrl === false) {
            throw new RuntimeException('Invalid DATABASE_URL format.');
        }

        // Decode URL components inline
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
    private function populateFromEnvironment(array $parameters = []): array
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
            if ($value === false) {
                continue;
            }

            $parameters[$paramKey] = ($paramKey === 'port') ? (int)$value : $value;
        }

        $parameters = $this->processPathParameter($parameters);
        $parameters = $this->processQueryParameter($parameters);

        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function processPathParameter(array $parameters): array
    {
        if (!isset($parameters['path'])) {
            return $parameters;
        }

        if (is_string($parameters['path']) || is_numeric($parameters['path'])) {
            $parameters['dbname'] = substr((string)$parameters['path'], 1);
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function processQueryParameter(array $parameters): array
    {
        if (!isset($parameters['query'])) {
            return $parameters;
        }

        if (is_string($parameters['query']) || is_numeric($parameters['query'])) {
            parse_str((string)$parameters['query'], $parsedQuery);
            // Ensure all keys are strings to maintain array<string, mixed> type
            foreach ($parsedQuery as $key => $value) {
                $parameters[(string)$key] = $value;
            }
        }

        return $parameters;
    }
}
