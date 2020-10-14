<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\FreeField;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\CollecteReference;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Article;
use App\Service\RefArticleDataService;
use App\Service\TransferOrderService;
use App\Service\TransferRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use App\Service\FreeFieldService;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

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

            $toTreat = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_ORDER, TransferOrder::TO_TREAT);
            $transfer = new TransferOrder();

            $transfer
                ->setNumber($this->createNumber($entityManager, $date))
                ->setCreationDate($date)
                ->setRequest($transferRequest)
                ->setStatus($toTreat);
            $entityManager->persist($transfer);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('transfer_order_show', ['transfer' => $transfer->getId()]),
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/voir/{transfer}", name="transfer_order_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param TransferOrder $transfer
     * @return Response
     */
    public function show(TransferOrder $transfer): Response {
        if(!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_TRANS)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('transfer/order/show.html.twig', [
            'transfer' => $transfer,
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
     * @Route("/supprimer", name="transfer_order_delete", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response {
        if(!$request->isXmlHttpRequest() || !$data = json_decode($request->getContent())) {
            throw new BadRequestHttpException();
        }

        if(!$this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
            return $this->redirectToRoute('access_denied');
        }

        $transferRepository = $entityManager->getRepository(TransferRequest::class);

        $transfer = $transferRepository->find($data->transfer);
        $entityManager->remove($transfer);
        $entityManager->flush();

        return $this->json([
            'redirect' => $this->generateUrl('transfer_request_index'),
        ]);
    }

    /**
     * @Route("/csv", name="get_transfer_requests_for_csv",options={"expose"=true}, methods="GET|POST" )
     * @param EntityManagerInterface $entityManager
     * @param TransferRequestService $transferRequestService
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @return Response
     * @throws Exception
     */
    public function getTransferRequestsCSV(EntityManagerInterface $entityManager,
                                           TransferRequestService $transferRequestService,
                                           Request $request,
                                           CSVExportService $CSVExportService): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        if(isset($dateTimeMin) && isset($dateTimeMax)) {

            $collecteRepository = $entityManager->getRepository(Collecte::class);
            $collectes = $collecteRepository->findByDates($dateTimeMin, $dateTimeMax);

            $csvHeader = array_merge(
                [
                    'Numero demande',
                    'Date de création',
                    'Date de validation',
                    'Type',
                    'Statut',
                    'Sujet',
                    'Stock ou destruction',
                    'Demandeur',
                    'Point de collecte',
                    'Commentaire',
                    'Code barre',
                    'Quantité',
                ]
            );
            $today = new DateTime('now', new \DateTimeZone('Europe/Paris'));
            $fileName = "export_demande_collecte" . $today->format('d_m_Y') . ".csv";
            return $CSVExportService->createBinaryResponseFromData(
                $fileName,
                $collectes,
                $csvHeader,
                function(Collecte $collecte) use ($transferRequestService) {
                    $rows = [];
                    foreach($collecte->getArticles() as $article) {

                    }

                    foreach($collecte->getCollecteReferences() as $collecteReference) {

                    }

                    return $rows;
                }
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

}
