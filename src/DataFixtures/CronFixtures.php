<?php

namespace App\DataFixtures;

use App\Command\AverageRequestTimeCommand;
use App\Command\CheckPairingValidityCommand;
use App\Command\DashboardFeedCommand;
use App\Command\GenerateAlertsCommand;
use App\Command\InventoryStatusUpdateCommand;
use App\Command\LaunchScheduledImportCommand;
use App\Command\LaunchUniqueImportCommand;
use App\Command\MailsLitigesComand;
use App\Command\RemindPackDeliveriesCommand;
use App\Command\ScheduledExportCommand;
use App\Command\ScheduledPurchaseRequestCommand;
use App\Command\ScheduleInventoryMissionCommand;
use App\Command\Sessions\CloseInactiveSessionsCommand;
use Cron\CronBundle\Entity\CronJob;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CronFixtures extends Fixture implements FixtureGroupInterface {

    public const CRON_JOBS = [
        [
            'name' => 'unique-imports',
            'command' => LaunchUniqueImportCommand::COMMAND_NAME,
            'schedule' => '*/30 * * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'scheduled-imports',
            'command' => LaunchScheduledImportCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'scheduled-missions',
            'command' => ScheduleInventoryMissionCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'dashboard-feeds',
            'command' => DashboardFeedCommand::COMMAND_NAME,
            'schedule' => '*/5 * * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'scheduled-exports',
            'command' => ScheduledExportCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'average-requests',
            'command' => AverageRequestTimeCommand::COMMAND_NAME,
            'schedule' => '0 20 * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'alerts',
            'command' => GenerateAlertsCommand::COMMAND_NAME,
            'schedule' => '0 20 * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'dispute-mails',
            'command' => MailsLitigesComand::COMMAND_NAME,
            'schedule' => '0 8 * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'remind-pack-deliveries',
            'command' => RemindPackDeliveriesCommand::COMMAND_NAME,
            'schedule' => '0 8 * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'cleanup-pairings',
            'command' => CheckPairingValidityCommand::COMMAND_NAME,
            'schedule' => '*/10 * * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'inventory-status',
            'command' => InventoryStatusUpdateCommand::COMMAND_NAME,
            'schedule' => '0 22 * * 0',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'scheduled-purchase-request',
            'command' => ScheduledPurchaseRequestCommand::COMMAND_NAME,
            'schedule' => '* * * * *',
            'description' => '',
            'enabled' => true
        ],
        [
            'name' => 'close-inactive-sessions',
            'command' => CloseInactiveSessionsCommand::COMMAND_NAME,
            'schedule' => '*/5 * * * *',
            'description' => '',
            'enabled' => true
        ],
    ];

    public function load(ObjectManager $manager): void
    {

        foreach (self::CRON_JOBS as $cronJobData) {
            $cronJob = (new CronJob())
                ->setName($cronJobData['name'])
                ->setCommand($cronJobData['command'])
                ->setSchedule($cronJobData['schedule'])
                ->setDescription($cronJobData['description'])
                ->setEnabled($cronJobData['enabled']);

            $manager->persist($cronJob);

        }
        $manager->flush();
    }

    public static function getGroups(): array {
        return ['cron'];
    }

}
