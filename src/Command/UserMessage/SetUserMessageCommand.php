<?php

namespace App\Command\UserMessage;

use App\Entity\Setting;
use App\Service\Cache\CacheNamespaceEnum;
use App\Service\Cache\CacheService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user-message:set',
    description: 'This command sets a user message.'
)]
class SetUserMessageCommand extends Command {
    private const DEFAULT_COLOR = '#d9534f';

    public function __construct(private EntityManagerInterface $entityManager,
                                private SettingsService        $settingsService,
                                private CacheService           $cacheService) {
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

        $settings = [Setting::USER_MESSAGE_CONFIG];

        $settingMessage = $this->settingsService->persistSetting($this->entityManager, $settings, Setting::USER_MESSAGE_CONFIG);
        $settingMessage->setValue($warningHeader);

        $this->entityManager->flush();

        $this->cacheService->delete(CacheNamespaceEnum::SETTINGS, Setting::USER_MESSAGE_CONFIG);

        return Command::SUCCESS;
    }
}
