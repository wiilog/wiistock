<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 */
class FreeFieldEntity
{

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    protected $freeFields = [];

    public function getFreeFields(): ?array
    {
        return $this->freeFields;
    }

    public function setFreeFields(?array $freeFields): self
    {
        $this->freeFields = $freeFields;

        return $this;
    }
}
