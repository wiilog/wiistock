<?php

namespace App\EventListener;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use App\Entity\Utilisateur;

class LoginListener
{
    /** @Required */
    public EntityManagerInterface $entityManager;

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        /** @var Utilisateur $user */
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof Utilisateur) {
            $user->setLastLogin(new DateTime());
            $this->entityManager->flush();
        }
    }
}
