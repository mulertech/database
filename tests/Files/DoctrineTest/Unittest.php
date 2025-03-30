<?php

namespace MulerTech\Database\Tests\Files\DoctrineTest;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name: 'unittest')]
class Unittest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Unittest::class, fetch: 'EAGER')]
    private Unittest $parent;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParent(): Unittest
    {
        return $this->parent;
    }

    public function setParent(Unittest $parent): void
    {
        $this->parent = $parent;
    }
}