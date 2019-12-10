<?php


namespace App\Service;

use App\Entity\FiltreSup;

use App\Entity\ReceptionTraca;
use App\Repository\FiltreSupRepository;
use App\Repository\ReceptionRepository;
use App\Repository\ReceptionTracaRepository;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Doctrine\ORM\EntityManagerInterface;
use Twig_Environment;

class ReceptionTracaService
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
     * @var UserService
     */
    private $userService;

    /**
     * @var ReceptionTracaRepository
     */
    private $receptionTracaRepository;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    private $security;

    private $em;

    public function __construct(UserService $userService, RouterInterface $router, EntityManagerInterface $em, Twig_Environment $templating, TokenStorageInterface $tokenStorage, Security $security, ReceptionTracaRepository $receptionTracaRepository, FiltreSupRepository $filtreSupRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
        $this->receptionTracaRepository = $receptionTracaRepository;
        $this->filtreSupRepository = $filtreSupRepository;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws \Exception
     */
    public function getDataForDatatable($params = null)
    {
        $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RCPT_TRACA, $this->security->getUser());

        $queryResult = $this->receptionTracaRepository->findByParamsAndFilters($params, $filters);

        $receptions = $queryResult['data'];

        $rows = [];
        foreach ($receptions as $reception) {
            $rows[] = $this->dataRowreceptionTraca($reception);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param ReceptionTraca $reception
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function dataRowReceptionTraca($reception)
    {
        $row = [
            'id' => $reception->getId(),
            'date' => $reception->getDateCreation() ? $reception->getDateCreation()->format('d/m/Y H:i:s') : '',
            'Arrivage' => $reception->getArrivage(),
            'Réception' => $reception->getNumber(),
            'Utilisateur' => $reception->getUser() ? $reception->getUser()->getUsername() : '',
            'Actions' => $this->templating->render('reception_traca/datatableRecepTracaRow.html.twig', [
                'recep' => $reception,
            ])
        ];

        return $row;
    }
}
