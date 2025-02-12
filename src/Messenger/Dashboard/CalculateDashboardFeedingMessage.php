<?php

namespace App\Messenger\Dashboard;

use App\Messenger\MessageInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueMessage;

class CalculateDashboardFeedingMessage implements UniqueMessage, MessageInterface {

    public function __construct(private ?int    $componentId,
                                private ?array  $latePackComponentIds) {}

    public function getComponentId(): ?int {
        return $this->componentId;
    }
    public function getLatePackComponentIds(): ?array {
        return $this->latePackComponentIds;
    }

    public function getUniqueKey(): string {
        return $this->componentId
            ? "Id du composant : $this->componentId"
            : ($this->latePackComponentIds
                ? "Type du composant : Colis en retard"
                : "Wiilock toggleFeedingCommand");
    }

    public function normalize(): array {
        return [
            "componentId" => $this->getComponentId(),
            "latePackComponentIds" => $this->getLatePackComponentIds(),
        ];
    }
}
