<?php

namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class TransferRequestService {

    private $templating;
    private $router;
    private $tokenStorage;
    private $em;
    private $userService;
    private $uniqueNumberService;

    #[Required]
    public FormatService $formatService;

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
        $this->tokenStorage = $tokenStorage;
        $this->userService = $userService;
    }

    public function getDataForDatatable($params = null)
    {
        $filters = $this->em->getRepository(FiltreSup::class)
            ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSFER_REQUEST, $this->tokenStorage->getToken()->getUser());
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
        $type = $entityManager->getRepository(Type::class)->findOneByCategoryLabel(CategoryType::TRANSFER_REQUEST);
        $now =  new DateTime("now");
        $transferRequestNumber = $this->uniqueNumberService->create($entityManager, TransferRequest::NUMBER_PREFIX, TransferRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $transfer = new TransferRequest();
        $transfer
            ->setType($type)
            ->setStatus($status)
            ->setCreationDate($now)
            ->setNumber($transferRequestNumber)
            ->setDestination($destination)
            ->setOrigin($origin)
            ->setRequester($requester)
            ->setComment($comment);

        return $transfer;
    }

    /**
     * @param TransferRequest $request
     * @param DateService $dateService
     * @param array $averageRequestTimesByType
     * @return array
     * @throws Exception
     */
    public function parseRequestForCard(TransferRequest $request,
                                        DateService $dateService,
                                        array $averageRequestTimesByType) {
        $requestStatus = $request->getStatus() ? $this->formatService->status($request->getStatus()) : '';

        if ($requestStatus !== TransferRequest::DRAFT && $request->getOrder()) {
            $href = $this->router->generate('transfer_order_show', ['id' => $request->getOrder()->getId()]);
        } else {
            $href = $this->router->generate('transfer_request_show', ['id' => $request->getId()]);
        }

        $articlesCounter = ($request->getArticles()->count() + $request->getReferences()->count());
        $articlePlural = $articlesCounter > 1 ? 's' : '';
        $bodyTitle = $articlesCounter . ' article' . $articlePlural;

        $typeId = $request->getType() ? $request->getType()->getId() : null;
        $averageTime = $averageRequestTimesByType[$typeId] ?? null;

        $deliveryDateEstimated = 'Non estimée';
        $estimatedFinishTimeLabel = 'Date de transfert non estimée';
        $today = new DateTime();

        if (isset($averageTime) && $request->getValidationDate()) {
            $expectedDate = (clone $request->getValidationDate())
                ->add($dateService->secondsToDateInterval($averageTime->getAverage()));
            if ($expectedDate >= $today) {
                $estimatedFinishTimeLabel = 'Date et heure de transfert prévue';
                $deliveryDateEstimated = $expectedDate->format('d/m/Y H:i');
                if ($expectedDate->format('d/m/Y') === $today->format('d/m/Y')) {
                    $estimatedFinishTimeLabel = 'Heure de transfert estimée';
                    $deliveryDateEstimated = $expectedDate->format('H:i');
                }
            }
        }

        $requestDate = $request->getCreationDate();
        $requestDateStr = $requestDate
            ? (
                $requestDate->format('d ')
                . DateService::ENG_TO_FR_MONTHS[$requestDate->format('M')]
                . $requestDate->format(' (H\hi)')
            )
            : 'Non défini';

        $statusesToProgress = [
            TransferRequest::DRAFT => 33,
            TransferRequest::TO_TREAT => 66,
            TransferRequest::TREATED => 100,
        ];

        return [
            'href' => $href ?? null,
            'errorMessage' => 'Vous n\'avez pas les droits d\'accéder à la page d\'état actuel de la demande de transfert',
            'estimatedFinishTime' => $deliveryDateEstimated,
            'estimatedFinishTimeLabel' => $estimatedFinishTimeLabel,
            'requestStatus' => $requestStatus,
            'requestBodyTitle' => $bodyTitle,
            'requestLocation' => $request->getDestination() ? $request->getDestination()->getLabel() : 'Non défini',
            'requestNumber' => $request->getNumber(),
            'requestDate' => $requestDateStr,
            'requestUser' => $request->getRequester() ? $request->getRequester()->getUsername() : 'Non défini',
            'cardColor' => $requestStatus === TransferRequest::DRAFT ? 'light-grey' : 'lightest-grey',
            'bodyColor' => $requestStatus === TransferRequest::DRAFT ? 'white' : 'light-grey',
            'topRightIcon' => 'transfer.svg',
            'progress' => $statusesToProgress[$requestStatus] ?? 0,
            'progressBarColor' => '#2ec2ab',
            'emergencyText' => '',
            'progressBarBGColor' => $requestStatus === TransferRequest::DRAFT ? 'white' : 'light-grey',
        ];
    }

}
