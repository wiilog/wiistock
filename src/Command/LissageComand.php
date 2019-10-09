<?php


namespace App\Command;

use App\Entity\InventoryMission;

use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\InventoryMissionRepository;
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

    public function __construct(UtilisateurRepository $userRepository, EntityManagerInterface $entityManager, ArticleRepository $articleRepository, ReferenceArticleRepository $referenceArticleRepository, InventoryFrequencyRepository $inventoryFrequencyRepository, InventoryMissionRepository $inventoryMissionRepository)
    {
        parent::__construct();
        $this->userRepository= $userRepository;
        $this->entityManager = $entityManager;
        $this->articleRepository = $articleRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
    }

    protected function configure()
    {
        $this->setDescription('This commands generates inventory missions.');
        $this->setHelp('This command is supposed to be executed at every end of week, via a cron on the server.');
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
        $firstMonday = $nowForMonday->modify('next monday');
        $nowForSunday = new \DateTime('now');
        $firstSunday = $nowForSunday->modify('next sunday');

        $counter = 0;
        while ($counter != $maxNbMission * 4) {
            $mission = new InventoryMission();
            $mission
                ->setStartPrevDate($firstMonday)
                ->setEndPrevDate($firstSunday);
            $this->entityManager->persist($mission);
            $this->entityManager->flush();
            $firstMonday->add(new \DateInterval('P7D'));
            $firstSunday->add(new \DateInterval('P7D'));
            $counter++;
        }

        $missions = $this->inventoryMissionRepository->findAll();

        foreach ($frequencies as $frequency) {
            $nbMonths = $frequency->getNbMonths();
            $refArticles = $this->referenceArticleRepository->findByFrequency($frequency);
            $refsToInv = [];
            foreach ($refArticles as $refArticle) {
                $refDate = $refArticle->getDateLastInventory();
                $diff = date_diff($refDate, $now)->format('%m');
                if ($diff >= $nbMonths) {
                    $refsToInv[] = $refArticle;
                }
            }
            $nbRefToInv = count($refsToInv);
            $nbMission = $maxNbMission * 4;
            if (($refPerMission = $nbRefToInv / $nbMission) <= 1) {
                $refPerMission = $nbRefToInv;
            }

            $offset = 0;
            $counter = 1;
            foreach ($missions as $mission) {
                if ($counter == $nbMission) {
                    dump('filsdgdsqg');
                    $refPerMission = null;
                }
                $addArray = array_slice($refsToInv, $offset, $refPerMission);
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