<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Setting;
use App\Entity\PreparationOrder\PreparationOrderArticleLine;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Exceptions\NegativeQuantityException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\NotificationService;
use App\Service\PDFGeneratorService;
use App\Service\PreparationsManagerService;
use App\Service\RefArticleDataService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ArticleDataService;
use App\Entity\DeliveryRequest\Demande;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use WiiCommon\Helper\Stream;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @Route("/finir/{idPrepa}", name="preparation_finish", methods={"POST"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finishPrepa($idPrepa,
                                Request $request,
                                NotificationService $notificationService,
                                EntityManagerInterface $entityManager,
                                LivraisonsManagerService $livraisonsManager,
                                PreparationsManagerService $preparationsManager) {

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $preparation = $preparationRepository->find($idPrepa);
        $locationEndPrepa = $emplacementRepository->find($request->request->get('emplacement'));

        try {
            $articlesNotPicked = $preparationsManager->createMouvementsPrepaAndSplit($preparation, $this->getUser(), $entityManager);
        } catch (NegativeQuantityException $exception) {
            $barcode = $exception->getArticle()->getBarCode();
            return new JsonResponse([
                'success' => false,
                'message' => "La quantité en stock de l'article $barcode est inférieure à la quantité prélevée."
            ]);
        }
        $dateEnd = new DateTime('now');
        $livraison = $livraisonsManager->createLivraison($dateEnd, $preparation, $entityManager);

        $preparationsManager->treatPreparation($preparation, $this->getUser(), $locationEndPrepa, $articlesNotPicked);
        $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $locationEndPrepa);

        $mouvementRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementRepository->findByPreparation($preparation);
        $entityManager->flush();

        foreach ($mouvements as $mouvement) {
            if ($mouvement->getType() === MouvementStock::TYPE_TRANSFER) {
                $preparationsManager->createMouvementLivraison(
                    $mouvement->getQuantity(),
                    $this->getUser(),
                    $livraison,
                    !empty($mouvement->getRefArticle()),
                    $mouvement->getRefArticle() ?? $mouvement->getArticle(),
                    $preparation,
                    false,
                    $locationEndPrepa
                );
            }
        }
        $entityManager->flush();
        if ($livraison->getDemande()->getType()->isNotificationsEnabled()) {
            $notificationService->toTreat($livraison);
        }
        $preparationsManager->updateRefArticlesQuantities($preparation);

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('livraison_show', [
                'id' => $livraison->getId()
            ])
        ]);
    }

    /**
     * @Route("/liste/{demandId}", name="preparation_index", methods="GET|POST")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_PREPA})
     */
    public function index(EntityManagerInterface $entityManager,
                          string                 $demandId = null): Response
    {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);

        $demandeLivraison = $demandId ? $demandeRepository->find($demandId) : null;

        return $this->render('preparation/index.html.twig', [
            'filterDemandId' => isset($demandeLivraison) ? $demandId : null,
            'filterDemandValue' => isset($demandeLivraison) ? $demandeLivraison->getNumero() : null,
            'filtersDisabled' => isset($demandeLivraison),
            'displayDemandFilter' => true,
            'statuts' => $statutRepository->findByCategorieName(Preparation::CATEGORIE),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON])
        ]);
    }

    /**
     * @Route("/api", name="preparation_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_PREPA}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        PreparationsManagerService $preparationsManagerService): Response
    {
        $filterDemand = $request->request->get('filterDemand');
        $data = $preparationsManagerService->getDataForDatatable($request->request, $filterDemand);

        return new JsonResponse($data);
    }


    /**
     * @Route("/api_article/{preparation}", name="preparation_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_PREPA}, mode=HasPermission::IN_JSON)
     */
    public function apiLignePreparation(Preparation $preparation): Response
    {
        $demande = $preparation->getDemande();
        $preparationStatut = $preparation->getStatut() ? $preparation->getStatut()->getNom() : null;
        $isPrepaEditable =
            $preparationStatut === Preparation::STATUT_A_TRAITER
            || $preparationStatut === Preparation::STATUT_VALIDATED
            || ($preparationStatut == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser());

        if (isset($demande)) {
            $rows = [];
            /** @var PreparationOrderReferenceLine $referenceLine */
            foreach ($preparation->getReferenceLines() as $referenceLine) {
                $articleRef = $referenceLine->getReference();
                $isRefByArt = $articleRef->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE;
                if ($referenceLine->getPickedQuantity() > 0 ||
                    ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                    $rows[] = [
                        "reference" => $articleRef->getReference(),
                        "label" => $articleRef->getLibelle(),
                        "location" => FormatHelper::location($articleRef->getEmplacement()),
                        "targetLocationPicking" => FormatHelper::location($referenceLine->getTargetLocationPicking()),
                        "quantity" => $articleRef->getQuantiteStock(),
                        "quantityToPick" => $referenceLine->getQuantityToPick() ?: ' ',
                        "pickedQuantity" => $referenceLine->getPickedQuantity() ?: ' ',
                        'active' => !empty($referenceLine->getPickedQuantity()),
                        "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                            'barcode' => $articleRef->getBarCode(),
                            'isRef' => true,
                            'artOrRefId' => $articleRef->getId(),
                            'isRefByArt' => $isRefByArt,
                            'id' => $referenceLine->getId(),
                            'isPrepaEditable' => $isPrepaEditable,
                            'stockManagement' => $articleRef->getStockManagement()
                        ])
                    ];
                }
            }

            /** @var PreparationOrderArticleLine $articleLine */
            foreach ($preparation->getArticleLines() as $articleLine) {
                $article = $articleLine->getArticle();
                if ($articleLine->getPickedQuantity() > 0 ||
                    ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                    $rows[] = [
                        "reference" => ($article->getArticleFournisseur() && $article->getArticleFournisseur()->getReferenceArticle()) ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        "label" => $article->getLabel() ?? '',
                        "location" => FormatHelper::location($article->getEmplacement()),
                        "targetLocationPicking" => FormatHelper::location($articleLine->getTargetLocationPicking()),
                        "quantity" => $article->getQuantite() ?? '',
                        "quantityToPick" => $articleLine->getQuantityToPick() ?? ' ',
                        "pickedQuantity" => $articleLine->getPickedQuantity() ?? ' ',
                        'active' => !empty($articleLine->getPickedQuantity()),
                        "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                            'barcode' => $article->getBarCode(),
                            'artOrRefId' => $article->getId(),
                            'isRef' => false,
                            'isRefByArt' => false,
                            'quantity' => $articleLine->getPickedQuantity(),
                            'id' => $articleLine->getId(),
                            'isPrepaEditable' => $isPrepaEditable,
                            'stockManagement' => $article->getArticleFournisseur()->getReferenceArticle()->getStockManagement()
                        ])
                    ];
                }
            }

            $data['data'] = $rows;
        } else {
            $data = false; //TODO gérer affichage erreur
        }
        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="preparation_show", methods="GET|POST")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_PREPA})
     */
    public function show(Preparation            $preparation,
                         EntityManagerInterface $entityManager): Response
    {
        $sensorWrappers = $entityManager->getRepository(SensorWrapper::class)->findWithNoActiveAssociation();
        $sensorWrappers = Stream::from($sensorWrappers)
            ->filter(fn (SensorWrapper $wrapper) => (
                Stream::from($wrapper->getPairings())
                    ->every(fn (Pairing $pairing) => !$pairing->isActive())
            ));

        $preparationStatus = $preparation->getStatut() ? $preparation->getStatut()->getNom() : null;

        $demande = $preparation->getDemande();
        $destination = $demande?->getDestination();
        $operator = $preparation?->getUtilisateur();
        $comment = $preparation->getCommentaire();

        $status = $preparation->isPlanned()
            ? (
                $preparation->getStatut()->getCode() === Preparation::STATUT_A_TRAITER
                    ? Preparation::STATUT_LAUNCHED
                    : (
                        $preparation->getStatut()->getCode() === Preparation::STATUT_PREPARE
                            ? 'traité'
                            : $preparation->getStatut()->getCode()
                    )
            )
            : $preparation->getStatut()->getCode();

        return $this->render('preparation/show.html.twig', [
            "sensorWrappers" => $sensorWrappers,
            'demande' => $demande,
            'showTargetLocationPicking' => $entityManager->getRepository(Setting::class)->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION),
            'livraison' => $preparation->getLivraison(),
            'preparation' => $preparation,
            'isPrepaEditable' => $preparationStatus === Preparation::STATUT_A_TRAITER
                || $preparationStatus === Preparation::STATUT_VALIDATED
                || ($preparationStatus == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser()),
            'headerConfig' => [
                ['label' => 'Numéro', 'value' => $preparation->getNumero()],
                ['label' => 'Statut', 'value' => ucfirst($status)],
                ['label' => 'Point de livraison', 'value' => $destination ? $destination->getLabel() : ''],
                ['label' => 'Opérateur', 'value' => $operator ? $operator->getUsername() : ''],
                ['label' => 'Demandeur', 'value' => FormatHelper::deliveryRequester($demande)],
                ...($demande->getExpectedAt() ? [['label' => 'Date attendue', 'value' => FormatHelper::date($demande->getExpectedAt())]] : []),
                ...($preparation->getExpectedAt() ? [['label' => 'Date de préparation', 'value' => FormatHelper::date($preparation->getExpectedAt())]] : []),
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ],
            ]
        ]);
    }

    /**
     * @Route("/supprimer/{preparation}", name="preparation_delete", methods="GET|POST")
     * @HasPermission({Menu::ORDRE, Action::DELETE})
     */
    public function delete(Preparation                $preparation,
                           EntityManagerInterface     $entityManager,
                           PreparationsManagerService $preparationsManagerService,
                           RefArticleDataService      $refArticleDataService): Response
    {

        $refToUpdate = $preparationsManagerService->managePreRemovePreparation($preparation, $entityManager);
        $entityManager->flush();

        $entityManager->remove($preparation);

        // il faut que la preparation soit supprimée avant une maj des articles
        $entityManager->flush();

        if (!$preparation->isPlanned()) {
            foreach ($refToUpdate as $reference) {
                $refArticleDataService->updateRefArticleQuantities($entityManager, $reference);
            }
        }

        $entityManager->flush();

        return $this->redirectToRoute('preparation_index');
    }

    #[Route('/modifier', name: "preparation_edit", options: ['expose' => true], methods: 'POST')]
    #[HasPermission([Menu::ORDRE, Action::EDIT_PREPARATION_DATE])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $preparation = $entityManager->find(Preparation::class, $request->request->get('id'));

        $preparation->setExpectedAt(DateTime::createFromFormat("Y-m-d", $request->request->get('expectedAt')));

        $entityManager->flush();

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Route("/commencer-scission", name="start_splitting", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function startSplitting(EntityManagerInterface $entityManager,
                                   Request                $request): Response
    {
        if ($ligneArticleId = json_decode($request->getContent(), true)) {
            $ligneArticlePreparationRepository = $entityManager->getRepository(PreparationOrderReferenceLine::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $settingRepository = $entityManager->getRepository(Setting::class);

            $ligneArticle = $ligneArticlePreparationRepository->find($ligneArticleId);

            $refArticle = $ligneArticle->getReference();
            $preparation = $ligneArticle->getPreparation();

            $pickedQuantitiesByArticle = Stream::from($preparation->getArticleLines())
                ->keymap(fn(PreparationOrderArticleLine $articleLine) => [
                    $articleLine->getArticle()->getId(),
                    $articleLine->getPickedQuantity()
                ])
                ->toArray();


            $displayTargetPickingLocation = $settingRepository->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION);
            $targetLocationPicking = $ligneArticle->getTargetLocationPicking();
            $management = $refArticle->getStockManagement();

            $articles = $articleRepository->findActiveArticles(
                $refArticle,
                $displayTargetPickingLocation ? $targetLocationPicking : null,
                $management === ReferenceArticle::STOCK_MANAGEMENT_FEFO ? 'expiryDate' : 'stockEntryDate',
                Criteria::ASC
            );

            $response = $this->renderView('preparation/modalSplitting.html.twig', [
                'reference' => $refArticle->getReference(),
                'referenceId' => $refArticle->getId(),
                'articles' => $articles,
                'pickedQuantities' => $pickedQuantitiesByArticle,
                'quantite' => $ligneArticle->getQuantityToPick(),
                'preparation' => $preparation,
                'demande' => $preparation->getDemande(),
                'managementType' => $refArticle->getStockManagement(),
                'displayTargetLocationPicking' => $displayTargetPickingLocation
            ]);

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/finir-scission", name="submit_splitting", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function submitSplitting(Request                    $request,
                                    PreparationsManagerService $preparationsManagerService,
                                    EntityManagerInterface     $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);

            $statutRepository = $entityManager->getRepository(Statut::class);
            $preparationRepository = $entityManager->getRepository(Preparation::class);
            $ligneArticlePreparationRepository = $entityManager->getRepository(PreparationOrderReferenceLine::class);

            if (!empty($data['articles'])) {
                $preparation = $preparationRepository->find($data['preparation']);

                $articles = [];
                $pickedQuantities = 0;
                foreach ($data['articles'] as $idArticle => $pickedQuantity) {
                    $article = $articleRepository->find($idArticle);
                    if ($pickedQuantity >= 0 && $pickedQuantity <= $article->getQuantite()) {
                        $pickedQuantities += (int)$pickedQuantity;
                        $articles[$idArticle] = $article;
                    } else {
                        return $this->json([
                            'success' => false,
                            'msg' => 'Une des quantités saisies est invalide'
                        ]);
                    }
                }

                $articleFirst = $articleRepository->find(array_key_first($data['articles']));
                $refArticle = $articleFirst->getArticleFournisseur()->getReferenceArticle();

                /** @var PreparationOrderReferenceLine $ligneArticle */
                $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation);

                if ($pickedQuantities > $ligneArticle->getQuantityToPick()) {
                    return $this->json([
                        'success' => false,
                        'msg' => 'Vous avez trop sélectionné d\'article.'
                    ]);
                }

                $inTransitStatus = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT);
                foreach ($articles as $idArticle => $article) {
                    $articleFournisseur = $article->getArticleFournisseur();
                    if ($articleFournisseur) {
                        $referenceArticle = $articleFournisseur->getReferenceArticle();
                        if ($referenceArticle && $referenceArticle->getId() === $ligneArticle->getReference()->getId()) {
                            $pickedQuantity = $data['articles'][$idArticle];
                            $preparationsManagerService->treatArticleSplitting(
                                $entityManager,
                                $article,
                                $pickedQuantity,
                                $ligneArticle,
                                $inTransitStatus
                            );
                        }
                    }
                }
                $preparationsManagerService->deleteLigneRefOrNot($ligneArticle, $preparation, $entityManager);
                $entityManager->flush();
                $resp = ['success' => true];
            } else {
                $resp = [
                    'success' => false,
                    'msg' => 'Vous devez sélectionner au moins un article.'
                ];
            }
            return new JsonResponse($resp);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-article", name="prepa_edit_ligne_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editLigneArticle(Request                $request,
                                     EntityManagerInterface $entityManager): Response
    {
        $preparationOrderArticleLineRepository = $entityManager->getRepository(PreparationOrderArticleLine::class);
        $preparationOrderReferenceLineRepository = $entityManager->getRepository(PreparationOrderReferenceLine::class);

        if ($data = json_decode($request->getContent(), true)) {

            /** @var PreparationOrderArticleLine|PreparationOrderReferenceLine $line */
            $line = $data['isRef']
                ? $preparationOrderReferenceLineRepository->find($data['ligneArticle'])
                : $preparationOrderArticleLineRepository->find($data['ligneArticle']);

            if ($line instanceof PreparationOrderArticleLine) {
                $article = $line->getArticle();
                $ligneRef = $preparationOrderReferenceLineRepository->findOneByRefArticleAndDemande(
                    $article->getArticleFournisseur()->getReferenceArticle(),
                    $line->getPreparation()
                );

                if (isset($ligneRef)) {
                    $ligneRef->setQuantityToPick($ligneRef->getQuantityToPick() + ($line->getPickedQuantity() - intval($data['quantite'])));
                }
            }
            // protection contre quantités négatives
            if (isset($data['quantite'])) {
                $line->setPickedQuantity(max($data['quantite'], 0));
            }
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-article-api", name="prepa_edit_api", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEditLigneArticle(Request                $request,
                                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceLineRepository = $entityManager->getRepository(PreparationOrderReferenceLine::class);
            $articleLineRepository = $entityManager->getRepository(PreparationOrderArticleLine::class);

            $repository = $data['ref']
                ? $referenceLineRepository
                : $articleLineRepository;

            /** @var PreparationOrderReferenceLine|PreparationOrderArticleLine $line */
            $line = $repository->find($data['id']);
            $quantity = $line->getQuantityToPick();

            $json = $this->renderView(
                'preparation/modalEditLigneArticleContent.html.twig',
                [
                    'isRef' => $data['ref'],
                    'quantity' => $quantity,
                    'max' => $data['ref']
                        ? $quantity
                        : ($line instanceof PreparationOrderArticleLine ? $line->getQuantityToPick() : null)
                ]
            );

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/commencer-preparation", name="prepa_begin", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     */
    public function beginPrepa(EntityManagerInterface $entityManager,
                               Request                $request): Response
    {
        if ($prepaId = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);
            $preparationRepository = $entityManager->getRepository(Preparation::class);

            $preparation = $preparationRepository->find($prepaId);

            if ($preparation) {
                $statusInProgress = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
                $preparation
                    ->setStatut($statusInProgress)
                    ->setUtilisateur($this->getUser());
                $entityManager->flush();
            }

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_preparations_csv", options={"expose"=true}, methods={"GET"})
     */
    public function getPreparationCSV(Request                    $request,
                                      PreparationsManagerService $preparationsManager,
                                      CSVExportService           $CSVExportService,
                                      EntityManagerInterface     $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $preparationRepository = $entityManager->getRepository(Preparation::class);
            $preparationIterator = $preparationRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            $csvHeader = [
                'numéro',
                'statut',
                'date création',
                'opérateur',
                'type',
                'référence',
                'libellé',
                'emplacement',
                'quantité à préparer',
                'code-barre'
            ];
            $nowStr = new DateTime('now');

            return $CSVExportService->streamResponse(
                function ($output) use ($preparationIterator, $CSVExportService, $preparationsManager) {
                    foreach ($preparationIterator as $preparation) {
                        $preparationsManager->putPreparationLines($output, $preparation);
                    }
                },
                "Export-Ordre-Preparation-" . $nowStr->format('d_m_Y') . ".csv",
                $csvHeader
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/{preparation}/check-etiquette", name="count_bar_codes", options={"expose"=true})
     */
    public function countBarcode(Preparation $preparation): Response
    {
        return $this->json(
            !$preparation->getArticleLines()->isEmpty()
            || !$preparation->getReferenceLines()->isEmpty()
        );
    }

    /**
     * @Route("/{preparation}/etiquettes", name="preparation_bar_codes_print", options={"expose"=true})
     */
    public function getBarCodes(Preparation           $preparation,
                                RefArticleDataService $refArticleDataService,
                                ArticleDataService    $articleDataService,
                                PDFGeneratorService   $PDFGeneratorService): ?Response
    {
        $articles = $preparation->getArticleLines()->toArray();
        $lignesArticle = $preparation->getReferenceLines()->toArray();
        $referenceArticles = [];

        /** @var PreparationOrderReferenceLine $ligne */
        foreach ($lignesArticle as $ligne) {
            $reference = $ligne->getReference();
            if ($reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                $referenceArticles[] = $reference;
            }
        }
        $barcodeConfigs = array_merge(
            array_map(
                function (PreparationOrderArticleLine $article) use ($articleDataService) {
                    return $articleDataService->getBarcodeConfig($article->getArticle());
                },
                $articles
            ),
            array_map(
                function (ReferenceArticle $referenceArticle) use ($refArticleDataService) {
                    return $refArticleDataService->getBarcodeConfig($referenceArticle);
                },
                $referenceArticles
            )
        );

        $barcodeCounter = count($barcodeConfigs);

        if ($barcodeCounter > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barcodeConfigs,
                'preparation'
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
                $fileName
            );
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/associer", name="preparation_sensor_pairing_new",options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::PAIR_SENSOR}, mode=HasPermission::IN_JSON)
     */
    function newPreparationPairingSensor(PreparationsManagerService $preparationsService,
                                         EntityManagerInterface     $entityManager,
                                         Request                    $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            if (!$data['sensorWrapper'] && !$data['sensor']) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Un capteur/code capteur est obligatoire pour valider l\'association'
                ]);
            }

            $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->findOneBy(["id" => $data['sensorWrapper'], 'deleted' => false]);
            $preparation = $entityManager->getRepository(Preparation::class)->find($data['orderID']);

            $pairingPreparation = $preparationsService->createPairing($sensorWrapper, $preparation);
            $entityManager->persist($pairingPreparation);

            try {
                $entityManager->flush();
            } /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Une autre association est en cours de création, veuillez réessayer.'
                ]);
            }

            $number = $sensorWrapper->getName();
            return $this->json([
                'success' => true,
                'msg' => "L'assocation avec le capteur <strong>${number}</strong> a bien été créée"
            ]);
        }

        throw new BadRequestHttpException();
    }

    #[Route('/planning', name: 'preparation_planning_index', methods: 'GET')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_PREPA_PLANNING], mode: HasPermission::IN_JSON)]
    public function planning(EntityManagerInterface $entityManager): Response
    {
        $typeRepository = $entityManager->getRepository(Type::class);
        return $this->render('preparation/planning.html.twig', [
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
        ]);
    }

    #[Route('/lancement-preparation/recuperer-prepa', name: 'planning_preparation_launching_filter', options: ['expose' => true], methods: 'POST')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_PREPA_PLANNING], mode: HasPermission::IN_JSON)]
    public function getPrepasForModal(EntityManagerInterface $manager, Request $request)
    {
        $preparationRepository = $manager->getRepository(Preparation::class);
        $from = DateTime::createFromFormat("Y-m-d", $request->query->get("from"));
        $to = DateTime::createFromFormat("Y-m-d", $request->query->get("to"));

        return $this->json([
            "success" => true,
            "template" => $this->renderView('preparation/preparationsContainerContent.html.twig', [
                "preparations" => $preparationRepository->findByStatusCodesAndExpectedAt([], [Preparation::STATUT_VALIDATED], $from, $to),
            ]),
        ]);
    }

    #[Route('/planning/api', name: 'preparation_planning_api', options: ['expose' => true], methods: 'GET')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_PREPA_PLANNING], mode: HasPermission::IN_JSON)]
    public function planningApi(EntityManagerInterface $entityManager,
                                Request                $request): Response
    {
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $nbDaysOnPlanning = 5;
        $planningStart = FormatHelper::parseDatetime($request->query->get('date'));
        $planningEnd = (clone $planningStart)->modify("+{$nbDaysOnPlanning} days");

        $filters = $entityManager->getRepository(FiltreSup::class)->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PREPARATION_PLANNING, $this->getUser());
        $preparationStatusesForFilters = [
            "planning-status-validated" => Preparation::STATUT_VALIDATED,
            "planning-status-launched" => Preparation::STATUT_A_TRAITER,
            "planning-status-ongoing" => Preparation::STATUT_EN_COURS_DE_PREPARATION,
            "planning-status-partial" => Preparation::STATUT_INCOMPLETE,
            "planning-status-done" => Preparation::STATUT_PREPARE,
        ];

        $filterStatuses = Stream::from($filters)
            ->filterMap(fn(array $filter) => (
                str_starts_with($filter['field'], 'planning-status-')
                    ? $preparationStatusesForFilters[$filter['field']]
                    : null
            ))
            ->toArray();
        $filterStatuses = $filterStatuses ?: array_values($preparationStatusesForFilters);

        $filters = Stream::from($filters)
            ->filter(fn(array $filter) => (
                $filter['field'] === FiltreSup::FIELD_REQUEST_NUMBER
                || $filter['field'] === FiltreSup::FIELD_TYPE
                || $filter['field'] === FiltreSup::FIELD_OPERATORS
            ))
            ->toArray();

        $preparations = $preparationRepository->findByStatusCodesAndExpectedAt($filters, $filterStatuses, $planningStart, $planningEnd);
        $cards = Stream::from($preparations)
            ->filter(fn(Preparation $preparation) => $preparation->getExpectedAt())
            ->keymap(fn(Preparation $preparation) => [
                $preparation->getExpectedAt()->format('Y-m-d'),
                $this->renderView('preparation/planning_card.html.twig', [
                    'preparation' => $preparation,
                    'color' => match ($preparation->getStatut()?->getCode()) {
                        Preparation::STATUT_VALIDATED => 'orange-card',
                        Preparation::STATUT_A_TRAITER => 'green-card',
                        Preparation::STATUT_EN_COURS_DE_PREPARATION => 'blue-card',
                        // Preparation::STATUT_INCOMPLETE, Preparation::STATUT_A_TRAITER => 'grey-card',
                        default => 'grey-card',
                    }
                ])
            ], true)
            ->toArray();

        $dates = Stream::fill(0, $nbDaysOnPlanning, null)
            ->map(function ($_, int $index) use ($planningStart, $preparations, $cards) {
                $day = (clone $planningStart)->modify("+{$index} days");
                $dayStr = $day->format('Y-m-d');
                $count = count($cards[$dayStr] ?? []);
                $sPreparation = $count > 1 ? 's' : '';
                return [
                    "label" => FormatHelper::longDate($day, ["short" => true, "year" => false]),
                    "cardSelector" => $dayStr,
                    "columnClass" => $index <= 1 ? "planning-col-2" : "",
                    "columnHint" => "<span class='font-weight-bold'>{$count} préparation{$sPreparation}</span>",
                ];
            })
            ->toArray();

        return $this->json([
            'success' => true,
            'template' => $this->renderView('preparation/planning_content.html.twig', [
                'planningDates' => $dates,
                'cards' => $cards,
            ])
        ]);
    }

    #[Route('/modifier-date-preparation/{preparation}/{date}', name: 'preparation_edit_preparation_date', options: ['expose' => true], methods: 'PUT')]
    public function editPreparationDate(Preparation            $preparation,
                                        string                 $date,
                                        EntityManagerInterface $manager): Response
    {
        $preparation->setExpectedAt(new DateTime($date));
        $manager->flush();
        return $this->json([
            'success' => true,
        ]);
    }


    #[Route('/lancement-preparations/check-preparation-stock', name: 'planning_preparation_launch_check_stock', options: ['expose' => true], methods: 'POST')]
    #[HasPermission([Menu::ORDRE, Action::DISPLAY_PREPA_PLANNING], mode: HasPermission::IN_JSON)]
    public function checkStock(Request                $request,
                               EntityManagerInterface $manager,
                               MailerService          $mailerService,
                               RefArticleDataService  $refArticleDataService,
                               NotificationService    $notificationService): JsonResponse {
        $data = json_decode($request->getContent());

        $preparationRepository = $manager->getRepository(Preparation::class);
        $statutRepository = $manager->getRepository(Statut::class);

        $launchPreparation = $request->query->get('launchPreparations');

        $preparationsToLaunch = $preparationRepository->findBy(['id' => $data]);
        $toTreatStatut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::PREPARATION, Preparation::STATUT_A_TRAITER);
        $checkQuantity = [];
        $quantityErrorPreparationId = [];
        $unavailableRefForTemplate = [];

        foreach ($preparationsToLaunch as $preparationToLaunch) {
            $refLines = $preparationToLaunch->getReferenceLines();

            foreach ($refLines as $refLine) {
                if (!isset($checkQuantity[$refLine->getReference()->getReference()]['quantityToPick'])) {
                    $checkQuantity[$refLine->getReference()->getReference()]['quantityToPick'] = 0;
                }
                $checkQuantity[$refLine->getReference()->getReference()]['quantityToPick'] += $refLine->getQuantityToPick() ?: 0;
                $checkQuantity[$refLine->getReference()->getReference()]['availableQuantity'] = $refLine->getReference()->getQuantiteDisponible();
            }

            foreach ($refLines as $refLine) {
                if ($checkQuantity[$refLine->getReference()->getReference()]['quantityToPick'] > $checkQuantity[$refLine->getReference()->getReference()]['availableQuantity']) {
                    if (!in_array($preparationToLaunch, $quantityErrorPreparationId)) {
                        $quantityErrorPreparationId[] = $preparationToLaunch;
                    }
                }
            }
        }

        foreach ($quantityErrorPreparationId as $preparation) {
            $refLines = $preparation->getReferenceLines();

            foreach ($refLines as $refLine) {
                if (!isset($unavailableRefForTemplate[$preparation->getNumero()][$refLine->getReference()->getReference()])) {
                    $unavailableRefForTemplate[$preparation->getNumero()][$refLine->getReference()->getReference()] = [
                        "reference" => $refLine->getReference(),
                        "quantityToPick" => $checkQuantity[$refLine->getReference()->getReference()]['quantityToPick'],
                        "availableQuantity" => $checkQuantity[$refLine->getReference()->getReference()]['availableQuantity']
                    ];
                }
            }
        }

        if (empty($quantityErrorPreparationId) && $launchPreparation === "1") {
            foreach ($preparationsToLaunch as $preparation) {
                $preparation->setStatut($toTreatStatut);
                $demande = $preparation->getDemande();
                $refLines = $preparation->getReferenceLines();
                if ($demande->getType()->isNotificationsEnabled()) {
                    $notificationService->toTreat($preparation);
                }
                if ($demande->getType()->getSendMail()) {
                    $nowDate = new DateTime('now');
                    $mailerService->sendMail(
                        'FOLLOW GT // Validation d\'une demande vous concernant',
                        $this->renderView('mails/contents/mailDemandeLivraisonValidate.html.twig', [
                            'demande' => $demande,
                            'title' => 'Votre demande de livraison ' . $demande->getNumero() . ' de type '
                                . $demande->getType()->getLabel()
                                . ' a bien été validée le '
                                . $nowDate->format('d/m/Y \à H:i')
                                . '.',
                        ]),
                        $demande->getUtilisateur()
                    );
                }
                foreach ($refLines as $refLine) {
                    $referenceArticle = $refLine->getReference();
                    if ($referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                        $referenceArticle->setQuantiteReservee(($referenceArticle->getQuantiteReservee() ?? 0) + $refLine->getQuantityToPick());
                    } else {
                        $refArticleDataService->updateRefArticleQuantities($manager, $referenceArticle);
                    }
                }
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "unavailablePreparationsId" => Stream::from($quantityErrorPreparationId)
                ->map(fn(Preparation $preparation) => $preparation->getId())
                ->toArray(),
            "template" => $this->renderView('preparation/quantityErrorTemplate.html.twig', [
                "preparations" => $unavailableRefForTemplate
            ]),
        ]);
    }

}
