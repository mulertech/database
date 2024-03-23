<?php

namespace MulerTech\Database\NonRelational\DocumentStore;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class PathManipulation
 * @package MulerTech\Database\NonRelational\DocumentStore
 * @author Sébastien Muler
 */
class PathManipulation
{

    /**
     * @param string $path
     * @param bool $recursive
     * @return array
     */
    public static function fileList(string $path, bool $recursive = true): array {
        return ($recursive === true) ? self::recursiveIteratorFileList($path) : self::iteratorFileList($path);
    }

    /**
     * @param $path
     * @return array
     */
    private static function recursiveIteratorFileList($path): array
    {
        $list = [];
        $directory = new RecursiveDirectoryIterator($path);
        foreach (new RecursiveIteratorIterator($directory) as $item) {
            if (!in_array($item->getFilename(), ['.', '..'], true)) {
                $list[] = $item->getPathname();
            }
        }
        return $list;
    }

    /**
     * @param $path
     * @return array
     */
    private static function iteratorFileList($path): array
    {
        $list = [];
        foreach (new DirectoryIterator($path) as $item) {
            if (!$item->isDot() && !$item->isDir()) {
                $list[] = $item->getPathname();
            }
        }
        return $list;
    }
}