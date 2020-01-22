<?php


namespace App\Service;

use App\Entity\Acheminements;
use App\Entity\FiltreSup;
use App\Entity\Utilisateur;

use App\Repository\AcheminementsRepository;
use App\Repository\FiltreSupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

Class AcheminementsService
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

    /**
     * @var AcheminementsRepository
     */
    private $acheminementsRepository;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    private $em;

    public function __construct(TokenStorageInterface $tokenStorage, RouterInterface $router, EntityManagerInterface $em, Twig_Environment $templating, AcheminementsRepository $acheminementsRepository, FiltreSupRepository $filtreSupRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->acheminementsRepository = $acheminementsRepository;
        $this->filtreSupRepository = $filtreSupRepository;
    }

    public function getDataForDatatable($params = null)
    {
        $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ACHEMINEMENTS, $this->user);
        $queryResult = $this->acheminementsRepository->findByParamAndFilters($params, $filters);

        $acheminementsArray = $queryResult['data'];

        $rows = [];
        foreach ($acheminementsArray as $acheminement) {
            $rows[] = $this->dataRowAcheminement($acheminement);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

	/**
	 * @param Acheminements $acheminement
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function dataRowAcheminement($acheminement)
    {
        $nbColis = count($acheminement->getColis());
        $row =
            [
                'id' => $acheminement->getId() ?? 'Non défini',
                'Date' => $acheminement->getDate() ? $acheminement->getDate()->format('d/m/Y H:i:s') : 'Non défini',
                'Demandeur' => $acheminement->getRequester() ? $acheminement->getReequester()->getUserName() : '',
                'Destinataire' => $acheminement->getReceiver() ? $acheminement->getReceiver()->getUserName() : '',
                'Emplacement prise' => $acheminement->getLocationTake() ?? '',
                'Emplacement de dépose' => $acheminement->getLocationDrop() ?? '',
                'Nb Colis' => $nbColis ?? 0,
                'Statut' => $acheminement->getStatut() ? $acheminement->getStatut()->getNom() : '',
                'Actions' => $this->templating->render('acheminements/datatableAcheminementsRow.html.twig', [
                    'acheminement' => $acheminement
                ]),
            ];
        return $row;
    }
}
