<?php

namespace App\Controller\Settings;

use App\Entity\Emplacement;
use App\Entity\FiltreRef;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\Language;
use App\Entity\LocationGroup;
use App\Entity\Role;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Exceptions\FormException;
use App\Service\CacheService;
use App\Service\CSVExportService;
use App\Service\LanguageService;
use App\Service\PasswordService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;
use App\Annotation\HasPermission;
use App\Entity\Menu;
use App\Entity\Action;

/**
 * @Route("/parametrage/users")
 */
class UserController extends AbstractController {

    /**
     * @Route("/api-modifier", name="user_api_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_USERS}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $languageRepository = $entityManager->getRepository(Language::class);

            $user = $utilisateurRepository->find($data['id']);

            return new JsonResponse([
                'userDeliveryTypes' => $user->getDeliveryTypeIds(),
                'userDispatchTypes' => $user->getDispatchTypeIds(),
                'userHandlingTypes' => $user->getHandlingTypeIds(),
                'html' => $this->renderView('settings/utilisateurs/utilisateurs/form.html.twig', [
                    'user' => $user,
                    "languages" => Stream::from($languageRepository->findBy(["hidden" => false]))
                        ->map(fn(Language $language) => [
                            "value" => $language->getId(),
                            "label" => $language->getLabel(),
                            "icon" => $language->getFlag(),
                            "selected" => $user->getLanguage() && $user->getLanguage() == $language
                        ])
                        ->toArray(),
                    "dateFormats" => Stream::from(Language::DATE_FORMATS)
                        ->map(fn($format, $key) => [
                            "label" => $key,
                            "value" => $format,
                            "selected" => $key == $user->getDateFormat()
                        ])
                        ->toArray(),
                ])
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="user_api",  options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_USERS}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        UserService $userService): Response
    {
        $data = $userService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/verification", name="user_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_USERS}, mode=HasPermission::IN_JSON)
     */
    public function checkUserCanBeDeleted(Request $request,
                                          UserService $userService,
                                          EntityManagerInterface $entityManager): Response
    {
        if ($userId = json_decode($request->getContent(), true)) {
            $user = $entityManager->find(Utilisateur::class, $userId);
            $userOwnership = $user
                ? Stream::from($userService->getUserOwnership($entityManager, $user))
                    ->filter()
                    ->toArray()
                : [];

            if ($user && empty($userOwnership)) {
                $delete = true;
                $html = "
                    <p>Voulez-vous réellement supprimer cet utilisateur ?</p>
                    <div class='error-msg mt-2'></div>
                ";
            } else {
                $delete = false;
                $entities = Stream::from($userOwnership)
                    ->takeKeys()
                    ->join(", ");
                $html = "
                    <p class='error-msg'>
                        Cet utilisateur est lié à un ou plusieurs $entities.<br>
                        Vous ne pouvez pas le supprimer.
                    </p>
                ";
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="user_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_USERS}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $user = $utilisateurRepository->find($data['user']);

            if ($user) {
                $userOwnership = Stream::from($userService->getUserOwnership($entityManager, $user))
                    ->filter()
                    ->toArray();

                if (!empty($userOwnership)) {
                    return new JsonResponse(false);
                }

                $username = $user->getUsername();

                $entityManager->remove($user);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'msg' => 'L\'utilisateur <strong>' . $username . '</strong> a bien été supprimé.'
                ]);
            }
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
                         EntityManagerInterface $entityManager,
                         CacheService $cacheService): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            $typeRepository = $entityManager->getRepository(Type::class);
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $roleRepository = $entityManager->getRepository(Role::class);
            $languageRepository = $entityManager->getRepository(Language::class);

            $user = $utilisateurRepository->find($data['user']);
            $role = $roleRepository->find($data['role']);
            $language = $languageRepository->find($data['language']);

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

