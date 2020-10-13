<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\CollecteReference;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\Article;
use App\Form\AddArticleToTransferType;
use App\Form\TransferRequestType;
use App\Service\RefArticleDataService;
use App\Service\TransferRequestService;
use DateTime;
use App\Service\CSVExportService;
use App\Service\DemandeCollecteService;
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
     * @Route("/liste/{filter}", name="transfer_request_index", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param string|null $filter
     * @return Response
     */
    public function index(Request $request, EntityManagerInterface $em, $filter = null): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
            return $this->redirectToRoute('access_denied');
        }

        $statusRepository = $em->getRepository(Statut::class);

        $transfer = new TransferRequest();
        $transfer->setRequester($this->getUser());

        $form = $this->createForm(TransferRequestType::class, $transfer);

        return $this->render('transfer/request/index.html.twig', [
            "new_transfer" => $form->createView(),
            'statuts' => $statusRepository->findByCategorieName(CategorieStatut::TRANSFER_REQUEST),
            'filterStatus' => $filter
        ]);
    }

    /**
     * @Route("/api", name="transfer_request_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response {
        if($request->isXmlHttpRequest()) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
                return $this->redirectToRoute('access_denied');
            }

            // cas d'un filtre statut depuis page d'accueil
            $filterStatus = $request->request->get('filterStatus');
            $data = $this->service->getDataForDatatable($request->request, $filterStatus);

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/creer", name="transfer_request_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return Response
     * @throws NonUniqueResultException
     */
    public function new(Request $request, EntityManagerInterface $em): Response {
        $statusRepository = $em->getRepository(Statut::class);
        $draft = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::DRAFT);

        $transfer = new TransferRequest();
        $transfer->setRequester($this->getUser());
        $transfer->setStatus($draft);

        $form = $this->createForm(TransferRequestType::class, $transfer);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            //TODO 2741: mettre en commun le numéro
            $transfer->setNumber("lol");
            $transfer->setCreationDate(new DateTime('now', new DateTimeZone('Europe/Paris')));

            $em->persist($transfer);
            $em->flush();

            return $this->json([
                "success" => true,
                "redirect" => $this->generateUrl("transfer_request_show", ["transfer" => $transfer->getId()])
            ]);
        }

        return $this->render('transfer/request/new.html.twig', [
            "new_transfer" => $form->createView(),
            "new_modal_open" => $form->isSubmitted() && !$form->isValid(),
        ]);
    }

    /**
     * @Route("/voir/{transfer}", name="transfer_request_show", options={"expose"=true}, methods={"GET", "POST"})
     * @param TransferRequest $transfer
     * @return Response
     */
    public function show(TransferRequest $transfer): Response {
        if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
            return $this->redirectToRoute('access_denied');
        }

        $form = $this->createForm(TransferRequestType::class, $transfer);

        return $this->render('transfer/request/show.html.twig', [
            'edit_transfer' => $form->createView(),
            'transfer' => $transfer,
            'modifiable' => $transfer->getStatus()->getNom() == TransferRequest::DRAFT,
            'detailsConfig' => $this->service->createHeaderDetailsConfig($transfer)
        ]);
    }

    /**
     * @Route("/modifier/{transfer}", name="transfer_request_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param TransferRequest $transfer
     * @return Response
     */
    public function edit(Request $request, EntityManagerInterface $em, TransferRequest $transfer): Response {
        $transfer->setRequester($this->getUser());

        $form = $this->createForm(TransferRequestType::class, $transfer);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($transfer);
            $em->flush();

            return $this->json([
                "success" => true,
                "redirect" => $this->generateUrl("transfer_request_show", ["transfer" => $transfer->getId()])
            ]);
        }

        return $this->render('transfer/request/edit.html.twig', [
            "edit_transfer" => $form->createView(),
            "edit_modal_open" => $form->isSubmitted() && !$form->isValid(),
        ]);
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
        if($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_TRANSFER_REQ)) {
                return $this->redirectToRoute('access_denied');
            }

            $articles = $transfer->getArticles();
            $references = $transfer->getReferences();

            $rowsRC = [];
            foreach($references as $reference) {
                $rowsRC[] = [
                    'Référence' => $reference->getReference(),
                    'Libellé' => $reference->getLibelle(),
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
                    'Libellé' => $article->getLabel(),
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
     * @Route("/", name="get_collecte_article_by_refArticle", options={"expose"=true})
     * @param Request $request
     * @param ReferenceArticle $reference
     * @return Response
     */
    public function getCollecteArticleByRefArticle(RefArticleDataService $service, ReferenceArticle $reference): Response {
        if ($reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            return $this->json([
                'modif' => $service->getViewEditRefArticle($reference, true),
                'selection' => $this->render('collecte/newRefArticleByQuantiteRefContent.html.twig'),
            ]);
        } elseif ($reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            return $this->json([
                'selection' => $this->render('collecte/newRefArticleByQuantiteRefContentTemp.html.twig'),
            ]);
        }

            return $this->json([
                "success" => false,
                "msg" => "Erreur interne"
            ]);
    }

    /**
     * @Route("/ajouter-article/{transfer}", name="transfer_request_add_article", options={"expose"=true})
     */
    public function addArticle(Request $request, TransferRequest $transfer): Response {
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

        if($article && $reference->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $transfer->addArticle($article);
            $manager->flush();
        } else if($reference->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
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
                'msg' => 'La référence a bien été supprimée de la collecte.'
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
     * @Route("/non-vide", name="transfer_request_has_articles", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function hasArticles(Request $request,
                                EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);

            $articles = $articleRepository->findByCollecteId($data['id']);
            $referenceCollectes = $collecteReferenceRepository->findByCollecte($data['id']);
            $count = count($articles) + count($referenceCollectes);

            return new JsonResponse($count > 0);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/autocomplete", name="get_demand_collect", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getDemandCollectAutoComplete(Request $request,
                                                 EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest()) {
            if(!$this->userService->hasRightFunction(Menu::DEM, Action::DISPLAY_DEM_COLL)) {
                return $this->redirectToRoute('access_denied');
            }

            $collecteRepository = $entityManager->getRepository(Collecte::class);

            $search = $request->query->get('term');

            $collectes = $collecteRepository->getIdAndLibelleBySearch($search);

            return new JsonResponse(['results' => $collectes]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/csv", name="get_demandes_collectes_for_csv",options={"expose"=true}, methods="GET|POST" )
     * @param EntityManagerInterface $entityManager
     * @param DemandeCollecteService $demandeCollecteService
     * @param Request $request
     * @param FreeFieldService $freeFieldService
     * @param CSVExportService $CSVExportService
     * @return Response
     * @throws Exception
     */
    public function getDemandesCollecteCSV(EntityManagerInterface $entityManager,
                                           DemandeCollecteService $demandeCollecteService,
                                           Request $request,
                                           FreeFieldService $freeFieldService,
                                           CSVExportService $CSVExportService): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
        $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');

        if(isset($dateTimeMin) && isset($dateTimeMax)) {
            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::DEMANDE_COLLECTE]);

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
                ],
                $freeFieldsConfig['freeFieldsHeader']
            );
            $today = new DateTime('now', new \DateTimeZone('Europe/Paris'));
            $fileName = "export_demande_collecte" . $today->format('d_m_Y') . ".csv";
            return $CSVExportService->createBinaryResponseFromData(
                $fileName,
                $collectes,
                $csvHeader,
                function(Collecte $collecte) use ($freeFieldsConfig, $freeFieldService, $demandeCollecteService) {
                    $rows = [];
                    foreach($collecte->getArticles() as $article) {
                        $rows[] = $demandeCollecteService->serialiseExportRow($collecte, $freeFieldsConfig, $freeFieldService, function() use ($article) {
                            return [
                                $article->getBarCode(),
                                $article->getQuantite()
                            ];
                        });
                    }

                    foreach($collecte->getCollecteReferences() as $collecteReference) {
                        $rows[] = $demandeCollecteService->serialiseExportRow($collecte, $freeFieldsConfig, $freeFieldService, function() use ($collecteReference) {
                            return [
                                $collecteReference->getReferenceArticle() ? $collecteReference->getReferenceArticle()->getBarCode() : '',
                                $collecteReference->getQuantite()
                            ];
                        });
                    }

                    return $rows;
                }
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

}
