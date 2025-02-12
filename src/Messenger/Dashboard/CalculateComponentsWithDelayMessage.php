<?php

namespace App\Messenger\Dashboard;

use App\Messenger\MessageInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueMessage;

class CalculateComponentsWithDelayMessage implements UniqueMessage, MessageInterface {

    public function __construct(private array $componentsWithDelayIds) {}

    public function getComponentsWithDelayIds(): ?array {
        return $this->componentsWithDelayIds;
    }

    public function getUniqueKey(): string {
        return "Groupage des composants avec délais";
    }

    public function normalize(): array {
        return [
            "componentsWithDelayIds" => $this->getComponentsWithDelayIds(),
        ];
    }
}
