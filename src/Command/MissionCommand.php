<?php


namespace App\Command;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\InventoryMission;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\InventoryMissionRepository;
use App\Service\InventoryService;
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
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

	/**
	 * @var InventoryService
	 */
    private $inventoryService;


    public function __construct(EntityManagerInterface $entityManager,
                                InventoryFrequencyRepository $inventoryFrequencyRepository,
                                InventoryMissionRepository $inventoryMissionRepository,
                                InventoryService $inventoryService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
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

        $now = new \DateTime('now');
        $frequencies = $this->inventoryFrequencyRepository->findUsedByCat();

        $monday = new \DateTime('now');
        $monday->modify('next monday');
        $mission = $this->inventoryMissionRepository->findFirstByStartDate($monday->format('Y/m/d'));

        if (!$mission) {
        	$mission = new InventoryMission();

        	$sunday = new \DateTime('now');
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

            /** @var ReferenceArticle[] $refArticles */
            $refArticles = $referenceArticleRepository->findByFrequencyOrderedByLocation($frequency);

            $refsAndArtToInv = [];
            foreach ($refArticles as $refArticle) {
            	if ($refArticle->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
					$refDate = $refArticle->getDateLastInventory();
					if ($refDate) {
						$diff = date_diff($refDate, $now)->format('%m');
						if ($diff >= $nbMonths) {
							$refsAndArtToInv[] = $refArticle;
						}
					}
				} else {
            		/** @var Article[] $articles */
            		$articles = $articleRepository->findByRefArticleAndStatut(
            		    $refArticle,
                        [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE],
                        ReferenceArticle::STATUT_ACTIF
                    );

            		foreach ($articles as $article) {
   						$artDate = $article->getDateLastInventory();
   						if ($artDate) {
   							$diff = date_diff($artDate, $now)->format('%m');
   							if ($diff >= $nbMonths) {
   								$refsAndArtToInv[] = $article;
							}
						}
					}
				}
            }

            foreach ($refsAndArtToInv as $refOrArt) {
                /** @var ReferenceArticle|Article $refOrArt */
				$alreadyInMission = $this->inventoryService->isInMissionInSamePeriod($refOrArt, $mission, $refOrArt instanceof ReferenceArticle);

				if (!$alreadyInMission) {
					$refOrArt->addInventoryMission($mission);
					$this->entityManager->flush();
				}
            }

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

    }
}
