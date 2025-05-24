<?php

namespace MulerTech\Database\PhpInterface;

/**
 * Interface DriverInterface
 * @package MulerTech\Database\PhpInterface
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
