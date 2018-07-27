<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Form\UtilisateursType;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SecuriteController extends Controller
{
    /**
     * @Route("/", name="default")
     */
    public function index() {
        return  $this->redirectToRoute('login');
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request,  AuthenticationUtils $authenticationUtils)
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

    	$user = new Utilisateurs();
    	$form = $this->createForm(UtilisateursType::class, $user);

    	$form->handleRequest($request);
    	if ($form->isSubmitted() && $form->isValid()) {
    		$password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
    		$user->setPassword($password);


    		$em->persist($user);
    		$em->flush();

    		return $this->redirectToRoute('login');
    	}

        return $this->render('securite/register.html.twig', [
            'controller_name' => 'SecuriteController',
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout() {
        return  $this->redirectToRoute('login');
    }
}
