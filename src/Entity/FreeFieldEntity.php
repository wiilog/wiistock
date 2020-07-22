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

    public function addFreeField(array $freeField) {
        $this->freeFields[] = $freeField;
    }

    public function removeFreeField(array $freeFieldToDelete) {
        foreach ($this->freeFields as $index => $freeField) {
            if (intval($freeField['id']) === $freeFieldToDelete['id']) {
                array_splice($this->freeFields, $index, 1);
            }
        }
    }

    public function updateFreeField(array $freeFieldToUpdate) {
        foreach ($this->freeFields as $index => $freeField) {
            if (intval($freeField['id']) === $freeFieldToUpdate['id']) {
                $freeFieldToUpdate['value'] = $freeField['value'];
                array_splice($this->freeFields, $index, 1);
                $this
                    ->freeFields[] = $freeFieldToUpdate;
            }
        }
    }

}
