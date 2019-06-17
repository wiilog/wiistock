<?php

namespace App\Service;

use App\Repository\UtilisateurRepository;
use App\Repository\MailerServerRepository;
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
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var \Twig_Environment
     */
    private $templating;


    public function __construct(MailerServerRepository $mailerServerRepository, UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, EntityManagerInterface $entityManager, MailerService $mailerService, \Twig_Environment $templating)
    {
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
    }

    public function sendToken($token, $to)
    {
        $this->mailerService->sendMail(
        'FOLLOW GT // Mot de passe oublié',
            $this->templating->render('mails/mailForgotPassword.html.twig',
            ['token' => $token]),
        $to);
        return $token;
    }

    public function generateToken($length)
    {
        do {
            $generated_string = '&';
            $domain = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
            $len = strlen($domain);
            for ($i = 0; $i < $length; ++$i) {
                $index = rand(0, $len - 1);
                $generated_string = $generated_string . $domain[$index];
            }
        } while (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $generated_string));

        return $generated_string;
    }

    private function updatePasswordUser($mail, $password)
    {
        $user = $this->utilisateurRepository->getByMail($mail);
        if ($user) {
            $password = $this->passwordEncoder->encodePassword($user, $password);
            $user->setPassword($password);
            $this->entityManager->flush();
            $return = true;
        } else {
            $return = false;
        }
        return new JsonResponse($return);
    }
}
