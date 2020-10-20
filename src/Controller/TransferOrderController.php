<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Article;
use App\Service\MouvementStockService;
use App\Service\TransferOrderService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/transfert/ordres")
 */
class TransferOrderController extends AbstractController {

    private $userService;
    private $service;

    public function __construct(UserService $us, TransferOrderService $service) {
        $this->userService = $us;
        $this->service = $service;
    }

    /**
     * @Route("/liste/{filter}", name="transfer_order_index", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param string|null $filter
     * @return Response
     */
    public function index(Request $request, EntityManagerInterface $em, $filter = null): Response {
        if(!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS)) {
            return $this->redirectToRoute('access_denied');
        }

        $statusRepository = $em->getRepository(Statut::class);

        $transfer = new TransferRequest();
        $transfer->setRequester($this->getUser());

        return $this->render('transfer/order/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::TRANSFER_ORDER),
            'filterStatus' => $filter
        ]);
    }

    /**
     * @Route("/api", name="transfer_orders_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @return Response
     */
    public function api(Request $request): Response {
        if($request->isXmlHttpRequest()) {
            if(!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->service->getDataForDatatable($request->request);

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    private function createNumber($entityManager, $date) {
        $dateStr = $date->format('Ymd');

        $lastDispatchNumber = $entityManager->getRepository(TransferOrder::class)->getLastTransferNumberByPrefix("T-" . $dateStr);

        if ($lastDispatchNumber) {
            $lastCounter = (int) substr($lastDispatchNumber, -4, 4);
            $currentCounter = ($lastCounter + 1);
        } else {
            $currentCounter = 1;
        }

        $currentCounterStr = (
        $currentCounter < 10 ? ('000' . $currentCounter) :
            ($currentCounter < 100 ? ('00' . $currentCounter) :
                ($currentCounter < 1000 ? ('0' . $currentCounter) :
                    $currentCounter))
        );

        return ("T-" . $dateStr . $currentCounterStr);
    }

    /**
     * @Route("/creer{id}", name="transfer_order_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TransferRequest $transferRequest
     * @return Response
     * @throws NonUniqueResultException
     */
    public function new(Request $request, EntityManagerInterface $entityManager, TransferRequest $transferRequest): Response {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);

            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $toTreatOrder = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_ORDER, TransferOrder::TO_TREAT);
            $toTreatRequest = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::TO_TREAT);
            $transfer = new TransferOrder();

            $transitStatusForArticles = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::ARTICLE,
                Article::STATUT_EN_TRANSIT
            );
            $transitStatusForRefs = $statutRepository->findOneByCategorieNameAndStatutCode(
                CategorieStatut::REFERENCE_ARTICLE,
                ReferenceArticle::STATUT_INACTIF
            );

            foreach ($transferRequest->getReferences() as $reference) {
                $reference->setStatut($transitStatusForRefs);
            }

            foreach ($transferRequest->getArticles() as $article) {
                $article->setStatut($transitStatusForArticles);
            }

            $transferRequest->setStatus($toTreatRequest);
            $transferRequest->setValidationDate(new DateTime());

            $transfer
                ->setNumber($this->createNumber($entityManager, $date))
                ->setCreationDate($date)
                ->setRequest($transferRequest)
                ->setStatus($toTreatOrder);
            $entityManager->persist($transfer);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('transfer_order_show', ['id' => $transfer->getId()]),
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/voir/{id}", name="transfer_order_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param TransferOrder $transfer
     * @return Response
     */
    public function show(TransferOrder $transfer): Response {
        if(!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('transfer/order/show.html.twig', [
            'order' => $transfer,
            'detailsConfig' => $this->service->createHeaderDetailsConfig($transfer),
            'modifiable' => $transfer->getStatus()->getNom() === TransferOrder::TO_TREAT
        ]);
    }

    /**
     * @Route("/article/api/{transfer}", name="transfer_order_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param TransferOrder $transfer
     * @return Response
     */
    public function articleApi(Request $request,
                               TransferOrder $transfer): Response {
        if($request->isXmlHttpRequest()) {
            if(!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS)) {
                return $this->redirectToRoute('access_denied');
            }

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
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/valider/{id}", name="transfer_order_validate", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TransferOrderService $transferOrderService
     * @param TransferOrder $transferOrder
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function finish(Request $request,
                           EntityManagerInterface $entityManager,
                           TransferOrderService $transferOrderService,
                           TransferOrder $transferOrder): Response {
        if(!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        if(!$this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
            return $this->redirectToRoute('access_denied');
        }

        $request = $transferOrder->getRequest();

        $statutRepository = $entityManager->getRepository(Statut::class);

        $treatedRequest = $statutRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::TREATED);

        $treatedOrder = $statutRepository
            ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_ORDER, TransferRequest::TREATED);

        $request->setStatus($treatedRequest);
        $transferOrder->setStatus($treatedOrder);
        $transferOrder->setOperator($this->getUser());
        $transferOrder->setTransferDate(new DateTime());

        $locationTo = $request->getDestination();
        $transferOrderService->releaseRefsAndArticles($locationTo, $transferOrder, $this->getUser(), $entityManager, true);

        foreach($request->getArticles() as $article) {
            $article->setEmplacement($request->getDestination());
        }

        foreach($request->getReferences() as $reference) {
            $reference->setEmplacement($request->getDestination());
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'redirect' => $this->generateUrl('transfer_order_show', [
                'id' => $transferOrder->getId()
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="transfer_order_delete", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param MouvementStockService $mouvementStockService
     * @param TransferOrderService $transferOrderService
     * @param TransferOrder $transferOrder
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           MouvementStockService $mouvementStockService,
                           TransferOrderService $transferOrderService,
                           TransferOrder $transferOrder): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);

            $draftRequest = $statutRepository
                ->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);

            $transferOrder->getRequest()->setStatus($draftRequest);
            $transferOrder->getRequest()->setValidationDate(null);

            $locationsRepository = $entityManager->getRepository(Emplacement::class);

            if (isset($data['destination'])) {
                $locationTo = $locationsRepository->find($data['destination']);
            } else {
                $locationTo = null;
            }

            $transferOrderService->releaseRefsAndArticles($locationTo, $transferOrder, $this->getUser(), $entityManager);

            foreach ($transferOrder->getStockMovements() as $mouvementStock) {
                $mouvementStockService->manageMouvementStockPreRemove($mouvementStock, $entityManager);
                $entityManager->flush();
                $transferOrder->removeStockMovement($mouvementStock);
                $entityManager->remove($mouvementStock);
            }

            $request = $transferOrder->getRequest();
            $requestId = $transferOrder->getRequest()->getId();

            foreach($request->getArticles() as $article) {
                $article->setEmplacement($locationTo);
            }

            foreach($request->getReferences() as $reference) {
                $reference->setEmplacement($locationTo);
            }

            $entityManager->remove($transferOrder);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'redirect' => $this->generateUrl('transfer_request_show', [
                    'transfer' => $requestId
                ])
            ]);
        }
        throw new BadRequestHttpException();
    }
    /**
     * @Route("/csv", name="transfer_order_export",options={"expose"=true}, methods="GET|POST" )
     */
    public function export(Request $request, EntityManagerInterface $entityManager, CSVExportService $CSVExportService): Response {
        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $from = DateTime::createFromFormat("Y-m-d H:i:s", $dateMin . " 00:00:00");
        $to = DateTime::createFromFormat("Y-m-d H:i:s", $dateMax . " 23:59:59");

        if(isset($from, $to)) {
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));

            $transfers = $entityManager->getRepository(TransferOrder::class)->findBetween($from, $to);

            $header = [
                "Numéro demande",
                "Numéro ordre",
                "Statut",
                "Demandeur",
                "Opérateur",
                "Destination",
                "Date de création",
                "Date de transfert",
                "Commentaire",
            ];

            return $CSVExportService->createBinaryResponseFromData(
                "export_ordre_transfert" . $now->format("d_m_Y") . ".csv",
                $transfers,
                $header,
                CSVExportService::$SERIALIZABLE
            );
        }

        throw new BadRequestHttpException();
    }

}
