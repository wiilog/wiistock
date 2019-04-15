<?php

namespace App\Controller;

use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;

use App\Form\ReceptionType;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ReceptionRepository;
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

    public function __construct(ReceptionReferenceArticleRepository $receptionReferenceArticleRepository, TypeRepository  $typeRepository, ChampsLibreRepository $champsLibreRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository)
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
                "redirect" => $this->generateUrl('reception_ajout_article', ['id' => $reception->getId()])
            ];

                return new JsonResponse( $data);
            }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="reception_edit", options={"expose"=true}, methods="POST")
     */
    public function edit(Request  $request): Response
    {
        if (! $request->isXmlHttpRequest() &&  $data = json_decode( $request->getContent(), true)) {
             $fournisseur =  $this->fournisseurRepository->find(intval( $data ['fournisseur']));
             $utilisateur =  $this->utilisateurRepository->find(intval( $data ['utilisateur']));
             $statut =  $this->statutRepository->find(intval( $data ['statut']));

             $reception =  $this->receptionRepository->find( $data ['receptionId']);
             $reception
                ->setNumeroReception( $data ['NumeroReception'])
                ->setDate(new \DateTime( $data ['date-commande']))
                ->setDateAttendu(new \DateTime( $data ['date-attendu']))
                ->setStatut( $statut)
                ->setFournisseur( $fournisseur)
                ->setUtilisateur( $utilisateur)
                ->setCommentaire( $data ['commentaire']);

             $em =  $this->getDoctrine()->getManager();
             $em->flush();
             $json = [
                'entete' =>  $this->renderView('reception/enteteReception.html.twig', [
                    'reception' =>  $reception,
                ])
            ];
            return new JsonResponse( $json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="api_reception_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEdit(Request  $request): Response
    {
        if (! $request->isXmlHttpRequest() &&  $data = json_decode( $request->getContent(), true)) {
             $reception =  $this->receptionRepository->find( $data);
             $json =  $this->renderView('reception/modalEditReceptionContent.html.twig', [
                'reception' =>  $reception,
                'fournisseurs' =>  $this->fournisseurRepository->getNoOne( $reception->getFournisseur()->getId()),
                'utilisateurs' =>  $this->utilisateurRepository->getNoOne( $reception->getUtilisateur()->getId()),
                'statuts' =>  $this->statutRepository->findByCategorieName(Reception::CATEGORIE)
            ]);
            return new JsonResponse( $json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="reception_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function api(Request  $request): Response
    {
        if ( $request->isXmlHttpRequest()) { //Si la requête est de type Xml
             $receptions =  $this->receptionRepository->findAll();
             $rows = [];
            foreach ( $receptions as  $reception) {
                 $url =  $this->generateUrl('reception_ajout_article', ['id' =>  $reception->getId()]);
                 $rows [] =
                        [
                            'id' => ( $reception->getId()),
                            "Statut" => ( $reception->getStatut() ?  $reception->getStatut()->getNom() : ''),
                            "Date" => ( $reception->getDate() ?  $reception->getDate() : '')->format('d/m/Y'),
                            "Fournisseur" => ( $reception->getFournisseur() ?  $reception->getFournisseur()->getNom() : ''),
                            "Référence" => ( $reception->getNumeroReception() ?  $reception->getNumeroReception() : ''),
                            'Actions' =>  $this->renderView(
                                'reception/datatableReceptionRow.html.twig',
                                ['url' =>  $url, 'reception' =>  $reception]
                            ),
                        ];
            }
             $data ['data'] =  $rows;
            return new JsonResponse( $data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="reception_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function articleApi(Request  $request,  $id): Response
    {
        if ( $request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                 $receptionRAByreferences =  $this->receptionReferenceArticleRepository->getByReception( $id);
                dump( $receptionRAByreferences);
                 $rowsARR = [];
                foreach ( $receptionRAByreferences as  $receptionRAByreference) {
                     $articleData = [
                        'ref' => "",
                        'id' =>  $receptionRAByreference->getId()
                    ];
                     $rowsARA [] =
                        [
                            "Référence" => '',
                            "Libellé" => ( $receptionRAByreference->getReferenceArticle() ?  $receptionRAByreference->getReferenceArticle()->getlibelle() : ''),
                            "Référence CEA" => ( $receptionRAByreference->getReferenceArticle() ?  $receptionRAByreference->getReferenceArticle()->getReference() : ''),
                            "Fabriquant" => '',
                            "A recevoir" => ( $receptionRAByreference->getQuantiteAR() ?  $receptionRAByreference->getQuantiteAR() : ""),
                            "Reçu" => ( $receptionRAByreference->getQuantite() ?  $receptionRAByreference->getQuantite : ""),
                            'Actions' =>  $this->renderView('reception/datatableArticleRow.html.twig', [
                                'article' =>  $articleData,
                            ]),
                        ];
                }
                 $articles =  $this->articleRepository->getArticleByReception( $id);
                 $rowsARA = [];
                foreach ( $articles as  $article) {
                     $articleData = [
                        'ref' =>  $article->getReference(),
                        'id' =>  $article->getId()
                    ];
                     $rowsARA [] =
                        [
                            "Référence" => ( $article->getReference() ?  $article->getReference() : ''),
                            "Libellé" => ( $article->getLabel() ?  $article->getlabel() : ''),
                            "Référence CEA" => ( $article->getArticleFournisseur() ?  $article->getArticleFournisseur()->getReferenceArticle()->getReference() : ''),
                            "Fabriquant" => ( $article->getArticleFournisseur() ?  $article->getArticleFournisseur()->getFournisseur()->getNom() : ''),
                            "A recevoir" => ( $article->getStatut() ?  $article->getStatut()->getNom() : ""),
                            "Reçu" => ( $article->getStatut() ?  $article->getStatut()->getNom() : ""),
                            'Actions' =>  $this->renderView('reception/datatableArticleRow.html.twig', [
                                'article' =>  $articleData,
                            ]),
                        ];
                }
                 $data ['data'] = array_merge( $rowsARA,  $rowsARR);
                return new JsonResponse( $data);
          
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/article-printer/{id}", name="article_printer_all", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function printerAllApi(Request  $request,  $id): Response
    {
        if (! $request->isXmlHttpRequest() &&  $data = json_decode( $request->getContent(), true)) { //Si la requête est de type Xml
             $references =  $this->articleRepository->getRefByRecep( $id);
             $rows = [];
            foreach ( $references as   $reference) {
                 $rows [] =  $reference ['reference'];
            }
            return new JsonResponse( $rows);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reception_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        return  $this->render('reception/index.html.twig' );
    }

    /**
     * @Route("/supprimer", name="reception_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request  $request): Response
    {
        if (! $request->isXmlHttpRequest() &&  $data = json_decode( $request->getContent(), true)) {
             $reception =  $this->receptionRepository->find( $data ['receptionId']);

             $entityManager =  $this->getDoctrine()->getManager();
             $entityManager->remove( $reception);
             $entityManager->flush();
             $data = [
                "redirect" =>  $this->generateUrl('reception_index')
            ];
            return new JsonResponse( $data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-article", name="reception_article_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function deleteArticle(Request  $request): Response
    {
        if (! $request->isXmlHttpRequest() &&  $data = json_decode( $request->getContent(), true)) {
             $article =  $this->articleRepository->find( $data ['article']);
             $entityManager =  $this->getDoctrine()->getManager();
             $entityManager->remove( $article);
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
        if (! $request->isXmlHttpRequest() &&   $contentData = json_decode( $request->getContent(), true)) { //Si la requête est de type Xml
            
             $refArticle =  $this->referenceArticleRepository->find( $contentData ['refArticle']);
             $reception =  $this->receptionRepository->find( $contentData ['reception']);

             $anomalie =  $contentData ['anomalie'] === 'on';
            if (! $anomalie) {
                 $articleAnomalie =  $this->articleRepository->countNotConformByReception( $reception);
                if ( $articleAnomalie < 1) {
                     $statutRecep =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_RECEPTION_PARTIELLE);
                     $reception->setStatut( $statutRecep);
                }
            } else {
                 $reception->setStatut( $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_ANOMALIE));
            }

             $quantite =  $contentData ['quantite'];
             $refArticle->setQuantiteStock( $refArticle->getQuantiteStock() +  $quantite);
             $date = new \DateTime('now');
             $ref =  $date->format('YmdHis');
             $articleFournisseur =  $this->articleFournisseurRepository->findOneByRefArticleAndFournisseur( $contentData ['refArticle'],  $contentData ['fournisseur']); //TODO CG

            if (!empty( $articleFournisseur)) {
                for ( $i = 0;  $i <  $quantite;  $i++) {
                     $article = new Article();
                   
                     $article
                            ->setlabel( $contentData ['libelle'])
                            ->setReference( $ref . '-' . strval( $i))
                            ->setArticleFournisseur( $articleFournisseur)
                            ->setConform(! $anomalie)
                            ->setStatut( $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF))
                            ->setCommentaire( $contentData ['commentaire'])
                            ->setReception( $reception)
                            ->setType( $this->typeRepository->getOneByCategoryLabel(Article::CATEGORIE));
                     $em =  $this->getDoctrine()->getManager();
                     $em->persist( $article);
                     $em->flush();
                   
                     $champsLibreKey = array_keys( $contentData);
                    
                    foreach ( $champsLibreKey as  $champs) {
                        if (gettype( $champs) === 'integer') {
                             $valeurChampLibre = new ValeurChampsLibre();
                             $valeurChampLibre
                                ->setValeur( $contentData [$champs])
                                ->addArticle( $article)
                                ->setChampLibre( $this->champsLibreRepository->find( $champs));
                             $em =  $this->getDoctrine()->getManager();
                             $em->persist( $valeurChampLibre);
                             $em->flush();
                        } //TODO gérer message erreur retour
                    }
                    return new JsonResponse();
                }
                throw new NotFoundHttpException("404");
            }
        }
    }

    /**
     * @Route("/api-modifier-article", name="reception_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEditArticle(Request  $request): Response
    {
        if (! $request->isXmlHttpRequest() &&  $articleId = json_decode( $request->getContent(), true)) {
             $article =  $this->articleRepository->find( $articleId);
             $valeurChampsLibre =  $this->valeurChampsLibreRepository->getByArticle( $article->getId());
             $type = ( $article->getType() ?  $article->getType() : "");
             $champsLibres =  $this->champsLibreRepository->getByType( $type->getId());
             $tabInfoChampsLibres = [];
            foreach ( $champsLibres as  $champLibre) {
                 $valeurChampLibre =  $this->valeurChampsLibreRepository->findOneByChampLibreAndArticle( $champLibre->getId(),  $articleId);
                 $tabInfoChampsLibres [] = ['id' =>  $valeurChampLibre->getId(),
                                        'typage' =>  $champLibre->getTypage(),
                                         'label' =>  $champLibre->getLabel(),
                                         'valeur' =>  $valeurChampLibre->getValeur()];
            }
            
             $json =  $this->renderView('reception/modalModifyArticleContent.html.twig', [
                'article' =>  $article,
                'type' =>  $type,
                'valeurChampsLibre' => isset( $valeurChampsLibre) ?  $valeurChampsLibre: null,
                'tabInfoChampsLibres' =>  $tabInfoChampsLibres,


            ]);
            return new JsonResponse( $json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article", name="reception_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request  $request): Response
    {
        
        if (! $request->isXmlHttpRequest() &&  $data = json_decode( $request->getContent(), true)) { //Si la requête est de type Xml
               
             $article =  $this->articleRepository->find( $data ['article']);
            
             $reception =  $this->receptionRepository->find( $article->getReception()->getId());

             $article
                    ->setConform( $data ['anomalie'] ? Article::NOT_CONFORM : Article::CONFORM)
                    ->setStatut( $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF))
                    ->setLabel( $data ['label'])
                    ->setCommentaire( $data ['commentaire'])
                    ->setType( $this->typeRepository->getOneByCategoryLabel(Article::CATEGORIE));
                    
             $em =  $this->getDoctrine()->getManager();
             $em->flush();

             $champsLibreKey = array_keys( $data);

            foreach ( $champsLibreKey as  $champ) {
                if (gettype( $champ) === 'integer') {
                               
                     $valeurChampLibre =  $this->valeurChampsLibreRepository->find( $champ);
                     $valeurChampLibre
                        ->setValeur( $data [$champ]);
                   

                    // si la valeur n'existe pas, on la crée
                    if (! $valeurChampLibre) {
                         $valeurChampLibre = new ValeurChampsLibre();
                         $valeurChampLibre
                            ->addArticle( $article)
                            ->setValeur( $data [$champ])
                            ->setChampLibre( $this->champsLibreRepository->find( $champ));
                       
                         $em->persist( $valeurChampLibre);
                    }
                   
                     $em->flush();
                }
            }

             $nbArticleNotConform =  $this->articleRepository->countNotConformByReception( $reception);
             $statutLabel =  $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
             $statut =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE,  $statutLabel);
             $reception->setStatut( $statut);

             $em->flush();
             $json = [
                    'entete' =>  $this->renderView('reception/enteteReception.html.twig', ['reception' =>  $reception])
                ];
            return new JsonResponse( $json);
        }
        throw new NotFoundHttpException("404");
    }



    /**
     * @Route("/article/{id}", name="reception_ajout_article", methods={"GET", "POST"})
     */
    public function ajoutArticle(Reception  $reception,  $id): Response
    {
         $refArticles =  $this->referenceArticleRepository->getIdAndLabelByFournisseur( $reception->getFournisseur()->getId());

        return  $this->render("reception/show.html.twig", [
            'reception' =>  $reception,
            'refArticles' =>  $refArticles,
            'id' =>  $id,
            'fournisseurs' =>  $this->fournisseurRepository->findAll(),
            'utilisateurs' =>  $this->utilisateurRepository->getIdAndUsername(),
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

        //  $date = new \DateTime('now');
        //              $ref =  $date->format('YmdHis');
        //              $articleFournisseur =  $this->articleFournisseurRepository->find( $contentData ['articleFournisseur']);
        //             for ( $i = 0;  $i <  $quantite;  $i++) {
        //                  $article = new Article();
        //                  $article
        //                     ->setlabel( $contentData ['libelle'])
        //                     ->setReference( $ref . '-' . strval( $i))
        //                     ->setArticleFournisseur( $articleFournisseur)
        //                     ->setConform(! $anomalie)
        //                     ->setStatut( $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF))
        //                     ->setCommentaire( $contentData ['commentaire'])
        //                     ->setReception( $reception);

        //                  $em->persist( $article);

         $reception->setStatut( $statut);
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
         $quantiteStock =  $this->referenceArticleRepository->getQuantiteStockById( $id);

        return new JsonResponse( $quantiteStock);
    }
}

