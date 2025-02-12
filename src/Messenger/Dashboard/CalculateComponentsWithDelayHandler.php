<?php

namespace App\Messenger\Dashboard;

use App\Entity\Dashboard\Component;
use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use WiiCommon\Helper\Stream;

#[AsMessageHandler]
class CalculateComponentsWithDelayHandler extends LoggedHandler
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface    $messageBus,
        private ExceptionLoggerService $loggerService,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(CalculateComponentsWithDelayMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param CalculateComponentsWithDelayMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        $componentsWithDelayIds = $message->getComponentsWithDelayIds();
        $componentRepository = $this->entityManager->getRepository(Component::class);

        if(!empty($componentsWithDelayIds)) {
            $componentsWithDelay = $componentRepository->findBy(["id" => $componentsWithDelayIds]);

            $groupedComponentsWithSameFilter = Stream::from($componentsWithDelay)
                ->reduce(function (array $carry, Component $component) {
                    $config = $component->getConfig();
                    $natures = $config['natures'] ?? null;
                    $locations = $config['locations'] ?? null;

                    $addToCarry = true;
                    foreach ($carry as &$groupedArray) {
                        $naturesDiff = array_diff($natures, $groupedArray['natures']);
                        $locationsDiff = array_diff($locations, $groupedArray['locations']);

                        if (count($naturesDiff) === 0 && count($locationsDiff) === 0) {
                            $groupedArray['componentId'][] = $component->getId();
                            $addToCarry = false;
                        }
                    }

                    if ($addToCarry) {
                        $carry[] = [
                            "componentId" => [$component->getId()],
                            "natures" => $natures,
                            "locations" => $locations,
                        ];
                    }

                    return $carry;
                }, []);

            foreach ($groupedComponentsWithSameFilter as $componentWithSameFilter) {
                $this->messageBus->dispatch(new CalculateComponentsWithDelayFeedingMessage($componentWithSameFilter));
            }
        }
    }
}
