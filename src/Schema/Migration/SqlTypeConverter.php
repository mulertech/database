<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

/**
 * Class SqlTypeConverter
 *
 * Converts SQL types to SchemaBuilder method calls
 *
 * @package MulerTech\Database\Schema\Migration
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

    private function handleBooleanTypes(string $sqlType): ?string
    {
        if (stripos($sqlType, 'boolean') === 0 || stripos($sqlType, 'bool') === 0) {
            return '->boolean()';
        }
        return null;
    }

    private function handleJsonTypes(string $sqlType): ?string
    {
        if (stripos($sqlType, 'json') === 0) {
            return '->json()';
        }
        return null;
    }

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
     * @param string $values
     * @return array<string>
     */
    private function parseEnumSetValues(string $values): array
    {
        $parsed = [];
        $values = trim($values);
        $inQuotes = false;
        $currentValue = '';
        $quoteChar = null;

        for ($i = 0; $i < strlen($values); $i++) {
            $char = $values[$i];

            if (!$inQuotes && ($char === "'" || $char === '"')) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar) {
                if ($i < strlen($values) - 1 && $values[$i + 1] === $quoteChar) {
                    // Escaped quote
                    $currentValue .= $char;
                    $i++; // Skip next char
                } else {
                    $inQuotes = false;
                    $parsed[] = $currentValue;
                    $currentValue = '';
                    $quoteChar = null;
                }
            } elseif ($inQuotes) {
                $currentValue .= $char;
            }
        }

        return $parsed;
    }
}
