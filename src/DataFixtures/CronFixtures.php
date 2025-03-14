<?php

namespace App\DataFixtures;

use App\Command\Cron\AverageRequestTimeCommand;
use App\Command\Cron\CheckPairingValidityCommand;
use App\Command\Cron\CloseInactiveSessionsCommand;
use App\Command\Cron\DashboardFeedCommand;
use App\Command\Cron\GenerateAlertsCommand;
use App\Command\Cron\InventoryStatusUpdateCommand;
use App\Command\Cron\KooveaHubsCommand;
use App\Command\Cron\KooveaTagsCommand;
use App\Command\Cron\LaunchUniqueImportCommand;
use App\Command\Cron\MailsLitigesComand;
use App\Command\Cron\RemindPackDeliveriesCommand;
use App\Command\Cron\ScheduledTask\LaunchScheduledImportCommand;
use App\Command\Cron\ScheduledTask\ScheduledExportCommand;
use App\Command\Cron\ScheduledTask\ScheduledPurchaseRequestCommand;
use App\Command\Cron\ScheduledTask\ScheduledSleepingStockAlerts;
use App\Command\Cron\ScheduledTask\ScheduleInventoryMissionCommand;
use App\Command\InactiveSensorsCommand;
use App\Service\SpecificService;
use Cron\CronBundle\Entity\CronJob;
use Cron\CronBundle\Entity\CronReport;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class CronFixtures extends Fixture implements FixtureGroupInterface {

    public function __construct(
        private  SpecificService $specificService,
    ) {}

    private const CRON_JOBS = [
        [
            'command' => LaunchUniqueImportCommand::COMMAND_NAME,
            'schedule' => '*/30 * * * *',
            'description' => '',
        ],
        [
            'command' => LaunchScheduledImportCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
        ],
        [
            'command' => ScheduleInventoryMissionCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
        ],
        [
            'command' => DashboardFeedCommand::COMMAND_NAME,
            'schedule' => [
                SpecificService::CLIENT_BARBECUE => '*/' . SpecificService::SPECIFIC_DASHBOARD_REFRESH_RATE[SpecificService::CLIENT_BARBECUE] .' * * * *',
                SpecificService::CLIENT_BOURGUIGNON => '*/' . SpecificService::SPECIFIC_DASHBOARD_REFRESH_RATE[SpecificService::CLIENT_BOURGUIGNON] .' * * * *',
                SpecificService::CLIENT_POTEE => '*/' . SpecificService::SPECIFIC_DASHBOARD_REFRESH_RATE[SpecificService::CLIENT_POTEE] . ' * * * *',
                SpecificService::CLIENT_QUICHE => '*/' . SpecificService::SPECIFIC_DASHBOARD_REFRESH_RATE[SpecificService::CLIENT_QUICHE] . ' * * * *',
                SpecificService::CLIENT_CROUSTADE => '*/' . SpecificService::SPECIFIC_DASHBOARD_REFRESH_RATE[SpecificService::CLIENT_CROUSTADE] . ' * * * *',
                'default' => '*/' . SpecificService::DEFAULT_DASHBOARD_REFRESH_RATE. ' * * * *',
            ],
            'description' => '',
        ],
        [
            'command' => ScheduledExportCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
        ],
        [
            'command' => AverageRequestTimeCommand::COMMAND_NAME,
            'schedule' => '0 20 * * *',
            'description' => '',
        ],
        [
            'command' => GenerateAlertsCommand::COMMAND_NAME,
            'schedule' => '0 20 * * *',
            'description' => '',
        ],
        [
            'command' => MailsLitigesComand::COMMAND_NAME,
            'schedule' => [
                SpecificService::CLIENT_POTEE => '0 8 * * *',
                SpecificService::CLIENT_QUICHE => '0 8 * * *',
            ],
            'description' => '',
        ],
        [
            'command' => RemindPackDeliveriesCommand::COMMAND_NAME,
            'schedule' => '0 8 * * *',
            'description' => '',
        ],
        [
            'command' => CheckPairingValidityCommand::COMMAND_NAME,
            'schedule' => '*/10 * * * *',
            'description' => '',
        ],
        [
            'command' => InventoryStatusUpdateCommand::COMMAND_NAME,
            'schedule' => '0 22 * * 0',
            'description' => '',
        ],
        [
            'command' => ScheduledPurchaseRequestCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
        ],
        [
            'command' => CloseInactiveSessionsCommand::COMMAND_NAME,
            'schedule' => '*/5 * * * *',
            'description' => '',
        ],
        [
            'command' => KooveaHubsCommand::COMMAND_NAME,
            'schedule' => [
                SpecificService::CLIENT_SAUCISSON_BRIOCHE => '*/1 * * * *'
            ],
            'description' => '',
        ],
        [
            'command' => KooveaTagsCommand::COMMAND_NAME,
            'schedule' => [
                SpecificService::CLIENT_SAUCISSON_BRIOCHE => '*/5 * * * *'
            ],
            'description' => '',
        ],
        // Clear all reports in job_report table older than 3 days
        // Execute each day at midnight
        [
            'command' => 'cron:reports:truncate',
            'schedule' => '0 0 * * *',
            'description' => '',
        ],
        [
            'command' => InactiveSensorsCommand::COMMAND_NAME,
            'schedule' => '*/1 * * * *',
            'description' => '',
        ],
        [
            'command' => ScheduledSleepingStockAlerts::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
        ]
    ];

    public function load(ObjectManager $manager): void {
        $cronJobRepository = $manager->getRepository(CronJob::class);

        $manager->createQueryBuilder()
            ->delete(CronReport::class, "cron_report")
            ->getQuery()
            ->execute();

        $existingCronJobs = $cronJobRepository->findAll();
        foreach ($existingCronJobs as $cronJob) {
            $manager->remove($cronJob);
        }

        $manager->flush();

        foreach (self::CRON_JOBS as $cronJobData) {
            $schedule = $this->getSchedule($cronJobData);
            if ($schedule) {
                $name = str_replace(':', '-', $cronJobData['command']);
                $cronJob = (new CronJob())
                    ->setName($name)
                    ->setCommand($cronJobData['command'])
                    ->setSchedule($schedule)
                    ->setDescription($cronJobData['description'])
                    ->setEnabled(true);

                $manager->persist($cronJob);
            }
        }
        $manager->flush();

        $output = new ConsoleOutput();
        $output->writeln("New cron job synchronised");
    }

    public function getSchedule(array $cronJobData): ?string {
        $schedule = $cronJobData['schedule'];
        if (is_string($schedule)) {
            return $schedule;
        }

        // is_array($schedule)
        $appClient = $this->specificService->getAppClient();
        return $schedule[$appClient]
            ?? $schedule['default']
            ?? null;
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }

}
