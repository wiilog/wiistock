<?php

namespace App\Messenger\Dashboard;

use App\Messenger\MessageInterface;
use App\Service\Dashboard\MultipleDashboardComponentGenerator\MultipleDashboardComponentGenerator;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueMessage;
use WiiCommon\Helper\Stream;

class FeedMultipleDashboardComponentMessage implements UniqueMessage, MessageInterface {

    /**
     * @param class-string<MultipleDashboardComponentGenerator> $generatorClass
     */
    public function __construct(private array $componentIds,  private string $generatorClass) {}

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
        return Stream::from($this->componentIds)->sort(static fn($a, $b) => $a <=> $b)->join('_');
    }

    public function normalize(): array {
        return [
            "componentIds" => $this->getComponentIds(),
        ];
    }
}
