<?php

namespace App\Messenger\Dashboard;

use App\Messenger\MessageInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueMessage;

class CalculateLatePackComponentsMessage implements UniqueMessage, MessageInterface {

    public function __construct(private array  $latePackComponentIds) {}

    public function getLatePackComponentIds(): ?array {
        return $this->latePackComponentIds;
    }

    public function getUniqueKey(): string {
        return "Type du composant : Colis en retard";
    }

    public function normalize(): array {
        return [
            "latePackComponentIds" => $this->getLatePackComponentIds(),
        ];
    }
}
