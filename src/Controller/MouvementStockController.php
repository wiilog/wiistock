<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;

use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;

use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\MouvementStockService;
use App\Service\TrackingMovementService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;

/**
 * @Route("/mouvement-stock")
 */
class MouvementStockController extends AbstractController
{

    /**
     * @Route("/", name="mouvement_stock_index")
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function index(UserService $userService,
                          EntityManagerInterface $entityManager)
    {
        if (!$userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_MOUV_STOC)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);

        return $this->render('mouvement_stock/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_STOCK),
            'typesMvt' => [
                MouvementStock::TYPE_ENTREE,
                MouvementStock::TYPE_SORTIE,
                MouvementStock::TYPE_TRANSFER,
            ]
        ]);
    }

    /**
     * @Route("/api", name="mouvement_stock_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param MouvementStockService $mouvementStockService
     * @return Response
     * @throws Exception
     */
    public function api(Request $request,
                        UserService $userService,
                        MouvementStockService $mouvementStockService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_MOUV_STOC)) {
                return $this->redirectToRoute('access_denied');
            }

            /** @var Utilisateur $user */
            $user = $this->getUser();

            $data = $mouvementStockService->getDataForDatatable($user, $request->request);
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="mvt_stock_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            $movement = $mouvementStockRepository->find($data['mvt']);

            if (!$userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            if (!empty($trackingMovementRepository->findBy(['mouvementStock' => $movement]))) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce mouvement de stock est lié à des mouvements de traçabilité.'
                ]);
            }

            $entityManager->remove($movement);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/nouveau", name="mvt_stock_new", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param MouvementStockService $mouvementStockService
     * @param TrackingMovementService $trackingMovementService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function new(Request $request,
                        MouvementStockService $mouvementStockService,
                        TrackingMovementService $trackingMovementService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
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
            $chosenMvtType = $data["chosen-type-mvt"];
            $chosenMvtQuantity = $data["chosen-mvt-quantity"];
            $chosenMvtLocation = $data["chosen-mvt-location"];
            $movementBarcode = $data["movement-barcode"];
            $unavailableArticleStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);
            $chosenArticleToMove = $referenceArticleRepository->findOneBy(['barCode' => $movementBarcode]);
            if (empty($chosenArticleToMove)) {
                $chosenArticleToMove = $articleRepository->findOneBy(['barCode' => $movementBarcode]);
            }
            $chosenArticleStatus = $chosenArticleToMove->getStatut();
            $chosenArticleStatusName = $chosenArticleStatus ? $chosenArticleStatus->getNom() : null;
            if (empty($chosenArticleToMove) || !in_array($chosenArticleStatusName, [ReferenceArticle::STATUT_ACTIF, Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])) {
                $response['msg'] = 'Le statut de la référence ou de l\'article choisi est incorrect, il doit être actif.';
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

                if ($chosenMvtType === MouvementStock::TYPE_SORTIE) {
                    if (intval($quantity) > $chosenArticleToMoveAvailableQuantity) {
                        $response['msg'] = 'La quantité saisie est superieure à la quantité disponible de la référence.';
                    } else {
                        $response['success'] = true;
                        $response['msg'] = "Mouvement créé avec succès";
                        $emplacementFrom = $chosenArticleToMove->getEmplacement();
                        if ($chosenArticleToMove instanceof ReferenceArticle) {
                            $chosenArticleToMove
                                ->setQuantiteStock($chosenArticleToMoveStockQuantity - $quantity);
                        } else {
                            if ($chosenArticleToMoveStockQuantity - $quantity === 0) {
                                $chosenArticleToMove->setStatut($unavailableArticleStatus);
                            } else {
                                $chosenArticleToMove->setQuantite($chosenArticleToMoveStockQuantity - $quantity);
                            }
                        }
                    }
                } else if ($chosenMvtType === MouvementStock::TYPE_ENTREE) {
                    $response['success'] = true;
                    $response['msg'] = "Mouvement créé avec succès";
                    $emplacementTo = $chosenArticleToMove->getEmplacement();
                    if ($chosenArticleToMove instanceof ReferenceArticle) {
                        $chosenArticleToMove
                            ->setQuantiteStock($chosenArticleToMoveStockQuantity + $quantity);
                    } else {
                        $chosenArticleToMove
                            ->setQuantite($chosenArticleToMoveAvailableQuantity + $quantity);
                    }
                } else if ($chosenMvtType === MouvementStock::TYPE_TRANSFER) {
                    $chosenLocation = $emplacementRepository->find($chosenMvtLocation);
                    if ($chosenArticleToMove->isUsedInQuantityChangingProcesses()) {
                        $response['msg'] = 'La référence saisie est présente dans une demande de livraison/collecte/transfert en cours de traitement, impossible de la transférer.';
                    } else if (empty($chosenLocation)) {
                        $response['msg'] = 'L\'emplacement saisi est inconnu.';
                    } else {
                        $response['success'] = true;
                        $response['msg'] = "Mouvement créé avec succès";
                        $quantity = $chosenArticleToMoveAvailableQuantity;
                        $emplacementTo = $chosenLocation;
                        $emplacementFrom = $chosenArticleToMove->getEmplacement();
                        $chosenArticleToMove->setEmplacement($emplacementTo);
                        $associatedPickTracaMvt = $trackingMovementService->createTrackingMovement(
                            $chosenArticleToMove->getBarCode(),
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
                    $newMvtStock = $mouvementStockService->createMouvementStock($loggedUser, $emplacementFrom, $quantity, $chosenArticleToMove, $chosenMvtType);
                    $mouvementStockService->finishMouvementStock($newMvtStock, $now, $emplacementTo);
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
     * @Route("/mouvement-stock-infos", name="get_mouvements_stock_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return StreamedResponse
     */
    public function getMouvementIntels(Request $request,
                                       EntityManagerInterface $entityManager,
                                       CSVExportService $CSVExportService): StreamedResponse
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        $headers = [
            'date',
            'ordre',
            'référence article',
            'quantité',
            'origine',
            'destination',
            'type',
            'opérateur',
            'code barre référence article',
            'code barre article',
        ];

        return $CSVExportService->streamResponse(

            function ($output) use ($entityManager, $dateTimeMin, $dateTimeMax, $CSVExportService) {
                $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
                $mouvements = $mouvementStockRepository->getByDates($dateTimeMin, $dateTimeMax);

                foreach ($mouvements as $mouvement) {
                    $this->putMouvementLine($output, $CSVExportService, $mouvement);
                }
            }, 'Export_mouvement_Stock.csv',
            $headers
        );
    }

    private function putMouvementLine($handle,
                                      CSVExportService $CSVExportService,
                                      array $mouvement)
    {
        $reference = $mouvement['articleRef']
            ?? $mouvement['refArticleRef']
            ?? null;

        $orderNo = $mouvement['preparationOrder']
            ?? $mouvement['livraisonOrder']
            ?? $mouvement['collecteOrder']
            ?? $mouvement['receptionOrder']
            ?? null;

        $data = [
            FormatHelper::datetime($mouvement['date']),
            $orderNo,
            $reference,
            $mouvement['quantity'],
            $mouvement['originEmpl'],
            $mouvement['destinationEmpl'],
            $mouvement['type'],
            $mouvement['operator'],
            !empty($mouvement['refArticleBarCode']) ? $mouvement['refArticleBarCode']: '',
            !empty($mouvement['articleBarCode']) ? $mouvement['articleBarCode'] : '',
        ];
        $CSVExportService->putLine($handle, $data);
    }
}
