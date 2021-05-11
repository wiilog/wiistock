<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\PurchaseRequest;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Article;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use App\Service\PurchaseRequestService;
use App\Service\TransferRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * @Route("/achat/demande")
 */
class PurchaseRequestController extends AbstractController
{

    private $userService;
    private $service;

    public function __construct(UserService $us, PurchaseRequestService $service) {
        $this->userService = $us;
        $this->service = $service;
    }

    /**
     * @Route("/liste", name="purchase_request_index")
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS)) {
            return $this->redirectToRoute('access_denied');
        }

        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('purchase_request/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::PURCHASE_REQUEST),
        ]);
    }

    /**
     * @Route("/api", name="purchase_request_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @return Response
     */
    public function api(Request $request): Response {
        if($request->isXmlHttpRequest()) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->service->getDataForDatatable($request->request);

            return new JsonResponse($data);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/voir/{id}", name="purchase_request_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param PurchaseRequest $request
     * @return Response
     */
    public function show(PurchaseRequest $request): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_PURCHASE_REQUESTS)) {
            return $this->redirectToRoute('access_denied');
        }

        /*return $this->render('transfer/request/show.html.twig', [
            'transfer' => $transfer,
            'modifiable' => FormatHelper::status($transfer->getStatus()) == TransferRequest::DRAFT,
            'detailsConfig' => $this->service->createHeaderDetailsConfig($transfer)
        ]);*/
    }

    /**
     * @Route("/csv", name="purchase_request_export",options={"expose"=true}, methods="GET|POST" )
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
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));

            $purchaseRequestRepository = $entityManager->getRepository(PurchaseRequest::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $requests = $purchaseRequestRepository->findByDates($dateTimeMin, $dateTimeMax);
            $articlesByRequest = $articleRepository->getArticlesGroupedByTransfer($requests);
            $referenceArticlesByRequest = $referenceArticleRepository->getReferenceArticlesGroupedByTransfer($requests);

            $header = [
                "numéro demande",
                "statut",
                "demandeur",
                "acheteur",
                "date de création",
                "date de validation",
                "date de traitement",
                "date de prise en compte",
                "commentaire",
            ];

            return $CSVExportService->createBinaryResponseFromData(
                "export_demande_achat" . $now->format("d_m_Y") . ".csv",
                $requests,
                $header,
                function (PurchaseRequest $request) use ($articlesByRequest, $referenceArticlesByRequest) {
                    $requestId = $request->getId();
                    $baseRow = $request->serialize();
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
