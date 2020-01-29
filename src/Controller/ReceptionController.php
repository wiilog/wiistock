<?php

namespace App\Controller;

use App\Entity\CategorieStatut;
use App\Entity\ChampLibre;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\FieldsParam;
use App\Entity\CategorieCL;
use App\Entity\MouvementStock;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;
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
use App\Repository\InventoryCategoryRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ReceptionRepository;
use App\Repository\ReceptionReferenceArticleRepository;
use App\Repository\TransporteurRepository;

use App\Service\DemandeLivraisonService;
use App\Service\MouvementStockService;
use App\Service\ReceptionService;
use App\Service\AttachmentService;
use App\Service\ArticleDataService;
use App\Service\RefArticleDataService;
use App\Service\UserService;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var TransporteurRepository
     */
    private $transporteurRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ReceptionReferenceArticleRepository
     */
    private $receptionReferenceArticleRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

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
     * @var InventoryCategoryRepository
     */
    private $inventoryCategoryRepository;

    /**
     * @var ParametrageGlobalRepository
     */
    private $paramGlobalRepository;

	/**
	 * @var MouvementStockService
	 */
    private $mouvementStockService;

    public function __construct(
        ArticleDataService $articleDataService,
        DimensionsEtiquettesRepository $dimensionsEtiquettesRepository,
        TypeRepository $typeRepository,
        ChampLibreRepository $champLibreRepository,
        ValeurChampLibreRepository $valeurChampsLibreRepository,
        FournisseurRepository $fournisseurRepository,
        StatutRepository $statutRepository,
        ReferenceArticleRepository $referenceArticleRepository,
        ReceptionRepository $receptionRepository,
        UtilisateurRepository $utilisateurRepository,
        EmplacementRepository $emplacementRepository,
        ArticleRepository $articleRepository,
        ArticleFournisseurRepository $articleFournisseurRepository,
        UserService $userService,
        ReceptionReferenceArticleRepository $receptionReferenceArticleRepository,
        InventoryCategoryRepository $inventoryCategoryRepository,
        PieceJointeRepository $pieceJointeRepository,
        ReceptionService $receptionService,
        LitigeRepository $litigeRepository,
        AttachmentService $attachmentService,
        FieldsParamRepository $fieldsParamRepository,
        TransporteurRepository $transporteurRepository,
        ParametrageGlobalRepository $parametrageGlobalRepository,
		MouvementStockService $mouvementStockService
    )
    {
        $this->paramGlobalRepository = $parametrageGlobalRepository;
        $this->inventoryCategoryRepository = $inventoryCategoryRepository;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->litigeRepository = $litigeRepository;
        $this->attachmentService = $attachmentService;
        $this->receptionService = $receptionService;
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->receptionRepository = $receptionRepository;
        $this->receptionReferenceArticleRepository = $receptionReferenceArticleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleRepository = $articleRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->valeurChampLibreRepository = $valeurChampsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->transporteurRepository = $transporteurRepository;
        $this->fieldsParamRepository = $fieldsParamRepository;
        $this->mouvementStockService = $mouvementStockService;
    }


    /**
     * @Route("/new", name="reception_new", options={"expose"=true}, methods="POST")
     */
    public function new(Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $type = $this->typeRepository->findOneByCategoryLabel(CategoryType::RECEPTION);
            $reception = new Reception();

            $statutLabel = $data['anomalie'] ? Reception::STATUT_ANOMALIE : Reception::STATUT_EN_ATTENTE;
            $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Reception::CATEGORIE, $statutLabel);

            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));

            // génère le numéro
            $lastNumero = $this->receptionRepository->getLastNumeroByPrefixeAndDate('R', $date->format('ymd'));
            $lastCpt = (int)substr($lastNumero, -4, 4);
            $i = $lastCpt + 1;
            $cpt = sprintf('%04u', $i);
            $numero = 'R' . $date->format('ymd') . $cpt;

            if (!empty($data['fournisseur'])) {
                $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
                $reception
                    ->setFournisseur($fournisseur);
            }

            if (!empty($data['transporteur'])) {
                $transporteur = $this->transporteurRepository->find(intval($data['transporteur']));
                $reception
                    ->setTransporteur($transporteur);
            }

            $reception
                ->setReference($data['reference'])
                ->setDateAttendue(
                    !empty($data['dateAttendue'])
                        ?
                        new DateTime(str_replace('/', '-', $data['dateAttendue']), new DateTimeZone("Europe/Paris"))
                        :
                        null)
                ->setDateCommande(
                    !empty($data['dateCommande'])
                        ?
                        new DateTime(str_replace('/', '-', $data['dateCommande']), new DateTimeZone("Europe/Paris"))
                        :
                        null)
                ->setCommentaire($data['commentaire'])
                ->setStatut($statut)
                ->setNumeroReception($numero)
                ->setDate($date)
                ->setReference($data['reference'])
                ->setUtilisateur($this->getUser())
                ->setType($type)
                ->setCommentaire($data['commentaire']);

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
                        ->setChampLibre($this->champLibreRepository->find($champs));

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
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statut = $this->statutRepository->find(intval($data['statut']));
            $reception = $this->receptionRepository->find($data['receptionId']);

            if ($data['fournisseur'] != null) {
                $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
                $reception
                    ->setFournisseur($fournisseur);
            }

            if ($data['utilisateur'] != null) {
                $utilisateur = $this->utilisateurRepository->find(intval($data['utilisateur']));
                $reception
                    ->setUtilisateur($utilisateur);
            }

            if ($data['transporteur'] != null) {
                $transporteur = $this->transporteurRepository->find(intval($data['transporteur']));
                $reception
                    ->setTransporteur($transporteur);
            }

            $reception
                ->setReference($data['numeroCommande'])
                ->setDateAttendue(
                    !empty($data['dateAttendue'])
                        ?
                        new DateTime(str_replace('/', '-', $data['dateAttendue']), new DateTimeZone("Europe/Paris"))
                        :
                        null)
                ->setDateCommande(
                    !empty($data['dateCommande'])
                        ?
                        new DateTime(str_replace('/', '-', $data['dateCommande']), new DateTimeZone("Europe/Paris"))
                        :
                        null)
                ->setNumeroReception($data['numeroReception'])
                ->setStatut($statut)
                ->setCommentaire($data['commentaire']);

            $em = $this->getDoctrine()->getManager();
            $em->flush();

            $champLibreKey = array_keys($data);
            foreach ($champLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $this->champLibreRepository->find($champ);
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);
                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addReception($reception)
                            ->setChampLibre($this->champLibreRepository->find($champ));
                        $em->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur(is_array($data[$champ]) ? implode(";", $data[$champ]) : $data[$champ]);
                    $em->flush();
                }
            }
            $type = $reception->getType();

            $valeurChampLibreTab = empty($type) ? [] : $this->valeurChampLibreRepository->getByReceptionAndType($reception, $type);
            $champsLibres = [];
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);
            foreach ($listTypes as $type) {
                $listChampLibreReception = $this->champLibreRepository->findByTypeId($type['id']);

                foreach ($listChampLibreReception as $champLibre) {
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

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
                    'typeChampsLibres' => $champsLibres
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api-modifier", name="api_reception_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $reception = $this->receptionRepository->find($data['id']);

            $listType = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);

            $typeChampLibre = [];
            foreach ($listType as $type) {
                $champsLibresComplet = $this->champLibreRepository->findByTypeId($type['id']);
                $champsLibres = [];
                //création array edit pour vue
                foreach ($champsLibresComplet as $champLibre) {
                    $valeurChampReception = $this->valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);
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
                'statuts' => $this->statutRepository->findByCategorieName(Reception::CATEGORIE),
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

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="reception_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function articleApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }

            $reception = $this->receptionRepository->find($id);
            $ligneArticles = $this->receptionReferenceArticleRepository->findByReception($reception);

            $rows = [];
            foreach ($ligneArticles as $ligneArticle) {
                $rows[] =
                    [
                        "Référence" => ($ligneArticle->getReferenceArticle() ? $ligneArticle->getReferenceArticle()->getReference() : ''),
                        "Commande" => ($ligneArticle->getCommande() ? $ligneArticle->getCommande() : ''),
                        "A recevoir" => ($ligneArticle->getQuantiteAR() ? $ligneArticle->getQuantiteAR() : ''),
                        "Reçu" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : ''),
                        "Urgence" => ($ligneArticle->getReferenceArticle()->getIsUrgent() ?? false),
                        'Actions' => $this->renderView(
                            'reception/datatableLigneRefArticleRow.html.twig',
                            [
                                'ligneId' => $ligneArticle->getId(),
                                'type' => $ligneArticle->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE ? 'list' : 'print',
                                'refArticle' => $ligneArticle->getReferenceArticle()->getReference(),
                                'modifiable' => ($reception->getStatut()->getNom() !== (Reception::STATUT_RECEPTION_TOTALE)),
                            ]

                        ),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reception_index", methods={"GET", "POST"}, options={"expose"=true})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
            return $this->redirectToRoute('access_denied');
        }

        //TODO à modifier si plusieurs types possibles pour une réception
        $listType = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);
        $fieldsParam = $this->fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

        $typeChampLibre = [];
        foreach ($listType as $type) {
            $champsLibres = $this->champLibreRepository->findByTypeId($type['id']);
            $typeChampLibre[] = [
                'typeLabel' => $type['label'],
                'typeId' => $type['id'],
                'champsLibres' => $champsLibres,
            ];
        }

        return $this->render('reception/index.html.twig', [
            'typeChampLibres' => $typeChampLibre,
            'fieldsParam' => $fieldsParam,
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::RECEPTION)
        ]);
    }

    /**
     * @Route("/supprimer", name="reception_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $reception = $this->receptionRepository->find($data['receptionId']);

            $entityManager = $this->getDoctrine()->getManager();
            foreach ($reception->getReceptionReferenceArticles() as $receptionArticle) {
                $entityManager->remove($receptionArticle);
                $this->articleRepository->setNullByReception($receptionArticle);
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
     */
    public function removeArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->receptionReferenceArticleRepository->find($data['ligneArticle']);

            if (!$ligneArticle) return new JsonResponse(false);

            $reception = $ligneArticle->getReception();
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($ligneArticle);
            $entityManager->flush();
            $nbArticleNotConform = $this->receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statutLabel = $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Reception::CATEGORIE, $statutLabel);
            $reception->setStatut($statut);
            $type = $reception->getType();

            $valeurChampLibreTab = empty($type) ? [] : $this->valeurChampLibreRepository->getByReceptionAndType($reception, $type);

            $champsLibres = [];
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);
            foreach ($listTypes as $type) {
                $listChampLibreReception = $this->champLibreRepository->findByTypeId($type['id']);

                foreach ($listChampLibreReception as $champLibre) {
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

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
                    'typeChampsLibres' => $champsLibres
                ])
            ];
            $entityManager->flush();
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/add-article", name="reception_article_add", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $contentData = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle = $this->referenceArticleRepository->find($contentData['referenceArticle']);
            $reception = $this->receptionRepository->find($contentData['reception']);
