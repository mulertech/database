<?php

namespace MulerTech\Database\NonRelational\DocumentStore\FileExtension;

use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use RuntimeException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Class Yaml
 * @package MulerTech\Database\NonRelational\DocumentStore\FileExtension
 * @author Sébastien Muler
 */
class Yaml extends FileManipulation
{
    private const string EXTENSION = 'yaml';

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

        if (function_exists('yaml_parse')){
            $content = yaml_parse($fileContent);

            //Error for yaml_parse
            if ($content === false) {
                throw new RuntimeException(
                    sprintf('The Yaml file "%s" can\'t be decode, it contain an error.', $this->getFilename())
                );
            }

            return $content;
        }

        return SymfonyYaml::parse($fileContent);
    }

    /**
     * @inheritDoc
     */
    public function saveFile(mixed $content, bool $recursive = false): bool
    {
        if (function_exists('yaml_emit')) {
            $file_content = yaml_emit($content);
        } else {
            $file_content = SymfonyYaml::dump($content);
        }

        return $this->filePutContents($file_content, $recursive);
    }
}