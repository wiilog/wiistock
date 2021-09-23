<?php

namespace App\Controller;

use App\Entity\Role;
use App\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\UtilisateurType;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use App\Service\PasswordService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Service\UserService;
use Twig\Environment as Twig_Environment;
use DateTime;

class SecuriteController extends AbstractController {

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordService;

    /**
     * @var PasswordService
     */
    private $passwordEncoder;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var Twig_Environment
     */
    private $templating;

    public function __construct(PasswordService $passwordService,
                                UserService $userService,
                                UserPasswordEncoderInterface $passwordEncoder,
                                MailerService $mailerService,
                                Twig_Environment $templating) {
        $this->passwordService = $passwordService;
        $this->userService = $userService;
        $this->passwordEncoder = $passwordEncoder;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
    }

    /**
     * @Route("/", name="default")
     */
    public function index() {
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/verification-connexion", name="check_login", options={"expose"=true})
     */
    public function checkLogin() {
        return $this->json([
            "success" => true,
            "loggedIn" => $this->getUser() !== null,
        ]);
    }


    /**
     * @Route("/login/{info}", name="login", options={"expose"=true})
     */
    public function login(AuthenticationUtils $authenticationUtils,
                          EntityManagerInterface $entityManager,
                          string $info = '') {
        $loggedUser = $this->getUser();
        if($loggedUser && $loggedUser instanceof Utilisateur) {
            $loggedUser->setLastLogin(new DateTime('now'));
            $entityManager->flush();
            return $this->redirectToRoute('accueil');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $errorToDisplay = "";

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $user = $utilisateurRepository->findOneBy(['email' => $lastUsername]);
        if($user && $user->getStatus() === false) {
            $errorToDisplay = 'Utilisateur inactif.';
        } else if($error) {
            $errorToDisplay = 'Identifiants incorrects.';
        }

        return $this->render('securite/login.html.twig', [
            'controller_name' => 'SecuriteController',
            'last_username' => $lastUsername,
            'error' => $errorToDisplay,
            'info' => $info
        ]);
    }

    /**
     * @Route("/register", name="register")
     */
    public function register(Request $request,
                             PasswordService $passwordService,
                             EntityManagerInterface $entityManager) {
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
                $password = $this->passwordEncoder->encodePassword($user, $user->getPlainPassword());
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
     * @Route("/check_last_login", name="check_last_login")
     */
    public function checkLastLogin(EntityManagerInterface $em) {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if(!$user) {
            throw new UsernameNotFoundException(
                sprintf('L\'utilisateur n\'existe pas.')
            );
        } elseif($user->getStatus() === false) {
            throw new UsernameNotFoundException(
                sprintf('Le compte est inactif')
            );
        }
        $user->setLastLogin(new Datetime(''));

        // remplit champ columnVisiblesForArticle si vide
        if(empty($user->getColumnsVisibleForArticle())) {
            $user->setColumnsVisibleForArticle(Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT);
        }
        // remplit champ columnVisibles si vide
        if(empty($user->getColumnVisible())) {
            $user->setColumnVisible(Utilisateur::COL_VISIBLE_REF_DEFAULT);
        }

        if(empty($user->getColumnsVisibleForArrivage())) {
            $user->setColumnsVisibleForArrivage(Utilisateur::COL_VISIBLE_ARR_DEFAULT);
        }

        if(empty($user->getColumnsVisibleForDispatch())) {
            $user->setColumnsVisibleForDispatch(Utilisateur::COL_VISIBLE_DISPATCH_DEFAULT);
        }

        if(empty($user->getColumnsVisibleForTrackingMovement())) {
            $user->setColumnsVisibleForTrackingMovement(Utilisateur::COL_VISIBLE_TRACKING_MOVEMENT_DEFAULT);
        }

        // remplit champ columnVisibles si vide
        if(empty($user->getColumnsVisibleForLitige())) {
            $user->setColumnsVisibleForLitige(Utilisateur::COL_VISIBLE_LIT_DEFAULT);
        }

        // remplit champ recherche rapide si vide
        if(empty($user->getRecherche())) {
            $user->setRecherche(Utilisateur::SEARCH_DEFAULT);
        }
        // remplit champ recherche rapide article si vide
        if(empty($user->getRechercheForArticle())) {
            $user->setRechercheForArticle(Utilisateur::SEARCH_DEFAULT);
        }

        $em->flush();

        return $this->redirectToRoute('accueil');
    }

    /**
     * @Route("/attente_validation", name="attente_validation")
     */
    public function attente_validation() {
        return $this->render('securite/attente_validation.html.twig', [
            //            'controller_name' => 'SecuriteController',
        ]);
    }

    /**
     * @Route("/acces-refuse", name="access_denied")
     */
    public function access_denied() {
        return $this->render('securite/access_denied.html.twig');
    }

    /**
     * @Route("/change-password", name="change_password", options={"expose"=true}, methods="GET|POST")
     */
    public function change_password(Request $request) {
        $token = $request->get('token');
        return $this->render('securite/change_password.html.twig', ['token' => $token]);
    }

    /**
     * @Route("/change-password-in-bdd", name="change_password_in_bdd", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function change_password_in_bdd(Request $request,
                                           EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $token = $data['token'];
            $user = $utilisateurRepository->findOneBy(['token' => $token]);
            if(!$user) {
                return new JsonResponse('Le lien a expiré. Veuillez refaire une demande de renouvellement de mot de passe.');
            } elseif($user->getStatus() === true) {
                $password = $data['password'];
                $password2 = $data['password2'];
                $result = $this->passwordService->checkPassword($password, $password2);

                if($result['response'] == true) {
                    if($password !== '') {
                        $password = $this->passwordEncoder->encodePassword($user, $password);
                        $user->setPassword($password);
                        $user->setToken(null);

                        $em = $this->getDoctrine()->getManager();
                        $em->persist($user);
                        $em->flush();

                        return new JsonResponse('ok');
                    }
                } else {
                    return new JsonResponse($result['message']);
                }
            } else {
                return new JsonResponse('access_denied');
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout() {
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/oubli", name="forgotten")
     */
    public function forgot() {
        return $this->render('securite/resetPassword.html.twig');
    }

    /**
     * @Route("/verifier-email", name="check_email", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function checkEmail(Request $request, EntityManagerInterface $entityManager): Response {
        if($email = json_decode($request->getContent())) {
            $errorCode = '';
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json([
                    "success" => false,
                    "msg" => "Adresse email invalide",
                ]);
            }

            $user = $utilisateurRepository->findOneBy(['email' => $email]);
            if($user) {
                if($user->getStatus()) {
                    $token = $this->passwordService->generateToken(80);
                    $this->passwordService->sendToken($token, $email);
                } else {
                    $errorCode = 'inactiv';
                }
            } else {
                $errorCode = 'mailNotFound';
            }

            return $this->json($errorCode);
        }

        throw new BadRequestHttpException();
    }

}
