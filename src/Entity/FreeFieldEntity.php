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

    public function hasFreeField(int $id): bool {
        return isset($this->freeFields[$id]);
    }

    public function getFreeFieldValue(int $id): string {
        if ($this->hasFreeField($id)) {
            return is_array($this->freeFields[$id])
                ? implode(';', $this->freeFields[$id])
                : $this->freeFields[$id];
        }
        return "";
    }

}
