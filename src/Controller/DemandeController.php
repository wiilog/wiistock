<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\ParametrageGlobal;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\GlobalParamService;
use App\Service\RefArticleDataService;
use App\Service\DemandeLivraisonService;
use App\Service\FreeFieldService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use App\Helper\FormatHelper;


/**
 * @Route("/demande")
 */
class DemandeController extends AbstractController
{

    /**
     * @Route("/compareStock", name="compare_stock", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function compareStock(Request $request,
                                 DemandeLivraisonService $demandeLivraisonService,
                                 FreeFieldService $champLibreService,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $responseAfterQuantitiesCheck = $demandeLivraisonService->checkDLStockAndValidate(
                $entityManager,
                $data,
                false,
                $champLibreService
            );
            return new JsonResponse($responseAfterQuantitiesCheck);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="demandeLivraison_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request,
                            GlobalParamService $globalParamService,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $globalSettingsRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $demande = $demandeRepository->find($data['id']);

            $listTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);

            $typeChampLibre = [];

            $freeFieldsGroupedByTypes = [];
            foreach ($listTypes as $type) {
                $deliveryRequestFreeFields = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);
                $typeChampLibre[] = [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'champsLibres' => $deliveryRequestFreeFields,
                ];
                $freeFieldsGroupedByTypes[$type->getId()] = $deliveryRequestFreeFields;
            }

            return $this->json($this->renderView('demande/modalEditDemandeContent.html.twig', [
                'demande' => $demande,
                'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
                'typeChampsLibres' => $typeChampLibre,
                'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
                'defaultDeliveryLocations' => $globalParamService->getDefaultDeliveryLocationsByTypeId($entityManager),
                'restrictedLocations' => $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST),
            ]));
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="demande_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         FreeFieldService $champLibreService,
                         DemandeLivraisonService $demandeLivraisonService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);

            $demande = $demandeRepository->find($data['demandeId']);
            if(isset($data['type'])) {
                $type = $typeRepository->find(intval($data['type']));
            } else {
                $type = $demande->getType();
            }

            // vérification des champs Libres obligatoires
            $requiredEdit = true;
            $CLRequired = $champLibreRepository->getByTypeAndRequiredEdit($type);
            foreach ($CLRequired as $CL) {
                if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                    $requiredEdit = false;
                }
            }

            if ($requiredEdit) {
                $utilisateur = $utilisateurRepository->find(intval($data['demandeur']));
                $emplacement = $emplacementRepository->find(intval($data['destination']));
                $demande
                    ->setUtilisateur($utilisateur)
                    ->setDestination($emplacement)
                    ->setFilled(true)
                    ->setType($type)
                    ->setCommentaire($data['commentaire']);
                $em = $this->getDoctrine()->getManager();
                $em->flush();
                $champLibreService->manageFreeFields($demande, $data, $entityManager);
                $em->flush();
                $response = [
                    'success' => true,
                    'entete' => $this->renderView('demande/demande-show-header.html.twig', [
                        'demande' => $demande,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                        'showDetails' => $demandeLivraisonService->createHeaderDetailsConfig($demande)
                    ]),
                ];

            } else {
                $response['success'] = false;
                $response['msg'] = "Tous les champs obligatoires n'ont pas été renseignés.";
            }

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="demande_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        DemandeLivraisonService $demandeLivraisonService,
                        FreeFieldService $champLibreService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $demande = $demandeLivraisonService->newDemande($data, $entityManager, $champLibreService);

            if ($demande instanceof Demande) {
                $entityManager->persist($demande);
                try {
                    $entityManager->flush();
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch (UniqueConstraintViolationException $e) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Une autre demande de livraison est en cours de création, veuillez réessayer.'
                    ]);
                }

                return new JsonResponse([
                    'success' => true,
                    'redirect' => $this->generateUrl('demande_show', ['id' => $demande->getId()]),
                ]);
            }
            else {
                return new JsonResponse($demande);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/liste/{reception}/{filter}", name="demande_index", methods="GET|POST", options={"expose"=true})
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR})
     */
    public function index(EntityManagerInterface $entityManager,
                          GlobalParamService $globalParamService,
                          $reception = null,
                          $filter = null): Response
    {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $globalSettingsRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);

        $typeChampLibre = [];
        foreach ($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }

