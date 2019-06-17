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

    public function sendToken()
    {
        $token = $this->generateToken(30);
//        if ($this->updateUser($to, $newPass) === 1) {
    $this->mailerService->sendMail(
        'FOLLOW GT // Mot de passe oublié',
        $this->templating->render('mails/mailForgotPassword.html.twig')
//                ['password' => $newPass]), $to
    );
        return $token;
//        }
    }


    private function generateToken($length)
    {
        $generated_string = '';
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

//    private function updateUser($mail)
//    {
//        $user = $this->utilisateurRepository->getByMail($mail);
//        if ($user !== null) {
////            $password = $this->passwordEncoder->encodePassword($user);
////            $user->setPassword($password);
//            $this->entityManager->persist($user);
//            $this->entityManager->flush();
//
//            return 1;
//        } else {
//            return new JsonResponse('Adresse email inconnue.');
//        }
//    }
}
