<?php


namespace mtphp\Database\NonRelational\DocumentStore;

/**
 * Interface FileInterface
 * @package mtphp\Database\NonRelational\DocumentStore
 * @author Sébastien Muler
 */
interface FileInterface
{

    /**
     * @param string|null $filename
     * @return string
     */
    public static function getExtension(string $filename = null): string;

    /**
     * @param string $filename
     * @return mixed
     */
    public static function openFile(string $filename);

    /**
     * @param string $filename
     * @param $content
     * @return bool True if success.
     */
    public static function saveFile(string $filename, $content): bool;

}