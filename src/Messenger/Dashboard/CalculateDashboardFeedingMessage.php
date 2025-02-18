<?php

namespace App\Messenger\Dashboard;

use App\Messenger\MessageInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueMessage;

class CalculateDashboardFeedingMessage implements UniqueMessage, MessageInterface {

    public function __construct(private int $componentId) {}

    public function getComponentId(): ?int {
        return $this->componentId;
    }

    public function getUniqueKey(): string {
        return $this->componentId;
    }

    public function normalize(): array {
        return [
            "componentId" => $this->getComponentId(),
        ];
    }
}
