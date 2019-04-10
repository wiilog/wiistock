<?php

namespace App\Service;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Swift_SmtpTransport;
use Swift_Mailer;

class PasswordService
{
    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var Swift_SmtpTransport
     */
    private $transport;

    /**
     * @var Swift_Mailer
     */
    private $mailer;

    public function __construct(UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, EntityManagerInterface $entityManager, \Swift_Mailer $mailer)
    {
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->mailer = $mailer;
    }

    public function sendNewPassword($from, $to)
    {
        $newPass = $this->generatePassword(10);
        $this->updateUser($to, $newPass);
        dump($to);
        dump($newPass);
        dump(explode('@', $to)[0]);
        $message = (new \Swift_Message('Oubli de mot de passe Wiilog.'))
            ->setFrom([$from => 'L\'équipe de Wiilog.'])
            ->setTo($to)
            ->setBody('Votre nouveau mot de passe est : '.$newPass, 'text/plain');

        $this->mailer->send($message);
    }

    private function generatePassword($length)
    {
        $generated_string = '';
        do {
            $generated_string = '&';
            $domain = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
            $len = strlen($domain);
            for ($i = 0; $i < $length; ++$i) {
                $index = rand(0, $len - 1);
                $generated_string = $generated_string.$domain[$index];
            }
        } while (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $generated_string));

        return $generated_string;
    }

    private function updateUser($mail, $newPass)
    {
        $user = $this->utilisateurRepository->getByMail($mail)[0];
        if ($user !== null) {
            $password = $this->passwordEncoder->encodePassword($user, $newPass);
            $user->setPassword($password);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            return new JsonResponse('Adresse email inconnue.');
        }
    }
}
