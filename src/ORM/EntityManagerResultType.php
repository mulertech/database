<?php

declare(strict_types=1);

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
    case LIST_KEY_BY_ID = 'list_key_by_id';
}
