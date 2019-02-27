<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Knp\Component\Pager\PaginatorInterface;

// use Proxies\__CG__\App\Entity\Utilisateur;

/**
 * @Route("/admin/utilisateur")
 */
class UtilisateurController extends Controller
{
    /**
     * @Route("/", name="utilisateur_index", methods="GET|POST")
     */
    public function index(UtilisateurRepository $utilisateurRepository, Request $request) : Response
    {
        if($_POST) 
        {
            $utilisateurId = array_keys($_POST); /* Chaque clé représente l'id d'un utilisateur */
            for($i = 1; $i < count($utilisateurId); $i++) /* Pour chaque utilisateur on regarde si le rôle a changé */
            {
                $utilisateur = $utilisateurRepository->find($utilisateurId[$i]);
                $roles = $utilisateur->getRoles(); /* On regarde le rôle de l'utilisateur */
                if($roles[0] != $_POST[$utilisateurId[$i]]) /* Si le rôle a changé on le modifie dans la bdd */
                {
                    $em = $this->getDoctrine()->getEntityManager();
                    $utilisateur->setRoles([$_POST[$utilisateurId[$i]]]);
                    $em->flush();
                }
            }
        }
        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),


        ]);
    }

    /**
     * @Route("/create", name="utilisateur_index_create", methods="GET|POST")
     */
    public function create(Request $request, EntityManagerInterface $em, UtilisateurRepository $utilisateurRepository/* , UserPasswordEncoderInterface $passwordEncoder */)
    {

        /* Création nouvel utilisateur si POST */
        if (array_key_exists('username', $_POST) 
        && array_key_exists('email', $_POST)
        && array_key_exists('password', $_POST)
        && array_key_exists('password2', $_POST)
        ) {
            /* On vérifie les erreurs */
            echo "1";
            $erreurs = array();
            $userCount = $utilisateurRepository->countByEmail($_POST['email']);

            /* On vérifie si l'email est valide ou si l'utilisateur existe déja */
            if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                array_push($erreurs, "L'adresse email est incorrect");
            }
            else if($userCount === '1')
            {
                array_push($erreurs, "Cette adresse est déja utilisée");
            }
            
            /* Si le mot de passe est trop court */
            if($_POST['password'] < 4) {
                array_push($erreurs, "Votre mot de passe est trop court");
                if($_POST['password'] != $_POST['password2']) {
                    array_push($erreurs, "Veuillez entrer le même mot de passe");
                }
            }

            if(count($erreurs) === 0) { /* Si la création d'utilisateur est valide on crée l'utilisateur */

                $utilisateur = new Utilisateur();
                
                foreach($_POST as $information) { 
                    strip_tags($information);
                }

                $password = $_POST['password']; /* Pour futur traitement comme le hashage*/

                $utilisateur->setUsername($_POST['username']);
                $utilisateur->setEmail($_POST['email']);
                $utilisateur->setPassword($password);
                
                /* Il faut ajouter le rôle utilisateur */

                $em = $this->getDoctrine()->getManager();
                $em->persist($utilisateur);
                $em->flush();
                return $this->redirectToRoute('utilisateur_index');

            }
            else /* Sinon on envoi un tableau d'erreurs */
            {
                return $this->render('utilisateur/create.html.twig', [
                    'erreurs' => $erreurs
                ]);
            }
            
        }
        else
        {
            return $this->render('utilisateur/create.html.twig');
        }
    }


    /**
     * @Route("/modif", name="utilisateur_index_modif", methods="GET|POST")
     */
    public function modif(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder)
    {

        if ($request->isXmlHttpRequest()) {

            $id = $request->request->get('id');
            $user = $em->getRepository(Utilisateur::class)->find($id);

            $encoders = array(new JsonEncoder());
            $normalizers = array(new ObjectNormalizer());

            $serializer = new Serializer($normalizers, $encoders);
            $jsonContent = $serializer->serialize($user, 'json');
            return new JsonResponse($jsonContent);
        }
        throw new NotFoundHttpException('404 Léo not found');
    }


    /**
     * @Route("/modif_bis", name="utilisateur_index_modif_bis", methods="GET|POST")
     */
    public function modif_bis(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder)
    {

        if ($request->isXmlHttpRequest()) {

            $user_modif = $request->request->get('user');


            $id = $user_modif[2]["value"];

            $user = $em->getRepository(Utilisateur::class)->find($id);

            $user->setUsername($user_modif[0]["value"]);
            $user->setEmail($user_modif[1]["value"]);

            $plain_password = $user_modif[3]["value"];
            if ($plain_password) {
                $new_password = $passwordEncoder->encodePassword($user, $plain_password);
                $user->setPassword($new_password);
            }

            $roles = array();
            for ($i = 4; $i < count($user_modif) - 1; ++$i) {
                array_push($roles, $user_modif[$i]["value"]);
            }
            $user->setRoles($roles);

            $em->flush();
            $session = $request->getSession();
            $session->getFlashBag()->add('success', 'Félicitations ! L\'utilisateur a été modifié avec succès !');

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404 Léo not found');
    }


    /**
     * @Route("/ajax/username", name="utilisateur_username_error", methods="GET|POST")
     */
    public function utlisateurs_username_error(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $username = $request->request->get('username');

            $utilisateurs = $em->getRepository(Utilisateur::class)->findAll();
            foreach ($utilisateurs as $utilisateur) {
                if (!strcmp($username, $utilisateur->getUsername())
                    && $utilisateur->getUsername() != null) {
                    return new JsonResponse(true);
                }
            }
            return new JsonResponse(false);
        }
        throw new NotFoundHttpException('404 Léo not found');
    }

    /**
     * @Route("/ajax/email", name="utilisateur_email_error", methods="GET|POST")
     */
    public function utlisateurs_email_error(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $em = $this->getDoctrine()->getManager();
            $email = $request->request->get('email');

            $utilisateurs = $em->getRepository(Utilisateur::class)->findAll();
            foreach ($utilisateurs as $utilisateur) {
                if (!strcmp($email, $utilisateur->getEmail())
                    && $utilisateur->getEmail() != null) {
                    return new JsonResponse(true);
                }
            }
            return new JsonResponse(false);
        }
        throw new NotFoundHttpException('404 Léo not found');
    }
 

    /**
     * @Route("/new", name="utilisateur_new", methods="GET|POST")
     */
    public function new(Request $request, UserPasswordEncoderInterface $passwordEncoder) : Response
    {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $password = $passwordEncoder->encodePassword($utilisateur, $utilisateur->getPlainPassword());
            $utilisateur->setPassword($password);
            $em->persist($utilisateur);
            $em->flush();

            return $this->redirectToRoute('utilisateur_index');
        }

        return $this->render('utilisateur/new.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="utilisateur_show", methods="GET")
     */
    public function show(Utilisateur $utilisateur) : Response
    {
        $receptions = $utilisateur->getReceptions();
        $demandes = $utilisateur->getDemandes();
        $alertes = $utilisateur->getUtilisateurAlertes();

        return $this->render('utilisateur/show.html.twig', [
            'utilisateur' => $utilisateur, 
            'receptions' => $receptions,
            'demandes' => $demandes,
            'alertes' => $alertes
        ]);
    }

    /**
     * @Route("/{id}/edit", name="utilisateur_edit", methods="GET|POST")
     */
    public function edit(Request $request, Utilisateur $utilisateur, UserPasswordEncoderInterface $passwordEncoder) : Response
    {
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = $passwordEncoder->encodePassword($utilisateur, $utilisateur->getPlainPassword());
            $utilisateur->setPassword($password);
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('utilisateur_edit', ['id' => $utilisateur->getId()]);
        }

        return $this->render('utilisateur/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="utilisateur_delete", methods="DELETE")
     */
    public function delete(Request $request, Utilisateur $utilisateur) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $utilisateur->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($utilisateur);
            $em->flush();
        }

        return $this->redirectToRoute('utilisateur_index');
    }

    /**
     * @Route("/{id}/remove", name="utilisateur_remove", methods="DELETE")
     */
    public function remove(Request $request, Utilisateur $utilisateur) : Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($utilisateur);
        $em->flush();
        $session = $request->getSession();
        $session->getFlashBag()->add('success', 'Félicitations ! L\'utilisateur a été supprimé avec succès !');


        return $this->redirectToRoute('utilisateur_index');
    }
}
