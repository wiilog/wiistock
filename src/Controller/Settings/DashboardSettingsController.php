<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dashboard;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\DashboardSettingsService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route('/parametrage-global/dashboard')]
class DashboardSettingsController extends AbstractController {

    #[Route('/', name: 'dashboard_settings', methods: ['GET'])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_DASHBOARD])]
    public function settings(DashboardSettingsService $dashboardSettingsService,
                             EntityManagerInterface   $entityManager): Response {
        $componentTypeRepository = $entityManager->getRepository(Dashboard\ComponentType::class);
        $componentTypes = $componentTypeRepository->findAll();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $orderedComponentTypes = [];
        foreach($componentTypes as $componentType) {
            $category = $componentType->getCategory();
            if(!isset($orderedComponentTypes[$category])) {
                $orderedComponentTypes[$category] = [];
            }

            $orderedComponentTypes[$category][] = $componentType;
        }

        $sortedCategories = array_keys(Dashboard\ComponentType::COMPONENT_ORDER);

        uksort($orderedComponentTypes, function(string $a, string $b) use ($sortedCategories) {
            return array_search($a, $sortedCategories) <=> array_search($b, $sortedCategories);
        });

        foreach($orderedComponentTypes as $category => &$categoryComponentTypes) {
            usort($categoryComponentTypes, function(Dashboard\ComponentType $a, Dashboard\ComponentType $b) use ($category) {
                return array_search($a->getMeterKey(), Dashboard\ComponentType::COMPONENT_ORDER[$category])
                    <=> array_search($b->getMeterKey(), Dashboard\ComponentType::COMPONENT_ORDER[$category]);
            });
        }

        return $this->render("dashboard/settings.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($entityManager, $loggedUser, DashboardSettingsService::MODE_EDIT),
            "token" => $_SERVER["APP_DASHBOARD_TOKEN"],
            "componentTypeConfig" => [
                // component types group by category
                "componentTypes" => $orderedComponentTypes,
            ]
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @return Response
     */
    #[Route('/save', name: 'save_dashboard_settings', options: ['expose' => true], methods: ['POST'])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_DASHBOARD], mode: HasPermission::IN_JSON)]
    public function save(Request                  $request,
                         EntityManagerInterface   $entityManager,
                         DashboardSettingsService $dashboardSettingsService): Response {
        $dashboards = json_decode($request->request->get("dashboards"), true);
        try {
            $dashboardSettingsService->save($entityManager, $dashboards);
        } catch(InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $componentCountExceededCode = DashboardSettingsService::COMPONENT_COUNT_EXCEEDED;
            if (preg_match("/$componentCountExceededCode-(.*)/", $message, $matches)) {
                $componentLimit = $matches[1] ?? '';
                throw new FormException("Vous avez un trop grand nombre de composant sur vos dashboards, la limite est de $componentLimit");
            }
            else {
                $unknownComponentCode = DashboardSettingsService::UNKNOWN_COMPONENT;
                if (preg_match("/$unknownComponentCode-(.*)/", $message, $matches)) {
                    $unknownComponentLabel = $matches[1] ?? '';
                    throw new FormException("Type de composant {$unknownComponentLabel} inconnu");
                }
                else {
                    $invalidSegmentsEntry = DashboardSettingsService::INVALID_SEGMENTS_ENTRY;
                    if (preg_match("/$invalidSegmentsEntry-(.*)/", $message, $matches)) {
                        $title = $matches[1] ?? '';
                        throw new FormException('Les valeurs de segments renseignées pour le composant "' . $title . '" ne sont pas valides');
                    }
                    else {
                        throw $exception;
                    }
                }
            }
        }

        $entityManager->flush();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        return $this->json([
            "success" => true,
            "dashboards" => $dashboardSettingsService->serialize($entityManager, $loggedUser, DashboardSettingsService::MODE_EDIT),
        ]);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\ComponentType $componentType
     * @return Response
     */
    #[Route('/api-component-type/{componentType}', name: 'dashboard_component_type_form', methods: ['POST'], options: ['expose' => true])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_DASHBOARD], mode: HasPermission::IN_JSON)]
    public function apiComponentTypeForm(Request                 $request,
                                         EntityManagerInterface  $entityManager,
                                         Dashboard\ComponentType $componentType,
                                         TranslationService      $translationService): Response {
        $templateName = $componentType->getTemplate();

        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $languageRepository = $entityManager->getRepository(Language::class);
        $fixedFieldByTypeRepository = $entityManager->getRepository(FixedFieldByType::class);

        $dispatchEmergencies = $fixedFieldByTypeRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_EMERGENCY);

        $values = json_decode($request->request->get('values'), true);
        $values += [ //default values should be initialized here
            "locations" => [],
            "firstOriginLocation" => [],
            "secondOriginLocation" => [],
            "firstDestinationLocation" => [],
            "secondDestinationLocation" => [],
            "carriers" => [],
            "arrivalTypes" => [],
            "handlingTypes" => [],
            "dispatchTypes" => [],
            "productionTypes" => [],
            "referenceTypes" => [],
            "managers" => [],
            "arrivalStatuses" => [],
            "handlingStatuses" => [],
            "deliveryOrderStatuses" => [],
            "deliveryOrderTypes" => [],
            "displayDeliveryOrderContent" => null,
            "displayDeliveryOrderWithExpectedDate" => null,
            "dispatchStatuses" => [],
            "productionStatuses" => [],
            "entityTypes" => [],
            "separateType" => false,
            "stackValues" => false,
            "entityStatuses" => [],
            "entity" => '',
            "treatmentDelay" => null,
            "natures" => [],
            "tooltip" => $componentType->getHint(),
            "pickLocations" => [],
            "dropLocations" => [],
            "dispatchEmergencies" => [],
            "disputeTypes" => [],
            "disputeStatuses" => [],
            "disputeEmergency" => false,
            "treatmentDelayType" => null,
            "trackingDelayLessThan" => null,
        ];
        $entities = [];
        $entityTypes = [];
        $entityStatuses = [];

        if (in_array($componentType->getMeterKey(), [
            Dashboard\ComponentType::REQUESTS_TO_TREAT,
            Dashboard\ComponentType::ORDERS_TO_TREAT,
            Dashboard\ComponentType::PENDING_REQUESTS,
        ])) {
            if (in_array($componentType->getMeterKey(), [
                Dashboard\ComponentType::PENDING_REQUESTS,
                Dashboard\ComponentType::REQUESTS_TO_TREAT,
            ])) {
                $entities = [
                    'Service' => [
                        'categoryType' => CategoryType::DEMANDE_HANDLING,
                        'categoryStatus' => CategorieStatut::HANDLING,
                        'key' => Dashboard\ComponentType::REQUESTS_TO_TREAT_HANDLING
                    ],
                    'Collecte' => [
                        'categoryType' => CategoryType::DEMANDE_COLLECTE,
                        'categoryStatus' => CategorieStatut::DEM_COLLECTE,
                        'key' => Dashboard\ComponentType::REQUESTS_TO_TREAT_COLLECT
                    ],
                    'Livraison' => [
                        'categoryType' => CategoryType::DEMANDE_LIVRAISON,
                        'categoryStatus' => CategorieStatut::DEM_LIVRAISON,
                        'key' => Dashboard\ComponentType::REQUESTS_TO_TREAT_DELIVERY
                    ],
                    'Acheminement' => [
                        'categoryType' => CategoryType::DEMANDE_DISPATCH,
                        'categoryStatus' => CategorieStatut::DISPATCH,
                        'key' => Dashboard\ComponentType::REQUESTS_TO_TREAT_DISPATCH
                    ],
                    'Transfert' => [
                        'categoryType' => CategoryType::TRANSFER_REQUEST,
                        'categoryStatus' => CategorieStatut::TRANSFER_REQUEST,
                        'key' => Dashboard\ComponentType::REQUESTS_TO_TREAT_TRANSFER
                    ],
                    'Expédition' => [
                        'categoryType' => CategoryType::SHIPPING_REQUEST,
                        'categoryStatus' => CategorieStatut::SHIPPING_REQUEST,
                        'key' => Dashboard\ComponentType::REQUESTS_TO_TREAT_SHIPPING
                    ],
                    'Production' => [
                        'categoryType' => CategoryType::PRODUCTION,
                        'categoryStatus' => CategorieStatut::PRODUCTION,
                        'key' => Dashboard\ComponentType::REQUESTS_TO_TREAT_PRODUCTION,
                    ]
                ];
            }
            else {
                $entities = [
                    'Collecte' => [
                        'categoryType' => CategoryType::DEMANDE_COLLECTE,
                        'categoryStatus' => CategorieStatut::ORDRE_COLLECTE,
                        'key' => Dashboard\ComponentType::ORDERS_TO_TREAT_COLLECT
                    ],
                    'Livraison' => [
                        'categoryType' => CategoryType::DEMANDE_LIVRAISON,
                        'categoryStatus' => CategorieStatut::ORDRE_LIVRAISON,
                        'key' => Dashboard\ComponentType::ORDERS_TO_TREAT_DELIVERY
                    ],
                    'Préparation' => [
                        'categoryType' => CategoryType::DEMANDE_LIVRAISON,
                        'categoryStatus' => CategorieStatut::PREPARATION,
                        'key' => Dashboard\ComponentType::ORDERS_TO_TREAT_PREPARATION
                    ],
                    'Transfert' => [
                        'categoryType' => CategoryType::TRANSFER_REQUEST,
                        'categoryStatus' => CategorieStatut::TRANSFER_ORDER,
                        'key' => Dashboard\ComponentType::ORDERS_TO_TREAT_TRANSFER
                    ]
                ];
            }

            $categoryTypes = array_values(Stream::from($entities)
                ->map(function (array $entityConfig) {
                    return $entityConfig['categoryType'];
                })
                ->toArray());

            $entitiesStatuses = array_values(Stream::from($entities)
                ->map(function (array $entityConfig) {
                    return $entityConfig['categoryStatus'];
                })
                ->toArray());

            $entityTypes = $typeRepository->findByCategoryLabels($categoryTypes);
            $entityStatuses = $statusRepository->findByCategorieNames($entitiesStatuses, true, [Statut::NOT_TREATED, Statut::TREATED, Statut::PARTIAL, Statut::IN_PROGRESS, Statut::SCHEDULED, Statut::SHIPPED]);
        } else if ($componentType->getMeterKey() === Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS) {
            $entityTypes = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        }
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        foreach(["locations", "firstOriginLocation", "secondOriginLocation", "firstDestinationLocation", "secondDestinationLocation", "pickLocations", "dropLocations"] as $field) {
            if(!empty($values[$field])) {
                $values[$field] = $locationRepository->findBy(['id' => $values[$field]]);
            }
        }

        if (!empty($values['carriers'])) {
            $carrierRepository = $entityManager->getRepository(Transporteur::class);
            $values['carriers'] = $carrierRepository->findBy(['id' => $values['carriers']]);
        }

        if (!empty($values['arrivalTypes'])) {
            $values['arrivalTypes'] = $typeRepository->findBy(['id' => $values['arrivalTypes']]);
        }

        if (!empty($values['dispatchTypes'])) {
            $values['dispatchTypes'] = $typeRepository->findBy(['id' => $values['dispatchTypes']]);
        }

        if (!empty($values['referenceTypes'])) {
            $values['referenceTypes'] = $typeRepository->findBy(['id' => $values['referenceTypes']]);
        }

        if (!empty($values['managers'])) {
            $values['managers'] = $userRepository->findBy(['id' => $values['managers']]);
        }

        if (!empty($values['handlingTypes'])) {
            $values['handlingTypes'] = $typeRepository->findBy(['id' => $values['handlingTypes']]);
        }

        if (!empty($values['productionTypes'])) {
            $values['productionTypes'] = $typeRepository->findBy(['id' => $values['productionTypes']]);
        }

        if (!empty($values['arrivalStatuses'])) {
            $values['arrivalStatuses'] = $statusRepository->findBy(['id' => $values['arrivalStatuses']]);
        }

        if (!empty($values['dispatchStatuses'])) {
            $values['dispatchStatuses'] = $statusRepository->findBy(['id' => $values['dispatchStatuses']]);
        }

        if (!empty($values['productionStatuses'])) {
            $values['productionStatuses'] = $statusRepository->findBy(['id' => $values['productionStatuses']]);
        }

        if (!empty($values['deliveryOrderStatuses'])) {
            $values['deliveryOrderStatuses'] = $statusRepository->findBy(['id' => $values['deliveryOrderStatuses']]);
        }

        if (!empty($values['deliveryOrderTypes'])) {
            $values['deliveryOrderTypes'] = $typeRepository->findBy(['id' => $values['deliveryOrderTypes']]);
        }

        if (!empty($values['handlingStatuses'])) {
            $values['handlingStatuses'] = $statusRepository->findBy(['id' => $values['handlingStatuses']]);
        }

        if (!empty($values['natures'])) {
            $values['natures'] = $natureRepository->findBy(['id' => $values['natures']]);
        }

        if (!empty($values['disputeTypes'])) {
            $values['disputeTypes'] = $typeRepository->findBy(['id' => $values['disputeTypes']]);
        }

        if (!empty($values['disputeStatuses'])) {
            $values['disputeStatuses'] = $statusRepository->findBy(['id' => $values['disputeStatuses']]);
        }

        if (!empty($values['pickLocations'])) {
            $values['pickLocations'] = Stream::from($locationRepository->findBy(['id' => $values['pickLocations']]))
                    ->map(static fn(Emplacement $pickLocation) => [
                        'label' => $pickLocation->getLabel(),
                        'value' => $pickLocation->getId(),
                        'selected' => true,
                    ])
                    ->toArray();
        }

        if (!empty($values['dropLocations'])) {
            $values['dropLocations'] = Stream::from($locationRepository->findBy(['id' => $values['dropLocations']]))
                    ->map(static fn(Emplacement $dropLocation) => [
                        'label' => $dropLocation->getLabel(),
                        'value' => $dropLocation->getId(),
                        'selected' => true,
                    ])
                    ->toArray();
        }

        if (!empty($values['dispatchEmergencies'])) {
            $values['dispatchEmergencies'] = Stream::from([$translationService->translate('Demande', 'Général', 'Non urgent', false), ...$dispatchEmergencies])
                ->filter(static fn($emergency) => in_array($emergency, $values['dispatchEmergencies']))
                ->toArray();
        }

        $values['languages'] = $languageRepository->findBy(['hidden' => false]);

        $tooltip = $values['tooltip'] ?? $componentType->getHint();
        $title = $values['title'] ?? $componentType->getName();
        $values['tooltip'] = [];
        $values['title'] = [];
        foreach ($values['languages'] as $language) {
            $values['tooltip'][$language->getSlug()] = $language->getSlug() === 'french' ? $tooltip : '';
            $values['title'][$language->getSlug()] = $language->getSlug() === 'french' ? $title : '';
        }

        Stream::from($values)
            ->each(function($conf, $key) use (&$values) {
                if (str_starts_with($key, 'tooltip_')) {
                    $values['tooltip'][str_replace('tooltip_', '', $key)] = $conf;
                    unset($values[$key]);
                }
            });

        Stream::from($values)
            ->each(function($conf, $key) use (&$values) {
                if (str_starts_with($key, 'title_')) {
                    $values['title'][str_replace('title_', '', $key)] = $conf;
                    unset($values[$key]);
                }
            });

        $displayLegend = $componentType->getMeterKey() === Dashboard\ComponentType::HANDLING_TRACKING || $componentType->getMeterKey() === Dashboard\ComponentType::PACK_TO_TREAT_FROM;
        $values['legends'] = [];
        if (!empty($values['chartColorsLabels'])) {
            $countLegend = 1;
            foreach($values['chartColorsLabels'] as $legend){
                $values['legends'][$legend] = [];
                Stream::from($values)
                    ->each(function ($conf, $arrayKey) use ($displayLegend, $legend, $countLegend, &$values, $translationService) {
                        if (str_starts_with($arrayKey, 'legend') && str_contains($arrayKey, '_') && str_contains($arrayKey, $countLegend)) {
                            $explode = explode('_', $arrayKey);
                            $values['legends'][$legend][$explode[1]] = $displayLegend ? $conf : $translationService->translate('Dashboard', $conf);
                            unset($values[$arrayKey]);
                        }
                    });
                $countLegend++;
            }
        } else if(!empty($values['chartColors'])) {
            $countLegend = 1;
            foreach($values['chartColors'] as $key => $legend){
                $values['legends'][$key] = [];

                Stream::from($values)
                    ->each(function ($conf, $arrayKey) use ($displayLegend, $countLegend, $key, &$values, $translationService) {
                        if (str_starts_with($arrayKey, 'legend') && str_contains($arrayKey, '_') && str_contains($arrayKey, $countLegend)) {
                            $explode = explode('_', $arrayKey);
                            $values['legends'][$key][$explode[1]] = $displayLegend ? $conf : $translationService->translate('Dashboard', $conf);
                            unset($values[$arrayKey]);
                        }
                    });
                $countLegend++;
            }
        }

        $arrivalTypes = $typeRepository->findByCategoryLabels([CategoryType::ARRIVAGE]);
        $dispatchTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
        $productionTypes = $typeRepository->findByCategoryLabels([CategoryType::PRODUCTION]);
        $referenceTypes = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);
        $arrivalStatuses = $statusRepository->findByCategorieName(CategorieStatut::ARRIVAGE);
        $handlingTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
        $deliveryOrderTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $disputeTypes = $typeRepository->findByCategoryLabels([CategoryType::DISPUTE]);
        $handlingStatuses = $statusRepository->findByCategorieName(CategorieStatut::HANDLING);
        $deliveryOrderStatuses = $statusRepository->findByCategorieName(CategorieStatut::ORDRE_LIVRAISON);
        $dispatchStatuses = $statusRepository->findByCategorieName(CategorieStatut::DISPATCH);
        $productionStatuses = $statusRepository->findByCategorieName(CategorieStatut::PRODUCTION);
        $disputeStatuses = $statusRepository->findByCategorieNames([CategorieStatut::DISPUTE_ARR, CategorieStatut::LITIGE_RECEPT]);

        $natures = $natureRepository->findAll();
        if ($templateName) {
            return $this->json([
                'success' => true,
                'html' => $this->renderView('dashboard/component_type/form.html.twig', [
                    'componentType' => $componentType,
                    'templateName' => $templateName,
                    'rowIndex' => $request->request->get('rowIndex'),
                    'columnIndex' => $request->request->get('columnIndex'),
                    'direction' => $request->request->get('direction'),
                    'cellIndex' => $request->request->get('cellIndex'),
                    'arrivalTypes' => $arrivalTypes,
                    'productionTypes' => $productionTypes,
                    'handlingTypes' => $handlingTypes,
                    'deliveryOrderTypes' => $deliveryOrderTypes,
                    'displayDeliveryOrderWithExpectedDate' => $values['displayDeliveryOrderWithExpectedDate'] ?? '',
                    'displayDeliveryOrderContentCheckbox' => $values['displayDeliveryOrderContentCheckbox'] ?? '',
                    'displayDeliveryOrderContent' => $values['displayDeliveryOrderContent'] ?? '',
                    'dispatchTypes' => $dispatchTypes,
                    'referenceTypes' => $referenceTypes,
                    'arrivalStatuses' => $arrivalStatuses,
                    'handlingStatuses' => $handlingStatuses,
                    'deliveryOrderStatuses' => $deliveryOrderStatuses,
                    'dispatchStatuses' => $dispatchStatuses,
                    'productionStatuses' => $productionStatuses,
                    'entities' => $entities,
                    'entityTypes' => $entityTypes,
                    'entityStatuses' => $entityStatuses,
                    'natures' => $natures,
                    'values' => $values,
                    'dispatchEmergencies' => Stream::from([$translationService->translate('Demande', 'Général', 'Non urgent', false), ...$dispatchEmergencies])
                        ->map(static fn(string $emergency) => [
                            'label' => $emergency,
                            'value' => $emergency,
                            'selected' => in_array($emergency, $values['dispatchEmergencies']),
                        ])
                        ->toArray(),
                    'disputeTypes' => $disputeTypes,
                    'disputeStatuses' => $disputeStatuses,
                ])
            ]);
        } else {
            return $this->json([
                'success' => true
            ]);
        }
    }

    #[Route('/api-component-type/{componentType}/example-values', name: 'dashboard_component_type_example_values', options: ['expose' => true], methods: ['POST'])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_DASHBOARD], mode: HasPermission::IN_JSON)]
    public function apiComponentTypeExample(Request                  $request,
                                            EntityManagerInterface   $entityManager,
                                            DashboardSettingsService $dashboardSettingsService,
                                            Dashboard\ComponentType  $componentType): Response {
        if ($request->request->has("values")) {
            $values = json_decode($request->request->get("values"), true);
            if (isset($values['jsonConfig'])) {
                $valuesDecoded = json_decode($values['jsonConfig'], true);
                if (isset($valuesDecoded['locations']) && isset($values['locations'])) {
                    $valuesDecoded['locations'] = $values['locations'];
                }
                if (isset($valuesDecoded['natures']) && isset($values['natures'])) {
                    $valuesDecoded['natures'] = $values['natures'];
                }
                if (isset($valuesDecoded['dispatchTypes']) && isset($values['dispatchTypes'])) {
                    $valuesDecoded['dispatchTypes'] = $values['dispatchTypes'];
                }
                if (isset($valuesDecoded['handlingTypes']) && isset($values['handlingTypes'])) {
                    $valuesDecoded['handlingTypes'] = $values['handlingTypes'];
                }
                if (isset($valuesDecoded['handlingStatuses']) && isset($values['handlingStatuses'])) {
                    $valuesDecoded['handlingStatuses'] = $values['handlingStatuses'];
                }
                $values = $valuesDecoded;
            }
            Stream::from($componentType->getExampleValues())
                ->each(function($conf, $key) use (&$values) {
                    if (str_starts_with($key, 'fontSize-')
                        || str_starts_with($key, 'textColor-')
                        || str_starts_with($key, 'textBold-')
                        || str_starts_with($key, 'textItalic-')
                        || str_starts_with($key, 'textUnderline-')) {
                        if (!isset($values[$key])) {
                            $values[$key] = $conf;
                        }
                    }
                });
            if ($values && isset($values['chartColors']) && is_string($values['chartColors'])){
                $values['chartColors'] = json_decode($values['chartColors'], true);
            }
        } else {
            $values = $componentType->getExampleValues();
        }

        return $this->json([
            'success' => true,
            'exampleValues' => $dashboardSettingsService->serializeValues($entityManager, $componentType, $values, DashboardSettingsService::MODE_EDIT, true),
        ]);
    }

}
