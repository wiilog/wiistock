<?php

namespace App\Service;

use App\Controller\TransferRequestController;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;

class TransferOrderService {

    private $templating;
    private $router;
    private $user;
    private $em;
    private $userService;
    private $mouvementTracaService;
    private $mouvementStockService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                RouterInterface $router,
                                UserService $userService,
                                EntityManagerInterface $entityManager,
                                TrackingMovementService $mouvementTracaService,
                                MouvementStockService $mouvementStockService,
                                Twig_Environment $templating) {
        $this->templating = $templating;
        $this->em = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->userService = $userService;
        $this->mouvementTracaService = $mouvementTracaService;
        $this->mouvementStockService = $mouvementStockService;
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

    /**
     * @param Emplacement|null $locationTo
     * @param TransferOrder $transferOrder
     * @param Utilisateur $utilisateur
     * @param EntityManagerInterface $entityManager
     * @param bool $isFinish
     * @throws NonUniqueResultException
     */
    public function releaseRefsAndArticles(?Emplacement $locationTo,
                                           TransferOrder $transferOrder,
                                           Utilisateur $utilisateur,
                                           EntityManagerInterface $entityManager, bool $isFinish = false) {

        $statutRepository = $entityManager->getRepository(Statut::class);

        $availableArticle = $statutRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);

        $availableRef = $statutRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, ReferenceArticle::STATUT_ACTIF);

        $context = [$locationTo, $utilisateur, $entityManager, $availableArticle, $availableRef, $transferOrder, $isFinish];

        Stream::from($transferOrder->getRequest()->getReferences()->toArray())
            ->merge($transferOrder->getRequest()->getArticles()->toArray())
            ->each(function($refOrArt) use ($context) {
                $this->createMovements($context, $refOrArt);
            });
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
                MouvementStock::TYPE_TRANSFERT
            );
            $trackingPick = $this->mouvementTracaService->createTrackingMovement(
                $barcode,
                $emplacementFrom,
                $utilisateur,
                new DateTime(),
                false,
                true,
                TrackingMovement::TYPE_PRISE,
                [
                    'mouvementStock' => $newMouvementStock
                ]
            );
            $entityManager->persist($trackingPick);
            $this->mouvementStockService->finishMouvementStock($newMouvementStock, new DateTime(), $locationTo);
            $trackingDrop = $this->mouvementTracaService->createTrackingMovement(
                $barcode,
                $locationTo,
                $utilisateur,
                new DateTime(),
                false,
                true,
                TrackingMovement::TYPE_DEPOSE,
                [
                    'mouvementStock' => $newMouvementStock
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
            'destination' => FormatHelper::location($transfer->getRequest()->getDestination()),
            'requester' => FormatHelper::user($transfer->getRequest()->getRequester()),
            'operator' => FormatHelper::user($transfer->getOperator()),
            'creationDate' => FormatHelper::datetime($transfer->getCreationDate()),
            'validationDate' => FormatHelper::datetime($transfer->getTransferDate()),
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
            ['label' => 'Destination', 'value' => FormatHelper::location($request->getDestination())],
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
}
