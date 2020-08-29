<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Manutention;
use App\Entity\OrdreCollecte;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Preparation;
use App\Entity\Reception;
use App\Entity\Role;
use App\Entity\Utilisateur;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

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

    public function __construct(Twig_Environment $templating,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {
        $this->user = $security->getUser();
        $this->templating = $templating;
        $this->entityManager = $entityManager;
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

    public function getUserRole($user = null)
    {
        if (!$user) $user = $this->user;

        $role = $user ? $user->getRole() : null;

        return $role;
    }

    public function hasRightFunction(string $menuLabel, string $actionLabel, $user = null)
    {
        if (!$user) $user = $this->user;

        $role = $this->getUserRole($user);
		$actions = $role ? $role->getActions() : [];
		$actionRepository = $this->entityManager->getRepository(Action::class);
        $thisAction = $actionRepository->findOneByMenuLabelAndActionLabel($menuLabel, $actionLabel);

        if ($thisAction) {
            foreach ($actions as $action) {
                if ($action->getId() == $thisAction->getId()) return true;
            }
        }

        return false;
    }

    public function getDataForDatatable($params = null)
    {
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);

        $data = $this->getUtilisateurDataByParams($params);
        $data['recordsTotal'] = (int) $utilisateurRepository->countAll();
        $data['recordsFiltered'] = (int) $utilisateurRepository->countAll();
        return $data;
    }

	/**
	 * @param null $params
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function getUtilisateurDataByParams($params = null)
    {
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);
        $utilisateurs = $utilisateurRepository->findByParams($params);

        $rows = [];
        foreach ($utilisateurs as $utilisateur) {
            $rows[] = $this->dataRowUtilisateur($utilisateur);
        }
        return ['data' => $rows];
    }

	/**
	 * @param Utilisateur $utilisateur
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowUtilisateur($utilisateur)
    {
        $idUser = $utilisateur->getId();
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $roles = $roleRepository->findAll();

		$row = [
			'id' => $utilisateur->getId() ?? '',
			"Nom d'utilisateur" => $utilisateur->getUsername() ?? '',
			'Email' => $utilisateur->getEmail() ?? '',
			'Dropzone' => $utilisateur->getDropzone() ? $utilisateur->getDropzone()->getLabel() : '',
			'Dernière connexion' => $utilisateur->getLastLogin() ? $utilisateur->getLastLogin()->format('d/m/Y') : '',
			'Rôle' => $this->templating->render('utilisateur/role.html.twig', ['utilisateur' => $utilisateur, 'roles' => $roles]),
			'Actions' => $this->templating->render('utilisateur/datatableUtilisateurRow.html.twig', ['idUser' => $idUser]),
		];

		return $row;
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

    /**
     * @param Utilisateur|int $user
     * @return bool
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
	public function isUsedByDemandsOrOrders($user)
	{
	    $collecteRepository = $this->entityManager->getRepository(Collecte::class);
	    $demandeRepository = $this->entityManager->getRepository(Demande::class);
	    $livraisonRepository = $this->entityManager->getRepository(Livraison::class);
	    $ordreCollecteRepository = $this->entityManager->getRepository(OrdreCollecte::class);
	    $manutentionRepository = $this->entityManager->getRepository(Manutention::class);
	    $preparationRepository = $this->entityManager->getRepository(Preparation::class);
        $receptionRepository = $this->entityManager->getRepository(Reception::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);

        $isUsedInRequests = $demandeRepository->countByUser($user) > 0;
        $isUsedInCollects = $collecteRepository->countByUser($user) > 0;
        $isUsedInDeliveryOrders = $livraisonRepository->countByUser($user) > 0;
        $isUsedInCollectOrders = $ordreCollecteRepository->countByUser($user) > 0;
        $isUsedInManutentions = $manutentionRepository->countByUser($user) > 0;
        $isUsedInPreparationOrders = $preparationRepository->countByUser($user) > 0;
        $isUsedInReceptions = $receptionRepository->countByUser($user) > 0;
        $isUsedInAcheminements = $dispatchRepository->countByUser($user) > 0;
        $isUsedInArrivals = $arrivageRepository->countByUser($user) > 0;

		return (
            $isUsedInRequests
            || $isUsedInCollects
            || $isUsedInDeliveryOrders
            || $isUsedInCollectOrders
            || $isUsedInManutentions
            || $isUsedInPreparationOrders
            || $isUsedInReceptions
            || $isUsedInAcheminements
            || $isUsedInArrivals
        );
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
}
