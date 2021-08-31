<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\Role;
use App\Entity\Type;
use App\Entity\Utilisateur;

use App\Service\CSVExportService;
use App\Service\PasswordService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/admin/utilisateur")
 */
class UserController extends AbstractController
{

    /**
     * @Route("/", name="user_index", methods="GET|POST")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_UTIL})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {

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
     * @Route("/creer", name="user_new",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        UserPasswordHasherInterface $encoder,
                        PasswordService $passwordService,
                        UserService $userService,
                        EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $roleRepository = $entityManager->getRepository(Role::class);

            $password = $data['password'];
            $password2 = $data['password2'];
            // validation du mot de passe
            $result = $passwordService->checkPassword($password,$password2);
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

            if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json([
                    "success" => false,
                    "msg" => "L'adresse email principale \"{$data['email']}\" n'est pas valide"
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

            $secondaryEmails = json_decode($data['secondaryEmails']);
            foreach($secondaryEmails as $email) {
                if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->json([
                        "success" => false,
                        "msg" => "L'adresse email \"{$email}\" n'est pas valide"
                    ]);
                }
            }

            $utilisateur = new Utilisateur();
            $uniqueMobileKey = $userService->createUniqueMobileLoginKey($entityManager);
            $role = $roleRepository->find($data['role']);
            $utilisateur
                ->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setSecondaryEmails($secondaryEmails)
                ->setPhone($data['phoneNumber'])
                ->setRole($role)
				->setDropzone($data['dropzone'] ? $emplacementRepository->find(intval($data['dropzone'])) : null)
                ->setStatus(true)
                ->setAddress($data['address'])
                ->setMobileLoginKey($uniqueMobileKey);

            if ($password !== '') {
				$password = $encoder->hashPassword($utilisateur, $data['password']);
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

            return new JsonResponse([
                'success' => true,
                'msg' => 'L\'utilisateur <strong>' . $data['username'] . '</strong> a bien été créé.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="user_api_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $user = $utilisateurRepository->find($data['id']);
            $deliveryTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
            $dispatchTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]);
            $handlingTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
            $roles = $entityManager->getRepository(Role::class)->findAll();

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
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="user_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         UserPasswordHasherInterface $encoder,
                         PasswordService $passwordService,
                         UserService $userService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            $typeRepository = $entityManager->getRepository(Type::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $roleRepository = $entityManager->getRepository(Role::class);

            $utilisateur = $utilisateurRepository->find($data['user']);
            $role = $roleRepository->find($data['role']);

            $result = $passwordService->checkPassword($data['password'],$data['password2']);
            if ($result['response'] == false){
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

            if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->json([
                    "success" => false,
                    "msg" => "L'adresse email principale \"{$data['email']}\" n'est pas valide"
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
            if ($utilisateur->getId() === $loggedUser->getId() && $data['status'] == 0) {
				return new JsonResponse([
						'success' => false,
						'msg' => 'Vous ne pouvez pas désactiver votre propre compte.',
						'action' => 'edit'
				]);
			}

            $secondaryEmails = json_decode($data['secondaryEmails']);
            foreach($secondaryEmails as $email) {
                if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->json([
                        "success" => false,
                        "msg" => "L'adresse email \"{$email}\" n'est pas valide"
                    ]);
                }
            }

            $utilisateur
                ->setSecondaryEmails($secondaryEmails)
                ->setRole($role)
                ->setStatus($data['status'])
                ->setUsername($data['username'])
                ->setAddress($data['address'])
                ->setDropzone($data['dropzone'] ? $emplacementRepository->find(intval($data['dropzone'])) : null)
                ->setEmail($data['email'])
                ->setPhone($data['phoneNumber'] ?? '');

            if ($data['password'] !== '') {
                $password = $encoder->hashPassword($utilisateur, $data['password']);
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
                        $utilisateur
                            ->setMobileLoginKey($mobileLoginKey)
                            ->setApiKey(null);
                    }
                }
            }

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            $dataResponse = ['success' => true];

            if ($utilisateur->getId() != $loggedUser->getId()) {
                $dataResponse['msg'] = 'L\'utilisateur <strong>' . $utilisateur->getUsername() . '</strong> a bien été modifié.';
            } else {
                if ($userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                    $dataResponse['msg'] = 'Vous avez bien modifié votre compte utilisateur.';
                } else {
                    $dataResponse['msg'] = 'Vous avez bien modifié votre rôle utilisateur.';
                    $dataResponse['redirect'] = $this->generateUrl('access_denied');
                }
            }
            return $this->json($dataResponse);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-role", name="user_edit_role",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editRole(Request $request,
                             EntityManagerInterface $entityManager)
    {
        if ($data = json_decode($request->getContent(), true)) {
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
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="user_api",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_UTIL}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        UserService $userService): Response
    {
        $data = $userService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/verification", name="user_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
	public function checkUserCanBeDeleted(Request $request,
                                          UserService $userService): Response
	{
		if ($userId = json_decode($request->getContent(), true)) {
			$userIsUsed = $userService->isUsedByDemandsOrOrders($userId);

			if (!$userIsUsed) {
				$delete = true;
				$html = $this->renderView('utilisateur/modalDeleteUtilisateurRight.html.twig');
			} else {
				$delete = false;
				$html = $this->renderView('utilisateur/modalDeleteUtilisateurWrong.html.twig');
			}

			return new JsonResponse(['delete' => $delete, 'html' => $html]);
		}
		throw new BadRequestHttpException();
	}

    /**
     * @Route("/supprimer", name="user_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $user = $utilisateurRepository->find($data['user']);
            $username = $user->getUsername();

			// on vérifie que l'utilisateur n'est plus utilisé
			$isUserUsed = $userService->isUsedByDemandsOrOrders($user);

			if ($isUserUsed) {
				return new JsonResponse(false);
			}

			$entityManager = $this->getDoctrine()->getManager();
			$entityManager->remove($user);
			$entityManager->flush();
			return new JsonResponse([
			    'success' => true,
                'msg' => 'L\'utilisateur <strong>' . $username . '</strong> a bien été supprimé.'
            ]);
		}
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocomplete", name="get_user", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getUserAutoComplete(Request $request,
                                        EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('term');

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $results = $utilisateurRepository->getIdAndLibelleBySearch($search);
        return new JsonResponse(['results' => $results]);
    }

    /**
     * @Route("/recherches", name="update_user_searches", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateSearches(Request $request,
                                   EntityManagerInterface $entityManager) {
        $data = $request->get("searches");
        if ($data && is_array($data)) {
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $currentUser->setRecherche($data);

            $entityManager->flush();
            $res = [
                "success" => true,
                "msg" => "Recherche rapide sauvegardée avec succès."
            ];
        }
        else {
            $res = [
                "success" => false,
                "msg" => "Vous devez sélectionner au moins un champ."
            ];
        }
        return $this->json($res);
    }

    /**
     * @Route("/recherchesArticle", name="update_user_searches_for_article", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateSearchesArticle(Request $request) {
        if ($data = $request->request->get("searches")) {
            $this->getUser()->setRechercheForArticle($data);
            $this->getDoctrine()->getManager()->flush();

            return $this->json([
                "success" => true
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/taille-page-arrivage", name="update_user_page_length_for_arrivage", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateUserPageLengthForArrivage(Request $request)
    {
        if ($data = json_decode($request->getContent(), true)) {
            /** @var Utilisateur $user */
            $user = $this->getUser();
            $user->setPageLengthForArrivage($data);
            $em = $this->getDoctrine()->getManager();
            $em->flush();
        }
        return new JsonResponse();
    }

    /**
     * @Route("/export", name="export_csv_user", methods="GET")
     */
    public function exportCSV(CSVExportService $CSVExportService,
                              UserService $userService,
                              EntityManagerInterface $entityManager): StreamedResponse {
        $csvHeader = [
            'Rôle',
            "Nom d'utilisateur",
            'Email',
            'Email 2',
            'Email 3',
            'Numéro de téléphone',
            'Adresse',
            'Dernière connexion',
            'Clé de connexion mobile',
            'Types de livraison',
            "Types de d'acheminement",
            'Types de service',
            'Dropzone',
            'Groupe de visibilité',
            'Statut'
        ];

        return $CSVExportService->streamResponse(
            function ($output) use ($CSVExportService, $userService, $entityManager) {
                $userRepository = $entityManager->getRepository(Utilisateur::class);
                $users = $userRepository->iterateAll();

                foreach ($users as $user) {
                    $userService->putCSVLine($CSVExportService, $output, $user);
                }
            }, 'export_utilisateurs.csv',
            $csvHeader
        );
    }

}
