<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategoryType;
use App\Entity\Chauffeur;
use App\Entity\Customer;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedField;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Fournisseur;
use App\Entity\FreeField\FreeField;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Language;
use App\Entity\LocationGroup;
use App\Entity\Menu;
use App\Entity\NativeCountry;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\PurchaseRequest;
use App\Entity\ReceptionLine;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\Vehicle;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\TruckArrivalLine;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\Zone;
use App\Helper\LanguageHelper;
use App\Service\LanguageService;
use App\Service\PackService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use EmptyIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

class SelectController extends AbstractController {
    #[Route("/select/emplacement", name: "ajax_select_locations", options: ["expose" => true])]
    public function locations(Request $request, EntityManagerInterface $manager): Response {
        $deliveryType = $request->query->get("deliveryType") ?? null;
        $collectType = $request->query->get("collectType") ?? null;
        $typeDispatchDropLocation = $request->query->get("typeDispatchDropLocation") ?? $request->query->get("typedispatchdroplocation") ?? null;
        $typeDispatchPickLocation = $request->query->get("typeDispatchPickLocation") ?? null;
        $term = $request->query->get("term");
        $addGroup = $request->query->getBoolean("add-group");

        $restrictedLocations = [];
        if($typeDispatchDropLocation) {
            $type = $manager->getRepository(Type::class)->find($typeDispatchDropLocation);
            $restrictedLocations = $type->getSuggestedDropLocations();
        } elseif($typeDispatchPickLocation) {
            $type = $manager->getRepository(Type::class)->find($typeDispatchPickLocation);
            $restrictedLocations = $type->getSuggestedPickLocations();
        }
        $locations = $manager->getRepository(Emplacement::class)->getForSelect(
            $term,
            [
                'deliveryType' => $deliveryType,
                'collectType' => $collectType,
                'idPrefix' => $addGroup ? 'location:' : '',
                'restrictedLocations' => $restrictedLocations,
            ]
        );

        $results = $locations;
        if($addGroup) {
            $locationGroups = $manager->getRepository(LocationGroup::class)->getForSelect($term);
            $results = array_merge($locations, $locationGroups);
            usort($results, fn($a, $b) => strtolower($a['text']) <=> strtolower($b['text']));
        }

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route('/select/roundsDelivererPending', name: 'ajax_select_rounds_deliverer_pending', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    public function roundsDelivererPending(Request $request, EntityManagerInterface $manager): Response {
        $term = $request->query->get("term");

        $transportRound = $manager->getRepository(TransportRound::class)->getForSelect($term);

        return $this->json([
            "results" => $transportRound,
        ]);
    }

    #[Route("select/roles", name: "ajax_select_roles", options: ["expose" => true])]
    public function roles(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Role::class)->getForSelect(
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/types/services", name: "ajax_select_handling_type", options: ["expose" => true])]
    public function handlingType(Request $request, EntityManagerInterface $manager): Response {
        $alreadyDefinedTypes = $request->query->has('alreadyDefinedTypes')
            ? explode(",", $request->query->get('alreadyDefinedTypes'))
            : [];
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_HANDLING,
            $request->query->get("term"),
            ['alreadyDefinedTypes' => $alreadyDefinedTypes]
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/types/dispatches", name: "ajax_select_dispatch_type", options: ["expose" => true])]
    public function dispatchType(Request $request, EntityManagerInterface $manager): JsonResponse {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_DISPATCH,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }


    #[Route("/select/types/collectes", name: "ajax_select_collect_type", options: ["expose" => true])]
    public function collectType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_COLLECTE,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/types/references", name: "ajax_select_reference_type", options: ["expose" => true])]
    public function referenceType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::ARTICLE,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/types", name: "ajax_select_types", options: ["expose" => true])]
    public function types(Request                $request,
                          EntityManagerInterface $entityManager): Response {

        $typeRepository = $entityManager->getRepository(Type::class);
        $categoryType = $request->query->get('category');

        $alreadyDefinedTypes = [];
        if ($request->query->has('alreadyDefinedTypes')) {
            $alreadyDefinedTypes = explode(";", $request->query->get('alreadyDefinedTypes'));
        } else if ($request->query->has('types')) {
            $parameters = $request->query->all();
            if (is_array($parameters['types'] ?? null)) {
                $alreadyDefinedTypes = $request->query->all('types');
            } else {
                $alreadyDefinedTypes = [$request->query->get('types')];
            }
        }

        $allTypesOption = [];
        if($request->query->has('all-types-option') && $request->query->getBoolean('all-types-option') && !in_array('all', $alreadyDefinedTypes)) {
            $allTypesOption = [[
                'id' => 'all',
                'text' => 'Tous les types'
            ]];
        }

        $results = $typeRepository->getForSelect(
            $categoryType,
            term: $request->query->get("term"),
            options: $alreadyDefinedTypes ? ['alreadyDefinedTypes' => $alreadyDefinedTypes] : null
        );

        $results = array_merge($results, $allTypesOption);

        return $this->json([
            "results" => $results,
            "availableResults" => $typeRepository->countAvailableForSelect($categoryType, ['alreadyDefinedTypes' => $alreadyDefinedTypes]),
        ]);
    }

    #[Route("/select/statuts", name: "ajax_select_status", options: ["expose" => true])]
    public function status(Request $request, EntityManagerInterface $manager): Response {
        $type = $request->query->get("type") ?? $request->query->get("handlingType") ?? null;
        $results = $manager->getRepository(Statut::class)->getForSelect(
            $request->query->get("term"),
            $type
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/references", name: "ajax_select_references", options: ["expose" => true])]
    public function references(Request $request, EntityManagerInterface $manager): Response {
        $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $options = [
            'needsOnlyMobileSyncReference' => $request->query->getBoolean('needs-mobile-sync'),
            'type-quantity' => $request->query->get('type-quantity'),
            'status' => $request->query->get('status'),
            'active-only' => $request->query->getBoolean('active-only'),
            'ignoredDeliveryRequest' => $request->query->get('ignored-delivery-request'),
            'ignoredShippingRequest' => $request->query->get('ignored-shipping-request'),
            'minQuantity' => $request->query->get('min-quantity'), // TODO WIIS-9607 : a supprimer ?
            'multipleFields' => $request->query->getBoolean('multipleFields'),
            'visibilityGroup' => $request->query->get('visibilityGroup'),
            'filterFields' => $request->query->get('filterFields'),
        ];

        $results = Stream::from($referenceArticleRepository->getForSelect($request->query->get("term"), $user, $options));

        $redirectRoute = $request->query->get('redirect-route');
        $redirectParams = $request->query->get('redirect-route-params');
        if ($redirectRoute) {
            $results
                ->unshift([
                    "id" => "redirect-url",
                    "url" => $this->generateUrl('reference_article_new_page', [
                        "shipping" => 1,
                        'redirect-route' => $redirectRoute,
                        'redirect-route-params' => $redirectParams
                    ]),
                    "html" => "<div class='new-item-container'><span class='wii-icon wii-icon-plus'></span> <b>Nouvelle Référence</b></div>",
                ]);
        }

        return $this->json([
            "results" => $results->toArray(),
        ]);
    }

    #[Route("/select/unites-logistiques", name: "ajax_select_packs", options: ["expose" => true])]
    public function packs(Request $request, EntityManagerInterface $manager): Response {
        $limit = $request->query->get('limit') ?: 250;
        $results = $manager->getRepository(Pack::class)->getForSelect($request->query->get("term"), ['limit' => $limit]);
        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/nature", name: "ajax_select_natures", options: ["expose" => true])]
    public function natures(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Nature::class)->getForSelect($request->query->get("term"));
        return $this->json([
            "results" => $results,
        ]);
    }


    #[Route("/select/capteurs-bruts", name: "ajax_select_sensors", options: ["expose" => true])]
    public function sensors(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Sensor::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/groupe-de-visibilite", name: "ajax_select_visibility_group", options: ["expose" => true])]
    public function visibilityGroup(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(VisibilityGroup::class)->getForSelect($request->query->get("term"));
        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/utilisateur", name: "ajax_select_user", options: ["expose" => true])]
    public function user(Request $request, EntityManagerInterface $manager): Response {
        $addDropzone = $request->query->getBoolean("add-dropzone") ?? false;
        $delivererOnly = $request->query->getBoolean("deliverer-only") ?? false;
        $withPhoneNumber = $request->query->getBoolean("with-phone-numbers") ?? false;

        $results = $manager->getRepository(Utilisateur::class)->getForSelect(
            $request->query->get("term"),
            [
                "addDropzone" => $addDropzone,
                "delivererOnly" => $delivererOnly,
                "withPhoneNumber" => $withPhoneNumber,
            ]
        );
        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route("/select/capteurs", name: "ajax_select_sensor_wrappers", options: ["expose" => true])]
    public function getSensorWrappers(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(SensorWrapper::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/capteurs/sans-action", name: "ajax_select_sensor_wrappers_for_pairings", options: ["expose" => true])]
    public function getSensorWrappersForPairings(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(SensorWrapper::class)
            ->getForSelect($request->query->get("term"), true);

        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/unites-logistiques-sans-association", name: "ajax_select_packs_without_pairing", options: ["expose" => true])]
    public function packsWithoutPairing(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Pack::class)->findWithNoPairing($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/articles-sans-association", name: "ajax_select_articles_without_pairing", options: ["expose" => true])]
    public function articlesWithoutPairing(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Article::class)->findWithNoPairing($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/emplacements-sans-association", name: "ajax_select_locations_without_pairing", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function locationsWithoutPairing(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $locationGroups = $entityManager->getRepository(LocationGroup::class)->getWithNoAssociationForSelect($request->query->get("term"));
        $locations = $entityManager->getRepository(Emplacement::class)->getWithNoAssociationForSelect($request->query->get("term"));
        $allLocations = array_merge($locations, $locationGroups);
        usort($allLocations, fn($a, $b) => strtolower($a['text']) <=> strtolower($b['text']));

        return $this->json([
            'results' => $allLocations
        ]);
    }

    #[Route("/select/capteurs-sans-association", name: "ajax_select_sensors_without_pairing", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function sensorsWithoutPairings(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"),'name');
        $sensorWrapper = Stream::from($sensorWrapper)
            ->filter(function(SensorWrapper $wrapper) {
                return $wrapper->getPairings()->filter(function(Pairing $pairing) {
                    return $pairing->isActive();
                })->isEmpty();
            })
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getName(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }

    #[Route("/select/code-capteurs-sans-association", name: "ajax_select_sensors_code_without_pairing", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function sensorsWithoutPairingsCode(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'code');
        $sensorWrapper = Stream::from($sensorWrapper)
            ->filter(function(SensorWrapper $wrapper) {
                return $wrapper->getPairings()->filter(function(Pairing $pairing) {
                    return $pairing->isActive();
                })->isEmpty();
            })
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getSensor()->getCode(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }

    #[Route("/select/actionneur-code-capteurs-sans-association", name: "ajax_select_trigger_sensors_code_without_pairing", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function triggerSensorsCodeWithoutPairings(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'code',true);
        $sensorWrapper = Stream::from($sensorWrapper)
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getSensor()->getCode(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }


    #[Route("/select/actionneur-capteurs-sans-association", name: "ajax_select_trigger_sensors_without_pairing", options: ["expose" => true], methods: [self::GET, self::POST])]
    public function triggerSensorWithoutPairings(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'name', true);
        $sensorWrapper = Stream::from($sensorWrapper)
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getName(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }

    #[Route("/select/fournisseur-code", name: "ajax_select_supplier_code", options: ["expose" => true])]
    public function supplierByCode(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');
        $reference = $request->query->get('refArticle');

        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->getIdAndCodeBySearch($search, $reference);

        return $this->json(['results' => $fournisseur]);
    }

    #[Route("/select/fournisseur-label", name: "ajax_select_supplier_label", options: ["expose" => true])]
    public function supplierByLabel(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

        $fournisseurs = $fournisseurRepository->getIdAndLabelseBySearch($search);
        return $this->json([
            'results' => $fournisseurs
        ]);
    }

    #[Route("/select/articles-collectables", name: "ajax_select_collectable_articles", options: ["expose" => true])]
    public function collectableArticles(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');
        $reference = $entityManager->find(ReferenceArticle::class, $request->query->get('referenceArticle'));
        $articleRepository = $entityManager->getRepository(Article::class);
        $articles = $articleRepository->getCollectableArticlesForSelect($search, $reference);

        return $this->json([
            "results" => $articles
        ]);
    }

    #[Route("/select/references-par-acheteur", name: "ajax_select_references_by_buyer", options: ["expose" => true])]
    public function getPurchaseRequestForSelectByBuyer(EntityManagerInterface $entityManager): Response
    {
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $purchaseRequest = $purchaseRequestRepository->getPurchaseRequestForSelect($this->getUser());

        return $this->json([
            "results" => $purchaseRequest
        ]);
    }

    #[Route("/select/keyboard/pack", name: "ajax_select_keyboard_pack", options: ["expose" => true])]
    public function keyboardPack(Request                $request,
                                 EntityManagerInterface $manager,
                                 PackService            $packService,
                                 SettingsService        $settingsService): JsonResponse|StreamedJsonResponse {
        $packRepository = $manager->getRepository(Pack::class);
        $packMustBeNew = $settingsService->getValue($manager, Setting::PACK_MUST_BE_NEW);

        $packCode = $request->query->get("term");
        if($request->query->has("searchPrefix")) {
            $packCode = $request->query->get("searchPrefix") . $packCode;
        }

        if($packMustBeNew) {
            if($packRepository->findOneBy(["code" => $packCode])) {
                return new JsonResponse([
                    "error" => "Cette unité logistique existe déjà en base de données",
                ]);
            } else {
                $results = new EmptyIterator();
            }
        } else {
            $limit = $request->query->get('limit') ?: 1000;
            $results = $packRepository->iterateForSelect(
                $packCode,
                [
                    'exclude'=> $request->query->all("pack"),
                    'limit' => $limit,
                ]
            );
        }

        return new StreamedJsonResponse([
            "results" => $packService->getFormatedKeyboardPackGenerator($results) ?? [],
            "error" => $error ?? null,
        ]);

    }

    #[Route("/select/business-unit",name: "ajax_select_business_unit",options: ["expose" => true], methods: ["GET"])]
    public function businessUnit(Request $request, EntityManagerInterface $manager): Response {
        $page = $request->query->get('page');

        $fixedFieldRepository = $manager->getRepository(in_array($page, FixedField::ENTITY_CODES_MANAGE_BY_TYPE) ? FixedFieldByType::class : FixedFieldStandard::class);

        $businessUnitValues = $fixedFieldRepository->getElements($page, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT);

        $results = Stream::from($businessUnitValues)
            ->map(fn(string $value) => [
                'id' => $value,
                'text' => $value
            ])
            ->toArray();

        return $this->json([
            'results' => $results
        ]);
    }

    #[Route("/select/carrier", name: "ajax_select_carrier", options: ["expose" => true])]
    public function carrier(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');
        $carriers = $entityManager->getRepository(Transporteur::class)->getForSelect($search);

        return $this->json([
            "results" => $carriers
        ]);
    }

    #[Route("/select/vehicles", name: "ajax_select_vehicles", options: ["expose" => true])]
    public function vehicles(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $vehicles = $entityManager->getRepository(Vehicle::class)->getForSelect($search);

        return $this->json([
            "results" => $vehicles
        ]);
    }

    #[Route("/select/categories-inventaire", name: "ajax_select_inventory_categories", options: ["expose" => true])]
    public function inventoryCategories(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $vehicles = $entityManager->getRepository(InventoryCategory::class)->getForSelect($search);

        return $this->json([
            "results" => $vehicles
        ]);
    }

    #[Route("/select/dispatch-packs", name: "ajax_select_dispatch_packs", options: ["expose" => true])]
    public function dispatchPacks(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $dispatchId = $request->query->get("dispatch-id");

        $search = $request->query->get('term');
        $packs = $entityManager->getRepository(Pack::class)->getForSelect($search, ['dispatchId' => $dispatchId]);

        return $this->json([
            "results" => $packs
        ]);
    }

    #[Route("/select/project", name: "ajax_select_project", options: ["expose" => true])]
    public function project(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $projects = $entityManager->getRepository(Project::class)->getForSelect($search);

        return $this->json([
            "results" => $projects
        ]);
    }

    #[Route("/select/zones", name: "ajax_select_zones", options: ["expose" => true])]
    public function zones(Request $request, EntityManagerInterface $entityManager): Response {
        $zones = $entityManager->getRepository(Zone::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $zones
        ]);
    }

    #[Route("/select/articles", name: "ajax_select_articles", options: ["expose" => true])]
    public function articles(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Article::class)->getForSelect($request->query->get("term"), null, $request->query->get("reference-new-mvt"));
        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/reception-logistic-units", name: "ajax_select_reception_logistic_units", options: ["expose" => true])]
    public function receptionLogisticUnits(Request $request,
                                           EntityManagerInterface $entityManager): Response {

        $options = [];

        if (!$request->query->getBoolean("all")) {
            $options["reference"] = $request->query->get("reference");
            $options["order-number"] = $request->query->get("order-number");
            $options["include-empty"] = true;
        }

        $results = $entityManager->getRepository(ReceptionLine::class)->getForSelectFromReception(
            $request->query->get("term"),
            $request->query->get("reception"),
            $options
        );

        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/delivery-logistic-units", name: "ajax_select_delivery_logistic_units", options: ["expose" => true])]
    public function deliveryLogisticUnits(Request $request, EntityManagerInterface $entityManager): Response {
        $fieldsParamRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $projectField = $fieldsParamRepository->findOneByEntityAndCode(FixedFieldStandard::ENTITY_CODE_DEMANDE, FixedFieldStandard::FIELD_CODE_DELIVERY_REQUEST_PROJECT);

        $results = $entityManager->getRepository(Pack::class)->getForSelectFromDelivery(
            $request->query->get("term"),
            $request->query->get("delivery"),
            (!$projectField->isDisplayedCreate() || !$projectField->isRequiredCreate())
            && (!$projectField->isDisplayedEdit() || !$projectField->isRequiredEdit())
        );

        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/articles-disponibles", name: "ajax_select_available_articles", options: ["expose" => true])]
    public function availableArticles(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Article::class)->getForSelect($request->query->get("term"), Article::STATUT_ACTIF);

        return $this->json([
            "results" => $results
        ]);
    }

    #[Route("/select/customers", name: "ajax_select_customers", options: ["expose" => true])]
    public function customers(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $customers = $entityManager->getRepository(Customer::class)->getForSelect($search);
        array_unshift($customers, [
            "id" => "new-item",
            "html" => "<div class='new-item-container'><span class='wii-icon wii-icon-plus'></span> <b>Nouveau client</b></div>",
        ]);

        return $this->json([
            "results" => $customers
        ]);
    }

    #[Route("/select/nature-or-type", name: "ajax_select_nature_or_type", options: ["expose" => true])]
    public function natureOrType(Request $request, EntityManagerInterface $entityManager): Response {
        $module = $request->query->get("module");
        $term = $request->query->get("term");

        $naturesOrTypes = $module === CategoryType::ARRIVAGE ?
            $entityManager->getRepository(Nature::class)->getForSelect($term) :
            $entityManager->getRepository(Type::class)->getForSelect(CategoryType::ARTICLE, $term);


        return $this->json([
            "results" => $naturesOrTypes,
        ]);
    }

    #[Route("/select/provider", name: "ajax_select_provider", options: ["expose" => true])]
    public function provider(Request $request,
                                   EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');

        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->getIdAndCodeBySearch($search);

        return $this->json(['results' => $fournisseur]);
    }

    #[Route("/select/native-countries", name: "ajax_select_native_countries", options: ["expose" => true])]
    public function nativeCountries(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $nativeCountries = $entityManager->getRepository(NativeCountry::class)->getForSelect($search);

        return $this->json([
            "results" => $nativeCountries
        ]);
    }

    #[Route("/select/supplier-articles", name: "ajax_select_supplier_articles", options: ["expose" => true])]
    public function supplierArticles(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');
        $supplier = $request->query->get('fournisseur');
        $referenceArticle = $request->query->get('refArticle');

        $supplierArticles = $entityManager->getRepository(ArticleFournisseur::class)->getForSelect($search, [
            'supplier' => $supplier,
            'referenceArticle' => $referenceArticle
        ]);

        return $this->json([
            "results" => $supplierArticles
        ]);
    }

    #[Route('/select/driver', name: 'ajax_select_driver', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    public function driver(Request $request, EntityManagerInterface $manager): Response {
        $term = $request->query->get("term");
        $carrierId = $request->query->get("carrier");

        $drivers = $manager->getRepository(Chauffeur::class)->getForSelect($term, [
            'carrierId' => $carrierId
        ]);

        return $this->json([
            "results" => $drivers,
        ]);
    }

    #[Route('/select/truck-arrival-line-number', name: 'ajax_select_truck_arrival_line', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    public function truckArrivalLineNumber(Request $request, EntityManagerInterface $manager): Response {
        $term = $request->query->get("term");
        $carrierId = $request->query->get("carrier-id") ?? $request->query->get("transporteur");
        $truckArrivalId = $request->query->get("truck-arrival-id") ?? $request->query->get("noTruckArrival");
        $newItem = $request->query->get("new-item");
        $lines = $manager->getRepository(TruckArrivalLine::class)->getForSelect($term, ['carrierId' =>  $carrierId, 'truckArrivalId' => $truckArrivalId]);

        if($newItem && $term){
            array_unshift($lines, [
                "id" => "new-item",
                "html" => "<div class='new-item-container'><span class='wii-icon wii-icon-plus'></span> <b>Nouveau numéro tracking</b></div>",
            ]);
        }

        return $this->json([
            "results" => $lines,
        ]);
    }

    #[Route('/select/location-with-group', name: 'ajax_select_location_with_group', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::DEM, Action::DISPLAY_ACHE], mode: HasPermission::IN_JSON)]
    public function locationWithGroup(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $locationGroups = $entityManager->getRepository(LocationGroup::class)->getWithGroupsForSelect($request->query->get("term"));
        $locations = $entityManager->getRepository(Emplacement::class)->getWithGroupsForSelect($request->query->get("term"));
        $allLocations = Stream::from($locations, $locationGroups)
            ->sort(static fn($a, $b) => strtolower($a['text']) <=> strtolower($b['text']))
            ->toArray();

        return $this->json([
            'results' => $allLocations
        ]);
    }

    #[Route('/select/types/production', name: 'ajax_select_production_request_type', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PRODUCTION, Action::DISPLAY_PRODUCTION_REQUEST], mode: HasPermission::IN_JSON)]
    public function productionRequestType(Request $request, EntityManagerInterface $manager, LanguageService $languageService): Response {
        $defaultSlug = LanguageHelper::clearLanguage($languageService->getDefaultSlug());
        $defaultLanguage = $manager->getRepository(Language::class)->findOneBy(['slug' => $defaultSlug]);
        $language = $this->getUser()->getLanguage() ?: $defaultLanguage;
        $withDropLocation = $request->query->getBoolean('with-drop-location');

        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::PRODUCTION,
            $request->query->get("term"),
            [
                "countStatuses" => true,
                "language" => $language,
                "defaultLanguage" => $defaultLanguage,
                "withDropLocation" => $withDropLocation,
            ]
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    #[Route('/select/truck-arrival', name: 'ajax_select_truck_arrival', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    public function truckArrival(Request $request, EntityManagerInterface $manager): JsonResponse {
        $truckArrivalRepository = $manager->getRepository(TruckArrival::class);

        $term = $request->query->get("term");
        $carrierId = $request->query->getInt("carrier-id") ?? $request->query->get("transporteur");
        $truckArrivalId = $request->query->getInt("truck-arrival-id");

        $lines = $truckArrivalRepository->getForSelect($term, ['carrierId' =>  $carrierId, 'truckArrivalId' => $truckArrivalId]);

        return $this->json([
            "results" => $lines,
        ]);
    }

    #[Route('/select/free-field', name: 'ajax_select_free_field', options: ['expose' => true], methods: self::GET, condition: self::IS_XML_HTTP_REQUEST)]
    public function freeField(Request $request, EntityManagerInterface $entityManager): JsonResponse {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);

        $term = $request->query->get("term", "");
        $category = $request->query->get("category-ff");
        $freeFields = $freeFieldRepository->getForSelect($term, $category);
        return $this->json([
            "results" => $freeFields,
        ]);
    }
}
