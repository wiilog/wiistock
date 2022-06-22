<?php

namespace App\Security;

use App\Entity\Role;
use App\Entity\Utilisateur;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SAMLUserFactory implements SamlUserFactoryInterface
{
    /** @Required  */
    public EntityManagerInterface $entityManager;

    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $user = new Utilisateur();
        $user
            ->setStatus(true)
            ->setPassword('notused')
            ->setEmail($attributes['Adresse de messagerie'])
            ->setUsername($attributes['Nom'] . ' ' . $attributes['Prénom'])
            ->setRole($roleRepository->findOneBy(['label' => Role::NO_ACCESS_USER]))
            ->setMobileLoginKey('');
        return $user;
    }
}
