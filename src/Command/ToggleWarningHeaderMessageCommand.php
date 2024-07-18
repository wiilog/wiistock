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
    name: 'app:set-warning-header-message',
    description: 'This command toggles the warning header message. It can be used to hide the warning header message.'
)]
class ToggleWarningHeaderMessageCommand extends Command
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public CacheService $cacheService;

    private const DEFAULT_COLOR = '#d9534f';

    public function __construct()
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
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        $message = $input->getOption('message');
        $color = $input->getOption('color');

        $settingMessage = $settingRepository->findOneBy(['label' => Setting::WARNING_HEADER_MESSAGE]);
        $settingMessage->setValue($message);

        $settingColor = $settingRepository->findOneBy(['label' => Setting::COLOR_WARNING_HEADER_MESSAGE]);
        $settingColor->setValue($color ?? self::DEFAULT_COLOR);

        $this->entityManager->flush();

        $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, Setting::WARNING_HEADER_MESSAGE);
        $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, Setting::COLOR_WARNING_HEADER_MESSAGE);


        if ($message) {
            $output->writeln('Updated warning header message to ' . $message);
        } else {
            $output->writeln('No message provided, clearing warning header message');
        }

        return Command::SUCCESS;
    }
}
