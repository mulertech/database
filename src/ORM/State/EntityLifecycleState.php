<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum EntityLifecycleState: string
{
    case NEW = 'new';
    case MANAGED = 'managed';
    case REMOVED = 'removed';
    case DETACHED = 'detached';
}
