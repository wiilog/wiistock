<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Dispatch;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Inventory\InventoryMissionRule;
use App\Entity\Livraison;
use App\Entity\Handling;
use App\Entity\Menu;
use App\Entity\OrdreCollecte;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\Reception;
use App\Entity\TrackingMovement;
use App\Entity\Utilisateur;

use App\Helper\FormatHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Twig\Environment as Twig_Environment;
use Symfony\Component\Security\Core\Security;

class UserService
{

    public const MIN_MOBILE_KEY_LENGTH = 14;
    public const MAX_MOBILE_KEY_LENGTH = 24;

     /**
     * @var Twig_Environment
     */
    private $templating;

	private $entityManager;
    private $roleService;
    private $security;

    public function __construct(Twig_Environment $templating,
                                RoleService $roleService,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {
        $this->security = $security;
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->roleService = $roleService;
    }

    public static function CreateMobileLoginKey(int $length = self::MIN_MOBILE_KEY_LENGTH): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function getUser(): ?Utilisateur {
        return $this->security->getUser();
    }

    public function hasRightFunction(string $menuLabel, string $actionLabel, $user = null) {
        $key = $this->roleService->getPermissionKey($menuLabel, $actionLabel);
        return isset($this->roleService->getPermissions($user ?: $this->getUser())[$key]);
    }

    public function getDataForDatatable(InputBag $params)
    {
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);
        $result = $utilisateurRepository->findByParams($params);

        $rows = [];
        foreach ($result['data'] as $utilisateur) {
            $rows[] = $this->dataRowUser($utilisateur);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
        ];
    }

    public function dataRowUser(Utilisateur $user): array {

		return [
			'id' => $user->getId() ?? '',
			'username' => $user->getUsername() ?? '',
			'email' => $user->getEmail() ?? '',
			'dropzone' => $user->getDropzone() ? $user->getDropzone()->getLabel() : '',
			'lastLogin' => FormatHelper::date($user->getLastLogin()),
            'role' => $user->getRole() ? $user->getRole()->getLabel() : '',
            'visibilityGroup' => FormatHelper::entity($user->getVisibilityGroups()->toArray(), "label", ' / '),
            'status' => $user->getStatus() ? 'Actif' : "Inactif",
			'Actions' => $this->templating->render('settings/utilisateurs/utilisateurs/actions.html.twig', ['idUser' => $user->getId()]),
		];
    }

	public function getUserOwnership(EntityManagerInterface $entityManager,
                                     Utilisateur $user): array {
	    $collecteRepository = $entityManager->getRepository(Collecte::class);
	    $demandeRepository = $entityManager->getRepository(Demande::class);
	    $livraisonRepository = $entityManager->getRepository(Livraison::class);
	    $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
	    $handlingRepository = $entityManager->getRepository(Handling::class);
	    $preparationRepository = $entityManager->getRepository(Preparation::class);
        $receptionRepository = $entityManager->getRepository(Reception::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $inventoryMissionRuleRepository = $entityManager->getRepository(InventoryMissionRule::class);

        $isUsedInRequests = $demandeRepository->countByUser($user);
        $isUsedInCollects = $collecteRepository->countByUser($user);
        $isUsedInDeliveryOrders = $livraisonRepository->countByUser($user);
        $isUsedInCollectOrders = $ordreCollecteRepository->countByUser($user);
        $isUsedInHandlings = $handlingRepository->countByUser($user);
        $isUsedInPreparationOrders = $preparationRepository->count(['utilisateur' => $user]);
        $isUsedInReceptions = $receptionRepository->countByUser($user);
        $isUsedInDispatches = $dispatchRepository->countByUser($user);
        $isUsedInArrivals = $arrivageRepository->countByUser($user);
        $hasTrackingMovement = $trackingMovementRepository->count(['operateur' => $user]);
        $hasSignatoryLocation = $locationRepository->count(['signatory' => $user]);
        $hasInventoryMissionRules = $inventoryMissionRuleRepository->count(['creator' => $user]);

        return [
            'demande(s) de livraison' => $isUsedInRequests,
            'demande(s) de collecte' => $isUsedInCollects,
            'ordre(s) de livraison' => $isUsedInDeliveryOrders,
            'ordre(s) de collecte' => $isUsedInCollectOrders,
            'demande(s) de service' => $isUsedInHandlings,
            'ordre(s) de préparation' => $isUsedInPreparationOrders,
            'reception(s)' => $isUsedInReceptions,
            'acheminement(s)' => $isUsedInDispatches,
            'arrivage(s)' => $isUsedInArrivals,
            'mouvement(s) de traçabilité' => $hasTrackingMovement,
            'emplacement(s)' => $hasSignatoryLocation,
            "planification d'inventaire" => $hasInventoryMissionRules,
        ];
	}

	public function createUniqueMobileLoginKey(EntityManagerInterface $entityManager): string {
	    $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        do {
            $mobileLoginKey = UserService::CreateMobileLoginKey();
            $userWithThisKey = $utilisateurRepository->findBy(['mobileLoginKey' => $mobileLoginKey]);
        }
        while(!empty($userWithThisKey));
        return $mobileLoginKey;
    }

    public function putCSVLine(CSVExportService $CSVExportService,
                               $output,
                               Utilisateur $user): void {
        $role = $user->getRole();
        $secondaryEmails = $user->getSecondaryEmails() ?? [];
        $dropzone = $user->getDropzone();
        $CSVExportService->putLine($output, [
            $role ? $role->getLabel() : '',
            $user->getUsername() ?? '',
            $user->getEmail() ?? '',
            $secondaryEmails[0] ?? '',
            $secondaryEmails[1] ?? '',
            $user->getPhone() ?? '',
            $user->getAddress() ?? '',
            FormatHelper::datetime($user->getLastLogin()),
            $user->getMobileLoginKey() ?? '',
            FormatHelper::entity($user->getDeliveryTypes()->toArray(), 'label', ' , '),
            FormatHelper::entity($user->getDispatchTypes()->toArray(), 'label', ' , '),
            FormatHelper::entity($user->getHandlingTypes()->toArray(), 'label', ' , '),
            $dropzone instanceof Emplacement ? FormatHelper::location($dropzone) : FormatHelper::locationGroup($dropzone),
            FormatHelper::entity($user->getVisibilityGroups()->toArray(), "label", ' / '),
            FormatHelper::bool($user->isDeliverer()),
            $user->getStatus() ? 'Actif' : 'Inactif'
        ]);
    }

    public function getMobileRights(Utilisateur $user): array {
        return [
            'demoMode' => $this->hasRightFunction(Menu::NOMADE, Action::DEMO_MODE, $user),
            'notifications' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_NOTIFICATIONS, $user),
            'stock' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_STOCK, $user),
            'tracking' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_TRACA, $user),
            'track' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_TRACK, $user),
            'group' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_GROUP, $user),
            'ungroup' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_UNGROUP, $user),
            'demande' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_HAND, $user),
            'inventoryManager' => $this->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER, $user),
            'groupedSignature' => $this->hasRightFunction(Menu::DEM, Action::GROUPED_SIGNATURE, $user),
            'emptyRound' => $this->hasRightFunction(Menu::TRACA, Action::EMPTY_ROUND, $user)
        ];
    }

    public function getUserFCMChannel(Utilisateur $user): string {
        return 'user-' . $user->getId();
    }

}
