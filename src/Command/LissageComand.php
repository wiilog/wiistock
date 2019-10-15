<?php


namespace App\Command;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\InventoryMission;

use App\Entity\ReferenceArticle;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\InventoryMissionRepository;
use App\Repository\StatutRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Date;

class LissageComand extends Command
{
    protected static $defaultName = 'app:lissage:mission';


    /**
     * @var UtilisateurRepository
     */
    private $userRepository;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    public function __construct(UtilisateurRepository $userRepository, EntityManagerInterface $entityManager, ArticleRepository $articleRepository, ReferenceArticleRepository $referenceArticleRepository, InventoryFrequencyRepository $inventoryFrequencyRepository, InventoryMissionRepository $inventoryMissionRepository, StatutRepository $statutRepository)
    {
        parent::__construct();
        $this->userRepository= $userRepository;
        $this->entityManager = $entityManager;
        $this->articleRepository = $articleRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->statutRepository = $statutRepository;
    }

    protected function configure()
    {
        $this->setDescription('This commands generates inventory missions for articles and references never inventoried.');
        $this->setHelp('This command is supposed to be executed at once, at the beginning of inventories.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		// récupération ou création de la prochaine mission
		$monday = new \DateTime('now');
		$monday->modify('next monday');
		$nextMission = $this->inventoryMissionRepository->findFirstByStartDate($monday->format('Y/m/d'));

		if (!$nextMission) {
			$nextMission = new InventoryMission();

			$sunday = new \DateTime('now');
			$sunday->modify('next monday + 6 days');

			$nextMission
				->setStartPrevDate($monday)
				->setEndPrevDate($sunday);
			$this->entityManager->persist($nextMission);
			$this->entityManager->flush();
		}

		// pour chaque fréquence, récupération des articles et réf à inventorier pour ajout dans la mission
		$frequencies = $this->inventoryFrequencyRepository->findUsedByCat();

		foreach ($frequencies as $frequency) {
        	$nbRefAndArtToInv = $this->referenceArticleRepository->countActiveByFrequencyWithoutDateInventory($frequency);
        	$nbToInv = $nbRefAndArtToInv['nbRa'] + $nbRefAndArtToInv['nbA'];

			$limit = (int)($nbToInv/($frequency->getNbMonths() * 4));

        	$listRefNextMission = $this->referenceArticleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit/2);
        	$listArtNextMission = $this->articleRepository->findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit/2);

        	foreach ($listRefNextMission as $ref) {
        		$ref->addInventoryMission($nextMission);
			}
        	foreach ($listArtNextMission as $art) {
        		$art->addInventoryMission($nextMission);
			}

        	$this->entityManager->flush();
        }
    }
}