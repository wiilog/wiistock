<?php

namespace App\Service;

use App\Entity\ArticleFournisseur;
use App\Entity\ReferenceArticle;
use App\Helper\Stream;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;

class CartService {

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public Environment $twig;

    /** @Required */
    public Security $security;

    public function getDataForDatatable($params = null) {
        $referenceRepository = $this->manager->getRepository(ReferenceArticle::class);

        $queryResult = $referenceRepository->findInCart($this->security->getUser(), $params);

        $references = $queryResult['data'];

        $rows = [];
        foreach ($references as $reference) {
            $rows[] = $this->dataRowReference($reference);
        }

        return [
            "data" => $rows,
            "recordsFiltered" => $queryResult["count"],
            "recordsTotal" => $queryResult["total"],
        ];
    }

    private function dataRowReference(ReferenceArticle $reference): array {
        return [
            "actions" => "<i class='fas fa-trash remove-reference pointer' data-id='{$reference->getId()}'></i>",
            "label" => $reference->getLibelle(),
            "reference" => $reference->getReference(),
            "supplierReference" => Stream::from($reference->getArticlesFournisseur())
                ->map(fn(ArticleFournisseur $article) => $article->getReference())
                ->join(";"),
            "type" => $reference->getType()->getLabel(),
            "availableQuantity" => $reference->getQuantiteDisponible(),
        ];
    }

}
