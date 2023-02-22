<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\ExportScheduleRule;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Transport\TransportRound;
use App\Exceptions\FTPException;
use App\Helper\FormatHelper;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class ScheduledExportService
{
    #[Required]
    public CacheService $cacheService;

    #[Required]
    public CSVExportService $csvExportService;

    #[Required]
    public DataExportService $dataExportService;

    #[Required]
    public RefArticleDataService $refArticleDataService;

    #[Required]
    public ArticleDataService $articleDataService;

    #[Required]
    public ArrivageService $arrivageService;

    #[Required]
    public TransportRoundService $transportRoundService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Environment $templating;

    #[Required]
    public FTPService $ftpService;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    public function saveScheduledExportsCache(EntityManagerInterface $entityManager): void {
        $this->cacheService->set(CacheService::EXPORTS, "scheduled", $this->buildScheduledExportsCache($entityManager));
    }

    public function getScheduledCache(EntityManagerInterface $entityManager): array {
        return $this->cacheService->get(CacheService::EXPORTS, "scheduled", fn() => $this->buildScheduledExportsCache($entityManager));
    }

    private function buildScheduledExportsCache(EntityManagerInterface $entityManager): array {
        $exportRepository = $entityManager->getRepository(Export::class);

        return Stream::from($exportRepository->findScheduledExports())
            ->keymap(fn(Export $export) => [$export->getId(), $this->scheduleRuleService->calculateNextExecutionDate($export->getExportScheduleRule())])
            ->filter(fn(?DateTime $nextExecutionDate) => isset($nextExecutionDate))
            ->map(fn(DateTime $date) => $this->getScheduleExportKeyCache($date))
            ->reduce(function ($accumulator, $date, $id) {
                $accumulator[$date][] = $id;
                return $accumulator;
            }, []);
    }

    public function getScheduleExportKeyCache(DateTime $dateTime) {
        return $dateTime->format("Y-m-d-H-i");
    }

    public function export(EntityManagerInterface $entityManager, Export $export) {
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
            $referenceArticleRepository = $entityManager->getRepository(Article::class);
            $articles = $referenceArticleRepository->iterateAll($exportToRun->getCreator());
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
        } else if ($exportToRun->getEntity() === Export::ENTITY_REF_LOCATION) {
            $storageRules = $entityManager->getRepository(StorageRule::class)->iterateAll();

            $csvHeader = $this->dataExportService->createStorageRulesHeader();
            $this->csvExportService->putLine($output, $csvHeader);
            $this->dataExportService->exportRefLocation($storageRules, $output);
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
                    "FOLLOW GT // Export des $entity",
                    $this->templating->render("mails/contents/mailExportDone.twig", [
                        "entity" => $entity,
                        "export" => $exportToRun,
                        "frequency" => match ($exportToRun->getExportScheduleRule()?->getFrequency()) {
                            ExportScheduleRule::ONCE => "une fois",
                            ExportScheduleRule::HOURLY => "chaque heure",
                            ExportScheduleRule::DAILY => "chaque jour",
                            ExportScheduleRule::WEEKLY => "chaque semaine",
                            ExportScheduleRule::MONTHLY => "chaque mois",
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
        } else { // ftp export
            try {
                $this->ftpService->send($exportToRun->getFtpParameters(), $output);
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
            ->setForced(false)
            ->setBeganAt($start)
            ->setEndedAt(new DateTime());

        $export
            ->setForced(false)
            ->setNextExecution($this->scheduleRuleService->calculateNextExecutionDate($exportToRun->getExportScheduleRule()));

        $entityManager->persist($exportToRun);
        $entityManager->flush();

        @unlink($path);
    }

    private function getFrequencyDescription(Export $export): string {
        $rule = $export->getExportScheduleRule();
        if($rule->getFrequency() === ExportScheduleRule::DAILY) {
            $period = $rule->getPeriod();
            $periodStr = $period && $period > 1
                ? "$period jours"
                : "jours";
        } else if($rule->getFrequency() === ExportScheduleRule::WEEKLY) {
            $days = Stream::from($rule->getWeekDays())
                ->map(fn(int $weekDay) => FormatHelper::WEEK_DAYS[$weekDay])
                ->join(", ");
            $period = $rule->getPeriod();
            $periodStr = $period && $period > 1
                ? "$period semaines"
                : "semaines";
        } else if($rule->getFrequency() === ExportScheduleRule::MONTHLY) {
            $days = join(", ", $rule->getMonthDays());
            $months = Stream::from($rule->getMonths())
                ->map(fn(int $month) => FormatHelper::MONTHS[$month])
                ->join(", ");
        }

        return match($rule->getFrequency()) {
            ExportScheduleRule::ONCE => "le {$rule->getBegin()?->format("d/m/Y H:i")}",
            ExportScheduleRule::HOURLY => "toutes les {$rule->getIntervalPeriod()} heures",
            ExportScheduleRule::DAILY => "tous les $periodStr à {$rule->getIntervalTime()}",
            ExportScheduleRule::WEEKLY => "toutes les $periodStr à {$rule->getIntervalTime()} les $days",
            ExportScheduleRule::MONTHLY => "les mois de $months le $days",
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
            [Export::PERIOD_INTERVAL_MONTH, Export::PERIOD_PREVIOUS] => new DateTime("last day of last month 23:59:59"),
            [Export::PERIOD_INTERVAL_YEAR, Export::PERIOD_CURRENT] => new DateTime("last day of december this year 23:59:59"),
            [Export::PERIOD_INTERVAL_YEAR, Export::PERIOD_PREVIOUS] => new DateTime("last day of december last year 23:59:59"),
        };

        return [$startDate, $endDate];
    }

    private function cloneScheduledExport(Export $export) {
        if($export->getExportScheduleRule()->getFrequency() === ExportScheduleRule::ONCE) {
            return $export;
        }

        $rule = $export->getExportScheduleRule() ?? new ExportScheduleRule();

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
            ->setType($export->getType())
            ->setStatus($export->getStatus())
            ->setCreatedAt($export->getCreatedAt())
            ->setExportScheduleRule((new ExportScheduleRule())
                ->setFrequency($rule->getFrequency())
                ->setPeriod($rule->getPeriod())
                ->setIntervalTime($rule->getIntervalTime())
                ->setIntervalPeriod($rule->getIntervalPeriod())
                ->setBegin($rule->getBegin())
                ->setMonths($rule->getMonths())
                ->setMonthDays($rule->getMonthDays())
                ->setWeekDays($rule->getWeekDays()));
    }
}
