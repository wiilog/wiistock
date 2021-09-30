<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use App\Entity\Utilisateur;

class LoginListener
{
    /** @Required */
    public EntityManagerInterface $entityManager;

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
// Get the User entity.
        /** @var Utilisateur $user */
        $user = $event->getAuthenticationToken()->getUser();

// Update your field here.
        $user->setLastLogin(new \DateTime());

// Persist the data to database.
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
