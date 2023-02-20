<?php

namespace App\Controller\Settings;

use App\Entity\Arrivage;
use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\ExportScheduleRule;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\ArrivageService;
use App\Service\ArticleDataService;
use App\Service\CacheService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\FreeFieldService;
use App\Service\RefArticleDataService;
use App\Service\ScheduledExportService;
use App\Service\Transport\TransportRoundService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use WiiCommon\Helper\Stream;


#[Route("/parametrage")]
class DataExportController extends AbstractController {

    public const EXPORT_UNIQUE = "unique";
    public const EXPORT_SCHEDULED = "scheduled";

    #[Route("/export/api", name: "settings_export_api", options: ["expose" => true], methods: "POST")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function api(Request $request, EntityManagerInterface $manager): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $exportRepository = $manager->getRepository(Export::class);
        $filtreSupRepository = $manager->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_EXPORT, $user);
        $queryResult = $exportRepository->findByParamsAndFilters($request->request, $filters);
        $exports = $queryResult["data"];

        $rows = [];
        /** @var Export $export */
        foreach ($exports as $export) {
            $rows[] = [
                "actions" => $this->renderView("settings/donnees/export/action.html.twig", [
                    "export" => $export,
                ]),
                "status" => $export->getStatus()->getNom(),
                "createdAt" => $export->getCreatedAt()->format("d/m/Y H:i"),
                "beganAt" => $export->getBeganAt()?->format("d/m/Y H:i"),
                "endedAt" => $export->getEndedAt()?->format("d/m/Y H:i"),
                "nextExecution" => $export->getNextExecution()?->format("d/m/Y H:i"),
                "frequency" => match($export->getExportScheduleRule()?->getFrequency()) {
                    ExportScheduleRule::ONCE => "Une fois",
                    ExportScheduleRule::HOURLY => "Chaque heure",
                    ExportScheduleRule::DAILY => "Chaque jour",
                    ExportScheduleRule::WEEKLY => "Chaque semaine",
                    ExportScheduleRule::MONTHLY => "Chaque mois",
                    default => null,
                },
                "user" => FormatHelper::user($export->getCreator()),
                "type" => FormatHelper::type($export->getType()),
                "entity" => Export::ENTITY_LABELS[$export->getEntity()],
            ];
        }

        return $this->json([
            "data" => $rows,
            "recordsFiltered" => $queryResult["count"] ?? 0,
            "recordsTotal" => $queryResult["total"] ?? 0,
        ]);
    }

    #[Route("/export/new", name: "settings_new_export", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        Security               $security,
                        CacheService           $cacheService,
                        DataExportService      $dataExportService): Response {

        $data = $request->request->all();

        if(!isset($data["entityToExport"])) {
            return $this->json([
                "success" => false,
                "msg" => "Veuillez sélectionner un type de données à exporter",
            ]);
        }

        $type = $data["type"];
        $entity = $data["entityToExport"];

        if($type === self::EXPORT_UNIQUE) {
            //do nothing the export has been done in JS
        } else {
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
                ->setCreatedAt(new DateTime())
                ->setForced(false);

            $dataExportService->updateExport($entityManager, $export, $data);
            $cacheService->delete(CacheService::EXPORTS);

            $entityManager->persist($export);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => "L'export planifié a été enregistré",
            ]);
        }

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/export/{export}/edit", name: "settings_edit_export", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function editExport(Request                $request,
                               EntityManagerInterface $entityManager,
                               DataExportService      $dataExportService,
                               CacheService           $cacheService,
                               Export                 $export): Response {

        $data = $request->request->all();

        if ($export->getType()?->getLabel() !== Type::LABEL_SCHEDULED_EXPORT) {
            throw new NotFoundHttpException('Page non trouvée');
        }

        $dataExportService->updateExport($entityManager, $export, $data);
        $cacheService->delete(CacheService::EXPORTS);

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "L'export planifié a été modifié",
        ]);
    }

    #[Route("/export/unique/reference", name: "settings_export_references", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportReferences(EntityManagerInterface $manager,
                                     CSVExportService       $csvService,
                                     DataExportService      $dataExportService,
                                     UserService            $userService,
                                     RefArticleDataService  $refArticleDataService,
                                     FreeFieldService       $freeFieldService): StreamedResponse {
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($manager, [CategorieCL::REFERENCE_ARTICLE], [CategoryType::ARTICLE]);
        $header = $dataExportService->createReferencesHeader($freeFieldsConfig);

        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");
        $user = $userService->getUser();

        return $csvService->streamResponse(function($output) use ($manager, $dataExportService, $user, $freeFieldsConfig, $refArticleDataService) {
            $referenceArticleRepository = $manager->getRepository(ReferenceArticle::class);
            $references = $referenceArticleRepository->iterateAll($user);

            $start = new DateTime();
            $dataExportService->exportReferences($refArticleDataService, $freeFieldsConfig, $references, $output);
            $dataExportService->createUniqueExportLine(Export::ENTITY_REFERENCE, $start);
        }, "export-references-$today.csv", $header);
    }

    #[Route("/export/unique/articles", name: "settings_export_articles", options: ["expose" => true], methods: "GET")]
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

        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");
        $dateTimeMin = DateTime::createFromFormat('d/m/Y', $dateMin);
        $dateTimeMax = DateTime::createFromFormat('d/m/Y', $dateMax);
        $referenceTypes = $request->query->all('referenceTypes');
        $statuses = $request->query->all('statuses');
        $suppliers = $request->query->all('suppliers');
        $today = (new DateTime('now'))->format("d-m-Y-H-i-s");
        $user = $userService->getUser();

        return $csvService->streamResponse(function($output) use ($freeFieldsConfig, $entityManager, $dataExportService, $user,
            $articleDataService, $referenceTypes, $statuses, $suppliers, $dateTimeMin, $dateTimeMax) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $articles = $articleRepository->iterateAll($user, $dateTimeMin, $dateTimeMax);

            $start = new DateTime();
            $dataExportService->exportArticles($articleDataService, $freeFieldsConfig, $articles, $output, $referenceTypes, $statuses, $suppliers);
            $dataExportService->createUniqueExportLine(Export::ENTITY_ARTICLE, $start);
        }, "export-articles-$today.csv", $header);
    }


    #[Route("/export/unique/rounds", name: "settings_export_round", options: ["expose" => true], methods: "GET")]
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
        return $csvService->streamResponse(function ($output) use ($csvService, $dataExportService, $dateTimeMin, $dateTimeMax, $transportRoundService, $transportRoundsIterator) {
            $start = new DateTime();
            $dataExportService->exportTransportRounds($transportRoundService, $transportRoundsIterator, $output, $dateTimeMin, $dateTimeMax);
            $dataExportService->createUniqueExportLine(Export::ENTITY_DELIVERY_ROUND, $start);
        }, "export-tournees-$today.csv", $header);
    }

    #[Route("/export/unique/arrivals", name: "settings_export_arrival", options: ["expose" => true], methods: "GET")]
    public function exportArrivals(CSVExportService     $csvService,
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
        return $csvService->streamResponse(function ($output) use ($dataExportService, $columnToExport, $arrivalsIterator) {
            $start = new DateTime();
            $dataExportService->exportArrivages($arrivalsIterator, $output, $columnToExport);
            $dataExportService->createUniqueExportLine(Export::ENTITY_ARRIVAL, $start);
        }, $nameFile, $csvHeader);
    }

    #[Route("/export-template", name: "export_template", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function exportTemplate(EntityManagerInterface $entityManager,
                                   Request                $request,
                                   ArrivageService        $arrivalService): JsonResponse {

        $exportRepository = $entityManager->getRepository(Export::class);

        $exportId = $request->query->get('export');
        $export = $exportId
            ? $exportRepository->find($exportId)
            : new Export();

        $columns = $arrivalService->getArrivalExportableColumns($entityManager);
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
            "arrivalFields" => Stream::from($columns)
                ->keymap(fn(array $config) => [$config['code'], $config['label']])
                ->toArray()
        ]));
    }

    #[Route("/export/plannifie/{export}/annuler", name: "settings_export_cancel", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function cancel(Export $export,
                           EntityManagerInterface $manager,
                           CacheService $cacheService): JsonResponse {
        $statusRepository = $manager->getRepository(Statut::class);

        $exportType = $export->getType();
        $exportStatus = $export->getStatus();
        if ($exportStatus && $exportType && $exportType->getLabel() == Type::LABEL_SCHEDULED_EXPORT && $exportStatus->getNom() == Export::STATUS_SCHEDULED) {
            $export->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::EXPORT, Export::STATUS_CANCELLED));
            $manager->flush();

            $cacheService->delete(CacheService::EXPORTS);
        }

        return $this->json([
            'success' => true,
        ]);
    }

    #[Route("/export/plannifie/{export}/force", name: "settings_export_force", options: ["expose" => true], methods: "GET|POST", condition:"request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function force(EntityManagerInterface $manager, ScheduledExportService $scheduledExportService, CacheService $cacheService, Export $export): JsonResponse {
        $export->setForced(true);
        $export->setNextExecution($scheduledExportService->calculateNextExecutionDate($export));
        $manager->flush();

        $cacheService->delete(CacheService::EXPORTS);

        return $this->json([
            "success" => true,
            "msg" => "L'export a bien été forcé",
        ]);
    }
}

