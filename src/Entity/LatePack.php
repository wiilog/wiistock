<?php

namespace App\Entity;

use App\Repository\LatePackRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LatePackRepository::class)]
class LatePack {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $LU;

    #[ORM\Column(type: 'string', length: 255)]
    private $date;

    #[ORM\Column(type: 'bigint')]
    private $delay;

    #[ORM\Column(type: 'string', length: 255)]
    private $emp;

    public function getId(): ?int {
        return $this->id;
    }

    public function getLU(): ?string {
        return $this->LU;
    }

    public function setLU(string $LU): self {
        $this->LU = $LU;

        return $this;
    }

    public function getDate(): ?string {
        return $this->date;
    }

    public function setDate(string $date): self {
        $this->date = $date;

        return $this;
    }

    public function getDelay(): ?int {
        return $this->delay;
    }

    public function setDelay(int $delay): self {
        $this->delay = $delay;

        return $this;
    }

    public function getEmp(): ?string {
        return $this->emp;
    }

    public function setEmp(string $emp): self {
        $this->emp = $emp;

        return $this;
    }

}
