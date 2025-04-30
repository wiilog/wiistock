<?php

namespace App\EventListener;

use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Service\SessionHistoryRecordService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessListener implements EventSubscriberInterface{
    public function __construct(
        private RequestStack                $requestStack,
        private EntityManagerInterface      $entityManager,
        private SessionHistoryRecordService $sessionHistoryRecordService,
    ){}

    public function onLoginSuccess(LoginSuccessEvent $event): void {
        $sessionId = $this->requestStack->getSession()->getId();
        $user = $event->getUser();
        $firewallName = $event->getFirewallName();
        if ($user instanceof Utilisateur
            && $sessionId
            && $firewallName === 'main') {
            $entityManager = $this->entityManager;
            $this->sessionHistoryRecordService->closeInactiveSessions($entityManager);
            $typeRepository = $entityManager->getRepository(Type::class);
            $type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::SESSION_HISTORY, Type::LABEL_WEB_SESSION_HISTORY);
            $this->sessionHistoryRecordService->newSessionHistoryRecord($entityManager, $user, new DateTime('now'), $type, $sessionId);
            $entityManager->flush();
       }
    }

    public static function getSubscribedEvents(): array {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }
}
