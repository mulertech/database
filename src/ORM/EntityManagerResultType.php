<?php

namespace MulerTech\Database\ORM;

/**
 * Class EntityManagerResultType
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
enum EntityManagerResultType: string
{
    case OBJECT = 'object';
    case LIST = 'list';
}