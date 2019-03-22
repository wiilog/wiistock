<?php

namespace App\Controller;

use App\Entity\Reception;
use App\Form\ReceptionType;
use App\Repository\ReceptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

    public function __construct(FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, ArticleRepository $articleRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->receptionRepository = $receptionRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleRepository = $articleRepository;
    }


    /**
     * @Route("/creationReception", name="createReception", options={"expose"=true}, methods="POST")
     */
    public function createReception(Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) //Si data est attribuée
            {
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
                return new JsonResponse($data);
            }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifierReception", name="reception_edit", options={"expose"=true}, methods="POST")
     */
    public function modifierReception(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true))
            {
                $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
                $utilisateur = $this->utilisateurRepository->find(intval($data['utilisateur']));
                $statut = $this->statutRepository->find(intval($data['statut']));

                $reception = $this->receptionRepository->find($data['reception']);
                $reception
                    ->setNumeroReception($data['NumeroReception'])
                    ->setDate(new \DateTime($data['date-commande']))
                    ->setDateAttendu(new \DateTime($data['date-attendu']))
                    ->setStatut($statut)
                    ->setFournisseur($fournisseur)
                    ->setUtilisateur($utilisateur)
                    ->setCommentaire($data['commentaire']);

                $em = $this->getDoctrine()->getManager();
                $em->flush();
                $json = [
                    'entete' => $this->renderView('reception/enteteReception.html.twig', [
                        'reception' => $reception,
                    ])
                ];
                return new JsonResponse($json);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editApi", name="reception_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $reception = $this->receptionRepository->find($data);
            $json = $this->renderView('reception/modalModifyReceptionContent.html.twig', [
                'reception' => $reception,
                'fournisseurs' => $this->fournisseurRepository->getNoOne($reception->getFournisseur()->getId()),
                'utilisateurs' => $this->utilisateurRepository->getNoOne($reception->getUtilisateur()->getId()),
                'statuts' => $this->statutRepository->findByCategorieName(Reception::CATEGORIE)
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/apiReception", name="reception_api", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function receptionApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $receptions = $this->receptionRepository->findAll();
                $rows = [];
                foreach ($receptions as $reception) {
                    $url = $this->generateUrl('reception_ajout_article', ['id' => $reception->getId()]);
                    $rows[] =
                        [
                            'id' => ($reception->getId()),
                            "Statut" => ($reception->getStatut() ? $reception->getStatut()->getNom() : ''),
                            "Date" => ($reception->getDate() ? $reception->getDate() : '')->format('d/m/Y'),
                            "Fournisseur" => ($reception->getFournisseur() ? $reception->getFournisseur()->getNom() : ''),
                            "Référence" => ($reception->getNumeroReception() ? $reception->getNumeroReception() : ''),
                            'Actions' => $this->renderView('reception/datatableReceptionRow.html.twig',
                                ['url' => $url, 'reception' => $reception]),
                        ];
                }
                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/articleApi/{id}", name="reception_article_api", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function receptionArticleApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
            {
                $articles = $this->articleRepository->getArticleByReception($id);
                $rows = [];
                foreach ($articles as $article) {
                    $articleData = [
                        'ref' => $article->getReference(),
                        'id' => $article->getId()
                    ];
                    $rows[] =
                        [
                            "Référence" => ($article->getReference() ? $article->getReference() : ''),
                            "Libellé" => ($article->getLabel() ? $article->getlabel() : ''),
                            "Référence CEA" => ($article->getRefArticle() ? $article->getRefArticle()->getReference() : ''),
                            "Statut" => ($article->getStatut() ? $article->getStatut()->getNom() : ""),
                            'Actions' => $this->renderView('reception/datatableArticleRow.html.twig', [
                                'article' => $articleData,
                            ]),
                        ];
                }
                $data['data'] = $rows;
                return new JsonResponse($data);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/articlePrinter/{id}", name="article_printer_all", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function printerAllApi(Request $request, $id): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml
            {
                $references = $this->articleRepository->getRefByRecep($id);
                $rows = [];
                foreach ($references as  $reference) {
                    $rows[] = $reference['reference'];
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
        return $this->render('reception/index.html.twig', [
            'fournisseurs' => $this->fournisseurRepository->findAll(), //a précisé avant modif
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
        ]);
    }

    /**
     * @Route("/supprimerReception", name="reception_delete",  options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function delete(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            dump($data);
            $reception = $this->receptionRepository->find($data['reception']);
            
            $entityManager = $this->getDoctrine()->getManager();
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
     * @Route("/supprimerArticle", name="reception_article_delete",  options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function deleteArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $article = $this->articleRepository->find($data['article']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($article);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/addArticle", name="reception_addArticle", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $contentData = json_decode($request->getContent(), true)) //Si la requête est de type Xml
            {
               $refArticle = $this->referenceArticleRepository->find($contentData['refArticle']);
                $reception = $this->receptionRepository->find($contentData['reception']);
                if ($contentData['etat'] === 'on') {
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
                    $articleAnomalie = $this->articleRepository->countByStatutAndReception(Article::NOT_CONFORM, $reception);
                    if ($articleAnomalie < 1) {
                        $statutRecep = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_RECEPTION_PARTIELLE);
                        $reception->setStatut($statutRecep);
                    }
                } else {
                    $reception->setStatut($this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_ANOMALIE));
                }
                
                $quantitie = $contentData['quantite'];
                $refArticle
                    ->setQuantiteStock($refArticle->getQuantiteStock() + $quantitie);
                $date = new \DateTime('now');
                $ref =  $date->format('YmdHis');
                for ($i = 0; $i < $quantitie; $i++) {
                    $article = new Article();
                    $article
                        ->setlabel($contentData['libelle'])
                        ->setReference($ref . '-' . strval($i))
                        ->setStatut($statut)
                        ->setConform($contentData['etat'] === 'on' ? true : false)
                        ->setCommentaire($contentData['commentaire'])
                        ->setRefArticle($refArticle)
                        ->setReception($reception);

                    $em = $this->getDoctrine()->getManager();
                    $em->persist($article);
                    $em->flush();
                }
                $json = ['anomalie'=> $reception->getStatut()->getNom()];
                return new JsonResponse($json);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editArticleApi", name="reception_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editArticleApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $article = $this->articleRepository->find($data);
            $json = $this->renderView('reception/modalModifyArticleContent.html.twig', [
                'article' => $article,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/editArticle", name="reception_article_edit", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function editArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml
            {
                $article = $this->articleRepository->find($data['article']);
                $reception = $this->receptionRepository->find($article->getReception()->getId());
                $statutAnomalie = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ANOMALIE);
                if ($data['conform'] === 'on') {
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
                } else {
                    $statut = $statutAnomalie;
                    $reception->setStatut($statutAnomalie);
                }
                $article
                    ->setCommentaire($data['commentaire'])
                    ->setConform($data['conform'] === 'on' ? true : false)
                    ->setStatut($statut)
                    ->setLabel($data['label']);

                $articleAnomalie = $this->articleRepository->countByStatutAndReception($statutAnomalie, $reception);
                if ($articleAnomalie > 1) {
                    $statutRecep = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_EN_COURS);
                    $reception->setStatut($statutRecep);
                }

                $em = $this->getDoctrine()->getManager();
                $em->flush();
                $json = ['anomalie'=> $reception->getStatut()->getNom()];
                return new JsonResponse($json);
            }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/article/{id}", name="reception_ajout_article", methods={"GET", "POST"})
     */
    public function ajoutArticle(Reception $reception, $id): Response
    {
        return $this->render("reception/ajoutArticle.html.twig", [
            'reception' => $reception,
            'refArticle' => $this->referenceArticleRepository->findAll(),
            'id' => $id,
            'fournisseurs' => $this->fournisseurRepository->findAll(),
            'utilisateurs' => $this->utilisateurRepository->getIdAndUsername(),
            'statuts' => $this->statutRepository->findByCategorieName(Reception::CATEGORIE),
        ]);
    }

    /**
     * @Route("/finreception/{id}", name="reception_fin", methods={"GET", "POST"})
     */
    public function finReception(Reception $reception): Response
    {

        $statut = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_RECEPTION_TOTALE);
        $reception->setStatut($statut);
        $reception->setDateReception(new \DateTime('now'));
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute('reception_index');
    }

     /**
     * @Route("/articleStock", name="get_article_stock", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticleStock(Request $request)
    {
        $id = $request->request->get('id'); 
        $quantiteStock = $this->referenceArticleRepository->getQuantiteStockById($id);
       
       return new JsonResponse($quantiteStock);     

    }

}
