<?php
// At 22:00 on Sunday
// 0 22 * * 0

namespace App\Command;

use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class InventoryStatusUpdateCommand extends Command
{
    protected static $defaultName = 'app:update:inventory-status';

    #[Required]
    public EntityManagerInterface $entityManager;

    protected function configure()
    {
		$this->setDescription('This commands updates the inventory dates on the refs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $referenceArticleRepository->updateInventoryStatusQuery();
        return 1;
    }
}
