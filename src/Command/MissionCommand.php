<?php
// At 23:00 on Sunday
// 0 23 * * 0

namespace App\Command;

use App\Entity\Article;
use App\Entity\Inventory\InventoryFrequency;
use App\Entity\Inventory\InventoryMission;
use App\Entity\ReferenceArticle;
use App\Service\InventoryService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MissionCommand extends Command
{
    protected static $defaultName = 'app:generate:mission';

    /**
     * @var EntityManager
     */
    private $entityManager;

	/**
	 * @var InventoryService
	 */
    private $inventoryService;


    public function __construct(EntityManagerInterface $entityManager,
                                InventoryService $inventoryService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->inventoryService = $inventoryService;
    }

    protected function configure()
    {
		$this->setDescription('This commands generates inventory missions.');
        $this->setHelp('This command is supposed to be executed at every end of week, via a cron on the server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $inventoryFrequencyRepository = $this->entityManager->getRepository(InventoryFrequency::class);
        $inventoryMissionRepository = $this->entityManager->getRepository(InventoryMission::class);

        $now = new DateTime('now');
        $frequencies = $inventoryFrequencyRepository->findUsedByCat();

        $monday = new DateTime('now');
        $monday->modify('next monday');
        $mission = $inventoryMissionRepository->findFirstByStartDate($monday->format('Y/m/d'));

        if (!$mission) {
        	$mission = new InventoryMission();

        	$sunday = new DateTime('now');
        	$sunday->modify('next monday + 6 days');

        	$mission
				->setStartPrevDate($monday)
				->setEndPrevDate($sunday);
        	$this->entityManager->persist($mission);
        	$this->entityManager->flush();
		}

        foreach ($frequencies as $frequency) {
        	// récupération des réf et articles à inventorier (fonction date dernier inventaire)
            $nbMonths = $frequency->getNbMonths();
            $referencesToInventory = $referenceArticleRepository->iterateReferencesToInventory($frequency, $mission);
            $articlesToInventory = $articleRepository->iterateArticlesToInventory($frequency, $mission);

            $treated = 0;

            foreach ($referencesToInventory as $reference) {
                $reference->addInventoryMission($mission);
                $treated++;
                if ($treated >= 500) {
                    $treated = 0;
                    $this->entityManager->flush();
                }
            }

            $treated = 0;
            $this->entityManager->flush();

            /** @var Article $article */
            foreach ($articlesToInventory as $article) {
                $article->addInventoryMission($mission);
                $treated++;
                if ($treated >= 500) {
                    $treated = 0;
                    $this->entityManager->flush();
                }
            }

            $treated = 0;
            $this->entityManager->flush();

			// lissage des réf et articles jamais inventoriés
			$nbRefAndArtToInv = $referenceArticleRepository->countActiveByFrequencyWithoutDateInventory($frequency);
			$nbToInv = $nbRefAndArtToInv['nbRa'] + $nbRefAndArtToInv['nbA'];

			$limit = (int)($nbToInv/($frequency->getNbMonths() * 4));

			$listRefNextMission = $referenceArticleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit/2);
			$listArtNextMission = $articleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit/2);

			/** @var ReferenceArticle $ref */
            foreach ($listRefNextMission as $ref) {
				$alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($ref, $mission, true);
				if (!$alreadyInMission) {
					$ref->addInventoryMission($mission);
				}
			}
            /** @var Article $art */
            foreach ($listArtNextMission as $art) {
				$alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($art, $mission, false);
				if (!$alreadyInMission) {
					$art->addInventoryMission($mission);
				}
			}

			$this->entityManager->flush();
		}
            return 0;

    }
}
