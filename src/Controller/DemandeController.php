<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\Setting;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\SubLineFieldsParam;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\FormatService;
use App\Service\RefArticleDataService;
use App\Service\DemandeLivraisonService;
use App\Service\FreeFieldService;
use App\Service\SettingsService;
use App\Service\TranslationService;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use App\Helper\FormatHelper;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;


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
                            SettingsService $settingsService,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $settingRepository = $entityManager->getRepository(Setting::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

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
                'defaultReceiver' => $demande->getReceiver() ? [
                    'label' => $demande->getReceiver()?->getUsername(),
                    'value' => $demande->getReceiver()?->getId(),
                    'selected' => true,
                ] : [],
                'fieldsParam' => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DEMANDE),
                'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
                'typeChampsLibres' => $typeChampLibre,
                'freeFieldsGroupedByTypes' => $freeFieldsGroupedByTypes,
                'defaultDeliveryLocations' => $settingsService->getDefaultDeliveryLocationsByTypeId($entityManager),
                'restrictedLocations' => $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST),
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
            $projectRepository = $entityManager->getRepository(Project::class);

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
                $receiver = isset($data['demandeReceiver']) ? $utilisateurRepository->find($data['demandeReceiver']) : null;
                $emplacement = $emplacementRepository->find(intval($data['destination']));
                $project = $projectRepository->find(isset($data['project']) ? intval($data['project']) : -1);
                $expectedAt = FormatHelper::parseDatetime($data['expectedAt'] ?? '');
                $demande
                    ->setUtilisateur($utilisateur)
                    ->setDestination($emplacement)
                    ->setProject($project)
                    ->setExpectedAt($expectedAt)
                    ->setType($type)
                    ->setReceiver($receiver)
                    ->setCommentaire(StringHelper::cleanedComment($data['commentaire'] ?? null));
                $entityManager->flush();
                $champLibreService->manageFreeFields($demande, $data, $entityManager);
                $entityManager->flush();
                $response = [
                    'success' => true,
                    'entete' => $this->renderView('demande/demande-show-header.html.twig', [
                        'demande' => $demande,
                        'modifiable' => $demande->getStatut()?->getCode() === Demande::STATUT_BROUILLON,
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
    public function new(Request                 $request,
                        EntityManagerInterface  $entityManager,
                        DemandeLivraisonService $demandeLivraisonService,
                        FreeFieldService        $champLibreService,
                        TranslationService      $translation): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $data['commentaire'] = StringHelper::cleanedComment($data['commentaire'] ?? null);
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
                        'msg' => 'Une autre ' . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ' est en cours de création, veuillez réessayer.'
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
    public function index(EntityManagerInterface  $entityManager,
                          SettingsService         $settingsService,
                          DemandeLivraisonService $deliveryRequestService,
                                                  $reception = null,
                                                  $filter = null): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $projectRepository = $entityManager->getRepository(Project::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $fields = $deliveryRequestService->getVisibleColumnsConfig($entityManager, $this->getUser());
        $defaultReceiverParam = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_RECEIVER_DEMANDE);
        $defaultReceiver = '';
        if(!empty($defaultReceiverParam->getElements())){
            $defaultReceiver = $userRepository->find($defaultReceiverParam->getElements()[0]);
        }

        $defaultTypeParam = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_TYPE_DEMANDE);
        $defaultType = null;
        if(!empty($defaultTypeParam->getElements())){
            $defaultType = $typeRepository->find($defaultTypeParam->getElements()[0]);
        }

        $typeChampLibre = [];
        foreach ($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }

        $receiverEqualRequester = boolval($settingRepository->getOneParamByLabel(Setting::RECEIVER_EQUALS_REQUESTER));
        $userForModal = $receiverEqualRequester ? $this->getUser() : $defaultReceiver;
        $defaultDeliveryLocations = $settingsService->getDefaultDeliveryLocationsByTypeId($entityManager);

        return $this->render('demande/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'typeChampsLibres' => $typeChampLibre,
            'fieldsParam' => $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_DEMANDE),
            'types' => $types,
            'fields' => $fields,
            'filterStatus' => $filter,
            'receptionFilter' => $reception,
            'defaultReceiver' => $userForModal ? '<option selected value="'.$userForModal->getId().'">'.$userForModal->getUsername().'</option>' : '',
            'defaultTypeId' => $defaultType?->getId(),
            'defaultDeliveryLocations' => $defaultDeliveryLocations,
            'restrictedLocations' => $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST),
            'projects' => $projectRepository->findActive(),
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
        $data = $demandeLivraisonService->getDataForDatatable($request->request, $filterStatus, $filterReception, $this->getUser());

        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="demande_show", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR})
     */
    public function show(EntityManagerInterface $entityManager,
                         DemandeLivraisonService $demandeLivraisonService,
                         Demande $demande,
                         EntityManagerInterface $manager): Response {

        $statutRepository = $entityManager->getRepository(Statut::class);
        $subLineFieldsParamRepository = $entityManager->getRepository(SubLineFieldsParam::class);
        $currentUser = $this->getUser();

        $status = $demande->getStatut();
        $fields = $demandeLivraisonService->getVisibleColumnsTableArticleConfig($entityManager, $demande);

        return $this->render('demande/show/index.html.twig', [
            'demande' => $demande,
            'statuts' => $statutRepository->findByCategorieName(Demande::CATEGORIE),
            'modifiable' => $status?->getCode() === Demande::STATUT_BROUILLON,
            'finished' => $status?->getCode() === Demande::STATUT_A_TRAITER,
            'fieldsParam' => $subLineFieldsParamRepository->getByEntity(SubLineFieldsParam::ENTITY_CODE_DEMANDE_REF_ARTICLE),
            "initial_visible_columns" => json_encode($demandeLivraisonService->getVisibleColumnsTableArticleConfig($entityManager, $demande, true)),
            'showDetails' => $demandeLivraisonService->createHeaderDetailsConfig($demande),
            'showTargetLocationPicking' => $manager->getRepository(Setting::class)->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION),
            'managePreparationWithPlanning' => $manager->getRepository(Setting::class)->getOneParamByLabel(Setting::MANAGE_PREPARATIONS_WITH_PLANNING),
            'fields' => $fields,
        ]);
    }

    #[Route("/delivery-request-logistic-units-api", name: "delivery_request_logistic_units_api", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_DEM_LIVR], mode: HasPermission::IN_JSON)]
    public function logisticUnitsApi(Request                 $request,
                                     DemandeLivraisonService $deliveryRequestService,
                                     EntityManagerInterface  $manager): Response {
        $deliveryRequest = $manager->find(Demande::class, $request->query->get('id'));
        $needsQuantitiesCheck = !$manager->getRepository(Setting::class)->getOneParamByLabel(Setting::MANAGE_PREPARATIONS_WITH_PLANNING);
        $editable = $deliveryRequest->getStatut()->getCode() === Demande::STATUT_BROUILLON;

        $lines = Stream::from($deliveryRequest->getArticleLines())
            ->map(fn(DeliveryRequestArticleLine $line) => $line->getPack())
            ->unique()
            // null packs in first
            ->sort(fn(?Pack $logisticUnit1, ?Pack $logisticUnit2) => ($logisticUnit1?->getCode() <=> $logisticUnit2?->getCode()))
            ->map(fn(?Pack $logisticUnit) => [
                "pack" => $logisticUnit
                    ? [
                        "packId" => $logisticUnit?->getId(),
                        "code" => $logisticUnit?->getCode() ?? null,
                        "location" => $this->formatService->location($logisticUnit?->getLastDrop()?->getEmplacement()),
                        "project" => $logisticUnit?->getProject()?->getCode() ?? null,
                        "nature" => $this->formatService->nature($logisticUnit?->getNature()),
                        "color" => $logisticUnit?->getNature()?->getColor() ?? null,
                        "currentQuantity" => Stream::from($deliveryRequest->getArticleLines()
                            ->filter(fn(DeliveryRequestArticleLine $line) => $line->getArticle()->getCurrentLogisticUnit() === $logisticUnit))
                            ->count(),
                        "totalQuantity" => $logisticUnit?->getChildArticles()?->count()
                    ]
                    : null,
                "articles" => Stream::from($deliveryRequest->getArticleLines())
                    ->filter(fn(DeliveryRequestArticleLine $line) => $line->getPack()?->getId() === $logisticUnit?->getId())
                    ->map(function(DeliveryRequestArticleLine $line) use ($needsQuantitiesCheck, $deliveryRequest, $editable) {
                        $article = $line->getArticle();
                        return [
                            "reference" => $article->getArticleFournisseur()->getReferenceArticle()
                                ? $article->getArticleFournisseur()->getReferenceArticle()->getReference()
                                : '',
                            "label" => $article->getLabel() ?: '',
                            "location" => $this->formatService->location($article->getEmplacement()),
                            "targetLocationPicking" => $this->formatService->location($line->getTargetLocationPicking()),
                            "quantityToPick" => $line->getQuantityToPick() ?: '',
                            "barcode" => $article->getBarCode() ?? '',
                            "error" => (
                                $needsQuantitiesCheck
                                && $article->getQuantite() < $line->getQuantityToPick()
                                && $deliveryRequest->getStatut()->getCode() === Demande::STATUT_BROUILLON
                            ),
                            "project" => $this->getFormatter()->project($line->getProject()),
                            "comment" => $line->getComment(),
                            "actions" => $this->renderView(
                                'demande/datatableLigneArticleRow.html.twig',
                                [
                                    'id' => $line->getId(),
                                    'articleId' => $article->getId(),
                                    'name' => ReferenceArticle::QUANTITY_TYPE_ARTICLE,
                                    'reference' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                                    'modifiable' => $editable,
                                ]
                            ),
                        ];
                    })
                    ->values(),
            ])
            ->values();

        $references = Stream::from($deliveryRequest->getReferenceLines())
            ->map(function(DeliveryRequestReferenceLine $line) use ($needsQuantitiesCheck, $deliveryRequest, $editable) {
                $reference = $line->getReference();
                return [
                    "reference" => $reference->getReference() ?: '',
                    "label" => $reference->getLibelle() ?: '',
                    "location" => $this->formatService->location($reference->getEmplacement()),
                    "targetLocationPicking" => $this->formatService->location($line->getTargetLocationPicking()),
                    "quantityToPick" => $line->getQuantityToPick() ?? '',
                    "barcode" => $reference->getBarCode() ?? '',
                    "error" => $needsQuantitiesCheck
                        && $reference->getQuantiteDisponible() < $line->getQuantityToPick()
                        && $deliveryRequest->getStatut()->getCode() === Demande::STATUT_BROUILLON,
                    "project" => $this->getFormatter()->project($line->getProject()),
                    "comment" => $line->getComment(),
                    "actions" => $this->renderView(
                        'demande/datatableLigneArticleRow.html.twig',
                        [
                            'id' => $line->getId(),
                            'name' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                            'refArticleId' => $reference->getId(),
                            'reference' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                            'modifiable' => $editable,
                        ]
                    )
                ];
            })
            ->toArray();


        if (!isset($lines[0]) || $lines[0]['pack'] !== null) {
            array_unshift($lines, [
                'pack' => null,
                'articles' => [],
            ]);
        }
        $lines[0]['articles'] = array_merge($lines[0]['articles'], $references);

        $columns = $deliveryRequestService->getVisibleColumnsTableArticleConfig($manager, $deliveryRequest);

        return $this->json([
            "success" => true,
            "html" => $this->renderView("demande/show/line-list.html.twig", [
                "lines" => $lines,
                "emptyLines" => $deliveryRequest->getArticleLines()->isEmpty() && $deliveryRequest->getReferenceLines()->isEmpty(),
                "editable" => $editable
            ]),
            "columns" => $columns,
        ]);
    }

    #[Route("remove_delivery_request_logistic_unit_line", name: "remove_delivery_request_logistic_unit_line", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_DEM_LIVR], mode: HasPermission::IN_JSON)]
    public function removeLogisticUnitLine(Request                  $request,
                                           EntityManagerInterface   $manager,
                                           TranslationService       $translation): Response {
        $query = $request->query->all();
        $logisticUnit = $manager->find(Pack::class, $query['logisticUnitId']);
        $deliveryRequest = $manager->find(Demande::class, $query['deliveryRequestId']);

        Stream::from($deliveryRequest->getArticleLines())
            ->filter(fn(DeliveryRequestArticleLine $line) => $line->getArticle()->getCurrentLogisticUnit() && $line->getArticle()->getCurrentLogisticUnit() === $logisticUnit)
            ->each(fn(DeliveryRequestArticleLine $line) => $manager->remove($line));

        $manager->flush();

        return $this->json([
            'success' => true,
            'msg' => "L'unité logistique a bien été retirée de la " . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . "."
        ]);
    }

    /**
     * @Route("/api/{id}", name="demande_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function articleApi(Demande $demande, EntityManagerInterface $entityManager): Response
    {
        $settings = $entityManager->getRepository(Setting::class);
        $needsQuantitiesCheck = !$settings->getOneParamByLabel(Setting::MANAGE_PREPARATIONS_WITH_PLANNING);
        $referenceLines = $demande->getReferenceLines();
        $rowsRC = [];
        foreach ($referenceLines as $line) {
            $rowsRC[] = [
                "reference" => $line->getReference()->getReference() ?: '',
                "label" => $line->getReference()->getLibelle() ?: '',
                "location" => FormatHelper::location($line->getReference()->getEmplacement()),
                "targetLocationPicking" => FormatHelper::location($line->getTargetLocationPicking()),
                "quantityToPick" => $line->getQuantityToPick() ?? '',
                "barcode" => $line->getReference() ? $line->getReference()->getBarCode() : '',
                "error" => $needsQuantitiesCheck && $line->getReference()->getQuantiteDisponible() < $line->getQuantityToPick()
                    && $demande->getStatut()->getCode() === Demande::STATUT_BROUILLON,
                "Actions" => $this->renderView(
                    'demande/datatableLigneArticleRow.html.twig',
                    [
                        'id' => $line->getId(),
                        'name' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                        'refArticleId' => $line->getReference()->getId(),
                        'reference' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                        'modifiable' => $demande->getStatut()?->getCode() === Demande::STATUT_BROUILLON,
                    ]
                )
            ];
        }
        $articleLines = $demande->getArticleLines();
        $rowsCA = [];
        foreach ($articleLines as $line) {
            $article = $line->getArticle();
            $rowsCA[] = [
                "reference" => $article->getArticleFournisseur()->getReferenceArticle()
                    ? $article->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : '',
                "label" => $article->getLabel() ?: '',
                "location" => FormatHelper::location($article->getEmplacement()),
                "targetLocationPicking" => FormatHelper::location($line->getTargetLocationPicking()),
                "quantityToPick" => $line->getQuantityToPick() ?: '',
                "barcode" => $article->getBarCode() ?? '',
                "error" => $needsQuantitiesCheck && $article->getQuantite() < $line->getQuantityToPick() && $demande->getStatut()->getCode() === Demande::STATUT_BROUILLON,
                "Actions" => $this->renderView(
                    'demande/datatableLigneArticleRow.html.twig',
                    [
                        'id' => $line->getId(),
                        'articleId' => $article->getId(),
                        'name' => ReferenceArticle::QUANTITY_TYPE_ARTICLE,
                        'reference' => ReferenceArticle::QUANTITY_TYPE_REFERENCE,
                        'modifiable' => $demande->getStatut()?->getCode() === Demande::STATUT_BROUILLON,
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
    public function addArticle(Request                $request,
                               EntityManagerInterface $entityManager,
                               ArticleDataService     $articleDataService,
                               RefArticleDataService  $refArticleDataService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $referenceArticle = $referenceArticleRepository->find($data['referenceArticle']);
            $demandeRepository = $entityManager->getRepository(Demande::class);
            $demande = $demandeRepository->find($data['livraison']);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $resp = $refArticleDataService->addReferenceToRequest(
                $data,
                $referenceArticle,
                $currentUser,
                false,
                $entityManager,
                $demande
            );
            if ($resp['article'] ?? false) {
                $articleDataService->editArticle($data);
                $resp = true;
            }
            $entityManager->flush();
            return new JsonResponse([
                "success" => $resp
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/retirer-article", name="demande_remove_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function removeArticle(Request                   $request,
                                  EntityManagerInterface    $entityManager,
                                  TranslationService        $translation): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $referenceLineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);
            $articleLineRepository = $entityManager->getRepository(DeliveryRequestArticleLine::class);

            if (array_key_exists(ReferenceArticle::QUANTITY_TYPE_REFERENCE, $data)) {
                $line = $referenceLineRepository->find($data[ReferenceArticle::QUANTITY_TYPE_REFERENCE]);
            } elseif (array_key_exists(ReferenceArticle::QUANTITY_TYPE_ARTICLE, $data)) {
                $line = $articleLineRepository->find($data[ReferenceArticle::QUANTITY_TYPE_ARTICLE]);
            }

            if (isset($line)) {
                $entityManager->remove($line);
                $entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'msg' => "La ligne a bien été retirée de la " . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . "."
            ]);
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
            $targetLocationPicking = isset($data['target-location-picking'])
                ? $entityManager->find(Emplacement::class, $data['target-location-picking'])
                : null;

            $line
                ->setQuantityToPick(max($data["quantite"], 0)) // protection contre quantités négatives
                ->setTargetLocationPicking($targetLocationPicking);

            $entityManager->flush();

            return new JsonResponse([
                "success" => true,
                "msg" => 'La ligne de la demande a bien été modifiée'
            ]);
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
                'maximum' => $maximumQuantity,
                "showTargetLocationPicking" => $entityManager->getRepository(Setting::class)->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION)
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
                                   Request                $request,
                                   FreeFieldService       $freeFieldService,
                                   CSVExportService       $CSVExportService,
                                   TranslationService     $translation): Response
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

            $demandes = $demandeRepository->findByDates($dateTimeMin, $dateTimeMax);
            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_LIVRAISON]);

            // en-têtes champs fixes
            $headers = array_merge(
                [
                    'demandeur',
                    'statut',
                    'destination',
                    'commentaire',
                    'date de création',
                    'date de validation',
                    'numéro',
                    'type demande',
                    'date attendue',
                    mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)),
                    'code(s) préparation(s)',
                    'code(s) ' . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)) . '(s)',
                    'référence article',
                    'libellé article',
                    'code-barre article',
                    'code-barre référence',
                    'quantité disponible',
                    'quantité à prélever'
                ],
                $freeFieldsConfig['freeFieldsHeader']
            );

            $prepartionOrders = $preparationRepository->getNumeroPrepaGroupByDemande($demandes);
            $livraisonOrders = $livraisonRepository->getNumeroLivraisonGroupByDemande($demandes);

            $articleLines = $articleLineRepository->findByRequests($demandes);
            $referenceLines = $referenceLineRepository->findByRequests($demandes);

            $nowStr = (new DateTime('now'))->format("d-m-Y-H-i-s");
            return $CSVExportService->createBinaryResponseFromData(
                "dem-livr $nowStr.csv",
                $demandes,
                $headers,
                function (Demande $demande)
                use (
                    $prepartionOrders,
                    $livraisonOrders,
                    $articleLines,
                    $referenceLines,
                    $freeFieldsConfig
                ) {
                    $rows = [];
                    $demandeId = $demande->getId();
                    $prepartionOrdersForDemande = $prepartionOrders[$demandeId] ?? [];
                    $livraisonOrdersForDemande = $livraisonOrders[$demandeId] ?? [];
                    $infosDemand = $this->getCSVExportFromDemand($demande, $prepartionOrdersForDemande, $livraisonOrdersForDemande);

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

                        $freeFieldValues = $demande->getFreeFields();
                        foreach($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
                            $demandeData[] = FormatHelper::freeField($freeFieldValues[$freeFieldId] ?? '', $freeField);
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

                        foreach ($freeFieldsConfig['freeFields'] as $freeFieldId => $freeField) {
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
                                            array $preparationOrdersNumeros,
                                            array $livraisonOrders): array {
        return [
            FormatHelper::deliveryRequester($demande),
            FormatHelper::status($demande->getStatut()),
            FormatHelper::location($demande->getDestination()),
            FormatHelper::html($demande->getCommentaire()),
            FormatHelper::datetime($demande->getCreatedAt()),
            FormatHelper::datetime($demande->getValidatedAt()),
            $demande->getNumero(),
            FormatHelper::type($demande->getType()),
            FormatHelper::date($demande->getExpectedAt()),
            $demande?->getProject()?->getCode(),
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

    /**
     * @Route("/api-references", options={"expose"=true}, name="demande_api_references", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function apiReferences(Request $request,
                        DemandeLivraisonService $demandeLivraisonService): Response
    {
        $data = $demandeLivraisonService->getDataForReferencesDatatable($request->request->get('deliveryId'));

        return new JsonResponse($data);
    }

    /**
     * @Route("/api-columns", name="delivery_request_api_columns", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function apiColumns(EntityManagerInterface $entityManager, DemandeLivraisonService $deliveryRequestService): Response {
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $columns = $deliveryRequestService->getVisibleColumnsConfig($entityManager, $currentUser);

        return $this->json(array_values($columns));
    }

    /**
     * @Route("/visible_column", name="save_visible_columns_for_delivery_request", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_DEM_LIVR}, mode=HasPermission::IN_JSON)
     */
    public function saveVisibleColumn(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService): Response {
        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $visibleColumnService->setVisibleColumns('deliveryRequest', $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées'
        ]);
    }

    #[Route("/{delivery}/ajouter-ul/{logisticUnit}", name: "delivery_add_logistic_unit", options: ["expose" => true], methods: "POST")]
    public function addLogisticUnit(EntityManagerInterface  $manager,
                                    DemandeLivraisonService $demandeLivraisonService,
                                    Demande                 $delivery,
                                    Pack                    $logisticUnit,
                                    TranslationService      $translation): JsonResponse {
        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);
        $projectField = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_DELIVERY_REQUEST_PROJECT);

        $projectRequired = $projectField->isDisplayedCreate() && $projectField->isRequiredCreate()
            || $projectField->isDisplayedEdit() && $projectField->isRequiredEdit();

        if(!$logisticUnit->getProject() && $projectRequired) {
            return $this->json([
                "success" => false,
                "msg" => "Le " . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . " est obligatoire pour les " . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ", l'unité logistique n'en a pas et ne peut pas être ajoutée",
            ]);
        }

        if($delivery->getProject() && $logisticUnit?->getProject()?->getId() != $delivery->getProject()->getId()) {
            return $this->json([
                "success" => false,
                "msg" => "L'unité logistique n'a pas le même " . mb_strtolower($translation->translate('Référentiel', 'Projet', 'Projet', false)) . " que la demande",
            ]);
        }

        $delivery->setProject($logisticUnit->getProject());

        foreach($logisticUnit->getChildArticles() as $article) {
            $line = $demandeLivraisonService->createArticleLine($article, $delivery, [
                'quantityToPick' => $article->getQuantite(),
                'targetLocationPicking' => $article->getEmplacement()
            ]);

            $delivery->addArticleLine($line);
            $manager->persist($line);
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "L'unité logistique <b>{$logisticUnit->getCode()}</b> a été ajoutée a la demande",
            "header" => $this->renderView('demande/demande-show-header.html.twig', [
                "demande" => $delivery,
                "modifiable" => $delivery->getStatut()?->getCode() === Demande::STATUT_BROUILLON,
                "showDetails" => $demandeLivraisonService->createHeaderDetailsConfig($delivery)
            ]),
        ]);
    }

    #[Route("/details-ul/{logisticUnit}", name: "delivery_logistic_unit_details", options: ["expose" => true], methods: "GET")]
    public function logisticUnitDetails(Pack $logisticUnit): JsonResponse {
        return $this->json([
            "success" => true,
            "html" => $this->renderView("demande/logisticUnitDetails.html.twig", [
                "logisticUnit" => $logisticUnit,
            ])
        ]);
    }

    #[Route("/redirect-before-index", name: 'redirect_before_index', options: ["expose" => true], methods: "GET")]
    public function redirectBeforeIndex(EntityManagerInterface  $entityManager,
                                        SettingsService         $settingsService,
                                        FreeFieldService        $champLibreService,
                                        DemandeLivraisonService $deliveryRequestService,
                                        TranslationService      $translation){
        $typeRepository = $entityManager->getRepository(Type::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $defaultReceiverParam = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_RECEIVER_DEMANDE);
        $defaultReceiver = '';
        if(!empty($defaultReceiverParam->getElements())){
            $defaultReceiver = $userRepository->find($defaultReceiverParam->getElements()[0]);
        }

        $defaultTypeParam = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_TYPE_DEMANDE);
        $defaultType = null;
        if(!empty($defaultTypeParam->getElements())){
            $defaultType = $typeRepository->find($defaultTypeParam->getElements()[0]);
        }

        $receiverEqualRequester = boolval($settingRepository->getOneParamByLabel(Setting::RECEIVER_EQUALS_REQUESTER));
        $demandeFieldParamExpectedAt = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_EXPECTED_AT);;
        $demandeFieldParamProject = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_DELIVERY_REQUEST_PROJECT);
        $recipient = $receiverEqualRequester ? $this->getUser() : $defaultReceiver;
        $defaultDeliveryLocations = $settingsService->getDefaultDeliveryLocationsByTypeId($entityManager);
        $requiredFreeField = $defaultType ? $freeFieldRepository->getByTypeAndRequiredCreate($defaultType) : [];
        $createDelivery = $recipient && $defaultType && isset($defaultDeliveryLocations[$defaultType->getId()]) && empty($requiredFreeField) && !$demandeFieldParamExpectedAt->isRequiredCreate() && !$demandeFieldParamProject->isRequiredCreate();

        $data = [];
        if($createDelivery){
            $data['destination'] = $defaultDeliveryLocations[$defaultType->getId()]["id"];
            $data['demandeur'] = $this->getUser();
            $data['demandeReceiver'] = $recipient->getId();
            $data['type'] = $defaultType;
            $data['commentaire'] = StringHelper::cleanedComment($data['commentaire'] ?? null);
            $demande = $deliveryRequestService->newDemande($data, $entityManager, $champLibreService);

            if ($demande instanceof Demande) {
                $entityManager->persist($demande);
                try {
                    $entityManager->flush();
                }
                    /** @noinspection PhpRedundantCatchClauseInspection */
                catch (UniqueConstraintViolationException $e) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Une autre ' . mb_strtolower($translation->translate("Demande", "Livraison", "Demande de livraison", false)) . ' est en cours de création, veuillez réessayer.'
                    ]);
                }

                return $this->redirectToRoute('demande_show', ['id' => $demande->getId()]);
            }
            else {
                return new JsonResponse($demande);
            }
        } else {
            return $this->redirectToRoute('demande_index', ['open-modal' => 'new']);
        }
    }

    #[Route("/api/table-article-content/{request}", name: "api_table_articles_content", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function apiTableArticleContent(Demande $request,
                                           FormatService $formatService,
                                           EntityManagerInterface $entityManager,
                                           ArticleDataService $articleDataService): JsonResponse {
        $user = $this->getUser();
        $subLineFieldsParamRepository = $entityManager->getRepository(SubLineFieldsParam::class);
        $fieldsParam = $subLineFieldsParamRepository->getByEntity(SubLineFieldsParam::ENTITY_CODE_DEMANDE_REF_ARTICLE);

        $projectParam = $fieldsParam[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT] ?? [];
        $isProjectRequired = $projectParam['required'] ?? false;
        $isProjectDisplayedUnderCondition = $projectParam['displayedUnderCondition'] ?? false;
        $projectConditionFixedField = $isProjectDisplayedUnderCondition ? $projectParam['conditionFixedField'] ?? null : null;
        $projectConditionFixedValue = $isProjectDisplayedUnderCondition ? $projectParam['conditionFixedFieldValue'] ?? [] : [];

        $commentParam = $fieldsParam[SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT] ?? [];
        $isCommentRequired = $commentParam['required'] ?? false;

        $referencesData = Stream::from($request->getReferenceLines())
            ->map(function (DeliveryRequestReferenceLine $line) use ($isProjectDisplayedUnderCondition, $projectConditionFixedField, $projectConditionFixedValue, $isProjectRequired, $isCommentRequired, $formatService) {
                $reference = $line->getReference();
                return [
                    "createRow" => false,
                    "actions" => '<span class="d-flex justify-content-start align-items-center delete-row" data-target="#modalDeleteArticle" data-toggle="modal" data-name="reference" data-id="' . $line->getId() . '" onclick="deleteRowDemande($(this), $(\'#modalDeleteArticle\'), $(\'#submitDeleteArticle\'))"><span class="wii-icon wii-icon-trash"></span></span>',
                    "reference" => ($reference->getReference() ?: '')
                        . $this->render('form.html.twig', [
                            'macroName' => 'input',
                            'macroParams' => ['lineId', null, true, $line->getId(), ['type' => 'hidden']],
                        ])->getContent()
                        . $this->render('form.html.twig', [
                            'macroName' => 'input',
                            'macroParams' => ['type', null, true, 'reference', ['type' => 'hidden']],
                        ])->getContent(),
                    "label" => $reference->getLibelle() ?: '',
                    "quantityToPick" => $this->render('form.html.twig', [
                        'macroName' => 'input',
                        'macroParams' => ['quantity-to-pick', null, true, $line->getQuantityToPick(), ['type' => 'number', 'min' => 1, 'onChange' => 'onChangeFillComment($(this))']],
                    ])->getContent(),
                    "location" => $reference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE ? $formatService->location($reference->getEmplacement()) : null,
                    "barcode" => $reference->getBarcode() ?: '',
                    "project" => !$isProjectDisplayedUnderCondition || ($isProjectDisplayedUnderCondition && $projectConditionFixedField === "Type Reference" && in_array($reference->getType()?->getId(), $projectConditionFixedValue))
                        ? $this->render('form.html.twig', [
                            'macroName' => 'select',
                            'macroParams' => [SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT, null, $isProjectRequired, ['type' => 'project', 'items' => $line->getProject() ? [
                                'text' => $formatService->project($line->getProject()),
                                'selected' => true,
                                'value' => $line->getProject()?->getId(),
                            ] : [], 'onChange' => 'onChangeFillComment($(this))']]])->getContent()
                        : $formatService->project($line->getProject()) ?? '',
                    "comment" =>  $this->render('form.html.twig', [
                            'macroName' => 'input',
                            'macroParams' => [SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT, null, $isCommentRequired, $line->getComment()]
                        ])->getContent(),
                    "article" => "",
                    "targetLocationPicking" => "",
                ];
            })
            ->toArray();

        $isUserQuantityTypeArticle = $user->getRole()->getQuantityType() == ReferenceArticle::QUANTITY_TYPE_ARTICLE;
        $articlesData = Stream::from($request->getArticleLines())
            ->map(function (DeliveryRequestArticleLine $line) use ($isUserQuantityTypeArticle, $projectConditionFixedValue, $projectConditionFixedField, $isProjectDisplayedUnderCondition, $isCommentRequired, $isProjectRequired, $entityManager, $request, $user, $articleDataService, $formatService) {
                $article = $line->getArticle();
                return [
                    "createRow" => false,
                    "actions" => '<span class="d-flex justify-content-start align-items-center delete-row" data-target="#modalDeleteArticle" data-toggle="modal" data-name="article" data-id="' . $line->getId() . '" onclick="deleteRowDemande($(this), $(\'#modalDeleteArticle\'), $(\'#submitDeleteArticle\'))"><span class="wii-icon wii-icon-trash"></span></span>',
                    "reference" =>
                        ($article->getReferenceArticle()->getReference() ?: '')
                        . $this->render('form.html.twig', [
                            'macroName' => 'input',
                            'macroParams' => ['lineId', null, true, $line->getId(), ['type' => 'hidden']],
                        ])->getContent()
                        . $this->render('form.html.twig', [
                            'macroName' => 'input',
                            'macroParams' => ['type', null, true, 'article', ['type' => 'hidden']],
                        ])->getContent(),
                    "label" => $article->getLabel() ?: '',
                    "quantityToPick" => $this->render('form.html.twig', [
                        'macroName' => 'input',
                        'macroParams' => ['quantity-to-pick', null, true, $line->getQuantityToPick(), ['type' => 'number', 'min' => 1]],
                    ])->getContent(),
                    "location" => $formatService->location($article->getEmplacement()),
                    "barcode" => $article->getBarcode() ?: '',
                    "project" => !$isProjectDisplayedUnderCondition || ($projectConditionFixedField === "Type Reference" && in_array($article->getReferenceArticle()->getType()?->getId(), $projectConditionFixedValue))
                        ? $this->render('form.html.twig', [
                            'macroName' => 'select',
                            'macroParams' => [SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_PROJECT, null, $isProjectRequired, ['type' => 'project', 'items' => $line->getProject() ? [
                                'text' => $formatService->project($line->getProject()),
                                'selected' => true,
                                'value' => $line->getProject()?->getId(),
                            ] : []]]])->getContent()
                        : $formatService->project($line->getProject()) ?? '',
                    "comment" => $this->render('form.html.twig', [
                            'macroName' => 'input',
                            'macroParams' => [SubLineFieldsParam::FIELD_CODE_DEMANDE_REF_ARTICLE_COMMENT, null, $isCommentRequired, $line->getComment()]
                        ])->getContent(),
                    "article" => $isUserQuantityTypeArticle
                        ? $this->render('form.html.twig', [
                            'macroName' => 'select',
                            'macroParams' => ['article', null, false, [
                                'items' => Stream::from($articleDataService->findAndSortActiveArticlesByRefArticle($article->getReferenceArticle(), $article->getReferenceArticle()->getStockManagement(), $entityManager))
                                    ->keymap(function (Article $article) use ($line, $formatService) {
                                        return [$article->getId(), $article->getBarCode()];
                                    })
                                    ->toArray(),
                                'value' => $article->getId(),
                                'onChange' => 'onChangeFillComment($(this))',
                            ]]])->getContent()
                        : $article->getLabel(),
                    "targetLocationPicking" => !$isUserQuantityTypeArticle
                        ? $this->render('form.html.twig', [
                            'macroName' => 'select',
                            'macroParams' => ['targetLocationPicking', null, false, ['type' => 'location', 'items' => [
                                $line->getTargetLocationPicking()?->getId() => $formatService->location($line->getTargetLocationPicking()),
                            ]]]])->getContent()
                        : $formatService->location($line->getTargetLocationPicking()),
                ];
            })
            ->toArray();

        $data = array_merge($referencesData, $articlesData);
        $data[] = [
            "createRow" => true,
            "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
            "reference" => "",
            "label" => "",
            "quantityToPick" => "",
            "project" => "",
            "comment" => "",
            "barcode" => "",
            "location" => "",
            "article" => "",
            "targetLocationPicking" => "",
        ];

        return $this->json([
            "data" => $data,
        ]);
    }

    #[Route("/api/demande-article-submit-change/{deliveryRequest}", name: "api_demande_article_submit_change", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function apiDemandeArticleChange(Demande                $deliveryRequest,
                                            Request                $request,
                                            EntityManagerInterface $entityManager,
                                            RefArticleDataService  $refArticleDataService): JsonResponse {
        if ($data = json_decode($request->getContent(), true)) {
            if ($lineId = $data['lineId'] ?? null) {
                $projectRepository = $entityManager->getRepository(Project::class);
                // modification
                if ($data['type'] === 'article') {
                    $lineRepository = $entityManager->getRepository(DeliveryRequestArticleLine::class);
                } else {
                    $lineRepository = $entityManager->getRepository(DeliveryRequestReferenceLine::class);
                }
                $line = $lineRepository->find($lineId);
                $line
                    ->setQuantityToPick($data['quantity-to-pick'] ?? null)
                    ->setComment($data['comment'] ?? null)
                    ->setProject(isset($data['project']) ? $projectRepository->find($data['project']) : null);

                if ($line instanceof DeliveryRequestArticleLine && !empty($data['article'])) {
                    $line->setArticle($entityManager->getRepository(Article::class)->find($data['article']));
                }

                $entityManager->flush();
                $resp = ['success' => true, 'created' => false];
            } elseif ($data['referenceId'] ?? null) {
                // creation
                $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
                $referenceArticle = $referenceArticleRepository->find($data['referenceId']);

                $currentUser = $this->getUser();
                $resp = $refArticleDataService->addReferenceToRequest(
                    $data,
                    $referenceArticle,
                    $currentUser,
                    false,
                    $entityManager,
                    $deliveryRequest,
                    false
                );
                $entityManager->flush();
                $resp['lineId'] = $resp['line']->getId();
                $resp['created'] = true;
                $resp['success'] = true;
            }
        }
        return new JsonResponse(
            $resp ?? ['success' => false, 'created' => false]
        );
    }

    #[Route("/api/articles-by-reference/{request}/{referenceArticle}", name: "api_articles-by-reference", options: ["expose" => true], methods: "GET", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function apiArticlesByReference(Demande $request, ReferenceArticle $referenceArticle, EntityManagerInterface $entityManager, ArticleDataService $articleDataService): JsonResponse {
        $articles = $articleDataService->findAndSortActiveArticlesByRefArticle($referenceArticle, $referenceArticle->getStockManagement(), $entityManager, $request);
        return $this->json([
            "success" => true,
            "data" => Stream::from($articles)
                ->map(function (Article $article) {
                    return [
                        'text' => $article->getBarCode(),
                        'value' => $article->getId(),
                    ];
                })
                ->toArray(),
        ]);
    }

    #[Route("/visible-column-show", name: "save_visible_columns_for_delivery_request_show", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::DEM, Action::DISPLAY_DEM_LIVR], mode: HasPermission::IN_JSON)]
    public function saveVisibleColumnShow(Request $request,
                                      EntityManagerInterface $entityManager,
                                      VisibleColumnService $visibleColumnService): Response {

        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);

        $deliveryRequestRepository = $entityManager->getRepository(Demande::class);
        $deliveryRequest = $deliveryRequestRepository->find($data['id']);

        $deliveryRequest->setVisibleColumns($fields);

        $currentUser = $this->getUser();
        $visibleColumnService->setVisibleColumns(Demande::VISIBLE_COLUMNS_SHOW_FIELD, $fields, $currentUser);

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Vos préférences de colonnes à afficher ont bien été sauvegardées'
        ]);
    }
}
