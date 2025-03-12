<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\Language;
use App\Entity\ProductionRequest;
use App\Entity\ReferenceArticle;
use App\Entity\ScheduledTask\Export;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\Transport\TransportRound;
use App\Exceptions\FTPException;
use App\Helper\LanguageHelper;
use App\Service\Tracking\PackService;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\InputBag;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class ScheduledExportService {
    public function __construct(
        private PackService               $packService,
        private TranslationService        $translationService,
        private LanguageService           $languageService,
        private FTPService                $ftpService,
        private Environment               $templating,
        private MailerService             $mailerService,
        private FreeFieldService          $freeFieldService,
        private TransportRoundService     $transportRoundService,
        private ArrivageService           $arrivageService,
        private ArticleDataService        $articleDataService,
        private RefArticleDataService     $refArticleDataService,
        private DataExportService         $dataExportService,
        private CSVExportService          $csvExportService,
        private TruckArrivalService       $truckArrivalService,
        private ReceiptAssociationService $receiptAssociationService,
        private DisputeService            $disputeService,
    ) {}

    public function export(EntityManagerInterface $entityManager,
                           Export                 $export,
                           DateTime               $taskExecution): void {
        $statusRepository = $entityManager->getRepository(Statut::class);

        $start = new DateTime();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "export-{$export->getEntity()}-$today.csv";
        $output = fopen($path, "x+");

        $exportToRun = $this->cloneScheduledExport($export);

        if($exportToRun->getEntity() === Export::ENTITY_REFERENCE) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $references = $referenceArticleRepository->iterateAll($exportToRun->getCreator());
            $freeFieldsConfig = $this->freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::REFERENCE_ARTICLE], [CategoryType::ARTICLE]);

            $this->csvExportService->putLine($output, $this->dataExportService->createReferencesHeader($freeFieldsConfig));

            $this->dataExportService->exportReferences($this->refArticleDataService, $freeFieldsConfig, $references, $output);
        } else if($exportToRun->getEntity() === Export::ENTITY_ARTICLE) {
            $articleRepository = $entityManager->getRepository(Article::class);

            $options = [];
            if ($exportToRun->getStockEntryStartDate() !== null && $exportToRun->getStockEntryEndDate() !== null) {
                $options["dateMin"] = $exportToRun->getStockEntryStartDate();
                $options["dateMax"] = $exportToRun->getStockEntryEndDate();
            }else if ($exportToRun->getStockEntryMinusDay() !== null && $exportToRun->getStockEntryAdditionalDay() !== null){
                $now = new DateTime("now");
                $options["dateMin"] = (clone $now)->modify("-{$exportToRun->getStockEntryMinusDay()} days");
                $options["dateMax"] = (clone $options["dateMin"])->modify("-{$exportToRun->getStockEntryAdditionalDay()} days");
            }
            if (!empty($exportToRun->getReferenceTypes())) {
                $options["referenceTypes"] = $exportToRun->getReferenceTypes();
            }
            if (!empty($exportToRun->getStatuses())) {
                $options["statuses"] = $exportToRun->getStatuses();
            }
            if (!empty($exportToRun->getSuppliers())) {
                $options["suppliers"] = $exportToRun->getSuppliers();
            }

            $articles = $articleRepository->iterateAll(
                $exportToRun->getCreator(),
                $options);
            $freeFieldsConfig = $this->freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::ARTICLE], [CategoryType::ARTICLE]);

            $this->csvExportService->putLine($output, $this->dataExportService->createArticlesHeader($freeFieldsConfig));
            $this->dataExportService->exportArticles($this->articleDataService, $freeFieldsConfig, $articles, $output);
        } else if($exportToRun->getEntity() === Export::ENTITY_DELIVERY_ROUND) {
            $transportRoundRepository = $entityManager->getRepository(TransportRound::class);

            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $transportRounds = $transportRoundRepository->iterateFinishedTransportRounds($startDate, $endDate);

            $this->csvExportService->putLine($output, $this->dataExportService->createDeliveryRoundHeader());
            $this->dataExportService->exportTransportRounds($this->transportRoundService, $transportRounds, $output, $startDate, $endDate);
        } else if($exportToRun->getEntity() === Export::ENTITY_ARRIVAL) {
            $arrivalRepository = $entityManager->getRepository(Arrivage::class);
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $arrivals = $arrivalRepository->iterateArrivals($startDate, $endDate);

            $this->arrivageService->launchExportCache($entityManager, $startDate, $endDate);

            $csvHeader = $this->dataExportService->createArrivalsHeader($entityManager, $exportToRun->getColumnToExport());
            $this->csvExportService->putLine($output, $csvHeader);
            $this->dataExportService->exportArrivages($arrivals, $output, $exportToRun->getColumnToExport());
        } else if($exportToRun->getEntity() === Export::ENTITY_TRACKING_MOVEMENT) {
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $trackingMovements = $trackingMovementRepository->getByDates($startDate, $endDate);

            $freeFieldsConfig = $this->freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::MVT_TRACA]);
            $columnToExport = $exportToRun->getColumnToExport();

            $csvHeader = $this->dataExportService->createTrackingMovementsHeader($entityManager, $columnToExport);
            $this->csvExportService->putLine($output, $csvHeader);
            $this->dataExportService->exportTrackingMovements($trackingMovements, $output, $columnToExport, $freeFieldsConfig);
        } else if ($exportToRun->getEntity() === Export::ENTITY_REF_LOCATION) {
            $storageRules = $entityManager->getRepository(StorageRule::class)->iterateAll();

            $csvHeader = $this->dataExportService->createStorageRulesHeader();
            $this->csvExportService->putLine($output, $csvHeader);
            $this->dataExportService->exportRefLocation($storageRules, $output);
        } else if($exportToRun->getEntity() === Export::ENTITY_DISPATCH) {
            $dispatchRepository = $entityManager->getRepository(Dispatch::class);
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $dispatches = $dispatchRepository->getByDates($startDate, $endDate);

            $freeFieldsById = Stream::from($dispatches)
                ->keymap(fn($dispatch) => [
                    $dispatch['id'],
                    $dispatch['freeFields']
                ])
                ->toArray();
            $freeFieldsConfig = $this->freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_DISPATCH]);
            $columnToExport = $exportToRun->getColumnToExport();

            $this->csvExportService->putLine($output, $this->dataExportService->createDispatchesHeader($entityManager, $columnToExport));
            $this->dataExportService->exportDispatch($dispatches, $output, $columnToExport, $freeFieldsConfig, $freeFieldsById);
        } else if($exportToRun->getEntity() === Export::ENTITY_PRODUCTION) {
            $productionRequestRepository = $entityManager->getRepository(ProductionRequest::class);
            $languageRepository = $entityManager->getRepository(Language::class);

            $freeFieldsConfig = $this->freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::PRODUCTION_REQUEST]);
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);

            $defaultSlug = LanguageHelper::clearLanguage($this->languageService->getDefaultSlug());
            $defaultLanguage = $languageRepository->findOneBy(["slug" => $defaultSlug]);
            $language = $languageRepository->findOneBy(["selected" => 1]);

            $productionRequests = $productionRequestRepository->getByDates(
                $startDate,
                $endDate,
                new InputBag([
                    "date-choice_createdAt" => true,
                ]),
                [
                    "userDateFormat" => Language::DMY_FORMAT,
                    "defaultLanguage" => $defaultLanguage,
                    "language" => $language,
                ]
            );

            $freeFieldsById = Stream::from($productionRequests)
                ->keymap(static fn(array $productionRequest) => [
                    $productionRequest['id'],
                    $productionRequest['freeFields']
                ])
                ->toArray();

            $this->csvExportService->putLine($output, $this->dataExportService->createProductionRequestsHeader());
            $this->dataExportService->exportProductionRequest($productionRequests, $output, $freeFieldsConfig, $freeFieldsById);
        } else if($exportToRun->getEntity() === Export::ENTITY_PACK) {
            $this->csvExportService->putLine($output, $this->packService->getCsvHeader());
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $this->packService->getExportPacksFunction($startDate, $endDate, $entityManager)($output);
        } else if($exportToRun->getEntity() === Export::ENTITY_TRUCK_ARRIVAL) {
            $this->csvExportService->putLine($output, $this->truckArrivalService->getCsvHeader());
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $this->truckArrivalService->getExportFunction($startDate, $endDate, $entityManager)($output);
        } else if($exportToRun->getEntity() === Export::ENTITY_RECEIPT_ASSOCIATION) {
            $this->csvExportService->putLine($output, $this->receiptAssociationService->getCsvHeader());
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $this->receiptAssociationService->getExportReceiptAssociationFunction($startDate, $endDate, $entityManager)($output);
        } else if($exportToRun->getEntity() === Export::ENTITY_DISPUTE) {
            $this->csvExportService->putLine($output, $this->disputeService->getCsvHeader());
            [$startDate, $endDate] = $this->getExportBoundaries($exportToRun);
            $this->disputeService->getExportGenerator($entityManager, $startDate, $endDate)($output);
        } else {
            throw new RuntimeException("Unknown entity type");
        }

        if($exportToRun->getDestinationType() == Export::DESTINATION_EMAIL) {
            $entity = strtolower(Export::ENTITY_LABELS[$exportToRun->getEntity()]);

            @fclose($output);

            if(filesize($path) >= 20_000_000) {
                $exportToRun->setError("L'export est trop volumineux pour être envoyé par mail (maximum 20MO)");
            } else {
                $this->mailerService->sendMail(
                    $entityManager,
                    $this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . "Export des $entity",
                    $this->templating->render("mails/contents/mailExportDone.twig", [
                        "entity" => $entity,
                        "export" => $exportToRun,
                        "frequency" => match ($exportToRun->getScheduleRule()?->getFrequency()) {
                            ScheduleRule::ONCE => "une fois",
                            ScheduleRule::HOURLY => "chaque heure",
                            ScheduleRule::DAILY => "chaque jour",
                            ScheduleRule::WEEKLY => "chaque semaine",
                            ScheduleRule::MONTHLY => "chaque mois",
                            default => null,
                        },
                        "setting" => $this->getFrequencyDescription($exportToRun),
                        "showMore" => in_array($exportToRun->getEntity(), [Export::ENTITY_ARRIVAL, Export::ENTITY_DELIVERY_ROUND]),
                    ]),
                    Stream::empty()
                        ->concat($exportToRun->getRecipientEmails())
                        ->concat($exportToRun->getRecipientUsers())
                        ->toArray(),
                    [$path],
                );
            }
        } else if($exportToRun->getDestinationType() == Export::DESTINATION_SFTP) {
            try {
                $FTPParameters = $exportToRun->getFtpParameters();
                $this->ftpService->send([
                    'host' => $FTPParameters['host'],
                    'port' => $FTPParameters['port'],
                    'user' => $FTPParameters['user'],
                    'pass' => $FTPParameters['pass'],
                ], $FTPParameters['path'], $output);
            } catch(FTPException $exception) {
                $exportToRun->setError($exception->getMessage());
            } finally {
                @fclose($output);
            }
        }

        if($exportToRun->getError()) {
            $errorStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::EXPORT, Export::STATUS_ERROR);
            $exportToRun->setStatus($errorStatus);
        } else {
            $finished = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::EXPORT, Export::STATUS_FINISHED);
            $exportToRun->setStatus($finished);
        }

        $exportToRun
            ->setBeganAt($start)
            ->setEndedAt(new DateTime());

        $exportToRun->setLastRun($taskExecution);

        $entityManager->persist($exportToRun);
        $entityManager->flush();

        @unlink($path);
    }

    private function getFrequencyDescription(Export $export): string {
        $rule = $export->getScheduleRule();
        if($rule->getFrequency() === ScheduleRule::DAILY) {
            $period = $rule->getPeriod();
            $periodStr = $period && $period > 1
                ? "$period jours"
                : "jours";
        } else if($rule->getFrequency() === ScheduleRule::WEEKLY) {
            $days = Stream::from($rule->getWeekDays())
                ->map(fn(int $weekDay) => FormatService::WEEK_DAYS[$weekDay])
                ->join(", ");
            $period = $rule->getPeriod();
            $periodStr = $period && $period > 1
                ? "$period semaines"
                : "semaines";
        } else if($rule->getFrequency() === ScheduleRule::MONTHLY) {
            $days = join(", ", $rule->getMonthDays());
            $months = Stream::from($rule->getMonths())
                ->map(fn(int $month) => FormatService::MONTHS[$month])
                ->join(", ");
        }

        return match($rule->getFrequency()) {
            ScheduleRule::ONCE => "le {$rule->getBegin()?->format("d/m/Y à H:i")}",
            ScheduleRule::HOURLY => "toutes les {$rule->getIntervalPeriod()} heures",
            ScheduleRule::DAILY => "tous les $periodStr à {$rule->getIntervalTime()}",
            ScheduleRule::WEEKLY => "toutes les $periodStr à {$rule->getIntervalTime()} les $days",
            ScheduleRule::MONTHLY => "les mois de $months le $days",
            default => null,
        };
    }

    private function getExportBoundaries(Export $export): array {
        $startDate = match ([$export->getPeriodInterval(), $export->getPeriod()]) {
            [Export::PERIOD_INTERVAL_DAY, Export::PERIOD_CURRENT] => new DateTime("today 00:00"),
            [Export::PERIOD_INTERVAL_DAY, Export::PERIOD_PREVIOUS] => new DateTime("yesterday 00:00"),
            [Export::PERIOD_INTERVAL_WEEK, Export::PERIOD_CURRENT] => new DateTime("monday this week 00:00"),
            [Export::PERIOD_INTERVAL_WEEK, Export::PERIOD_PREVIOUS] => new DateTime("monday last week 00:00"),
            [Export::PERIOD_INTERVAL_MONTH, Export::PERIOD_CURRENT] => new DateTime("first day of this month 00:00"),
            [Export::PERIOD_INTERVAL_MONTH, Export::PERIOD_CURRENT_3] => (new DateTime("first day of this month 00:00"))->modify("-3 months"),
            [Export::PERIOD_INTERVAL_MONTH, Export::PERIOD_PREVIOUS] => new DateTime("first day of last month 00:00"),
            [Export::PERIOD_INTERVAL_YEAR, Export::PERIOD_CURRENT] => new DateTime("first day of january this year 00:00"),
            [Export::PERIOD_INTERVAL_YEAR, Export::PERIOD_PREVIOUS] => new DateTime("first day of january last year 00:00"),
        };

        $endDate = match ([$export->getPeriodInterval(), $export->getPeriod()]) {
            [Export::PERIOD_INTERVAL_DAY, Export::PERIOD_CURRENT] => new DateTime("today 23:59:59"),
            [Export::PERIOD_INTERVAL_DAY, Export::PERIOD_PREVIOUS] => new DateTime("yesterday 23:59:59"),
            [Export::PERIOD_INTERVAL_WEEK, Export::PERIOD_CURRENT] => new DateTime("sunday this week 23:59:59"),
            [Export::PERIOD_INTERVAL_WEEK, Export::PERIOD_PREVIOUS] => new DateTime("sunday last week 23:59:59"),
            [Export::PERIOD_INTERVAL_MONTH, Export::PERIOD_CURRENT] => new DateTime("last day of this month 23:59:59"),
            [Export::PERIOD_INTERVAL_MONTH, Export::PERIOD_CURRENT_3] => new DateTime("last day of this month 23:59:59"),
            [Export::PERIOD_INTERVAL_MONTH, Export::PERIOD_PREVIOUS] => new DateTime("last day of last month 23:59:59"),
            [Export::PERIOD_INTERVAL_YEAR, Export::PERIOD_CURRENT] => new DateTime("last day of december this year 23:59:59"),
            [Export::PERIOD_INTERVAL_YEAR, Export::PERIOD_PREVIOUS] => new DateTime("last day of december last year 23:59:59"),
        };

        return [$startDate, $endDate];
    }

    private function cloneScheduledExport(Export $export): Export {
        if($export->getScheduleRule()->getFrequency() === ScheduleRule::ONCE) {
            return $export;
        }

        $ruleToClone = $export->getScheduleRule() ?? new ScheduleRule();

        return (new Export())
            ->setEntity($export->getEntity())
            ->setPeriod($export->getPeriod())
            ->setPeriodInterval($export->getPeriodInterval())
            ->setFtpParameters($export->getFtpParameters())
            ->setRecipientEmails($export->getRecipientEmails())
            ->setRecipientUsers($export->getRecipientUsers())
            ->setDestinationType($export->getDestinationType())
            ->setCreator($export->getCreator())
            ->setColumnToExport($export->getColumnToExport())
            ->setStockEntryStartDate($export->getStockEntryStartDate())
            ->setStockEntryEndDate($export->getStockEntryEndDate())
            ->setStockEntryMinusDay($export->getStockEntryMinusDay())
            ->setStockEntryAdditionalDay($export->getStockEntryAdditionalDay())
            ->setType($export->getType())
            ->setStatus($export->getStatus())
            ->setCreatedAt($export->getCreatedAt())
            ->setScheduleRule($ruleToClone->clone());
    }
}
