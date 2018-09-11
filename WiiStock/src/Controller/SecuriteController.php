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
use Symfony\Component\Config\Definition\Exception\Exception;

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
        dump($user);

    	$form = $this->createForm(UtilisateursType::class, $user);

    	$form->handleRequest($request);
    	if ($form->isSubmitted() && $form->isValid()) {
    		$password = $passwordEncoder->encodePassword($user, $user->getPlainPassword());
    		$user->setPassword($password);
            $user->setRoles(array('ROLE_USER'));

            dump($user);
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
     * @Route("/check_last_login", name="check_last_login")
     */
    public function checkLastLogin(EntityManagerInterface $em) {

        $user = $this->getUser();
        $user->setLastLogin(new \Datetime());
        $em->flush();
        
        $roles = $user->getRoles();
        /*
        $new_roles = array("ROLE_PARC_ADMIN", "ROLE_USER");
        $this->getUser()->setRoles($new_roles);
        $this->getDoctrine()->getManager()->flush();
        */

        if (in_array("ROLE_PARC", $roles) || in_array("ROLE_PARC_ADMIN", $roles)) {
            return $this->redirectToRoute('parc_list');
        }

        return $this->redirectToRoute('accueil');
        
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout() {
        return  $this->redirectToRoute('login');
    }
}
