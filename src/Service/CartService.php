<?php

namespace App\Service;

use App\Entity\ArticleFournisseur;
use App\Entity\Cart;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
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

    public function renderDeliveryTypeModal(Cart $cart, EntityManagerInterface $entityManager) {
        $deliveryRepository = $entityManager->getRepository(Demande::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $parametreRepository = $entityManager->getRepository(Parametre::class);
        $parametreRoleRepository = $entityManager->getRepository(ParametreRole::class);

        $managed = $parametreRoleRepository->findOneBy([
            'role' => $cart->getUser()->getRole(),
            'parametre' => $parametreRepository->findOneBy([
                'label' => Parametre::LABEL_AJOUT_QUANTITE
            ]),
        ]);

        $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_BROUILLON);
        $refs = Stream::from($cart->getRefArticle())
            ->map(function(ReferenceArticle $referenceArticle) {
                return [
                    'articles' => $referenceArticle->getAssociatedArticles(),
                    'reference' => $referenceArticle->getReference(),
                ];
            });
        $deliveries = $deliveryRepository->findBy([
            'utilisateur' => $cart->getUser(),
            'statut' => $draft
        ]);

        return $this->twig->render('cart/deliveryTypeContent.html.twig', [
            'refs' => $refs,
            'deliveries' => $deliveries,
            'managedByArticle' => $managed && $managed->getValue() == Parametre::VALUE_PAR_ART,
        ]);
    }

}
