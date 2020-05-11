<?php
/**
 * Commande Cron exécutée toute les minutes tous les jours de 7h a 19h excepté le dimanche :
 *
 */
// */1 6-18 * * 1-6
namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\DashboardMeter;
use App\Entity\Import;
use App\Entity\Statut;
use App\Repository\DashboardMeterRepository;
use App\Service\DashboardService;
use App\Service\ImportService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DashboardFeedCommand extends Command
{
    protected static $defaultName = 'app:feed:dashboards';

    private $em;
    private $dashboardService;

    public function __construct(EntityManagerInterface $entityManager,
                                DashboardService $dashboardService)
    {
        parent::__construct(self::$defaultName);
        $this->em = $entityManager;
        $this->dashboardService = $dashboardService;
    }

    protected function configure()
    {
        $this->setDescription('This command feeeds the dashboard data.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws ORMException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dashboardService->retrieveAndInsertGlobalDashboardData($this->getEntityManager());
    }

    /**
     * @return EntityManagerInterface
     * @throws ORMException
     */
    private function getEntityManager(): EntityManagerInterface
    {
        return $this->em->isOpen()
            ? $this->em
            : EntityManager::Create($this->em->getConnection(), $this->em->getConfiguration());
    }
}
