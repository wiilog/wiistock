<?php

namespace App\Messenger\Dashboard;

use App\Entity\Dashboard;
use App\Exceptions\DashboardException;
use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\Dashboard\DashboardService;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
class FeedDashboardComponentHandler extends LoggedHandler
{

    public function __construct(
        #[Autowire("@service_container")]
        private ContainerInterface     $container,
        private EntityManagerInterface $entityManager,
        private ExceptionLoggerService $loggerService,

    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(FeedDashboardComponentMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param FeedDashboardComponentMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        $componentId = $message->getComponentId();
        $generatorClass = $message->getGeneratorClass();
        $componentRepository = $this->entityManager->getRepository(Dashboard\Component::class);

        $component = $componentId ? $componentRepository->findOneBy(["id" => $componentId]) : null;

        if(!$component) {
            return;
        }

        $component->setErrorMessage(null);
        try {
            $generator = $generatorClass
                ? $this->container->get($generatorClass)
                : null;

            $generator->persist($this->entityManager, $component);
            $this->entityManager->flush();
        } catch (Throwable $exception) {
            $message = $exception instanceof DashboardException
                ? $exception->getMessage()
                : DashboardService::DASHBOARD_ERROR_MESSAGE;
            $this->flushErrorMessage($component, $message);

            throw $exception;
        }
    }

    private function flushErrorMessage(Dashboard\Component $component,
                                       ?string             $message): void {
        $this->entityManager = new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
        $component = $this->entityManager->getReference(Dashboard\Component::class, $component->getId());
        $component->setErrorMessage($message);
        $this->entityManager->flush();
    }
}
