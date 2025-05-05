<?php

namespace App\Command\UserMessage;

use App\Entity\Setting;
use App\Service\Cache\CacheNamespaceEnum;
use App\Service\Cache\CacheService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user-message:clear',
    description: 'This command clears the user message. It can be used to hide the user message.'
)]
class ClearUserMessageCommand extends Command {

    public function __construct(private EntityManagerInterface $entityManager,
                                private SettingsService        $settingsService,
                                private CacheService           $cacheService) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = [Setting::USER_MESSAGE_CONFIG];

        $settingMessage = $this->settingsService->persistSetting($this->entityManager, $settings, Setting::USER_MESSAGE_CONFIG);
        $settingMessage->setValue(null);

        $this->entityManager->flush();

        $this->cacheService->delete(CacheNamespaceEnum::SETTINGS, Setting::USER_MESSAGE_CONFIG);

        return Command::SUCCESS;
    }
}
