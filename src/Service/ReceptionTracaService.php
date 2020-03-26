<?php


namespace App\Service;

use App\Entity\FiltreSup;

use App\Entity\ReceptionTraca;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

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

    private $security;

    private $entityManager;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                Security $security)
    {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws \Exception
     */
    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $receptionTracaRepository = $this->entityManager->getRepository(ReceptionTraca::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_RCPT_TRACA, $this->security->getUser());
        $queryResult = $receptionTracaRepository->findByParamsAndFilters($params, $filters);

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
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
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
