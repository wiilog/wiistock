<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09.
 */

namespace App\Service;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;
use App\Entity\CategorieCL;

use App\Repository\ArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\CategorieCLRepository;


use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Demande;


class ArticleDataService
{

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /*
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var FilterRepository
     */
    private $filterRepository;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var object|string
     */
    private $user;

    /**
     * @var RouterInterface
     */
    private $router;

    private $em;

    public function __construct(EmplacementRepository $emplacementRepository, RouterInterface $router, UserService $userService, CategorieCLRepository $categorieCLRepository, RefArticleDataService $refArticleDataService, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository, TypeRepository  $typeRepository, StatutRepository $statutRepository, EntityManagerInterface $em, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->statutRepository = $statutRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->filterRepository = $filterRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->typeRepository = $typeRepository;
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->userService = $userService;
        $this->router = $router;
        $this->emplacementRepository = $emplacementRepository;
    }

    /**
     * @return array
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getArticleOrNoByRefArticle($refArticle, $demande, $modifieRefArticle)
    {

        if ($demande === 'livraison') {
            $articleStatut = Article::STATUT_ACTIF;
        } elseif ($demande === 'collecte') {
            $articleStatut = Article::STATUT_INACTIF;
        }

        $articleFournisseur = $this->articleFournisseurRepository->getByRefArticle($refArticle);
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if ($modifieRefArticle === true) {
                $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
            } else {
                $data = false;
            }

            $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);

            if ($demande == 'livraison') $demande = 'demande';
            $json = $this->templating->render($demande . '/newRefArticleByQuantiteRefContent.html.twig', [
                'articleRef' => $refArticle,
                'articles' => $this->articleFournisseurRepository->getByRefArticle($refArticle->getId()),
                'statut' => ($refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                'types' => $this->typeRepository->getByCategoryLabel(ReferenceArticle::CATEGORIE),
                'statuts' => $statuts,
                'modifieRefArticle' => $modifieRefArticle,
                'valeurChampsLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                'articlesFournisseur' => ($data ? $data['listArticlesFournisseur'] : ''),
                'totalQuantity' => ($data['totalQuantity'] ? $data['totalQuantity'] : ''),
            ]);
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $articleStatut);
            $articles = $this->articleRepository->getByAFAndInactif($articleFournisseur, $statut);
            if (count($articles) < 1) {
                $articles[] = [
                    'id' => '',
                    'reference' => 'aucun article disponible',
                ];
            }
            $json = $this->templating->render('demande/newRefArticleByQuantiteArticleContent.html.twig', [
                'articles' => $articles,
            ]);
        } else {
            $json = false; //TODO gérer erreur retour
        }

        return $json;
    }


    //TODOO les méthode getCollecteArticleOrNoByRefArticle() et getLivraisonArticleOrNoByRefArticle() ont le même fonctionnement la seul différence et le statut de l'article (actif/ inactif)

    /**
     * @return array
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getCollecteArticleOrNoByRefArticle($refArticle)
    {
        $articleFournisseur = $this->articleFournisseurRepository->getByRefArticle($refArticle);
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('collecte/newRefArticleByQuantiteRefContent.html.twig'),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $data = [
                'selection' => $this->templating->render('collecte/newRefArticleByQuantiteRefContentTemp.html.twig'),
            ];
            // $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF);
            // $articles = $this->articleRepository->getByAFAndInactif($articleFournisseur, $statut);
            // if (count($articles) < 1) {
            //     $articles[] = [
            //         'id' => '',
            //         'reference' => 'aucun article disponible',
            //     ];
            // }
            // $data = [
            //     'selection' => $this->templating->render(
            //         'collecte/newRefArticleByQuantiteArticleContent.html.twig',
            //         [
            //             'articles' => $articles,
            //         ]
            //     )
            // ];
        } else {
            $data = false; //TODO gérer erreur retour
        }

        return $data;
    }

    /**
     * @return array
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getLivraisonArticleOrNoByRefArticle($refArticle)
    {
        $articleFournisseur = $this->articleFournisseurRepository->getByRefArticle($refArticle);
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('demande/newRefArticleByQuantiteRefContent.html.twig'),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
            $demandeStatut = $this->statutRepository->findOneByCategorieAndStatut(Demande::CATEGORIE, Demande::STATUT_LIVRE);

            $articlesNull = $this->articleRepository->getByAFAndActifAndDemandeNull($articleFournisseur, $statut);
            $articleStatut = $this->articleRepository->getByAFAndActifAndDemandeStatus($articleFournisseur, $statut, $demandeStatut);

            $articles = array_merge($articlesNull, $articleStatut);

            if (count($articles) < 1) {
                $articles[] = [
                    'id' => '',
                    'reference' => 'aucun article disponible',
                ];
            }
            $data = [
                'selection' => $this->templating->render(
                    'demande/newRefArticleByQuantiteArticleContent.html.twig',
                    [
                        'articles' => $articles,
                    ]
                )
            ];
        } else {
            $data = false; //TODO gérer erreur retour
        }

        return $data;
    }


    /**
     * @param Article $article
     * @return array
     */
    public function getDataEditForArticle($article)
    {
        $type = $article->getType();
        if ($type) {
            $valeurChampLibre = $this->valeurChampsLibreRepository->getByArticleAndType($article->getId(), $type->getId());
        } else {
            $valeurChampLibre = [];
        }
        return $data = [
            'valeurChampLibre' => $valeurChampLibre
        ];
    }

