<?php

namespace App\Messenger\Dashboard;

use App\Messenger\MessageInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\UniqueMessage;
use WiiCommon\Helper\Stream;

class CalculateComponentsWithDelayFeedingMessage implements UniqueMessage, MessageInterface {

    public function __construct(private array $groupedComponentIdsWithSameFilter) {}

    public function getGroupedComponentIdsWithSameFilter(): ?array {
        return $this->groupedComponentIdsWithSameFilter;
    }

    public function getUniqueKey(): string {
        return "Calcul des composants avec les mêmes délais : " . Stream::from($this->getGroupedComponentIdsWithSameFilter()['componentId'])->join(',');
    }

    public function normalize(): array {
        return [
            "componentsWithDelayIds" => $this->getGroupedComponentIdsWithSameFilter(),
        ];
    }
}
