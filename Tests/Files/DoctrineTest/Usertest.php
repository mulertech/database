<?php

namespace MulerTech\Database\Tests\Files\DoctrineTest;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name: 'usertest')]
class Usertest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column()]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $username = null;

    #[ORM\ManyToOne(targetEntity: Unittest::class)]
    private Unittest $unittest;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUnittest(): Unittest
    {
        return $this->unittest;
    }

    public function setUnittest(Unittest $unittest): self
    {
        $this->unittest = $unittest;

        return $this;
    }


}