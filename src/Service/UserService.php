<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;

use App\Entity\Parametre;
use App\Entity\Utilisateur;

use App\Repository\ActionRepository;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\PreparationRepository;
use App\Repository\ManutentionRepository;
use App\Repository\ReceptionRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\RoleRepository;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class UserService
{
     /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RoleRepository
     */
    private $roleRepository;
    /**
     * @var Utilisateur
     */
    private $user;

    /**
     * @var ActionRepository
     */
    private $actionRepository;

     /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

	/**
	 * @var ParametreRepository
	 */
	private $parametreRepository;

	/**
	 * @var ParametreRoleRepository
	 */
	private $parametreRoleRepository;

	/**
	 * @var DemandeRepository
	 */
	private $demandeRepository;

	/**
	 * @var LivraisonRepository
	 */
	private $livraisonRepository;

	/**
	 * @var CollecteRepository
	 */
	private $collecteRepository;

	/**
	 * @var OrdreCollecteRepository
	 */
	private $ordreCollecteRepository;

	/**
	 * @var ManutentionRepository
	 */
	private $manutentionRepository;

	/**
	 * @var PreparationRepository
	 */
	private $preparationRepository;

	/**
	 * @var ReceptionRepository
	 */
	private $receptionRepository;

    public function __construct(ReceptionRepository $receptionRepository,
                                DemandeRepository $demandeRepository,
                                LivraisonRepository $livraisonRepository,
                                CollecteRepository $collecteRepository,
                                OrdreCollecteRepository $ordreCollecteRepository,
                                ManutentionRepository $manutentionRepository,
                                PreparationRepository $preparationRepository,
                                ParametreRepository $parametreRepository,
                                ParametreRoleRepository $parametreRoleRepository,
                                Twig_Environment $templating,
                                RoleRepository $roleRepository,
                                UtilisateurRepository $utilisateurRepository,
                                Security $security,
                                ActionRepository $actionRepository)
    {
        $this->user = $security->getUser();
        $this->actionRepository = $actionRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->roleRepository = $roleRepository;
        $this->templating = $templating;
        $this->parametreRepository = $parametreRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->collecteRepository = $collecteRepository;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->manutentionRepository = $manutentionRepository;
        $this->preparationRepository = $preparationRepository;
        $this->receptionRepository = $receptionRepository;
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

        $thisAction = $this->actionRepository->findOneByMenuLabelAndActionLabel($menuLabel, $actionLabel);

        if ($thisAction) {
            foreach ($actions as $action) {
                if ($action->getId() == $thisAction->getId()) return true;
            }
        }

        return false;
    }

    public function getDataForDatatable($params = null)
    {
        $data = $this->getUtilisateurDataByParams($params);
        $data['recordsTotal'] = (int)$this->utilisateurRepository->countAll();
        $data['recordsFiltered'] = (int)$this->utilisateurRepository->countAll();
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
        $utilisateurs = $this->utilisateurRepository->findByParams($params);

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
        $roles = $this->roleRepository->findAll();

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

		$role = $this->user->getRole();
		$param = $this->parametreRepository->findOneBy(['label' => Parametre::LABEL_AJOUT_QUANTITE]);
		if ($param) {
			$paramQuantite = $this->parametreRoleRepository->findOneByRoleAndParam($role, $param);
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
	 */
	public function isUsedByDemandsOrOrders($user)
	{
		$nbDemandesLivraison = $this->demandeRepository->countByUser($user);
		$nbDemandesCollecte = $this->collecteRepository->countByUser($user);
		$nbOrdresLivraison = $this->livraisonRepository->countByUser($user);
		$nbOrdresCollecte = $this->ordreCollecteRepository->countByUser($user);
		$nbManutentions = $this->manutentionRepository->countByUser($user);
		$nbPrepa = $this->preparationRepository->countByUser($user);
		$nbReceptions = $this->receptionRepository->countByUser($user);

		return $nbDemandesLivraison + $nbDemandesCollecte + $nbOrdresLivraison + $nbOrdresCollecte + $nbManutentions + $nbPrepa + $nbReceptions > 0;
	}
}
