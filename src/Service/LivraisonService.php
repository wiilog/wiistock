<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Livraison;

use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\FiltreSupRepository;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;

class LivraisonService
{
    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var RouterInterface
     */
    private $router;

	/**
	 * @var UserService
	 */
    private $userService;

    private $security;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

	/**
	 * @var DemandeRepository
	 */
    private $demandeRepository;

    private $em;

    public function __construct(DemandeRepository $demandeRepository, UserService $userService, LivraisonRepository $livraisonRepository, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, TokenStorageInterface $tokenStorage, FiltreSupRepository $filtreSupRepository, Security $security)
    {
    
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->livraisonRepository = $livraisonRepository;
        $this->userService = $userService;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
        $this->demandeRepository = $demandeRepository;
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws NonUniqueResultException
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function getDataForDatatable($params = null)
    {
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_LIVRAISON, $this->security->getUser());

		$queryResult = $this->livraisonRepository->findByParamsAndFilters($params, $filters);

		$livraisons = $queryResult['data'];

		$rows = [];
		foreach ($livraisons as $livraison) {
			$rows[] = $this->dataRowLivraison($livraison);
		}
		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

	/**
	 * @param Livraison $livraison
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 * @throws NonUniqueResultException
	 */
    public function dataRowLivraison($livraison)
    {
		$url['show'] = $this->router->generate('livraison_show', ['id' => $livraison->getId()]);

		$demande = $this->demandeRepository->findOneByLivraison($livraison);

		$row = [
			'id' => $livraison->getId() ?? '',
			'Numéro' => $livraison->getNumero() ?? '',
			'Date' => $livraison->getDate() ? $livraison->getDate()->format('d/m/Y') : '',
			'Statut' => $livraison->getStatut() ? $livraison->getStatut()->getNom() : '',
			'Opérateur' => $livraison->getUtilisateur() ? $livraison->getUtilisateur()->getUsername() : '',
			'Type' => $demande && $demande->getType() ? $demande->getType()->getLabel() : '',
			'Actions' => $this->templating->render('livraison/datatableLivraisonRow.html.twig', ['url' => $url])
		];

        return $row;
    }
}
