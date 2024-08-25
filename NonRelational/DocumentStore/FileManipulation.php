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

    private const NEW_LINE_UNIX = "\n";
    private const NEW_LINE_WINDOWS = "\r\n";
    private const NEW_LINE_MAC = "\r";

    /**
     * @param string $folder
     * @return bool
     */
    public function folderExists(string $folder): bool
    {
        return (is_dir($folder));
    }

    /**
     * @param string $folder
     * @param int $mode
     * @return bool
     */
    public function folderCreate(string $folder, int $mode = 0770): bool
    {
        if (!is_dir($folder) && is_writable(dirname($folder))) {
            if (mkdir($folder, $mode) && is_dir($folder)) {
                return true;
            }

            throw new RuntimeException(sprintf('Unable to create the path "%s".', $folder));
        }

        throw new RuntimeException(sprintf('Unable to create the path "%s", it is write protected.', $folder));
    }

    /**
     * Open the file $filename
     * @param string $filename
     * @return string
     */
    public static function openFile(string $filename): string
    {
        if (file_exists($filename)) {
            if (($content = file_get_contents($filename)) === false) {
                throw new RuntimeException(sprintf('Unable to read the content of file "%s".', $filename));
            }

            return $content;
        }

        throw new RuntimeException(sprintf('File "%s" not found.', $filename));
    }

    /**
     * Save the file
     * @param string $filename
     * @param mixed $content
     * @return bool
     */
    public static function saveFile(string $filename, $content): bool
    {
        if (file_exists($filename)) {
            if (is_writable($filename) && file_put_contents($filename, $content)) {
                return true;
            }

            throw new RuntimeException(sprintf('Unable to save the file "%s", it probably write protected.', $filename));
        }

        if ($handle = fopen($filename, 'wb+')) {
            fwrite($handle, $content);
            fclose($handle);
            return true;
        }

        throw new RuntimeException(sprintf('Unable to create and save the file "%s", the path is probably write protected.', $filename));
    }

    /**
     * @param string $filename
     * @param FileInterface $originFormat
     * @param FileInterface $destinationFormat
     * @param string|null $newFilename If $newFilename stay null the $newFilename was the same but with the new destination format extension.
     * @return bool
     */
    public static function convertFile(string $filename, FileInterface $originFormat, FileInterface $destinationFormat, string $newFilename = null): bool
    {
        $content = $originFormat->openFile($filename);
        if (is_null($newFilename)) {
            $newFilename = str_replace('.' . self::getExtension($filename), '.' . $destinationFormat::getExtension(), $filename);
        }
        return $destinationFormat->saveFile($newFilename, $content);
    }

    /**
     * @param string|null $filename It's needed and null is here because this function implements FileInterface.
     * @return string
     */
    public static function getExtension(string $filename = null): string
    {
        if (is_null($filename)) {
            throw new RuntimeException('Class FileManipulation, function getExtension. The filename must be given to determine the extension.');
        }
        return (new SplFileInfo($filename))->getExtension();
    }

    /**
     * @param string $filename
     * @return bool|int
     */
    public function countLines(string $filename)
    {
        $handle = fopen($filename, 'r');
        $line = 0;
        if ($handle !== false) {
            while (!feof($handle)) {
                fgets($handle);
                $line++;
            }
            fclose($handle);
            return $line;
        }

        return false;
    }

    /**
     * @param string $filename
     * @param int $line
     * @param string $content
     */
    public function insertContent(string $filename, int $line, string $content): void
    {
        //create new tmp file
        $path = dirname(($filename)) . DIRECTORY_SEPARATOR;
        $tmp_handle = fopen($path . 'tmp.MulerTech', 'w+b');
        //copy the lines before $line
        $handle = fopen($filename, 'rb');
        for ($i = 1; $i < $line; $i++) {
            fwrite($tmp_handle, fgets($handle));
        }
        //prepare content
        if (iconv_strlen($content) === 1 && $content[0] === "\n") {
            $new_content[] = "\n";
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
            fwrite($tmp_handle, $data);
        }
        //copy the rest of file
        while (!feof($handle)) {
            fwrite($tmp_handle, fgets($handle));
        }
        fclose($handle);
        //open and blank the original file
        $handle = fopen($filename, 'w+b');
        rewind($tmp_handle);
        //copy the entire tmp file on the file
        while (!feof($tmp_handle)) {
            fwrite($handle, fgets($tmp_handle));
        }
        fclose($handle);
        fclose($tmp_handle);
        //delete the tmp file if success
        unlink($path . 'tmp.MulerTech');
    }

    /**
     * Return the line number of the last occurence ($occurence),
     * null if not found.
     * @param string $filename
     * @param string $occurrence
     * @param bool $case_insensitive
     * @return int
     */
    public function lastOccurrence(string $filename, string $occurrence, bool $case_insensitive = false): ?int
    {
        $fileContent = file_get_contents($filename);
        $line = null;
        if ($fileContent !== false) {
            $tokens = token_get_all($fileContent);
            foreach ($tokens as $token) {
                if (is_array($token) && (($case_insensitive === true && stripos(
                                $token[1],
                                $occurrence
                            ) !== false) || ($case_insensitive === false && strpos(
                                $token[1],
                                $occurrence
                            ) !== false))) {
                    $line = $token[2];
                }
            }
            return $line;
        }

        throw new RuntimeException(sprintf('Failed to open the file : "%s".', $filename));
    }

    /**
     * Return the line number of the first occurrence ($occurrence),
     * null if not found.
     * @param string $filename
     * @param string $occurrence
     * @param bool $case_insensitive
     * @return int|null
     */
    public function firstOccurrence(string $filename, string $occurrence, bool $case_insensitive = false): ?int
    {
        $fileContent = file_get_contents($filename);
        $line = null;
        if ($fileContent !== false) {
            $tokens = token_get_all($fileContent);
            foreach ($tokens as $token) {
                if (is_array($token) && (($case_insensitive === true && stripos(
                            $token[1],
                            $occurrence
                        ) !== false) || ($case_insensitive === false && strpos(
                            $token[1],
                            $occurrence
                        ) !== false))) {
                    $line = $token[2];
                    break;
                }
            }
            return $line;
        }

        throw new RuntimeException(sprintf('Failed to open the file : "%s".', $filename));
    }

    /**
     * Get the line $line of the file $filename with or without semicolon
     * @param int $line
     * @param string $filename
     * @param bool $withSemicolon
     * @return string|null
     */
    public function getLine(int $line, string $filename, bool $withSemicolon = false): ?string
    {
        $fileContent = file_get_contents($filename);
        $nextString = false;
        $lineContent = '';
        if ($fileContent !== false) {
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
        throw new RuntimeException(sprintf('Failed to open the file : "%s".', $filename));
    }

    /**
     * Return the complete class name of the file given by the $filename.
     * @param string $filename
     * @return string
     */
    public function fileClassName(string $filename): string
    {
        if (is_null($namespaceLine = $this->firstOccurrence($filename, 'namespace'))) {
            throw new RuntimeException(sprintf('Class FileManipulation, function fileClassName. The file "%s" does not contain namespace.', $filename));
        }

        $namespaceLineContent = $this->getLine($namespaceLine, $filename);
        $namespaceParts = explode(' ', trim($namespaceLineContent));
        $namespace = $namespaceParts[1];

        //Class name
        $className = $this->findClassName($filename);

        return $namespace . '\\' . $className;
    }

    /**
     * @param string $filename
     * @return string|null
     */
    private function findClassName(string $filename): ?string
    {
        $fileContent = file_get_contents($filename);
        if ($fileContent !== false) {
            $tokens = token_get_all($fileContent);
            foreach ($tokens as $token) {
                if (is_array($token) && (strpos($token[1], 'class') !== false)) {
                    $lineContent = trim($this->getLine($token[2], $filename));
                    if (0 === strpos($lineContent, 'class')) {
                        return explode(' ', $lineContent)[1];
                    }
                }
            }
            return null;
        }

        throw new RuntimeException(sprintf('Class FileManipulation, function findClassName. Failed to open the file : "%s".', $filename));
    }
}
