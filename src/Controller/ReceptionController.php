<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;

use App\Form\ReceptionType;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ReceptionRepository;
use App\Service\UserService;
use App\Repository\ReceptionReferenceArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\ValeurChampsLibre;
use App\Repository\ChampsLibreRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\TypeRepository;


use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\UtilisateurRepository;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\Reference;

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
     * @var ChampslibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var ReceptionReferenceArticleRepository
     */
    private $receptionReferenceArticleRepository;

    /*
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(TypeRepository  $typeRepository, ChampsLibreRepository $champsLibreRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository, UserService $userService, ReferenceArticleRepository $referenceArticleRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->receptionRepository = $receptionRepository;
        $this->receptionReferenceArticleRepository = $receptionReferenceArticleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleRepository = $articleRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->userService = $userService;
    }


    /**
     * @Route("/new", name="reception_new", options={"expose"=true}, methods="POST")
     */
    public function new(Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) { //Si data est attribuée
            $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
            $reception = new Reception();

            if ($data['anomalie'] == true) {
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_ANOMALIE);
            } else {
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_EN_ATTENTE);
            }

            $date = new \DateTime('now');
            $numeroReception = 'R' . $date->format('ymd-His'); //TODO CG ajouter numéro

            $reception
                ->setStatut($statut)
                ->setNumeroReception($numeroReception)
                ->setDate(new \DateTime($data['date-commande']))
                ->setDateAttendu(new \DateTime($data['date-attendu']))
                ->setFournisseur($fournisseur)
                ->setReference($data['reference'])
                ->setUtilisateur($this->getUser())
                ->setCommentaire($data['commentaire']);

            $em = $this->getDoctrine()->getManager();
            $em->persist($reception);
            $em->flush();

            $data = [
                    "redirect" => $this->generateUrl('reception_show', ['id' => $reception->getId()])
                ];
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="reception_edit", options={"expose"=true}, methods="POST")
     */
    public function edit(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            $fournisseur =  $this->fournisseurRepository->find(intval($data['fournisseur']));
            $utilisateur =  $this->utilisateurRepository->find(intval($data['utilisateur']));
            $statut =  $this->statutRepository->find(intval($data['statut']));

            $reception =  $this->receptionRepository->find($data['receptionId']);
            $reception
                ->setNumeroReception($data['NumeroReception'])
                ->setDate(new \DateTime($data['date-commande']))
                ->setDateAttendu(new \DateTime($data['date-attendu']))
                ->setStatut($statut)
                ->setFournisseur($fournisseur)
                ->setUtilisateur($utilisateur)
                ->setCommentaire($data['commentaire']);

            $em =  $this->getDoctrine()->getManager();
            $em->flush();
            $json = [
                'entete' =>  $this->renderView('reception/enteteReception.html.twig', [
                    'reception' =>  $reception,
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="api_reception_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEdit(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            $reception =  $this->receptionRepository->find($data);
            $json =  $this->renderView('reception/modalEditReceptionContent.html.twig', [
                'reception' =>  $reception,
                'fournisseurs' =>  $this->fournisseurRepository->getNoOne($reception->getFournisseur()->getId()),
                'utilisateurs' =>  $this->utilisateurRepository->getNoOne($reception->getUtilisateur()->getId()),
                'statuts' =>  $this->statutRepository->findByCategorieName(Reception::CATEGORIE)
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
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $receptions = $this->receptionRepository->findAll();
            $rows = [];
            foreach ($receptions as $reception) {
                $url = $this->generateUrl('reception_show', ['id' => $reception->getId()]);
                $rows[] =
                    [
                        'id' => ($reception->getId()),
                        "Statut" => ($reception->getStatut() ?  $reception->getStatut()->getNom() : ''),
                        "Date" => ($reception->getDate() ?  $reception->getDate() : '')->format('d/m/Y'),
                        "Fournisseur" => ($reception->getFournisseur() ?  $reception->getFournisseur()->getNom() : ''),
                        "Référence" => ($reception->getNumeroReception() ?  $reception->getNumeroReception() : ''),
                        'Actions' =>  $this->renderView(
                            'reception/datatableReceptionRow.html.twig',
                            ['url' =>  $url, 'reception' =>  $reception]
                        ),
                    ];
            }
            $data['data'] =  $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="reception_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function articleApi(Request  $request,  $id): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $reception = $this->receptionRepository->find($id);
                $ligneArticles = $this->receptionReferenceArticleRepository->getByReception($reception);

                $rows = [];
                foreach ($ligneArticles as  $ligneArticle) {
                    $rows[] =
                        [
                            "Référence CEA" => ($ligneArticle->getReferenceArticle() ?  $ligneArticle->getReferenceArticle()->getReference() : ''),
                            "Fournisseur" => ($ligneArticle->getFournisseur() ?  $ligneArticle->getFournisseur()->getNom() : ''),
                            "Libellé" => ($ligneArticle->getLabel() ?  $ligneArticle->getLabel() : ''),
                            "A recevoir" => ($ligneArticle->getQuantiteAR() ?  $ligneArticle->getQuantiteAR() : ''),
                            "Reçu" => ($ligneArticle->getQuantite() ?  $ligneArticle->getQuantite() : ''),
                            'Actions' =>  $this->renderView(
                                'reception/datatableLigneRefArticleRow.html.twig',
                                [
                                    'ligneId' => $ligneArticle->getId(),
                                ]

                            ),
                        ];
                }
                $data['data'] =  $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/article-printer/{id}", name="article_printer_all", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function printerAllApi(Request  $request,  $id): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) { //Si la requête est de type Xml
            $references =  $this->articleRepository->getRefByRecep($id);
            $rows = [];
            foreach ($references as   $reference) {
                $rows[] =  $reference['reference'];
            }
            return new JsonResponse($rows);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reception_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('reception/index.html.twig' );
    }

    /**
     * @Route("/supprimer", name="reception_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            $reception =  $this->receptionRepository->find($data['receptionId']);

            $entityManager =  $this->getDoctrine()->getManager();
            $entityManager->remove($reception);
            $entityManager->flush();
            $data = [
                "redirect" =>  $this->generateUrl('reception_index')
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-article", name="reception_article_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function deleteArticle(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            $ligneArticle =  $this->receptionReferenceArticleRepository->find($data['ligneArticle']);
            $entityManager =  $this->getDoctrine()->getManager();
            $entityManager->remove($ligneArticle);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/add-article", name="reception_article_add", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addArticle(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() && $contentData = json_decode($request->getContent(), true)) { //Si la requête est de type Xml
            $refArticle =  $this->referenceArticleRepository->find($contentData['referenceArticle']);
            $reception =  $this->receptionRepository->find($contentData['reception']);
            $fournisseur = $this->fournisseurRepository->find($contentData['fournisseur']);
            $anomalie =  $contentData['anomalie'];
            if ($anomalie) {
                $articleAnomalie =  $this->receptionReferenceArticleRepository->countNotConformByReception($reception);
                if ($articleAnomalie < 1) {
                    $statutRecep =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_RECEPTION_PARTIELLE);
                    $reception->setStatut($statutRecep);
                }
            } else {
                $reception->setStatut($this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_ANOMALIE));
            }

            $quantite =  $contentData['quantite'];
            $refArticle->setQuantiteStock($refArticle->getQuantiteStock() +  $quantite);
            $receptionReferenceArticle = new ReceptionReferenceArticle;
            $receptionReferenceArticle
                ->setLabel($contentData['libelle'])
                ->setAnomalie($anomalie)
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($refArticle)
                ->setQuantite($contentData['quantite'])
                ->setQuantiteAR($contentData['quantiteAR'])
                ->setCommentaire($contentData['commentaire'])
                ->setReception($reception);

            if (array_key_exists('articleFournisseur', $contentData)) {
                $articleFournisseur = $this->articleFournisseurRepository->find($contentData['articleFournisseur']);
                $receptionReferenceArticle
                    ->setArticleFournisseur($articleFournisseur);
            }
            $em =  $this->getDoctrine()->getManager();
            $em->persist($receptionReferenceArticle);
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier-article", name="reception_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEditArticle(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {

            $ligneArticle = $this->receptionReferenceArticleRepository->find($data);

            $json =  $this->renderView(
                'reception/modalModifyLigneArticleContent.html.twig',
                ['ligneArticle' => $ligneArticle]
            );
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article", name="reception_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request  $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
            return $this->redirectToRoute('access_denied');
        }

        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) { //Si la requête est de type Xml
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) { //Si la requête est de type Xml

            $receptionReferenceArticle =  $this->receptionReferenceArticleRepository->find($data['article']);
            $fournisseur = $this->fournisseurRepository->find($data['fournisseur']);
            $refArticle =  $this->referenceArticleRepository->find($data['referenceArticle']);
            $reception = $receptionReferenceArticle->getReception();

            $receptionReferenceArticle
                ->setLabel($data['libelle'])
                ->setAnomalie($data['anomalie'])
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($refArticle)
                ->setQuantite($data['quantite'])
                ->setQuantiteAR($data['quantiteAR'])
                ->setCommentaire($data['commentaire']);

            if (array_key_exists('articleFournisseur', $data)) {
                $articleFournisseur = $this->articleFournisseurRepository->find($data['articleFournisseur']);
                $receptionReferenceArticle
                    ->setArticleFournisseur($articleFournisseur);
            }

            $em =  $this->getDoctrine()->getManager();
            $em->flush();


            $nbArticleNotConform =  $this->receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statutLabel =  $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE,  $statutLabel);
            $reception->setStatut($statut);

            $em->flush();
            $json = [
                'entete' =>  $this->renderView('reception/enteteReception.html.twig', ['reception' =>  $reception])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/voir/{id}", name="reception_show", methods={"GET", "POST"})
     */
    public function show(Reception $reception, $id): Response
    {
        return  $this->render("reception/show.html.twig", [
            'reception' =>  $reception,
            'id' =>  $id,
            'statuts' =>  $this->statutRepository->findByCategorieName(Reception::CATEGORIE),
            'type' =>  $this->typeRepository->getOneByCategoryLabel(Article::CATEGORIE),
        ]);
    }

    /**
     * @Route("/finir/{id}", name="reception_finish", methods={"GET", "POST"})
     */
    public function finish(Reception  $reception): Response
    {
        $statut =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_RECEPTION_TOTALE);
        $receptionReferenceArticle = $this->receptionReferenceArticleRepository->getByReception($reception);
        foreach ($receptionReferenceArticle as $row) {
            $referenceArticle = $row->getReferenceArticle();
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticle->setQuantiteStock($referenceArticle->getQuantiteStock() + $row->getQuantite());
            } elseif ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                for ($i = 0; $i < $row->getQuantite(); $i++) {
                    $date = new \DateTime('now');
                    $ref = $date->format('YmdHis');
                    $article = new Article();
                    $article
                        ->setlabel($row->getLabel())
                        ->setReference($ref . '-' . strval($i))
                        ->setArticleFournisseur($row->getArticleFournisseur())
                        ->setConform(!$row->²getAnomalie)
                        ->setStatut($this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF))
                        ->setReception($reception)
                        ->setType($this->typeRepository->getOneByCategoryLabel(Article::CATEGORIE));
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($article);
                    $em->flush();
                }
            }
        }
        $reception->setStatut($statut);
        $reception->setDateReception(new \DateTime('now'));
        $this->getDoctrine()->getManager()->flush();

        return  $this->redirectToRoute('reception_index');
    }

    /**
     * @Route("/article-stock", name="get_article_stock", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticleStock(Request  $request)
    {
        $id =  $request->request->get('id');
        $quantiteStock =  $this->referenceArticleRepository->getQuantiteStockById($id);

        return new JsonResponse($quantiteStock);
    }

    /**
     * @Route("/article-fournisseur", name="get_article_fournisseur", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticleFournisseur(Request  $request)
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $fournisseur = $this->fournisseurRepository->find($data['fournisseur']);
                $articlesFournisseurs = $this->articleFournisseurRepository->getByRefArticleAndFournisseur($refArticle, $fournisseur);
                if ($articlesFournisseurs !== null) {
                    $json = [
                        "option" => $this->renderView(
                            'reception/optionArticleFournisseur.html.twig',
                            [
                                'articlesFournisseurs' =>  $articlesFournisseurs,
                            ]
                        )
                    ];
                } else {
                    $json = null;
                }
            } else {
                $json = null;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }
}
