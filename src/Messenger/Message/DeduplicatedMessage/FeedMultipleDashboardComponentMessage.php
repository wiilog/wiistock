<?php

namespace App\Messenger\Message\DeduplicatedMessage;

use App\Service\Dashboard\MultipleDashboardComponentGenerator\MultipleDashboardComponentGenerator;
use WiiCommon\Helper\Stream;

class FeedMultipleDashboardComponentMessage implements DeduplicatedMessageInterface {

    /**
     * @param class-string<MultipleDashboardComponentGenerator> $generatorClass
     */
    public function __construct(
        private array  $componentIds,
        private string $generatorClass
    ) {
    }

    public function getComponentIds(): ?array {
        return $this->componentIds;
    }

    /**
     * @return class-string<MultipleDashboardComponentGenerator>
     */
    public function getGeneratorClass(): string {
        return $this->generatorClass;
    }

    public function getUniqueKey(): string {
        $classCode = str_replace("\\", "_", get_class($this));
        $componentIds = Stream::from($this->componentIds)
            ->sort(static fn($a, $b) => $a <=> $b)
            ->join('_');
        return "{$classCode}_{$componentIds}";
    }

    public function normalize(): array {
        return [
            "componentIds" => $this->getComponentIds(),
        ];
    }
}
