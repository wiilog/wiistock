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
use App\Helper\FormatHelper;
use WiiCommon\Helper\Stream;
use App\Service\TransferRequestService;
use DateTime;
use App\Service\CSVExportService;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use WiiCommon\Helper\StringHelper;

/**
 * @Route("/transfert/demande")
 */
class TransferRequestController extends AbstractController {

    /**
     * @Route("/liste", name="transfer_request_index", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_TRANSFER_REQ})
     */
    public function index(EntityManagerInterface $entityManager): Response {

        $statusRepository = $entityManager->getRepository(Statut::class);

        return $this->render('transfer/request/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::TRANSFER_REQUEST),
        ]);
    }

    /**
     * @Route("/api", name="transfer_request_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_TRANSFER_REQ}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        TransferRequestService $transferRequestService): Response {

        $data = $transferRequestService->getDataForDatatable($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="transfer_request_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        TransferRequestService $transferRequestService,
                        EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);

            $destination = $emplacementRepository->find($data['destination']);
            $origin = $emplacementRepository->find($data['origin']);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $validComment = StringHelper::cleanedComment($data['comment']);
            $transfer = $transferRequestService->createTransferRequest($entityManager, $draft, $origin, $destination, $currentUser, $validComment);

            $entityManager->persist($transfer);

            try {
                $entityManager->flush();
            }
            /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Une autre demande de transfert est en cours de création, veuillez réessayer.'
                ]);
            }

            return new JsonResponse([
                'redirect' => $this->generateUrl('transfer_request_show', ['id' => $transfer->getId()]),
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/voir/{id}", name="transfer_request_show", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::DEM, Action::DISPLAY_TRANSFER_REQ})
     */
    public function show(TransferRequest $transfer,
                         TransferRequestService $transferRequestService): Response {

        return $this->render('transfer/request/show.html.twig', [
            'transfer' => $transfer,
            'modifiable' => FormatHelper::status($transfer->getStatus()) == TransferRequest::DRAFT,
            'detailsConfig' => $transferRequestService->createHeaderDetailsConfig($transfer)
        ]);
    }

    /**
     * @Route("/api-modifier", name="transfer_request_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $transfer = $entityManager->getRepository(TransferRequest::class)->find($data['id']);

            $json = $this->renderView('transfer/request/modalEditTransferContent.html.twig', [
                'transfer' => $transfer,
            ]);

            return new JsonResponse($json);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="transfer_request_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request                $request,
                         TransferRequestService $service,
                         EntityManagerInterface $entityManager): Response {

        if ($data = json_decode($request->getContent(), true)) {
            $destination = $entityManager->getRepository(Emplacement::class)->find($data['destination']);
            $origin = null;
            if (isset($data['origin'])) {
                $origin = $entityManager->getRepository(Emplacement::class)->find($data['origin']);
            }
            $transfer = $entityManager->getRepository(TransferRequest::class)->find($data['transfer']);
            $transfer
                ->setDestination($destination)
                ->setComment(StringHelper::cleanedComment($data['comment']));
            if ($origin) {
                $transfer->setOrigin($origin);
            }
            $entityManager->flush();

            return $this->json([
                'entete' => $this->renderView('transfer/request/show_header.html.twig', [
                    'transfer' => $transfer,
                    'modifiable' => $transfer->getStatus()?->getCode() == TransferRequest::DRAFT,
                    'showDetails' => $service->createHeaderDetailsConfig($transfer)
                ]),
                'success' => true,
                'msg' => 'La demande de transfert a bien été modifiée.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/article/api/{transfer}", name="transfer_request_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DISPLAY_TRANSFER_REQ}, mode=HasPermission::IN_JSON)
     */
    public function articleApi(TransferRequest $transfer): Response {

        $articles = $transfer->getArticles();
        $references = $transfer->getReferences();

        $rowsRC = [];
        foreach($references as $reference) {
            $rowsRC[] = [
                'Référence' => $reference->getReference(),
                'barCode' => $reference->getBarCode(),
                'Quantité' => $reference->getQuantiteDisponible(),
                'Actions' => $this->renderView('transfer/request/article/actions.html.twig', [
                    'type' => 'reference',
                    'id' => $reference->getId(),
                    'name' => $reference->getTypeQuantite(),
                    'refArticleId' => $reference->getId(),
                    'transferId' => $transfer->getid(),
                    'modifiable' => $transfer->getStatus()?->getCode() == TransferRequest::DRAFT,
                ]),
            ];
        }

        $rowsCA = [];
        foreach($articles as $article) {
            $rowsCA[] = [
                'Référence' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                'barCode' => $article->getBarCode(),
                'Quantité' => $article->getQuantite(),
                'Actions' => $this->renderView('transfer/request/article/actions.html.twig', [
                    'name' => ReferenceArticle::QUANTITY_TYPE_ARTICLE,
                    'type' => 'article',
                    'id' => $article->getId(),
                    'transferId' => $transfer->getid(),
                    'modifiable' => $transfer->getStatus()?->getCode() == TransferRequest::DRAFT,
                ]),
            ];
        }
        $data['data'] = array_merge($rowsCA, $rowsRC);

        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter-article/{transfer}", name="transfer_request_add_article", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addArticle(Request $request, EntityManagerInterface $manager,
                               TransferRequest $transfer): Response {

        if(!$content = json_decode($request->getContent())) {
            throw new BadRequestHttpException();
        }

        $reference = $content->reference;
        $reference = $manager->getRepository(ReferenceArticle::class)->find($reference);

        if(isset($content->article)) {
            $article = $manager->getRepository(Article::class)->find($content->article);
        } else {
            $article = null;
        }

        if(isset($content->fetchOnly) && $reference->getTypeQuantite() == ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            $locationLabel = $transfer->getOrigin()->getLabel();
            $articles = $manager
                ->getRepository(Article::class)
                ->findActiveOrDisputeForReference($reference, $transfer->getOrigin());

            if (!empty($articles)) {
                return $this->json([
                    "success" => true,
                    "html" => $this->renderView("transfer/request/article/select_article_form.html.twig", [
                        "articles" => $articles,
                        'articleAlreadyInRequest' => $transfer->getArticles()->map(function (Article $article) {
                            return $article->getId();
                        })
                    ])
                ]);
            } else {
                return $this->json([
                    "success" => false,
                    "msg" => "Aucun article lié à cette référence présent sur $locationLabel."
                ]);
            }
        }
        if(!isset($content->fetchOnly) && $article && $reference->getTypeQuantite() == ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
            if($transfer->getArticles()->contains($article)) {
                return $this->json([
                    "success" => false,
                    "msg" => 'Cet article est déjà présent dans la demande de transfert.'
                ]);
            }
            $transfer->addArticle($article);
            $manager->flush();
        } else if(!isset($content->fetchOnly) && $reference->getTypeQuantite() == ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            if($transfer->getReferences()->contains($reference)) {
                return $this->json([
                    "success" => false,
                    "msg" => 'Cette référence est déjà présente dans la demande de transfert.'
                ]);
            }
            $transfer->addReference($reference);
            $manager->flush();
        }

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/retirer-article", name="transfer_request_remove_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function removeArticle(Request $request, EntityManagerInterface $manager) {

        if($data = json_decode($request->getContent(), true)) {
            $transerRepository = $manager->getRepository(TransferRequest::class);
            $transfer = $transerRepository->find($data['transfer']);

            if(array_key_exists(ReferenceArticle::QUANTITY_TYPE_REFERENCE, $data)) {
                $transfer->removeReference($manager->find(ReferenceArticle::class, $data['reference']));
            } elseif(array_key_exists(ReferenceArticle::QUANTITY_TYPE_ARTICLE, $data)) {
                $transfer->removeArticle($manager->find(Article::class, $data['article']));
            }

            $manager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence a bien été supprimée de la demande de transfert.'
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="transfer_request_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::DEM, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response {

        if(!$data = json_decode($request->getContent())) {
            throw new BadRequestHttpException();
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
     * @Route("/non-vide/{id}", name="transfer_request_has_articles", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
    public function hasArticles(TransferRequest $transferRequest,
                                EntityManagerInterface $entityManager): Response {

        $count = $transferRequest->getArticles()->count() + $transferRequest->getReferences()->count();

        if ($transferRequest->getStatus()?->getCode() !== TransferRequest::DRAFT) {
            $transferOrderRepository = $entityManager->getRepository(TransferOrder::class);
            $transferOrder = $transferOrderRepository->findOneBy(['request' => $transferRequest]);

            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('transfer_order_show', [
                    'id' => $transferOrder->getId()
                ]),
            ]);
        }

        if($count > 0) {
            return $this->redirectToRoute('transfer_order_new', [
                'transferRequest' => $transferRequest->getId()
            ]);
        } else {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Aucun article dans la demande de transfert.'
            ]);
        }
    }

    /**
     * @Route("/csv", name="transfer_request_export",options={"expose"=true}, methods="GET|POST" )
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
            $now = (new DateTime('now'))->format("d-m-Y-H-i-s");

            $transferRequestRepository = $entityManager->getRepository(TransferRequest::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $transfers = $transferRequestRepository->findByDates($dateTimeMin, $dateTimeMax);
            $articlesByRequest = $articleRepository->getArticlesGroupedByTransfer($transfers);
            $referenceArticlesByRequest = $referenceArticleRepository->getReferenceArticlesGroupedByTransfer($transfers);

            $header = [
                "numéro demande",
                "statut",
                "demandeur",
                "origine",
                "destination",
                "date de création",
                "date de validation",
                "commentaire",
                "référence",
                "code barre"
            ];

            return $CSVExportService->createBinaryResponseFromData(
                "export_demande_transfert_$now.csv",
                $transfers,
                $header,
                function (TransferRequest $transferRequest) use ($articlesByRequest, $referenceArticlesByRequest) {
                    $requestId = $transferRequest->getId();
                    $baseRow = $transferRequest->serialize();
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
