<?php

namespace MulerTech\Database\PhpInterface\PdoMysql;

use MulerTech\Database\PhpInterface\DriverInterface;

/**
 * Class Driver
 * @package MulerTech\Database\PhpInterface\PdoMysql
 * @author Sébastien Muler
 */
class Driver implements DriverInterface
{
    /**
     * @param array $dsnOptions
     * @return string
     */
    public function generateDsn(array $dsnOptions): string
    {
        $parts = [];
        if (!empty($dsnOptions['host'])) {
            $parts[] = 'host=' . $dsnOptions['host'];
        }
        if (!empty($dsnOptions['port'])) {
            $parts[] = 'port=' . $dsnOptions['port'];
        }
        if (!empty($dsnOptions['dbname'])) {
            $parts[] = 'dbname=' . $dsnOptions['dbname'];
        }
        if (!empty($dsnOptions['unix_socket'])) {
            $parts[] = 'unix_socket=' . $dsnOptions['unix_socket'];
        }
        if (!empty($dsnOptions['charset'])) {
            $parts[] = 'charset=' . $dsnOptions['charset'];
        }
        return 'mysql:' . implode(';', $parts);
    }

}
