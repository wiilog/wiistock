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
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        $email = $attributes['E-Mail-Addressid'];

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new Utilisateur();
            $user
                ->setStatus(true)
                ->setPassword('notused')
                ->setEmail($email)
                ->setUsername($attributes['Given-Name'] . ' ' . $attributes['Name'])
                ->setRole($roleRepository->findOneBy(['label' => Role::NO_ACCESS_USER]))
                ->setMobileLoginKey('');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
        return $user;
    }
}
