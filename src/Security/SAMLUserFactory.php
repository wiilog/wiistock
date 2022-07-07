<?php

namespace App\Security;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\MailerService;
use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\ORM\EntityManagerInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

class SAMLUserFactory implements SamlUserFactoryInterface
{
    /** @Required  */
    public EntityManagerInterface $entityManager;

    /** @Required  */
    public MailerService $mailerService;

    /** @Required  */
    public Environment $templating;

    public function createUser(string $identifier, array $attributes): UserInterface
    {
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        $email = $identifier;

        $user = $userRepository->findOneByEmail($email);
        if (!$user) {
            $user = new Utilisateur();
            $user
                ->setStatus(true)
                ->setPassword('notused')
                ->setEmail($email)
                ->setUsername($email)
                ->setRole($roleRepository->findOneBy(['label' => Role::NO_ACCESS_USER]))
                ->setMobileLoginKey('');

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $userMailByRole = $userRepository->getUserMailByIsMailSendRole();
            if(!empty($userMailByRole)) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // Notification de création d\'un compte utilisateur',
                    $this->templating->render('mails/contents/mailNouvelUtilisateur.html.twig', [
                        'user' => $user->getUsername(),
                        'mail' => $user->getEmail(),
                        'title' => 'Création d\'un nouvel utilisateur'
                    ]),
                    $userMailByRole
                );
            }
        }
        return $user;
    }
}
