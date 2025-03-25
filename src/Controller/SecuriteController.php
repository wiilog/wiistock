<?php

namespace App\Controller;

use App\Entity\Role;
use App\Security\UserChecker;
use App\Service\MailerService;
use Symfony\Component\Form\FormError;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
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

    #[Route("/", name: "default")]
    public function index(): Response {
        return $this->redirectToRoute('login');
    }

    #[Route("/login", name: "login", options: ["expose" => true], methods: [ self::GET,  self::POST])]
    public function login(AuthenticationUtils $authenticationUtils,
                          string $success = ''): Response {
        $loggedUser = $this->getUser();
        $securityContext = $this->container->get('security.authorization_checker');
        if ($loggedUser instanceof Utilisateur && $securityContext->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $errorToDisplay = "";

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        if(in_array($error?->getCode(), [UserChecker::ACCOUNT_DISABLED_CODE, UserChecker::NO_MORE_SESSION_AVAILABLE])) {
            $errorToDisplay = $error->getMessage();
        } else if($error) {
            $errorToDisplay = 'Les identifiants renseignés sont incorrects';
        }

        return $this->render('securite/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $errorToDisplay,
        ]);
    }

    #[Route("/register", name: "register")]
    public function register(Request                $request,
                             PasswordService        $passwordService,
                             EntityManagerInterface $entityManager): Response {
        $session = $request->getSession();
        $user = new Utilisateur();

        $form = $this->createForm(UtilisateurType::class, $user);
        $roleRepository = $entityManager->getRepository(Role::class);

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
                    ->setStatus(false)
                    ->setPassword($password)
                    ->setRole($roleRepository->findOneBy(['label' => Role::NO_ACCESS_USER]))
                    ->setMobileLoginKey($uniqueMobileKey);
                $entityManager->persist($user);
                $entityManager->flush();

                $this->userService->notifyUserCreation($entityManager, $user);

                $session->getFlashBag()->add('success', 'Votre nouveau compte a été créé avec succès.');

                return $this->redirectToRoute('login');
            }
        }

        return $this->render('securite/register.html.twig', [
            'controller_name' => 'SecuriteController',
            'form' => $form->createView(),
        ]);
    }

    #[Route("/acces-refuse", name: "access_denied")]
    public function access_denied(): Response {
        return $this->render('securite/access_denied.html.twig');
    }

    #[Route("/change-password", name: "change_password", options: ["expose" => true], methods: [ self::GET, self::POST])]
    public function change_password(Request $request): Response {
        $token = $request->get('token');

        return $token
            ? $this->render('securite/change_password.html.twig', ['token' => $token])
            : $this->redirectToRoute('login');
    }

    #[Route("/change-password-in-bdd", name: "change_password_in_bdd", options: ["expose" => true], methods: [ self::POST], condition: "request.isXmlHttpRequest()")]
    public function change_password_in_bdd(Request $request,
                                           EntityManagerInterface $entityManager): Response {
        $data = $request->request->all();
        $user = $data['token'] !== null ? $entityManager->getRepository(Utilisateur::class)->findOneBy(['token' => $data['token']]) : null;

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

    #[Route("/logout", name: "logout")]
    public function logout(): Response {
        return $this->redirectToRoute('login');
    }

    #[Route("/mot-de-passe-oublie", name: "password_forgotten")]
    public function passwordForgotten(): Response {
        return $this->render('securite/password_forgotten.html.twig');
    }

    #[Route("/reset-password", name: "reset_password_request", options: ["expose" => true], methods: [ self::POST ], condition: self::IS_XML_HTTP_REQUEST)]
    public function resetPasswordRequest(Request $request, EntityManagerInterface $entityManager): Response {
        $userRepository = $entityManager->getRepository(Utilisateur::class);
        $email = $request->request->get('email');

        $user = $email
            ? $userRepository->findOneBy(['email' => $email])
            : null;
        if($user) {
            if($user->getStatus()) {
                $token = $this->passwordService->generateToken(80);
                $this->passwordService->sendToken($token, $email);
            }
        }

        return $this->json([
            'success' => true,
            'msg' => "Un lien pour réinitialiser votre mot de passe vient d'être envoyé sur votre adresse email si elle correspond à un compte valide.",
        ]);
    }

}
