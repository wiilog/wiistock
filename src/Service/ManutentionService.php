<?php


namespace App\Service;


use App\Entity\FiltreSup;
use App\Entity\Manutention;
use App\Entity\Utilisateur;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ManutentionService
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

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function getDataForDatatable($params = null, $statusFilter = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $manutentionRepository = $this->entityManager->getRepository(Manutention::class);

		if ($statusFilter) {
			$filters = [
				[
					'field' => 'statut',
					'value' => $statusFilter
				]
			];
		} else {
        	$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_MANUT, $this->user);
		}
        $queryResult = $manutentionRepository->findByParamAndFilters($params, $filters);

        $manutArray = $queryResult['data'];

        $rows = [];
        foreach ($manutArray as $manutention) {
            $rows[] = $this->dataRowManut($manutention);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    /**
     * @param Manutention $manutention
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowManut($manutention)
    {
        $row = [
            'id' => ($manutention->getId() ? $manutention->getId() : 'Non défini'),
            'Date demande' => ($manutention->getDate() ? $manutention->getDate()->format('d/m/Y') : null),
            'Demandeur' => ($manutention->getDemandeur() ? $manutention->getDemandeur()->getUserName() : null),
            'Libellé' => ($manutention->getlibelle() ? $manutention->getLibelle() : null),
            'Date souhaitée' => ($manutention->getDateAttendue() ? $manutention->getDateAttendue()->format('d/m/Y H:i') : null),
            'Statut' => ($manutention->getStatut()->getNom() ? $manutention->getStatut()->getNom() : null),
            'Actions' => $this->templating->render('manutention/datatableManutentionRow.html.twig', [
                'manut' => $manutention
            ]),
        ];
        return $row;
    }
}
