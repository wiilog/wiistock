<?php
// At every 5th minute
// */5 * * * *

namespace App\Command\Cron;

use App\Entity\Dashboard;
use App\Entity\Wiilock;
use App\Exceptions\DashboardException;
use App\Service\Dashboard\DashboardComponentGenerator\ActiveReferenceAlertsComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\ArrivalsAndPacksComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\ArrivalsEmergenciesComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\CarrierTrackingComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyDeliveryOrdersComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyDispatchesComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyHandlingIndicatorComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyHandlingOrOperationsComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DailyProductionsComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DashboardComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DisputeToTreatComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\DropOffDistributedPacksComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\EntriesToHandleByTrackingDelayComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\EntriesToHandleComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\HandlingTrackingComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\MonetaryReliabilityComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\MonetaryReliabilityGraphComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\MonetaryReliabilityIndicatorComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\OngoingPackComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\OngoingPacksWithTrackingDelayComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\PackToTreatFromComponentGenerator;
use App\Service\Dashboard\DashboardComponentGenerator\RequestsOrdersToTreatComponentGenerator;
use App\Service\Dashboard\MultipleDashboardComponentGenerator\LatePackComponentGenerator;
use App\Service\Dashboard\MultipleDashboardComponentGenerator\MultipleDashboardComponentGenerator;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

#[AsCommand(
    name: DashboardFeedCommand::COMMAND_NAME,
    description: 'Feeds the dashboard data.'
)]
class DashboardFeedCommand extends Command {
    public const COMMAND_NAME = 'app:feed:dashboards';

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire("@service_container")]
        private ContainerInterface     $container,
        private WiilockService         $wiilockService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if(!$this->wiilockService->dashboardNeedsFeeding($this->entityManager)) {
            $output->writeln("Dashboards are being fed, aborting");
            return 0;
        }

        $this->wiilockService->toggleFeedingCommand($this->entityManager, true, Wiilock::DASHBOARD_FED_KEY);
        $this->entityManager->flush();

        $dashboardComponentRepository = $this->entityManager->getRepository(Dashboard\Component::class);

        $components = $dashboardComponentRepository->findAll();

        /** @var MultipleDashboardComponentGenerator[] $multipleComponentGenerators */
        $multipleComponentGenerators = [];

        foreach ($components as $component) {
            $componentType = $component->getType();
            $meterKey = $componentType->getMeterKey();
            $component->setErrorMessage(null);

            try {

                $generatorClass = match ($meterKey) {
                    Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY => OngoingPacksWithTrackingDelayComponentGenerator::class,
                    Dashboard\ComponentType::ONGOING_PACKS => OngoingPackComponentGenerator::class,
                    Dashboard\ComponentType::DAILY_HANDLING_INDICATOR => DailyHandlingIndicatorComponentGenerator::class,
                    Dashboard\ComponentType::DROP_OFF_DISTRIBUTED_PACKS => DropOffDistributedPacksComponentGenerator::class,
                    Dashboard\ComponentType::CARRIER_TRACKING => CarrierTrackingComponentGenerator::class,
                    Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS, Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS => ArrivalsAndPacksComponentGenerator::class,
                    Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY => EntriesToHandleByTrackingDelayComponentGenerator::class,
                    Dashboard\ComponentType::ENTRIES_TO_HANDLE => EntriesToHandleComponentGenerator::class,
                    Dashboard\ComponentType::PACK_TO_TREAT_FROM => PackToTreatFromComponentGenerator::class,
                    Dashboard\ComponentType::ARRIVALS_EMERGENCIES_TO_RECEIVE, Dashboard\ComponentType::DAILY_ARRIVALS_EMERGENCIES => ArrivalsEmergenciesComponentGenerator::class,
                    Dashboard\ComponentType::ACTIVE_REFERENCE_ALERTS => ActiveReferenceAlertsComponentGenerator::class,
                    Dashboard\ComponentType::MONETARY_RELIABILITY_GRAPH => MonetaryReliabilityGraphComponentGenerator::class,
                    Dashboard\ComponentType::MONETARY_RELIABILITY_INDICATOR => MonetaryReliabilityIndicatorComponentGenerator::class,
                    Dashboard\ComponentType::REFERENCE_RELIABILITY => MonetaryReliabilityComponentGenerator::class,
                    Dashboard\ComponentType::DAILY_DISPATCHES => DailyDispatchesComponentGenerator::class,
                    Dashboard\ComponentType::DAILY_PRODUCTION => DailyProductionsComponentGenerator::class,
                    Dashboard\ComponentType::DAILY_HANDLING, Dashboard\ComponentType::DAILY_OPERATIONS => DailyHandlingOrOperationsComponentGenerator::class,
                    Dashboard\ComponentType::DAILY_DELIVERY_ORDERS => DailyDeliveryOrdersComponentGenerator::class,
                    Dashboard\ComponentType::REQUESTS_TO_TREAT, Dashboard\ComponentType::ORDERS_TO_TREAT => RequestsOrdersToTreatComponentGenerator::class,
                    Dashboard\ComponentType::DISPUTES_TO_TREAT => DisputeToTreatComponentGenerator::class,
                    Dashboard\ComponentType::HANDLING_TRACKING => HandlingTrackingComponentGenerator::class,
                    Dashboard\ComponentType::LATE_PACKS => LatePackComponentGenerator::class,
                    default => null,
                };

                $generator = $generatorClass
                    ? $this->container->get($generatorClass)
                    : null;

                if ($generator instanceof DashboardComponentGenerator) {
                    $generator->persist($this->entityManager,  $component);
                }
                else if ($generator instanceof MultipleDashboardComponentGenerator) {
                    $multipleComponentGenerators[] = $generator;
                    $generator->push($component);
                }
                else {
                    // default error in component "Erreur : Impossible de charger le composant"
                    throw new Exception("Not implemented yet.");
                }
            } catch (Throwable $throwable) {
                $this->treatException($throwable, $component);
                $this->entityManager = $this->getEntityManager();
            }
        }

        foreach ($multipleComponentGenerators as $generator) {
            try {
                $generator->persistAll($this->entityManager);
                $generator->clear();
            } catch (Throwable $throwable) {
                foreach ($generator->collection as $component) {
                    $this->treatException($throwable, $component);
                }
                $this->entityManager = $this->getEntityManager();
            }
        }

        $this->entityManager->flush();

        $this->wiilockService->toggleFeedingCommand($this->entityManager, false, Wiilock::DASHBOARD_FED_KEY);
        $this->entityManager->flush();

        return 0;
    }

    private function treatException(Throwable           $throwable,
                                    Dashboard\Component $component): void {
        if ($throwable instanceof DashboardException) {
            $component->setErrorMessage($throwable->getMessage());
        } else {
            $component->setErrorMessage("Erreur : Impossible de charger le composant");
        }
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }

}