            if ($emailAlreadyUsed > 0  && $data['email'] != $user->getEmail()) {
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

            if ($usernameAlreadyUsed > 0  && $data['username'] != $user->getUsername() ) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Ce nom d'utilisateur est déjà utilisé.",
                    'action' => 'edit'
                ]);
            }

            //vérification que l'user connecté ne se désactive pas
            if ($user->getId() === $loggedUser->getId() && $data['status'] == 0) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Vous ne pouvez pas désactiver votre propre compte.',
                    'action' => 'edit'
                ]);
            }

            $secondaryEmails = isset($data['secondaryEmails'])
                ? json_decode($data['secondaryEmails'])
                : [];
            foreach($secondaryEmails as $email) {
                if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->json([
                        "success" => false,
                        "msg" => "L'adresse email \"{$email}\" n'est pas valide"
                    ]);
                }
            }

            $dropzone = explode(":", $data['dropzone']);
            if($dropzone[0] === 'location') {
                $dropzone = $entityManager->find(Emplacement::class, $dropzone[1]);
            } elseif($dropzone[0] === 'locationGroup') {
                $dropzone = $entityManager->find(LocationGroup::class, $dropzone[1]);
            }

            $user
                ->setSecondaryEmails($secondaryEmails)
                ->setRole($role)
                ->setStatus($data['status'])
                ->setUsername($data['username'])
                ->setAddress($data['address'])
                ->setDropzone($dropzone)
                ->setEmail($data['email'])
                ->setPhone($data['phoneNumber'] ?? '')
                ->setDeliverer($data['deliverer'] ?? false)
                ->setLanguage($language)
                ->setDateFormat($data['dateFormat']);

            $visibilityGroupsIds = is_string($data["visibility-group"]) ? explode(',', $data['visibility-group']) : $data["visibility-group"];
            if ($visibilityGroupsIds) {
                $visibilityGroups = $visibilityGroupRepository->findBy(["id" => $visibilityGroupsIds]);
            }

            $user->setVisibilityGroups($visibilityGroups ?? []);

            if(!$user->getVisibilityGroups()->isEmpty()) {
                $filters = $entityManager->getRepository(FiltreRef::class)->findBy(["champFixe" => FiltreRef::FIXED_FIELD_VISIBILITY_GROUP]);
                foreach($filters as $filter) {
                    $entityManager->remove($filter);
                }
            }

            $plainSignatoryPassword = $data['signatoryPassword'] ?? null;
            if (!empty($plainSignatoryPassword)) {
                if (strlen($plainSignatoryPassword) < 4) {
                    throw new FormException("Le code signataire doit contenir au moins 4 caractères");
                }
                $signatoryPassword = $encoder->hashPassword($user, $plainSignatoryPassword);
                $user->setSignatoryPassword($signatoryPassword);
            }


            if ($data['password'] !== '') {
                $password = $encoder->hashPassword($user, $data['password']);
                $user->setPassword($password);
            }
            foreach ($user->getDeliveryTypes() as $typeToRemove) {
                $user->removeDeliveryType($typeToRemove);
            }
            if (isset($data['deliveryTypes'])) {
                foreach ($data['deliveryTypes'] as $type) {
                    $user->addDeliveryType($typeRepository->find($type));
                }
            }
            foreach ($user->getDispatchTypes() as $typeToRemove) {
                $user->removeDispatchType($typeToRemove);
            }
            if (isset($data['dispatchTypes'])) {
                foreach ($data['dispatchTypes'] as $type) {
                    $user->addDispatchType($typeRepository->find($type));
                }
            }
            foreach ($user->getHandlingTypes() as $typeToRemove) {
                $user->removeHandlingType($typeToRemove);
            }
            if (isset($data['handlingTypes'])) {
                foreach ($data['handlingTypes'] as $type) {
                    $user->addHandlingType($typeRepository->find($type));
                }
            }

            if (!empty($data['mobileLoginKey'])
                && $data['mobileLoginKey'] !== $user->getMobileLoginKey()) {

                $usersWithKey = $utilisateurRepository->findBy([
                    'mobileLoginKey' => $data['mobileLoginKey']
                ]);
                if (!empty($usersWithKey)
                    && (
                        count($usersWithKey) > 1
                        || $usersWithKey[0]->getId() !== $user->getId()
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
                        $user
                            ->setMobileLoginKey($mobileLoginKey)
                            ->setApiKey(null);
                    }
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();
            $cacheService->delete(CacheService::LANGUAGES, 'languagesSelector'.$user->getId());

            $dataResponse = ['success' => true];

            if ($user->getId() != $loggedUser->getId()) {
                $dataResponse['msg'] = 'L\'utilisateur <strong>' . $user->getUsername() . '</strong> a bien été modifié.';
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
            'Groupe(s) de visibilité',
            'Livreur',
            'Statut',
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

    /**
     * @Route("/creer", name="user_new",  options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_DISPLAY_USERS}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        UserPasswordHasherInterface $encoder,
                        PasswordService $passwordService,
                        UserService $userService,
                        LanguageService $languageService,
                        EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $roleRepository = $entityManager->getRepository(Role::class);
            $languageRepository = $entityManager->getRepository(Language::class);

            $password = $data['password'];
            $password2 = $data['password2'];
            // validation du mot de passe
            $result = $passwordService->checkPassword($password, $password2);
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

            $secondaryEmails = isset($data['secondaryEmails'])
                ? json_decode($data['secondaryEmails'])
                : [];
            foreach($secondaryEmails as $email) {
                if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->json([
                        "success" => false,
                        "msg" => "L'adresse email \"{$email}\" n'est pas valide"
                    ]);
                }
            }

            $dropzone = explode(":", $data['dropzone']);
            if($dropzone[0] === 'location') {
                $dropzone = $entityManager->find(Emplacement::class, $dropzone[1]);
            } elseif($dropzone[0] === 'locationGroup') {
                $dropzone = $entityManager->find(LocationGroup::class, $dropzone[1]);
            }

            $utilisateur = new Utilisateur();
            $uniqueMobileKey = $userService->createUniqueMobileLoginKey($entityManager);
            $language = !empty($data['language'])
                ? $languageRepository->find($data['language'])
                : $languageService->getNewUserLanguage($entityManager);
            $role = $roleRepository->find($data['role']);

            $plainSignatoryPassword = $data['signatoryPassword'] ?? null;

            if (!empty($plainSignatoryPassword) && strlen($plainSignatoryPassword) < 4) {
                throw new FormException("Le code signataire doit contenir au moins 4 caractères");
            }

            $signatoryPassword = $plainSignatoryPassword ? $encoder->hashPassword($utilisateur, $plainSignatoryPassword) : null;

            $utilisateur
                ->setUsername($data['username'])
                ->setEmail($data['email'])
                ->setSecondaryEmails($secondaryEmails)
                ->setPhone($data['phoneNumber'])
                ->setRole($role)
                ->setStatus(true)
                ->setDropzone($dropzone)
                ->setAddress($data['address'])
                ->setLanguage($language)
                ->setDateFormat($data['dateFormat'] ?? Utilisateur::DEFAULT_DATE_FORMAT)
                ->setMobileLoginKey($uniqueMobileKey)
                ->setDeliverer($data['deliverer'] ?? false)
                ->setSignatoryPassword($signatoryPassword);

            if ($password !== '') {
                $password = $encoder->hashPassword($utilisateur, $data['password']);
                $utilisateur->setPassword($password);
            }

            $visibilityGroupsIds = is_string($data["visibility-group"]) ? explode(',', $data['visibility-group']) : $data["visibility-group"];
            if ($visibilityGroupsIds) {
                $visibilityGroups = $visibilityGroupRepository->findBy(["id" => $visibilityGroupsIds]);
            }

            $utilisateur->setVisibilityGroups($visibilityGroups ?? []);

            if (!empty($data['deliveryTypes'])) {
                $types = $typeRepository->findBy(["id" => $data['deliveryTypes']]);
                foreach ($types as $type) {
                    $utilisateur->addDeliveryType($type);
                }
            }

            if (!empty($data['dispatchTypes'])) {
                $types = $typeRepository->findBy(["id" => $data['dispatchTypes']]);
                foreach ($types as $type) {
                    $utilisateur->addDispatchType($type);
                }
            }

            if (!empty($data['handlingTypes'])) {
                $types = $typeRepository->findBy(["id" => $data['handlingTypes']]);
                foreach ($types as $type) {
                    $utilisateur->addHandlingType($type);
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
    public function updateSearchesArticle(Request $request, EntityManagerInterface $entityManager) {
        $searches = $request->request->all("searches");

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $user->setRechercheForArticle($searches ?: []);

        $entityManager->flush();

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/taille-page-arrivage", name="update_user_page_length_for_arrivage", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updateUserPageLengthForArrivage(Request $request, EntityManagerInterface $manager)
    {
        if ($data = json_decode($request->getContent(), true)) {
            /** @var Utilisateur $user */
            $user = $this->getUser();
            $user->setPageLengthForArrivage($data);
            $manager->flush();
        }
        return new JsonResponse();
    }

    /**
     * @Route("/langues/api", name="header_language_dateFormat_api" , methods={"POST"}, options={"expose"=true})
     */
    public function userLanguageApi(EntityManagerInterface $manager,
                                       Request $request, CacheService $cacheService ): Response {
        $data = $request->request;
        $user = $this->getUser();

        $languageRepository = $manager->getRepository(Language::class);
        $newLanguage = $languageRepository->find($data->get('language'));

        $user
            ->setDateFormat($data->get('dateFormat'))
            ->setLanguage($newLanguage);

        $manager->flush();
        $cacheService->delete(CacheService::LANGUAGES, 'languagesSelector'.$user->getId());

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/set-columns-order", name="set_columns_order", methods="POST", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function setColumnsOrder(Request $request, EntityManagerInterface $manager): JsonResponse {
        $data = $request->request->all();

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $columnsOrder = $loggedUser->getColumnsOrder();
        $columnsOrder[$data['page']] = $data['order'];

        $loggedUser->setColumnsOrder($columnsOrder);

        $manager->flush();

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Route("/get-columns-order", name="get_columns_order", methods="GET", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function getColumnsOrder(Request $request): JsonResponse {
        $page = $request->query->get('page');

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $columnsOrder = $loggedUser->getColumnsOrder();

        return $this->json([
            'success' => true,
            'order' => $columnsOrder[$page] ?? []
        ]);
    }
}
