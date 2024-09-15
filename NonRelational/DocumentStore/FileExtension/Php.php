<?php

namespace MulerTech\Database\NonRelational\DocumentStore\FileExtension;

use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use MulerTech\Database\NonRelational\DocumentStore\PathManipulation;
use RuntimeException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Class Php
 * @package MulerTech\Database\NonRelational\DocumentStore\FileExtension
 * @author Sébastien Muler
 */
class Php extends FileManipulation
{
    private const string EXTENSION = 'php';

    /**
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename, self::EXTENSION);
    }

    public function firstOccurrence(string $occurrence, bool $case_insensitive = false): ?int
    {
        $fileContent = file_get_contents($this->getFilename());

        if ($fileContent === false) {
            throw new RuntimeException(sprintf('Failed to open the file : "%s".', $this->getFilename()));
        }

        $tokens = token_get_all($fileContent);

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }

            if (($case_insensitive === true && stripos($token[1], $occurrence) !== false) ||
                ($case_insensitive === false && str_contains($token[1], $occurrence))
            ) {
                return $token[2];
            }
        }

        return null;
    }

    /**
     * Return the line number of the last occurence ($occurence),
     * null if not found.
     * @param string $occurrence
     * @param bool $case_insensitive
     * @return int|null
     */
    public function lastOccurrence(string $occurrence, bool $case_insensitive = false): ?int
    {
        $fileContent = $this->getFileContent();
        $line = null;

        $tokens = token_get_all($fileContent);
        var_dump('tokens :', $tokens);
        foreach ($tokens as $token) {
            var_dump('token :', $token);
            if (!is_array($token)) {
                continue;
            }

            if (($case_insensitive === true && stripos($token[1], $occurrence) !== false) ||
                ($case_insensitive === false && str_contains($token[1], $occurrence) !== false)
            ) {
                $line = $token[2];
            }
        }
        return $line;
    }

    /**
     * Get the line $line of the file $filename with or without semicolon
     * @param int $line
     * @param string $filename
     * @param bool $withSemicolon
     * @return string|null
     */
    public static function getLine(int $line, string $filename, bool $withSemicolon = false): ?string
    {
        $fileContent = file_get_contents($filename);

        if ($fileContent === false) {
            throw new RuntimeException(sprintf('Failed to open the file : "%s".', $filename));
        }

        $nextString = false;
        $lineContent = '';

        $tokens = token_get_all($fileContent);
        foreach ($tokens as $token) {
            if (is_array($token) && $token[2] === $line) {
                $nextString = true;
                $lineContent .= $token[1];
            }

            if ($withSemicolon && is_string($token) && $nextString) {
                $lineContent .= $token;
                break;
            }

            if (is_array($token) && $token[2] > $line) {
                break;
            }
        }
        return $lineContent;
    }

    /**
     * Return the complete class name of the file given by the $filename.
     * @return string
     */
    public function fileClassName(): string
    {
        if (is_null($namespaceLine = $this->firstOccurrence('namespace'))) {
            throw new RuntimeException(
                sprintf(
                    'Class FileManipulation, function fileClassName. The file "%s" does not contain namespace.',
                    $this->getFilename()
                )
            );
        }

        $namespaceLineContent = self::getLine($namespaceLine, $this->getFilename());
        $namespaceParts = explode(' ', trim($namespaceLineContent));
        $namespace = $namespaceParts[1];

        //Class name
        $className = $this->findClassName();

        return $namespace . '\\' . $className;
    }

    /**
     * @return string|null
     */
    protected function findClassName(): ?string
    {
        $fileContent = file_get_contents($this->getFilename());
        if ($fileContent !== false) {
            $tokens = token_get_all($fileContent);
            foreach ($tokens as $token) {
                if (is_array($token) && (str_contains($token[1], 'class'))) {
                    $lineContent = trim(self::getLine($token[2], $this->getFilename()));
                    if (str_starts_with($lineContent, 'class')) {
                        return explode(' ', $lineContent)[1];
                    }
                }
            }
            return null;
        }

        throw new RuntimeException(
            sprintf(
                'Class FileManipulation, function findClassName. Failed to open the file : "%s".',
                $this->getFilename()
            )
        );
    }

    /**
     * @param string $path
     * @param bool $recursive
     * @return array
     */
    public static function getClassNames(string $path, bool $recursive = false): array
    {
        $fileList = PathManipulation::fileList($path, $recursive);

        $classNames = [];
        foreach ($fileList as $filename) {
            $php = new Php($filename);
            $classNames[] = $php->fileClassName();
        }
        sort($classNames);

        return $classNames;
    }
}