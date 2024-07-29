<?php

namespace App\Command;

use App\Entity\Setting;
use App\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:warning-message-set',
    description: 'This command sets the warning header message. It can be used to hide the warning header message.'
)]
class WarningMessageSetCommand extends Command
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public CacheService $cacheService;

    private const DEFAULT_COLOR = '#d9534f';

    protected function configure(): void
    {
        $this
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'The message to display')
            ->addOption('color', 'c', InputOption::VALUE_OPTIONAL, 'The color of the message');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        $message = $input->getOption('message');
        $color = $input->getOption('color');

        if ($message) {
            $output->writeln('Updated warning header message to ' . $message);
        }
        else {
            $output->writeln('No message provided, clearing warning header message');
        }

        $warningHeader = json_encode(
            [
                "color" => $color ?? self::DEFAULT_COLOR,
                "message" => $message
            ]
        );

        $settingMessage = $settingRepository->findOneBy(['label' => Setting::WARNING_HEADER]);
        $settingMessage->setValue($warningHeader);

        $this->entityManager->flush();

        $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, Setting::WARNING_HEADER);

        return Command::SUCCESS;
    }
}
