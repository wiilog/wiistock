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
    /**
     * @var password
     */
    private $username;

    /**
     * @var password
     */
    private $password;

    public function __construct(UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->username = 'admin@wiilog.fr'; // TODO
        $this->password = 'Kellhus16^^'; // TODO
        $this->transport = (new Swift_SmtpTransport('smtp.sendgrid.net', 465, 'ssl'))
            ->setUsername($this->username)
            ->setPassword($this->password);
        $this->mailer = (new Swift_Mailer($this->transport));
    }

    public function sendNewPassword($to)
    {
        $newPass = $this->generatePassword(10);
        if (gettype($this->updateUser($to, $newPass)) !== JsonResponse) {
            $message = (new \Swift_Message('Oubli de mot de passe Wiilog.'))
                ->setFrom([$this->username => 'L\'équipe de Wiilog.'])
                ->setTo($to)
                ->setBody('Votre nouveau mot de passe est : '.$newPass);

            $this->mailer->send($message);
        }
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
