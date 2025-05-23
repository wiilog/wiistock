<?php

namespace App\Service\Emergency;


use App\Entity\Emergency\EmergencyTriggerEnum;
use App\Entity\Emergency\StockEmergency;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Repository\Emergency\StockEmergencyRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class StockEmergencyService
{
    public function __construct(
    ) {}


    public function trigger(
        EntityManagerInterface    $entityManager,
        ReferenceArticle          $referenceArticle,
        DateTime                  $now,
        ReceptionReferenceArticle $receptionReferenceArticle
    ): void {
        /** @var StockEmergencyRepository $stockEmergencyRepository */
        $stockEmergencyRepository = $entityManager->getRepository(StockEmergency::class);
        $emergenciesTriggeredByRefArticleOrSupplier = $stockEmergencyRepository->findEmergencyTriggeredByRefArticle($referenceArticle, $now);

        /** @var StockEmergency $stockEmergency */
        foreach ($emergenciesTriggeredByRefArticleOrSupplier as $stockEmergency) {
            $stockEmergency->setLastTriggeredAt($now);
            $receptionReferenceArticle->addStockEmergency($stockEmergency);
        }
    }
}
