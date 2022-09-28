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
use App\Entity\FieldsParam;
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
use App\Service\ImportService;
use App\Service\RefArticleDataService;
use App\Service\ScheduledExportService;
use App\Service\Transport\TransportRoundService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Exp;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use WiiCommon\Helper\Stream;

#[Route("/parametrage")]
class DataExportController extends AbstractController {

    public const EXPORT_UNIQUE = "exportUnique";
    public const EXPORT_SCHEDULED = "exportScheduled";

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
                "creationDate" => $export->getCreatedAt()->format("d/m/Y H:i"),
                "startDate" => $export->getBeganAt()?->format("d/m/Y H:i"),
                "endDate" => $export->getEndedAt()?->format("d/m/Y H:i"),
                "nextRun" => $export->getNextExecution()?->format("d/m/Y H:i"),
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

    #[Route("/export/submit", name: "settings_submit_export", options: ["expose" => true], methods: "POST", condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function submitExport(Request $request, EntityManagerInterface $manager, Security $security, CacheService $cacheService): Response {
        $userRepository = $manager->getRepository(Utilisateur::class);

        $data = $request->request->all();

        if(!isset($data["entityToExport"])) {
            return $this->json([
                "success" => false,
                "msg" => "Veuillez sélectionner un type de données à exporter",
            ]);
        }

        $type = $data["exportTypeContainer"];
        $entity = $data["entityToExport"];

        if($type === self::EXPORT_UNIQUE) {
            //do nothing the export has been done in JS
        } else {
            $type = $manager->getRepository(Type::class)->findOneByCategoryLabelAndLabel(
                CategoryType::EXPORT,
                Type::LABEL_SCHEDULED_EXPORT,
            );

            $status = $manager->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(
                CategorieStatut::EXPORT,
                Export::STATUS_SCHEDULED,
            );

            $export = new Export();
            $export->setEntity($entity);
            $export->setType($type);
            $export->setStatus($status);
            $export->setCreator($security->getUser());
            $export->setCreatedAt(new DateTime());
            $export->setForced(false);

            $export->setDestinationType($data["destinationType"]);
            if($export->getDestinationType() == Export::DESTINATION_EMAIL) {
                $export->setFtpParameters(null);

                $emails = isset($data["recipientEmails"]) && $data["recipientEmails"] ? explode(",", $data["recipientEmails"]) : [];
                $counter = 0;
                foreach ($emails as $email) {
                    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $counter++;
                    }
                }

                if($counter !== 0) {
                    return $this->json([
                        "success" => false,
                        "msg" => $counter === 1
                            ? "Une adresse email n'est pas valide dans votre saisie"
                            : "Plusieurs adresses email ne sont pas valides dans votre saisie"
                    ]);
                }

                $export->setRecipientUsers($userRepository->findBy(["id" => explode(",", $data["recipientUsers"])]));
                $export->setRecipientEmails($emails);

                if($export->getRecipientUsers()->isEmpty() && count($emails) === 0) {
                    return $this->json([
                        "success" => false,
                        "msg" => "Vous devez renseigner au moins un utilisateur ou une adresse mail destinataire"
                    ]);
                }
            } else {
                $export->setRecipientUsers([]);
                $export->setRecipientEmails([]);

                $export->setFtpParameters([
                    "host" => $data["host"],
                    "port" => $data["port"],
                    "user" => $data["user"],
                    "pass" => $data["password"],
                    "path" => $data["targetDirectory"],
                ]);
            }
            if($entity === Export::ENTITY_ARRIVAL) {
                $export->setColumnToExport(explode(",", $data["columnToExport"]));
            }

            if($entity === Export::ENTITY_ARRIVAL || $entity === Export::ENTITY_DELIVERY_ROUND) {
                $export->setPeriod($data["period"]);
                $export->setPeriodInterval($data["periodInterval"]);
            }

            $export->setExportScheduleRule((new ExportScheduleRule())
                ->setBegin(DateTime::createFromFormat("Y-m-d\TH:i", $data["startDate"]))
                ->setFrequency($data["frequency"] ?? null)
                ->setPeriod($data["repeatPeriod"] ?? null)
                ->setIntervalTime($data["intervalTime"] ?? null)
                ->setIntervalPeriod($data["intervalPeriod"] ?? null)
                ->setMonths(isset($data["months"]) ? explode(",", $data["months"]) : null)
                ->setWeekDays(isset($data["weekDays"]) ? explode(",", $data["weekDays"]) : null)
                ->setMonthDays(isset($data["monthDays"]) ? explode(",", $data["monthDays"]) : null));

            $export
                ->setNextExecution($cacheService->delete(CacheService::EXPORTS));

            $manager->persist($export);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "L'export planifié a été enregistré",
            ]);
        }

        return $this->json([
            "success" => true,
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

        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
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
                                   UserService            $userService,
                                   CSVExportService       $csvService): StreamedResponse {
        $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARTICLE], [CategoryType::ARTICLE]);
        $header = $dataExportService->createArticlesHeader($freeFieldsConfig);

        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $user = $userService->getUser();

        return $csvService->streamResponse(function($output) use ($freeFieldsConfig, $entityManager, $dataExportService, $user, $articleDataService) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $articles = $articleRepository->iterateAll($user);

            $start = new DateTime();
            $dataExportService->exportArticles($articleDataService, $freeFieldsConfig, $articles, $output);
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
        $today = $today->format("d-m-Y H:i:s");
        $header = $dataExportService->createDeliveryRoundHeader();

        $transportRoundsIterator = $transportRoundRepository->iterateFinishedTransportRounds($dateTimeMin, $dateTimeMax);
        return $csvService->streamResponse(function ($output) use ($csvService, $dataExportService, $dateTimeMin, $dateTimeMax, $transportRoundService, $transportRoundsIterator) {
            $start = new DateTime();
            $dataExportService->exportTransportRounds($transportRoundService, $transportRoundsIterator, $dateTimeMin, $dateTimeMax, $output);
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

        $csvHeader = $arrivalService->getHeaderForExport($entityManager, $columnToExport);

        $arrivalsIterator = $arrivageRepository->iterateArrivals($dateTimeMin, $dateTimeMax);
        return $csvService->streamResponse(function ($output) use ($dataExportService, $columnToExport, $arrivalsIterator) {
            $start = new DateTime();
            $dataExportService->exportArrivages($arrivalsIterator, $output, $columnToExport);
            $dataExportService->createUniqueExportLine(Export::ENTITY_ARRIVAL, $start);
        }, $nameFile, $csvHeader);
    }

    #[Route("/modale-new-export", name: "new_export_modal", options: ["expose" => true], methods: "GET")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function getFirstModalContent(EntityManagerInterface $entityManager,
                                         ArrivageService        $arrivalService): JsonResponse {
        $columns = $arrivalService->getArrivalExportableColumns($entityManager);

        return new JsonResponse($this->renderView('settings/donnees/export/modalNewExportContent.html.twig', [
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

        return new JsonResponse();
    }

    #[Route("/export/plannifie/{export}/force", name: "settings_export_force", options: ["expose" => true], methods: "GET|POST", condition:"request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_EXPORT])]
    public function force(EntityManagerInterface $manager, CacheService $cacheService, Export $export): JsonResponse {
        $export->setForced(true);
        $manager->flush();

        $cacheService->delete(CacheService::EXPORTS);

        return $this->json([
            "success" => true,
            "msg" => "L'export a bien été forcé",
        ]);
    }
}

