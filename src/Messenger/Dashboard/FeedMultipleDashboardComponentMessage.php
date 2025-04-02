<?php

namespace App\Messenger\Dashboard;

use App\Messenger\DeduplicatedMessageInterface;
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
        $joinedComponentIds = Stream::from($this->componentIds)
            ->sort(static fn($a, $b) => $a <=> $b)
            ->join('_');

        // There can be a lot of dashboard components, so to avoid the key being too long, we hash it
        // the hash is not there to securing anything but just to have a shorter and unique key. 😉
        return hash('md5', $joinedComponentIds);
    }

    public function normalize(): array {
        return [
            "componentIds" => $this->getComponentIds(),
        ];
    }
}
