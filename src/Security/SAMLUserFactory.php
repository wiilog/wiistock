<?php

namespace App\Security;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\LanguageService;
use App\Service\MailerService;
use App\Service\TranslationService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;
use Symfony\Contracts\Service\Attribute\Required;

class SAMLUserFactory implements SamlUserFactoryInterface
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Environment $templating;

    #[Required]
    public UserService $userService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public TranslationService $translation;

    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        $email = $identifier;

        $user = $userRepository->findOneByEmail($email);
        if (!$user) {
            $language = $this->languageService->getNewUserLanguage();

            $user = new Utilisateur();
            $user
                ->setStatus(true)
                ->setPassword('notused')
                ->setEmail($email)
                ->setUsername($email)
                ->setLanguage($language)
                ->setDateFormat(Utilisateur::DEFAULT_DATE_FORMAT)
                ->setRole($roleRepository->findOneBy(['label' => Role::NO_ACCESS_USER]))
                ->setMobileLoginKey($this->userService->createUniqueMobileLoginKey($this->entityManager));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->userService->notifyUserCreation($this->entityManager, $user);
        }
        return $user;
    }
}
