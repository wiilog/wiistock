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
        $now = new \DateTime('now');
        $frequencies = $this->inventoryFrequencyRepository->findAll();

        $maxNbMission = 0;
        foreach ($frequencies as $frequency) {
            $growth = $frequency->getNbMonths();
            if ($growth > $maxNbMission) {
                $maxNbMission = $growth;
            }
        }

        $nowForMonday = new \DateTime('now');
        $monday = $nowForMonday->modify('next monday');
        $nowForSunday = new \DateTime('now');
        $sunday = $nowForSunday->modify('next monday + 6 days');

        $counter = 0;
        while ($counter != $maxNbMission * 4) {
            $mission = new InventoryMission();
            $mission
                ->setStartPrevDate($monday)
                ->setEndPrevDate($sunday);
            $this->entityManager->persist($mission);
            $this->entityManager->flush();
            $monday->add(new \DateInterval('P7D'));
            $sunday->add(new \DateInterval('P7D'));
            $counter++;
        }

        $missions = $this->inventoryMissionRepository->findAll();

        foreach ($frequencies as $frequency) {
            $nbMonths = $frequency->getNbMonths();
            $refArticles = $this->referenceArticleRepository->findByFrequencyOrderedByLocation($frequency);
            $refsAndArtsToInv = [];
            foreach ($refArticles as $refArticle) {
                if ($refArticle->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $refDate = $refArticle->getDateLastInventory();
                    if (!$refDate) {
                        $refsAndArtsToInv[] = $refArticle;
                    }
                } else {
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
                    $articles = $this->articleRepository->findByRefArticleAndStatut($refArticle, $statut);

                    foreach ($articles as $article) {
                        $artDate = $article->getDateLastInventory();
                        if (!$artDate) {
                            $refsAndArtsToInv[] = $article;
                        }
                    }
                }
            }
            $nbRefToInv = count($refsAndArtsToInv);
            $nbMission = $maxNbMission * 4;
            if (($refPerMission = $nbRefToInv / $nbMission) <= 1) {
                $refPerMission = $nbRefToInv;
            }

            $offset = 0;
            $counter = 1;
            foreach ($missions as $mission) {
                if ($counter == $nbMission) {
                    $refPerMission = null;
                }
                $addArray = array_slice($refsAndArtsToInv, $offset, $refPerMission);
                foreach ($addArray as $item) {
                    $mission->addRefArticle($item);
                    $this->entityManager->flush();
                }
                $offset = $refPerMission * $counter;
                $counter++;
            }
        }
    }
}