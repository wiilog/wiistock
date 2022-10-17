<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Menu;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;

use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\DemandeCollecteService;
use App\Service\NotificationService;
use App\Service\OrdreCollecteService;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use App\Service\UserService;

use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * @Route("/ordre-collecte")
 */
class OrdreCollecteController extends AbstractController
{
    /**
     * @Route("/liste/{demandId}", name="ordre_collecte_index")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_COLL})
     */
    public function index(EntityManagerInterface $entityManager, string $demandId = null)
    {
        $collecteRepository = $entityManager->getRepository(Collecte::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $demandeCollecte = $demandId ? $collecteRepository->find($demandId) : null;

        return $this->render('ordre_collecte/index.html.twig', [
            'filterDemandId' => $demandeCollecte ? $demandId : null,
            'filterDemandValue' => $demandeCollecte ? $demandeCollecte->getNumero() : null,
            'filtersDisabled' => isset($demandeCollecte),
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ORDRE_COLLECTE),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]),
        ]);
    }

    /**
     * @Route("/api", name="ordre_collecte_api", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_COLL}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, OrdreCollecteService $ordreCollecteService): Response
    {
        // cas d'un filtre par demande de collecte
        $filterDemand = $request->request->get('filterDemand');
        $data = $ordreCollecteService->getDataForDatatable($request->request, $filterDemand);

        return new JsonResponse($data);
    }

    /**
     * @Route("/voir/{id}", name="ordre_collecte_show",  methods={"GET","POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_COLL})
     */
    public function show(OrdreCollecte $ordreCollecte,
                         OrdreCollecteService $ordreCollecteService): Response
    {
        return $this->render('ordre_collecte/show.html.twig', [
            'collecte' => $ordreCollecte,
            'finished' => $ordreCollecte->getStatut()?->getCode() === OrdreCollecte::STATUT_TRAITE,
            'detailsConfig' => $ordreCollecteService->createHeaderDetailsConfig($ordreCollecte),
        ]);
    }

    /**
     * @Route("/finir/{id}", name="ordre_collecte_finish", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finish(Request $request,
                           OrdreCollecte $ordreCollecte,
                           OrdreCollecteService $ordreCollecteService): Response
    {
        $rows = $request->request->all('rows');
        if (!empty($rows) && $ordreCollecte->getStatut()?->getCode() === OrdreCollecte::STATUT_A_TRAITER) {

            $date = new DateTime('now');

            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            try {
                $ordreCollecteService->finishCollecte($ordreCollecte, $loggedUser, $date, $rows);

                $data = [
                    'success' => true,
                    'entete' => $this->renderView('ordre_collecte/ordre-collecte-show-header.html.twig', [
                        'collecte' => $ordreCollecte,
                        'finished' => $ordreCollecte->getStatut()?->getCode() === OrdreCollecte::STATUT_TRAITE,
                        'showDetails' => $ordreCollecteService->createHeaderDetailsConfig($ordreCollecte)
                    ])
                ];
            }
            catch(ArticleNotAvailableException $exception) {
                $data = [
                    'success' => false,
                    'msg' => 'Une référence de la collecte n\'est pas active, vérifiez les transferts de stock en cours associés à celle-ci.'
                ];
            }
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-article/{id}", name="ordre_collecte_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_ORDRE_COLL}, mode=HasPermission::IN_JSON)
     */
    public function apiArticle(Request $request, OrdreCollecte $ordreCollecte): Response
    {
        $rows = [];
        $isDestruct = $ordreCollecte->getDemandeCollecte()->isDestruct();
        foreach ($ordreCollecte->getOrdreCollecteReferences() as $ligneArticle) {
            $referenceArticle = $ligneArticle->getReferenceArticle();
            $location = $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '';
            $rows[] = [
                "Référence" => $referenceArticle ? $referenceArticle->getReference() : ' ',
                "Libellé" => $referenceArticle ? $referenceArticle->getLibelle() : ' ',
                "Emplacement" => $location,
                "Quantité" => $ligneArticle->getQuantite() ?? ' ',
                "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                    'id' => $ligneArticle->getId(),
                    'refArticleId' => $referenceArticle->getId(),
                    'barCode' => $referenceArticle ? $referenceArticle->getBarCode() : '',
                    'quantity' => $ligneArticle->getQuantite(),
                    'modifiable' => $ordreCollecte->getStatut()?->getCode() === OrdreCollecte::STATUT_A_TRAITER,
                    'location' => $location,
                    'byArticle' => $referenceArticle->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE,
                    'isDestruct' => $isDestruct
                ])
            ];
        }

        foreach ($ordreCollecte->getArticles() as $article) {
            $location = $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '';
            $rows[] = [
                'Référence' => $article->getArticleFournisseur()
                    ? $article->getArticleFournisseur()->getReferenceArticle()->getReference()
                    : '',
                'Libellé' => $article->getLabel(),
                "Emplacement" => $location,
                'Quantité' => $article->getQuantite(),
                "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                    'id' => $article->getId(),
                    'barCode' => $article->getBarCode(),
                    'quantity' => $article->getQuantite(),
                    'modifiable' => $ordreCollecte->getStatut()?->getCode() === OrdreCollecte::STATUT_A_TRAITER,
                    'articleId' =>$article->getId(),
                    "location" => $location,
                    'byArticle' => false,
                    'isDestruct' => $isDestruct
                ])
            ];
        }

        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer/{id}", name="ordre_collecte_new", options={"expose"=true}, methods={"GET","POST"} )
     * @HasPermission({Menu::ORDRE, Action::CREATE})
     */
    public function new(Collecte $demandeCollecte, EntityManagerInterface $entityManager, NotificationService $notificationService): Response
    {
        $statutRepository = $entityManager->getRepository(Statut::class);
        // on crée l'ordre de collecte
        $statut = $statutRepository
            ->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER);
        $ordreCollecte = new OrdreCollecte();
        $date = new DateTime('now');
        $ordreCollecte
            ->setDate($date)
            ->setNumero('C-' . $date->format('YmdHis'))
            ->setStatut($statut)
            ->setDemandeCollecte($demandeCollecte);
        foreach ($demandeCollecte->getArticles() as $article) {
            $ordreCollecte->addArticle($article);
        }
        foreach ($demandeCollecte->getCollecteReferences() as $collecteReference) {
            $ordreCollecteReference = new OrdreCollecteReference();
            $ordreCollecteReference
                ->setOrdreCollecte($ordreCollecte)
                ->setQuantite($collecteReference->getQuantite())
                ->setReferenceArticle($collecteReference->getReferenceArticle());
            $entityManager->persist($ordreCollecteReference);
            $ordreCollecte->addOrdreCollecteReference($ordreCollecteReference);
        }

        $entityManager->persist($ordreCollecte);

        // on modifie statut + date validation de la demande
        $demandeCollecte
            ->setStatut(
            	$statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER)
			)
            ->setValidationDate($date);

        try {
            $entityManager->flush();
            if ($ordreCollecte->getDemandeCollecte()->getType()->isNotificationsEnabled()) {
                $notificationService->toTreat($ordreCollecte);
            }
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            $this->addFlash('danger', 'Un autre ordre de collecte est en cours de création, veuillez réessayer.');
        }

        return $this->redirectToRoute('collecte_show', [
            'id' => $demandeCollecte->getId(),
        ]);
    }

    /**
     * @Route("/modifier-article-api", name="ordre_collecte_edit_api", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEditArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $ordreCollecteReferenceRepository = $entityManager->getRepository(OrdreCollecteReference::class);
            $ligneArticle = $ordreCollecteReferenceRepository->find($data['id']);
            $modif = isset($data['ref']) && !($data['ref'] === 0);

            $json = $this->renderView(
                'ordre_collecte/modalEditArticleContent.html.twig',
                [
                    'ligneArticle' => $ligneArticle,
                    'modifiable' => $modif
                ]
            );
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-article", name="ordre_collecte_edit_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editArticle(Request $request,
                                UserService $userService,
                                EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $ordreCollecteReferenceRepository = $entityManager->getRepository(OrdreCollecteReference::class);
            $ligneArticle = $ordreCollecteReferenceRepository->find($data['ligneArticle']);
            if (isset($data['quantite'])) $ligneArticle->setQuantite(max($data['quantite'], 0)); // protection contre quantités négatives

            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajouter-article", name="ordre_collecte_add_article", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addArticle(Request $request,
                                DemandeCollecteService $collecteService,
                                EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $reference = $referenceArticleRepository->find($data['referenceArticle']);
            $order = $ordreCollecteRepository->find($data['collecte']);

            $demande = $order->getDemandeCollecte();
            $collecteService->persistArticleInDemand($data, $reference, $demande, $order);

            /**
             * @var OrdreCollecteReference $orderLine
             */
            $orderLine = $order
                ->getOrdreCollecteReferences()
                ->filter(fn(OrdreCollecteReference $ordreCollecteReference) => $ordreCollecteReference->getReferenceArticle() === $reference)
                ->first();

            if ($orderLine) {
                $newQuantity = max($orderLine->getQuantite() - $data['quantity-to-pick'], 0);
                $orderLine->setQuantite($newQuantity);
                if ($newQuantity === 0) {
                    $entityManager->remove($orderLine);
                }
            }

            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer/{id}", name="ordre_collecte_delete", options={"expose"=true}, methods={"GET","POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(OrdreCollecte $ordreCollecte, EntityManagerInterface $entityManager): Response
    {
        if ($ordreCollecte->getStatut()?->getCode() === OrdreCollecte::STATUT_A_TRAITER) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);
            $collecte = $ordreCollecte->getDemandeCollecte();
            $isOnlyOrdreCollecte = $collecte->getOrdresCollecte()->count() === 1;

            $statusName = $isOnlyOrdreCollecte ? Collecte::STATUT_BROUILLON : Collecte::STATUT_COLLECTE;
            $collecte->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_COLLECTE, $statusName));

            foreach ($ordreCollecte->getArticles() as $article) {
                if (!$isOnlyOrdreCollecte) {
                    $article->removeCollecte($collecte);
                }
                $article->removeOrdreCollecte($ordreCollecte);
            }
            foreach ($ordreCollecte->getOrdreCollecteReferences() as $cr) {
                if (!$isOnlyOrdreCollecte) {
                    $entityManager->remove($collecteReferenceRepository->getByCollecteAndRA($collecte, $cr->getReferenceArticle()));
                }
                $entityManager->remove($cr);
            }

            $collecte
                ->setValidationDate(null);

            $entityManager->remove($ordreCollecte);
            $entityManager->flush();
            $data = [
                'redirect' => $this->generateUrl('ordre_collecte_index'),
            ];

            return new JsonResponse($data);
        } else {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Erreur lors de la suppression de l\'ordre de collecte.',
            ]);
        }
    }

    /**
     * @Route("/csv", name="get_collect_orders_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param OrdreCollecteService $ordreCollecteService
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return Response
     */
    public function getCollectOrdersCSV(Request $request,
                                        OrdreCollecteService $ordreCollecteService,
                                        EntityManagerInterface $entityManager,
                                        CSVExportService $CSVExportService): Response {

        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');
        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }
        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $CSVheader = [
                'numéro',
                'statut',
                'date création',
                'opérateur',
                'type',
                'référence',
                'libellé',
                'emplacement',
                'quantité à collecter',
                'code-barre',
                'destination'
            ];

            $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
            $collecteIterator = $ordreCollecteRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            return $CSVExportService->streamResponse(
                function ($output) use ($collecteIterator, $CSVExportService, $ordreCollecteService) {
                    foreach ($collecteIterator as $collectOrder) {
                        $ordreCollecteService->putCollecteLine($output, $CSVExportService, $collectOrder);
                    }
                },'Export_Ordres_Collectes.csv',
                $CSVheader
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/{ordreCollecte}/etiquettes", name="collecte_bar_codes_print", options={"expose"=true})
     *
     * @param OrdreCollecte $ordreCollecte
     * @param RefArticleDataService $refArticleDataService
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printCollecteBarCodes(OrdreCollecte $ordreCollecte,
                                          RefArticleDataService $refArticleDataService,
                                          ArticleDataService $articleDataService,
                                          PDFGeneratorService $PDFGeneratorService): Response
    {

        $articles = $ordreCollecte->getArticles()->toArray();
        $ordreCollecteReferences = $ordreCollecte->getOrdreCollecteReferences()->toArray();

        $barCodesArticles = array_map(function (Article $article) use ($articleDataService) {
            return $articleDataService->getBarcodeConfig($article);
        }, $articles);

        $barCodesReferences = array_map(function (OrdreCollecteReference $ordreCollecteReference) use ($refArticleDataService) {
            $referenceArticle = $ordreCollecteReference->getReferenceArticle();
            return $referenceArticle
                ? $refArticleDataService->getBarcodeConfig($referenceArticle)
                : null;
        }, $ordreCollecteReferences);

        $barCodes = array_merge($barCodesArticles, array_filter($barCodesReferences , function ($value) {
            return $value !== null;}
        ));
        $barCodesCount = count($barCodes);
        if($barCodesCount > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barCodes,
                'ordreCollecte'
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodes),
                $fileName
            );
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/associer", name="collect_sensor_pairing_new",options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::PAIR_SENSOR}, mode=HasPermission::IN_JSON)
     */
    public function newCollectSensorPairing(OrdreCollecteService $collecteService,
                                            EntityManagerInterface $entityManager,
                                            Request $request): Response
    {
        if($data = json_decode($request->getContent(), true)) {
            if(!$data['sensorWrapper'] && !$data['sensor']) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Un capteur/code capteur est obligatoire pour valider l\'association'
                ]);
            }

            $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->findOneBy(["id" => $data['sensorWrapper'], 'deleted' => false]);
            $collectOrder = $entityManager->getRepository(OrdreCollecte::class)->find($data['orderID']);

            $pairingOrderCollect = $collecteService->createPairing($sensorWrapper, $collectOrder);
            $entityManager->persist($pairingOrderCollect);

            try {
                $entityManager->flush();
            } /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Une autre association est en cours de création, veuillez réessayer.'
                ]);
            }

            $number = $sensorWrapper->getName();
            return $this->json([
                'success' => true,
                'msg' => "L'assocation avec le capteur <strong>${number}</strong> a bien été créée"
            ]);
        }

        throw new BadRequestHttpException();
    }
}
