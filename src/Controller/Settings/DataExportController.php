<?php

namespace App\Controller\Settings;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\ExportScheduleRule;
use App\Entity\FieldsParam;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\ArrivageService;
use App\Service\ArticleDataService;
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

    public const ENTITY_REFERENCE = "references";
    public const ENTITY_ARTICLE = "articles";
    public const ENTITY_TRANSPORT_ROUNDS = "transportRounds";
    public const ENTITY_ARRIVALS = "arrivals";

    #[Route("/export/api", name: "settings_export_api", options: ["expose" => true], methods: "POST")]
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
                "creationDate" => $export->getCreatedAt()->format("d/m/Y"),
                "startDate" => $export->getBeganAt()?->format("d/m/Y"),
                "endDate" => $export->getEndedAt()?->format("d/m/Y"),
                "nextRun" => $export->getNextExecution()?->format("d/m/Y"),
                "frequency" => $export->getExportScheduleRule()?->getFrequency(), //TODO: formatter = probleme de marwane
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

    #[Route("/export/submit", name: "settings_submit_export", options: ["expose" => true], methods: "POST")]
    public function submitExport(Request $request, EntityManagerInterface $manager, Security $security): Response {
        $userRepository = $manager->getRepository(Utilisateur::class);

        $data = $request->request->all();
dump($request->getContent(), $request->request);
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

                $emails = explode(",", $data["recipientEmails"]);
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
            } else {
                $export->setRecipientUsers([]);
                $export->setRecipientEmails([]);

                $export->setFtpParameters([
                    "host" => $data["host"],
                    "port" => $data["port"],
                    "user" => $data["user"],
                    "password" => $data["password"],
                    "targetDirectory" => $data["targetDirectory"],
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
                ->setPeriod($data["period"] ?? null)
                ->setIntervalTime($data["intervalTime"] ?? null)
                ->setIntervalPeriod($data["intervalPeriod"] ?? null)
                ->setIntervalType($data["intervalType"] ?? null)
                ->setMonths(isset($data["months"]) ? explode(",", $data["months"]) : null)
                ->setWeekDays(isset($data["weekDays"]) ? explode(",", $data["weekDays"]) : null)
                ->setMonthDays(isset($data["monthDays"]) ? explode(",", $data["monthDays"]) : null));

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

            $dataExportService->exportReferences($refArticleDataService, $freeFieldsConfig, $references, $output);
        }, "export-references-$today.csv", $header);
    }

    #[Route("/export/unique/articles", name: "settings_export_articles", options: ["expose" => true], methods: "GET")]
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

            $dataExportService->exportArticles($articleDataService, $freeFieldsConfig, $articles, $output);
        }, "export-articles-$today.csv", $header);
    }


    #[Route("/export/unique/rounds", name: "settings_export_round", options: ["expose" => true], methods: "GET")]
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
        $nameFile = "export-tournees-$today.csv";
        $csvHeader = $transportRoundService->getHeaderRoundAndRequestExport();

        $transportRoundsIterator = $transportRoundRepository->iterateFinishedTransportRounds($dateTimeMin, $dateTimeMax);
        return $csvService->streamResponse(function ($output) use ($dataExportService, $csvService, $transportRoundService, $transportRoundsIterator) {
            $dataExportService->exportTransportRounds($transportRoundService, $transportRoundsIterator, $output);
        }, $nameFile, $csvHeader);
    }

    #[Route("/export/unique/arrivals", name: "settings_export_arrival", options: ["expose" => true], methods: "GET")]
    public function exportArrivals(CSVExportService       $csvService,
                                 ArrivageService  $arrivageService,
                                 DataExportService $dataExportService,
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
        $csvHeader = $arrivageService->getHeaderForExport($columnToExport);

        $arrivalsIterator = $arrivageRepository->iterateArrivals($dateTimeMin, $dateTimeMax);
        return $csvService->streamResponse(function ($output) use ($dataExportService, $columnToExport, $entityManager, $csvService, $arrivageService, $arrivalsIterator) {
            $dataExportService->exportArrivages($arrivageService, $arrivalsIterator, $output, $columnToExport);
        }, $nameFile, $csvHeader);
    }


    #[Route("/modale-new-export", name: "new_export_modal", options: ["expose" => true], methods: "GET")]
    public function getFirstModalContent(EntityManagerInterface $entityManager): JsonResponse
    {
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $arrivalFields = $fieldsParamRepository->getByEntityForExport(FieldsParam::ENTITY_CODE_ARRIVAGE);
        $arrivalFields = Stream::from($arrivalFields)
            ->keymap(fn(FieldsParam $field) => [$field->getFieldCode(), $field->getFieldLabel()])
            ->toArray();

        unset($arrivalFields['pj']);
        unset($arrivalFields['imprimerArrivage']);

        return new JsonResponse($this->renderView('settings/donnees/export/modalNewExportContent.html.twig', [
            "arrivalFields" => $arrivalFields
        ]));
    }

    #[Route("/annuler-export/{export}", name: "export_cancel", options: ["expose" => true], methods: "GET|POST", condition: "request.isXmlHttpRequest()")]
    public function cancel(Export $export, Request $request,
                           EntityManagerInterface $manager,
                           ScheduledExportService $scheduledExportService): JsonResponse {
        $statusRepository = $manager->getRepository(Statut::class);

        $exportType = $export->getType();
        $exportStatus = $export->getStatus();
        if ($exportStatus && $exportType && $exportType->getLabel() == Type::LABEL_SCHEDULED_EXPORT && $exportStatus->getNom() == Export::STATUS_SCHEDULED) {
            $export->setStatus($statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::EXPORT, Export::STATUS_CANCELLED));
            $manager->flush();
            $scheduledExportService->saveScheduledExportsCache($manager);
        }

        return new JsonResponse();
    }
}
