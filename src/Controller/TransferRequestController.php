<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Article;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\TransferRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\UserService;

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

/**
 * @Route("/transfert/demande")
 */
class TransferRequestController extends AbstractController {

    private $userService;
    private $service;

    public function __construct(UserService $us, TransferRequestService $service) {
        $this->userService = $us;
        $this->service = $service;
    }

    /**
     * @Route("/liste", name="transfer_request_index", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function index(EntityManagerInterface $em): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
            return $this->redirectToRoute('access_denied');
        }

        $statusRepository = $em->getRepository(Statut::class);

        $transfer = new TransferRequest();

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $transfer->setRequester($currentUser);

        return $this->render('transfer/request/index.html.twig', [
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::TRANSFER_REQUEST),
        ]);
    }

    /**
     * @Route("/api", name="transfer_request_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @return Response
     */
    public function api(Request $request): Response {
        if($request->isXmlHttpRequest()) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->service->getDataForDatatable($request->request);

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    public static function createNumber($entityManager, $date) {
        $dateStr = $date->format('Ymd');

        $transferRequestRepository = $entityManager->getRepository(TransferRequest::class);
        $lastDispatchNumber = $transferRequestRepository->getLastTransferNumberByPrefix(TransferRequest::NUMBER_PREFIX . "-" . $dateStr);

        if($lastDispatchNumber) {
            $lastCounter = (int)substr($lastDispatchNumber, -4, 4);
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

        return (TransferRequest::NUMBER_PREFIX . "-" . $dateStr . $currentCounterStr);
    }

    /**
     * @Route("/creer", name="transfer_request_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));

            $draft = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);
            $transfer = new TransferRequest();
            $destination = $emplacementRepository->find($data['destination']);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $transfer
                ->setStatus($draft)
                ->setNumber(self::createNumber($entityManager, $date))
                ->setDestination($destination)
                ->setCreationDate($date)
                ->setRequester($currentUser)
                ->setComment($data['comment']);
            $entityManager->persist($transfer);
            $entityManager->flush();

            return new JsonResponse([
                'redirect' => $this->generateUrl('transfer_request_show', ['id' => $transfer->getId()]),
            ]);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/voir/{id}", name="transfer_request_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param TransferRequest $transfer
     * @return Response
     */
    public function show(TransferRequest $transfer): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('transfer/request/show.html.twig', [
            'transfer' => $transfer,
            'modifiable' => FormatHelper::status($transfer->getStatus()) == TransferRequest::DRAFT,
            'detailsConfig' => $this->service->createHeaderDetailsConfig($transfer)
        ]);
    }

    /**
     * @Route("/api-modifier", name="transfer_request_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $transfer = $entityManager->getRepository(TransferRequest::class)->find($data['id']);

            $json = $this->renderView('transfer/request/modalEditTransferContent.html.twig', [
                'transfer' => $transfer,
            ]);

            return new JsonResponse($json);
        }

        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="transfer_request_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param TransferRequestService $service
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         TransferRequestService $service,
                         EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $destination = $entityManager->getRepository(Emplacement::class)->find($data['destination']);

            $transfer = $entityManager->getRepository(TransferRequest::class)->find($data['transfer']);
            $transfer
                ->setDestination($destination)
                ->setComment($data['comment']);

            $entityManager->flush();

            return $this->json([
                'entete' => $this->renderView('transfer/request/show_header.html.twig', [
                    'transfer' => $transfer,
                    'modifiable' => ($transfer->getStatus()->getNom() == TransferRequest::DRAFT),
                    'showDetails' => $service->createHeaderDetailsConfig($transfer)
                ]),
                'success' => true,
                'msg' => 'La demande de transfert a bien été modifiée.'
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/article/api/{transfer}", name="transfer_request_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param TransferRequest $transfer
     * @return Response
     */
    public function articleApi(EntityManagerInterface $entityManager,
                               Request $request,
                               TransferRequest $transfer): Response {
        if($request->isXmlHttpRequest()) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
                return $this->redirectToRoute('access_denied');
            }

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
                        'modifiable' => ($transfer->getStatus()->getNom() == TransferRequest::DRAFT),
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
                        'name' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
                        'type' => 'article',
                        'id' => $article->getId(),
                        'transferId' => $transfer->getid(),
                        'modifiable' => ($transfer->getStatus()->getNom() == TransferRequest::DRAFT),
                    ]),
                ];
            }
            $data['data'] = array_merge($rowsCA, $rowsRC);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajouter-article/{transfer}", name="transfer_request_add_article", options={"expose"=true})
     * @param Request $request
     * @param TransferRequest $transfer
     * @return Response
     */
    public function addArticle(Request $request,
                               TransferRequest $transfer): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if(!$content = json_decode($request->getContent())) {
            throw new BadRequestHttpException();
        }

        $manager = $this->getDoctrine()->getManager();

        $reference = $content->reference;
        $reference = $manager->getRepository(ReferenceArticle::class)->find($reference);

        if(isset($content->article)) {
            $article = $manager->getRepository(Article::class)->find($content->article);
        } else {
            $article = null;
        }

        if(isset($content->fetchOnly) && $reference->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $articles = $this->getDoctrine()
                ->getRepository(Article::class)
                ->findForReferenceWithoutTransfer($reference);

            return $this->json([
                "success" => true,
                "html" => $this->renderView("transfer/request/article/select_article_form.html.twig", [
                    "articles" => $articles
                ])
            ]);
        }
        if(!isset($content->fetchOnly) && $article && $reference->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            if($transfer->getArticles()->contains($article)) {
                return $this->json([
                    "success" => false,
                    "msg" => 'Cet article est déjà présent dans la demande de transfert.'
                ]);
            }
            $transfer->addArticle($article);
            $manager->flush();
        } else if(!isset($content->fetchOnly) && $reference->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
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
     * @Route("/retirer-article", name="transfer_request_remove_article", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $manager
     * @return JsonResponse|RedirectResponse
     */
    public function removeArticle(Request $request, EntityManagerInterface $manager) {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent())) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $transerRepository = $manager->getRepository(TransferRequest::class);
            $transfer = $transerRepository->find($data->transfer);

            if(array_key_exists(ReferenceArticle::TYPE_QUANTITE_REFERENCE, $data)) {
                $transfer->removeReference($manager
                    ->getRepository(ReferenceArticle::class)
                    ->find($data->reference));
            } elseif(array_key_exists(ReferenceArticle::TYPE_QUANTITE_ARTICLE, $data)) {
                $transfer->removeArticle($manager
                    ->getRepository(Article::class)
                    ->find($data->article));
            }

            $manager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence a bien été supprimée de la demande de transfert.'
            ]);
        }

        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="transfer_request_delete", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response {
        if(!$request->isXmlHttpRequest() || !$data = json_decode($request->getContent())) {
            throw new BadRequestHttpException();
        }

        if(!$this->userService->hasRightFunction(Menu::DEM, Action::DELETE)) {
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
     * @Route("/non-vide/{id}", name="transfer_request_has_articles", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param TransferRequest $transferRequest
     * @return Response
     */
    public function hasArticles(Request $request,
                                TransferRequest $transferRequest): Response {
        if($request->isXmlHttpRequest()) {
            $count = $transferRequest->getArticles()->count() + $transferRequest->getReferences()->count();

            if($count > 0) {
                return $this->redirectToRoute('transfer_order_new', [
                    'id' => $transferRequest->getId()
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Aucun article dans la demande de transfert.'
                ]);
            }
        }
        throw new NotFoundHttpException('404');
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
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));

            $transfers = $entityManager->getRepository(TransferRequest::class)->getByDates($dateTimeMin, $dateTimeMax);

            $header = [
                "numéro demande",
                "statut",
                "demandeur",
                "destination",
                "date de création",
                "date de validation",
                "commentaire",
                "référence",
                "code barre"
            ];

            return $CSVExportService->createBinaryResponseFromData(
                "export_demande_transfert" . $now->format("d_m_Y") . ".csv",
                $transfers,
                $header,
                function ($transferRequest) {
                    $row = [];
                    $row[] = $transferRequest['number'] ?? '';
                    $row[] = $transferRequest['status'] ?? '';
                    $row[] = $transferRequest['requester'] ?? '';
                    $row[] = $transferRequest['destination'] ?? '';
                    $row[] = $transferRequest['creationDate'] ? $transferRequest['creationDate']->format('d/m/Y H:i:s') : '';
                    $row[] = $transferRequest['validationDate'] ? $transferRequest['validationDate']->format('d/m/Y H:i:s') : '';
                    $row[] = $transferRequest['comment'] ? strip_tags($transferRequest['comment']) : '';
                    $row[] = $transferRequest['reference'] ?? '';
                    $row[] = $transferRequest['barcode'] ?? '';

                    return [$row];
                }
            );
        }

        throw new BadRequestHttpException();
    }

}
