<?php


namespace App\Service;


use App\Entity\Statut;
use App\Entity\PurchaseRequest;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;

class PurchaseRequestService
{
    private $uniqueNumberService;

    public function __construct(UniqueNumberService $uniqueNumberService){
        $this->uniqueNumberService = $uniqueNumberService;
    }

    public function createPurchaseRequest(EntityManagerInterface $entityManager,
                                          ?Statut $status,
                                          ?Utilisateur $requester,
                                          ?string $comment = null): PurchaseRequest {
        $now =  new DateTime("now", new DateTimeZone("Europe/Paris"));
        $purchase = new PurchaseRequest();
        $purchaseRequestNumber = $this->uniqueNumberService->createUniqueNumber($entityManager, PurchaseRequest::NUMBER_PREFIX, PurchaseRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $purchase
            ->setCreationDate($now)
            ->setStatus($status)
            ->setRequester($requester)
            ->setComment($comment)
            ->setNumber($purchaseRequestNumber);

        return $purchase;
    }

}
