<?php

declare(strict_types=1);

namespace MulerTech\Database\Relational\Sql;

/**
 * SQL operators for conditions in queries
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum SqlOperator: string
{
    /**
     * Example: "WHERE `age` IN (20, 30, 40)"
     *  or "WHERE `age` IN (SELECT `age` FROM `users` WHERE `active` = 1)"
     */
    case IN = 'IN';
    /**
     * Example: "WHERE `age` NOT IN (20, 30, 40)"
     * or "WHERE `age` NOT IN (SELECT `age` FROM `users` WHERE `active` = 1)"
     */
    case NOT_IN = 'NOT IN';
    /**
     * Example: "WHERE `age` BETWEEN 35 AND 50"
     */
    case BETWEEN = 'BETWEEN';
    /**
     * Example: "WHERE `age` NOT BETWEEN 35 AND 50"
     */
    case NOT_BETWEEN = 'NOT BETWEEN';
    /**
     * Example: "WHERE exists (SELECT 1 FROM `users` WHERE `users`.`id` = `posts`.`user_id`)"
     *  or "WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `users`.`id` = `posts`.`user_id`)"
     */
    case EXISTS = 'EXISTS';
    /**
     * Example: "WHERE `name` LIKE '%John%'"
     */
    case LIKE = 'LIKE';
    /**
     * Example: "WHERE `name` NOT LIKE '%John%'"
     */
    case NOT_LIKE = 'NOT LIKE';
}
