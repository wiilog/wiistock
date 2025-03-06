<?php

namespace App\Messenger\Dashboard;

use App\Entity\Dashboard;
use App\Exceptions\DashboardException;
use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use WiiCommon\Helper\Stream;

#[AsMessageHandler]
class FeedMultipleDashboardComponentHandler extends LoggedHandler
{

    public function __construct(
        #[Autowire("@service_container")]
        private ContainerInterface     $container,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface    $messageBus,
        private ExceptionLoggerService $loggerService,
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

        $groupedComponents = Stream::from($components)
            ->keymap(function (Dashboard\Component $component) {
                return [
                    $this->getGroupingKey($component),
                    $component
                ];
            }, true)
            ->toArray();

        if(count($groupedComponents) === 1) {
            try {
                $generator = $generatorClass
                    ? $this->container->get($generatorClass)
                    : null;
                $generator->persistAll($this->entityManager, $components);
                $this->entityManager->flush();
            } catch (Throwable $exception) {
                foreach ($components as $component) {
                    if ($exception instanceof DashboardException) {
                        $component->setErrorMessage($exception->getMessage());
                    } else {
                        $component->setErrorMessage($exception->getMessage());
                    }
                }
                $this->entityManager = $this->getEntityManager();
            }
            $this->entityManager->flush();
        } else {
            // TODO WIIS-12419 renommer $groupedComponent avec pluriel
            foreach ($groupedComponents as $groupedComponent) {
                $groupedComponentIds = Stream::from($groupedComponent)
                    ->map(static fn(Dashboard\Component $component) => $component->getId())
                    ->toArray();
                $this->messageBus->dispatch(new FeedMultipleDashboardComponentMessage($groupedComponentIds, $generatorClass));
            }
        }
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
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
                // TODO WIIS-12419 voir pour envoyer au logger
                throw new DashboardException("Unsupported meter key: $meterKey");
        }
    }
}
