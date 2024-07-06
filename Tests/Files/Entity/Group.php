<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Tests\Files\GroupRepository;

/**
 * Class Group
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 */
#[MtEntity(repository: GroupRepository::class)]
class Group
{

}