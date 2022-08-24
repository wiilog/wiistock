<?php

namespace App\Controller;

use App\Entity\Role;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\UtilisateurType;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Service\PasswordService;
use Symfony\Component\HttpFoundation\Response;
use App\Service\UserService;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use DateTime;

class SecuriteController extends AbstractController {

    #[Required]
    public PasswordService $passwordService;

    #[Required]
    public UserPasswordHasherInterface $passwordEncoder;

    #[Required]
    public UserService $userService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Twig_Environment $templating;

    /**
     * @Route("/", name="default")
     */
    public function index(): Response {
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/verification-connexion", name="check_login", options={"expose"=true})
     */
    public function checkLogin(): Response {
        return $this->json([
            "success" => true,
            "loggedIn" => $this->getUser() !== null,
        ]);
    }


    /**
     * @Route("/login/{success}", name="login", options={"expose"=true})
     */
    public function login(AuthenticationUtils $authenticationUtils,
                          EntityManagerInterface $entityManager,
                          string $success = ''): Response {
        $loggedUser = $this->getUser();
        if($loggedUser && $loggedUser instanceof Utilisateur) {
            $loggedUser->setLastLogin(new DateTime('now'));
            $entityManager->flush();
            return $this->redirectToRoute('app_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $errorToDisplay = "";

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $user = $utilisateurRepository->findOneBy(['email' => $lastUsername]);
        if($user && $user->getStatus() === false) {
            $errorToDisplay = 'L\'utilisateur est inactif';
        } else if($error) {
            $errorToDisplay = 'Les identifiants renseignés sont incorrects';
        }

        return $this->render('securite/login.html.twig', [
            'controller_name' => 'SecuriteController',
            'last_username' => $lastUsername,
            'error' => $errorToDisplay,
            'success' => $success
        ]);
    }

    /**
     * @Route("/register", name="register")
     */
    public function register(Request $request,
                             PasswordService $passwordService,
                             EntityManagerInterface $entityManager): Response {
        $session = $request->getSession();
        $user = new Utilisateur();

        $form = $this->createForm(UtilisateurType::class, $user);
        $roleRepository = $entityManager->getRepository(Role::class);
        $utilisateur = $entityManager->getRepository(Utilisateur::class);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $check = $passwordService->checkPassword($user->getPlainPassword(), $user->getPlainPassword());

            if(!$check["response"]) {
                $form->get("plainPassword")->get("first")->addError(new FormError($check["message"]));
                $form->get("plainPassword")->get("second")->addError(new FormError($check["message"]));
            } else {
                $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($entityManager);
                $password = $this->passwordEncoder->hashPassword($user, $user->getPlainPassword());
                $user
                    ->setStatus(true)
                    ->setPassword($password)
                    ->setRole($roleRepository->findOneBy(['label' => Role::NO_ACCESS_USER]))
                    ->setMobileLoginKey($uniqueMobileKey);
                $entityManager->persist($user);
                $entityManager->flush();

                $userMailByRole = $utilisateur->getUserMailByIsMailSendRole();
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

                $session->getFlashBag()->add('success', 'Votre nouveau compte a été créé avec succès.');

                return $this->redirectToRoute('login');
            }
        }

        return $this->render('securite/register.html.twig', [
            'controller_name' => 'SecuriteController',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/acces-refuse", name="access_denied")
     */
    public function access_denied(): Response {
        return $this->render('securite/access_denied.html.twig');
    }

    /**
     * @Route("/change-password", name="change_password", options={"expose"=true}, methods="GET|POST")
     */
    public function change_password(Request $request): Response {
        $token = $request->get('token');
        return $this->render('securite/change_password.html.twig', ['token' => $token]);
    }

    /**
     * @Route("/change-password-in-bdd", name="change_password_in_bdd", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function change_password_in_bdd(Request $request,
                                           EntityManagerInterface $entityManager): Response {

        $data = $request->query->all();
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['token' => $data['token']]);

        $response = [];
        if(!$user) {
            $response = [
                'success' => false,
                'msg' => 'Le lien a expiré. Veuillez refaire une demande de renouvellement de mot de passe.'
            ];
        } else if($user->getStatus()) {
            $password = $data['password'];
            $password2 = $data['password2'];
            $result = $this->passwordService->checkPassword($password, $password2);

            if($result['response']) {
                if($password !== '') {
                    $password = $this->passwordEncoder->hashPassword($user, $password);
                    $user->setPassword($password);
                    $user->setToken(null);

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $response = [
                        'success' => true,
                        'msg' => 'Votre mot de passe a bien été modifié.'
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'msg' => $result['message']
                ];
            }
        } else {
            return $this->redirectToRoute("access_denied");
        }

        return $this->json($response);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout(): Response {
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/oubli", name="forgotten")
     */
    public function forgot(): Response {
        return $this->render('securite/resetPassword.html.twig');
    }

    /**
     * @Route("/verifier-email", name="check_email", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function checkEmail(Request $request, EntityManagerInterface $entityManager): Response {

        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $email = $request->request->get('email');

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                "success" => false,
                "msg" => "L'adresse email renseignée est invalide.",
            ]);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if($user) {
            if($user->getStatus()) {
                $token = $this->passwordService->generateToken(80);
                $this->passwordService->sendToken($token, $email);

                $response = [
                    'success' => true,
                    'msg' => "Un lien pour réinitialiser le mot de passe de votre compte vient d'être envoyé sur votre adresse email."
                ];
            } else {
                $response = [
                    'success' => false,
                    'msg' => "Votre compte est inactif, veuillez contacter l'administrateur de votre application."
                ];
            }
        } else {
            $response = [
                'success' => false,
                'msg' => "L'adresse email renseignée n'est liée à aucun utilisateur."
            ];
        }

        return $this->json($response);
    }

}
