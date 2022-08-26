<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Article;
use App\Entity\Utilisateur;
use App\Service\NotificationService;
use WiiCommon\Helper\Stream;
use App\Service\TransferOrderService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use App\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/transfert/ordres")
 */
class TransferOrderController extends AbstractController {

    private $userService;
    private $service;

    /** @Required */
    public NotificationService $notificationService;

    public function __construct(UserService $us, TransferOrderService $service) {
        $this->userService = $us;
        $this->service = $service;
    }

    /**
     * @Route("/liste/{reception}", name="transfer_order_index", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS})
     */
    public function index(EntityManagerInterface $em,
                          $reception = null): Response {
        $statusRepository = $em->getRepository(Statut::class);

        return $this->render('transfer/order/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::TRANSFER_ORDER),
            'receptionFilter' => $reception,
        ]);
    }

    /**
     * @Route("/api", name="transfer_orders_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request): Response {
        $filterReception = $request->request->get('filterReception');
        $data = $this->service->getDataForDatatable($request->request, $filterReception);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer/{transferRequest}", name="transfer_order_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(TransferOrderService $transferOrderService,
                        EntityManagerInterface $entityManager,
                        TransferRequest $transferRequest): Response {
        $statutRepository = $entityManager->getRepository(Statut::class);

        $toTreatOrder = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_ORDER, TransferOrder::TO_TREAT);
        $toTreatRequest = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::TO_TREAT);

        $transitStatusForArticles = $statutRepository->findOneByCategorieNameAndStatutCode(
            CategorieStatut::ARTICLE,
            Article::STATUT_EN_TRANSIT
        );
        $transitStatusForRefs = $statutRepository->findOneByCategorieNameAndStatutCode(
            CategorieStatut::REFERENCE_ARTICLE,
            ReferenceArticle::STATUT_INACTIF
        );

        $inTransit = [];

        foreach ($transferRequest->getReferences() as $reference) {
            if($reference->getStatut()->getCode() === Article::STATUT_EN_TRANSIT) {
                $inTransit["reference"][] = $reference->getReference();
            }

            $reference->setStatut($transitStatusForRefs);
        }

        /** @var Article $article */
        foreach ($transferRequest->getArticles() as $article) {
            if($article->getStatut()->getCode() === Article::STATUT_EN_TRANSIT) {
                $inTransit["article"][] = $article->getBarCode();
            }

            $article->setStatut($transitStatusForArticles);
        }

        if(!empty($inTransit)) {
            $output = [];

            if(isset($inTransit["reference"])) {
                $references = implode(", ", $inTransit["reference"]);
                $output[] = "Les références $references sont en transit.";
            }

            if(isset($inTransit["article"])) {
                $articles = implode(", ", $inTransit["article"]);
                $output[] = "Les articles $articles sont en transit.";
            }

            return new JsonResponse([
                "success" => false,
                "msg" => implode(" ", $output)
            ]);
        }

        $transferRequest->setStatus($toTreatRequest);
        $transferRequest->setValidationDate(new DateTime());

        $transferOrder = $transferOrderService->createTransferOrder($entityManager, $toTreatOrder, $transferRequest);
        $entityManager->persist($transferOrder);

        try {
            $entityManager->flush();
            if ($transferRequest->getType()->isNotificationsEnabled()) {
                $this->notificationService->toTreat($transferOrder);
            }

        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Un autre ordre de transfert est en cours de création, veuillez réessayer.'
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('transfer_order_show', ['id' => $transferOrder->getId()]),
        ]);
    }

    /**
     * @Route("/voir/{id}", name="transfer_order_show", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS})
     */
    public function show(TransferOrder $transfer): Response {
        return $this->render('transfer/order/show.html.twig', [
            'order' => $transfer,
            'detailsConfig' => $this->service->createHeaderDetailsConfig($transfer),
            'modifiable' => $transfer->getStatus()->getNom() === TransferOrder::TO_TREAT
        ]);
    }

    /**
     * @Route("/article/api/{transfer}", name="transfer_order_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS}, mode=HasPermission::IN_JSON)
     */
    public function articleApi(TransferOrder $transfer): Response {

        $articles = $transfer->getRequest()->getArticles();
        $references = $transfer->getRequest()->getReferences();

        $rowsRC = [];
        foreach($references as $reference) {
            $rowsRC[] = [
                'Référence' => $reference->getReference(),
                'barCode' => $reference->getBarCode(),
                'Quantité' => $reference->getQuantiteDisponible(),
                'Actions' => $this->renderView('transfer/order/article/actions.html.twig', [
                    'refArticleId' => $reference->getId(),
                ]),
            ];
        }

        $rowsCA = [];
        foreach($articles as $article) {
            $rowsCA[] = [
                'Référence' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                'barCode' => $article->getBarCode(),
                'Quantité' => $article->getQuantite(),
                'Actions' => $this->renderView('transfer/order/article/actions.html.twig', [
                    'id' => $article->getId(),
                ]),
            ];
        }
        $data['data'] = array_merge($rowsCA, $rowsRC);

        return new JsonResponse($data);
    }

    /**
     * @Route("/valider/{id}", name="transfer_order_validate", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finish(EntityManagerInterface $entityManager,
                           TransferOrderService $transferOrderService,
                           TransferOrder $transferOrder): Response {

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $transferOrderService->finish($transferOrder, $currentUser, $entityManager);

        $entityManager->flush();
        return $this->json([
            'success' => true,
            'redirect' => $this->generateUrl('transfer_order_show', [
                'id' => $transferOrder->getId()
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="transfer_order_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           TransferOrderService $transferOrderService,
                           TransferOrder $transferOrder): Response
    {
        if ($data = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);
            $availableStatus = $statutRepository
                ->findOneByCategorieNameAndStatutCode( CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
            $draftRequest = $statutRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);

            $transferRequest = $transferOrder->getRequest();

            foreach ($transferRequest->getArticles() as $article) {
                if($article->getStatut()->getCode() === Article::STATUT_EN_TRANSIT) {
                    $article->setStatut($availableStatus);
                }
            }

            $transferRequest->setStatus($draftRequest);
            $transferRequest->setValidationDate(null);

            $locationsRepository = $entityManager->getRepository(Emplacement::class);

            if (isset($data['destination'])) {
                $locationTo = $locationsRepository->find($data['destination']);
            } else {
                $locationTo = null;
            }

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $transferOrderService->releaseRefsAndArticles($locationTo, $transferOrder, $currentUser, $entityManager);

            foreach ($transferOrder->getStockMovements() as $mouvementStock) {
                $mouvementStock->setTransferOrder(null);
            }
            $entityManager->flush();

            $requestId = $transferRequest->getId();

            $entityManager->remove($transferOrder);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'redirect' => $this->generateUrl('transfer_request_show', [
                    'id' => $requestId
                ])
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="transfer_order_export",options={"expose"=true}, methods="GET|POST" )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return Response
     * @throws Exception
     */
    public function export(Request $request,
                           EntityManagerInterface $entityManager,
                           CSVExportService $CSVExportService): Response {
        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("Y-m-d H:i:s", $dateMin . " 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("Y-m-d H:i:s", $dateMax . " 23:59:59");

        if(isset($dateTimeMin, $dateTimeMax)) {
            $now = new DateTime("now");

            $transferRepository = $entityManager->getRepository(TransferOrder::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $transfers = $transferRepository->findByDates($dateTimeMin, $dateTimeMax);
            $articlesByRequest = $articleRepository->getArticlesGroupedByTransfer($transfers, false);
            $referenceArticlesByRequest = $referenceArticleRepository->getReferenceArticlesGroupedByTransfer($transfers, false);

            $header = [
                "numéro ordre",
                "numéro demande",
                "statut",
                "demandeur",
                "opérateur",
                "origine",
                "destination",
                "date de création",
                "date de traitement",
                "commentaire",
                "référence",
                "code barre"
            ];

            return $CSVExportService->createBinaryResponseFromData(
                "export_ordre_transfert" . $now->format("d_m_Y") . ".csv",
                $transfers,
                $header,
                function (TransferOrder $transferOrder) use ($articlesByRequest, $referenceArticlesByRequest) {
                    $requestId = $transferOrder->getId();
                    $baseRow = $transferOrder->serialize();
                    $articles = $articlesByRequest[$requestId] ?? [];
                    $referenceArticles = $referenceArticlesByRequest[$requestId] ?? [];
                    if (!empty($articles) || !empty($referenceArticles)) {
                        return Stream::from($articles, $referenceArticles)
                            ->map(function ($article) use ($baseRow) {
                                return array_merge(
                                    $baseRow,
                                    [
                                        $article['reference'],
                                        $article['barCode']
                                    ]
                                );
                            })
                            ->toArray();
                    }
                    else {
                        return [$baseRow];
                    }
                }
            );
        }

        throw new BadRequestHttpException();
    }

}
