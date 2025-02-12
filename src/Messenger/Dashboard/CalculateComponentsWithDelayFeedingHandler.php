<?php

namespace App\Messenger\Dashboard;

use App\Entity\Dashboard\Component;
use App\Entity\Dashboard\ComponentType;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\Tracking\TrackingDelay;
use App\Exceptions\DashboardException;
use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\DashboardService;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;
use WiiCommon\Helper\Stream;

#[AsMessageHandler]
class CalculateComponentsWithDelayFeedingHandler extends LoggedHandler
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DashboardService       $dashboardService,
        private ExceptionLoggerService $loggerService,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(CalculateComponentsWithDelayFeedingMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param CalculateComponentsWithDelayFeedingMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        $groupedComponentIdsWithSameFilter = $message->getGroupedComponentIdsWithSameFilter();
        $componentRepository = $this->entityManager->getRepository(Component::class);
        $locationRepository = $this->entityManager->getRepository(Emplacement::class);
        $natureRepository = $this->entityManager->getRepository(Nature::class);
        $trackingDelayRepository = $this->entityManager->getRepository(TrackingDelay::class);

        if(!empty($groupedComponentIdsWithSameFilter)) {
            $groupedComponentWithSameFilter = $componentRepository->findBy(["id" => $groupedComponentIdsWithSameFilter['componentId']]);

            $naturesFilter = !empty($groupedComponentIdsWithSameFilter['natures'])
                ? $natureRepository->findBy(['id' => $groupedComponentIdsWithSameFilter['natures']])
                : [];

            $locationsFilter = !empty($groupedComponentIdsWithSameFilter['locations'])
                ? $locationRepository->findBy(['id' => $groupedComponentIdsWithSameFilter['locations']])
                : [];

            $trackingDelayByFilters = $trackingDelayRepository->iterateTrackingDelayByFilters($naturesFilter, $locationsFilter, [], 1000);

            foreach ($groupedComponentWithSameFilter as &$component) {
                $componentType = $component->getType();
                $meterKey = $componentType->getMeterKey();
                $component->setErrorMessage(null);
                dump('0');
                dump(!$trackingDelayByFilters->valid());
                if(!$trackingDelayByFilters->valid()) {
                    dump('1');
                    $trackingDelayByFilters->rewind();
                    dump('2');
                }
                dump('3');

                try {
                    switch ($meterKey) {
                        case ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
                            $this->dashboardService->persistEntriesToHandleByTrackingDelay($this->entityManager, $component, $naturesFilter, $trackingDelayByFilters);
                            break;
                        default:
                            break;
                    }
                    $this->entityManager->flush();
                } catch (Throwable $exception) {
                    if ($exception instanceof DashboardException) {
                        $component->setErrorMessage($exception->getMessage());
                    } else {
                        $component->setErrorMessage($exception->getMessage());
                    }
                    $this->entityManager = $this->getEntityManager();
                }
            }
            $this->entityManager->flush();

        }
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
