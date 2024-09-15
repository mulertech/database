<?php

namespace MulerTech\Database\NonRelational\DocumentStore;

use RuntimeException;
use SplFileInfo;

/**
 * Class FileManipulation
 * @package MulerTech\Database\NonRelational\DocumentStore
 * @author Sébastien Muler
 */
class FileManipulation implements FileInterface
{
    private const string NEW_LINE_UNIX = "\n";

    /**
     * @var string
     */
    private string $filename;
    /**
     * @var string
     */
    protected string $extension = '';

    /**
     * Json constructor.
     * @param string $filename
     * @param string $extension
     */
    public function __construct(string $filename, string $extension = '')
    {
        $this->filename = $filename;
        $this->extension = $extension;
    }

    /**
     * @return bool
     */
    public function checkExtension(): bool
    {
        if (!stripos($this->filename, '.' . $this->extension)) {
            throw new RuntimeException(
                sprintf(
                    'Class FileManipulation, function checkExtension. The given filename does not have the %s extension.',
                    $this->extension
                )
            );
        }

        return true;
    }

    /**
     * @param string $folder
     * @return bool
     */
    public static function folderExists(string $folder): bool
    {
        return is_dir($folder);
    }

    /**
     * @param string $folder
     * @param string|null $last
     * @return string|null
     */
    public static function firstExistingParentFolder(string $folder, string $last = null): ?string
    {
        $parent = dirname($folder);

        if ($parent === $last) {
            return null;
        }

        if (is_dir($parent)) {
            return $parent;
        }

        return self::firstExistingParentFolder($parent, $parent);
    }

    /**
     * @param string $folder
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    public static function folderCreate(string $folder, int $mode = 0770, bool $recursive = false): bool
    {
        if (is_dir($folder)) {
            return true;
        }
        $parent = self::firstExistingParentFolder($folder);

        if ($parent === null) {
            throw new RuntimeException(sprintf('The parent folder of "%s" does not exist.', $folder));
        }

        if (is_writable($parent)) {
            return mkdir($folder, $mode, $recursive) || is_dir($folder);
        }

        throw new RuntimeException(
            sprintf(
                'Unable to create the path "%s", the parent folder "%s" is write protected.',
                $folder,
                $parent
            )
        );
    }

    /**
     * @param string $folder
     * @return bool
     */
    public static function folderDelete(string $folder): bool
    {
        return !is_dir($folder) || rmdir($folder);
    }

    /**
     * @return string
     */
    protected function getFileContent(): string
    {
        if (false === $content = file_get_contents($this->getFilename())) {
            throw new RuntimeException(
                sprintf('Unable to read the content of file "%s".', $this->getFilename())
            );
        }

        return $content;
    }

    /**
     * @return mixed
     */
    public function openFile(): mixed
    {
        return $this->getFileContent();
    }

    /**
     * @param mixed $content
     * @param bool $recursive
     * @return bool
     */
    protected function filePutContents(mixed $content, bool $recursive = false): bool
    {
        $filename = $this->getFilename();

        if (file_exists($filename) && !is_writable($filename)) {
            throw new RuntimeException(
                sprintf('Unable to save the file "%s", it is write protected.', $filename)
            );
        }

        $parent = dirname($filename);

        if (!is_dir($parent)) {
            if (!$recursive) {
                throw new RuntimeException(
                    sprintf(
                        'Unable to save the file "%s", the parent folder "%s" does not exist.',
                        $filename,
                        $parent
                    )
                );
            }

            self::folderCreate($parent, 0777, true);
        }

        return file_put_contents($filename, $content);
    }

    /**
     * @param mixed $content
     * @param bool $recursive
     * @return bool
     */
    public function saveFile(mixed $content, bool $recursive = false): bool
    {
        return $this->filePutContents($content, $recursive);
    }

    /**
     * @param FileInterface $destinationFormat
     * @return bool
     */
    public function convertFile(FileInterface $destinationFormat): bool
    {
        $content = $this->getFileContent();

        return $destinationFormat->filePutContents($content);
    }

    /**
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @return bool|int
     */
    public function countLines(): bool|int
    {
        $handle = fopen($this->getFilename(), 'r');

        if ($handle === false) {
            return false;
        }

        $line = 0;

        while (!feof($handle)) {
            fgets($handle);
            $line++;
        }

        fclose($handle);

        return $line;
    }

    /**
     * @param int $line
     * @param string $content
     */
    public function insertContent(int $line, string $content): void
    {
        $filename = $this->getFilename();
        //create new tmp file
        $tmpFilename = dirname($filename) . DIRECTORY_SEPARATOR . 'tmp.MulerTech';
        $tmpHandle = fopen($tmpFilename, 'w+b');
        //copy the lines before $line
        $handle = fopen($filename, 'rb');
        for ($i = 1; $i < $line; $i++) {
            fwrite($tmpHandle, fgets($handle));
        }
        //prepare content
        if (iconv_strlen($content) === 1 && $content[0] === self::NEW_LINE_UNIX) {
            $new_content[] = self::NEW_LINE_UNIX;
        } else {
            $array_content = (strpos($content, self::NEW_LINE_UNIX)) ? explode(
                self::NEW_LINE_UNIX,
                $content
            ) : [$content];
            if (empty(end($array_content))) {
                array_pop($array_content);
            }
            //add \n at the end of lines
            $new_content = [];
            foreach ($array_content as $l) {
                $new_content[] = $l . self::NEW_LINE_UNIX;
            }
        }
        //copy the content
        foreach ($new_content as $data) {
            fwrite($tmpHandle, $data);
        }
        //copy the rest of file
        while (!feof($handle)) {
            fwrite($tmpHandle, fgets($handle));
        }
        fclose($handle);
        //open and blank the original file
        $handle = fopen($filename, 'w+b');
        rewind($tmpHandle);
        //copy the entire tmp file on the file
        while (!feof($tmpHandle)) {
            fwrite($handle, fgets($tmpHandle));
        }
        fclose($handle);
        fclose($tmpHandle);
        //delete the tmp file if success
        unlink($tmpFilename);
    }

    /**
     * Return the line number of the first occurrence ($occurrence),
     * null if not found.
     * @param string $occurrence
     * @param bool $case_insensitive
     * @return int|null
     */
    public function firstOccurrence(string $occurrence, bool $case_insensitive = false): ?int
    {
        // todo: Implement firstOccurrence() method for other files than php.
        return null;
    }

    /**
     * Return the line number of the last occurrence ($occurrence),
     * null if not found.
     * @param string $occurrence
     * @param bool $case_insensitive
     * @return int|null
     */
    public function lastOccurrence(string $occurrence, bool $case_insensitive = false): ?int
    {
        // todo: Implement lastOccurrence() method for other files than php.
        return null;
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
        // todo: Implement getLine() method for other files than php.
        return null;
    }

    /**
     * @return string
     */
    protected function getFilename(): string
    {
        if (!isset($this->filename)) {
            throw new RuntimeException(
                'Class Json, function checkFilename. The filename is not defined.'
            );
        }

        return $this->filename;
    }
}
