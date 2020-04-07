<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\InventoryCategory;
use App\Entity\LigneArticle;
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
use App\Entity\ValeurChampLibre;
use App\Entity\ReferenceArticle;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\CategoryType;
use App\Repository\LitigeRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\FieldsParamRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ReceptionRepository;
use App\Repository\TransporteurRepository;

use App\Service\DemandeLivraisonService;
use App\Service\GlobalParamService;
use App\Service\MailerService;
use App\Service\MouvementStockService;
use App\Service\MouvementTracaService;
use App\Service\PDFGeneratorService;
use App\Service\ReceptionService;
use App\Service\AttachmentService;
use App\Service\ArticleDataService;
use App\Service\RefArticleDataService;
use App\Service\TranslationService;
use App\Service\UserService;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

use Doctrine\ORM\NoResultException;
use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/reception")
 */
class ReceptionController extends AbstractController
{

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

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
     * @var FieldsParamRepository
     */
    private $fieldsParamRepository;

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
     * @var LitigeRepository
     */
    private $litigeRepository;

    /**
     * @var ReceptionService
     */
    private $receptionService;

    /**
     * @var PieceJointeRepository
     */
    private $pieceJointeRepository;

    /**
     * @var ParametrageGlobalRepository
     */
    private $paramGlobalRepository;

    /**
     * @var MouvementStockService
     */
    private $mouvementStockService;
    private $translationService;
    private $mailerService;

    public function __construct(
        ArticleDataService $articleDataService,
        GlobalParamService $globalParamService,
        ReceptionRepository $receptionRepository,
        UtilisateurRepository $utilisateurRepository,
        UserService $userService,
        PieceJointeRepository $pieceJointeRepository,
        ReceptionService $receptionService,
        LitigeRepository $litigeRepository,
        MailerService $mailerService,
        AttachmentService $attachmentService,
        FieldsParamRepository $fieldsParamRepository,
        TransporteurRepository $transporteurRepository,
        ParametrageGlobalRepository $parametrageGlobalRepository,
        MouvementStockService $mouvementStockService,
        TranslationService $translationService
    )
    {
        $this->paramGlobalRepository = $parametrageGlobalRepository;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->litigeRepository = $litigeRepository;
        $this->mailerService = $mailerService;
        $this->attachmentService = $attachmentService;
        $this->receptionService = $receptionService;
        $this->globalParamService = $globalParamService;
        $this->receptionRepository = $receptionRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->transporteurRepository = $transporteurRepository;
        $this->fieldsParamRepository = $fieldsParamRepository;
        $this->mouvementStockService = $mouvementStockService;
        $this->translationService = $translationService;
    }


