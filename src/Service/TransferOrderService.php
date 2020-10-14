<?php

namespace App\Service;

use App\Controller\TransferRequestController;
use App\Entity\FiltreSup;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;

class TransferOrderService {

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

    public function getDataForDatatable($params = null)
    {
        $filters = $this->em->getRepository(FiltreSup::class)
            ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSFER_ORDER, $this->user);
        $queryResult = $this->em->getRepository(TransferOrder::class)
            ->findByParamsAndFilters($params, $filters);

        $transfers = $queryResult['data'];

        $rows = [];
        foreach ($transfers as $transfer) {
            $rows[] = $this->dataRowTransfer($transfer);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowTransfer(TransferOrder $transfer) {
        $url = $this->router->generate('transfer_order_show', [
            "transfer" => $transfer->getId()
        ]);

        return [
            'id' => $transfer->getId(),
            'number' => $transfer->getNumber(),
            'status' => $transfer->getStatus() ? $transfer->getStatus()->getNom() : "",
            'destination' => $transfer->getRequest()->getDestination() ? $transfer->getRequest()->getDestination()->getLabel() : "",
            'requester' => $transfer->getRequest()->getRequester() ? $transfer->getRequest()->getRequester()->getUsername() : "",
            'operator' => $transfer->getOperator() ? $transfer->getOperator()->getUsername() : "",
            'creationDate' => $transfer->getCreationDate() ? $transfer->getCreationDate()->format("d/m/Y H:i") : "",
            'validationDate' => $transfer->getTransferDate() ? $transfer->getTransferDate()->format("d/m/Y H:i") : "",
            'actions' => $this->templating->render('transfer/request/actions.html.twig', [
                'url' => $url,
            ]),
        ];
    }

    public function createHeaderDetailsConfig(TransferOrder $transferOrder): array {
        $transfer = $transferOrder->getRequest();
        $status = $transferOrder->getStatus();
        $requester = $transfer->getRequester();
        $destination = $transfer->getDestination();
        $created = $transferOrder->getCreationDate();
        $validated = $transferOrder->getTransferDate();

        return [
            ['label' => 'Numéro', 'value' => $transferOrder->getNumber()],
            ['label' => 'Statut', 'value' => $status ? $status->getNom() : ''],
            ['label' => 'Demandeur', 'value' => $requester ? $requester->getUsername() : ''],
            ['label' => 'Destination', 'value' => $destination ? $destination->getLabel() : ''],
            ['label' => 'Date de création', 'value' => $created ? $created->format('d/m/Y H:i') : ''],
            ['label' => 'Date de validation', 'value' => $validated ? $validated->format('d/m/Y H:i') : ''],
        ];
    }

}
