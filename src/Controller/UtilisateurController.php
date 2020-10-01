<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Role;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\PasswordService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\RedirectResponse;
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


    public function __construct(PasswordService $passwordService,
                                UserPasswordEncoderInterface $encoder,
                                UserService $userService)
    {
        $this->userService = $userService;
        $this->encoder = $encoder;
        $this->passwordService = $passwordService;
    }

    /**
     * @Route("/", name="user_index", methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_UTIL)) {
            return $this->redirectToRoute('access_denied');
        }
        $typeRepository = $entityManager->getRepository(Type::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $roleRepository = $entityManager->getRepository(Role::class);

        if ($_POST) {
            $utilisateurId = array_keys($_POST); /* Chaque clé représente l'id d'un utilisateur */
            for ($i = 1; $i < count($utilisateurId); ++$i) /* Pour chaque utilisateur on regarde si le rôle a changé */ {
                $utilisateur = $utilisateurRepository->find($utilisateurId[$i]);
                $roles = $utilisateur->getRoles(); /* On regarde le rôle de l'utilisateur */
                if ($roles[0] != $_POST[$utilisateurId[$i]]) /* Si le rôle a changé on le modifie dans la bdd */ {
                    $utilisateur->setRoles([$_POST[$utilisateurId[$i]]]);
                    $entityManager->flush();
                }
            }
        }

        return $this->render('utilisateur/index.html.twig', [
            'roles' => $roleRepository->findAll(),
            'deliveryTypes' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
            'dispatchTypes' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]),
            'handlingTypes' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING])
        ]);
    }

    /**
     * @Route("/creer", name="user_new",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $roleRepository = $entityManager->getRepository(Role::class);

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
            $emailAlreadyUsed = $utilisateurRepository->count(['email' => $data['email']]);

            if ($emailAlreadyUsed > 0) {
				return new JsonResponse([
					'success' => false,
					'msg' => 'Cette adresse email est déjà utilisée.',
					'action' => 'new'
				]);
            }

			// unicité de l'username
            $usernameAlreadyUsed = $utilisateurRepository->count(['username' => $data['username']]);
			if ($usernameAlreadyUsed > 0) {
				return new JsonResponse([
					'success' => false,
					'msg' => "Ce nom d'utilisateur est déjà utilisé.",
					'action' => 'new'
				]);
			}

            $utilisateur = new Utilisateur();
            $uniqueMobileKey = $this->userService->createUniqueMobileLoginKey($entityManager);
            $role = $roleRepository->find($data['role']);
            $utilisateur
                ->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setSecondaryEmails(json_decode($data['secondaryEmails']))
                ->setPhone($data['phoneNumber'])
                ->setRole($role)
				->setDropzone($data['dropzone'] ? $emplacementRepository->find(intval($data['dropzone'])) : null)
                ->setStatus(true)
                ->setRoles(['USER'])// évite bug -> champ roles ne doit pas être vide
                ->setAddress($data['address'])
                ->setColumnVisible(Utilisateur::COL_VISIBLE_REF_DEFAULT)
				->setColumnsVisibleForArticle(Utilisateur::COL_VISIBLE_ARTICLES_DEFAULT)
                ->setColumnsVisibleForArrivage(Utilisateur::COL_VISIBLE_ARR_DEFAULT)
                ->setColumnsVisibleForDispatch(Utilisateur::COL_VISIBLE_DISPATCH_DEFAULT)
                ->setColumnsVisibleForLitige(Utilisateur::COL_VISIBLE_LIT_DEFAULT)
				->setRechercheForArticle(Utilisateur::SEARCH_DEFAULT)
                ->setRecherche(Utilisateur::SEARCH_DEFAULT)
                ->setMobileLoginKey($uniqueMobileKey);

            if ($password !== '') {
				$password = $this->encoder->encodePassword($utilisateur, $data['password']);
				$utilisateur->setPassword($password);
			}

            if (isset($data['deliveryTypes'])) {
                foreach ($data['deliveryTypes'] as $type) {
                    $utilisateur->addDeliveryType($typeRepository->find($type));
                }
            }

            if (isset($data['dispatchTypes'])) {
                foreach ($data['dispatchTypes'] as $type) {
                    $utilisateur->addDispatchType($typeRepository->find($type));
                }
            }

            if (isset($data['handlingTypes'])) {
                foreach ($data['handlingTypes'] as $type) {
                    $utilisateur->addHandlingType($typeRepository->find($type));
                }
            }

            $entityManager->persist($utilisateur);
            $entityManager->flush();

			return new JsonResponse(['success' => true]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="user_api_edit", options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $user = $utilisateurRepository->find($data['id']);
            $deliveryTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
            $dispatchTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
            $handlingTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
            $roles = $entityManager->getRepository(Role::class)->findAllExceptNoAccess();

            return new JsonResponse([
            	'userDeliveryTypes' => $user->getDeliveryTypeIds(),
            	'userDispatchTypes' => $user->getDispatchTypeIds(),
            	'userHandlingTypes' => $user->getHandlingTypeIds(),
				'html' => $this->renderView('utilisateur/modalEditUserContent.html.twig', [
                    'user' => $user,
                    'roles' => $roles,
                    'deliveryTypes' => $deliveryTypes,
                    'dispatchTypes' => $dispatchTypes,
                    'handlingTypes' => $handlingTypes
                ]),
				'dropzone' => $user->getDropzone()
                    ? [
                        'id' => $user->getDropzone()->getId(),
                        'text' => $user->getDropzone()->getLabel()
                    ]
                    : null]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="user_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $roleRepository = $entityManager->getRepository(Role::class);

            $utilisateur = $utilisateurRepository->find($data['id']);
            $role = $roleRepository->find($data['role']);

            $result = $this->passwordService->checkPassword($data['password'],$data['password2']);
            if($result['response'] == false){
                return new JsonResponse([
                	'success' => false,
					'msg' => $result['message'],
					'action' => 'edit'
				]);
            }

            // unicité de l'email
            $emailAlreadyUsed = $utilisateurRepository->count(['email' => $data['email']]);

            if ($emailAlreadyUsed > 0  && $data['email'] != $utilisateur->getEmail()) {
				return new JsonResponse([
					'success' => false,
					'msg' => 'Cette adresse email est déjà utilisée.',
					'action' => 'edit'
				]);
			}

			// unicité de l'username
            $usernameAlreadyUsed = $utilisateurRepository->count(['username' => $data['username']]);

			if ($usernameAlreadyUsed > 0  && $data['username'] != $utilisateur->getUsername() ) {
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
                ->setSecondaryEmails(json_decode($data['secondaryEmails']))
                ->setRole($role)
                ->setStatus($data['status'])
                ->setUsername($data['username'])
                ->setAddress($data['address'])
                ->setDropzone($data['dropzone'] ? $emplacementRepository->find(intval($data['dropzone'])) : null)
                ->setEmail($data['email'])
                ->setPhone($data['phoneNumber'] ?? '');

            if ($data['password'] !== '') {
                $password = $this->encoder->encodePassword($utilisateur, $data['password']);
                $utilisateur->setPassword($password);
            }
            foreach ($utilisateur->getDeliveryTypes() as $typeToRemove) {
                $utilisateur->removeDeliveryType($typeToRemove);
            }
            if (isset($data['deliveryTypes'])) {
                foreach ($data['deliveryTypes'] as $type) {
                    $utilisateur->addDeliveryType($typeRepository->find($type));
                }
            }
            foreach ($utilisateur->getDispatchTypes() as $typeToRemove) {
                $utilisateur->removeDispatchType($typeToRemove);
            }
            if (isset($data['dispatchTypes'])) {
                foreach ($data['dispatchTypes'] as $type) {
                    $utilisateur->addDispatchType($typeRepository->find($type));
                }
            }
            foreach ($utilisateur->getHandlingTypes() as $typeToRemove) {
                $utilisateur->removeHandlingType($typeToRemove);
            }
            if (isset($data['handlingTypes'])) {
                foreach ($data['handlingTypes'] as $type) {
                    $utilisateur->addHandlingType($typeRepository->find($type));
                }
            }

            if (!empty($data['mobileLoginKey'])
                && $data['mobileLoginKey'] !== $utilisateur->getMobileLoginKey()) {

                $usersWithKey = $utilisateurRepository->findBy([
                    'mobileLoginKey' => $data['mobileLoginKey']
                ]);
                if (!empty($usersWithKey)
                    && (
                        count($usersWithKey) > 1
                        || $usersWithKey[0]->getId() !== $utilisateur->getId()
                    )) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Cette clé de connexion est déjà utilisée.',
                        'action' => 'edit'
                    ]);
                }
                else {
                    $mobileLoginKey = $data['mobileLoginKey'];
                    if (empty($mobileLoginKey)
                        || strlen($mobileLoginKey) < UserService::MIN_MOBILE_KEY_LENGTH
                        || strlen($mobileLoginKey) > UserService::MAX_MOBILE_KEY_LENGTH) {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => 'La longueur de la clé de connexion doit être comprise entre ' . UserService::MIN_MOBILE_KEY_LENGTH . ' et ' . UserService::MAX_MOBILE_KEY_LENGTH . ' caractères.',
                            'action' => 'edit'
                        ]);
                    }
                    else {
                        $utilisateur->setMobileLoginKey($mobileLoginKey);
                    }
                }
            }

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            return new JsonResponse(['success' => true]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier-role", name="user_edit_role",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse|RedirectResponse
     */
    public function editRole(Request $request,
                             EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $roleRepository = $entityManager->getRepository(Role::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $role = $roleRepository->find((int)$data['role']);
            $user = $utilisateurRepository->find($data['userId']);

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
     * @param Request $request
     * @return Response
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
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $user = $utilisateurRepository->find($data['user']);

			// on vérifie que l'utilisateur n'est plus utilisé
			$isUserUsed = $this->userService->isUsedByDemandsOrOrders($user);

			if ($isUserUsed) {
				return new JsonResponse(false);
			}

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->remove($user);
			$entityManager->flush();
			return new JsonResponse(true);
		}
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/autocomplete", name="get_user", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getUserAutoComplete(Request $request,
                                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $results = $utilisateurRepository->getIdAndLibelleBySearch($search);
            return new JsonResponse(['results' => $results]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/recherches", name="update_user_searches", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return JsonResponse
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
     * @param Request $request
     * @return JsonResponse
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
     * @param Request $request
     * @return JsonResponse
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
