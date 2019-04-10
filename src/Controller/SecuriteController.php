<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Form\UtilisateurType;
use App\Entity\Utilisateur;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class SecuriteController extends Controller
{
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

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('securite/login.html.twig', [
            'controller_name' => 'SecuriteController',
            'last_username' => $lastUsername,
            'error' => $error,
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
            $user->setPassword($password);
            $user->setRoles(array('ROLE_USER'));

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
        }
        $user->setLastLogin(new \Datetime());
        $em->flush();

//        $roles = $user->getRoles();

//        if ($this->isGranted("ROLE_STOCK")) {
//            return $this->redirectToRoute('accueil');
//        }
//
//        if ($this->isGranted("ROLE_PARC")) {
//            return $this->redirectToRoute('parc_list');
//        }
        if ($this->isGranted('ROLE_CUSTOMER')) {
            return $this->redirectToRoute('accueil');
        }

        return $this->redirectToRoute('attente_validation');
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
     * @Route("/change_password", name="change_password")
     */
    public function change_password(EntityManagerInterface $em, Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {

        $session = $request->getSession();
        $user = $this->getUser();
        $form = $this->createFormBuilder()
            ->add("password", PasswordType::class, array(
                'label' => "Mot de Passe actuel"
            ))
            ->add("plainPassword", RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options' => array('label' => 'Nouveau Mot de Passe'),
                'second_options' => array('label' => 'Confirmer Nouveau Mot de Passe')
            ))
            ->add("modifier", SubmitType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($passwordEncoder->isPasswordValid($user, $data["password"])) {
                $new_password = $passwordEncoder->encodePassword($user, $data["plainPassword"]);
                $user->setPassword($new_password);
                $em->persist($user);
                $em->flush();
                $session->getFlashBag()->add('success', 'Le mot de passe a bien été modifié');
                return $this->redirectToRoute('check_last_login');
            } else {
                $session->getFlashBag()->add('danger', 'Mot de passe invalide');
            }
        }

        return $this->render('securite/change_password.html.twig', [
            'controller_name' => 'SecuriteController',
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        return $this->redirectToRoute('login');
    }
}
