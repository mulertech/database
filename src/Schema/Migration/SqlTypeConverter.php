<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

/**
 * Class SqlTypeConverter
 *
 * Converts SQL types to SchemaBuilder method calls
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SqlTypeConverter
{
    /**
     * Convert SQL type definition to SchemaBuilder method call
     *
     * @param string $sqlType
     * @return string
     */
    public function convertToBuilderMethod(string $sqlType): string
    {
        return $this->handleIntegerTypes($sqlType)
            ?? $this->handleStringTypes($sqlType)
            ?? $this->handleDecimalTypes($sqlType)
            ?? $this->handleBinaryTypes($sqlType)
            ?? $this->handleBlobTypes($sqlType)
            ?? $this->handleTextTypes($sqlType)
            ?? $this->handleDateTimeTypes($sqlType)
            ?? $this->handleBooleanTypes($sqlType)
            ?? $this->handleJsonTypes($sqlType)
            ?? $this->handleEnumSetTypes($sqlType)
            ?? $this->handleGeometryTypes($sqlType)
            ?? '->string()'; // Default fallback
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleIntegerTypes(string $sqlType): ?string
    {
        if (preg_match('/^(tiny|small|medium|big)?int(\(\d+\))?\s*(unsigned)?/i', $sqlType, $matches)) {
            $size = strtolower($matches[1] ?? '');
            $unsigned = isset($matches[3]);

            $method = match($size) {
                'tiny' => '->tinyInt()',
                'small' => '->smallInt()',
                'medium' => '->mediumInt()',
                'big' => '->bigInteger()',
                default => '->integer()'
            };

            return $unsigned ? $method . '->unsigned()' : $method;
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleStringTypes(string $sqlType): ?string
    {
        if (preg_match('/^varchar\((\d+)\)/i', $sqlType, $matches)) {
            return '->string(' . $matches[1] . ')';
        }
        if (preg_match('/^char\((\d+)\)/i', $sqlType, $matches)) {
            return '->char(' . $matches[1] . ')';
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleDecimalTypes(string $sqlType): ?string
    {
        if (preg_match('/^decimal\((\d+),(\d+)\)/i', $sqlType, $matches)) {
            return '->decimal(' . $matches[1] . ', ' . $matches[2] . ')';
        }
        if (preg_match('/^float\((\d+),(\d+)\)/i', $sqlType, $matches)) {
            return '->float(' . $matches[1] . ', ' . $matches[2] . ')';
        }
        if (stripos($sqlType, 'double') === 0) {
            return '->double()';
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleBinaryTypes(string $sqlType): ?string
    {
        if (preg_match('/^binary\((\d+)\)/i', $sqlType, $matches)) {
            return '->binary(' . $matches[1] . ')';
        }
        if (preg_match('/^varbinary\((\d+)\)/i', $sqlType, $matches)) {
            return '->varbinary(' . $matches[1] . ')';
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleBlobTypes(string $sqlType): ?string
    {
        $blobTypes = [
            'tinyblob' => '->tinyBlob()',
            'mediumblob' => '->mediumBlob()',
            'longblob' => '->longBlob()',
            'blob' => '->blob()',
        ];

        foreach ($blobTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleTextTypes(string $sqlType): ?string
    {
        $textTypes = [
            'tinytext' => '->tinyText()',
            'mediumtext' => '->mediumText()',
            'longtext' => '->longText()',
            'text' => '->text()',
        ];

        foreach ($textTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleDateTimeTypes(string $sqlType): ?string
    {
        $dateTimeTypes = [
            'datetime' => '->datetime()',
            'timestamp' => '->timestamp()',
            'date' => '->date()',
            'time' => '->time()',
            'year' => '->year()',
        ];

        foreach ($dateTimeTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleBooleanTypes(string $sqlType): ?string
    {
        if (stripos($sqlType, 'boolean') === 0 || stripos($sqlType, 'bool') === 0) {
            return '->boolean()';
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleJsonTypes(string $sqlType): ?string
    {
        if (stripos($sqlType, 'json') === 0) {
            return '->json()';
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleEnumSetTypes(string $sqlType): ?string
    {
        if (preg_match('/^enum\((.*)\)/i', $sqlType, $matches)) {
            $enumValues = $this->parseEnumSetValues($matches[1]);
            return '->enum([' . implode(', ', array_map(static fn ($enumValue) => "'" . addslashes($enumValue) . "'", $enumValues)) . '])';
        }

        if (preg_match('/^set\((.*)\)/i', $sqlType, $matches)) {
            $setValues = $this->parseEnumSetValues($matches[1]);
            return '->set([' . implode(', ', array_map(static fn ($setValue) => "'" . addslashes($setValue) . "'", $setValues)) . '])';
        }
        return null;
    }

    /**
     * @param string $sqlType
     * @return string|null
     */
    private function handleGeometryTypes(string $sqlType): ?string
    {
        $geometryTypes = [
            'geometry' => '->geometry()',
            'point' => '->point()',
            'linestring' => '->lineString()',
            'polygon' => '->polygon()',
            'multipoint' => '->multiPoint()',
            'multilinestring' => '->multiLineString()',
            'multipolygon' => '->multiPolygon()',
            'geometrycollection' => '->geometryCollection()',
        ];

        foreach ($geometryTypes as $type => $method) {
            if (stripos($sqlType, $type) === 0) {
                return $method;
            }
        }
        return null;
    }

    /**
     * Parse ENUM/SET values from SQL definition
     *
     * Simplified approach using token extraction
     *
     * @param string $values Raw ENUM/SET values string like "'value1','value2','val''ue3'"
     * @return array<string> Array of parsed values
     */
    private function parseEnumSetValues(string $values): array
    {
        $values = trim($values);

        if (empty($values)) {
            return [];
        }

        $result = [];
        $tokens = $this->extractQuotedTokens($values);

        foreach ($tokens as $token) {
            $result[] = $this->cleanQuotedValue($token);
        }

        return $result;
    }

    /**
     * Extract quoted tokens from the values string
     *
     * @param string $values
     * @return array<string>
     */
    private function extractQuotedTokens(string $values): array
    {
        $tokens = [];
        $position = 0;

        while ($position < strlen($values)) {
            // Skip whitespace and commas
            $position += strspn($values, ' ,', $position);

            if ($position >= strlen($values)) {
                break;
            }

            // Find the quoted token
            $token = $this->extractSingleQuotedToken($values, $position);
            if ($token !== null) {
                $tokens[] = $token['value'];
                $position = $token['endPosition'];
                continue;
            }

            $position++;
        }

        return $tokens;
    }

    /**
     * Extract a single quoted token starting at the given position
     *
     * @param string $values
     * @param int $position
     * @return array{value: string, endPosition: int}|null
     */
    private function extractSingleQuotedToken(string $values, int &$position): ?array
    {
        if (!isset($values[$position]) || ($values[$position] !== "'" && $values[$position] !== '"')) {
            return null;
        }

        $quote = $values[$position];
        $start = $position;
        $position++; // Move past opening quote

        // Find closing quote, handling escaped quotes
        while ($position < strlen($values)) {
            if ($values[$position] !== $quote) {
                $position++;
                continue;
            }

            if ($position + 1 < strlen($values) && $values[$position + 1] === $quote) {
                $position += 2; // Skip escaped quote pair
                continue;
            }

            $position++; // Move past closing quote
            break;
        }

        return [
            'value' => substr($values, $start, $position - $start),
            'endPosition' => $position,
        ];
    }

    /**
     * Clean and unescape a quoted value
     *
     * @param string $quotedValue Quoted string like "'value'" or "'val''ue'"
     * @return string Clean unquoted value
     */
    private function cleanQuotedValue(string $quotedValue): string
    {
        if (strlen($quotedValue) < 2) {
            return $quotedValue;
        }

        // Remove outer quotes
        $content = substr($quotedValue, 1, -1);
        $quote = $quotedValue[0];

        // Replace escaped quotes ('' becomes ' or "" becomes ")
        return str_replace($quote . $quote, $quote, $content);
    }
}
