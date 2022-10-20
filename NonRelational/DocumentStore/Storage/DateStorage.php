<?php


namespace mtphp\Database\NonRelational\DocumentStore\Storage;


use mtphp\Database\NonRelational\DocumentStore\FileManipulation;

class DateStorage
{

    /**
     * @var FileManipulation
     */
    private $fileManipulation;

    public function __construct()
    {
        $this->fileManipulation = new FileManipulation();
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function yearExists(string $path): bool
    {
        return (is_dir($path . DIRECTORY_SEPARATOR . date("Y")));
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function yearCreate(string $path): bool
    {
        return $this->fileManipulation->folderCreate($path . DIRECTORY_SEPARATOR . date("Y"));
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function monthExists(string $path): bool
    {
        return (is_dir($path . DIRECTORY_SEPARATOR . date("Y") . DIRECTORY_SEPARATOR . date("m")));
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function monthCreate(string $path): bool
    {
        return $this->fileManipulation->folderCreate($path . DIRECTORY_SEPARATOR . date("Y") . DIRECTORY_SEPARATOR . date("m"));
    }

    /**
     * Create or verify all the folders for save the archive,
     * return complete path.
     * @param string $path
     * @return string
     */
    public function datePath(string $path): string
    {
        //archive directory
        ($this->fileManipulation->folderExists($path)) ?: $this->fileManipulation->folderCreate($path);
        //year directory
        ($this->yearExists($path)) ?: $this->yearCreate($path);
        //month directory
        ($this->monthExists($path)) ?: $this->monthCreate($path);
        //complete filename path
        return $path . DIRECTORY_SEPARATOR . date("Y") . DIRECTORY_SEPARATOR . date("m");
    }

    /**
     * @param string $suffix
     * @param string $separator
     * @return string
     */
    public static function dateFilename(string $suffix, string $separator = '-'): string
    {
        return date('Ymd') . $separator . $suffix;
    }

    /**
     * @param string $suffix
     * @param string $separator
     * @return string
     */
    public static function dateTimeFilename(string $suffix, string $separator = '-'): string
    {
        return date('Ymd-Hi') . $separator . $suffix;
    }
}