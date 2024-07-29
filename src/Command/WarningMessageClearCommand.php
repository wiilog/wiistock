<?php

namespace App\Command;

use App\Entity\Setting;
use App\Service\CacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:warning-message-clear',
    description: 'This command clears the warning header message. It can be used to hide the warning header message.'
)]
class WarningMessageClearCommand extends Command
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public CacheService $cacheService;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settingRepository = $this->entityManager->getRepository(Setting::class);

        $settingMessage = $settingRepository->findOneBy(['label' => Setting::WARNING_HEADER]);
        $settingMessage->setValue(null);

        $this->entityManager->flush();

        $this->cacheService->delete(CacheService::COLLECTION_SETTINGS, Setting::WARNING_HEADER);

        return Command::SUCCESS;
    }
}
