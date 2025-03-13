<?php

namespace App\EventListener;

use App\Service\SessionHistoryRecordService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Contracts\Service\Attribute\Required;

class LogoutListener
{

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryRecordService $sessionService;


    public function onSymfonyComponentSecurityHttpEventLogoutEvent(LogoutEvent $logoutEvent): void
    {
        $this->sessionService->closeSessionHistoryRecord($this->entityManager, $logoutEvent->getRequest()->getSession()->getId(), new DateTime('now'));
        $this->entityManager->flush();
    }
}
