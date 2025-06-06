<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Dispatch;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\ProductionRequest;
use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\Export;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Transport\TransportRound;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\LanguageHelper;
use App\Service\ArrivageService;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\DispatchService;
use App\Service\Emergency\EmergencyService;
use App\Service\FormatService;
use App\Service\LocationService;
use App\Service\FreeFieldService;
use App\Service\LanguageService;
use App\Service\ReceiptAssociationService;
use App\Service\RefArticleDataService;
use App\Service\ScheduledTaskService;
use App\Service\Tracking\PackService;
use App\Service\Tracking\TrackingMovementService;
use App\Service\Transport\TransportRoundService;
use App\Service\TruckArrivalService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;


#[Route("/parametrage")]
class DataExportController extends AbstractController
{

    public const EXPORT_UNIQUE = "unique";
    public const EXPORT_SCHEDULED = "scheduled";

    #[Route("/export/api", name: "settings_export_api", options: ["expose" => true], methods: [self::POST])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function api(Request                $request,
                        ScheduledTaskService   $scheduledTaskService,
                        EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $exportRepository = $entityManager->getRepository(Export::class);
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_EXPORT, $user);
        $queryResult = $exportRepository->findByParamsAndFilters($request->request, $filters);
        $exports = $queryResult["data"];

        $rows = Stream::from($exports)
            ->map(function (Export $export) use ($scheduledTaskService) {
                $status = $export->getStatus();
                $nextExecution = $status?->getCode() === Export::STATUS_SCHEDULED
                    ? $scheduledTaskService->calculateTaskNextExecution($export, new DateTime("now"))
                    : null;
                return [
                    "actions" => $this->renderView("settings/donnees/export/action.html.twig", [
                        "export" => $export,
                    ]),
                    "status" => $this->getFormatter()->status($status),
                    "createdAt" => $this->getFormatter()->datetime($export->getCreatedAt()),
                    "beganAt" => $this->getFormatter()->datetime($export->getBeganAt()),
                    "endedAt" => $this->getFormatter()->datetime($export->getEndedAt()),
                    "nextExecution" => $this->getFormatter()->datetime($nextExecution),
                    "frequency" => match ($export->getScheduleRule()?->getFrequency()) {
                        ScheduleRule::ONCE => "Une fois",
                        ScheduleRule::HOURLY => "Chaque heure",
                        ScheduleRule::DAILY => "Chaque jour",
                        ScheduleRule::WEEKLY => "Chaque semaine",
                        ScheduleRule::MONTHLY => "Chaque mois",
                        default => null,
                    },
                    "user" => $this->getFormatter()->user($export->getCreator()),
                    "type" => $this->getFormatter()->type($export->getType()),
                    "entity" => Export::ENTITY_LABELS[$export->getEntity()],
                ];
            })
            ->toArray();

        return $this->json([
            "data" => $rows,
            "recordsFiltered" => $queryResult["count"] ?? 0,
            "recordsTotal" => $queryResult["total"] ?? 0,
        ]);
    }

    #[Route("/export/new", name: "settings_new_export", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        Security               $security,
                        ScheduledTaskService   $scheduledTaskService,
                        DataExportService      $dataExportService): JsonResponse {

        $data = $request->request->all();

        if (!isset($data["entityToExport"])) {
            return $this->json([
                "success" => false,
                "msg" => "Veuillez sélectionner un type de données à exporter",
            ]);
        }

        $type = $data["type"];
        $entity = $data["entityToExport"];

        if ($type === self::EXPORT_UNIQUE) {
            //do nothing the export has been done in JS
        } else {
            // Check if the user can plan a new export
            if (!$scheduledTaskService->canSchedule($entityManager, Export::class)) {
                throw new FormException("Vous avez déjà planifié " . ScheduledTaskService::MAX_ONGOING_SCHEDULED_TASKS . " planifications. Pensez à supprimer celles qui sont terminées en fréquence \"une fois\".");
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $statusRepository = $entityManager->getRepository(Statut::class);
            $type = $typeRepository->findOneByCategoryLabelAndLabel(
                CategoryType::EXPORT,
                Type::LABEL_SCHEDULED_EXPORT,
            );

            $status = $statusRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::EXPORT,
                Export::STATUS_SCHEDULED,
            );

            $export = new Export();
            $export
                ->setEntity($entity)
                ->setType($type)
                ->setStatus($status)
                ->setCreator($security->getUser())
                ->setCreatedAt(new DateTime());

            $dataExportService->updateExport($entityManager, $export, $data);

            $entityManager->persist($export);
            $entityManager->flush();

            $scheduledTaskService->deleteCache(Export::class);

            return $this->json([
                "success" => true,
                "msg" => "L'export planifié a été enregistré",
            ]);
        }

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/export/{export}/edit", name: "settings_edit_export", options: ["expose" => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function editExport(Request                $request,
                               EntityManagerInterface $entityManager,
                               DataExportService      $dataExportService,
                               ScheduledTaskService   $scheduledTaskService,
                               Export                 $export): Response {

        $data = $request->request->all();

        if ($export->getType()?->getLabel() !== Type::LABEL_SCHEDULED_EXPORT) {
            throw new NotFoundHttpException('Page non trouvée');
        }

        $dataExportService->updateExport($entityManager, $export, $data);

        $entityManager->flush();

        $scheduledTaskService->deleteCache(Export::class);

        return $this->json([
            "success" => true,
            "msg" => "L'export planifié a été modifié",
        ]);
    }

    #[Route("/export/unique/reference", name: "settings_export_references", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportReferences(EntityManagerInterface $entityManager,
                                     CSVExportService       $csvService,
                                     DataExportService      $dataExportService,
                                     UserService            $userService,
                                     RefArticleDataService  $refArticleDataService,
                                     FreeFieldService       $freeFieldService): StreamedResponse {
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::REFERENCE_ARTICLE], [CategoryType::ARTICLE]);
        $header = $dataExportService->createReferencesHeader($freeFieldsConfig);

        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");
        $user = $userService->getUser();

        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $references = $referenceArticleRepository->iterateAll($user);

        return $csvService->streamResponse(function ($output) use ($references, $entityManager, $dataExportService, $freeFieldsConfig, $refArticleDataService) {
            $start = new DateTime();
            $dataExportService->exportReferences($refArticleDataService, $freeFieldsConfig, $references, $output);
            $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_REFERENCE, $start);
            $entityManager->flush();
        }, "export-references-$today.csv", $header);
    }

    #[Route("/export/unique/location", name: "settings_export_locations", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportLocations(EntityManagerInterface $entityManager,
                                    CSVExportService       $csvService,
                                    DataExportService      $dataExportService,
                                    LocationService        $locationDataService): StreamedResponse {
        $now = new DateTime('now');
        $today = $now->format("d-m-Y-H-i-s");
        $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_LOCATION, $now);

        return $csvService->streamResponse(
            $locationDataService->getExportFunction(
                $entityManager,
            ), "export-emplacement-$today.csv",
            $locationDataService->getCsvHeader()
        );
    }



    #[Route("/export/unique/articles", name: "settings_export_articles", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportArticles(EntityManagerInterface $entityManager,
                                   FreeFieldService       $freeFieldService,
                                   DataExportService      $dataExportService,
                                   ArticleDataService     $articleDataService,
                                   CSVExportService       $csvService,
                                   Request                $request,
                                   UserService            $userService): StreamedResponse {
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARTICLE], [CategoryType::ARTICLE]);
        $header = $dataExportService->createArticlesHeader($freeFieldsConfig);

        $options = [];
        if ($request->query->get("dateMin") !== "" && $request->query->get("dateMax") !== "") {
            $options["dateMin"] = DateTime::createFromFormat('d/m/Y', $request->query->get("dateMin"))->setTime(0, 0);
            $options["dateMax"] = DateTime::createFromFormat('d/m/Y', $request->query->get("dateMax"))->setTime(23, 59, 59);
        }

        if ($request->query->all("referenceTypes") !== null) {
            $options["referenceTypes"] = $request->query->all("referenceTypes");
        }
        if ($request->query->all("statuses") !== null) {
            $options["statuses"] = $request->query->all("statuses");
        }
        if ($request->query->all("suppliers") !== null) {
            $options["suppliers"] = $request->query->all("suppliers");
        }

        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");
        $user = $userService->getUser();

        $articleRepository = $entityManager->getRepository(Article::class);
        $articles = $articleRepository->iterateAll($user, $options);

        return $csvService->streamResponse(function ($output) use ($articles, $freeFieldsConfig, $entityManager, $dataExportService, $articleDataService) {
            $start = new DateTime();
            $dataExportService->exportArticles($articleDataService, $freeFieldsConfig, $articles, $output);
            $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_ARTICLE, $start);
            $entityManager->flush();
        }, "export-articles-$today.csv", $header);
    }


    #[Route("/export/unique/rounds", name: "settings_export_round", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportRounds(CSVExportService       $csvService,
                                 TransportRoundService  $transportRoundService,
                                 DataExportService      $dataExportService,
                                 EntityManagerInterface $entityManager,
                                 Request                $request): Response {

        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        $transportRoundRepository = $entityManager->getRepository(TransportRound::class);
        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");
        $header = $dataExportService->createDeliveryRoundHeader();

        $transportRoundsIterator = $transportRoundRepository->iterateFinishedTransportRounds($dateTimeMin, $dateTimeMax);
        return $csvService->streamResponse(function ($output) use ($csvService, $dataExportService, $entityManager, $dateTimeMin, $dateTimeMax, $transportRoundService, $transportRoundsIterator) {
            $start = new DateTime();
            $dataExportService->exportTransportRounds($transportRoundService, $transportRoundsIterator, $output, $dateTimeMin, $dateTimeMax);
            $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_DELIVERY_ROUND, $start);
            $entityManager->flush();
        }, "export-tournees-$today.csv", $header);
    }

    #[Route("/export/unique/arrivals", name: "settings_export_arrival", options: ["expose" => true], methods: [self::GET])]
    public function exportArrivals(CSVExportService       $csvService,
                                   ArrivageService        $arrivalService,
                                   DataExportService      $dataExportService,
                                   EntityManagerInterface $entityManager,
                                   Request                $request): Response {

        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");
        $columnToExport = $request->query->all("columnToExport");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $nameFile = "export-arrivages-$today.csv";
        $arrivalService->launchExportCache($entityManager, $dateTimeMin, $dateTimeMax);

        $csvHeader = $dataExportService->createArrivalsHeader($entityManager, $columnToExport);

        $arrivalsIterator = $arrivageRepository->iterateArrivals($dateTimeMin, $dateTimeMax);
        return $csvService->streamResponse(function ($output) use ($entityManager, $dataExportService, $columnToExport, $arrivalsIterator) {
            $start = new DateTime();
            $dataExportService->exportArrivages($arrivalsIterator, $output, $columnToExport);
            $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_ARRIVAL, $start);
            $entityManager->flush();
        }, $nameFile, $csvHeader);
    }

    #[Route("/export/unique/ref-location", name: "settings_export_ref_location", options: ["expose" => true], methods: [self::GET])]
    public function exportRefLocation(CSVExportService       $csvService,
                                      DataExportService      $dataExportService,
                                      EntityManagerInterface $entityManager): Response {

        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $nameFile = "export-reference-emplacement-$today.csv";

        $csvHeader = $dataExportService->createStorageRulesHeader();
        $refLocationsIterator = $entityManager->getRepository(StorageRule::class)->iterateAll();

        return $csvService->streamResponse(function ($output) use ($entityManager, $dataExportService, $refLocationsIterator) {
            $start = new DateTime();
            $dataExportService->exportRefLocation($refLocationsIterator, $output);
            $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_REF_LOCATION, $start);
            $entityManager->flush();
        }, $nameFile, $csvHeader);
    }

    #[Route("/export/unique/dispatches", name: "settings_export_dispatches", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportDispatches(EntityManagerInterface $entityManager,
                                     CSVExportService       $csvService,
                                     DataExportService      $dataExportService,
                                     FreeFieldService       $freeFieldService,
                                     Request                $request): StreamedResponse {
        $columnToExport = $request->query->all("columnToExport");
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_DISPATCH]);
        $headers = $dataExportService->createDispatchesHeader($entityManager, $columnToExport);

        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");

        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        return $csvService->streamResponse(
            function ($output) use ($dateTimeMax, $dateTimeMin, $entityManager, $dataExportService, $csvService, $freeFieldsConfig, $columnToExport) {
                $dispatchRepository = $entityManager->getRepository(Dispatch::class);
                $userDateFormat = $this->getUser()->getDateFormat();
                $dispatches = $dispatchRepository->getByDates($dateTimeMin, $dateTimeMax, $userDateFormat);

                $freeFieldsById = Stream::from($dispatches)
                    ->keymap(fn($dispatch) => [$dispatch['id'], $dispatch['freeFields']])
                    ->toArray();

                $start = new DateTime();
                $dataExportService->exportDispatch($dispatches, $output, $columnToExport, $freeFieldsConfig, $freeFieldsById);
                $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_DISPATCH, $start);
                $entityManager->flush();
            },
            "export_acheminements-$today.csv",
            $headers
        );
    }

    #[Route("/export/unique/tracking-movements", name: "settings_export_tracking_movements", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportTrackingMovements(EntityManagerInterface $entityManager,
                                            CSVExportService       $csvService,
                                            DataExportService      $dataExportService,
                                            FreeFieldService       $freeFieldService,
                                            Request                $request): StreamedResponse {
        $columnToExport = $request->query->all("columnToExport");
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::MVT_TRACA]);
        $headers = $dataExportService->createTrackingMovementsHeader($entityManager, $columnToExport);

        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");

        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $userDateFormat = $this->getUser()->getDateFormat();
        $trackingMovements = $trackingMovementRepository->getByDates($dateTimeMin, $dateTimeMax, $userDateFormat);

        return $csvService->streamResponse(
            static function ($output) use ($trackingMovements, $entityManager, $dataExportService, $freeFieldsConfig, $columnToExport) {

                $start = new DateTime();
                $dataExportService->exportTrackingMovements($trackingMovements, $output, $columnToExport, $freeFieldsConfig);
                $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_TRACKING_MOVEMENT, $start);
                $entityManager->flush();
            },
            "export_mouvements_tracabilite-$today.csv",
            $headers
        );
    }

    #[Route("/export/unique/production-requests", name: "settings_export_production_requests", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportProductionRequests(EntityManagerInterface $manager,
                                             CSVExportService       $csvService,
                                             DataExportService      $dataExportService,
                                             FreeFieldService       $freeFieldService,
                                             Request                $request,
                                             LanguageService        $languageService): StreamedResponse {

        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($manager, [CategorieCL::PRODUCTION_REQUEST]);
        $header = $dataExportService->createProductionRequestsHeader();

        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");

        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        $user = $this->getUser();
        $defaultSlug = LanguageHelper::clearLanguage($languageService->getDefaultSlug());
        $defaultLanguage = $manager->getRepository(Language::class)->findOneBy(["slug" => $defaultSlug]);
        $userDateFormat = $user->getDateFormat();

        return $csvService->streamResponse(
            function ($output) use ($dateTimeMax, $dateTimeMin, $manager, $dataExportService, $csvService, $freeFieldsConfig, $user, $defaultLanguage, $userDateFormat) {
                $dispatchRepository = $manager->getRepository(ProductionRequest::class);
                $productionRequests = $dispatchRepository->getByDates(
                    $dateTimeMin,
                    $dateTimeMax,
                    new InputBag([
                        "date-choice_createdAt" => true,
                    ]),
                    [
                        "userDateFormat" => $userDateFormat,
                        "defaultLanguage" => $defaultLanguage,
                        "language" => $user->getLanguage(),
                    ]
                );

                $freeFieldsById = Stream::from($productionRequests)
                    ->keymap(static fn(array $productionRequest) => [
                        $productionRequest['id'], $productionRequest['freeFields']
                    ])->toArray();

                $start = new DateTime();
                $dataExportService->exportProductionRequest($productionRequests, $output, $freeFieldsConfig, $freeFieldsById);
                $dataExportService->persistUniqueExport($manager, Export::ENTITY_PRODUCTION, $start);
            },
            "export_productions-$today.csv",
            $header
        );
    }

    #[Route("/export/unique/unités-logistiques", name: "settings_export_packs", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function printCSVPacks(Request                $request,
                                  PackService            $packService,
                                  EntityManagerInterface $entityManager,
                                  CSVExportService       $CSVExportService,
                                  DataExportService      $dataExportService): StreamedResponse {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');
        $now = new DateTime('now');
        $today = $now->format("d-m-Y-H-i-s");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_PACK, $now);

        return $CSVExportService->streamResponse(
            $packService->getExportPacksFunction(
                $dateTimeMin,
                $dateTimeMax,
                $entityManager,
            ), "export_UL_$today.csv",
            $packService->getCsvHeader()
        );
    }

    #[Route("/export/unique/associations-BR", name: "settings_export_receipt_association", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function printCSVReceiptAssociation(Request                   $request,
                                               EntityManagerInterface    $entityManager,
                                               CSVExportService          $CSVExportService,
                                               DataExportService         $dataExportService,
                                               ReceiptAssociationService $receiptAssociationService): StreamedResponse {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $now = new DateTime('now');
        $today = $now->format("d-m-Y-H-i-s");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_RECEIPT_ASSOCIATION, $now);

        return $CSVExportService->streamResponse(
            $receiptAssociationService->getExportReceiptAssociationFunction(
                $dateTimeMin,
                $dateTimeMax,
                $entityManager,
            ), "export_UL_$today.csv",
            $receiptAssociationService->getCsvHeader()
        );
    }


    #[Route("/export-template", name: "export_template", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportTemplate(EntityManagerInterface  $entityManager,
                                   Request                 $request,
                                   ArrivageService         $arrivalService,
                                   DispatchService         $dispatchService,
                                   TrackingMovementService $trackingMovementService): JsonResponse {

        $exportRepository = $entityManager->getRepository(Export::class);

        $exportId = $request->query->get('export');
        $export = $exportId
            ? $exportRepository->find($exportId)
            : new Export();

        $arrivalExportableColumns = $arrivalService->getArrivalExportableColumns($entityManager);
        $dispatchExportableColumns = $dispatchService->getDispatchExportableColumns($entityManager);
        $trackingMovementExportableColumns = $trackingMovementService->getTrackingMovementExportableColumns($entityManager);
        $refTypes = $entityManager->getRepository(Type::class)->findByCategoryLabels([CategoryType::ARTICLE]);

        $statuses = $entityManager->getRepository(Statut::class)->findBy(["nom" => [Article::STATUT_ACTIF, Article::STATUT_INACTIF]]);
        $suppliers = $entityManager->getRepository(Fournisseur::class)->getForExport();

        return new JsonResponse($this->renderView('settings/donnees/export/form.html.twig', [
            "export" => $export,
            "refTypes" => Stream::from($refTypes)
                ->keymap(fn(Type $type) => [$type->getId(), $type->getLabel()])
                ->toArray(),
            "statuses" => Stream::from($statuses)
                ->keymap(fn(Statut $status) => [$status->getId(), $status->getNom()])
                ->toArray(),
            "suppliers" => Stream::from($suppliers)
                ->keymap(fn(Fournisseur $supplier) => [$supplier->getId(), $supplier->getNom()])
                ->toArray(),
            "exportableColumns" => [
                Export::ENTITY_ARRIVAL => Stream::from($arrivalExportableColumns)
                    ->keymap(fn(array $config) => [$config['code'], $config['label']])
                    ->toArray(),
                Export::ENTITY_DISPATCH => Stream::from($dispatchExportableColumns)
                    ->keymap(fn(array $config) => [$config['code'], $config['label']])
                    ->toArray(),
                Export::ENTITY_TRACKING_MOVEMENT => Stream::from($trackingMovementExportableColumns)
                    ->keymap(fn(array $config) => [$config['code'], $config['label']])
                    ->toArray(),
            ],
        ]));
    }

    #[Route("/export/plannifie/{export}/annuler", name: "settings_export_cancel", options: ["expose" => true], methods: [self::PATCH], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function cancel(Export                 $export,
                           EntityManagerInterface $entityManager,
                           ScheduledTaskService   $scheduledTaskService): JsonResponse {
        $statusRepository = $entityManager->getRepository(Statut::class);

        $exportType = $export->getType();
        $exportStatus = $export->getStatus();
        $canCancel = (
            $exportType?->getLabel() === Type::LABEL_SCHEDULED_EXPORT
            && $exportStatus?->getNom() === Export::STATUS_SCHEDULED
        );
        if ($canCancel) {
            $statusCancelled = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::EXPORT, Export::STATUS_CANCELLED);
            $export->setStatus($statusCancelled);
            $entityManager->flush();

            $scheduledTaskService->deleteCache(Export::class);
        }

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route("/export/unique/truck-arrival", name: "settings_export_truck_arrival", options: ["expose" => true], methods: [self::GET])]
    public function exportTruckArrival(Request                $request,
                                       TruckArrivalService    $truckArrivalService,
                                       EntityManagerInterface $entityManager,
                                       CSVExportService       $CSVExportService,
                                       DataExportService      $dataExportService): StreamedResponse {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');
        $now = new DateTime('now');
        $today = $now->format("d-m-Y-H-i-s");

        $dateTimeMin = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMin 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("d/m/Y H:i:s", "$dateMax 23:59:59");

        $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_EMERGENCY, $now);

        return $CSVExportService->streamResponse(
            $truckArrivalService->getExportFunction(
                $dateTimeMin,
                $dateTimeMax,
                $entityManager,
            ), "export-arrivage-camion-$today.csv",
            $truckArrivalService->getCsvHeader()
        );
    }

    #[Route("/export/unique/emergency", name: "settings_export_emergency", options: ["expose" => true], methods: [self::GET])]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportEmergency(Request                $request,
                                    EmergencyService       $emergencyService,
                                    EntityManagerInterface $entityManager,
                                    CSVExportService       $csvExportService,
                                    DataExportService      $dataExportService,
                                    FormatService          $formatService): StreamedResponse {

        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');
        $now = new DateTime('now');
        $today = $now->format("d-m-Y-H-i-s");

        $dateTimeMin = $formatService->parseDatetime("$dateMin 00:00:00");
        $dateTimeMax = $formatService->parseDatetime("$dateMax 23:59:59");

        if (!$dateTimeMin || !$dateTimeMax) {
            throw new RuntimeException("Invalid dates");
        }

        $dataExportService->persistUniqueExport($entityManager, Export::ENTITY_EMERGENCY, $now);

        return $csvExportService->streamResponse(
            $emergencyService->getExportFunction(
                $entityManager,
                $dateTimeMin,
                $dateTimeMax
            ), "export-urgences-$today.csv",
            $dataExportService->createEmergencyHeader()
        );
    }
}

