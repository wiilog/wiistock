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
            if (intval($freeField['id']) === intval($freeFieldToDelete['id'])) {
                array_splice($this->freeFields, $index, 1);
            }
        }
    }

    public function updateFreeField(array $freeFieldToUpdate) {
        $updated = false;
        foreach ($this->freeFields as $index => $freeField) {
            if (intval($freeField['id']) === intval($freeFieldToUpdate['id'])) {
                $freeFieldToUpdate['value'] = $freeField['value'];
                array_splice($this->freeFields, $index, 1);
                $this
                    ->freeFields[] = $freeFieldToUpdate;
                $updated = true;
            }
        }
        if (!$updated) $this->addFreeField($freeFieldToUpdate);
    }

    public function hasFreeField(int $id): bool {
        foreach ($this->freeFields as $index => $freeField) {
            if (intval($freeField['id']) === $id) {
                return true;
            }
        }
        return false;
    }

    public function getFreeFieldValue(int $id): string {
        foreach ($this->freeFields as $index => $freeField) {
            if (intval($freeField['id']) === $id) {
                return $freeField['value'];
            }
        }
        return null;
    }

}
