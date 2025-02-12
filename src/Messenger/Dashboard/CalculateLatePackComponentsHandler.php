<?php

namespace App\Messenger\Dashboard;

use App\Entity\Dashboard\Component;
use App\Messenger\LoggedHandler;
use App\Messenger\MessageInterface;
use App\Service\DashboardService;
use App\Service\ExceptionLoggerService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
class CalculateLatePackComponentsHandler extends LoggedHandler
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DashboardService       $dashboardService,
        private ExceptionLoggerService $loggerService,
    ) {
        parent::__construct($this->loggerService);
    }

    public function __invoke(CalculateLatePackComponentsMessage $message): void {
        $this->handle($message);
    }

    /**
     * @param CalculateLatePackComponentsMessage $message Not typed in php to implement LoggedHandler
     */
    protected function process(MessageInterface $message): void {
        $latePackComponentIds = $message->getLatePackComponentIds();
        $componentRepository = $this->entityManager->getRepository(Component::class);

        if(!empty($latePackComponentIds)) {
            try {
                $this->dashboardService->persistEntitiesLatePack($this->entityManager);
            } catch (Throwable $exception) {
                $latePackComponents = $componentRepository->findBy(["id" => $latePackComponentIds]);
                foreach ($latePackComponents as $latePackComponent) {
                    $latePackComponent->setErrorMessage("Erreur : Impossible de charger le composant");
                }
                $this->entityManager = $this->getEntityManager();
            }
        }
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
