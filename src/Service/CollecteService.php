<?php


namespace App\Service;

use App\Entity\Collecte;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use App\Repository\ArticleRepository;
use App\Repository\CollecteRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\ReferenceArticleRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CollecteService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

	/**
	 * @var OrdreCollecteRepository
	 */
    private $ordreCollecteRepository;

	/**
	 * @var FiltreSupRepository
	 */
    private $filtreSupRepository;

	/**
	 * @var Utilisateur
	 */
    private $user;

    private $em;

    public function __construct(TokenStorageInterface $tokenStorage,
                                OrdreCollecteRepository $ordreCollecteRepository,
                                FiltreSupRepository $filtreSupRepository,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ArticleRepository $articleRepository,
                                CollecteRepository $collecteRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->collecteRepository = $collecteRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function getDataForDatatable($params = null, $statusFilter = null)
    {
		if ($statusFilter) {
			$filters = [
				[
					'field' => 'statut',
					'value' => $statusFilter
				]
			];
		} else {
    		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DEM_COLLECTE, $this->user);
		}
        $queryResult = $this->collecteRepository->findByParamsAndFilters($params, $filters);

        $collecteArray = $queryResult['data'];

        $rows = [];
        foreach ($collecteArray as $collecte) {
            $rows[] = $this->dataRowCollecte($collecte);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    /**
     * @param Collecte $collecte
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowCollecte($collecte)
    {
        $url = $this->router->generate('collecte_show', ['id' => $collecte->getId()]);
        $row =
            [
                'id' => $collecte->getId() ?? '',
                'Création' => $collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : '',
                'Validation' => $collecte->getValidationDate() ? $collecte->getValidationDate()->format('d/m/Y') : '',
                'Demandeur' => $collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : '',
				'Objet' => $collecte->getObjet() ?? '',
				'Numéro' => $collecte->getNumero() ?? '',
                'Statut' => $collecte->getStatut()->getNom() ?? '',
                'Type' => $collecte->getType() ? $collecte->getType()->getLabel() : '',
                'Actions' => $this->templating->render('collecte/datatableCollecteRow.html.twig', [
                    'url' => $url,
                ]),
            ];
        return $row;
    }
}