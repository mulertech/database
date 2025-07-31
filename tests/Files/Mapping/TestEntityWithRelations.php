<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TestEntityWithRelations
{
    public int $id;
    public string $name;

    #[MtOneToOne(targetEntity: 'App\\Entity\\UserProfile')]
    public $profile;

    #[MtManyToOne(targetEntity: 'App\\Entity\\Category')]
    public $category;

    #[MtManyToOne(targetEntity: 'App\\Entity\\User')]
    public $author;

    #[MtOneToMany(targetEntity: 'App\\Entity\\Post', inverseJoinProperty: 'author')]
    public $posts;

    #[MtOneToMany(targetEntity: 'App\\Entity\\Comment', inverseJoinProperty: 'user')]
    public $comments;

    #[MtManyToMany(targetEntity: 'App\\Entity\\Role', mappedBy: 'App\\Entity\\UserRole')]
    public $roles;

    #[MtManyToMany(targetEntity: 'App\\Entity\\Tag', joinProperty: 'post_id', inverseJoinProperty: 'tag_id')]
    public $tags;
}

