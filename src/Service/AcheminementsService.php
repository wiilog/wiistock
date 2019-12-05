<?php


namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Utilisateur;

use App\Repository\AcheminementsRepository;
use App\Repository\FiltreSupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

Class AcheminementsService
{
    /**
     * @var \Twig_Environment
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

    public function __construct(TokenStorageInterface $tokenStorage, RouterInterface $router, EntityManagerInterface $em, \Twig_Environment $templating, AcheminementsRepository $acheminementsRepository, FiltreSupRepository $filtreSupRepository)
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
        foreach ($acheminementsArray as $acheminements) {
            $rows[] = $this->dataRowAcheminements($acheminements);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }
    public function dataRowAcheminements($acheminements)
    {
        $nbColis = count($acheminements->getColis());
        $row =
            [
                'id' => ($acheminements->getId() ? $acheminements->getId() : 'Non défini'),
                'Demandeur' => ($acheminements->getRequester() ? $acheminements->getRequester()->getUserName() : null),
                'Destinataire' => ($acheminements->getReceiver() ? $acheminements->getReceiver()->getUserName() : null),
                'Emplacement prise' => ($acheminements->getLocationTake() ? $acheminements->getLocationTake() : null),
                'Emplacement de dépose' => ($acheminements->getLocationDrop() ? $acheminements->getLocationDrop() : null),
                'Nb Colis' => ($nbColis ? $nbColis : 0),
                'Statut' => ($acheminements->getStatut()->getNom() ? $acheminements->getStatut()->getNom() : null),
                'Actions' => $this->templating->render('acheminements/datatableAcheminementsRow.html.twig', [
                    'acheminement' => $acheminements
                ]),
            ];
        return $row;
    }
}