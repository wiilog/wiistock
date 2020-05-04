<?php


namespace App\Service;

use App\Entity\Collecte;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
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
	 * @var Utilisateur
	 */
    private $user;

    private $entityManager;
    private $stringService;
    private $valeurChampLibreService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                StringService $stringService,
                                ValeurChampLibreService $valeurChampLibreService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->stringService = $stringService;
        $this->valeurChampLibreService = $valeurChampLibreService;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    /**
     * @param null $params
     * @param null $statusFilter
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
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
            $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
    		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DEM_COLLECTE, $this->user);
		}

        $collecteRepository = $this->entityManager->getRepository(Collecte::class);

        $queryResult = $collecteRepository->findByParamsAndFilters($params, $filters);

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


    public function createHeaderDetailsConfig(Collecte $collecte): array {
        $requester = $collecte->getDemandeur();
        $status = $collecte->getStatut();
        $date = $collecte->getDate();
        $validationDate = $collecte->getValidationDate();
        $pointCollecte = $collecte->getPointCollecte();
        $object = $collecte->getObjet();
        $type = $collecte->getType();
        $comment = $collecte->getCommentaire();

        $detailsChampLibres = $collecte
            ->getValeurChampLibre()
            ->map(function (ValeurChampLibre $valeurChampLibre) {
                $champLibre = $valeurChampLibre->getChampLibre();
                $value = $this->valeurChampLibreService->formatValeurChampLibreForShow($valeurChampLibre);
                return [
                    'label' => $this->stringService->mbUcfirst($champLibre->getLabel()),
                    'value' => $value
                ];
            })
            ->toArray();

        return array_merge(
            [
                [ 'label' => 'Statut', 'value' => $status ? $this->stringService->mbUcfirst($status->getNom()) : '' ],
                [ 'label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : '' ],
                [ 'label' => 'Date de la demande', 'value' => $date ? $date->format('d/m/Y H:i') : '' ],
                [ 'label' => 'Date de validation', 'value' => $validationDate ? $validationDate->format('d/m/Y H:i') : '' ],
                [ 'label' => 'Destination', 'value' => $collecte->getStockOrDestruct() ? 'Mise en stock' : 'Destruction' ],
                [ 'label' => 'Objet', 'value' => $object ],
                [ 'label' => 'Point de collecte', 'value' => $pointCollecte ? $pointCollecte->getLabel() : '' ],
                [ 'label' => 'Type', 'value' => $type ? $type->getLabel() : '' ]
            ],
            $detailsChampLibres,
            [
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        );
    }
}
