<?php

namespace App\Service;

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
     * @var transport
     */
    private $transport;

    /**
     * @var mailer
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
        $this->username = 'mail';
        $this->password = 'pass';
        $this->transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, 'ssl')
            ->setUsername($this->username)
            ->setPassword($this->password);
        $this->mailer = Swift_Mailer::newInstance($this->transport);
    }

    public function sendNewPassword($to, $body)
    {
        $newPass = $this->generatePassword(10);
        updateUser($to, $newPass);
        $message = (new Swift_Message('Oubli de mot de passe Wiilog.'))
            ->setFrom([$this->username => 'L\'équipe de Wiilog.'])
            ->setTo([$to => explode('@', $to)])
            ->setBody('Votre nouveau mot de passe est : ' + $newPass);

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
        $user = $this->utilisateurRepository->getByMail($mail);
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
