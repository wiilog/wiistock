<?php

namespace App\Messenger\Dashboard;

use App\Messenger\DeduplicatedMessageInterface;
use App\Service\Dashboard\DashboardComponentGenerator\DashboardComponentGenerator;

class FeedDashboardComponentMessage implements DeduplicatedMessageInterface {

    /**
     * @param class-string<DashboardComponentGenerator> $generatorClass
     */
    public function __construct(
        private int    $componentId,
        private string $generatorClass
    ) {
    }

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
