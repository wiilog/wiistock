<?php

namespace App\Command\WarningHeader;

use App\Entity\Setting;
use App\Service\CacheService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:warning-header:set',
    description: 'This command sets the warning header message. It can be used to hide the warning header message.'
)]
class WarningHeaderSetCommand extends Command
{
    private const DEFAULT_COLOR = '#d9534f';

    public function __construct(private  EntityManagerInterface  $entityManager,
                                private  SettingsService         $settingsService,
                                private  CacheService            $cacheService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'The message to display')
            ->addOption('color', 'c', InputOption::VALUE_OPTIONAL, 'The color of the message');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getOption('message');
        $color = $input->getOption('color');

        if ($message) {
            $output->writeln("Warning header message pushed successfully");
        } else {
            throw new RuntimeCommandException('No message provided, clearing warning header message');
        }

        $warningHeader = json_encode(
            [
                "color" => $color ?? self::DEFAULT_COLOR,
                "message" => $message,
                "messageHash" => hash('sha256', $message)
            ]
        );

        $settings = [Setting::WARNING_HEADER];

        $settingMessage = $this->settingsService->persistSetting($this->entityManager, $settings, Setting::WARNING_HEADER);
        $settingMessage->setValue($warningHeader);

        $this->entityManager->flush();

        $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, Setting::WARNING_HEADER);

        return Command::SUCCESS;
    }
}
