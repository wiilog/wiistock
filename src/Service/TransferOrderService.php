<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Tracking\TrackingMovement;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\Tracking\TrackingMovementService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class TransferOrderService {

    private $templating;
    private $router;
    private $user;
    private $em;
    private $userService;
    private $tokenStorage;
    private $mouvementTracaService;
    private $mouvementStockService;
    private $uniqueNumberService;

    #[Required]
    public NotificationService $notificationService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                UniqueNumberService $uniqueNumberService,
                                RouterInterface $router,
                                UserService $userService,
                                EntityManagerInterface $entityManager,
                                TrackingMovementService $mouvementTracaService,
                                MouvementStockService $mouvementStockService,
                                Twig_Environment $templating) {
        $this->templating = $templating;
        $this->uniqueNumberService = $uniqueNumberService;
        $this->em = $entityManager;
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
        $this->userService = $userService;
        $this->mouvementTracaService = $mouvementTracaService;
        $this->mouvementStockService = $mouvementStockService;
    }

    public function getDataForDatatable($params, $filterReception)
    {
        $filters = $this->em->getRepository(FiltreSup::class)
            ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_TRANSFER_ORDER, $this->tokenStorage->getToken()->getUser());
        $queryResult = $this->em->getRepository(TransferOrder::class)
            ->findByParamsAndFilters($params, $filters, $filterReception);

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

    /**
     * @param TransferOrder $order
     * @param Utilisateur $operator
     * @param EntityManagerInterface $entityManager
     * @throws NonUniqueResultException
     */
    public function finish(TransferOrder $order,
                           Utilisateur $operator,
                           EntityManagerInterface $entityManager,
                           ?Emplacement $destination = null) {
        $oldOrderStatus = $order->getStatus();
        if (!$oldOrderStatus || $oldOrderStatus->getCode() === TransferRequest::TO_TREAT) {
            $request = $order->getRequest();

            $statutRepository = $entityManager->getRepository(Statut::class);

            $treatedRequest = $statutRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::TREATED);

            $treatedOrder = $statutRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_ORDER, TransferRequest::TREATED);

            $request->setStatus($treatedRequest);

            if($destination) {
                $order->setDropLocation($destination);
            }
            $order
                ->setStatus($treatedOrder)
                ->setOperator($operator)
                ->setTransferDate(new DateTime());
            $locationTo = $request->getDestination();
            $this->releaseRefsAndArticles($destination ?? $locationTo, $order, $operator, $entityManager, true);
        }
    }

    /**
     * @param Emplacement|null $locationTo
     * @param TransferOrder $order
     * @param Utilisateur $utilisateur
     * @param EntityManagerInterface $entityManager
     * @param bool $isFinish
     * @throws NonUniqueResultException
     */
    public function releaseRefsAndArticles(?Emplacement $locationTo,
                                           TransferOrder $order,
                                           Utilisateur $utilisateur,
                                           EntityManagerInterface $entityManager,
                                           bool $isFinish = false)
    {

        $statutRepository = $entityManager->getRepository(Statut::class);

        $availableArticle = $statutRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);

        $availableRef = $statutRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, ReferenceArticle::STATUT_ACTIF);

        $context = [$locationTo, $utilisateur, $entityManager, $availableArticle, $availableRef, $order, $isFinish];
        $request = $order->getRequest();

        $transferArticles = Stream::from($request->getReferences(), $request->getArticles());
        /** @var Article|ReferenceArticle $item */
        foreach ($transferArticles as $item) {
            if ($locationTo) {
                $this->createMovements($context, $item);
                $item->setEmplacement($locationTo);
            }
        }
    }

    private function createMovements($context, $refOrArt) {
        [$locationTo, $utilisateur, $entityManager, $availableArticle, $availableRef, $transferOrder, $isFinish] = $context;

        $statutAvailable = $refOrArt instanceof Article ? $availableArticle : $availableRef;
        $emplacementFrom = $refOrArt->getEmplacement();
        $quantite = $refOrArt instanceof Article ? $refOrArt->getQuantite() : $refOrArt->getQuantiteDisponible();
        $barcode = $refOrArt->getBarCode();
        if($locationTo) {
            $newMouvementStock = $this->mouvementStockService->createMouvementStock(
                $utilisateur,
                $emplacementFrom,
                $quantite,
                $refOrArt,
                MouvementStock::TYPE_TRANSFER
            );

            $trackingPick = $this->mouvementTracaService->createTrackingMovement(
                $refOrArt->getTrackingPack() ?: $barcode,
                $emplacementFrom,
                $utilisateur,
                new DateTime(),
                false,
                true,
                TrackingMovement::TYPE_PRISE,
                [
                    'mouvementStock' => $newMouvementStock,
                ]
            );
            $entityManager->persist($trackingPick);
            $this->mouvementStockService->finishStockMovement($newMouvementStock, new DateTime(), $locationTo);
            $trackingDrop = $this->mouvementTracaService->createTrackingMovement(
                $trackingPick->getPack(),
                $locationTo,
                $utilisateur,
                new DateTime(),
                false,
                true,
                TrackingMovement::TYPE_DEPOSE,
                [
                    'mouvementStock' => $newMouvementStock,
                ]
            );
            $entityManager->persist($trackingDrop);
            $entityManager->persist($newMouvementStock);
            if($isFinish) {
                $transferOrder->addStockMovement($newMouvementStock);
            }
        }
        $refOrArt->setStatut($statutAvailable);
    }

    public function dataRowTransfer(TransferOrder $transfer) {
        $url = $this->router->generate('transfer_order_show', [
            "id" => $transfer->getId()
        ]);

        return [
            'id' => $transfer->getId(),
            'number' => $transfer->getNumber(),
            'status' => FormatHelper::status($transfer->getStatus()),
            'origin' => FormatHelper::location($transfer->getRequest()->getOrigin()),
            'destination' => FormatHelper::location($transfer->getRequest()->getDestination()),
            'requester' => FormatHelper::user($transfer->getRequest()->getRequester()),
            'operator' => FormatHelper::user($transfer->getOperator()),
            'creationDate' => FormatHelper::datetime($transfer->getCreationDate()),
            'transferDate' => FormatHelper::datetime($transfer->getTransferDate()),
            'actions' => $this->templating->render('transfer/request/actions.html.twig', [
                'url' => $url,
            ]),
        ];
    }

    public function createHeaderDetailsConfig(TransferOrder $order): array {
        $request = $order->getRequest();

        return [
            ['label' => 'Numéro', 'value' => $order->getNumber()],
            ['label' => 'Statut', 'value' => FormatHelper::status($order->getStatus())],
            ['label' => 'Demandeur', 'value' => FormatHelper::user($request->getRequester())],
            ['label' => 'Opérateur', 'value' => FormatHelper::user($order->getOperator())],
            ['label' => 'Origine', 'value' => FormatHelper::location($request->getOrigin())],
            ['label' => 'Destination', 'value' => FormatHelper::location($request->getDestination())],
            ['label' => 'Dépose réelle', 'value' => FormatHelper::location($order->getDropLocation())],
            ['label' => 'Date de création', 'value' => FormatHelper::datetime($order->getCreationDate())],
            ['label' => 'Date de transfert', 'value' => FormatHelper::datetime($order->getTransferDate())],
            [
                'label' => 'Commentaire',
                'value' => $request->getComment() ?: '',
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ]
        ];
    }

    public function createTransferOrder(EntityManagerInterface $entityManager,
                                        ?Statut $status,
                                        ?TransferRequest $request): TransferOrder {
        $now =  new DateTime("now");

        $transferOrderNumber = $this->uniqueNumberService->create($entityManager, TransferOrder::NUMBER_PREFIX, TransferOrder::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $transfer = new TransferOrder();
        $transfer
            ->setRequest($request)
            ->setNumber($transferOrderNumber)
            ->setStatus($status)
            ->setCreationDate($now);

        return $transfer;
    }

}
