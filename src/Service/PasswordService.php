<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\MailerServerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

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
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var Twig_Environment
     */
    private $templating;


    public function __construct(MailerServerRepository $mailerServerRepository,
                                UserPasswordEncoderInterface $passwordEncoder,
                                EntityManagerInterface $entityManager,
                                MailerService $mailerService,
                                Twig_Environment $templating)
    {
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
    }


    public function sendToken($token, $to)
    {
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);
        $user = $utilisateurRepository->findOneBy(['email' => $to, 'status' => true]);
        if ($user) {
        	$user->setToken($token);
        	$this->entityManager->flush();
			$this->mailerService->sendMail(
				'FOLLOW GT // Mot de passe oublié',
				$this->templating->render('mails/template.html.twig', [
					'title' => 'Renouvellement de votre mot de passe Follow GT.',
					'urlSuffix' => '/change-password?token=' . $token,
					'buttonText' => 'Cliquez ici pour modifier votre mot de passe',
				]),
				$to);
		}
    }

    public function generateToken($length)
    {
        do {
            $generated_string = '';
            $domain = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
            $len = strlen($domain);
            for ($i = 0; $i < $length; ++$i) {
                $index = rand(0, $len - 1);
                $generated_string = $generated_string . $domain[$index];
            }
        } while (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$/', $generated_string));

        return $generated_string;
    }

	public function checkPassword($password, $password2)
	{
		if ($password === $password2 && $password === '') {
			$response = true;
			$message = '';
		} else if ($password !== $password2) {
			$response = false;
			$message = 'Les mots de passe ne correspondent pas.';
		} else if (strlen($password) < 8) {
			$response = false;
			$message = 'Le mot de passe doit faire au moins 8 caractères.';
		} else if(!$this->matchesAll($password, "[A-Z]", "[a-z]", "\d", "\W|_")) {
			$response = false;
			$message = "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial";
		} else {
			$response = true;
			$message = '';
		}

		return [
			'response' => $response,
			'message' => $message
		];
	}

    private function matchesAll($password, ...$regexes): bool {
        foreach($regexes as $regex) {
            if(!preg_match("/$regex/", $password))
                return false;
        }

        return true;
    }

}
