<?php

namespace MulerTech\Database\NonRelational\DocumentStore;

/**
 * Interface FileInterface
 * @package MulerTech\Database\NonRelational\DocumentStore
 * @author Sébastien Muler
 */
interface FileInterface
{
    /**
     * @return string
     */
    public function getExtension(): string;

    /**
     * @return mixed
     */
    public function openFile(): mixed;

    /**
     * @param mixed $content
     * @return bool True if success.
     */
    public function saveFile(mixed $content): bool;

}