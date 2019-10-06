<?php


namespace App\Command;

use App\Entity\InventoryMission;

use App\Entity\ReferenceArticle;
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
use function Sodium\add;

class MissionCommand extends Command
{
    protected static $defaultName = 'app:generate:mission';


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
        $this->setDescription('This commands generates');
        $this->setHelp('This command is supposed to be executed at every end of week, via a cron on the server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new \DateTime('now');
        $frequencies = $this->inventoryFrequencyRepository->findAll();
        $monday = new \DateTime('now');
        $monday->add(new \DateInterval('P1D'));
        $mission = $this->inventoryMissionRepository->findByDate($monday->format('Y/m/d'));

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
            /** @var ReferenceArticle $ref */
            foreach ($refsToInv as $ref) {
                $ref->addInventoryMission($mission);
                $this->entityManager->flush();
            }
        }
    }
}