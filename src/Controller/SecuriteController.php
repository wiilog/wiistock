<?php

namespace App\Controller;

use App\Entity\Role;
use App\Repository\RoleRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Form\UtilisateurType;
use App\Entity\Utilisateur;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use App\Service\PasswordService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\UserService;


class SecuriteController extends Controller
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
     * @Route("/login", name="login")
     */
    public function login(Request $request, AuthenticationUtils $authenticationUtils)
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
        ]);
    }

    /**
     * @Route("/register", name="register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, EntityManagerInterface $em)
    {
        $session = $request->getSession();
        $user = new Utilisateur();

        $form = $this->createForm(UtilisateurType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
            $user
                ->setStatus(true)
                ->setPassword($password)
                ->setRoles(['USER']) // évite bug -> champ roles ne doit pas être vide
                ->setRole($this->roleRepository->findOneByLabel(Role::NO_ACCESS_USER))
                ->setColumnVisible(["Actions", "Libellé", "Référence", "Type", "Quantité", "Emplacement"])
                ->setRecherche(["Libellé", "Référence"]);
            $em->persist($user);
            $em->flush();
            $session->getFlashBag()->add('success', 'Félicitations ! Votre nouveau compte a été créé avec succès !');

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
    public function change_password()
    {
        return $this->render('securite/change_password.html.twig');
    }

    /**
     * @Route("/change-password-in-bdd", name="change_password_in_bdd", options={"expose"=true}, methods="GET|POST")
     */
    public function change_password_in_bdd(Request $request,  UserPasswordEncoderInterface $passwordEncoder, UserService $userService) : Response
    {
        dump('t0');

//        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
        $data = json_decode($request->getContent(), true);
            dump('t1');
//dump($data);
        echo $_GET['token'];
$token='&AnKC1KjbMm1BoXAUfJlumUlA15cPlkslYHzVVNWqxaZoG3mEWUAiwNVZkhk0TX0RrQcfnfFqmYWSRglD' ;
//dump($token);
            $user = $this->utilisateurRepository->findOneByToken($token); //c'est OK, on récupe l'utilisateur
            if ($user) {
                dump('t2');
                if($user->getStatus() === true){
                    $password = $data['password'];
//dump($password);

                    $userService->checkPassword($password , $data['passsword2']);
                    if($password !== ''){
                        $password = $passwordEncoder->encodePassword($user, $data['password']);
                        $user->setPassword($password);
                        $user->setToken('null');

                        $em = $this->getDoctrine()->getManager();
                        $em->persist($user);
                        $em->flush();
                        return $this->render('securite/login.html.twig');
                    }
                }
                else{ return new JsonResponse('access_denied');}
//            }
            return new JsonResponse('Cet utilisateur n\'existe pas');
        }
        throw new NotFoundHttpException('404');



//        if ($request->isXmlHttpRequest() && $mail = json_decode($request->getContent()) {
//            $user = $this->utilisateurRepository->getByMail($mail);
//            if ($user) {
//                if ($user->getStatus() === true) {
//                    $this->passwordService->updatePasswordUser($mail, $passwordEncoder);
//                    $user->setToken('null');
//                    $this->entityManager->flush();
//                }
//                else {
//                    return new JsonResponse('inactiv');
//                }
//                return new JsonResponse(false);
//            }
//            return new JsonResponse(true);
////        }

//        throw new NotFoundHttpException('404');

//        $session = $request->getSession();
//        $user = $this->getUser();
//        $form = $this->createFormBuilder()
//            ->add('password', PasswordType::class, array(
//                'label' => 'Adresse email',
//            ))
//            ->add('plainPassword', RepeatedType::class, array(
//                'type' => PasswordType::class,
//                'first_options' => array('label' => 'Nouveau Mot de Passe'),
//                'second_options' => array('label' => 'Confirmer Nouveau Mot de Passe'),
//            ))
//            ->add('modifier', SubmitType::class)
//            ->getForm();
//
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//            $data = $form->getData();
//            if ($passwordEncoder->isPasswordValid($user, $data['password'])) {
//                $new_password = $passwordEncoder->encodePassword($user, $data['plainPassword']);
//                $user->setPassword($new_password);
//                $em->persist($user);
//                $em->flush();
//                $session->getFlashBag()->add('success', 'Le mot de passe a bien été modifié');
//
//                return $this->redirectToRoute('check_last_login');
//            } else {
//                $session->getFlashBag()->add('danger', 'Mot de passe invalide');
//            }
//        }
//
//        return $this->render('securite/change_password.html.twig', [
//            'controller_name' => 'SecuriteController',
//            'form' => $form->createView(),
//        ]);
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
            $user = $this->utilisateurRepository->findOneByMail($email);
            if ($user) {
                if ($user->getStatus() === true) {
                    $token = $this->passwordService->generateToken(80);
                    $this->passwordService->sendToken($token, $email);
                }
                else {
                    return new JsonResponse('inactiv');
                }
                return new JsonResponse(false);
            }
            return new JsonResponse(true);
        }
        throw new NotFoundHttpException('404');
    }
}
