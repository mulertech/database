<?php


namespace mtphp\Database\NonRelational\DocumentStore\FileExtension;

use mtphp\Database\NonRelational\DocumentStore\FileInterface;
use mtphp\Database\NonRelational\DocumentStore\FileManipulation;
use RuntimeException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Class Yaml
 * @package mtphp\Database\NonRelational\DocumentStore\FileExtension
 * @author Sébastien Muler
 */
class Yaml implements FileInterface
{

    private const EXTENSION = 'yaml';

    /**
     * @param string|null $filename
     * @return string
     */
    public static function getExtension(string $filename = null): string
    {
        if (!is_null($filename) && strpos($filename, self::EXTENSION) === false) {
            throw new RuntimeException(
                'Class Yaml, function getExtension. The filename given haven\'t the yaml extension.'
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
        if (function_exists('yaml_parse')) {
            $content = yaml_parse($file_content);
        } else {
            $content = SymfonyYaml::parse($file_content);
        }

        //Error for yaml_parse
        if ($content === false) {
            throw new RuntimeException(
                sprintf('The Yaml file "%s" can\'t be decode, it contain an error.', $filename)
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
        if (function_exists('yaml_emit')) {
            $file_content = yaml_emit($content);
        } else {
            $file_content = SymfonyYaml::dump($content);
        }

        return FileManipulation::saveFile($filename, $file_content);
    }

}