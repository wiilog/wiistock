<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Entity\RequestTemplate\RequestTemplateLineArticle;
use App\Entity\RequestTemplate\RequestTemplateLineReference;
use App\Exceptions\FormException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class RequestTemplateLineService {

    public function createRequestTemplateLineReference(EntityManagerInterface $entityManager,
                                                       int                    $referenceArticleId,
                                                       DateTime               $now): RequestTemplateLineReference {
        $referenceArticle = $entityManager->getReference(ReferenceArticle::class, $referenceArticleId);
        $referenceArticle->setLastSleepingStockAlertAnswer($now);
        return (New RequestTemplateLineReference())
            ->setReference($referenceArticle)
            ->setQuantityToTake($referenceArticle->getQuantiteDisponible());
    }

    public function createRequestTemplateLineArticle(EntityManagerInterface $entityManager,
                                                     int                    $articleId,
                                                     DateTime               $now): RequestTemplateLineArticle {
        $article = $entityManager->getReference(Article::class, $articleId);
        $article->getReferenceArticle()->setLastSleepingStockAlertAnswer($now);
        $article->setLastSleepingStockAlertAnswer($now);
        return (New RequestTemplateLineArticle())
            ->setArticle($article)
            ->setQuantityToTake($article->getQuantite());
    }
}
