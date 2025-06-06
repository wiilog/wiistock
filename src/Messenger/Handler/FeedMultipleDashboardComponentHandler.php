<?php

namespace App\Messenger\Handler;

use App\Entity\Dashboard;
use App\Exceptions\DashboardException;
use App\Messenger\Message\DeduplicatedMessage\FeedMultipleDashboardComponentMessage;
use App\Messenger\Message\MessageInterface;
use App\Service\Dashboard\DashboardService;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use WiiCommon\Helper\Stream;


/**
 * Handler for multiple dashboard component
 */
#[AsMessageHandler(fromTransport: "async_dashboard_feeding")]
class FeedMultipleDashboardComponentHandler extends LoggedHandler {

    public function __construct(
        private ExceptionLoggerService                               $loggerService,
        #[Autowire("@service_container")] private ContainerInterface $container,
        private EntityManagerInterface                               $entityManager,
        private MessageBusInterface                                  $messageBus,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(FeedMultipleDashboardComponentMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param FeedMultipleDashboardComponentMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        $componentIds = $message->getComponentIds();
        $generatorClass = $message->getGeneratorClass();
        $componentRepository = $this->entityManager->getRepository(Dashboard\Component::class);

        $components = !empty($componentIds) ? $componentRepository->findBy(["id" => $componentIds]) : null;

        if(empty($components)) {
            return;
        }

        $groupedComponentsByKey = Stream::from($components)
            ->keymap(function (Dashboard\Component $component) {
                return [
                    $this->getGroupingKey($component),
                    $component
                ];
            }, true)
            ->toArray();

        if(count($groupedComponentsByKey) === 1) {
            try {
                $generator = $generatorClass
                    ? $this->container->get($generatorClass)
                    : null;
                $generator->persistAll($this->entityManager, $components);
                $this->entityManager->flush();
            } catch (Throwable $exception) {
                $this->entityManager = new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());

                foreach ($components as $component) {
                    $component = $this->entityManager->getReference(Dashboard\Component::class, $component->getId());

                    if ($exception instanceof DashboardException) {
                        $component->setErrorMessage($exception->getMessage());
                    } else {
                        $component->setErrorMessage(DashboardService::DASHBOARD_ERROR_MESSAGE);
                    }
                }

                $this->entityManager->flush();

                throw $exception;
            }
        } else {
            foreach ($groupedComponentsByKey as $groupedComponents) {
                $groupedComponentIds = Stream::from($groupedComponents)
                    ->map(static fn(Dashboard\Component $component) => $component->getId())
                    ->toArray();
                $this->messageBus->dispatch(new FeedMultipleDashboardComponentMessage($groupedComponentIds, $generatorClass));
            }
        }
    }

    private function getGroupingKey(Dashboard\Component $component): string {
        $config = $component->getConfig();
        $componentType = $component->getType();
        $meterKey = $componentType->getMeterKey();
        switch ($meterKey) {
            case Dashboard\ComponentType::LATE_PACKS:
                return 0;
            case Dashboard\ComponentType::ONGOING_PACKS_WITH_TRACKING_DELAY :
            case Dashboard\ComponentType::ENTRIES_TO_HANDLE_BY_TRACKING_DELAY:
                $natures = $config['natures'] ?? [];
                $locations = $config['locations'] ?? [];
                $eventType = $config['treatmentDelayType'] ?? '';

                $natureStr = Stream::from($natures)->sort(static fn($a, $b) => $a <=> $b)->join('_');
                $locationStr = Stream::from($locations)->sort(static fn($a, $b) => $a <=> $b)->join('_');

                return "natures_$natureStr-locations_$locationStr-$eventType";
            default:
                throw new Exception("Unsupported meter key: $meterKey");
        }
    }
}