//            $fournisseur = $this->fournisseurRepository->find(intval($contentData['fournisseur']));
            $anomalie = $contentData['anomalie'];
            if ($anomalie) {
                $statutRecep = $this->statutRepository->findOneByCategorieNameAndStatutName(Reception::CATEGORIE, Reception::STATUT_ANOMALIE);
                $reception->setStatut($statutRecep);
            }

            $receptionReferenceArticle = new ReceptionReferenceArticle;
            $receptionReferenceArticle
                ->setCommande($contentData['commande'])
                ->setAnomalie($contentData['anomalie'])
                ->setCommentaire($contentData['commentaire'])
//                ->setFournisseur($fournisseur)
                ->setReferenceArticle($refArticle)
                ->setQuantiteAR(max($contentData['quantiteAR'], 0))// protection contre quantités négatives
                ->setReception($reception);

            if (array_key_exists('quantite', $contentData) && $contentData['quantite']) {
                $receptionReferenceArticle->setQuantite(max($contentData['quantite'], 0));
            }

//            if (array_key_exists('articleFournisseur', $contentData) && $contentData['articleFournisseur']) {
//                $articleFournisseur = $this->articleFournisseurRepository->find($contentData['articleFournisseur']);
//                $receptionReferenceArticle->setArticleFournisseur($articleFournisseur);
//            }
            $em = $this->getDoctrine()->getManager();
            $em->persist($receptionReferenceArticle);
            $em->flush();

            $type = $reception->getType();
            $valeurChampLibreTab = empty($type) ? [] : $this->valeurChampLibreRepository->getByReceptionAndType($reception, $type);

            $champsLibres = [];
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);
            foreach ($listTypes as $oneType) {
                $listChampLibreReception = $this->champLibreRepository->findByTypeId($oneType['id']);

                foreach ($listChampLibreReception as $champLibre) {
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

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
                    'typeChampsLibres' => $champsLibres
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier-article", name="reception_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEditArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->receptionReferenceArticleRepository->find($data['id']);
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
     */
    public function editArticle(Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) { //Si la requête est de type Xml
            $receptionReferenceArticle = $this->receptionReferenceArticleRepository->find($data['article']);
//            $fournisseur = $this->fournisseurRepository->find($data['fournisseur']);
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            $reception = $receptionReferenceArticle->getReception();

            $receptionReferenceArticle
                ->setCommande($data['commande'])
                ->setAnomalie($data['anomalie'])
//                ->setFournisseur($fournisseur)
                ->setReferenceArticle($refArticle)
                ->setQuantiteAR(max($data['quantiteAR'], 0))// protection contre quantités négatives
                ->setCommentaire($data['commentaire']);

            $typeQuantite = $receptionReferenceArticle->getReferenceArticle()->getTypeQuantite();
            if ($typeQuantite == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $receptionReferenceArticle->setQuantite(max($data['quantite'], 0)); // protection contre quantités négatives
            }

            if (array_key_exists('articleFournisseur', $data) && $data['articleFournisseur']) {
                $articleFournisseur = $this->articleFournisseurRepository->find($data['articleFournisseur']);
                $receptionReferenceArticle->setArticleFournisseur($articleFournisseur);
            }

            $em = $this->getDoctrine()->getManager();
            $em->flush();


            $nbArticleNotConform = $this->receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statutLabel = $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Reception::CATEGORIE, $statutLabel);
            $reception->setStatut($statut);
            $em->flush();
            $type = $reception->getType();

            $valeurChampLibreTab = empty($type) ? [] : $this->valeurChampLibreRepository->getByReceptionAndType($reception, $type);

            $champsLibres = [];
            $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);
            foreach ($listTypes as $oneType) {
                $listChampLibreReception = $this->champLibreRepository->findByTypeId($oneType['id']);

                foreach ($listChampLibreReception as $champLibre) {
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

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
                    'typeChampsLibres' => $champsLibres
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="reception_show", methods={"GET", "POST"})
     */
    public function show(Reception $reception): Response
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
            return $this->redirectToRoute('access_denied');
        }

        $type = $reception->getType();
        if ($type) {
            $valeurChampLibreTab = $this->valeurChampLibreRepository->getByReceptionAndType($reception, $type);
        } else {
            $valeurChampLibreTab = [];
        }

        $listTypes = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);
        $champsLibresReception = [];
        foreach ($listTypes as $type) {
            $listChampLibreReception = $this->champLibreRepository->findByTypeId($type['id']);

            foreach ($listChampLibreReception as $champLibre) {
                $valeurChampLibre = $this->valeurChampLibreRepository->findOneByReceptionAndChampLibre($reception, $champLibre);

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

        $listTypesDL = $this->typeRepository->findByCategoryLabel(CategoryType::DEMANDE_LIVRAISON);
        $typeChampLibreDL = [];
        foreach ($listTypesDL as $typeDL) {
            $champsLibresDL = $this->champLibreRepository->findByTypeAndCategorieCLLabel($typeDL, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibreDL[] = [
                'typeLabel' => $typeDL->getLabel(),
                'typeId' => $typeDL->getId(),
                'champsLibres' => $champsLibresDL,
            ];
        }

        return $this->render("reception/show.html.twig", [
            'reception' => $reception,
            'type' => $this->typeRepository->findOneByCategoryLabel(Reception::CATEGORIE),
            'modifiable' => ($reception->getStatut()->getNom() !== (Reception::STATUT_RECEPTION_TOTALE)),
            'statuts' => $this->statutRepository->findByCategorieName(Reception::CATEGORIE),
            'typeId' => $reception->getType() ? $reception->getType()->getId() : '',
            'valeurChampLibreTab' => $valeurChampLibreTab,
            'statusLitige' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, true),
            'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
            'acheteurs' => $this->utilisateurRepository->getIdAndLibelleBySearch(''),
            'typeChampsLibres' => $champsLibresReception,
            'typeChampsLibresDL' => $typeChampLibreDL,
            'createDL' => $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION)->getParametre()
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
     * @return JsonResponse
     */
    public function getRefTypeQtyArticle(Request $request, Reception $reception)
    {
        if ($request->isXmlHttpRequest()) {
            $ref = array_map(
                function ($item) {
                    return [
                        'id' => "{$item['reference']}_{$item['commande']}",
                        'reference' => $item['reference'],
                        'commande' => $item['commande'],
                        'text' => "{$item['reference']} – {$item['commande']}"
                    ];
                },
                $this->referenceArticleRepository->getRefTypeQtyArticleByReception($reception->getId())
            );

            return new JsonResponse(['results' => $ref]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ligne-article-conditionnement", name="get_ligne_article_conditionnement", options={"expose"=true}, methods="GET")
     *
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getLigneArticleCondtionnement(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $reference = $request->query->get('reference');
            $commande = $request->query->get('commande');
            $quantity = $request->query->get('quantity');

            // TODO verif null

            /** @var ReferenceArticle $refArticle */
            $refArticle = $this->referenceArticleRepository->findOneByReference($reference);

            $typeArticle = $refArticle->getType();

            $champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
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
                    'typeArticle' => $typeArticle->getLabel(),
                    'champsLibres' => $champsLibres,
					'references' => $this->articleFournisseurRepository->getIdAndLibelleByRef($refArticle)
                ]
            ));
            return $response;
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_reception",  options={"expose"=true}, methods="GET|POST")
     */
    public function editLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $litige = $this->litigeRepository->find($post->get('id'));
            $typeBefore = $litige->getType()->getId();
            $typeBeforeName = $litige->getType()->getLabel();
            $typeAfter = (int)$post->get('typeLitige');
            $statutBefore = $litige->getStatus()->getId();
            $statutBeforeName = $litige->getStatus()->getNom();
            $statutAfter = (int)$post->get('statutLitige');
            $litige
                ->setUpdateDate(new \DateTime('now'))
                ->setType($this->typeRepository->find($post->get('typeLitige')))
                ->setStatus($this->statutRepository->find($post->get('statutLitige')));

            if (!empty($colis = $post->get('colis'))) {
                // on détache les colis existants...
                $existingColis = $litige->getArticles();
                foreach ($existingColis as $coli) {
                    $litige->removeArticle($coli);
                }
                // ... et on ajoute ceux sélectionnés
                $listColis = explode(',', $colis);
                foreach ($listColis as $colisId) {
                    $litige->addArticle($this->articleRepository->find($colisId));
                }
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
                    $litige->addBuyer($this->utilisateurRepository->find($buyerId));
                }
            }
            $em->flush();

            $comment = '';
            $statutinstance = $this->statutRepository->find($post->get('statutLitige'));
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
                $em->persist($histoLitige);
                $em->flush();
            }

            $listAttachmentIdToKeep = $post->get('files') ?? [];
            $attachments = $litige->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, null, $litige);
                }
            }

            $this->attachmentService->addAttachements($request->files, null, $litige);
            $em->flush();

            $response = [];
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer-litige", name="litige_new_reception", options={"expose"=true}, methods={"POST"})
     */
    public function newLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;
            $em = $this->getDoctrine()->getManager();

            $litige = new Litige();
            $litige
                ->setStatus($this->statutRepository->find($post->get('statutLitige')))
                ->setType($this->typeRepository->find($post->get('typeLitige')))
                ->setCreationDate(new \DateTime('now'));

            if (!empty($colis = $post->get('colisLitige'))) {
                $listColisId = explode(',', $colis);
                foreach ($listColisId as $colisId) {
                    $litige->addArticle($this->articleRepository->find($colisId));
                }
            }
            if (!empty($buyers = $post->get('acheteursLitige'))) {
                $listBuyers = explode(',', $buyers);
                foreach ($listBuyers as $buyer) {
                    $litige->addBuyer($this->utilisateurRepository->find($buyer));
                }
            }
            $statutinstance = $this->statutRepository->find($post->get('statutLitige'));
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
                $em->persist($histo);
            }

            $em->persist($litige);
            $em->flush();

            $this->attachmentService->addAttachements($request->files, null, $litige);
            $em->flush();
            $this->sendMailToAcheteurs($litige);
            $response = [];

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit_reception", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEditLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $litige = $this->litigeRepository->find($data['litigeId']);
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
                'typesLitige' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'statusLitige' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, true),
                'attachements' => $this->pieceJointeRepository->findBy(['litige' => $litige]),
                'acheteurs' => $this->utilisateurRepository->getIdAndLibelleBySearch(''),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode, 'acheteurs' => $acheteursCode]);
        }
        throw new NotFoundHttpException("404");
    }

    private function sendMailToAcheteurs(Litige $litige)
    {
        $acheteursEmail = $this->litigeRepository->getAcheteursArrivageByLitigeId($litige->getId());
        foreach ($acheteursEmail as $email) {
            $title = 'Un litige a été déclaré sur une réception vous concernant :';

            $this->mailerService->sendMail(
                'FOLLOW GT // Litige sur réception',
                $this->renderView('mails/mailLitigesReception.html.twig', [
                    'litiges' => [$litige],
                    'title' => $title,
                    'urlSuffix' => 'reception'
                ]),
                $email
            );
        }
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_reception", options={"expose"=true}, methods="GET|POST")
     */
    public function deleteLitige(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::DELETE)) {
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
     * @return Response
     * @throws NonUniqueResultException
     */
    public function finish(Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $reception = $this->receptionRepository->find($data['id']);
            $listReceptionReferenceArticle = $this->receptionReferenceArticleRepository->findByReception($reception);

            if (empty($listReceptionReferenceArticle)) {
                return new JsonResponse('Vous ne pouvez pas finir une réception sans article.');
            } else {
                if ($data['confirmed'] === true) {
                    $this->validateReception($reception, $listReceptionReferenceArticle);
                    return new JsonResponse(1);
                } else {
                    $partielle = false;
                    foreach ($listReceptionReferenceArticle as $receptionRA) {
                        if ($receptionRA->getQuantite() !== $receptionRA->getQuantiteAR()) $partielle = true;
                    }
                    if (!$partielle) $this->validateReception($reception, $listReceptionReferenceArticle);
                    return new JsonResponse($partielle ? 0 : 1);
                }
            }
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @param Reception $reception
	 * @param ReceptionReferenceArticle[] $listReceptionReferenceArticle
	 * @throws NonUniqueResultException
	 */
    private function validateReception($reception, $listReceptionReferenceArticle)
    {
    	$em = $this->getDoctrine()->getManager();
        $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Reception::CATEGORIE, Reception::STATUT_RECEPTION_TOTALE);
		$now = new DateTime('now', new DateTimeZone('Europe/Paris'));

		foreach ($listReceptionReferenceArticle as $receptionRA) {
            $referenceArticle = $receptionRA->getReferenceArticle();
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticle->setQuantiteStock($referenceArticle->getQuantiteStock() + $receptionRA->getQuantite());

                $mouvementStock = new MouvementStock();
                $mouvementStock
					->setUser($this->getUser())
					->setEmplacementTo($referenceArticle->getEmplacement())
					->setQuantity($receptionRA->getQuantite())
					->setRefArticle($referenceArticle)
					->setType(MouvementStock::TYPE_ENTREE)
					->setReceptionOrder($reception)
					->setDate($now);
				$em->persist($mouvementStock);
            } else {
				$articles = $receptionRA->getArticles();
				foreach ($articles as $article) {
					$mouvementStock = new MouvementStock();
					$mouvementStock
						->setUser($this->getUser())
						->setEmplacementTo($article->getEmplacement())
						->setQuantity($article->getQuantite())
						->setArticle($article)
						->setType(MouvementStock::TYPE_ENTREE)
						->setReceptionOrder($reception)
						->setDate($now);
					$em->persist($mouvementStock);
				}
			}
        }

        $reception
            ->setStatut($statut)
            ->setDateFinReception($now)
            ->setDateCommande($now);

        $em->flush();
    }

    /**
     * @Route("/article-stock", name="get_article_stock", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticleStock(Request $request)
    {
        if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
            return $this->redirectToRoute('access_denied');
        }

        $id = $request->request->get('id');
        $quantiteStock = $this->referenceArticleRepository->getQuantiteStockById($id);

        return new JsonResponse($quantiteStock);
    }

    /**
     * @Route("/article-fournisseur", name="get_article_fournisseur", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticleFournisseur(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }
            $json = null;
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);

            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $fournisseur = $this->fournisseurRepository->find($data['fournisseur']);
                $articlesFournisseurs = $this->articleFournisseurRepository->getByRefArticleAndFournisseur($refArticle, $fournisseur);
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
     */
    public function checkIfQuantityArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_REF_FROM_RECEP)) {
                return $this->redirectToRoute('access_denied');
            }
            $types = $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
            $inventoryCategories = $this->inventoryCategoryRepository->findAll();
            $typeChampLibre = [];
            foreach ($types as $type) {
                $champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
                $typeChampLibre[] = [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'champsLibres' => $champsLibres,
                ];
            }
            return new JsonResponse($this->renderView('reception/modalNewRefArticle.html.twig', [
                'typeChampsLibres' => $typeChampLibre,
                'types' => $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE),
                'categories' => $inventoryCategories,
            ]));
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verif-avant-suppression", name="ligne_recep_check_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function checkBeforeLigneDelete(Request $request)
    {
        if ($request->isXmlHttpRequest() && $id = json_decode($request->getContent(), true)) {
            $nbArticles = $this->receptionReferenceArticleRepository->countArticlesByRRA($id);
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
     * @Route("/articlesRefs", name="get_article_refs", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param RefArticleDataService $refArticleDataService
     * @param ArticleDataService $articleDataService
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getAllReferences(Request $request,
                                     RefArticleDataService $refArticleDataService,
                                     ArticleDataService $articleDataService): Response
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {
            $data = $this->dimensionsEtiquettesRepository->getDimensionArray(false);
            $data['refs'] = [];
            $data['barcodeLabel'] = [];

            $reception = $this->receptionRepository->find($dataContent['reception']);
            $listReceptionReferenceArticle = $this->receptionReferenceArticleRepository->findByReception($reception);
            $wantBL = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
            foreach ($listReceptionReferenceArticle as $recepRef) {
                $referenceArticle = $recepRef->getReferenceArticle();
                if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $refBarcodeInformations = $refArticleDataService->getBarcodeInformations($referenceArticle);
                    $data['refs'][] = $refBarcodeInformations['barcode'];
                    $data['barcodeLabels'][] = $refBarcodeInformations['barcodeLabel'];
                } else {
                    foreach ($recepRef->getArticles() as $article) {
                        $articles = $this->articleRepository->getRefAndLabelRefAndArtAndBarcodeAndBLById($article->getId());
                        $wantedIndex = 0;
                        foreach ($articles as $key => $articleWithCL) {
                            if ($articleWithCL['cl'] === ChampLibre::SPECIC_COLLINS_BL) {
                                $wantedIndex = $key;
                                break;
                            }
                        }
                        $articleArray = $articles[$wantedIndex];
                        $articleBarcodeInformations = $articleDataService->getBarcodeInformations([
                            'barcode' => $article->getBarCode(),
                            'refReference' => $article->getArticleFournisseur()->getReferenceArticle()->getReference(),
                            'refLabel' => $article->getArticleFournisseur()->getReferenceArticle()->getLibelle(),
                            'artLabel' => $article->getLabel(),
                            'artBL' => (($wantBL && $wantBL->getParametre() && ($articleArray['cl'] === ChampLibre::SPECIC_COLLINS_BL))
                                ? $articleArray['bl']
                                : null)
                        ]);
                        $data['refs'][] = $articleBarcodeInformations['barcode'];
                        $data['barcodeLabel'][] = $articleBarcodeInformations['barcodeLabel'];
                    }
                }
            }

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/obtenir-ligne", name="get_ligne_from_id", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param RefArticleDataService $refArticleDataService
     * @return JsonResponse
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getLignes(Request $request,
                              RefArticleDataService $refArticleDataService)
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {
            if ($this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']))->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $articleRef = $this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']))->getReferenceArticle();
                $data = $this->dimensionsEtiquettesRepository->getDimensionArray(false);
                $barcodeInformations = $refArticleDataService->getBarcodeInformations($articleRef);
                $data['barcode'] = $barcodeInformations['barcode'];
                $data['barcodeLabel'] = $barcodeInformations['barcodeLabel'];
            } else {
                $data = [];
                $data['article'] = $this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']))->getReferenceArticle()->getReference();
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
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
     */
    public function apiArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $ligne = $request->request->get('ligne')) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligne = $this->receptionReferenceArticleRepository->find(intval($ligne));
            $data = $this->articleDataService->getDataForDatatableByReceptionLigne($ligne, $this->getUser());

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="reception_check_delete", options={"expose"=true})
     */
    public function checkReceptionCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $receptionId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_RECE)) {
                return $this->redirectToRoute('access_denied');
            }

            if ($this->receptionReferenceArticleRepository->countByReceptionId($receptionId) == 0) {
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
     */
    public function getReceptionIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$dateMin = $data['dateMin'] . ' 00:00:00';
			$dateMax = $data['dateMax'] . ' 23:59:59';

			$dateTimeMin = DateTime::createFromFormat('d/m/Y H:i:s', $dateMin);
			$dateTimeMax = DateTime::createFromFormat('d/m/Y H:i:s', $dateMax);

            $receptions = $this->receptionRepository->findByDates($dateTimeMin, $dateTimeMax);

            $headers = [];
            $headers = array_merge($headers,
                [
                    'n° réception',
                    'n° de commande',
                    'fournisseur',
                    'utilisateur',
                    'statut',
                    'date',
                    'quantité à recevoir',
                    'quantité reçue',
                    'référence',
                    'libellé',
                    'quantité stock',
                    'type',
                    'code-barre reference',
                    'code-barre article'
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
                $reception->getDate() ? $reception->getDate()->format('d/m/Y h:i') : '',
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
                    $reception->getDate() ? $reception->getDate()->format('d/m/Y h:i') : '',
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
     * @param ArticleDataService $articleDataService
     * @param Reception $reception
     * @return Response
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function newWithPacking(Request $request,
                                   DemandeLivraisonService $demandeLivraisonService,
                                   ArticleDataService $articleDataService,
                                   Reception $reception): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $articles = $data['conditionnement'];
            // protection quantité réceptionnée < quantité attendue
            $totalQuantities = [];
            foreach ($articles as $article) {
                $rra = $this->receptionReferenceArticleRepository->findOneByReceptionAndCommandeAndRefArticleId(
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
                $rra = $this->receptionReferenceArticleRepository->find($rraId);
                if ($totalQuantity > $rra->getQuantiteAR()) {
                    return new JsonResponse(false);
                } else {
                    $rra->setQuantite($totalQuantity);
                    $em->flush();
                }
            }
            // optionnel : crée la demande de livraison
            $paramCreateDL = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION);
            $needCreateLivraison = $paramCreateDL ? $paramCreateDL->getParametre() : false;

            if ($needCreateLivraison) {
                // optionnel : crée l'ordre de prépa
                $paramCreatePrepa = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL);
                $needCreatePrepa = $paramCreatePrepa ? $paramCreatePrepa->getParametre() : false;
                $data['needPrepa'] = $needCreatePrepa;

                $demande = $demandeLivraisonService->newDemande($data);
            }

            // crée les articles et les ajoute à la demande, à la réception, crée les urgences
            $response['barcodes'] = $response['barcodesLabel'] = [];
            $wantBL = $this->paramGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
            foreach ($articles as $article) {
                $createdArticle = $this->articleDataService->newArticle($article, $demande ?? null, $reception);
                $refArticle = $createdArticle->getArticleFournisseur() ? $createdArticle->getArticleFournisseur()->getReferenceArticle() : null;
                $articles = $this->articleRepository->getRefAndLabelRefAndArtAndBarcodeAndBLById($createdArticle->getId());
                $wantedIndex = 0;
                foreach ($articles as $key => $articleWithCL) {
                    if ($articleWithCL['cl'] === ChampLibre::SPECIC_COLLINS_BL) {
                        $wantedIndex = $key;
                        break;
                    }
                }
                $articleArray = $articles[$wantedIndex];

                $articleBarcodeInformations = $articleDataService->getBarcodeInformations([
                    'barcode' => $createdArticle->getBarCode(),
                    'refReference' => $refArticle ? $refArticle->getReference() : '',
                    'refLabel' => $refArticle ? $refArticle->getLibelle() : '',
                    'artLabel' => $createdArticle->getLabel(),
                    'artBL' => (($wantBL && $wantBL->getParametre() && ($articleArray['cl'] === ChampLibre::SPECIC_COLLINS_BL))
                        ? $articleArray['bl']
                        : null)
                ]);

                $response['barcodes'][] = $articleBarcodeInformations['barcode'];
                $response['barcodesLabel'][] = $articleBarcodeInformations['barcodeLabel'];
            }

            $em->flush();

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

}
