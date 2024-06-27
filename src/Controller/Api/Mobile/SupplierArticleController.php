<?php

namespace App\Controller\Api\Mobile;

use App\Controller\AbstractController;
use App\Entity\ArticleFournisseur;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use App\Annotation as Wii;
use Symfony\Component\HttpFoundation\Response;
use WiiCommon\Helper\Stream;

#[Rest\Route("/api/mobile")]
class SupplierArticleController extends AbstractController {


    #[Rest\Get("/supplier_reference/{ref}/{supplier}", condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function getArticleFournisseursByRefAndSupplier(EntityManagerInterface $entityManager, int $ref, int $supplier): Response
    {
        $articlesFournisseurs = $entityManager->getRepository(ArticleFournisseur::class)->getByRefArticleAndFournisseur($ref, $supplier);
        $formattedReferences = Stream::from($articlesFournisseurs)
            ->map(static fn (ArticleFournisseur $supplier) => [
                'label' => $supplier->getReference(),
                'id' => $supplier->getId(),
            ])
            ->toArray();

        return $this->json([
            'supplierReferences' => $formattedReferences
        ]);
    }

}
