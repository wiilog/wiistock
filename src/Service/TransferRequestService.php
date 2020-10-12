<?php

namespace App\Service;

use App\Controller\TransferRequestController;
use App\Entity\FiltreSup;
use App\Entity\TransferRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;

class TransferRequestService {

    private $templating;
    private $router;
    private $user;
    private $em;
    private $userService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                UserService $userService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating) {
        $this->templating = $templating;
        $this->em = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->userService = $userService;
    }

    public function getDataForDatatable($params = null, $statusFilter = null) {
        if($statusFilter) {
            $filters = [['field' => 'statut', 'value' => $statusFilter]];
        } else {
            $filters = $this->em->getRepository(FiltreSup::class)
                ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSFER_REQUEST, $this->user);
        }

        $queryResult = $this->em->getRepository(TransferRequest::class)
            ->findByParamsAndFilters($params, $filters);

        $transfers = $queryResult['data'];

        $rows = [];
        foreach($transfers as $transfer) {
            $rows[] = $this->dataRowTransfer($transfer);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowTransfer(TransferRequest $transfer) {
        $url = $this->router->generate('transfer_request_show', [
            "transfer" => $transfer->getId()
        ]);

        return [
            'id' => $transfer->getId(),
            'number' => $transfer->getNumber(),
            'status' => $transfer->getStatus() ? $transfer->getStatus()->getNom() : "",
            'destination' => $transfer->getDestination() ? $transfer->getDestination()->getLabel() : "",
            'requester' => $transfer->getRequester() ? $transfer->getRequester()->getUsername() : "",
            'creationDate' => $transfer->getCreationDate() ? $transfer->getCreationDate()->format("d/m/Y H:i") : "",
            'validationDate' => $transfer->getValidationDate() ? $transfer->getValidationDate()->format("d/m/Y H:i") : "",
            'actions' => $this->templating->render('transfer/request/actions.html.twig', [
                'url' => $url,
            ]),
        ];
    }

    public function createHeaderDetailsConfig(TransferRequest $transfer): array {
        $status = $transfer->getStatus();
        $requester = $transfer->getRequester();
        $destination = $transfer->getDestination();
        $created = $transfer->getCreationDate();
        $validated = $transfer->getValidationDate();

        return [
            ['label' => 'Numéro', 'value' => $transfer->getNumber()],
            ['label' => 'Statut', 'value' => $status ? $status->getNom() : ''],
            ['label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : ''],
            ['label' => 'Destination', 'value' => $destination ? $destination->getLabel() : ''],
            ['label' => 'Date de création', 'value' => $created ? $created->format('d/m/Y H:i') : ''],
            ['label' => 'Date de validation', 'value' => $validated ? $validated->format('d/m/Y H:i') : ''],
            [
                'label' => 'Commentaire',
                'value' => $transfer->getComment() ?: "",
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]
        ];
    }

}
