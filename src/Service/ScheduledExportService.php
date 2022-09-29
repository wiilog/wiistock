<?php

namespace App\Service;

use App\Controller\Settings\DataExportController;
use App\Entity\Article;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\ExportScheduleRule;
use App\Entity\FreeField;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Transport\TransportRound;
use App\Exceptions\FTPException;
use App\Helper\FormatHelper;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Net\SFTP;
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
    public TransportRoundService $transportRoundService;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Environment $templating;

    #[Required]
    public FTPService $ftpService;

    public function saveScheduledExportsCache(EntityManagerInterface $entityManager): void {
        $this->cacheService->set(CacheService::EXPORTS, $this->buildScheduledExportsCache($entityManager));
    }

    public function getScheduledCache(EntityManagerInterface $entityManager): array {
        return $this->cacheService->get(CacheService::EXPORTS, fn() => $this->buildScheduledExportsCache($entityManager));
    }

    private function buildScheduledExportsCache(EntityManagerInterface $entityManager): array {
        $exportRepository = $entityManager->getRepository(Export::class);

        return Stream::from($exportRepository->findScheduledExports())
            ->keymap(fn(Export $export) => [$export->getId(), $this->calculateNextExecutionDate($export)])
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

    public function calculateNextExecutionDate(Export $export): ?DateTime {
        $now = new DateTime();
        $now->setTime($now->format('H'), $now->format('i'), 0, 0);
        $rule = $export->getExportScheduleRule();
        $now = new DateTime();
        $now->setTime($now->format('H'), $now->format('i'), 0, 0);

        $executionDate = match ($rule->getFrequency()) {
            ExportScheduleRule::ONCE => $this->calculateOnce($rule, $now),
            ExportScheduleRule::DAILY => $this->calculateFromDailyRule($rule, $now),
            ExportScheduleRule::WEEKLY => $this->calculateFromWeeklyRule($rule, $now),
            ExportScheduleRule::HOURLY => $this->calculateFromHourlyRule($rule, $now),
            ExportScheduleRule::MONTHLY => $this->calculateFromMonthlyRule($rule, $now),
            default => throw new RuntimeException('Invalid schedule rule frequency'),
        };
        if ($export->isForced()) {
            $now->setTime($now->format('H'), ((int)$now->format('i')) + 2, 0, 0);
            $executionDate = min($now, $executionDate);
        }
        return $executionDate;
    }

    public function calculateFromWeeklyRule(ExportScheduleRule $rule, DateTime $now): ?DateTime {
        $DAY_LABEL = [
            1 => "monday",
            2 => "tuesday",
            3 => "wednesday",
            4 => "thursday",
            5 => "friday",
            6 => "saturday",
            7 => "sunday",
        ];

        [$hour, $minute] = explode(":", $rule->getIntervalTime());
        $nextOccurrence = clone $rule->getBegin();

        $weeksDifferential = floor($now->diff($rule->getBegin())->days / 7);
        $add = $weeksDifferential + $weeksDifferential % $rule->getPeriod();
        $nextOccurrence->modify("+$add weeks");

        $goToNextWeek = false;
        if ($now->format("W") != $nextOccurrence->format("W")) {
            $day = $rule->getWeekDays()[0];
        } else {
            $isTimeEqualOrBefore = $this->isTimeEqualOrBefore($rule->getIntervalTime(), $now);
            $currentDay = $now->format("N");

            $day = Stream::from($rule->getWeekDays())
                ->filter(fn($day) => $isTimeEqualOrBefore ? $day > $currentDay : $day >= $currentDay)
                ->firstOr(function() use ($rule, &$goToNextWeek) {
                    $goToNextWeek = true;
                    return $rule->getWeekDays()[0];
                });
        }

        if ($goToNextWeek) {
            $nextOccurrence->modify("+{$rule->getPeriod()} week");
        }

        $dayAsString = $DAY_LABEL[$day];
        $nextOccurrence->modify("$dayAsString this week");
        $nextOccurrence->setTime($hour, $minute);

        return $nextOccurrence;
    }

    public function calculateFromMonthlyRule(ExportScheduleRule $rule, DateTime $now): ?DateTime {
        $start = ($now > $rule->getBegin()) ? $now : $rule->getBegin();
        $isTimeEqualOrBefore = $this->isTimeEqualOrBefore($rule->getIntervalTime(), $start);

        $year = $start->format("Y");
        $currentMonth = $start->format("n");
        $currentDay = (int) $start->format("j");
        $currentLastDayMonth = (int) (clone $start)
            ->modify('last day this month')
            ->format("j");

        $day = Stream::from($rule->getMonthDays())
            ->filter(function ($day) use ($isTimeEqualOrBefore, $currentDay) {
                $day = $day === ExportScheduleRule::LAST_DAY_OF_WEEK ? 32 : $day;
                return $isTimeEqualOrBefore
                    ? $day > $currentDay
                    : $day >= $currentDay;
            })
            ->firstOr(fn() => $rule->getMonthDays()[0]);

        $day = $day !== ExportScheduleRule::LAST_DAY_OF_WEEK ? $day : $currentLastDayMonth;
        $isDayEqual = $day == $currentDay;
        $isDayBefore = $day < $currentDay;

        $ignoreCurrentMonth = $isDayBefore || ($isDayEqual && $isTimeEqualOrBefore);

        $month = Stream::from($rule->getMonths())
            ->filter(fn($month) => $ignoreCurrentMonth ? $month > $currentMonth : $month >= $currentMonth)
            ->firstOr(function() use ($rule, &$year) {
                $year += 1;
                return $rule->getMonths()[0];
            });

        return DateTime::createFromFormat("d/m/Y H:i", "$day/$month/$year {$rule->getIntervalTime()}");
    }

    public function calculateFromDailyRule(ExportScheduleRule $rule, DateTime $now): ?DateTime {
        $start = $rule->getBegin();
        // set time to 0
        $start->setTime(0, 0);
        $period = $rule->getPeriod();
        [$hour, $minute] = explode(":", $rule->getIntervalTime());

        if ($now >= $start) {
            $nextOccurrence = clone $start;
            $daysDifferential = $now->diff($start)->days;

            $add = $daysDifferential - $daysDifferential % $period;
            if ($add < $daysDifferential) {
                $add += $period;
            }
            $nextOccurrence->modify("+$add day");
            $nextOccurrence->setTime($hour, $minute);

            if ($this->isTimeEqualOrBefore($rule->getIntervalTime(), $now)) {
                $nextOccurrence->modify("+1 day");
            }
        } else {
            $nextOccurrence = clone $start;
            $nextOccurrence->setTime($hour, $minute);
        }

        return $nextOccurrence;
    }

    public function calculateFromHourlyRule(ExportScheduleRule $rule, DateTime $now): ?DateTime {
        $start = clone $rule->getBegin();
        $start->setTime(0, 0, 0, 0);
        $intervalPeriod = $rule->getIntervalPeriod();

        if ($intervalPeriod) {
            if ($now >= $start) {
                $nextOccurrence = clone $now;
                $hours = (int)$now->format('H');
                $minutes = 0;
                $hours = $hours + ($intervalPeriod - ($hours % $intervalPeriod));
                $nextOccurrence->setTime($hours, $minutes);
            } else {
                $nextOccurrence = $this->calculateOnce($rule, $now);
            }

            return $nextOccurrence;
        }
        return null;
    }

    public function calculateOnce(ExportScheduleRule $rule, DateTime $now): ?DateTime {
        return $now <= $rule->getBegin()
            ? $rule->getBegin()
            : null;
    }

    private function isTimeEqualOrBefore(string $time, DateTime $date) {
        [$hour, $minute] = explode(":", $time);
        return $date->format('H') > $hour || ($date->format('H') == $hour && $date->format('i') >= $minute);
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
            //TODO: exporter les arrivages
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
            ->setNextExecution($this->calculateNextExecutionDate($exportToRun));


        $entityManager->persist($exportToRun);
        $entityManager->flush();

        @unlink($path);
    }

    private function getFrequencyDescription(Export $export): string {
        $rule = $export->getExportScheduleRule();
        if($rule->getFrequency() === ExportScheduleRule::WEEKLY) {
            $days = Stream::from($rule->getWeekDays())
                ->map(fn(int $weekDay) => FormatHelper::WEEK_DAYS[$weekDay])
                ->join(", ");
        } else if($rule->getFrequency() === ExportScheduleRule::MONTHLY) {
            $days = join(", ", $rule->getMonthDays());
            $months = Stream::from($rule->getMonths())
                ->map(fn(int $month) => FormatHelper::MONTHS[$month])
                ->join(", ");
        }

        return match($rule->getFrequency()) {
            ExportScheduleRule::ONCE => "le {$rule->getBegin()->format("d/m/Y H:i")}",
            ExportScheduleRule::HOURLY => "toutes les {$rule->getIntervalPeriod()} heures",
            ExportScheduleRule::DAILY => "tous les {$rule->getPeriod()} à {$rule->getIntervalTime()}",
            ExportScheduleRule::WEEKLY => "toutes les {$rule->getFrequency()} semaines à {$rule->getIntervalTime()} les {$days}",
            ExportScheduleRule::MONTHLY => "les mois de {$months} le {$days}",
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
                ->setExport($export)
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
