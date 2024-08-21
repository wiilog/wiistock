<?php

namespace App\EventListener\sse\entityListener;

use App\Entity\ProductionRequest;
use App\Service\ServerSentEventService;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

class ProductionListener
{

    private array $productionRequestDates;

    public function __construct(private readonly ServerSentEventService $serverSentEventService)
    {
        $this->productionRequestDates = [];
    }

    public function getSubscribedEvents(): array
    {
        return array(
            Events::preUpdate,
            Events::postFlush,
            Events::postPersist,
            Events::postRemove
        );
    }

    public function preUpdate(PreUpdateEventArgs $eventArgs): void
    {
        if ($eventArgs->getObject() instanceof ProductionRequest) {
            if ($eventArgs->hasChangedField('expectedAt')) {
                $this->productionRequestDates[] = $eventArgs->getOldValue('expectedAt')->format("Y-m-d");
                $this->productionRequestDates[] = $eventArgs->getNewValue('expectedAt')->format("Y-m-d");
            }
        }
    }

    public function postPersist(PostPersistEventArgs $eventArgs): void
    {
        if ($eventArgs->getObject() instanceof ProductionRequest) {
            $this->productionRequestDates[] = $eventArgs->getObject()->getExpectedAt()->format("Y-m-d");
        }
    }

    public function postRemove(PostRemoveEventArgs $eventArgs): void{
        if ($eventArgs->getObject() instanceof ProductionRequest) {
            $this->productionRequestDates[] = $eventArgs->getObject()->getExpectedAt()->format("Y-m-d");
        }
    }

    public function postFlush(): void
    {
        $this->serverSentEventService->sendEvent(
            ServerSentEventService::PRODUCTION_REQUEST_UPDATE_TOPIC,
            [
                "dates" => $this->productionRequestDates,
            ]
        );
    }

}
