<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Dispatch;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Livraison;
use App\Entity\Handling;
use App\Entity\Menu;
use App\Entity\OrdreCollecte;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
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
    /**
     * @var Utilisateur
     */
    private $user;

	private $entityManager;
	private $roleService;

    public function __construct(Twig_Environment $templating,
                                RoleService $roleService,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {
        $this->user = $security->getUser();
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
        return $this->user;
    }

    public function hasRightFunction(string $menuLabel, string $actionLabel, $user = null) {
        $key = $this->roleService->getPermissionKey($menuLabel, $actionLabel);
        return isset($this->roleService->getPermissions($user ?: $this->user)[$key]);
    }

    public function getDataForDatatable(InputBag $params)
    {
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);
        $utilisateurs = $utilisateurRepository->findByParams($params);

        $rows = [];
        foreach ($utilisateurs as $utilisateur) {
            $rows[] = $this->dataRowUser($utilisateur);
        }
        $countAll = (int) $utilisateurRepository->countAll();

        return [
            'data' => $rows,
            'recordsTotal' => $countAll,
            'recordsFiltered' => $countAll,
        ];
    }

    public function dataRowUser(Utilisateur $user): array
    {
        $idUser = $user->getId();

		return [
			'id' => $user->getId() ?? '',
			"Nom d'utilisateur" => $user->getUsername() ?? '',
			'Email' => $user->getEmail() ?? '',
			'Dropzone' => $user->getDropzone() ? $user->getDropzone()->getLabel() : '',
			'Dernière connexion' => $user->getLastLogin() ? $user->getLastLogin()->format('d/m/Y') : '',
            'role' => $user->getRole() ? $user->getRole()->getLabel() : '',
            'visibilityGroup' => FormatHelper::entity($user->getVisibilityGroups()->toArray(), "label", ' / '),
            'status' => $user->getStatus() ? 'Actif' : "Inactif",
			'Actions' => $this->templating->render('utilisateur/datatableUtilisateurRow.html.twig', ['idUser' => $idUser]),
		];
    }

	/**
	 * @return bool
	 */
    public function hasParamQuantityByRef()
	{
		$response = false;

        $parametreRoleRepository = $this->entityManager->getRepository(ParametreRole::class);
        $parametreRepository = $this->entityManager->getRepository(Parametre::class);

		$role = $this->user->getRole();
		$param = $parametreRepository->findOneBy(['label' => Parametre::LABEL_AJOUT_QUANTITE]);
		if ($param) {
			$paramQuantite = $parametreRoleRepository->findOneByRoleAndParam($role, $param);
			if ($paramQuantite) {
				$response = $paramQuantite->getValue() == Parametre::VALUE_PAR_REF;
			}
		}

		return $response;
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
            FormatHelper::location($user->getDropzone()),
            FormatHelper::entity($user->getVisibilityGroups()->toArray(), "label", ' / '),
            $user->getStatus() ? 'Actif' : 'Inactif'
        ]);
    }

    public function getMobileRights(Utilisateur $user): array {
        return [
            'demoMode' => $this->hasRightFunction(Menu::NOMADE, Action::DEMO_MODE, $user),
            'notifications' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_NOTIFICATIONS, $user),
            'stock' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_STOCK, $user),
            'tracking' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_TRACA, $user),
            'group' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_GROUP, $user),
            'ungroup' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_UNGROUP, $user),
            'demande' => $this->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_HAND, $user),
            'inventoryManager' => $this->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER, $user),
            'emptyRound' => $this->hasRightFunction(Menu::TRACA, Action::EMPTY_ROUND, $user)
        ];
    }

}
