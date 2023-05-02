<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategoryType;
use App\Entity\Customer;
use App\Entity\Chauffeur;
use App\Entity\Dispatch;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\LocationGroup;
use App\Entity\NativeCountry;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Project;
use App\Entity\ReceptionLine;
use App\Entity\Setting;
use App\Entity\PurchaseRequest;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\Vehicle;
use App\Entity\Transporteur;
use App\Entity\TruckArrivalLine;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\Zone;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

class SelectController extends AbstractController {

    /**
     * @Route("/select/emplacement", name="ajax_select_locations", options={"expose": true})
     */
    public function locations(Request $request, EntityManagerInterface $manager): Response {
        $deliveryType = $request->query->get("deliveryType") ?? null;
        $collectType = $request->query->get("collectType") ?? null;
        $term = $request->query->get("term");
        $addGroup = $request->query->getBoolean("add-group");

        $locations = $manager->getRepository(Emplacement::class)->getForSelect(
            $term,
            [
                'deliveryType' => $deliveryType,
                'collectType' => $collectType,
                'idPrefix' => $addGroup ? 'location:' : ''
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

    /**
     * @Route("/select/roles", name="ajax_select_roles", options={"expose": true})
     */
    public function roles(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Role::class)->getForSelect(
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/services", name="ajax_select_handling_type", options={"expose": true})
     */
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

    /**
     * @Route("/select/types/dispatches", name="ajax_select_dispatch_type", options={"expose": true})
     */
    public function dispatchType(Request $request, EntityManagerInterface $manager): JsonResponse {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_DISPATCH,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/livraisons", name="ajax_select_delivery_type", options={"expose": true})
     */
    public function deliveryType(Request $request, EntityManagerInterface $manager): Response {
        $alreadyDefinedTypes = [];
        if($request->query->has('alreadyDefinedTypes')) {
            $alreadyDefinedTypes = explode(";", $request->query->get('alreadyDefinedTypes'));
        } else if($request->query->has('deliveryType')) {
            $parameters = $request->query->all();
            if (is_array($parameters['deliveryType'] ?? null)) {
                $alreadyDefinedTypes = $request->query->all('deliveryType');
            }
            else {
                $alreadyDefinedTypes = [$request->query->get('deliveryType')];
            }
        }

        $allTypesOption = [];
        if($request->query->has('all-types-option') && $request->query->getBoolean('all-types-option') && !in_array('all', $alreadyDefinedTypes)) {
            $allTypesOption = [[
                'id' => 'all',
                'text' => 'Tous les types'
            ]];
        }

        $typeRepository = $manager->getRepository(Type::class);
        $results = $typeRepository->getForSelect(
            CategoryType::DEMANDE_LIVRAISON,
            $request->query->get("term"),
            ['alreadyDefinedTypes' => $alreadyDefinedTypes]
        );

        $results = array_merge($results, $allTypesOption);

        return $this->json([
            "results" => $results,
            "availableResults" => $typeRepository->countAvailableForSelect(CategoryType::DEMANDE_LIVRAISON, ['alreadyDefinedTypes' => $alreadyDefinedTypes]),
        ]);
    }

    /**
     * @Route("/select/types/collectes", name="ajax_select_collect_type", options={"expose": true})
     */
    public function collectType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_COLLECTE,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/references", name="ajax_select_reference_type", options={"expose": true})
     */
    public function referenceType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::ARTICLE,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types", name="ajax_select_types", options={"expose": true})
     */
    public function types(Request                $request,
                          EntityManagerInterface $entityManager): Response {
        $typeRepository = $entityManager->getRepository(Type::class);

        $categoryType = $request->query->get('categoryType');

        $results = $typeRepository->getForSelect(
            $categoryType,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/statuts", name="ajax_select_status", options={"expose": true})
     */
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

    /**
     * @Route("/select/references", name="ajax_select_references", options={"expose": true})
     */
    public function references(Request $request, EntityManagerInterface $manager): Response {
        $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $options = [
            'needsOnlyMobileSyncReference' => $request->query->getBoolean('needs-mobile-sync'),
            'type-quantity' => $request->query->get('type-quantity'),
            'status' => $request->query->get('status'),
            'demandeId' => $request->query->get('demandeId'),
        ];

        $results = $referenceArticleRepository->getForSelect($request->query->get("term"), $user, $options);

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/unites-logistiques", name="ajax_select_packs", options={"expose": true})
     */
    public function packs(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Pack::class)->getForSelect($request->query->get("term"));
        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/nature", name="ajax_select_natures", options={"expose": true})
     */
    public function natures(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Nature::class)->getForSelect($request->query->get("term"));
        return $this->json([
            "results" => $results,
        ]);
    }


    /**
     * @Route("/select/capteurs-bruts", name="ajax_select_sensors", options={"expose": true})
     */
    public function sensors(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Sensor::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/groupe-de-visibilite", name="ajax_select_visibility_group", options={"expose"=true})
     */
    public function visibilityGroup(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(VisibilityGroup::class)->getForSelect($request->query->get("term"));
        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/utilisateur", name="ajax_select_user", options={"expose"=true})
     */
    public function user(Request $request, EntityManagerInterface $manager): Response {
        $addDropzone = $request->query->getBoolean("add-dropzone") ?? false;
        $delivererOnly = $request->query->getBoolean("deliverer-only") ?? false;

        $results = $manager->getRepository(Utilisateur::class)->getForSelect(
            $request->query->get("term"),
            [
                "addDropzone" => $addDropzone,
                "delivererOnly" => $delivererOnly
            ]
        );
        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/capteurs", name="ajax_select_sensor_wrappers", options={"expose"=true})
     */
    public function getSensorWrappers(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(SensorWrapper::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/capteurs/sans-action", name="ajax_select_sensor_wrappers_for_pairings", options={"expose"=true})
     */
    public function getSensorWrappersForPairings(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(SensorWrapper::class)
            ->getForSelect($request->query->get("term"), true);

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/unites-logistiques-sans-association", name="ajax_select_packs_without_pairing", options={"expose"=true})
     */
    public function packsWithoutPairing(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Pack::class)->findWithNoPairing($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/articles-sans-association", name="ajax_select_articles_without_pairing", options={"expose"=true})
     */
    public function articlesWithoutPairing(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Article::class)->findWithNoPairing($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/emplacements-sans-association", name="ajax_select_locations_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function locationsWithoutPairing(Request $request, EntityManagerInterface $entityManager){
        $locationGroups = $entityManager->getRepository(LocationGroup::class)->getWithNoAssociationForSelect($request->query->get("term"));
        $locations = $entityManager->getRepository(Emplacement::class)->getWithNoAssociationForSelect($request->query->get("term"));
        $allLocations = array_merge($locations, $locationGroups);
        usort($allLocations, fn($a, $b) => strtolower($a['text']) <=> strtolower($b['text']));

        return $this->json([
            'results' => $allLocations
        ]);
    }

    /**
     * @Route("/select/capteurs-sans-association", name="ajax_select_sensors_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function sensorsWithoutPairings(Request $request, EntityManagerInterface $entityManager){
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
    /**
     * @Route("/select/code-capteurs-sans-association", name="ajax_select_sensors_code_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function sensorsWithoutPairingsCode(Request $request, EntityManagerInterface $entityManager){
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
    /**
     * @Route("/select/actionneur-code-capteurs-sans-association", name="ajax_select_trigger_sensors_code_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function triggerSensorsCodeWithoutPairings(Request $request, EntityManagerInterface $entityManager){
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'code',true);
        $sensorWrapper = Stream::from($sensorWrapper)
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getSensor()->getCode(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }

    /**
     * @Route("/select/actionneur-capteurs-sans-association", name="ajax_select_trigger_sensors_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function triggerSensorWithoutPairings(Request $request, EntityManagerInterface $entityManager){
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'name', true);
        $sensorWrapper = Stream::from($sensorWrapper)
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getName(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }

    /**
     * @Route("/select/fournisseur-code", name="ajax_select_supplier_code", options={"expose"=true})
     */
    public function supplierByCode(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get('term');
        $reference = $request->query->get('refArticle');

        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->getIdAndCodeBySearch($search, $reference);

        return $this->json(['results' => $fournisseur]);
    }

    /**
     * @Route("/select/fournisseur-label", name="ajax_select_supplier_label", options={"expose"=true})
     */
    public function supplierByLabel(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);

        $fournisseurs = $fournisseurRepository->getIdAndLabelseBySearch($search);
        return $this->json([
            'results' => $fournisseurs
        ]);
    }

    /**
     * @Route("/select/articles-collectables", name="ajax_select_collectable_articles", options={"expose"=true})
     */
    public function collectableArticles(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');
        $reference = $entityManager->find(ReferenceArticle::class, $request->query->get('referenceArticle'));
        $articleRepository = $entityManager->getRepository(Article::class);
        $articles = $articleRepository->getCollectableArticlesForSelect($search, $reference);

        return $this->json([
            "results" => $articles
        ]);
    }

    /**
     * @Route("/select/references-par-acheteur", name="ajax_select_references_by_buyer", options={"expose"=true})
     */
    public function getPurchaseRequestForSelectByBuyer(EntityManagerInterface $entityManager): Response
    {
        $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
        $purchaseRequest = $purchaseRequestRepository->getPurchaseRequestForSelect($this->getUser());

        return $this->json([
            "results" => $purchaseRequest
        ]);
    }

    /**
     * @Route("/select/keyboard/pack", name="ajax_select_keyboard_pack", options={"expose"=true})
     */
    public function keyboardPack(Request $request, EntityManagerInterface $manager): Response
    {
        $settingsRepository = $manager->getRepository(Setting::class);
        $packRepository = $manager->getRepository(Pack::class);
        $packMustBeNew = $settingsRepository->getOneParamByLabel(Setting::PACK_MUST_BE_NEW);

        $packCode = $request->query->get("term");
        if($request->query->has("searchPrefix")) {
            $packCode = $request->query->get("searchPrefix") . $packCode;
        }

        if($packMustBeNew) {
            if($packRepository->findOneBy(["code" => $packCode])) {
                return $this->json([
                    "error" => "Cette unité logistique existe déjà en base de données"
                ]);
            } else {
                $results = [];
            }
        } else {
            $results = $packRepository->getForSelect(
                $packCode,
                ['exclude'=> $request->query->all("pack")]
            );
            foreach($results as &$result) {
                $result["stripped_comment"] = strip_tags($result["comment"]);
                $result["lastMvtDate"] = FormatHelper::datetime(DateTime::createFromFormat('d/m/Y H:i', $result['lastMvtDate']) ?: null, "", false, $this->getUser());
            }
        }

        array_unshift($results, [
            "id" => "new-item",
            "html" => "<div class='new-item-container'><span class='wii-icon wii-icon-plus'></span> <b>Nouvelle unité logistique</b></div>",
        ]);

        if(isset($results[1])) {
            $results[1]["highlighted"] = true;
        } else {
            $results[0]["highlighted"] = true;

            if(!$packMustBeNew) {
                $results[1] = [
                    "id" => "no-result",
                    "text" => "Aucun résultat",
                    "disabled" => true,
                ];
            }
        }
        return $this->json([
            "results" => $results ?? null,
            "error" => $error ?? null,
        ]);
    }

    /**
     * @Route("/select/business-unit", name="ajax_select_business_unit", options={"expose"=true})
     */
    public function businessUnit(Request $request, EntityManagerInterface $manager): Response {
        $page = $request->query->get('page');

        $businessUnitValues = $manager
            ->getRepository(FieldsParam::class)
            ->getElements($page, FieldsParam::FIELD_CODE_BUSINESS_UNIT);

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

    /**
     * @Route("/select/carrier", name="ajax_select_carrier", options={"expose"=true})
     */
    public function carrier(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');
        $carriers = $entityManager->getRepository(Transporteur::class)->getForSelect($search);

        return $this->json([
            "results" => $carriers
        ]);
    }

    /**
     * @Route("/select/vehicles", name="ajax_select_vehicles", options={"expose": true})
     */
    public function vehicles(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $vehicles = $entityManager->getRepository(Vehicle::class)->getForSelect($search);

        return $this->json([
            "results" => $vehicles
        ]);
    }

    /**
     * @Route("/select/categories-inventaire", name="ajax_select_inventory_categories", options={"expose": true})
     */
    public function inventoryCategories(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $vehicles = $entityManager->getRepository(InventoryCategory::class)->getForSelect($search);

        return $this->json([
            "results" => $vehicles
        ]);
    }

    /**
     * @Route("/select/dispatch-packs", name="ajax_select_dispatch_packs", options={"expose"=true})
     */
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


    /**
     * @Route("/select/project", name="ajax_select_project", options={"expose": true})
     */
    public function project(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $projects = $entityManager->getRepository(Project::class)->getForSelect($search);

        return $this->json([
            "results" => $projects
        ]);
    }

    /**
     * @Route("/select/zones", name="ajax_select_zones", options={"expose": true})
     */
    public function zones(Request $request, EntityManagerInterface $entityManager): Response {
        $zones = $entityManager->getRepository(Zone::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $zones
        ]);
    }

    /**
     * @Route("/select/articles", name="ajax_select_articles", options={"expose"=true})
     */
    public function articles(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Article::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/reception-logistic-units", name="ajax_select_reception_logistic_units", options={"expose"=true})
     */
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

    /**
     * @Route("/select/delivery-logistic-units", name="ajax_select_delivery_logistic_units", options={"expose"=true})
     */
    public function deliveryLogisticUnits(Request $request, EntityManagerInterface $entityManager): Response {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $projectField = $fieldsParamRepository->findByEntityAndCode(FieldsParam::ENTITY_CODE_DEMANDE, FieldsParam::FIELD_CODE_PROJECT);

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

    /**
     * @Route("/select/articles-disponibles", name="ajax_select_available_articles", options={"expose"=true})
     */
    public function availableArticles(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Article::class)->getForSelect($request->query->get("term"), Article::STATUT_ACTIF);

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/customers", name="ajax_select_customers", options={"expose": true})
     */
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

    /**
     * @Route("/select/nature-or-type", name="ajax_select_nature_or_type", options={"expose": true})
     */
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

    /**
     * @Route("/select/provider", name="ajax_select_provider", options={"expose"=true})
     */
    public function provider(Request $request,
                                   EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');

        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->getIdAndCodeBySearch($search);

        return $this->json(['results' => $fournisseur]);
    }

    /**
     * @Route("/select/native-countries", name="ajax_select_native_countries", options={"expose": true})
     */
    public function nativeCountries(Request $request, EntityManagerInterface $entityManager): Response {
        $search = $request->query->get("term");
        $nativeCountries = $entityManager->getRepository(NativeCountry::class)->getForSelect($search);

        return $this->json([
            "results" => $nativeCountries
        ]);
    }

    /**
     * @Route("/select/supplier-articles", name="ajax_select_supplier_articles", options={"expose": true})
     */
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

        $drivers = $manager->getRepository(Chauffeur::class)->getForSelect($term);

        return $this->json([
            "results" => $drivers,
        ]);
    }

    #[Route('/select/truck-arrival-line-number', name: 'ajax_select_truck_arrival_line', options: ['expose' => true], methods: 'GET', condition: 'request.isXmlHttpRequest()')]
    public function truckArrivalLineNumber(Request $request, EntityManagerInterface $manager): Response {
        $term = $request->query->get("term");
        $carrierId = $request->query->get("carrier-id") ?? $request->query->get("transporteur");
        $truckArrivalId = $request->query->get("truck-arrival-id");

        $lines = $manager->getRepository(TruckArrivalLine::class)->getForSelect($term, ['carrierId' =>  $carrierId, 'truckArrivalId' => $truckArrivalId]);
        return $this->json([
            "results" => $lines,
        ]);
    }
}
