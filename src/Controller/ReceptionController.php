<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\InventoryCategory;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\FieldsParam;
use App\Entity\CategorieCL;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ReferenceArticle;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\CategoryType;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\ReceptionRepository;
use App\Repository\TransporteurRepository;

use App\Service\CSVExportService;
use App\Service\DemandeLivraisonService;
use App\Service\GlobalParamService;
use App\Service\LitigeService;
use App\Service\MailerService;
use App\Service\MouvementStockService;
use App\Service\MouvementTracaService;
use App\Service\PDFGeneratorService;
use App\Service\ReceptionService;
use App\Service\AttachmentService;
use App\Service\ArticleDataService;
use App\Service\RefArticleDataService;
use App\Service\UniqueNumberService;
use App\Service\UserService;

use App\Service\FreeFieldService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

use Doctrine\ORM\NoResultException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/reception")
 */
class ReceptionController extends AbstractController
{

    /**
     * @var TransporteurRepository
     */
    private $transporteurRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var AttachmentService
     */
    private $attachmentService;

    /**
     * @var ReceptionService
     */
    private $receptionService;

    /**
     * @var ParametrageGlobalRepository
     */
    private $paramGlobalRepository;

    /**
     * @var MouvementStockService
     */
    private $mouvementStockService;
    private $mailerService;

    public function __construct(
        ArticleDataService $articleDataService,
        GlobalParamService $globalParamService,
        ReceptionRepository $receptionRepository,
        UserService $userService,
        ReceptionService $receptionService,
        MailerService $mailerService,
        AttachmentService $attachmentService,
        TransporteurRepository $transporteurRepository,
        ParametrageGlobalRepository $parametrageGlobalRepository,
        MouvementStockService $mouvementStockService
    )
    {
        $this->paramGlobalRepository = $parametrageGlobalRepository;
        $this->mailerService = $mailerService;
        $this->attachmentService = $attachmentService;
        $this->receptionService = $receptionService;
        $this->globalParamService = $globalParamService;
        $this->receptionRepository = $receptionRepository;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->transporteurRepository = $transporteurRepository;
        $this->mouvementStockService = $mouvementStockService;
    }


