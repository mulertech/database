<?php


namespace mtphp\Database\PhpInterface;

/**
 * Interface DriverInterface
 * @package mtphp\Database\PhpInterface
 * @author Sébastien Muler
 */
interface DriverInterface
{

    /**
     * @param array $dsnOptions
     * @return string
     */
    public function generateDsn(array $dsnOptions): string;

}