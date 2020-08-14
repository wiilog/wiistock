<?php


namespace App\Service;

use App\Entity\Nature;

class NatureService
{
    public function serializeNature(Nature $nature): array {
        return [
            'id' => $nature->getId(),
            'label' => $nature->getLabel(),
            'color' => $nature->getColor(),
            'hide' => (bool) !$nature->getNeedsMobileSync()
        ];
    }
}