    /**
     * @Route("/new", name="reception_new", options={"expose"=true}, methods="POST")
     * @param EntityManagerInterface $entityManager
     * @param FreeFieldService $champLibreService
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function new(EntityManagerInterface $entityManager,
                        FreeFieldService $champLibreService,
                        Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::CREATE)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $type = $typeRepository->findOneByCategoryLabel(CategoryType::RECEPTION);
            $reception = new Reception();

            $statusCode = !empty($data['anomalie']) ? ($data['anomalie'] ? Reception::STATUT_ANOMALIE : Reception::STATUT_EN_ATTENTE) : Reception::STATUT_EN_ATTENTE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);

            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));

            // génère le numéro
            $lastNumero = $receptionRepository->getLastNumeroByPrefixeAndDate('R', $date->format('ymd'));
            $lastCpt = (int)substr($lastNumero, -4, 4);
            $i = $lastCpt + 1;
            $cpt = sprintf('%04u', $i);
            $numero = 'R' . $date->format('ymd') . $cpt;

            if (!empty($data['fournisseur'])) {
                $fournisseur = $fournisseurRepository->find(intval($data['fournisseur']));
                $reception
                    ->setFournisseur($fournisseur);
            }

            if (!empty($data['location'])) {
                $location = $emplacementRepository->find(intval($data['location']));
                $reception
                    ->setLocation($location);
            }

            if (!empty($data['transporteur'])) {
                $transporteur = $this->transporteurRepository->find(intval($data['transporteur']));
                $reception
                    ->setTransporteur($transporteur);
            }

            $reception
                ->setReference(!empty($data['reference']) ? $data['reference'] : null)
                ->setDateAttendue(
                    !empty($data['dateAttendue'])
                        ? new DateTime(str_replace('/', '-', $data['dateAttendue']), new DateTimeZone("Europe/Paris"))
                        : null)
                ->setDateCommande(
                    !empty($data['dateCommande'])
                        ? new DateTime(str_replace('/', '-', $data['dateCommande']), new DateTimeZone("Europe/Paris"))
                        : null)
                ->setCommentaire(!empty($data['commentaire']) ? $data['commentaire'] : null)
                ->setStatut($statut)
                ->setNumeroReception($numero)
                ->setDate($date)
                ->setReference(!empty($data['reference']) ? $data['reference'] : null)
                ->setUtilisateur($this->getUser())
                ->setType($type)
                ->setCommentaire(!empty($data['commentaire']) ? $data['commentaire'] : null);

            $entityManager->persist($reception);
            $entityManager->flush();

            $champLibreService->manageFreeFields($reception, $data, $entityManager);

            $entityManager->flush();

            $data = [
                "redirect" => $this->generateUrl('reception_show', [
                    'id' => $reception->getId(),
                ])
            ];
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="reception_edit", options={"expose"=true}, methods="POST")
     * @param EntityManagerInterface $entityManager
     * @param FreeFieldService $champLibreService
     * @param ReceptionService $receptionService
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function edit(EntityManagerInterface $entityManager,
                         FreeFieldService $champLibreService,
                         ReceptionService $receptionService,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);

            $reception = $receptionRepository->find($data['receptionId']);

            $statut = $statutRepository->find(intval($data['statut']));
            $reception->setStatut($statut);

            $fournisseur = !empty($data['fournisseur']) ? $fournisseurRepository->find($data['fournisseur']) : null;
            $reception->setFournisseur($fournisseur);

            $utilisateur = !empty($data['utilisateur']) ? $utilisateurRepository->find($data['utilisateur']) : null;
            $reception->setUtilisateur($utilisateur);

            $transporteur = !empty($data['transporteur']) ? $transporteurRepository->find($data['transporteur']) : null;
            $reception->setTransporteur($transporteur);

            $location = !empty($data['location']) ? $emplacementRepository->find($data['location']) : null;
            $reception->setLocation($location);

            $reception
                ->setReference(!empty($data['numeroCommande']) ? $data['numeroCommande'] : null)
                ->setDateAttendue(
                    !empty($data['dateAttendue'])
                        ? new DateTime(str_replace('/', '-', $data['dateAttendue']), new DateTimeZone("Europe/Paris"))
                        : null)
                ->setDateCommande(
                    !empty($data['dateCommande'])
                        ? new DateTime(str_replace('/', '-', $data['dateCommande']), new DateTimeZone("Europe/Paris"))
                        : null)
                ->setNumeroReception(isset($data['numeroReception']) ? $data['numeroReception'] : null)
                ->setCommentaire(isset($data['commentaire']) ? $data['commentaire'] : null);

            $entityManager->flush();


            $champLibreService->manageFreeFields($reception, $data, $entityManager);

            $entityManager->flush();
            $json = [
                'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                    'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                    'reception' => $reception,
                    'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api-modifier", name="api_reception_edit", options={"expose"=true},  methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function apiEdit(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

            $reception = $receptionRepository->find($data['id']);

            $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

            $typeChampLibre = [];
            $champsLibresEntity = [];
            foreach ($listType as $type) {
                $champsLibresComplet = $champLibreRepository->findByType($type['id']);
                $champsLibres = [];
                //création array edit pour vue
                foreach ($champsLibresComplet as $champLibre) {
                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'requiredEdit' => $champLibre->getRequiredEdit()
                    ];
                    $champsLibresEntity[] = $champLibre;
                }

                $typeChampLibre[] = [
                    'typeLabel' => $type['label'],
                    'typeId' => $type['id'],
                    'champsLibres' => $champsLibres,
                ];
            }

            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);
            $json = $this->renderView('reception/modalEditReceptionContent.html.twig', [
                'reception' => $reception,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::RECEPTION),
                'typeChampsLibres' => $typeChampLibre,
                'fieldsParam' => $fieldsParam,
                'freeFieldsGroupedByTypes' => $champsLibresEntity
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="reception_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function api(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->receptionService->getDataForDatatable($request->request);

            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
            $fieldsParam = $fieldsParamRepository->getHiddenByEntity(FieldsParam::ENTITY_CODE_RECEPTION);
            $data['columnsToHide'] = $fieldsParam;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="reception_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function articleApi(EntityManagerInterface $entityManager,
                               Request $request,
                               $id): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }

            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $reception = $receptionRepository->find($id);
            $ligneArticles = $receptionReferenceArticleRepository->findByReception($reception);

            $rows = [];
            $hasBarCodeToPrint = false;
            foreach ($ligneArticles as $ligneArticle) {
                $referenceArticle = $ligneArticle->getReferenceArticle();
                if (!$hasBarCodeToPrint && isset($referenceArticle)) {
                    $articles = $ligneArticle->getArticles();
                    $hasBarCodeToPrint = (
                        ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) ||
                        ($articles->count() > 0)
                    );
                }

                $rows[] = [
                    "Référence" => (isset($referenceArticle) ? $referenceArticle->getReference() : ''),
                    "Commande" => ($ligneArticle->getCommande() ? $ligneArticle->getCommande() : ''),
                    "A recevoir" => ($ligneArticle->getQuantiteAR() ? $ligneArticle->getQuantiteAR() : 0),
                    "Reçu" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : 0),
                    "Urgence" => ($ligneArticle->getEmergencyTriggered() ?? false),
                    "Comment" => ($ligneArticle->getEmergencyComment() ?? ''),
                    'Actions' => $this->renderView(
                        'reception/datatableLigneRefArticleRow.html.twig',
                        [
                            'ligneId' => $ligneArticle->getId(),
                            'receptionId' => $reception->getId(),
                            'showPrint' => $ligneArticle->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                            'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                            'packFilter' => (isset($referenceArticle) ? $referenceArticle->getBarCode() : ''),
                            'showTrackingMovementsLink' => isset($referenceArticle) && ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE)
                        ]
                    ),
                ];
            }
            $data['data'] = $rows;
            $data['hasBarCodeToPrint'] = $hasBarCodeToPrint;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reception_index", methods={"GET", "POST"}, options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
            return $this->redirectToRoute('access_denied');
        }

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        //TODO à modifier si plusieurs types possibles pour une réception
        $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

        $typeChampLibre = [];
        foreach ($listType as $type) {
            $champsLibres = $champLibreRepository->findByType($type['id']);
            $typeChampLibre[] = [
                'typeLabel' => $type['label'],
                'typeId' => $type['id'],
                'champsLibres' => $champsLibres,
            ];
        }

        return $this->render('reception/index.html.twig', [
            'typeChampLibres' => $typeChampLibre,
            'fieldsParam' => $fieldsParam,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::RECEPTION),
            'receptionLocation' => $this->globalParamService->getReceptionDefaultLocation()
        ]);
    }

    /**
     * @Route("/supprimer", name="reception_delete", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param MouvementTracaService $mouvementTracaService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           MouvementTracaService $mouvementTracaService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $articleRepository = $entityManager->getRepository(Article::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $reception = $receptionRepository->find($data['receptionId']);

            foreach ($reception->getReceptionReferenceArticles() as $receptionArticle) {
                $entityManager->remove($receptionArticle);
                $articleRepository->setNullByReception($receptionArticle);
            }

            foreach ($reception->getMouvementsTraca() as $receptionMvtTraca) {
                $entityManager->remove($receptionMvtTraca);
            }
            $entityManager->flush();

            $entityManager->remove($reception);
            $entityManager->flush();
            $data = [
                "redirect" => $this->generateUrl('reception_index')
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/annuler", name="reception_cancel", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function cancel(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $statutPartialReception = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_PARTIELLE);
            $reception = $receptionRepository->find($data['receptionId']);
            if ($reception->getStatut()->getCode() === Reception::STATUT_RECEPTION_TOTALE) {
                $reception->setStatut($statutPartialReception);
                $entityManager->flush();
            }
            $data = [
                "redirect" => $this->generateUrl('reception_show', [
                    'id' => $reception->getId()
                ])
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/retirer-article", name="reception_article_remove",  options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param ReceptionService $receptionService
     * @param MouvementTracaService $mouvementTracaService
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function removeArticle(EntityManagerInterface $entityManager,
                                  ReceptionService $receptionService,
                                  MouvementTracaService $mouvementTracaService,
                                  Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);

            $ligneArticle = $receptionReferenceArticleRepository->find($data['ligneArticle']);

            if (!$ligneArticle) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La référence est introuvable'
                ]);
            }

            $reception = $ligneArticle->getReception();

            $associatedMvts = $mouvementTracaRepository->findBy([
                'receptionReferenceArticle' => $ligneArticle
            ]);

            $reference = $ligneArticle->getReferenceArticle();
            if ($reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $newRefQuantity = $reference->getQuantiteStock() - $ligneArticle->getQuantite();
                $newRefAvailableQuantity = $newRefQuantity - $reference->getQuantiteReservee();
                if ($newRefAvailableQuantity < 0) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'La suppression de la référence engendre des quantités négatives'
                    ]);
                }
                $reference->setQuantiteStock($newRefQuantity);
            }

            foreach ($associatedMvts as $associatedMvt) {
                $entityManager->remove($associatedMvt);
            }
            $entityManager->flush();

            $entityManager->remove($ligneArticle);
            $entityManager->flush();
            $nbArticleNotConform = $receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statusCode = $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);
            $reception->setStatut($statut);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $quantity = $ligneArticle->getQuantite();
            $stockMovement = $this->mouvementStockService->createMouvementStock(
                $currentUser,
                null,
                $quantity,
                $reference,
                MouvementStock::TYPE_SORTIE
            );

            $stockMovement->setReceptionOrder($reception);
            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $this->mouvementStockService->finishMouvementStock($stockMovement, $date, $reception->getLocation());
            $entityManager->persist($stockMovement);

            $entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                    'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                    'reception' => $reception,
                    'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
                ])
            ]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/add-article", name="reception_article_add", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param ReceptionService $receptionService
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function addArticle(EntityManagerInterface $entityManager,
                               ReceptionService $receptionService,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $contentData = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $refArticleId = (int)$contentData['referenceArticle'];
            $refArticle = $referenceArticleRepository->find($refArticleId);

            $reception = $receptionRepository->find($contentData['reception']);
            $commande = $contentData['commande'];

            $receptionReferenceArticle = $reception->getReceptionReferenceArticles();

            // On vérifie que le couple (référence, commande) n'est pas déjà utilisé dans la réception
            $refAlreadyExists = $receptionReferenceArticle->filter(function (ReceptionReferenceArticle $receptionReferenceArticle) use ($refArticleId, $commande) {
                return (
                    $commande === $receptionReferenceArticle->getCommande() &&
                    $refArticleId === $receptionReferenceArticle->getReferenceArticle()->getId()
                );
            });

            if ($refAlreadyExists->count() === 0) {
                $anomalie = $contentData['anomalie'];
                if ($anomalie) {
                    $statutRecep = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_ANOMALIE);
                    $reception->setStatut($statutRecep);
                }

                $receptionReferenceArticle = new ReceptionReferenceArticle();
                $receptionReferenceArticle
                    ->setCommande($commande)
                    ->setAnomalie($contentData['anomalie'])
                    ->setCommentaire($contentData['commentaire'])
                    ->setReferenceArticle($refArticle)
                    ->setQuantiteAR(max($contentData['quantiteAR'], 1))// protection contre quantités négatives ou nulles
                    ->setReception($reception);

                if (array_key_exists('quantite', $contentData) && $contentData['quantite']) {
                    $receptionReferenceArticle->setQuantite(max($contentData['quantite'], 0));
                }

                $entityManager->persist($receptionReferenceArticle);
                $entityManager->flush();

                if ($refArticle->getIsUrgent()) {
                    $reception->setEmergencyTriggered(true);
                    $receptionReferenceArticle->setEmergencyTriggered(true);
                    $receptionReferenceArticle->setEmergencyComment($refArticle->getEmergencyComment());
                }
                $entityManager->flush();

                $json = [
                    'success' => true,
                    'msg' => 'La référence a été ajoutée à la réception',
					'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                        'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                        'reception' => $reception,
                        'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
					])
				];
			}
			else {
				$json = [
				    'success' => false,
					'msg' => 'Attention ! La référence et le numéro de commande d\'achat saisis existent déjà pour cette réception.'
				];
			}
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier-article", name="reception_article_edit_api", options={"expose"=true},  methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function apiEditArticle(EntityManagerInterface $entityManager,
                                   Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $ligneArticle = $receptionReferenceArticleRepository->find($data['id']);
            $canUpdateQuantity = $ligneArticle->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE;

            $json = $this->renderView(
                'reception/modalEditLigneArticleContent.html.twig',
                [
                    'ligneArticle' => $ligneArticle,
                    'canUpdateQuantity' => $canUpdateQuantity,
                    'minValue' => $ligneArticle->getQuantite() ?? 0
                ]
            );
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article", name="reception_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param ReceptionService $receptionService
     * @param Request $request
     * @param MouvementTracaService $mouvementTracaService
     * @return Response
     * @throws Exception
     */
    public function editArticle(EntityManagerInterface $entityManager,
                                ReceptionService $receptionService,
                                Request $request,
                                MouvementTracaService $mouvementTracaService): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) { //Si la requête est de type Xml
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $receptionReferenceArticle = $receptionReferenceArticleRepository->find($data['article']);
            $reception = $receptionReferenceArticle->getReception();
            $quantite = $data['quantite'];
            $receivedQuantity = $receptionReferenceArticle->getQuantite();

            if (empty($receivedQuantity)) {
                $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
                $receptionReferenceArticle->setReferenceArticle($refArticle);
            }

            $receptionReferenceArticle
                ->setCommande($data['commande'])
                ->setAnomalie($data['anomalie'])
                ->setQuantiteAR(max($data['quantiteAR'], 0))// protection contre quantités négatives
                ->setCommentaire($data['commentaire']);

            $typeQuantite = $receptionReferenceArticle->getReferenceArticle()->getTypeQuantite();
            if ($typeQuantite === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
                $oldReceivedQuantity = $receptionReferenceArticle->getQuantite();
                $newReceivedQuantity = max((int)$quantite, 0);
                $diffReceivedQuantity = $newReceivedQuantity - $oldReceivedQuantity;
                // protection quantité reçue <= quantité à recevoir
                if ($receptionReferenceArticle->getQuantiteAR() && $quantite > $receptionReferenceArticle->getQuantiteAR()) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'La quantité reçue ne peut pas être supérieure à la quantité à recevoir.'
                    ]);
                }

                /** @var Utilisateur $currentUser */
                $currentUser = $this->getUser();
                $receptionLocation = $reception->getLocation();
                $now = new DateTime('now', new DateTimeZone('Europe/Paris'));


                if ($diffReceivedQuantity != 0) {
                    $newRefQuantity = $referenceArticle->getQuantiteStock() + $diffReceivedQuantity;
                    if ($newRefQuantity - $referenceArticle->getQuantiteReservee() < 0) {
                        return new JsonResponse([
                            'success' => false,
                            'msg' =>
                                'Vous ne pouvez pas avoir reçu '
                                . $newReceivedQuantity
                                . ' : la quantité disponible de la référence est : '
                                . $referenceArticle->getQuantiteDisponible()
                        ]);
                    } else {
                        $mouvementStock = $this->mouvementStockService->createMouvementStock(
                            $currentUser,
                            null,
                            abs($diffReceivedQuantity),
                            $referenceArticle,
                            $diffReceivedQuantity < 0 ? MouvementStock::TYPE_SORTIE : MouvementStock::TYPE_ENTREE
                        );
                        $mouvementStock->setReceptionOrder($reception);

                        $this->mouvementStockService->finishMouvementStock(
                            $mouvementStock,
                            $now,
                            $receptionLocation
                        );
                        $entityManager->persist($mouvementStock);
                        $createdMvt = $mouvementTracaService->createTrackingMovement(
                            $referenceArticle->getBarCode(),
                            $receptionLocation,
                            $currentUser,
                            $now,
                            false,
                            true,
                            MouvementTraca::TYPE_DEPOSE,
                            [
                                'mouvementStock' => $mouvementStock,
                                'quantity' => $mouvementStock->getQuantity(),
                                'from' => $reception,
                                'receptionReferenceArticle' => $receptionReferenceArticle
                            ]
                        );

                        $receptionReferenceArticle->setQuantite($newReceivedQuantity); // protection contre quantités négatives
                        $mouvementTracaService->persistSubEntities($entityManager, $createdMvt);
                        $referenceArticle->setQuantiteStock($newRefQuantity);
                        $entityManager->persist($createdMvt);
                    }
                }
            }

            if (array_key_exists('articleFournisseur', $data) && $data['articleFournisseur']) {
                $articleFournisseur = $articleFournisseurRepository->find($data['articleFournisseur']);
                $receptionReferenceArticle->setArticleFournisseur($articleFournisseur);
            }

            $entityManager->flush();

            $json = [
                'success' => true,
                'msg' => '',
                'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                    'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                    'reception' => $reception,
                    'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="reception_show", methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param GlobalParamService $globalParamService
     * @param ReceptionService $receptionService
     * @param Reception $reception
     * @return Response
     * @throws NonUniqueResultException
     */
    public function show(EntityManagerInterface $entityManager,
                         GlobalParamService $globalParamService,
                         ReceptionService $receptionService,
                         Reception $reception): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
            return $this->redirectToRoute('access_denied');
        }

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $listTypesDL = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $typeChampLibreDL = [];

        foreach ($listTypesDL as $typeDL) {
            $champsLibresDL = $champLibreRepository->findByTypeAndCategorieCLLabel($typeDL, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibreDL[] = [
                'typeLabel' => $typeDL->getLabel(),
                'typeId' => $typeDL->getId(),
                'champsLibres' => $champsLibresDL,
            ];
        }

        $createDL = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
        $needsCurrentUser = $this->paramGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEMANDEUR_DANS_DL);

        $defaultDisputeStatus = $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::LITIGE_RECEPT);
        return $this->render("reception/show.html.twig", [
            'reception' => $reception,
            'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
            'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, true),
            'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
            'utilisateurs' => $utilisateurRepository->getIdAndLibelleBySearch(''),
            'typeChampsLibres' => $typeChampLibreDL,
            'createDL' => $createDL ? $createDL->getValue() : false,
            'livraisonLocation' => $globalParamService->getLivraisonDefaultLocation(),
            'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
            'needsCurrentUser' => $needsCurrentUser,
            'detailsHeader' => $receptionService->createHeaderDetailsConfig($reception)
        ]);
    }

    /**
     * @Route(
     *     "/autocomplete-art{reception}",
     *     name="get_article_reception",
     *     options={"expose"=true},
     *     methods="GET|POST",
     *     condition="request.isXmlHttpRequest()"
     * )
     *
     * @param ArticleDataService $articleDataService
     * @param Reception $reception
     * @return JsonResponse
     */
    public function getArticles(ArticleDataService $articleDataService,
                                Reception $reception): JsonResponse {
        $articles = [];
        foreach ($reception->getReceptionReferenceArticles() as $rra) {
            foreach ($rra->getArticles() as $article) {
                if ($articleDataService->articleCanBeAddedInDispute($article)) {
                    $articles[] = [
                        'id' => $article->getId(),
                        'text' => $article->getBarCode(),
                        'numReception' => $article->getReceptionReferenceArticle()];
                }
            }
        }

        return new JsonResponse([
            'results' => $articles
        ]);
    }

    /**
     * @Route("/autocomplete-ref-art/{reception}", name="get_ref_article_reception", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     *
     * @param Request $request
     * @param Reception $reception
     *
     * @param EntityManagerInterface $entityManager
     * @param RefArticleDataService $refArticleDataService
     * @return JsonResponse
     */
    public function getRefTypeQtyArticle(Request $request,
                                         Reception $reception,
                                         EntityManagerInterface $entityManager,
                                         RefArticleDataService $refArticleDataService)
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $articlesFournisseurArrays = [];

        $selectedReference = $request->query->get('reference');
        $selectedCommande = $request->query->get('commande');

        $ref = array_map(
            function ($item) use ($articleFournisseurRepository, &$articlesFournisseurArrays, $refArticleDataService) {
                if (!isset($articlesFournisseurArrays[$item['reference']])) {
                    $articlesFournisseurArrays[$item['reference']] = $articleFournisseurRepository->getIdAndLibelleByRefRef($item['reference']);
                }
                return [
                    'id' => "{$item['reference']}_{$item['commande']}",
                    'reference' => $item['reference'],
                    'commande' => $item['commande'],
                    'defaultArticleFournisseur' => count($articlesFournisseurArrays[$item['reference']]) === 1
                        ? [
                            'text' => $articlesFournisseurArrays[$item['reference']][0]['reference'],
                            'value' => $articlesFournisseurArrays[$item['reference']][0]['id']
                        ]
                        : null,
                    'text' => "{$item['reference']} – {$item['commande']}"
                ];
            },
            $referenceArticleRepository->getRefTypeQtyArticleByReception($reception->getId(), $selectedReference, $selectedCommande)
        );

        return new JsonResponse(['results' => $ref]);
    }

    /**
     * @Route("/ligne-article-conditionnement", name="get_ligne_article_conditionnement", options={"expose"=true}, methods="GET")
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getLigneArticleCondtionnement(Request $request,
                                                  EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $reference = $request->query->get('reference');
            $commande = $request->query->get('commande');
            $quantity = $request->query->get('quantity');
            $defaultArticleFournisseurReference = $request->query->get('defaultArticleFournisseurReference');

            // TODO verif null

            /** @var ReferenceArticle $refArticle */
            $refArticle = $referenceArticleRepository->findOneByReference($reference);

            $typeArticle = $refArticle->getType();

            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
            $response = new Response();
            $response->setContent($this->renderView(
                'reception/conditionnementArticleTemplate.html.twig',
                [
                    'reception' => [
                        'refArticleId' => $refArticle->getId(),
                        'reference' => $reference,
                        'referenceLabel' => $refArticle->getLibelle(),
                        'commande' => $commande,
                        'quantity' => $quantity,
                        'defaultArticleFournisseurReference' => $defaultArticleFournisseurReference,
                    ],
                    'typeArticle' => $typeArticle ? $typeArticle->getLabel() : '',
                    'champsLibres' => $champsLibres,
                    'references' => $articleFournisseurRepository->getIdAndLibelleByRef($refArticle)
                ]
            ));
            return $response;
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_reception",  options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param ArticleDataService $articleDataService
     * @param LitigeService $litigeService
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editLitige(EntityManagerInterface $entityManager,
                               ArticleDataService $articleDataService,
                               LitigeService $litigeService,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = $litigeRepository->find($post->get('id'));
            $typeBefore = $litige->getType()->getId();
            $typeBeforeName = $litige->getType()->getLabel();
            $typeAfter = (int)$post->get('typeLitige');
            $statutBeforeId = $litige->getStatus()->getId();
            $statutBeforeName = $litige->getStatus()->getNom();
            $statutAfterId = (int)$post->get('statutLitige');
            $statutAfter = $statutRepository->find($statutAfterId);

            $articlesNotAvailableCounter = $litige
                ->getArticles()
                ->filter(function (Article $article) {
                    // articles non disponibles
                    return in_array(
                        $article->getStatut()->getNom(),
                        [
                            Article::STATUT_EN_TRANSIT,
                            Article::STATUT_INACTIF
                        ]
                    );
                })
                ->count();

            if (!$statutAfter->isTreated()
                && $articlesNotAvailableCounter > 0) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Vous ne pouvez pas passer le litige dans un statut non traité car il concerne des articles non disponibles.'
                ]);
            }

            $litige
                ->setDeclarant($utilisateurRepository->find($post->get('declarantLitige')))
                ->setUpdateDate(new \DateTime('now'))
                ->setType($typeRepository->find($post->get('typeLitige')))
                ->setStatus($statutAfter);

            $errorResponse = $this->addArticleIntoDispute($entityManager, $articleDataService, $post->get('colis'), $litige);
            if ($errorResponse) {
                return $errorResponse;
            }

            if ($post->get('emergency')) {
                $litige->setEmergencyTriggered($post->get('emergency') === 'true');
            }
            if (!empty($buyers = $post->get('acheteursLitige'))) {
                // on détache les colis existants...
                $existingBuyers = $litige->getBuyers();
                foreach ($existingBuyers as $buyer) {
                    $litige->removeBuyer($buyer);
                }
                // ... et on ajoute ceux sélectionnés
                $listBuyer = explode(',', $buyers);
                foreach ($listBuyer as $buyerId) {
                    $litige->addBuyer($utilisateurRepository->find($buyerId));
                }
            }
            $entityManager->flush();

            $comment = '';
            $statutinstance = $statutRepository->find($post->get('statutLitige'));
            $commentStatut = $statutinstance->getComment();
            if ($typeBefore !== $typeAfter) {
                $comment .= "Changement du type : " . $typeBeforeName . " -> " . $litige->getType()->getLabel() . ".";
            }
            if ($statutBeforeId !== $statutAfterId) {
                if (!empty($comment)) {
                    $comment .= "\n";
                }
                $comment .= "Changement du statut : " .
                    $statutBeforeName . " -> " . $litige->getStatus()->getNom() . "." .
                    (!empty($commentStatut) ? ("\n" . $commentStatut . ".") : '');
            }
            if ($post->get('commentaire')) {
                if (!empty($comment)) {
                    $comment .= "\n";
                }
                $comment .= trim($post->get('commentaire'));
            }

            if (!empty($comment)) {
                $histoLitige = new LitigeHistoric();
                $histoLitige
                    ->setLitige($litige)
                    ->setDate(new \DateTime('now'))
                    ->setUser($this->getUser())
                    ->setComment($comment);
                $entityManager->persist($histoLitige);
                $entityManager->flush();
            }

            $listAttachmentIdToKeep = $post->get('files') ?? [];
            $attachments = $litige->getAttachments()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, $litige);
                }
            }

            $this->createAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
            $entityManager->flush();
            $isStatutChange = ($statutBeforeId !== $statutAfterId);
            if ($isStatutChange) {
                $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_RECEPTION, true);
            }
            return new JsonResponse([
                'success' => true
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer-litige", name="litige_new_reception", options={"expose"=true}, methods={"POST"})
     * @param EntityManagerInterface $entityManager
     * @param LitigeService $litigeService
     * @param ArticleDataService $articleDataService
     * @param Request $request
     * @param UniqueNumberService $uniqueNumberService
     * @return Response
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function newLitige(EntityManagerInterface $entityManager,
                              LitigeService $litigeService,
                              ArticleDataService $articleDataService,
                              Request $request,
                              UniqueNumberService $uniqueNumberService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $disputeRepository = $entityManager->getRepository(Litige::class);

            $litige = new Litige();

            $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $dateStr = $now->format('Ymd');
            $prefix = Litige::DISPUTE_RECEPTION_PREFIX;

            $lastDisputeNumber = $disputeRepository->getLastDisputeNumberByPrefixAndDate($prefix, $dateStr);
            $disputeNumber = $uniqueNumberService->createUniqueNumber($prefix, $lastDisputeNumber);

            $litige
                ->setStatus($statutRepository->find($post->get('statutLitige')))
                ->setType($typeRepository->find($post->get('typeLitige')))
                ->setDeclarant($utilisateurRepository->find($post->get('declarantLitige')))
                ->setCreationDate($now)
                ->setNumeroLitige($disputeNumber);

            $errorResponse = $this->addArticleIntoDispute($entityManager, $articleDataService, $post->get('colisLitige'), $litige);
            if ($errorResponse) {
                return $errorResponse;
            }

            if ($post->get('emergency')) {
                $litige->setEmergencyTriggered($post->get('emergency') === 'true');
            }
            if (!empty($buyers = $post->get('acheteursLitige'))) {
                $listBuyers = explode(',', $buyers);
                foreach ($listBuyers as $buyer) {
                    $litige->addBuyer($utilisateurRepository->find($buyer));
                }
            }
            $statutinstance = $statutRepository->find($post->get('statutLitige'));
            $commentStatut = $statutinstance->getComment();

            $trimCommentStatut = trim($commentStatut);
            $userComment = trim($post->get('commentaire'));
            $nl = !empty($userComment) ? "\n" : '';
            $commentaire = $userComment . (!empty($trimCommentStatut) ? ($nl . $commentStatut) : '');
            if (!empty($commentaire)) {
                $histo = new LitigeHistoric();
                $histo
                    ->setDate(new \DateTime('now'))
                    ->setComment($commentaire)
                    ->setLitige($litige)
                    ->setUser($this->getUser());
                $entityManager->persist($histo);
            }

            $entityManager->persist($litige);
            $entityManager->flush();

            $this->createAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
            $entityManager->flush();
            $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_RECEPTION);

            return new JsonResponse([
                'success' => true
            ]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit_reception", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function apiEditLitige(EntityManagerInterface $entityManager,
                                  Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $pieceJointeRepository = $entityManager->getRepository(PieceJointe::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = $litigeRepository->find($data['litigeId']);
            $colisCode = [];
            $acheteursCode = [];

            foreach ($litige->getArticles() as $colis) {
                $colisCode[] = [
                    'id' => $colis->getId(),
                    'text' => $colis->getBarCode()
                ];
            }
            foreach ($litige->getBuyers() as $buyer) {
                $acheteursCode[] = $buyer->getId();
            }

            $html = $this->renderView('reception/modalEditLitigeContent.html.twig', [
                'litige' => $litige,
                'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
                'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, true),
                'attachments' => $pieceJointeRepository->findBy(['litige' => $litige]),
                'utilisateurs' => $utilisateurRepository->getIdAndLibelleBySearch(''),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode, 'acheteurs' => $acheteursCode]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_reception", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $statutRepository = $entityManager->getRepository(Statut::class);

            $dispute = $litigeRepository->find($data['litige']);
            $articlesInDispute = $dispute->getArticles()->toArray();

            $articleStatusAvailable = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            /** @var Article $article */
            foreach ($articlesInDispute as $article) {
                $article->removeLitige($dispute);
                $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
            }

            $entityManager->remove($dispute);
            $entityManager->flush();

            return new JsonResponse(['success' => true]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/litiges/api/{reception}", name="litige_reception_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param Reception $reception
     * @return Response
     */
    public function apiReceptionLitiges(Request $request,
                                        EntityManagerInterface $entityManager,
                                        Reception $reception): Response
    {
        if ($request->isXmlHttpRequest()) {
            $litigeRepository = $entityManager->getRepository(Litige::class);

            /** @var Litige[] $litiges */
            $litiges = $litigeRepository->findByReception($reception);

            $rows = [];

            foreach ($litiges as $litige) {
                $buyers = [];
                $articles = [];
                foreach ($litige->getBuyers() as $buyer) {
                    $buyers[] = $buyer->getUsername();
                }
                foreach ($litige->getArticles() as $article) {
                    $articles[] = $article->getBarCode();
                }
                $lastHistoric = count($litige->getLitigeHistorics()) > 0
                    ?
                    $litige->getLitigeHistorics()[count($litige->getLitigeHistorics()) - 1]->getComment()
                    :
                    '';
                $rows[] = [
                    'type' => $litige->getType()->getLabel(),
                    'status' => $litige->getStatus()->getNom(),
                    'lastHistoric' => $lastHistoric,
                    'date' => $litige->getCreationDate()->format('d/m/Y H:i'),
                    'actions' => $this->renderView('reception/datatableLitigesRow.html.twig', [
                        'receptionId' => $reception->getId(),
                        'url' => [
                            'edit' => $this->generateUrl('litige_edit_reception', ['id' => $litige->getId()])
                        ],
                        'litigeId' => $litige->getId(),
                        'disputeNumber' => $litige->getNumeroLitige()
                    ]),
                    'urgence' => $litige->getEmergencyTriggered()
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/finir", name="reception_finish", methods={"GET", "POST"}, options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param MouvementTracaService $mouvementTracaService
     * @return Response
     * @throws Exception
     */
    public function finish(Request $request,
                           EntityManagerInterface $entityManager,
                           MouvementTracaService $mouvementTracaService): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $receptionRepository = $entityManager->getRepository(Reception::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $reception = $receptionRepository->find($data['id']);
            $listReceptionReferenceArticle = $receptionReferenceArticleRepository->findByReception($reception);

            if (empty($listReceptionReferenceArticle)) {
                return new JsonResponse('Vous ne pouvez pas finir une réception sans article.');
            } else {
                if ($data['confirmed'] === true) {
                    $this->validateReception($entityManager, $reception, $listReceptionReferenceArticle, $mouvementTracaService);
                    return new JsonResponse(1);
                } else {
                    $partielle = false;
                    foreach ($listReceptionReferenceArticle as $receptionRA) {
                        if ($receptionRA->getQuantite() !== $receptionRA->getQuantiteAR()) $partielle = true;
                    }
                    if (!$partielle) {
                        $this->validateReception($entityManager, $reception, $listReceptionReferenceArticle, $mouvementTracaService);
                    }
                    return new JsonResponse($partielle ? 0 : 1);
                }
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Reception $reception
     * @throws Exception
     */
    private function validateReception(EntityManagerInterface $entityManager,
                                       $reception)
    {
        $statutRepository = $entityManager->getRepository(Statut::class);

        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_TOTALE);
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));

        $reception
            ->setStatut($statut)
            ->setDateFinReception($now)
            ->setDateCommande($now);

        $entityManager->flush();
    }

    /**
     * @Route("/obtenir-modal-for-ref", name="get_modal_new_ref", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @return Response
     */
    public function checkIfQuantityArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::CREATE_REF_FROM_RECEP)) {
                return $this->redirectToRoute('access_denied');
            }
            $entityManager = $this->getDoctrine()->getManager();

            $typeRepository = $entityManager->getRepository(Type::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);

            $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);

            $inventoryCategories = $inventoryCategoryRepository->findAll();
            $typeChampLibre = [];
            foreach ($types as $type) {
                $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
                $typeChampLibre[] = [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'champsLibres' => $champsLibres,
                ];
            }
            return new JsonResponse($this->renderView('reception/modalNewRefArticle.html.twig', [
                'typeChampsLibres' => $typeChampLibre,
                'types' => $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]),
                'categories' => $inventoryCategories,
            ]));
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verif-avant-suppression", name="ligne_recep_check_delete", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return JsonResponse
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function checkBeforeLigneDelete(EntityManagerInterface $entityManager,
                                           Request $request)
    {
        if ($request->isXmlHttpRequest() && $id = json_decode($request->getContent(), true)) {
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $nbArticles = $receptionReferenceArticleRepository->countArticlesByRRA($id);
            $ligneArticle = $receptionReferenceArticleRepository->find($id);
            $reference = $ligneArticle->getReferenceArticle();
            $newRefQuantity = $reference->getQuantiteStock() - $ligneArticle->getQuantite();
            $newRefAvailableQuantity = $newRefQuantity - $reference->getQuantiteReservee();
            if (intval($nbArticles) === 0 && ($newRefAvailableQuantity >= 0 || $reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE)) {
                $delete = true;
                $html = $this->renderView('reception/modalDeleteLigneArticleRight.html.twig');
            } else {
                $delete = false;
                if (intval($nbArticles) > 0) {
                    $html = $this->renderView('reception/modalDeleteLigneArticleWrong.html.twig');
                } else {
                    $html = $this->renderView('reception/modalDeleteLigneArticleWrong.html.twig', [
                        'msg' => 'En effet, cela décrémenterait le stock de '
                            . $ligneArticle->getQuantite() . ' alors que la quantité disponible de la référence est de '
                            . $reference->getQuantiteDisponible() . '.'
                    ]);
                }
            }
            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
    }


    /**
     * @Route("/{reception}/etiquettes", name="reception_bar_codes_print", options={"expose"=true})
     * @param Reception $reception
     * @param EntityManagerInterface $entityManager
     * @param RefArticleDataService $refArticleDataService
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getReceptionBarCodes(Reception $reception,
                                         EntityManagerInterface $entityManager,
                                         RefArticleDataService $refArticleDataService,
                                         ArticleDataService $articleDataService,
                                         PDFGeneratorService $PDFGeneratorService): Response
    {
        $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

        $listReceptionReferenceArticle = $receptionReferenceArticleRepository->findByReception($reception);

        $barcodeConfigs = array_reduce(
            $listReceptionReferenceArticle,
            function (array $carry, ReceptionReferenceArticle $recepRef) use ($refArticleDataService, $articleDataService): array {
                $referenceArticle = $recepRef->getReferenceArticle();

                if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $carry[] = $refArticleDataService->getBarcodeConfig($referenceArticle);
                } else {
                    $articlesReception = $recepRef->getArticles()->toArray();
                    if (!empty($articlesReception)) {
                        array_push(
                            $carry,
                            ...array_map(
                                function (Article $article) use ($articleDataService) {
                                    return $articleDataService->getBarcodeConfig($article);
                                },
                                $articlesReception
                            )
                        );
                    }
                }
                return $carry;
            },
            []);

        if (!empty($barcodeConfigs)) {
            $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'articles_reception');
            $pdf = $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs);
            return new PdfResponse($pdf, $fileName);
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }


    /**
     * @Route("/{reception}/ligne-article/{ligneArticle}/etiquette", name="reception_ligne_article_bar_code_print", options={"expose"=true})
     * @param Reception $reception
     * @param ReceptionReferenceArticle $ligneArticle
     * @param RefArticleDataService $refArticleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getReceptionLigneArticleBarCode(Reception $reception,
                                                    ReceptionReferenceArticle $ligneArticle,
                                                    RefArticleDataService $refArticleDataService,
                                                    PDFGeneratorService $PDFGeneratorService): Response
    {
        if ($reception->getReceptionReferenceArticles()->contains($ligneArticle) && $ligneArticle->getReferenceArticle()) {
            $barcodeConfigs = [$refArticleDataService->getBarcodeConfig($ligneArticle->getReferenceArticle())];
            $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'articles_reception');
            $pdf = $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs);
            return new PdfResponse($pdf, $fileName);
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/ajouter_lot", name="add_lot", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addLot(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($this->renderView('reception/modalConditionnementRow.html.twig'));
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/apiArticle", name="article_by_reception_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function apiArticle(EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $ligne = $request->request->get('ligne')) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $ligne = $receptionReferenceArticleRepository->find(intval($ligne));
            $data = $this->articleDataService->getDataForDatatableByReceptionLigne($ligne, $this->getUser());

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="reception_check_delete", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function checkReceptionCanBeDeleted(EntityManagerInterface $entityManager,
                                               Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $receptionId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            if ($receptionReferenceArticleRepository->countByReceptionId($receptionId) == 0) {
                $delete = true;
                $html = $this->renderView('reception/modalDeleteReceptionRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('reception/modalDeleteReceptionWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/csv", name="get_receptions_csv", options={"expose"=true}, methods={"GET"})
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @param CSVExportService $CSVExportService
     * @param Request $request
     * @return Response
     */
    public function getReceptionCSV(EntityManagerInterface $entityManager,
                                    TranslatorInterface $translator,
                                    CSVExportService $CSVExportService,
                                    Request $request): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        }
        catch(\Throwable $throwable) {}

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $receptions = $receptionRepository->getByDates($dateTimeMin, $dateTimeMax);

            $csvHeader = [
                $translator->trans('réception.n° de réception'),
                'n° de commande',
                'fournisseur',
                'utilisateur',
                'statut',
                'date de création',
                'date de fin',
                'commentaire',
                'quantité à recevoir',
                'quantité reçue',
                'référence',
                'libellé',
                'quantité stock',
                'type',
                'code-barre reference',
                'code-barre article',
            ];

            return $CSVExportService->createBinaryResponseFromData(
                'export.csv',
                $receptions,
                $csvHeader,
                function ($reception) {
                    $rows = [];
                    if ($reception['articleId'] || $reception['referenceArticleId']) {
                        if ($reception['referenceArticleId']) {
                            $row = $this->serializeReception($reception);

                            $row[] = $reception['referenceArticleReference'] ?: '';
                            $row[] = $reception['referenceArticleLibelle'] ?: '';
                            $row[] = $reception['referenceArticleQuantiteStock'] ?: '';
                            $row[] = $reception['referenceArticleTypeLabel'] ?: '';
                            $row[] = $reception['referenceArticleBarcode'] ?: '';

                            $rows[] = $row;
                        }

                        if ($reception['articleId']) {
                            $row = $this->serializeReception($reception);

                            $row[] = $reception['articleReference'] ?: '';
                            $row[] = $reception['articleLabel'] ?: '';
                            $row[] = $reception['articleQuantity'] ?: '';
                            $row[] = $reception['articleTypeLabel'] ?: '';
                            $row[] = $reception['articleReferenceArticleBarcode'] ?: '';
                            $row[] = $reception['articleBarcode'] ?: '';

                            $rows[] = $row;
                        }
                    }
                    else {
                        $rows[] = $this->serializeReception($reception);
                    }
                    return $rows;
                }
            );

        } else {
            throw new NotFoundHttpException('404');
        }
    }

    private function serializeReception(array $reception): array {
        return [
            $reception['numeroReception'] ?: '',
            $reception['reference'] ?: '',
            $reception['providerName'] ?: '',
            $reception['userUsername'] ?: '',
            $reception['statusName'] ?: '',
            $reception['date'] ? $reception['date']->format('d/m/Y H:i') : '',
            $reception['dateFinReception'] ? $reception['dateFinReception']->format('d/m/Y H:i') : '',
            $reception['commentaire'] ? strip_tags($reception['commentaire']) : '',
            $reception['receptionRefArticleQuantiteAR'] ?: '',
            $reception['receptionRefArticleQuantite'] ?: ''
        ];
    }

    /**
     * @Route("/avec-conditionnement/{reception}", name="reception_new_with_packing", options={"expose"=true})
     * @param Request $request
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @param Reception $reception
     * @param FreeFieldService $champLibreService
     * @param MouvementTracaService $mouvementTracaService
     * @param MouvementStockService $mouvementStockService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function newWithPacking(Request $request,
                                   DemandeLivraisonService $demandeLivraisonService,
                                   TranslatorInterface $translator,
                                   EntityManagerInterface $entityManager,
                                   Reception $reception,
                                   FreeFieldService $champLibreService,
                                   MouvementTracaService $mouvementTracaService,
                                   MouvementStockService $mouvementStockService): Response
    {

        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $articles = $data['conditionnement'];

            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $totalQuantities = [];
            foreach ($articles as $article) {
                $rra = $receptionReferenceArticleRepository->findOneByReceptionAndCommandeAndRefArticleId(
                    $reception,
                    $article['noCommande'],
                    $article['refArticle']
                );
                if (!isset($totalQuantities[$rra->getId()])) {
                    $totalQuantities[$rra->getId()] = ($rra->getQuantite() ?? 0);
                }
                $totalQuantities[$rra->getId()] += $article['quantite'];
            }
            foreach ($totalQuantities as $rraId => $totalQuantity) {
                $rra = $receptionReferenceArticleRepository->find($rraId);

                // protection quantité reçue <= quantité à recevoir
                if ($totalQuantity > $rra->getQuantiteAR() || $totalQuantity < 0) {
                    return new JsonResponse(false);
                }
                $rra->setQuantite($totalQuantity);
                $entityManager->flush();
            }
            // optionnel : crée la demande de livraison
            $needCreateLivraison = (bool) $data['create-demande'];

            if ($needCreateLivraison) {
                // optionnel : crée l'ordre de prépa
                $paramCreatePrepa = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);
                $needCreatePrepa = $paramCreatePrepa ? $paramCreatePrepa->getValue() : false;
                $data['needPrepa'] = $needCreatePrepa;

                $demande = $demandeLivraisonService->newDemande($data, $entityManager, false, $champLibreService);
                $entityManager->persist($demande);
                $entityManager->flush();
            }

            $receptionLocation = $reception->getLocation();
            // crée les articles et les ajoute à la demande, à la réception, crée les urgences
            $receptionLocationId = isset($receptionLocation) ? $receptionLocation->getId() : null;
            foreach ($articles as $article) {
                if (isset($receptionLocationId)) {
                    $article['emplacement'] = $receptionLocationId;
                }
                $article = $this->articleDataService->newArticle($article, $demande ?? null, $reception);

                $mouvementStock = $mouvementStockService->createMouvementStock(
                    $currentUser,
                    null,
                    $article->getQuantite(),
                    $article,
                    MouvementStock::TYPE_ENTREE
                );
                $mouvementStock->setReceptionOrder($reception);

                $mouvementStockService->finishMouvementStock(
                    $mouvementStock,
                    $now,
                    $receptionLocation
                );

                $entityManager->persist($mouvementStock);

                $createdMvt = $mouvementTracaService->createTrackingMovement(
                    $article->getBarCode(),
                    $receptionLocation,
                    $currentUser,
                    $now,
                    false,
                    true,
                    MouvementTraca::TYPE_DEPOSE,
                    [
                        'mouvementStock' => $mouvementStock,
                        'quantity' => $mouvementStock->getQuantity(),
                        'from' => $reception
                    ]
                );
                $mouvementTracaService->persistSubEntities($entityManager, $createdMvt);
                $entityManager->persist($createdMvt);
            }

            if (isset($demande) && $demande->getType()->getSendMail()) {
                $nowDate = new DateTime('now');
                $this->mailerService->sendMail(
                    'FOLLOW GT // Réception d\'un colis ' . 'de type «' . $demande->getType()->getLabel() . '».',
                    $this->renderView('mails/contents/mailDemandeLivraisonValidate.html.twig', [
                        'demande' => $demande,
                        'fournisseur' => $reception->getFournisseur(),
                        'isReception' => true,
                        'title' => 'Une ' . $translator->trans('réception.réception')
                            . ' '
                            . $reception->getNumeroReception()
                            . ' de type «'
                            . $demande->getType()->getLabel()
                            . '» a été réceptionnée le '
                            . $nowDate->format('d/m/Y \à H:i')
                            . '.',
                    ]),
                    $demande->getUtilisateur()->getMainAndSecondaryEmails()
                );
            }
            $entityManager->flush();

            return new JsonResponse(true);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param Litige $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
    private function createAttachmentsForEntity(Litige $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager) {
        $attachments = $attachmentService->createAttachements($request->files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $entity->addAttachment($attachment);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param ArticleDataService $articleDataService
     * @param string $articlesParamStr
     * @param Litige $dispute
     * @return Response|null
     * @throws NonUniqueResultException
     */
    private function addArticleIntoDispute(EntityManagerInterface $entityManager,
                                           ArticleDataService $articleDataService,
                                           string $articlesParamStr,
                                           Litige $dispute): ?Response {
        if (!empty($articlesParamStr)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $articleStatusAvailable = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            $articleStatusDispute = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_LITIGE);

            // on détache les colis existants...
            $existingArticles = $dispute->getArticles();
            foreach ($existingArticles as $article) {
                $article->removeLitige($dispute);
                $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
            }

            // ... et on ajoute ceux sélectionnés
            $listArticlesId = explode(',', $articlesParamStr);
            foreach ($listArticlesId as $articleId) {
                $article = $articleRepository->find($articleId);
                $dispute->addArticle($article);
                $ligneIsUrgent = $article->getReceptionReferenceArticle() && $article->getReceptionReferenceArticle()->getEmergencyTriggered();
                if ($ligneIsUrgent) {
                    $dispute->setEmergencyTriggered(true);
                }

                if (!$articleDataService->articleCanBeAddedInDispute($article)) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Les articles doivent être en statut "disponible" ou "en litige".'
                    ]);
                }
                else {
                    if ($dispute->getStatus()->isTreated()) {
                        $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
                    }
                    else { // !$dispute->getStatus()->isTreated()
                        $article->setStatut($articleStatusDispute);
                    }
                }
            }
        }
        return null;
    }

    public function setArticleStatusForTreatedDispute(Article $article,
                                                      Statut $articleStatusAvailable) {
        // on check si l'article a des
        $currentDisputesCounter = $article
            ->getLitiges()
            ->filter(function (Litige $articleDispute) {
                return !$articleDispute->getStatus()->isTreated();
            })
            ->count();

        if ($currentDisputesCounter === 0) {
            $article->setStatut($articleStatusAvailable);
        }
    }

}
