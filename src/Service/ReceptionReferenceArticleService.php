<?php

namespace App\Service;


use App\Entity\ArticleFournisseur;
use App\Entity\ReceptionReferenceArticle;

class ReceptionReferenceArticleService {

    public function serializeForSelect(ReceptionReferenceArticle $item): array {
        $receptionLine = $item->getReceptionLine();
        $pack = $receptionLine->getPack();
        $referenceArticle = $item->getReferenceArticle();

        /** @var ArticleFournisseur|null $defaultSupplierArticle */
        $defaultSupplierArticle = $referenceArticle->getArticlesFournisseur()->count() === 1
            ? ($referenceArticle->getArticlesFournisseur()->first() ?: null)
            : null;

        return [
            "id" => "{$referenceArticle->getReference()}-{$item->getCommande()}",
            "reference" => $referenceArticle->getReference(),
            "orderNumber" => $item->getCommande(),
            "pack" => $pack
                ? [
                    "id" => $pack->getId(),
                    "code" => $pack->getCode(),
                ]
                : null,
            "text" => "{$referenceArticle->getReference()} – {$item->getCommande()}",
            "defaultArticleFournisseur" => $defaultSupplierArticle
                ? [
                    'text' => $defaultSupplierArticle->getReference(),
                    'value' => $defaultSupplierArticle->getId(),
                ]
                : null
        ];
    }
}
