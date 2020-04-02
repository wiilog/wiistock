<?php

namespace App\Controller;

use App\Entity\Role;
use App\Repository\RoleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\UserService;


class SecuriteController extends AbstractController
{
    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordService;

    /**
     * @var PasswordService
     */
    private $passwordEncoder;

    /**
     * @var RoleRepository
     */
    private $roleRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(UtilisateurRepository $utilisateurRepository, PasswordService $passwordService, RoleRepository $roleRepository, UserService $userService, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordService = $passwordService;
        $this->roleRepository = $roleRepository;
        $this->userService = $userService;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/", name="default")
     */
    public function index()
    {
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/login/{info}", name="login", options={"expose"=true})
     */
    public function login(AuthenticationUtils $authenticationUtils, string $info = '')
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $errorToDisplay = "";

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        $user = $this->utilisateurRepository->findOneByMail($lastUsername);
        if ($user && $user->getStatus() === false) {
            $errorToDisplay = 'Utilisateur inactif.';
        } else if ($error) {
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
     * @param Request $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, EntityManagerInterface $entityManager)
    {
        $session = $request->getSession();
        $user = new Utilisateur();

        $form = $this->createForm(UtilisateurType::class, $user);
        $roleRepository = $entityManager->getRepository(Role::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user
                ->setStatus(true)
                ->setPassword($password)
                ->setRoles(['USER']) // évite bug -> champ roles ne doit pas être vide
                ->setRole($roleRepository->findOneByLabel(Role::NO_ACCESS_USER))
                ->setColumnVisible(Utilisateur::COL_VISIBLE_REF_DEFAULT)
				->setColumnsVisibleForArticle(Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT)
				->setRecherche(Utilisateur::SEARCH_DEFAULT);
            $entityManager->persist($user);
            $entityManager->flush();
            $session->getFlashBag()->add('success', 'Votre nouveau compte a été créé avec succès.');

            return $this->redirectToRoute('login');
        }

        return $this->render('securite/register.html.twig', [
            'controller_name' => 'SecuriteController',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/check_last_login", name="check_last_login")
     */
    public function checkLastLogin(EntityManagerInterface $em)
    {
    	/** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            throw new UsernameNotFoundException(
                sprintf('L\'utilisateur n\'existe pas.')
            );
        } elseif ($user->getStatus() === false) {
            throw new UsernameNotFoundException(
                sprintf('Le compte est inactif')
            );
        }
        $user->setLastLogin(new \Datetime('', new \DateTimeZone('Europe/Paris')));

        // remplit champ columnVisiblesForArticle si vide
		if (empty($user->getColumnsVisibleForArticle())) {
			$user->setColumnsVisibleForArticle(Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT);
		}
		// remplit champ columnVisibles si vide
		if (empty($user->getColumnVisible())) {
			$user->setColumnVisible(Utilisateur::COL_VISIBLE_REF_DEFAULT);
		}

		// remplit champ recherche rapide si vide
		if (empty($user->getRecherche())) {
			$user->setRecherche(Utilisateur::SEARCH_DEFAULT);
		}
		// remplit champ recherche rapide article si vide
		if (empty($user->getRechercheForArticle())) {
			$user->setRechercheForArticle(Utilisateur::SEARCH_DEFAULT);
		}

		$em->flush();

		return $this->redirectToRoute('accueil');
    }

    /**
     * @Route("/attente_validation", name="attente_validation")
     */
    public function attente_validation()
    {
        return $this->render('securite/attente_validation.html.twig', [
            //            'controller_name' => 'SecuriteController',
        ]);
    }

    /**
     * @Route("/acces-refuse", name="access_denied")
     */
    public function access_denied()
    {
        return $this->render('securite/access_denied.html.twig');
    }

    /**
     * @Route("/change-password", name="change_password", options={"expose"=true}, methods="GET|POST")
     */
    public function change_password(Request $request)
    {
        $token = $request->get('token');
        return $this->render('securite/change_password.html.twig', ['token' => $token]);
    }

    /**
     * @Route("/change-password-in-bdd", name="change_password_in_bdd", options={"expose"=true}, methods="GET|POST")
     */
    public function change_password_in_bdd(Request $request, UserPasswordEncoderInterface $passwordEncoder) : Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $token = $data['token'];
            $user = $this->utilisateurRepository->findOneByToken($token);
            if (!$user) {
                return new JsonResponse('Le lien a expiré. Veuillez refaire une demande de renouvellement de mot de passe.');
            }
            elseif ($user->getStatus() === true) {
                $password = $data['password'];
                $password2 = $data['password2'];
                $result = $this->passwordService->checkPassword($password,$password2);

                if ($result['response'] == true) {
                    if ($password !== '') {
                        $password = $passwordEncoder->encodePassword($user, $password);
                        $user->setPassword($password);
                        $user->setToken(null);

                        $em = $this->getDoctrine()->getManager();
                        $em->persist($user);
                        $em->flush();

                        return new JsonResponse('ok');
                    }
                }
                else  {
                    return new JsonResponse($result['message']);
                }
            }
            else {
                return new JsonResponse('access_denied');
            }
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        return $this->redirectToRoute('login');
    }

    /**
     * @Route("/oubli", name="forgotten")
     */
    public function forgot()
    {
        return $this->render('securite/resetPassword.html.twig');
    }

    /**
     * @Route("/verifier-email", name="check_email", options={"expose"=true}, methods="GET|POST")
     */
    public function checkEmail(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $email = json_decode($request->getContent())) {
        	$errorCode = '';

            $user = $this->utilisateurRepository->findOneByMail($email);
            if ($user) {
				if ($user->getStatus()) {
					$token = $this->passwordService->generateToken(80);
					$this->passwordService->sendToken($token, $email);
				} else {
					$errorCode = 'inactiv';
				}
			} else {
				$errorCode = 'mailNotFound';
			}

            return new JsonResponse($errorCode);
        }
        throw new NotFoundHttpException('404');
    }
}
