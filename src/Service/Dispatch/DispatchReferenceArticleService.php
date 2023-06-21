<?php

namespace App\Service\Dispatch;

use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use DateTime;
use Doctrine\ORM\EntityManager;

class DispatchReferenceArticleService
{

    public function deleteReference(EntityManager            $entityManager,
                                    DispatchReferenceArticle $dispatchReferenceArticle,
                                    DispatchPack             $dispatchPack){
        $dispatchReferenceArticle->getDispatchPack()->getDispatch()->setUpdatedAt(new DateTime());
        $dispatchPack->removeDispatchReferenceArticles($dispatchReferenceArticle);
        $entityManager->remove($dispatchReferenceArticle);
    }
}
