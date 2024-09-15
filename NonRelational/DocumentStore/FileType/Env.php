<?php

namespace MulerTech\Database\NonRelational\DocumentStore\FileType;

use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use SplFileObject;

class Env extends FileManipulation
{
    /**
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename);
    }

    /**
     * Parse env file from a file like :
     * key1=value1
     * #Some comments
     * key2="value2"
     *
     * To this type of array :
     * ['key1' => 'value1', 'key2' => 'value2']
     * @param string $filename
     * @return array|null
     */
    public static function parseFile(string $filename): ?array
    {
        if (!is_file($filename)) {
            return null;
        }

        $content = [];
        $file = new SplFileObject($filename);
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (($equal = strpos($line, '=')) === false) {
                continue;
            }

            $fistPart = substr($line, 0, $equal);
            $secondPart = substr($line, $equal + 1);

            if ($secondPart !== '') {
                if (($secondPart[0] === '"' && substr($secondPart, -1) === '"') || ($secondPart[0] === '\'' && substr($secondPart, -1) === '\'')) {
                    $value = substr($secondPart, 1, -1);
                }
            }

            $content[$fistPart] = $value ?? $secondPart;
            unset($value);
        }

        return $content;
    }

    /**
     * Set all the environment key => value
     * @param string $filename
     */
    public static function loadEnv(string $filename): void
    {
        $envParsed = self::parseFile($filename);
        if (is_null($envParsed)) {
            return;
        }

        foreach ($envParsed as $key => $value) {
            putenv("$key=$value");
        }
    }
}