        return $this->render('demande/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'filterStatus' => $filter,
            'receptionFilter' => $reception,
            'defaultDeliveryLocations' => $globalParamService->getDefaultDeliveryLocationsByTypeId($entityManager),
            'restrictedLocations' => $globalSettingsRepository->getOneParamByLabel(ParametrageGlobal::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST),
        ]);
    }

    /**
     * @Route("/delete", name="demande_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           DemandeLivraisonService $demandeLivraisonService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $demandeRepository = $entityManager->getRepository(Demande::class);

            $demande = $demandeRepository->find($data['demandeId']);
            $preparations = $demande->getPreparations();

            if ($preparations->count() === 0) {
                $demandeLivraisonService->managePreRemoveDeliveryRequest($demande, $entityManager);
                $entityManager->remove($demande);
                $entityManager->flush();
                $data = [
                    'redirect' => $this->generateUrl('demande_index'),
                    'success' => true
                ];
            }
            else {

                $data = [
                    'message' => 'Vous ne pouvez pas supprimer cette demande, vous devez d\'abord supprimer ses ordres.',
                    'success' => false
                ];
            }
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", options={"expose"=true}, name="demande_api", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        DemandeLivraisonService $demandeLivraisonService): Response
    {
        // cas d'un filtre statut depuis page d'accueil
        $filterStatus = $request->request->get('filterStatus');
        $filterReception = $request->request->get('filterReception');
        $data = $demandeLivraisonService->getDataForDatatable($request->request, $filterStatus, $filterReception);

        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="demande_show", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR})
     */
    public function show(EntityManagerInterface $entityManager,
                         DemandeLivraisonService $demandeLivraisonService,
                         Demande $demande): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);

        return $this->render('demande/show.html.twig', [
            'demande' => $demande,
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
            'finished' => ($demande->getStatut()->getNom() === Demande::STATUT_A_TRAITER),
            'showDetails' => $demandeLivraisonService->createHeaderDetailsConfig($demande),
        ]);
    }

    /**
     * @Route("/api/{id}", name="demande_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function articleApi(EntityManagerInterface $entityManager,
                               Demande $demande): Response
    {
        $referenceLines = $demande->getReferenceLines();
        $rowsRC = [];
        foreach ($referenceLines as $line) {
            $rowsRC[] = [
                "Référence" => ($line->getReference()->getReference() ? $line->getReference()->getReference() : ''),
                "Libellé" => ($line->getReference()->getLibelle() ? $line->getReference()->getLibelle() : ''),
                "Emplacement" => ($line->getReference()->getEmplacement() ? $line->getReference()->getEmplacement()->getLabel() : ' '),
                "quantityToPick" => $line->getQuantityToPick() ?? '',
                "barcode" => $line->getReference() ? $line->getReference()->getBarCode() : '',
                "error" => $line->getReference()->getQuantiteDisponible() < $line->getQuantityToPick()
                    && $demande->getStatut()->getCode() === Demande::STATUT_BROUILLON,
                "Actions" => $this->renderView(
                    'demande/datatableLigneArticleRow.html.twig',
                    [
                        'id' => $line->getId(),
                        'name' => (ReferenceArticle::TYPE_QUANTITE_REFERENCE),
                        'refArticleId' => $line->getReference()->getId(),
                        'reference' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                    ]
                )
            ];
        }
        $articleLines = $demande->getArticleLines();
        $rowsCA = [];
        foreach ($articleLines as $line) {
            $article = $line->getArticle();
            $rowsCA[] = [
                "Référence" => ($article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                "Libellé" => ($article->getLabel() ?: ''),
                "Emplacement" => ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : ' '),
                "quantityToPick" => $line->getQuantityToPick() ?: '',
                "barcode" => $article->getBarCode() ?? '',
                "error" => $article->getQuantite() < $line->getQuantityToPick() && $demande->getStatut()->getCode() === Demande::STATUT_BROUILLON,
                "Actions" => $this->renderView(
                    'demande/datatableLigneArticleRow.html.twig',
                    [
                        'id' => $line->getId(),
                        'articleId' => $article->getId(),
                        'name' => (ReferenceArticle::TYPE_QUANTITE_ARTICLE),
                        'reference' => ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                        'modifiable' => ($demande->getStatut()->getNom() === (Demande::STATUT_BROUILLON)),
                    ]
                ),
            ];
        }

        $data['data'] = array_merge($rowsCA, $rowsRC);
        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter-article", name="demande_add_article", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addArticle(Request $request,
                               EntityManagerInterface $entityManager,
                               ArticleDataService $articleDataService,
                               RefArticleDataService $refArticleDataService,
                               FreeFieldService $champLibreService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $referenceArticle = $referenceArticleRepository->find($data['referenceArticle']);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $demande = $demandeRepository->find($data['livraison']);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $resp = $refArticleDataService->addRefToDemand(
                $data,
                $referenceArticle,
                $currentUser,
                false,
                $entityManager,
                $demande,
                $champLibreService
            );
            if ($resp === 'article') {
                $articleDataService->editArticle($data);
                $resp = true;
            }
            $entityManager->flush();
            return new JsonResponse($resp);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/retirer-article", name="demande_remove_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function removeArticle(Request $request,
                                  EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceLineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);
            $articleLineRepository = $entityManager->getRepository(DeliveryRequestArticleLine::class);

            if (array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $line = $referenceLineRepository->find($data[ReferenceArticle::TYPE_QUANTITE_REFERENCE]);
            } elseif (array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $line = $articleLineRepository->find($data[ReferenceArticle::TYPE_QUANTITE_ARTICLE]);
            }

            if (isset($line)) {
                $entityManager->remove($line);
                $entityManager->flush();
            }

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-article", name="demande_article_edit", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editArticle(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceLineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);
            $line = $referenceLineRepository->find($data['ligneArticle']);
            $line->setQuantityToPick(max($data["quantite"], 0)); // protection contre quantités négatives
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier-article", name="demande_article_api_edit", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function articleEditApi(EntityManagerInterface $entityManager,
                                   Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceLineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);

            $referenceLine = $referenceLineRepository->find($data['id']);
            $articleRef = $referenceLine->getReference();

            $maximumQuantity = $articleRef->getQuantiteStock();
            $json = $this->renderView('demande/modalEditArticleContent.html.twig', [
                'line' => $referenceLine,
                'maximum' => $maximumQuantity
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/non-vide", name="demande_livraison_has_articles", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
    public function hasArticles(Request $request,
                                EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $requestRepository = $entityManager->getRepository(Demande::class);
            $request = $requestRepository->find($data['id']);

            $count = $request
                ? ($request->getArticleLines()->count() + $request->getReferenceLines()->count())
                : 0;

            return new JsonResponse($count > 0);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_demandes_csv", options={"expose"=true}, methods={"GET"})
     * @HasPermission({Menu::DEM, Action::EXPORT})
     */
    public function getDemandesCSV(EntityManagerInterface $entityManager,
                                   Request $request,
                                   FreeFieldService $freeFieldService,
                                   CSVExportService $CSVExportService): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $preparationRepository = $entityManager->getRepository(Preparation::class);
            $livraisonRepository = $entityManager->getRepository(Livraison::class);
            $referenceLineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);
            $articleLineRepository = $entityManager->getRepository(DeliveryRequestArticleLine::class);
            $freeFieldsRepository = $entityManager->getRepository(FreeField::class);

            $demandes = $demandeRepository->findByDates($dateTimeMin, $dateTimeMax);
            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_LIVRAISON]);

            // en-têtes champs fixes
            $headers = array_merge(
                [
                    'demandeur',
                    'statut',
                    'destination',
                    'commentaire',
                    'date demande',
                    'date validation',
                    'numéro',
                    'type demande',
                    'code(s) préparation(s)',
                    'code(s) livraison(s)',
                    'référence article',
                    'libellé article',
                    'code-barre article',
                    'code-barre référence',
                    'quantité disponible',
                    'quantité à prélever'
                ],
                $freeFieldsConfig['freeFieldsHeader']
            );

            $firstDates = $preparationRepository->getFirstDatePreparationGroupByDemande($demandes);
            $prepartionOrders = $preparationRepository->getNumeroPrepaGroupByDemande($demandes);
            $livraisonOrders = $livraisonRepository->getNumeroLivraisonGroupByDemande($demandes);

            $articleLines = $articleLineRepository->findByRequests($demandes);
            $referenceLines = $referenceLineRepository->findByRequests($demandes);

            $nowStr = date("d-m-Y H:i");
            return $CSVExportService->createBinaryResponseFromData(
                "dem-livr $nowStr.csv",
                $demandes,
                $headers,
                function (Demande $demande)
                use (
                    $firstDates,
                    $prepartionOrders,
                    $livraisonOrders,
                    $articleLines,
                    $referenceLines,
                    $freeFieldsConfig,
                    $freeFieldService,
                    $freeFieldsRepository
                ) {
                    $rows = [];
                    $demandeId = $demande->getId();
                    $firstDatePrepaForDemande = isset($firstDates[$demandeId]) ? $firstDates[$demandeId] : null;
                    $prepartionOrdersForDemande = isset($prepartionOrders[$demandeId]) ? $prepartionOrders[$demandeId] : [];
                    $livraisonOrdersForDemande = isset($livraisonOrders[$demandeId]) ? $livraisonOrders[$demandeId] : [];
                    $infosDemand = $this->getCSVExportFromDemand($demande, $firstDatePrepaForDemande, $prepartionOrdersForDemande, $livraisonOrdersForDemande);

                    $referenceLinesForRequest = $referenceLines[$demandeId] ?? [];
                    /** @var DeliveryRequestReferenceLine $line */
                    foreach ($referenceLinesForRequest as $line) {
                        $demandeData = [];
                        $articleRef = $line->getReference();

                        $availableQuantity = $articleRef->getQuantiteDisponible();

                        array_push($demandeData, ...$infosDemand);
                        $demandeData[] = $articleRef ? $articleRef->getReference() : '';
                        $demandeData[] = $articleRef ? $articleRef->getLibelle() : '';
                        $demandeData[] = '';
                        $demandeData[] = $articleRef ? $articleRef->getBarCode() : '';
                        $demandeData[] = $availableQuantity;
                        $demandeData[] = $line->getQuantityToPick();

                        foreach($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
                            $freeField = $freeFieldsRepository->find($freeFieldId);
                            $demandeData[] = FormatHelper::freeField($demandeData['freeFields'][$freeFieldId] ?? '',$freeField);
                        }
                        $rows[] = $demandeData;
                    }

                    $articleLinesForRequest = $articleLines[$demandeId] ?? [];
                    /** @var DeliveryRequestArticleLine $line */
                    foreach ($articleLinesForRequest as $line) {
                        $article = $line->getArticle();
                        $demandeData = [];

                        array_push($demandeData, ...$infosDemand);
                        $demandeData[] = $article->getArticleFournisseur()->getReferenceArticle()->getReference();
                        $demandeData[] = $article->getLabel();
                        $demandeData[] = $article->getBarCode();
                        $demandeData[] = '';
                        $demandeData[] = $article->getQuantite();
                        $demandeData[] = $line->getQuantityToPick();

                        foreach ($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
                            $freeField = $freeFieldsRepository->find($freeFieldId);
                            $demandeData[] = FormatHelper::freeField($demandeData['freeFields'][$freeFieldId] ?? '', $freeField);
                        }
                        $rows[] = $demandeData;
                    }

                    return $rows;
                }
            );
        } else {
            throw new BadRequestHttpException();
        }
    }

    private function getCSVExportFromDemand(Demande $demande,
                                            $firstDatePrepaStr,
                                            array $preparationOrdersNumeros,
                                            array $livraisonOrders): array {
        $firstDatePrepa = isset($firstDatePrepaStr)
            ? DateTime::createFromFormat('Y-m-d H:i:s', $firstDatePrepaStr)
            : null;

        $requestCreationDate = $demande->getDate();

        return [
            FormatHelper::deliveryRequester($demande),
            $demande->getStatut()->getNom(),
            FormatHelper::location($demande->getDestination()),
            strip_tags($demande->getCommentaire()),
            isset($requestCreationDate) ? $requestCreationDate->format('d/m/Y H:i:s') : '',
            isset($firstDatePrepa) ? $firstDatePrepa->format('d/m/Y H:i:s') : '',
            $demande->getNumero(),
            $demande->getType() ? $demande->getType()->getLabel() : '',
            !empty($preparationOrdersNumeros) ? implode(' / ', $preparationOrdersNumeros) : 'ND',
            !empty($livraisonOrders) ? implode(' / ', $livraisonOrders) : 'ND',
        ];
    }


    /**
     * @Route("/autocomplete", name="get_demandes", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function getDemandesAutoComplete(Request $request,
                                            EntityManagerInterface $entityManager): Response
    {
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $search = $request->query->get('term');

        return new JsonResponse([
            'results' => $demandeRepository->getIdAndLibelleBySearch($search)
        ]);
    }

}
