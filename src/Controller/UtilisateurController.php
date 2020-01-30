<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Utilisateur;

use App\Repository\EmplacementRepository;
use App\Repository\RoleRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;

use App\Service\PasswordService;
use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


/**
 * @Route("/admin/utilisateur")
 */
class UtilisateurController extends AbstractController
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
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
	 * @var UserPasswordEncoderInterface
	 */
    private $encoder;

	/**
	 * @var PasswordService
	 */
    private $passwordService;


    public function __construct(TypeRepository $typeRepository, PasswordService $passwordService, UserPasswordEncoderInterface $encoder, UtilisateurRepository $utilisateurRepository, RoleRepository $roleRepository, UserService $userService)
    {
        $this->typeRepository = $typeRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->roleRepository = $roleRepository;
        $this->userService = $userService;
        $this->encoder = $encoder;
        $this->passwordService = $passwordService;
    }

    /**
     * @Route("/", name="user_index", methods="GET|POST")
     */
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_UTIL)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($_POST) {
            $utilisateurId = array_keys($_POST); /* Chaque clé représente l'id d'un utilisateur */
            for ($i = 1; $i < count($utilisateurId); ++$i) /* Pour chaque utilisateur on regarde si le rôle a changé */ {
                $utilisateur = $utilisateurRepository->find($utilisateurId[$i]);
                $roles = $utilisateur->getRoles(); /* On regarde le rôle de l'utilisateur */
                if ($roles[0] != $_POST[$utilisateurId[$i]]) /* Si le rôle a changé on le modifie dans la bdd */ {
                    $em = $this->getDoctrine()->getManager();
                    $utilisateur->setRoles([$_POST[$utilisateurId[$i]]]);
                    $em->flush();
                }
            }
        }

        $types = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);
        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurRepository->findAll(),
            'roles' => $this->roleRepository->findAll(),
            'types' => $types
        ]);
    }

	/**
	 * @Route("/creer", name="user_new",  options={"expose"=true}, methods="GET|POST")
	 * @param Request $request
	 * @param EmplacementRepository $emplacementRepository
	 * @return Response
	 */
    public function newUser(Request $request, EmplacementRepository $emplacementRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $password = $data['password'];
            $password2 = $data['password2'];
            // validation du mot de passe
            $result = $this->passwordService->checkPassword($password,$password2);
            if($result['response'] == false){
				return new JsonResponse([
					'success' => false,
					'msg' => $result['message'],
					'action' => 'new'
				]);

			}
            // unicité de l'email
            $emailAlreadyUsed = intval($this->utilisateurRepository->countByEmail($data['email']));

            if ($emailAlreadyUsed) {
				return new JsonResponse([
					'success' => false,
					'msg' => 'Cette adresse email est déjà utilisée.',
					'action' => 'new'
				]);
            }

			// unicité de l'username
			$usernameAlreadyUsed = intval($this->utilisateurRepository->countByUsername($data['username']));

			if ($usernameAlreadyUsed) {
				return new JsonResponse([
					'success' => false,
					'msg' => "Ce nom d'utilisateur est déjà utilisé.",
					'action' => 'new'
				]);
			}

            $utilisateur = new Utilisateur();

            $role = $this->roleRepository->find($data['role']);

            $utilisateur
                ->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setRole($role)
				->setDropzone($data['dropzone'] ? $emplacementRepository->find(intval($data['dropzone'])) : null)
                ->setStatus(true)
                ->setRoles(['USER'])// évite bug -> champ roles ne doit pas être vide
                ->setColumnVisible(Utilisateur::COL_VISIBLE_REF_DEFAULT)
				->setColumnsVisibleForArticle(Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT)
                ->setRecherche(Utilisateur::SEARCH_DEFAULT);

            if ($password !== '') {
				$password = $this->encoder->encodePassword($utilisateur, $data['password']);
				$utilisateur->setPassword($password);
			}

            if (isset($data['type'])) {
                foreach ($data['type'] as $type)
                {
                    $utilisateur->addType($this->typeRepository->find($type));
                }
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($utilisateur);
            $em->flush();

			return new JsonResponse(['success' => true]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="user_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $user = $this->utilisateurRepository->find($data['id']);
            $types = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);

            $typeUser = [];
            foreach ($user->getTypes() as $type)
            {
                $typeUser[] = $type->getId();
            }


            $json = $this->renderView('utilisateur/modalEditUserContent.html.twig', [
                'user' => $user,
                'types' => $types
            ]);

            return new JsonResponse([
            	'userTypes' => $typeUser,
				'html' => $json,
				'dropzone' => $user->getDropzone() ? [
					'id' => $user->getDropzone()->getId(),
					'text' => $user->getDropzone()->getLabel()
            		] : null]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="user_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EmplacementRepository $emplacementRepository
     * @return Response
     */
    public function edit(Request $request, EmplacementRepository $emplacementRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $utilisateur = $this->utilisateurRepository->find($data['id']);

            $result = $this->passwordService->checkPassword($data['password'],$data['password2']);
            if($result['response'] == false){
                return new JsonResponse([
                	'success' => false,
					'msg' => $result['message'],
					'action' => 'edit'
				]);
            }

            // unicité de l'email
            $emailAlreadyUsed = intval($this->utilisateurRepository->countByEmail($data['email']));

            if ($emailAlreadyUsed && $data['email'] != $utilisateur->getEmail()) {
				return new JsonResponse([
					'success' => false,
					'msg' => 'Cette adresse email est déjà utilisée.',
					'action' => 'edit'
				]);
			}

			// unicité de l'username
			$usernameAlreadyUsed = intval($this->utilisateurRepository->countByUsername($data['username']));

			if ($usernameAlreadyUsed && $data['username'] != $utilisateur->getUsername()) {
				return new JsonResponse([
					'success' => false,
					'msg' => "Ce nom d'utilisateur est déjà utilisé.",
					'action' => 'edit'
				]);
			}

            //vérification que l'user connecté ne se désactive pas
            if (($data['id']) == $this->getUser()->getId() &&
                $utilisateur->getStatus() == true &&
                $data['status'] == 'inactive') {
				return new JsonResponse([
						'success' => false,
						'msg' => 'Vous ne pouvez pas désactiver votre propre compte.',
						'action' => 'edit'
				]);
			}
            $utilisateur
                ->setStatus($data['status'] === 'active')
                ->setUsername($data['username'])
                ->setDropzone($data['dropzone'] ? $emplacementRepository->find(intval($data['dropzone'])) : null)
                ->setEmail($data['email']);
            if ($data['password'] !== '') {
                $password = $this->encoder->encodePassword($utilisateur, $data['password']);
                $utilisateur->setPassword($password);
            }
            foreach ($utilisateur->getTypes() as $typeToRemove)
            {
                $utilisateur->removeType($typeToRemove);
            }
            if (isset($data['type'])) {
                foreach ($data['type'] as $type)
                {
                    $utilisateur->addType($this->typeRepository->find($type));
                }
            }
            $em = $this->getDoctrine()->getManager();
            $em->persist($utilisateur);
            $em->flush();

            return new JsonResponse(['success' => true]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-role", name="user_edit_role",  options={"expose"=true}, methods="GET|POST")
     */
    public function editRole(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
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
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_UTIL)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->userService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

	/**
	 * @Route("/verification", name="user_check_delete", options={"expose"=true})
	 */
	public function checkUserCanBeDeleted(Request $request): Response
	{
		if ($request->isXmlHttpRequest() && $userId = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
				return $this->redirectToRoute('access_denied');
			}

			$userIsUsed = $this->userService->isUsedByDemandsOrOrders($userId);

			if (!$userIsUsed) {
				$delete = true;
				$html = $this->renderView('utilisateur/modalDeleteUtilisateurRight.html.twig');
			} else {
				$delete = false;
				$html = $this->renderView('utilisateur/modalDeleteUtilisateurWrong.html.twig');
			}

			return new JsonResponse(['delete' => $delete, 'html' => $html]);
		}
		throw new NotFoundHttpException('404');
	}

    /**
     * @Route("/supprimer", name="user_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $user = $this->utilisateurRepository->find($data['user']);

			// on vérifie que l'utilisateur n'est plus utilisé
			$isUserUsed = $this->userService->isUsedByDemandsOrOrders($user);

			if ($isUserUsed) {
				return new JsonResponse(false);
			}

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->remove($user);
			$entityManager->flush();
			return new JsonResponse();
		}
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/autocomplete", name="get_user", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     */
    public function getUserAutoComplete(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $user = $this->utilisateurRepository->getIdAndLibelleBySearch($search);
            return new JsonResponse(['results' => $user]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/recherches", name="update_user_searches", options={"expose"=true}, methods="GET|POST")
     */
    public function updateSearches(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $this->getUser()->setRecherche($data['recherches']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
        }
        return new JsonResponse();
    }

    /**
     * @Route("/recherchesArticle", name="update_user_searches_for_article", options={"expose"=true}, methods="GET|POST")
     */
    public function updateSearchesArticle(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            /**
             * @var Utilisateur $user
             */
            $user = $this->getUser();
            $user->setRechercheForArticle($data['recherches']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
        }
        return new JsonResponse();
    }

    /**
     * @Route("/taille-page-arrivage", name="update_user_page_length_for_arrivage", options={"expose"=true}, methods="GET|POST")
     */
    public function updateUserPageLengthForArrivage(Request $request)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            /**
             * @var Utilisateur $user
             */
            $user = $this->getUser();
            $user->setPageLengthForArrivage($data);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
        }
        return new JsonResponse();
    }

}
