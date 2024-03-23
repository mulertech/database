<?php


namespace MulerTech\Database\NonRelational\DocumentStore\FileExtension;

use MulerTech\Database\NonRelational\DocumentStore\FileInterface;
use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use RuntimeException;

/**
 * Class Json
 * @package MulerTech\Database\NonRelational\DocumentStore\FileExtension
 * @author Sébastien Muler
 */
class Json implements FileInterface
{

    private const EXTENSION = 'json';

    /**
     * @param string|null $filename
     * @return string
     */
    public static function getExtension(string $filename = null): string
    {
        if (!is_null($filename) && strpos($filename, self::EXTENSION) === false) {
            throw new RuntimeException(
                'Class Json, function getExtension. The filename given haven\'t the json extension.'
            );
        }
        return self::EXTENSION;
    }

    /**
     * Open the file $filename
     * @param string $filename
     * @return mixed
     */
    public static function openFile(string $filename)
    {
        $file_content = FileManipulation::openFile($filename);
        if (function_exists('json_decode')) {
            $content = json_decode($file_content, true);
        } else {
            throw new RuntimeException(
                'Class Json, function openFile. The json_decode function don\'t exists, this is a PHP extension to activate.'
            );
        }

        if (is_null($content) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf('The JSON file "%s" can\'t be decode, it contain an error.', $filename)
            );
        }

        return $content;
    }

    /**
     * Save the file
     * @param string $filename
     * @param mixed $content
     * @return bool
     */
    public static function saveFile(string $filename, $content): bool
    {
        if (function_exists('json_encode')) {
            $file_content = json_encode($content);
        } else {
            throw new RuntimeException(
                'Class Json, function openFile. The json_encode function don\'t exists, this is a PHP extension to activate.'
            );
        }
        return FileManipulation::saveFile($filename, $file_content);
    }

}