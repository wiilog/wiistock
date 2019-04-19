<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use http\Client\Curl\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @Route("/admin/utilisateur")
 */
class UtilisateurController extends Controller
{
    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var RoleRepository
     */
    private $roleRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(UtilisateurRepository $utilisateurRepository, RoleRepository $roleRepository, UserService $userService)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->roleRepository = $roleRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="user_index", methods="GET|POST")
     */
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($_POST) {
            $utilisateurId = array_keys($_POST); /* Chaque clé représente l'id d'un utilisateur */
            for ($i = 1; $i < count($utilisateurId); ++$i) /* Pour chaque utilisateur on regarde si le rôle a changé */ {
                $utilisateur = $utilisateurRepository->find($utilisateurId[$i]);
                $roles = $utilisateur->getRoles(); /* On regarde le rôle de l'utilisateur */
                if ($roles[0] != $_POST[$utilisateurId[$i]]) /* Si le rôle a changé on le modifie dans la bdd */ {
                    $em = $this->getDoctrine()->getEntityManager();
                    $utilisateur->setRoles([$_POST[$utilisateurId[$i]]]);
                    $em->flush();
                }
            }
        }

        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
            'roles' => $this->roleRepository->findAll()
        ]);
    }

    /**
     * @Route("/creer", name="user_new",  options={"expose"=true}, methods="GET|POST")
     */
    public function newUser(Request $request, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $password = $data['password'];

            // validation du mot de passe
            if ($password !== $data['password2']) {
                return new JsonResponse('Les mots de passe ne correspondent pas.');
                //TODO gérer retour erreur propre
            }
            if (strlen($password) < 8) {
                return new JsonResponse('Le mot de passe doit faire au moins 8 caractères.');
                //TODO gérer retour erreur propre
            }
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
                return new JsonResponse('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial.');
                //TODO gérer retour erreur propre
            }

            // validation de l'email
            $emailAlreadyUsed = intval($this->utilisateurRepository->countByEmail($data['email']));

            if ($emailAlreadyUsed) {
                return new JsonResponse('Un compte existe déjà avec cet email.');
                //TODO gérer retour erreur propre
            }

            $utilisateur = new Utilisateur();
            $password = $passwordEncoder->encodePassword($utilisateur, $data['password']);

            $role = $this->roleRepository->find($data['role']);
            $utilisateur
                ->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setRole($role)
                ->setRoles(['USER']) // évite bug -> champ roles ne doit pas être vide
                ->setPassword($password);
            $em = $this->getDoctrine()->getManager();
            $em->persist($utilisateur);
            $em->flush();

            return new JsonResponse(true);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="user_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $user = $this->utilisateurRepository->find($data);
            $json = $this->renderView('utilisateur/modalEditUserContent.html.twig', [
                'user' => $user,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="user_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request, UserPasswordEncoderInterface $passwordEncoder): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $utilisateur = $this->utilisateurRepository->find($data['user']);
            $password = $data['password'];
            if ($password !== '') {
                // validation du mot de passe
                if ($password !== $data['password2']) {
                    return new JsonResponse('Les mots de passe ne correspondent pas.');
                    //TODO gérer retour erreur propre
                }
                if (strlen($password) < 8) {
                    return new JsonResponse('Le mot de passe doit faire au moins 8 caractères.');
                    //TODO gérer retour erreur propre
                }
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
                    return new JsonResponse('Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial.');
                    //TODO gérer retour erreur propre
                }
            } else {
                if ($data['password2'] !== '') {
                    return new JsonResponse('Les mots de passe ne correspondent pas.');
                }
            }

            // validation de l'email
            $emailAlreadyUsed = intval($this->utilisateurRepository->countByEmail($data['email']));

            if ($emailAlreadyUsed && $data['email'] != $utilisateur->getEmail()) {
                return new JsonResponse('Un compte existe déjà avec cet email.');
                //TODO gérer retour erreur propre
            }

            $utilisateur
                ->setStatus($data['status'] === 'active')
                ->setUsername($data['username'])
                ->setEmail($data['email']);
            if ($password !== '') {
                $password = $passwordEncoder->encodePassword($utilisateur, $data['password']);
                $utilisateur->setPassword($password);
            }
            $em = $this->getDoctrine()->getManager();
            $em->persist($utilisateur);
            $em->flush();

            return new JsonResponse(true);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-role", name="user_edit_role",  options={"expose"=true}, methods="GET|POST")
     */
    public function editRole(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $role = $this->roleRepository->find((int)$data['role']);
            $user = $this->utilisateurRepository->find($data['userId']);

            if ($user) {
                $user->setRole($role);
                $em = $this->getDoctrine()->getManager();
                $em->flush();

                return new JsonResponse(true);
            } else {
                return new JsonResponse(false); //TODO gérer erreur
            }

        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api", name="user_api",  options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $utilisateurs = $this->utilisateurRepository->findAll();
            $roles = $this->roleRepository->findAll();

            $rows = [];
            foreach ($utilisateurs as $utilisateur) {
                $idUser = $utilisateur->getId();
                $rows[] =
                    [
                        'id' => ($utilisateur->getId() ? $utilisateur->getId() : 'Non défini'),
                        "Nom d'utilisateur" => ($utilisateur->getUsername() ? $utilisateur->getUsername() : ''),
                        'Email' => ($utilisateur->getEmail() ? $utilisateur->getEmail() : ''),
                        'Dernière connexion' => ($utilisateur->getLastLogin() ? $utilisateur->getLastLogin()->format('d/m/Y') : ''),
                        'Rôle' => $this->renderView('utilisateur/role.html.twig', ['utilisateur' => $utilisateur, 'roles' => $roles]),
                        'Actions' => $this->renderView(
                            'utilisateur/datatableUtilisateurRow.html.twig',
                            [
                                'idUser' => $idUser,
                            ]
                        ),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="user_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $user = $this->utilisateurRepository->find($data['user']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($user);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/autocomplete", name="get_user", options={"expose"=true}, methods="GET|POST")
     */
    public function getUserAutoComplete(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $search = $request->query->get('term');

            $fournisseur = $this->utilisateurRepository->getIdAndLibelleBySearch($search);

            return new JsonResponse(['results' => $fournisseur]);
        }
        throw new NotFoundHttpException("404");
    }

}
