<?php

namespace App\Command\WarningHeader;

use App\Entity\Setting;
use App\Service\CacheService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:warning-header:clear',
    description: 'This command clears the warning header message. It can be used to hide the warning header message.'
)]
class WarningHeaderClearCommand extends Command
{

    public function __construct(private  EntityManagerInterface  $entityManager,
                                private  SettingsService         $settingsService,
                                private  CacheService            $cacheService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = [Setting::WARNING_HEADER];

        $settingMessage = $this->settingsService->persistSetting($this->entityManager, $settings, Setting::WARNING_HEADER);
        $settingMessage->setValue(null);

        $this->entityManager->flush();

        $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, Setting::WARNING_HEADER);

        return Command::SUCCESS;
    }
}
