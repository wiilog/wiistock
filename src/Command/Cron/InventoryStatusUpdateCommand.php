<?php
// At 22:00 on Sunday
// 0 22 * * 0

namespace App\Command\Cron;

use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: InventoryStatusUpdateCommand::COMMAND_NAME,
    description: 'This commands updates the inventory dates on the refs',
)]
class InventoryStatusUpdateCommand extends Command
{

    public const COMMAND_NAME = "app:update:inventory-status";

    #[Required]
    public EntityManagerInterface $entityManager;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $referenceArticleRepository->updateInventoryStatusQuery();
        return Command::SUCCESS;
    }
}
