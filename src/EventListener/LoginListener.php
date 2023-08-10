<?php

namespace App\EventListener;

use App\Entity\CategoryType;
use App\Entity\Type;
use App\Service\SessionHistoryService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use App\Entity\Utilisateur;
use Symfony\Contracts\Service\Attribute\Required;

class LoginListener
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryService $sessionService;

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        /** @var Utilisateur $user */
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof Utilisateur) {
            $typeRepository = $this->entityManager->getRepository(Type::class);
            $type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::SESSION_HISTORY, Type::LABEL_WEB_SESSION_HISTORY);
            $this->sessionService->logSession($this->entityManager, $user, $event->getRequest(), new DateTime('now'), $type);
        }
    }
}
