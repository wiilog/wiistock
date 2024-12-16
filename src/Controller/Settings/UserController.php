<?php

namespace App\Controller\Settings;

use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreRef;
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
use App\Service\TranslationService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;
use App\Annotation\HasPermission;
use App\Entity\Menu;
use App\Entity\Action;

#[Route('/parametrage/users')]
class UserController extends AbstractController {

    #[Route('/api-modifier', name: 'user_api_edit', options: ['expose' => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_USERS], mode: HasPermission::IN_JSON)]
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $languageRepository = $entityManager->getRepository(Language::class);
            $fixedFieldRepository = $entityManager->getRepository(FixedFieldByType::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            $user = $utilisateurRepository->find($data['id']);
            $SECONDARYMAILNUMBER = 2;

            return new JsonResponse([
                "html" => $this->renderView('settings/utilisateurs/utilisateurs/form.html.twig', [
                    "user" => $user,
                    "secondaryMailNumber" => $SECONDARYMAILNUMBER,
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
                    "dispatchBusinessUnits" => $fixedFieldRepository->getElements(FixedFieldStandard::ENTITY_CODE_DISPATCH, FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT),
                    "deliveryTypes" => Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]))
                        ->map(fn(Type $type) => [
                            "label" => $type->getLabel(),
                            "value" => $type->getId(),
                            "selected" => in_array($type->getId(), $user->getDeliveryTypeIds()),
                        ])
                        ->toArray(),
                    "dispatchTypes" => Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]))
                        ->map(fn(Type $type) => [
                            "label" => $type->getLabel(),
                            "value" => $type->getId(),
                            "selected" => in_array($type->getId(), $user->getDispatchTypeIds()),
                        ])
                        ->toArray(),
                    "handlingTypes" => Stream::from($typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]))
                        ->map(fn(Type $type) => [
                            "label" => $type->getLabel(),
                            "value" => $type->getId(),
                            "selected" => in_array($type->getId(), $user->getHandlingTypeIds()),
                        ])
                        ->toArray(),
                ])
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route('/api', name: 'user_api', options: ['expose' => true], methods: 'POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_USERS], mode: HasPermission::IN_JSON)]
    public function api(Request $request,
                        UserService $userService): Response
    {
        $data = $userService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    #[Route('/verification', name: 'user_check_delete', options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_USERS], mode: HasPermission::IN_JSON)]
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

    #[Route('/supprimer', name: 'user_delete', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_USERS], mode: HasPermission::IN_JSON)]
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

    #[Route("/modifier", name: "user_edit", options: ["expose" => true], methods: [self::POST], condition:  self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::PARAM, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request $request,
                         UserPasswordHasherInterface $encoder,
                         PasswordService $passwordService,
                         UserService $userService,
                         EntityManagerInterface $entityManager,
                         CacheService $cacheService): Response {
        $data = $request->request;

        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();

        $typeRepository = $entityManager->getRepository(Type::class);
        $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $roleRepository = $entityManager->getRepository(Role::class);
        $languageRepository = $entityManager->getRepository(Language::class);

        $user = $utilisateurRepository->find($data->get('user'));
        $role = $roleRepository->find($data->get('role'));
        $language = $languageRepository->find($data->get('language'));

        $result = $passwordService->checkPassword($data->get('password') ?? '',$data->get('password2') ?? '');
        if (!$result['response']){
            throw new FormException($result['message']);
        }

        // unicité de l'email
        $emailAlreadyUsed = $utilisateurRepository->count(['email' => $data->get('email')]);

        if ($emailAlreadyUsed > 0  && $data->get('email') != $user->getEmail()) {
            throw new FormException("Cette adresse email est déjà utilisée.");
        }

        if(!filter_var($data->get('email'), FILTER_VALIDATE_EMAIL)) {
            throw new FormException("L'adresse email principale {$data->get('email')} n'est pas valide");
        }

        // unicité de l'username
        $usernameAlreadyUsed = $utilisateurRepository->count(['username' => $data->get('username')]);

        if ($usernameAlreadyUsed > 0  && $data->get('username') != $user->getUsername() ) {
            throw new FormException("Ce nom d'utilisateur est déjà utilisé.");
        }

        //vérification que l'user connecté ne se désactive pas
        if ($user->getId() === $loggedUser->getId() && $data->get('status') == 0) {
            throw new FormException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        // vérification si logged user a le droit de modifier un utilisateur wiilog
        if (!$loggedUser->isWiilogUser() && $user->isWiilogUser()) {
            throw new FormException("Vous ne pouvez pas modifier un membre de l'equipe Wiilog.");
        }

        $secondaryEmails = $data->has('secondaryEmails')
            ? Stream::explode(',', $data->get('secondaryEmails'))->filter()
            : [];


        if($secondaryEmails){
            foreach($secondaryEmails as $index => $email) {
                if($email == ""){
                    $emptyIndex[] = $index;
                }
                else if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)){
                    throw new FormException("L'adresse email $email n'est pas valide");
                }
            }
        }

        $dropzone = explode(":", $data->get('dropzone'));
        if($dropzone[0] === 'location') {
            $dropzone = $entityManager->find(Emplacement::class, $dropzone[1]);
        } elseif($dropzone[0] === 'locationGroup') {
            $dropzone = $entityManager->find(LocationGroup::class, $dropzone[1]);
        }

        $user
            ->setSecondaryEmails($secondaryEmails)
            ->setRole($role)
            ->setStatus($data->get('status'))
            ->setUsername($data->get('username'))
            ->setAddress($data->get('address'))
            ->setDropzone($dropzone)
            ->setEmail($data->get('email'))
            ->setPhone($data->get('phoneNumber') ?? '')
            ->setDeliverer($data->get('deliverer') ?? false)
            ->setLanguage($language)
            ->setDateFormat($data->get('dateFormat'))
            ->setDispatchBusinessUnit($data->get('dispatchBusinessUnit') ?? null)
            ->setAllowedToBeRemembered($data->getBoolean('isAllowedToBeRemembered'));

        $visibilityGroupsIds = is_string($data->get("visibility-group")) ? explode(',', $data->get('visibility-group')) : $data->get("visibility-group");
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

        $plainSignatoryPassword = $data->get('signatoryPassword') ?? null;
        if (!empty($plainSignatoryPassword)) {
            if (strlen($plainSignatoryPassword) < 4) {
                throw new FormException("Le code signataire doit contenir au moins 4 caractères");
            }
            $signatoryPassword = $encoder->hashPassword($user, $plainSignatoryPassword);
            $user->setSignatoryPassword($signatoryPassword);
        }


        if ($data->get('password') !== null) {
            $password = $encoder->hashPassword($user, $data->get('password'));
            $user->setPassword($password);
        }
        foreach ($user->getDeliveryTypes() as $typeToRemove) {
            $user->removeDeliveryType($typeToRemove);
        }
        if ($data->get('deliveryTypes')) {
            $deliveryTypes = explode(',', $data->get('deliveryTypes'));
            foreach ($deliveryTypes as $typeId) {
                $deliveryType = $typeRepository->find($typeId);
                $user->addDeliveryType($deliveryType);
            }
        }
        foreach ($user->getDispatchTypes() as $typeToRemove) {
            $user->removeDispatchType($typeToRemove);
        }
        if ($data->get('dispatchTypes')) {
            $dispatchTypes = explode(',', $data->get('dispatchTypes'));
            foreach ($dispatchTypes as $typeId) {
                $dispatchType = $typeRepository->find($typeId);
                $user->addDispatchType($dispatchType);
            }
        }
        foreach ($user->getHandlingTypes() as $typeToRemove) {
            $user->removeHandlingType($typeToRemove);
        }
        if ($data->get('handlingTypes')) {
            $handlingTypes = explode(',', $data->get('handlingTypes'));
            foreach ($handlingTypes as $typeId) {
                $handlingType = $typeRepository->find($typeId);
                $user->addHandlingType($handlingType);
            }
        }

        if (!empty($data->get('mobileLoginKey'))
            && $data->get('mobileLoginKey') !== $user->getMobileLoginKey()) {

            $usersWithKey = $utilisateurRepository->findBy([
                'mobileLoginKey' => $data->get('mobileLoginKey'),
            ]);
            if (!empty($usersWithKey)
                && (
                    count($usersWithKey) > 1
                    || $usersWithKey[0]->getId() !== $user->getId()
                )) {
                throw new FormException("Cette clé de connexion est déjà utilisée.");
            }
            else {
                $mobileLoginKey = $data->get('mobileLoginKey');
                if (empty($mobileLoginKey)
                    || strlen($mobileLoginKey) < UserService::MIN_MOBILE_KEY_LENGTH
                    || strlen($mobileLoginKey) > UserService::MAX_MOBILE_KEY_LENGTH) {
                    throw new FormException('La longueur de la clé de connexion doit être comprise entre ' . UserService::MIN_MOBILE_KEY_LENGTH . ' et ' . UserService::MAX_MOBILE_KEY_LENGTH . ' caractères.');
                }
                else {
                    $user->setMobileLoginKey($mobileLoginKey);
                }
            }
        }

        $entityManager->persist($user);
        $entityManager->flush();
        $cacheService->delete(CacheService::COLLECTION_LANGUAGES, 'languagesSelector'.$user->getId());

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

    #[Route('/export', name: 'export_csv_user', methods: 'GET')]
    public function exportCSV(CSVExportService          $CSVExportService,
                              UserService               $userService,
                              EntityManagerInterface    $entityManager,
                              TranslationService        $translation): StreamedResponse {
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
            'Types de ' . mb_strtolower($translation->translate("Demande", "Livraison", "Livraison", false)),
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

    #[Route("/creer", name: "user_new", options: ["expose" => true], methods: [self::POST], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::PARAM, Action::SETTINGS_DISPLAY_USERS], mode: HasPermission::IN_JSON)]
    public function new(Request $request,
                        UserPasswordHasherInterface $encoder,
                        PasswordService $passwordService,
                        UserService $userService,
                        LanguageService $languageService,
                        EntityManagerInterface $entityManager): Response {
        $data = $request->request;

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $visibilityGroupRepository = $entityManager->getRepository(VisibilityGroup::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $roleRepository = $entityManager->getRepository(Role::class);
        $languageRepository = $entityManager->getRepository(Language::class);

        $password = $data->get('password');
        $password2 = $data->get('password2');
        // validation du mot de passe
        $result = $passwordService->checkPassword($password, $password2);
        if(!$result['response']){
            return new JsonResponse([
                'success' => false,
                'msg' => $result['message'],
                'action' => 'new'
            ]);

        }

        // unicité de l'email
        $emailAlreadyUsed = $utilisateurRepository->count(['email' => $data->get('email')]);

        if ($emailAlreadyUsed > 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Cette adresse email est déjà utilisée.',
                'action' => 'new'
            ]);
        }

        if(!filter_var($data->get('email'), FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                "success" => false,
                "msg" => "L'adresse email principale \"{$data->get('email')}\" n'est pas valide"
            ]);
        }

        // unicité de l'username
        $usernameAlreadyUsed = $utilisateurRepository->count(['username' => $data->get('username')]);
        if ($usernameAlreadyUsed > 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => "Ce nom d'utilisateur est déjà utilisé.",
                'action' => 'new'
            ]);
        }

        $secondaryEmails = $data->has('secondaryEmails')
            ? explode(',', $data->get('secondaryEmails'))
            : [];

        if($secondaryEmails){
            foreach($secondaryEmails as $email) {
                if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $this->json([
                        "success" => false,
                        "msg" => "L'adresse email \"{$email}\" n'est pas valide"
                    ]);
                }
            }
        }

        $dropzone = explode(":", $data->get('dropzone'));
        if($dropzone[0] === 'location') {
            $dropzone = $entityManager->find(Emplacement::class, $dropzone[1]);
        } elseif($dropzone[0] === 'locationGroup') {
            $dropzone = $entityManager->find(LocationGroup::class, $dropzone[1]);
        }

        $utilisateur = new Utilisateur();
        $uniqueMobileKey = $userService->createUniqueMobileLoginKey($entityManager);
        $language = !empty($data->get('language'))
            ? $languageRepository->find($data->get('language'))
            : $languageService->getNewUserLanguage($entityManager);
        $role = $roleRepository->find($data->get('role'));

        $plainSignatoryPassword = $data->get('signatoryPassword') ?? null;

        if (!empty($plainSignatoryPassword) && strlen($plainSignatoryPassword) < 4) {
            throw new FormException("Le code signataire doit contenir au moins 4 caractères");
        }

        $signatoryPassword = $plainSignatoryPassword ? $encoder->hashPassword($utilisateur, $plainSignatoryPassword) : null;

        $utilisateur
            ->setUsername($data->get('username'))
            ->setEmail($data->get('email'))
            ->setSecondaryEmails($secondaryEmails)
            ->setPhone($data->get('phoneNumber'))
            ->setRole($role)
            ->setStatus(true)
            ->setDropzone($dropzone)
            ->setAddress($data->get('address'))
            ->setLanguage($language)
            ->setDateFormat($data->get('dateFormat') ?? Utilisateur::DEFAULT_DATE_FORMAT)
            ->setMobileLoginKey($uniqueMobileKey)
            ->setDeliverer($data->get('deliverer') ?? false)
            ->setSignatoryPassword($signatoryPassword)
            ->setDispatchBusinessUnit($data->get('dispatchBusinessUnit') ?? null);

        if ($password !== '') {
            $password = $encoder->hashPassword($utilisateur, $data->get('password'));
            $utilisateur->setPassword($password);
        }

        $visibilityGroupsIds = is_string($data->get("visibility-group")) ? explode(',', $data->get("visibility-group")) : $data->get("visibility-group");
        if ($visibilityGroupsIds) {
            $visibilityGroups = $visibilityGroupRepository->findBy(["id" => $visibilityGroupsIds]);
        }

        $utilisateur->setVisibilityGroups($visibilityGroups ?? []);
        if ($data->get('deliveryTypes')) {
            $deliveryTypes = explode(',', $data->get('deliveryTypes'));
            foreach ($deliveryTypes as $typeId) {
                $deliveryType = $typeRepository->find($typeId);
                $utilisateur->addDeliveryType($deliveryType);
            }
        }

        if ($data->get('dispatchTypes')) {
            $dispatchTypes = explode(',', $data->get('dispatchTypes'));
            foreach ($dispatchTypes as $typeId) {
                $dispatchType = $typeRepository->find($typeId);
                $utilisateur->addDispatchType($dispatchType);
            }
        }

        if ($data->get('handlingTypes')) {
            $handlingTypes = explode(',', $data->get('handlingTypes'));
            foreach ($handlingTypes as $typeId) {
                $handlingType = $typeRepository->find($typeId);
                $utilisateur->addHandlingType($handlingType);
            }
        }

        $entityManager->persist($utilisateur);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => 'L\'utilisateur <strong>' . $data->get('username') . '</strong> a bien été créé.'
        ]);
    }

    #[Route('/recherches', name: 'update_user_searches', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
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

    #[Route('/recherchesArticle', name: 'update_user_searches_for_article', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
    public function updateSearchesArticle(Request $request, EntityManagerInterface $entityManager) {
        $searches = $request->request->all("searches");

        /** @var Utilisateur $user */
        $user = $this->getUser();
        $user->setRechercheForArticle($searches ?: []);

        $entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "msg" => "Recherche rapide sauvegardée avec succès."
        ]);
    }

    #[Route('/taille-page-arrivage', name: 'update_user_page_length_for_arrivage', options: ['expose' => true], methods: 'GET|POST', condition: 'request.isXmlHttpRequest()')]
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

    #[Route('/langues/api', name: 'header_language_dateFormat_api', methods: ['POST'], options: ['expose' => true])]
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
        $cacheService->delete(CacheService::COLLECTION_LANGUAGES, 'languagesSelector'.$user->getId());

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route('/set-columns-order', name: 'set_columns_order', methods: 'POST', options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
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

    #[Route('/get-columns-order', name: 'get_columns_order', methods: 'GET', options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
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
