<?php

namespace App\Service;

use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;

class TransferRequestService {

    private $templating;
    private $router;
    private $user;
    private $em;
    private $userService;
    private $uniqueNumberService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                UniqueNumberService $uniqueNumberService,
                                RouterInterface $router,
                                UserService $userService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating) {
        $this->templating = $templating;
        $this->uniqueNumberService = $uniqueNumberService;
        $this->em = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->userService = $userService;
    }

    public function getDataForDatatable($params = null)
    {
        $filters = $this->em->getRepository(FiltreSup::class)
            ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSFER_REQUEST, $this->user);
        $queryResult = $this->em->getRepository(TransferRequest::class)
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

    public function dataRowTransfer(TransferRequest $transfer) {
        $url = $this->router->generate('transfer_request_show', [
            "id" => $transfer->getId()
        ]);

        return [
            'id' => $transfer->getId(),
            'number' => $transfer->getNumber(),
            'status' => FormatHelper::status($transfer->getStatus()),
            'origin' =>  FormatHelper::location($transfer->getOrigin()),
            'destination' =>  FormatHelper::location($transfer->getDestination()),
            'requester' => FormatHelper::user($transfer->getRequester()),
            'creationDate' => FormatHelper::datetime($transfer->getCreationDate()),
            'validationDate' => FormatHelper::datetime($transfer->getValidationDate()),
            'actions' => $this->templating->render('transfer/request/actions.html.twig', [
                'url' => $url,
            ]),
        ];
    }

    public function createHeaderDetailsConfig(TransferRequest $transfer): array {
        return [
            ['label' => 'Numéro', 'value' => $transfer->getNumber()],
            ['label' => 'Statut', 'value' => FormatHelper::status($transfer->getStatus())],
            ['label' => 'Demandeur', 'value' => FormatHelper::user($transfer->getRequester())],
            ['label' => 'Origine', 'value' => FormatHelper::location($transfer->getOrigin())],
            ['label' => 'Destination', 'value' => FormatHelper::location($transfer->getDestination())],
            ['label' => 'Date de création', 'value' => FormatHelper::datetime($transfer->getCreationDate())],
            ['label' => 'Date de validation', 'value' => FormatHelper::datetime($transfer->getValidationDate())],
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

    /**
     * @param EntityManagerInterface $entityManager
     * @param Statut|null $status
     * @param Emplacement|null $origin
     * @param Emplacement|null $destination
     * @param Utilisateur|null $requester
     * @param string|null $comment
     * @return TransferRequest
     * @throws Exception
     */
    public function createTransferRequest(EntityManagerInterface $entityManager,
                                          ?Statut $status,
                                          ?Emplacement $origin,
                                          ?Emplacement $destination,
                                          ?Utilisateur $requester,
                                          ?string $comment = null): TransferRequest {
        $now =  new DateTime("now", new DateTimeZone("Europe/Paris"));
        $transferRequestNumber = $this->uniqueNumberService->createUniqueNumber($entityManager, TransferRequest::NUMBER_PREFIX, TransferRequest::class);

        $transfer = new TransferRequest();
        $transfer
            ->setStatus($status)
            ->setCreationDate($now)
            ->setNumber($transferRequestNumber)
            ->setDestination($destination)
            ->setOrigin($origin)
            ->setRequester($requester)
            ->setComment($comment);

        return $transfer;
    }

}
