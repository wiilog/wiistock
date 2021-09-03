<?php

namespace App\Command;

use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class NotificationCommand extends Command {

    protected static $defaultName = "app:notifications:send";

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public NotificationService $notificationService;

    public function __construct(EntityManagerInterface $entityManager,
                                NotificationService $notificationService) {
        parent::__construct(self::$defaultName);
        $this->entityManager = $entityManager;
        $this->notificationService = $notificationService;
    }


    protected function configure() {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, '"preparation"|"delivery"|"collect"|"transfer"|"dispatch"|"service"')
            ->addArgument('id', InputArgument::REQUIRED, 'Id of entity which send the notification')
            ->setDescription("Send notification for a given entity");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $entityType = $input->getArgument('entity');
        $id = $input->getArgument('id');

        $class = $this->getClassFromEntityString($entityType);
        $repository = $this->entityManager->getRepository($class);
        $entity = $repository->find($id);

        $this->notificationService->toTreat($entity);

        return 0;
    }

    private function getClassFromEntityString(string $entity): ?string {
        $class = null;
        foreach (NotificationService::TYPE_BY_CLASS as $key => $value) {
            if ($value === $entity) {
                $class = $key;
                break;
            }
        }
        return $class;
    }
}
