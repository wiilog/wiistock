<?php

namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class StatusService {

    private $specificService;
    private $entityManager;
    private $security;
    private $templating;
    private $router;

    /**
     * @var Utilisateur
     */
    private $user;

    public function __construct(SpecificService $specificService,
                                EntityManagerInterface $entityManager,
                                Security $security,
                                TokenStorageInterface $tokenStorage,
                                Twig_Environment $templating,
                                RouterInterface $router) {
        $this->specificService = $specificService;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->templating = $templating;
        $this->router = $router;
    }

    /**
     * @param null $params
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getDataForDatatable($params = null) {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $statusRepository = $this->entityManager->getRepository(Statut::class);

        $statusFilter = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_STATUS, $this->security->getUser());
        $queryResult = $statusRepository->findByParamsAndFilters($params, $statusFilter);

        $statusArray = $queryResult['data'];

        $rows = [];
        foreach ($statusArray as $status) {
            $rows[] = $this->dataRowStatus($status);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    /**
     * @param Statut $status
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowStatus($status) {

        $url['edit'] = $this->router->generate('status_api_edit', ['id' => $status->getId()]);
        return [
            'id' => $status->getId() ?? '',
            'category' => $status->getCategorie() ? $status->getCategorie()->getNom() : '',
            'label' => $status->getNom() ? $status->getNom() : '',
            'comment' => $status->getComment() ? $status->getComment() : '',
            'state' => $this->getStatusStateLabel($status->getState()),
            'defaultStatus' => $status->isDefaultForCategory() ? 'oui' : 'non',
            'notifToDeclarant' => $status->getSendNotifToDeclarant() ? 'oui' : 'non',
            'order' => $status->getDisplayOrder() ?? '',
            'type' => $status->getType() ? $status->getType()->getLabel() : '',
            'actions' => $this->templating->render('status/datatableStatusRow.html.twig', [
                'url' => $url,
                'statusId' => $status->getId(),
            ]),
        ];
    }

    public function getStatusStatesValues(): array {
        return [
            [
                'label' => 'Brouillon',
                'id' => Statut::DRAFT
            ],
            [
                'label' => 'À traiter',
                'id' => Statut::NOT_TREATED
            ],
            [
                'label' => 'Traité',
                'id' => Statut::TREATED
            ],
            [
                'label' => 'Litige',
                'id' => Statut::DISPUTE
            ],
            [
                'label' => 'Partiel',
                'id' => Statut::PARTIAL
            ]
        ];
    }

    public function getStatusStateLabel(int $stateId): ?string {
        $states = $this->getStatusStatesValues();
        $label = null;
        foreach ($states as $state) {
            if ($state['id'] === $stateId) {
                $label = $state['label'];
                break;
            }
        }
        return $label;
    }

}
