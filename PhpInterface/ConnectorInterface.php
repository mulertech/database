<?php

namespace MulerTech\Database\PhpInterface;

/**
 * Interface ConnectorInterface
 * @package MulerTech\Database\PhpInterface
 * @author Sébastien Muler
 */
interface ConnectorInterface
{

    /**
     * @param array $dsnOptions
     * @param string $username
     * @param string $password
     * @param array|null $options
     * @return mixed Connection to database
     */
    public function connect(array $dsnOptions, string $username, string $password, ?array $options = null);

}