    public function getViewEditArticle($article, $isADemand = false)
    {
        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $typeArticle = $refArticle->getType()->getLabel();
        $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE);

        $champsLibresComplet = $this->champsLibreRepository->findByLabelTypeAndCategorieCL($typeArticle, $categorieCL);
        $champsLibres = [];
        foreach ($champsLibresComplet as $champLibre) {
            $valeurChampArticle = $this->valeurChampsLibreRepository->findOneByChampLibreAndArticle($champLibre->getId(), $article->getId());
            $champsLibres[] = [
                'id' => $champLibre->getId(),
                'label' => $champLibre->getLabel(),
                'typage' => $champLibre->getTypage(),
                'requiredCreate' => $champLibre->getRequiredCreate(),
                'requiredEdit' => $champLibre->getRequiredEdit(),
                'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                'defaultValue' => $champLibre->getDefaultValue(),
                'valeurChampLibre' => $valeurChampArticle
            ];
        }

        $typeChampLibre =
            [
                'type' => $typeArticle,
                'champsLibres' => $champsLibres,
            ];

        $view = $this->templating->render('article/modalModifyArticleContent.html.twig', [
            'typeChampsLibres' => $typeChampLibre,
            'typeArticle' => $typeArticle,
            'article' => $article,
            'statut' => ($article->getStatut()->getNom() === Article::STATUT_ACTIF ? true : false),
            'isADemand' => $isADemand
        ]);
        return $view;
    }

    public function editArticle($data)
    {
        // spécifique CEA : accès pour tous au champ libre 'Code projet'
        //        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
        //            return new RedirectResponse($this->router->generate('access_denied'));
        //        }

        $entityManager = $this->em;
        $article = $this->articleRepository->find($data['article']);
        if ($article) {

            if ($this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                $article
                    ->setLabel($data['label'])
                    ->setConform(!$data['conform'])
                    ->setQuantite($data['quantite'] ? $data['quantite'] : 0)
                    ->setCommentaire($data['commentaire']);

                if (isset($data['statut'])) { // si on est dans une demande (livraison ou collecte), pas de champ statut
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $data['statut'] === Article::STATUT_ACTIF ? Article::STATUT_ACTIF : Article::STATUT_INACTIF);
                    $article->setStatut($statut);
                }
                if ($data['emplacement']) {
                    $article->setEmplacement($this->emplacementRepository->find($data['emplacement']));
                }
            }

            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champ) {

                if (gettype($champ) === 'integer') {
                    // spécifique CEA : accès pour tous au champ libre 'Code projet'
                    $champLibre = $this->champsLibreRepository->find($champ);
                    if ($this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT) || $champLibre->getLabel() == 'Code projet') {

                        $valeurChampLibre = $this->valeurChampsLibreRepository->findOneByArticleANDChampsLibre($article->getId(), $champ);
                        if (!$valeurChampLibre) {
                            $valeurChampLibre = new ValeurChampsLibre();
                            $valeurChampLibre
                                ->addArticle($article)
                                ->setChampLibre($champLibre);
                        }
                        $valeurChampLibre->setValeur($data[$champ]);
                        $entityManager->persist($valeurChampLibre);
                        $entityManager->flush();
                    }
                }
            }
            $entityManager->flush();
            return true;
        } else {
            return false;
        }
    }

    public function newArticle($data)
    {
        $entityManager = $this->em;
        $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $data['statut'] === Article::STATUT_ACTIF ? Article::STATUT_ACTIF : Article::STATUT_INACTIF);
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $ref = $date->format('YmdHis');

        $toInsert = new Article();
        $toInsert
            ->setLabel($data['libelle'])
            ->setConform(!$data['conform'])
            ->setStatut($statut)
            ->setCommentaire($data['commentaire'])
            ->setReference($ref . '-0')
            ->setQuantite((int)$data['quantite'])
            ->setEmplacement($this->emplacementRepository->find($data['emplacement']))
            ->setArticleFournisseur($this->articleFournisseurRepository->find($data['articleFournisseur']))
            ->setType($this->typeRepository->findOneByCategoryLabel(Article::CATEGORIE));
        $entityManager->persist($toInsert);

        $champsLibreKey = array_keys($data);
        foreach ($champsLibreKey as $champ) {
            if (gettype($champ) === 'integer') {
                $valeurChampLibre = $this->valeurChampsLibreRepository->findOneByArticleANDChampsLibre($toInsert->getId(), $champ);
                if (!$valeurChampLibre) {
                    $valeurChampLibre = new ValeurChampsLibre();
                    $valeurChampLibre
                        ->addArticle($toInsert)
                        ->setChampLibre($this->champsLibreRepository->find($champ));
                    $entityManager->persist($valeurChampLibre);
                }
                $valeurChampLibre->setValeur($data[$champ]);
                $entityManager->flush();
            }
        }
        $entityManager->flush();

        return true;
    }
    public function getDataForDatatable($params = null)
    {
        $data = $this->getArticleDataByParams($params);
        $data['recordsTotal'] = (int)$this->articleRepository->countAll();
        $data['recordsFiltered'] = (int)$this->articleRepository->countAll();
        return $data;
    }

    public function getDataForDatatableByReceptionLigne($ligne)
    {
        if ($ligne) {
            $data = $this->getArticleDataByReceptionLigne($ligne);
        } else {
            $data = $this->getArticleDataByParams(null);
        }
        $data['recordsTotal'] = (int)$this->articleRepository->countAll();
        $data['recordsFiltered'] = count($data['data']);
        return $data;
    }

    /**
     * @param null $params
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getArticleDataByReceptionLigne($ligne)
    {
        $articleRef = $this->referenceArticleRepository->getByLigneReception($ligne);
        $articlesFournisseur = $this->articleFournisseurRepository->getByRefArticle($articleRef);
        $articles = [];
        foreach ($articlesFournisseur as $af) {
            foreach ($this->articleRepository->getByAF($af) as $a) {
                if ($a->getReception() && $ligne->getReception()) $articles[] = $a;
            }
        }
        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowRefArticle($article);
        }
        return ['data' => $rows];
    }

    /**
     * @param null $params
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getArticleDataByParams($params = null)
    {
        if ($this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            $articles = $this->articleRepository->findByParams($params);
        } else {
            $categorieName = 'article';
            $statutName = 'actif';
            $statut = $this->statutRepository->findOneByCategorieAndStatut($categorieName, $statutName);
            $statutId = $statut->getId();

            $articles = $this->articleRepository->findByParamsActifStatut($params, $statutId);
        }

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowRefArticle($article);
        }
        return ['data' => $rows];
    }

    /**
     * @param Article $article
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function dataRowRefArticle($article)
    {
        $url['edit'] = $this->router->generate('demande_article_edit', ['id' => $article->getId()]);
        if ($this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            $row =
                [
                    'id' => ($article->getId() ? $article->getId() : 'Non défini'),
                    'Référence' => ($article->getReference() ? $article->getReference() : 'Non défini'),
                    'Statut' => ($article->getStatut() ? $article->getStatut()->getNom() : 'Non défini'),
                    'Libellé' => ($article->getLabel() ? $article->getLabel() : 'Non défini'),
                    'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : 'Non défini'),
                    'Quantité' => ($article->getQuantite() ? $article->getQuantite() : 0),
                    'Actions' => $this->templating->render('article/datatableArticleRow.html.twig', [
                        'url' => $url,
                        'articleId' => $article->getId(),
                    ]),
                ];
        } else {
            $row =
                [
                    'id' => ($article->getId() ? $article->getId() : 'Non défini'),
                    'Référence' => ($article->getReference() ? $article->getReference() : 'Non défini'),
                    'Statut' => false,
                    'Libellé' => ($article->getLabel() ? $article->getLabel() : 'Non défini'),
                    'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : 'Non défini'),
                    'Quantité' => ($article->getQuantite() ? $article->getQuantite() : 0),
                    'Actions' => $this->templating->render('article/datatableArticleRow.html.twig', [
                        'url' => $url,
                        'articleId' => $article->getId(),
                    ]),
                ];
        }

        return $row;
    }
}