    /**
     * @Route("/new", name="reception_new", options={"expose"=true}, methods="POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function new(EntityManagerInterface $entityManager,
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
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
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

            $em = $this->getDoctrine()->getManager();
            $em->persist($reception);
            $em->flush();

            $champsLibresKey = array_keys($data);
            foreach ($champsLibresKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = new ValeurChampLibre();
                    $valeurChampLibre
                        ->setValeur(is_array($data[$champs]) ? implode(";", $data[$champs]) : $data[$champs])
                        ->addReception($reception)
                        ->setChampLibre($champLibreRepository->find($champs));

                    $em->persist($valeurChampLibre);
                    $em->flush();
                }
            }

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
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);

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

            $em = $this->getDoctrine()->getManager();
            $em->flush();

            $champLibreKey = array_keys($data);
            foreach ($champLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $champLibreRepository->find($champ);
                    $valeurChampLibre = $valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);
                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addReception($reception)
                            ->setChampLibre($champLibreRepository->find($champ));
                        $em->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur(is_array($data[$champ]) ? implode(";", $data[$champ]) : $data[$champ]);
                    $em->flush();
                }
            }
            $type = $reception->getType();

            $valeurChampLibreTab = empty($type) ? [] : $valeurChampLibreRepository->getByReceptionAndType($reception, $type);
            $champsLibres = [];

            $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

            foreach ($listTypes as $type) {
                $listChampLibreReception = $champLibreRepository->findByType($type['id']);

                foreach ($listChampLibreReception as $champLibre) {
                    $valeurChampLibre = $valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampLibre,
                    ];
                }
            }
            $json = [
                'entete' => $this->renderView('reception/enteteReception.html.twig', [
                    'reception' => $reception,
                    'valeurChampLibreTab' => $valeurChampLibreTab,
                    'typeChampsLibres' => $champsLibres,
                    'fieldsParam' => $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION)
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
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $reception = $receptionRepository->find($data['id']);

            $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

            $typeChampLibre = [];
            foreach ($listType as $type) {
                $champsLibresComplet = $champLibreRepository->findByType($type['id']);
                $champsLibres = [];
                //création array edit pour vue
                foreach ($champsLibresComplet as $champLibre) {
                    $valeurChampReception = $valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);
                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampReception,
                        'requiredEdit' => $champLibre->getRequiredEdit()
                    ];
                }

                $typeChampLibre[] = [
                    'typeLabel' => $type['label'],
                    'typeId' => $type['id'],
                    'champsLibres' => $champsLibres,
                ];
            }

            $fieldsParam = $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);
            $json = $this->renderView('reception/modalEditReceptionContent.html.twig', [
                'reception' => $reception,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::RECEPTION),
                'valeurChampLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                'typeChampsLibres' => $typeChampLibre,
                'fieldsParam' => $fieldsParam,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="reception_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $this->receptionService->getDataForDatatable($request->request);

            $fieldsParam = $this->fieldsParamRepository->getHiddenByEntity(FieldsParam::ENTITY_CODE_RECEPTION);
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
                    "A recevoir" => ($ligneArticle->getQuantiteAR() ? $ligneArticle->getQuantiteAR() : ''),
                    "Reçu" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ''),
                    "Urgence" => ($ligneArticle->getEmergencyTriggered() ?? false),
                    "Comment" => ($ligneArticle->getEmergencyComment() ?? ''),
                    'Actions' => $this->renderView(
                        'reception/datatableLigneRefArticleRow.html.twig',
                        [
                            'ligneId' => $ligneArticle->getId(),
                            'receptionId' => $reception->getId(),
                            'showPrint' => $ligneArticle->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE,
                            'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE
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

        //TODO à modifier si plusieurs types possibles pour une réception
        $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);
        $fieldsParam = $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

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
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
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
     * @Route("/retirer-article", name="reception_article_remove",  options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function removeArticle(EntityManagerInterface $entityManager,
                                  Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $ligneArticle = $receptionReferenceArticleRepository->find($data['ligneArticle']);

            if (!$ligneArticle) return new JsonResponse(false);

            $reception = $ligneArticle->getReception();

            $entityManager->remove($ligneArticle);
            $entityManager->flush();
            $nbArticleNotConform = $receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statusCode = $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);
            $reception->setStatut($statut);
            $type = $reception->getType();

            $valeurChampLibreTab = empty($type) ? [] : $valeurChampLibreRepository->getByReceptionAndType($reception, $type);

            $champsLibres = [];

            $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

            foreach ($listTypes as $type) {
                $listChampLibreReception = $champLibreRepository->findByType($type['id']);

                foreach ($listChampLibreReception as $champLibre) {
                    $valeurChampLibre = $valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampLibre,
                    ];
                }
            }

            $json = [
                'entete' => $this->renderView('reception/enteteReception.html.twig', [
                    'reception' => $reception,
                    'valeurChampLibreTab' => $valeurChampLibreTab,
                    'typeChampsLibres' => $champsLibres,
                    'fieldsParam' => $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION)
                ])
            ];
            $entityManager->flush();
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/add-article", name="reception_article_add", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function addArticle(EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $contentData = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
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

                $receptionReferenceArticle = new ReceptionReferenceArticle;
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

                $type = $reception->getType();
                $valeurChampLibreTab = empty($type) ? [] : $valeurChampLibreRepository->getByReceptionAndType($reception, $type);

                $champsLibres = [];
                $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);
                foreach ($listTypes as $oneType) {
                    $listChampLibreReception = $champLibreRepository->findByType($oneType['id']);

                    foreach ($listChampLibreReception as $champLibre) {
                        $valeurChampLibre = $valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

                        $champsLibres[] = [
                            'id' => $champLibre->getId(),
                            'label' => $champLibre->getLabel(),
                            'typage' => $champLibre->getTypage(),
                            'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                            'defaultValue' => $champLibre->getDefaultValue(),
                            'valeurChampLibre' => $valeurChampLibre,
                        ];
                    }
                }
                if ($refArticle->getIsUrgent()) {
                    $reception->setEmergencyTriggered(true);
                    $receptionReferenceArticle->setEmergencyTriggered(true);
                    $receptionReferenceArticle->setEmergencyComment($refArticle->getEmergencyComment());
                }
                $entityManager->flush();
                $json = [
                    'entete' => $this->renderView('reception/enteteReception.html.twig', [
                        'reception' => $reception,
                        'valeurChampLibreTab' => $valeurChampLibreTab,
                        'typeChampsLibres' => $champsLibres,
                        'fieldsParam' => $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION)
                    ])
                ];
            } else {
                $json = [
                    'errorMsg' => 'Attention ! La référence et le numéro de commande d\'achat saisis existent déjà pour cette réception.'
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
                    'canUpdateQuantity' => $canUpdateQuantity
                ]
            );
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article", name="reception_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editArticle(EntityManagerInterface $entityManager,
                                Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) { //Si la requête est de type Xml

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);

            $receptionReferenceArticle = $receptionReferenceArticleRepository->find($data['article']);
            $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
            $reception = $receptionReferenceArticle->getReception();
            $quantite = $data['quantite'];

            $receptionReferenceArticle
                ->setCommande($data['commande'])
                ->setAnomalie($data['anomalie'])
                ->setReferenceArticle($refArticle)
                ->setQuantiteAR(max($data['quantiteAR'], 0))// protection contre quantités négatives
                ->setCommentaire($data['commentaire']);

            $typeQuantite = $receptionReferenceArticle->getReferenceArticle()->getTypeQuantite();
            if ($typeQuantite === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {

                // protection quantité reçue <= quantité à recevoir
                if ($receptionReferenceArticle->getQuantite() && $quantite > $receptionReferenceArticle->getQuantite()) {
                    return new JsonResponse(false);
                }
                $receptionReferenceArticle->setQuantite(max($quantite, 0)); // protection contre quantités négatives
            }

            if (array_key_exists('articleFournisseur', $data) && $data['articleFournisseur']) {
                $articleFournisseur = $articleFournisseurRepository->find($data['articleFournisseur']);
                $receptionReferenceArticle->setArticleFournisseur($articleFournisseur);
            }

            $entityManager->flush();

            $nbArticleNotConform = $receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statusCode = $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);

            $reception->setStatut($statut);
            $entityManager->flush();
            $type = $reception->getType();

            $valeurChampLibreTab = empty($type) ? [] : $valeurChampLibreRepository->getByReceptionAndType($reception, $type);

            $champsLibres = [];
            $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);
            foreach ($listTypes as $oneType) {
                $listChampLibreReception = $champLibreRepository->findByType($oneType['id']);

                foreach ($listChampLibreReception as $champLibre) {
                    $valeurChampLibre = $valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampLibre,
                    ];
                }
            }

            $json = [
                'entete' => $this->renderView('reception/enteteReception.html.twig', [
                    'reception' => $reception,
                    'valeurChampLibreTab' => $valeurChampLibreTab,
                    'typeChampsLibres' => $champsLibres,
                    'fieldsParam' => $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION)
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
     * @param Reception $reception
     * @return Response
     * @throws NonUniqueResultException
     */
    public function show(EntityManagerInterface $entityManager,
                         GlobalParamService $globalParamService,
                         Reception $reception): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
            return $this->redirectToRoute('access_denied');
        }

        $paramGlobalRepository = $this->getDoctrine()->getRepository(ParametrageGlobal::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);

        $type = $reception->getType();
        if ($type) {
            $valeurChampLibreTab = $valeurChampLibreRepository->getByReceptionAndType($reception, $type);
        } else {
            $valeurChampLibreTab = [];
        }

        $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

        $champsLibresReception = [];
        foreach ($listTypes as $type) {
            $listChampLibreReception = $champLibreRepository->findByType($type['id']);

            foreach ($listChampLibreReception as $champLibre) {
                $valeurChampLibre = $valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

                $champsLibresReception[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'elements' => $champLibre->getElements() ? $champLibre->getElements() : '',
                    'defaultValue' => $champLibre->getDefaultValue(),
                    'valeurChampLibre' => $valeurChampLibre,
                ];
            }
        }

        $listTypesDL = $typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);
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

        return $this->render("reception/show.html.twig", [
            'reception' => $reception,
            'type' => $typeRepository->findOneByCategoryLabel(CategoryType::RECEPTION),
            'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
            'typeId' => $reception->getType() ? $reception->getType()->getId() : '',
            'valeurChampLibreTab' => $valeurChampLibreTab,
            'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, true),
            'typesLitige' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
            'acheteurs' => $this->utilisateurRepository->getIdAndLibelleBySearch(''),
            'typeChampsLibres' => $champsLibresReception,
            'typeChampsLibresDL' => $typeChampLibreDL,
            'createDL' => $createDL ? $createDL->getValue() : false,
            'livraisonLocation' => $globalParamService->getLivraisonDefaultLocation(),
            'fieldsParam' => $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION),
            'defaultLitigeStatusId' => $paramGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_STATUT_LITIGE_REC),
        ]);
    }

    /**
     * @Route("/autocomplete-art{reception}", name="get_article_reception", options={"expose"=true}, methods="GET|POST")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getArticles(Request $request, Reception $reception)
    {
        if ($request->isXmlHttpRequest()) {
            $articles = [];
            foreach ($reception->getReceptionReferenceArticles() as $rra) {
                foreach ($rra->getArticles() as $article) {
                    $articles[] = [
                        'id' => $article->getId(),
                        'text' => $article->getBarCode()
                    ];
                }
            }

            return new JsonResponse(['results' => $articles]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete-ref-art/{reception}", name="get_ref_article_reception", options={"expose"=true}, methods="GET")
     *
     * @param Request $request
     * @param Reception $reception
     *
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getRefTypeQtyArticle(Request $request, Reception $reception, EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $ref = array_map(
                function ($item) {
                    return [
                        'id' => "{$item['reference']}_{$item['commande']}",
                        'reference' => $item['reference'],
                        'commande' => $item['commande'],
                        'text' => "{$item['reference']} – {$item['commande']}"
                    ];
                },
                $referenceArticleRepository->getRefTypeQtyArticleByReception($reception->getId())
            );

            return new JsonResponse(['results' => $ref]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ligne-article-conditionnement", name="get_ligne_article_conditionnement", options={"expose"=true}, methods="GET")
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getLigneArticleCondtionnement(Request $request, EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $reference = $request->query->get('reference');
            $commande = $request->query->get('commande');
            $quantity = $request->query->get('quantity');

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
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function editLitige(EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = $litigeRepository->find($post->get('id'));
            $typeBefore = $litige->getType()->getId();
            $typeBeforeName = $litige->getType()->getLabel();
            $typeAfter = (int)$post->get('typeLitige');
            $statutBefore = $litige->getStatus()->getId();
            $statutBeforeName = $litige->getStatus()->getNom();
            $statutAfter = (int)$post->get('statutLitige');
            $litige
                ->setUpdateDate(new \DateTime('now'))
                ->setType($typeRepository->find($post->get('typeLitige')))
                ->setStatus($statutRepository->find($post->get('statutLitige')));

            if (!empty($colis = $post->get('colis'))) {
                // on détache les colis existants...
                $existingColis = $litige->getArticles();
                foreach ($existingColis as $coli) {
                    $litige->removeArticle($coli);
                }
                // ... et on ajoute ceux sélectionnés
                $listColis = explode(',', $colis);
                foreach ($listColis as $colisId) {
                    $article = $articleRepository->find($colisId);
                    $litige->addArticle($article);
                    $ligneIsUrgent = $article->getReceptionReferenceArticle() && $article->getReceptionReferenceArticle()->getEmergencyTriggered();
                    if ($ligneIsUrgent) {
                        $litige->setEmergencyTriggered(true);
                    }
                }
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
            if ($statutBefore !== $statutAfter) {
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
            $attachments = $litige->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, null, $litige);
                }
            }

            $this->createAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
            $entityManager->flush();

            $response = [];
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer-litige", name="litige_new_reception", options={"expose"=true}, methods={"POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function newLitige(EntityManagerInterface $entityManager,
                              Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = new Litige();
            $litige
                ->setStatus($statutRepository->find($post->get('statutLitige')))
                ->setType($typeRepository->find($post->get('typeLitige')))
                ->setCreationDate(new \DateTime('now'));

            if (!empty($colis = $post->get('colisLitige'))) {
                $listColisId = explode(',', $colis);
                foreach ($listColisId as $colisId) {
                    $article = $articleRepository->find($colisId);
                    $litige->addArticle($article);
                    $ligneIsUrgent = $article->getReceptionReferenceArticle() && $article->getReceptionReferenceArticle()->getEmergencyTriggered();
                    if ($ligneIsUrgent) {
                        $litige->setEmergencyTriggered(true);
                    }
                }
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
            $this->sendMailToAcheteurs($litige);
            $response = [];

            return new JsonResponse($response);
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
                'typesLitige' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, true),
                'attachements' => $pieceJointeRepository->findBy(['litige' => $litige]),
                'acheteurs' => $utilisateurRepository->getIdAndLibelleBySearch(''),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode, 'acheteurs' => $acheteursCode]);
        }
        throw new NotFoundHttpException("404");
    }

    private function sendMailToAcheteurs(Litige $litige)
    {
        $acheteursEmail = $litige->getBuyers()->toArray();
        /** @var Utilisateur $buyer */
        foreach ($acheteursEmail as $buyer) {
            $title = 'Un litige a été déclaré sur une réception vous concernant :';
            $this->mailerService->sendMail(
                'FOLLOW GT // Litige sur réception',
                $this->renderView('mails/mailLitigesReception.html.twig', [
                    'litiges' => [$litige],
                    'title' => $title,
                    'urlSuffix' => 'reception'
                ]),
                $buyer->getEmail()
            );
        }
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_reception", options={"expose"=true}, methods="GET|POST")
     */
    public function deleteLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $litige = $this->litigeRepository->find($data['litige']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($litige);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/litiges/api/{reception}", name="litige_reception_api", options={"expose"=true}, methods="GET|POST")
     */
    public function apiReceptionLitiges(Request $request, Reception $reception): Response
    {
        if ($request->isXmlHttpRequest()) {

            /** @var Litige[] $litiges */
            $litiges = $this->litigeRepository->findByReception($reception);

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
     * @throws NonUniqueResultException
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
                    $this->validateReception($reception, $listReceptionReferenceArticle, $mouvementTracaService);
                    return new JsonResponse(1);
                } else {
                    $partielle = false;
                    foreach ($listReceptionReferenceArticle as $receptionRA) {
                        if ($receptionRA->getQuantite() !== $receptionRA->getQuantiteAR()) $partielle = true;
                    }
                    if (!$partielle) $this->validateReception($reception, $listReceptionReferenceArticle, $mouvementTracaService);
                    return new JsonResponse($partielle ? 0 : 1);
                }
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @param Reception $reception
     * @param ReceptionReferenceArticle[] $listReceptionReferenceArticle
     * @param MouvementTracaService $mouvementTracaService
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function validateReception($reception, $listReceptionReferenceArticle, MouvementTracaService $mouvementTracaService)
    {
        $entityManager = $this->getDoctrine()->getManager();

        $statutRepository = $entityManager->getRepository(Statut::class);

        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_TOTALE);
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $receptionLocation = $reception->getLocation();
        $currentUser = $this->getUser();

        foreach ($listReceptionReferenceArticle as $receptionRA) {
            $referenceArticle = $receptionRA->getReferenceArticle();
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticle->setQuantiteStock(($referenceArticle->getQuantiteStock() ?? 0) + $receptionRA->getQuantite());

                $mouvementStock = new MouvementStock();
                $mouvementStock
                    ->setUser($currentUser)
                    ->setEmplacementTo($receptionLocation)
                    ->setQuantity($receptionRA->getQuantite())
                    ->setRefArticle($referenceArticle)
                    ->setType(MouvementStock::TYPE_ENTREE)
                    ->setReceptionOrder($reception)
                    ->setDate($now);
                $entityManager->persist($mouvementStock);


                $entityManager->persist($mouvementTracaService->createMouvementTraca(
                    $referenceArticle->getBarCode(),
                    $receptionLocation,
                    $currentUser,
                    $now,
                    false,
                    true,
                    MouvementTraca::TYPE_DEPOSE,
                    [
                        'mouvementStock' => $mouvementStock,
                        'from' => $reception
                    ]
                ));
            } else {
                $articles = $receptionRA->getArticles();
                foreach ($articles as $article) {
                    $mouvementStock = new MouvementStock();
                    $mouvementStock
                        ->setUser($currentUser)
                        ->setEmplacementTo($receptionLocation)
                        ->setQuantity($article->getQuantite())
                        ->setArticle($article)
                        ->setType(MouvementStock::TYPE_ENTREE)
                        ->setReceptionOrder($reception)
                        ->setDate($now);
                    $entityManager->persist($mouvementStock);

                    $entityManager->persist($mouvementTracaService->createMouvementTraca(
                        $article->getBarCode(),
                        $receptionLocation,
                        $currentUser,
                        $now,
                        false,
                        true,
                        MouvementTraca::TYPE_DEPOSE,
                        [
                            'mouvementStock' => $mouvementStock,
                            'from' => $reception
                        ]
                    ));
                }
            }
        }

        $reception
            ->setStatut($statut)
            ->setDateFinReception($now)
            ->setDateCommande($now);

        $entityManager->flush();
    }

    /**
     * @Route("/article-fournisseur", name="get_article_fournisseur", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse|RedirectResponse
     */
    public function getArticleFournisseur(Request $request, EntityManagerInterface $entityManager)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

            $json = null;
            $refArticle = $referenceArticleRepository->find($data['referenceArticle']);

            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $fournisseur = $fournisseurRepository->find($data['fournisseur']);
                $articlesFournisseurs = $articleFournisseurRepository->getByRefArticleAndFournisseur($refArticle, $fournisseur);
                if ($articlesFournisseurs !== null) {
                    $json = [
                        "option" => $this->renderView(
                            'reception/optionArticleFournisseur.html.twig',
                            [
                                'articlesFournisseurs' => $articlesFournisseurs,
                            ]
                        )
                    ];
                }
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
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

            $types = $typeRepository->findByCategoryLabel(CategoryType::ARTICLE);

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
                'types' => $typeRepository->findByCategoryLabel(CategoryType::ARTICLE),
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
            if ($nbArticles == 0) {
                $delete = true;
                $html = $this->renderView('reception/modalDeleteLigneArticleRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('reception/modalDeleteLigneArticleWrong.html.twig');
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
     * @param LigneArticle $ligneArticle
     * @param RefArticleDataService $refArticleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
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
     * @Route("/receptions-infos", name="get_receptions_for_csv", options={"expose"=true}, methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getReceptionIntels(EntityManagerInterface $entityManager,
                                       Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $dateMin = $data['dateMin'] . ' 00:00:00';
            $dateMax = $data['dateMax'] . ' 23:59:59';

            $dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
            $dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $receptionRepository = $entityManager->getRepository(Reception::class);
            $receptions = $receptionRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [];
            $headers = array_merge($headers,
                [
                    $this->translationService->getTranslation('réception', 'n° de réception'),
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
                ]);

            $data = [];
            $data[] = $headers;

            foreach ($receptions as $reception) {
                $this->buildInfos($reception, $data);
            }
            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    private function buildInfos(Reception $reception, &$data)
    {
        foreach ($reception->getReceptionReferenceArticles() as $receptionReferenceArticle) {
            $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
            $data[] = [
                $reception->getNumeroReception() ?? '',
                $reception->getReference() ?? '',
                $reception->getFournisseur() ? $reception->getFournisseur()->getNom() : '',
                $reception->getUtilisateur() ? $reception->getUtilisateur()->getUsername() : '',
                $reception->getStatut() ? $reception->getStatut()->getNom() : '',
                $reception->getDate() ? $reception->getDate()->format('d/m/Y H:i') : '',
                $reception->getDateFinReception() ? $reception->getDateFinReception()->format('d/m/Y H:i') : '',
                strip_tags($reception->getCommentaire()),
                $receptionReferenceArticle->getQuantiteAR(),
                $receptionReferenceArticle->getQuantite(),
                $referenceArticle->getReference(),
                $referenceArticle->getLibelle(),
                $referenceArticle->getQuantiteStock(),
                $referenceArticle->getType() ? $referenceArticle->getType()->getLabel() : '',
                $referenceArticle->getBarCode(),
                '',
            ];
            $articles = $receptionReferenceArticle->getArticles();
            foreach ($articles as $article) {
                $data[] = [
                    $reception->getNumeroReception() ?? '',
                    $reception->getReference() ?? '',
                    $reception->getFournisseur() ? $reception->getFournisseur()->getNom() : '',
                    $reception->getUtilisateur() ? $reception->getUtilisateur()->getUsername() : '',
                    $reception->getStatut() ? $reception->getStatut()->getNom() : '',
                    $reception->getDate() ? $reception->getDate()->format('d/m/Y H:i') : '',
                    $reception->getDateFinReception() ? $reception->getDateFinReception()->format('d/m/Y H:i') : '',
                    strip_tags($reception->getCommentaire()),
                    $receptionReferenceArticle->getQuantiteAR(),
                    $receptionReferenceArticle->getQuantite(),
                    $article->getReference(),
                    $article->getLabel(),
                    $article->getQuantite(),
                    $article->getType() ? $article->getType()->getLabel() : '',
                    $article->getArticleFournisseur()->getReferenceArticle()->getBarCode(),
                    $article->getBarCode(),
                ];
            }
        }
    }

    /**
     * @Route("/avec-conditionnement/{reception}", name="reception_new_with_packing", options={"expose"=true})
     * @param Request $request
     * @param DemandeLivraisonService $demandeLivraisonService
     * @param EntityManagerInterface $entityManager
     * @param Reception $reception
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function newWithPacking(Request $request,
                                   DemandeLivraisonService $demandeLivraisonService,
                                   EntityManagerInterface $entityManager,
                                   Reception $reception): Response
    {
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
                if ($totalQuantity > $rra->getQuantiteAR()) {
                    return new JsonResponse(false);
                }
                $rra->setQuantite($totalQuantity);
                $entityManager->flush();
            }
            // optionnel : crée la demande de livraison
            $paramCreateDL = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
            $needCreateLivraison = $paramCreateDL ? $paramCreateDL->getValue() : false;

            if ($needCreateLivraison) {
                // optionnel : crée l'ordre de prépa
                $paramCreatePrepa = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);
                $needCreatePrepa = $paramCreatePrepa ? $paramCreatePrepa->getValue() : false;
                $data['needPrepa'] = $needCreatePrepa;

                $demande = $demandeLivraisonService->newDemande($data);
            }

            // crée les articles et les ajoute à la demande, à la réception, crée les urgences
            $receptionLocation = $reception->getLocation();
            $receptionLocationId = isset($receptionLocation) ? $receptionLocation->getId() : null;
            foreach ($articles as $article) {
                if (isset($receptionLocationId)) {
                    $article['emplacement'] = $receptionLocationId;
                }
                $this->articleDataService->newArticle($article, $demande ?? null, $reception);
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
            $entity->addAttachement($attachment);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

}
