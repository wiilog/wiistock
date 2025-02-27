<?php

namespace App\Messenger\Dashboard;

use App\Messenger\MessageInterface;
use App\Service\Dashboard\DashboardComponentGenerator\DashboardComponentGenerator;
use App\Service\Dashboard\MultipleDashboardComponentGenerator\MultipleDashboardComponentGenerator;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueMessage;

class FeedDashboardComponentMessage implements UniqueMessage, MessageInterface {

    /**
     * @param class-string<DashboardComponentGenerator> $generatorClass
     */
    public function __construct(private int $componentId, private string $generatorClass) {}

    public function getComponentId(): ?int {
        return $this->componentId;
    }

    /**
     * @return class-string<DashboardComponentGenerator>
     */
    public function getGeneratorClass(): string {
        return $this->generatorClass;
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
