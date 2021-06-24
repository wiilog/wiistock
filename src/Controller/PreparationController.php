<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\SensorWrapper;
use App\Entity\LigneArticlePreparation;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Exceptions\NegativeQuantityException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\LivraisonsManagerService;
use App\Service\PDFGeneratorService;
use App\Service\PreparationsManagerService;
use App\Service\RefArticleDataService;
use App\Service\SpecificService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ArticleDataService;
use App\Entity\Demande;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use WiiCommon\Helper\Stream;

/**
 * @Route("/preparation")
 */
class PreparationController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var SpecificService
     */
    private $specificService;

    /**
     * @var PreparationsManagerService
     */
    private $preparationsManagerService;

    public function __construct(PreparationsManagerService $preparationsManagerService,
                                SpecificService $specificService,
                                ArticleDataService $articleDataService,
                                UserService $userService)
    {
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->specificService = $specificService;
        $this->preparationsManagerService = $preparationsManagerService;
    }


    /**
     * @Route("/finir/{idPrepa}", name="preparation_finish", methods={"POST"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finishPrepa($idPrepa,
                                Request $request,
                                EntityManagerInterface $entityManager,
                                LivraisonsManagerService $livraisonsManager,
                                PreparationsManagerService $preparationsManager)
    {

        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $preparation = $preparationRepository->find($idPrepa);
        $locationEndPrepa = $emplacementRepository->find($request->request->get('emplacement'));

        try {
            $articlesNotPicked = $preparationsManager->createMouvementsPrepaAndSplit($preparation, $this->getUser(), $entityManager);
        }
        catch(NegativeQuantityException $exception) {
            $barcode = $exception->getArticle()->getBarCode();
            return new JsonResponse([
                'success' => false,
                'message' => "La quantité en stock de l'article $barcode est inférieure à la quantité prélevée."
            ]);
        }

        $dateEnd = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $livraison = $livraisonsManager->createLivraison($dateEnd, $preparation);
        $entityManager->persist($livraison);
        $preparationsManager->treatPreparation($preparation, $this->getUser(), $locationEndPrepa, $articlesNotPicked);
        $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $locationEndPrepa);

        $mouvementRepository = $entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementRepository->findByPreparation($preparation);
        $entityManager->flush();

        foreach ($mouvements as $mouvement) {
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

        $entityManager->flush();

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
                          string $demandId = null): Response
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
    public function api(Request $request): Response
    {
        $filterDemand = $request->request->get('filterDemand');
        $data = $this->preparationsManagerService->getDataForDatatable($request->request, $filterDemand);

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
        $isPrepaEditable = $preparationStatut === Preparation::STATUT_A_TRAITER || ($preparationStatut == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser());

        if (isset($demande)) {
            $rows = [];
            foreach ($preparation->getLigneArticlePreparations() as $ligneArticle) {
                $articleRef = $ligneArticle->getReference();
                $isRefByArt = $articleRef->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE;
                if ($ligneArticle->getQuantitePrelevee() > 0 ||
                    ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                    $qttForCurrentLine = $ligneArticle->getQuantite() ?? null;
                    $rows[] = [
                        "Référence" => $articleRef ? $articleRef->getReference() : ' ',
                        "Libellé" => $articleRef ? $articleRef->getLibelle() : ' ',
                        "Emplacement" => $articleRef ? ($articleRef->getEmplacement() ? $articleRef->getEmplacement()->getLabel() : '') : '',
                        "Quantité" => $articleRef->getQuantiteStock(),
                        "Quantité à prélever" => $qttForCurrentLine,
                        "Quantité prélevée" => $ligneArticle->getQuantitePrelevee() ? $ligneArticle->getQuantitePrelevee() : ' ',
                        'active' => !empty($ligneArticle->getQuantitePrelevee()),
                        "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                            'barcode' => $articleRef->getBarCode(),
                            'isRef' => true,
                            'artOrRefId' => $articleRef->getId(),
                            'isRefByArt' => $isRefByArt,
                            'id' => $ligneArticle->getId(),
                            'isPrepaEditable' => $isPrepaEditable,
                            'stockManagement' => $articleRef->getStockManagement()
                        ])
                    ];
                }
            }

            foreach ($preparation->getArticles() as $article) {
                if ($article->getQuantite() > 0 ||
                    ($preparationStatut !== Preparation::STATUT_PREPARE && $preparationStatut !== Preparation::STATUT_INCOMPLETE)) {
                    if (empty($article->getQuantiteAPrelever())) {
                        $article->setQuantiteAPrelever($article->getQuantite());
                        $this->getDoctrine()->getManager()->flush();
                    }
                    $rows[] = [
                        "Référence" => ($article->getArticleFournisseur() && $article->getArticleFournisseur()->getReferenceArticle()) ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                        "Libellé" => $article->getLabel() ?? '',
                        "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                        "Quantité" => $article->getQuantite() ?? '',
                        "Quantité à prélever" => $article->getQuantiteAPrelever() ?? '',
                        "Quantité prélevée" => $article->getQuantitePrelevee() ?? ' ',
                        'active' => !empty($article->getQuantitePrelevee()),
                        "Actions" => $this->renderView('preparation/datatablePreparationListeRow.html.twig', [
                            'barcode' => $article->getBarCode(),
                            'artOrRefId' => $article->getId(),
                            'isRef' => false,
                            'isRefByArt' => false,
                            'quantity' => $article->getQuantiteAPrelever(),
                            'id' => $article->getId(),
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
    public function show(Preparation $preparation,
                         EntityManagerInterface $entityManager): Response
    {
        $sensorWrappers = $entityManager->getRepository(SensorWrapper::class)->findWithNoActiveAssociation();
        $sensorWrappers = Stream::from($sensorWrappers)
            ->filter(function(SensorWrapper $wrapper) {
                return $wrapper->getPairings()->filter(function(Pairing $pairing) {
                    return $pairing->isActive();
                })->isEmpty();
            });
        $articleRepository = $entityManager->getRepository(Article::class);

        $preparationStatus = $preparation->getStatut() ? $preparation->getStatut()->getNom() : null;

        $demande = $preparation->getDemande();
        $destination = $demande ? $demande->getDestination() : null;
        $operator = $preparation ? $preparation->getUtilisateur() : null;
        $comment = $preparation->getCommentaire();

        return $this->render('preparation/show.html.twig', [
            "sensorWrappers" => $sensorWrappers,
            'demande' => $demande,
            'livraison' => $preparation->getLivraison(),
            'preparation' => $preparation,
            'isPrepaEditable' => $preparationStatus === Preparation::STATUT_A_TRAITER || ($preparationStatus == Preparation::STATUT_EN_COURS_DE_PREPARATION && $preparation->getUtilisateur() == $this->getUser()),
            'articles' => $articleRepository->getIdRefLabelAndQuantity(),
            'headerConfig' => [
                ['label' => 'Numéro', 'value' => $preparation->getNumero()],
                ['label' => 'Statut', 'value' => $preparation->getStatut() ? ucfirst($preparation->getStatut()->getNom()) : ''],
                ['label' => 'Point de livraison', 'value' => $destination ? $destination->getLabel() : ''],
                ['label' => 'Opérateur', 'value' => $operator ? $operator->getUsername() : ''],
                ['label' => 'Demandeur', 'value' => FormatHelper::deliveryRequester($demande)],
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
     * @Route("/supprimer/{id}", name="preparation_delete", methods="GET|POST")
     * @HasPermission({Menu::ORDRE, Action::DELETE})
     */
    public function delete(Preparation $preparation,
                           EntityManagerInterface $entityManager,
                           PreparationsManagerService $preparationsManagerService,
                           RefArticleDataService $refArticleDataService): Response
    {

        $refToUpdate = $preparationsManagerService->managePreRemovePreparation($preparation, $entityManager);
        $entityManager->flush();

        $entityManager->remove($preparation);

        // il faut que la preparation soit supprimée avant une maj des articles
        $entityManager->flush();

        foreach ($refToUpdate as $reference) {
            $refArticleDataService->updateRefArticleQuantities($entityManager, $reference);
        }

        $entityManager->flush();

        return $this->redirectToRoute('preparation_index');
    }

    /**
     * @Route("/commencer-scission", name="start_splitting", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function startSplitting(EntityManagerInterface $entityManager,
                                   Request $request): Response
    {
        if ($ligneArticleId = json_decode($request->getContent(), true)) {
            $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);
            $articleRepository = $entityManager->getRepository(Article::class);

            $ligneArticle = $ligneArticlePreparationRepository->find($ligneArticleId);

            $refArticle = $ligneArticle->getReference();
            $preparation = $ligneArticle->getPreparation();
            $articles = $articleRepository->findActifByRefArticleWithoutDemand($refArticle, $preparation, $preparation->getDemande());
            $response = $this->renderView('preparation/modalSplitting.html.twig', [
                'reference' => $refArticle->getReference(),
                'referenceId' => $refArticle->getId(),
                'articles' => $articles,
                'quantite' => $ligneArticle->getQuantite(),
                'preparation' => $preparation,
                'demande' => $preparation->getDemande(),
                'managementType' => $refArticle->getStockManagement()
            ]);

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/finir-scission", name="submit_splitting", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function submitSplitting(Request $request,
                                    EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);

            $statutRepository = $entityManager->getRepository(Statut::class);
            $preparationRepository = $entityManager->getRepository(Preparation::class);
            $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);

            if (!empty($data['articles'])) {
                $preparation = $preparationRepository->find($data['preparation']);

                $articles = [];
                $pickedQuantities = 0;
                foreach ($data['articles'] as $idArticle => $pickedQuantity) {
                    $article = $articleRepository->find($idArticle);
                    if ($pickedQuantity >= 0 && $pickedQuantity <= $article->getQuantite()) {
                        $pickedQuantities += (int) $pickedQuantity;
                        $articles[$idArticle] = $article;
                    }
                    else {
                        return $this->json([
                            'success' => false,
                            'msg' => 'Une des quantités saisies est invalide'
                        ]);
                    }
                }

                $articleFirst = $articleRepository->find(array_key_first($data['articles']));
                $refArticle = $articleFirst->getArticleFournisseur()->getReferenceArticle();

                /** @var LigneArticlePreparation $ligneArticle */
                $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation);

                if ($pickedQuantities > $ligneArticle->getQuantite()) {
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
                            $this->preparationsManagerService->treatArticleSplitting($article, $pickedQuantity, $ligneArticle, $inTransitStatus);
                        }
                    }
                }
                $this->preparationsManagerService->deleteLigneRefOrNot($ligneArticle);
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
    public function editLigneArticle(Request $request,
                                     EntityManagerInterface $entityManager): Response
    {
        $articleRepository = $entityManager->getRepository(Article::class);
        $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);

        if ($data = json_decode($request->getContent(), true)) {
            if ($data['isRef']) {
                $ligneArticle = $ligneArticlePreparationRepository->find($data['ligneArticle']);
            } else {
                $ligneArticle = $articleRepository->find($data['ligneArticle']);
            }

            if ($ligneArticle instanceof Article) {
                $ligneRef = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($ligneArticle->getArticleFournisseur()->getReferenceArticle(), $ligneArticle->getPreparation());

                if (isset($ligneRef)) {
                    $ligneRef->setQuantite($ligneRef->getQuantite() + ($ligneArticle->getQuantitePrelevee() - intval($data['quantite'])));
                }
            }
            // protection contre quantités négatives
            if (isset($data['quantite'])) {
                $ligneArticle->setQuantitePrelevee(max($data['quantite'], 0));
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
    public function apiEditLigneArticle(Request $request,
                                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);

            if ($data['ref']) {
                $ligneArticle = $ligneArticlePreparationRepository->find($data['id']);
                $quantity = $ligneArticle->getQuantite();
            } else {
                $article = $articleRepository->find($data['id']);
                $quantity = $article->getQuantitePrelevee();
            }

            $json = $this->renderView(
                'preparation/modalEditLigneArticleContent.html.twig',
                [
                    'isRef' => $data['ref'],
                    'quantity' => $quantity,
                    'max' => $data['ref']
                        ? $quantity
                        : (isset($article) ? $article->getQuantiteAPrelever() : null)
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
                               Request $request): Response
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
                $this->getDoctrine()->getManager()->flush();
            }

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_preparations_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param PreparationsManagerService $preparationsManager
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function getPreparationCSV(Request $request,
                                      PreparationsManagerService $preparationsManager,
                                      CSVExportService $CSVExportService,
                                      EntityManagerInterface $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {
        }

        if(isset($dateTimeMin) && isset($dateTimeMax)) {
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
                'quantité à collecter',
                'code-barre'
            ];
            $nowStr = new DateTime('now', new \DateTimeZone('Europe/Paris'));

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
     * @Route("/{preparation}/etiquettes", name="preparation_bar_codes_print", options={"expose"=true})
     *
     * @param Preparation $preparation
     * @param RefArticleDataService $refArticleDataService
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getBarCodes(Preparation $preparation,
                                RefArticleDataService $refArticleDataService,
                                ArticleDataService $articleDataService,
                                PDFGeneratorService $PDFGeneratorService): Response
    {
        $articles = $preparation->getArticles()->toArray();
        $lignesArticle = $preparation->getLigneArticlePreparations()->toArray();
        $referenceArticles = [];

        /** @var LigneArticlePreparation $ligne */
        foreach ($lignesArticle as $ligne) {
            $reference = $ligne->getReference();
            if ($reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticles[] = $reference;
            }
        }
        $barcodeConfigs = array_merge(
            array_map(
                function (Article $article) use ($articleDataService) {
                    return $articleDataService->getBarcodeConfig($article);
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
                                         EntityManagerInterface $entityManager,
                                         Request $request): Response{
        if($data = json_decode($request->getContent(), true)) {
            if(!$data['sensor'] && !$data['sensorCode']) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Un capteur/code capteur est obligatoire pour valider l\'association'
                ]);
            }

            $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->findByNameOrCode($data['sensor'], $data['sensorCode']);
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
}
