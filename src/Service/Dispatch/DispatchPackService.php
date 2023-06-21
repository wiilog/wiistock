<?php

namespace App\Service\Dispatch;

use App\Entity\DispatchPack;
use DateTime;
use Doctrine\ORM\EntityManager;

class DispatchPackService
{
    public function deletePack(EntityManager $entityManager,
                               DispatchPack  $dispatchPack)
    {
        if($dispatchPack->getDispatchReferenceArticles()->isEmpty()){
            $dispatchPack->getDispatch()->setUpdatedAt(new DateTime());
            $entityManager->remove($dispatchPack);
        }
    }
}
