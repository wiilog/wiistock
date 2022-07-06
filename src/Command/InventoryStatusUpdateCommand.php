<?php
// At 23:00 on Sunday
// 0 23 * * 0

namespace App\Command;

use App\Entity\Article;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Inventory\InventoryMission;
use App\Entity\ReferenceArticle;
use App\Service\InventoryService;
use App\Service\RefArticleDataService;
use DateTime;
use Doctrine\ORM\EntityManager;
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

    #[Required]
    public RefArticleDataService $refArticleDataService;

    protected function configure()
    {
		$this->setDescription('This commands updates the inventory dates on the refs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $refsToUpdate = $referenceArticleRepository->findNeedingInventoryDateUpdate();

        foreach ($refsToUpdate as $ref) {
            $this->refArticleDataService->updateInventoryStatus($this->entityManager, $ref);
        }
        $this->entityManager->flush();
    }
}
