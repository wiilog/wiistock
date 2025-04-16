<?php

namespace App\Controller\Api\Mobile;

use App\Controller\AbstractController;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Service\DemandeCollecteService;
use App\Service\ExceptionLoggerService;
use App\Service\MouvementStockService;
use App\Service\OrdreCollecteService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Annotation as Wii;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile")]
class CollectRequestController extends AbstractController {

    #[Route("/check-manual-collect-scan", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function checkManualCollectScan(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);

        $barcode = $request->query->get("barCode");
        $reference = null;
        $article = null;
        if(str_starts_with($barcode, Article::BARCODE_PREFIX)) {
            $article = $articleRepository->findOneBy(["barCode" => $barcode]);
        } else {
            $reference = $referenceArticleRepository->findOneBy(["barCode" => $barcode]);
        }

        $reference = $reference ?? ($article->isInactive() ? $article->getReferenceArticle() : null);
        return $this->json([
            "reference" => $reference
                ? [
                    "id" => $reference->getId(),
                    "reference" => $reference->getReference(),
                    "label" => $reference->getLibelle(),
                    "location" => $reference->getEmplacement()?->getLabel(),
                    "quantityType" => $reference->getTypeQuantite(),
                    "refArticleBarCode" => $reference->getBarCode(),
                ]
                : [],
            "article" => $article?->isInactive() ? $article->getBarCode() : null,
        ]);
    }

    #[Route("/finish-manual-collect", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function finishManualCollect(Request                   $request,
                                        EntityManagerInterface    $entityManager,
                                        DemandeCollecteService    $demandeCollecteService,
                                        ExceptionLoggerService    $exceptionLoggerService,
                                        MouvementStockService     $mouvementStockService,
                                        OrdreCollecteService      $ordreCollecteService): Response
    {
        $data = $request->request;
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);
        $references = json_decode($data->get('references'), true);

        $date = new DateTime('now');

        //Création de la demande de collecte
        $collecte = $demandeCollecteService->createDemandeCollecte($entityManager, [
            'type' => $data->getInt('type'),
            'destination' => Collecte::STOCKPILLING_STATE,
            'demandeur' => $this->getUser()->getId(),
            'emplacement' => $data->getInt('pickLocation'),
            'Objet' => '',
            'commentaire' => '',
        ]);

        $entityManager->persist($collecte);

        try {
            $entityManager->flush();
        }
        catch (Exception $error) {
            $exceptionLoggerService->sendLog($error);
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la création de la demande de collecte',
            ]);
        }

        //Ajout des références dans la demande
        foreach ($references as $index => $reference){
            $refArticle = $referenceArticleRepository->findOneBy(['barCode' => $reference['refArticleBarCode']]);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                if ($collecteReferenceRepository->countByCollecteAndRA($collecte, $refArticle) > 0) {
                    $collecteReference = $collecteReferenceRepository->getByCollecteAndRA($collecte, $refArticle);
                    $collecteReference->setQuantite(intval($collecteReference->getQuantite()) + max(intval($data['quantity-to-pick']), 1)); // protection contre quantités < 1
                } else {
                    $collecteReference = new CollecteReference();
                    $collecteReference
                        ->setCollecte($collecte)
                        ->setReferenceArticle($refArticle)
                        ->setQuantite(max($reference['quantity-to-pick'], 1)); // protection contre quantités < 1

                    $entityManager->persist($collecteReference);

                    $collecte->addCollecteReference($collecteReference);
                }

                if($refArticle->getQuantiteStock() > 0 && $refArticle->getStatut()->getCode() === ReferenceArticle::STATUT_ACTIF) {
                    $mvtStock = $mouvementStockService->createMouvementStock(
                        $this->getUser(),
                        null,
                        $refArticle->getQuantiteStock(),
                        $refArticle,
                        MouvementStock::TYPE_ENTREE
                    );

                    $refArticle
                        ->setEditedBy($this->getUser())
                        ->setEditedAt($date);
                    $mvtStock->setEmplacementTo($refArticle->getEmplacement());
                    $entityManager->persist($mvtStock);
                }
            } else if ($refArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
                $article = $demandeCollecteService->persistArticleInDemand($reference, $refArticle, $collecte);
                $references[$index] = [
                    ...$reference,
                    "article-to-pick" => $article->getBarCode(),
                ];
            }
        }

        try {
            $entityManager->flush();
        } catch(Exception $error) {
            $exceptionLoggerService->sendLog($error);
            return new JsonResponse([
                'success' => false,
                'message' => "Erreur lors de l'ajout des références dans la demande de collecte"
            ]);
        }

        //Création de l'ordre de collecte
        $ordreCollecte = $ordreCollecteService->createCollectOrder($entityManager, $collecte);

        try {
            $entityManager->flush();
        }
        catch (Exception $error) {
            $exceptionLoggerService->sendLog($error);
            return new JsonResponse([
                'success' => false,
                'message' => "Erreur lors de la création de l'ordre de collecte",
            ]);
        }

        //Traitement de l'ordre de collecte
        try {
            $movements = Stream::from($references)
                ->map(static fn($reference) => [
                    'barcode' => $reference['article-to-pick'] ?? $reference['refArticleBarCode'],
                    'is_ref' => $reference['quantityType'] === ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                    'quantity' => $reference['quantity-to-pick'],
                    ...(isset($reference['dropLocation'])
                        ? ['depositLocationId' => $reference['dropLocation']]
                        : []
                    ),
                ])
                ->toArray();

            $ordreCollecteService->finishCollecte($ordreCollecte, $this->getUser(), $date, $movements);
        }
        catch(Exception $error) {
            $exceptionLoggerService->sendLog($error);
            return new JsonResponse([
                'success' => false,
                'message' => 'Une référence de la collecte n\'est pas active, vérifiez les transferts de stock en cours associés à celle-ci.'
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'La collecte manuelle a été effectué avec succès.',
        ]);
    }

}
