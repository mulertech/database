<?php

namespace MulerTech\Database\NonRelational\DocumentStore\FileExtension;

use JsonException;
use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use RuntimeException;

/**
 * Class Json
 * @package MulerTech\Database\NonRelational\DocumentStore\FileExtension
 * @author Sébastien Muler
 */
class Json extends FileManipulation
{
    private const string EXTENSION = 'json';

    /**
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename, self::EXTENSION);
    }

    /**
     * @inheritDoc
     */
    public function openFile(): mixed
    {
        $fileContent = $this->getFileContent();

        if (!function_exists('json_decode')) {
            throw new RuntimeException(
                'Class Json, function openFile. The json_decode function don\'t exists, this is a PHP extension to activate.'
            );
        }

        $content = json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR);
        if (is_null($content) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf('The JSON file "%s" can\'t be decode, it contain an error.', $this->getFilename())
            );
        }

        return $content;
    }

    /**
     * @inheritDoc
     * @throws JsonException
     */
    public function saveFile(mixed $content, bool $recursive = false): bool
    {
        if (!function_exists('json_encode')) {
            throw new RuntimeException(
                'Class Json, function openFile. The json_encode function don\'t exists, this is a PHP extension to activate.'
            );
        }

        return $this->filePutContents(json_encode($content, JSON_THROW_ON_ERROR), $recursive);
    }
}