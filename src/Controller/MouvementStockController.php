<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\MouvementStockService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/mouvement-stock")]
class MouvementStockController extends AbstractController
{

    #[Route("/", name: "mouvement_stock_index")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_MOV_STOCK])]
    public function index(EntityManagerInterface $entityManager,
                          MouvementStockService  $mouvementStockService): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('mouvement_stock/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_STOCK),
            'typesMvt' => [
                MouvementStock::TYPE_ENTREE,
                MouvementStock::TYPE_SORTIE,
                MouvementStock::TYPE_TRANSFER,
            ],
            "fields" => $mouvementStockService->getColumnVisibleConfig($user)
        ]);
    }

    #[Route("/api", name: "mouvement_stock_api", options: ["expose" => true], methods: ["GET", "POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_MOV_STOCK], mode: HasPermission::IN_JSON)]
    public function api(Request $request, MouvementStockService $mouvementStockService): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data = $mouvementStockService->getDataForDatatable($user, $request->request);
        return new JsonResponse($data);
    }

    #[Route("/api-columns", name: "mouvement_stock_api_columns", options: ["expose" => true], methods: [self::GET,self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DISPLAY_MOV_STOCK])]
    public function apiColumns(MouvementStockService $mouvementStockService, EntityManagerInterface $entityManager): Response {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $columns = $mouvementStockService->getColumnVisibleConfig($currentUser);
        return new JsonResponse($columns);
    }

    #[Route("/delete/{mvtStock}", name: "mvt_stock_delete", options: ["expose" => true], methods: ["GET", "POST", "DELETE"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::STOCK, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request, EntityManagerInterface $entityManager, MouvementStock $mvtStock): Response
    {
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

        if (!empty($trackingMovementRepository->findBy(['mouvementStock' => $mvtStock]))) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Ce mouvement de stock est lié à des mouvements de traçabilité.'
            ]);
        }

        $entityManager->remove($mvtStock);
        $entityManager->flush();
        return new JsonResponse([
            'success' => true,
        ]);
    }

    #[Route("/nouveau", name: "mvt_stock_new", options: ["expose" => true], methods: ["GET", "POST"], condition: "request.isXmlHttpRequest()")]
    public function new(Request                 $request,
                        MouvementStockService   $mouvementStockService,
                        TrackingMovementService $trackingMovementService,
                        EntityManagerInterface  $entityManager,
                        TranslationService      $translation): Response
    {
        $chosenMvtType = $request->request->get("chosen-type-mvt");
        $chosenRefQuantity = $request->request->get("chosen-ref-quantity");
        $chosenRefLabel = $request->request->get("chosen-ref-label");

        if ($chosenMvtType
            && $chosenRefQuantity != null
            && $chosenRefLabel) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $statusRepository = $entityManager->getRepository(Statut::class);

            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            $response = [
                'success' => false,
                'msg' => 'Mauvais type de mouvement choisi.'
            ];
            $chosenMvtQuantity = $request->request->get("chosen-mvt-quantity");
            $chosenMvtLocation = $request->request->get("chosen-mvt-location");
            $movementBarcode = $request->request->get("chosen-ref-barcode")
                               ?: $request->request->get("chosen-art-barcode");
            $movementComment = $request->request->get("comment");

            /** @var Article|ReferenceArticle|null $chosenArticleToMove */
            $chosenArticleToMove = (
                $referenceArticleRepository->findOneBy(['barCode' => $movementBarcode])
                ?: $articleRepository->findOneBy(['barCode' => $movementBarcode])
            );

            if (empty($chosenArticleToMove)
                || !$mouvementStockService->isArticleMovable($chosenMvtType, $chosenArticleToMove)) {
                $response['msg'] = 'Le statut de la référence ou de l\'article choisi est incorrect.';
            } else {
                $now = new DateTime();
                $emplacementTo = null;
                $emplacementFrom = null;
                $quantity = $chosenMvtQuantity;
                $associatedPickTracaMvt = null;
                $associatedDropTracaMvt = null;
                $chosenArticleToMoveAvailableQuantity = $chosenArticleToMove instanceof ReferenceArticle
                    ? $chosenArticleToMove->getQuantiteDisponible()
                    : $chosenArticleToMove->getQuantite();

                $chosenArticleToMoveStockQuantity = $chosenArticleToMove instanceof ReferenceArticle
                    ? $chosenArticleToMove->getQuantiteStock()
                    : $chosenArticleToMove->getQuantite();

                if ($chosenMvtType === MouvementStock::TYPE_ENTREE) {
                    $response['success'] = true;
                    $response['msg'] = "Mouvement créé avec succès";
                    $emplacementTo = $chosenArticleToMove->getEmplacement();
                    if ($chosenArticleToMove instanceof ReferenceArticle) {
                        $chosenArticleToMove
                            ->setQuantiteStock($chosenArticleToMoveStockQuantity + $quantity);
                    }
                    // article
                    else {
                        // "consommé"
                        if($chosenArticleToMove->getStatut()->getCode() === Article::STATUT_INACTIF){
                            $actifStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);

                            $chosenArticleToMove
                                ->setQuantite($quantity)
                                ->setStatut($actifStatus);
                        }
                        // "disponible"
                        else {
                            $chosenArticleToMove
                                ->setQuantite($chosenArticleToMoveStockQuantity + $quantity);
                        }
                    }

                    $associatedDropTracaMvt = $trackingMovementService->createTrackingMovement(
                        $chosenArticleToMove->getTrackingPack() ?: $chosenArticleToMove->getBarCode(),
                        $emplacementTo,
                        $loggedUser,
                        $now,
                        false,
                        true,
                        TrackingMovement::TYPE_DEPOSE,
                        ['quantity' => $quantity]
                    );

                    $trackingMovementService->persistSubEntities($entityManager, $associatedDropTracaMvt);
                    $entityManager->persist($associatedDropTracaMvt);
                } else if ($chosenMvtType === MouvementStock::TYPE_TRANSFER || $chosenMvtType === MouvementStock::TYPE_SORTIE) {
                    $chosenLocation = $emplacementRepository->find($chosenMvtLocation);
                    if (($chosenArticleToMove instanceof Article && !in_array($chosenArticleToMove->getStatut()->getCode(), [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]))
                        || ($chosenArticleToMove instanceof ReferenceArticle && $referenceArticleRepository->isUsedInQuantityChangingProcesses($chosenArticleToMove))) {
                        $response['msg'] = 'La référence saisie est présente dans une demande de ' . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . '/collecte/transfert en cours de traitement, impossible de la transférer.';
                    } else if (intval($quantity) > $chosenArticleToMoveAvailableQuantity) {
                        $response['msg'] = 'La quantité saisie est superieure à la quantité disponible de la référence.';
                    } else if (empty($chosenLocation)) {
                        $response['msg'] = 'L\'emplacement de dépose est inconnu.';
                    } else {
                        $response['success'] = true;
                        $response['msg'] = "Mouvement créé avec succès";
                        $emplacementTo = $chosenLocation;
                        $emplacementFrom = $chosenArticleToMove->getEmplacement();
                        if ($chosenMvtType === MouvementStock::TYPE_TRANSFER) {
                            $quantity = $chosenArticleToMoveAvailableQuantity;
                            $chosenArticleToMove->setEmplacement($emplacementTo);
                        } else {
                            if ($chosenArticleToMove instanceof ReferenceArticle) {
                                $chosenArticleToMove
                                    ->setQuantiteStock($chosenArticleToMoveStockQuantity - $quantity);
                            } else {
                                if ($chosenArticleToMoveStockQuantity - $quantity === 0) {
                                    $unavailableArticleStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);
                                    $chosenArticleToMove->setStatut($unavailableArticleStatus);
                                } else {
                                    $chosenArticleToMove
                                        ->setQuantite($chosenArticleToMoveStockQuantity - $quantity);
                                }
                            }
                        }
                        $associatedPickTracaMvt = $trackingMovementService->createTrackingMovement(
                            $chosenArticleToMove->getTrackingPack() ?: $chosenArticleToMove->getBarCode(),
                            $emplacementFrom,
                            $loggedUser,
                            $now,
                            false,
                            true,
                            TrackingMovement::TYPE_PRISE,
                            ['quantity' => $quantity]
                        );
                        $trackingMovementService->persistSubEntities($entityManager, $associatedPickTracaMvt);
                        $createdPack = $associatedPickTracaMvt->getPack();

                        if ($chosenArticleToMove instanceof Article && $chosenArticleToMove->getCurrentLogisticUnit()){
                            $associatedPickLUTracaMvt = $trackingMovementService->createTrackingMovement(
                                $associatedPickTracaMvt->getPack(),
                                $emplacementFrom,
                                $loggedUser,
                                $now,
                                false,
                                true,
                                TrackingMovement::TYPE_PICK_LU,
                                ['quantity' => $quantity]
                            );
                            $trackingMovementService->persistSubEntities($entityManager, $associatedPickLUTracaMvt);
                            $entityManager->persist($associatedPickLUTracaMvt);
                        }

                        $associatedDropTracaMvt = $trackingMovementService->createTrackingMovement(
                            $createdPack,
                            $emplacementTo,
                            $loggedUser,
                            $now,
                            false,
                            true,
                            TrackingMovement::TYPE_DEPOSE,
                            ['quantity' => $quantity]
                        );
                        $trackingMovementService->persistSubEntities($entityManager, $associatedDropTracaMvt);
                        $entityManager->persist($associatedPickTracaMvt);
                        $entityManager->persist($associatedDropTracaMvt);
                    }
                }

                if ($response['success']) {
                    $newMvtStock = $mouvementStockService->createMouvementStock($loggedUser, $emplacementFrom, $quantity, $chosenArticleToMove, $chosenMvtType,["comment" => $movementComment]);
                    $mouvementStockService->finishStockMovement($newMvtStock, $now, $emplacementTo);
                    if ($associatedDropTracaMvt && $associatedPickTracaMvt) {
                        $associatedPickTracaMvt->setMouvementStock($newMvtStock);
                        $associatedDropTracaMvt->setMouvementStock($newMvtStock);
                    }
                    $entityManager->persist($newMvtStock);
                    $entityManager->flush();
                }
            }
            return new JsonResponse($response);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @param Request $request
     * @param MouvementStockService $mouvementStockService
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return StreamedResponse
     * @throws Exception
     */
    #[Route("/csv", name: "get_stock_movements_csv", options: ["expose" => true], methods: ["GET"])]
    public function getStockMovementsCSV(Request $request,
                                         MouvementStockService $mouvementStockService,
                                         EntityManagerInterface $entityManager,
                                         CSVExportService $CSVExportService): StreamedResponse
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $headers = [
                'date',
                'ordre',
                'référence article',
                'code barre référence article',
                'code barre article',
                'quantité',
                'origine',
                'destination',
                'type',
                'opérateur',
                "prix unitaire",
                "commentaire",
            ];

            $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
            $movementIterator = $mouvementStockRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            return $CSVExportService->streamResponse(
                function ($output) use ($movementIterator, $mouvementStockService, $CSVExportService) {
                    foreach ($movementIterator as $movement) {
                        $mouvementStockService->putMovementLine($output, $CSVExportService, $movement);
                    }
                }, 'Export_mouvement_Stock.csv',
                $headers
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }
}
