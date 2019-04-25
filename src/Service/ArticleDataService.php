<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09.
 */

namespace App\Service;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;

use App\Repository\ArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\FilterRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

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
     * @var object|string
     */
    private $user;

    private $em;

    public function __construct(RefArticleDataService $refArticleDataService, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository, TypeRepository  $typeRepository, StatutRepository $statutRepository, EntityManagerInterface $em, ValeurChampsLibreRepository $valeurChampsLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampsLibreRepository $champsLibreRepository, FilterRepository $filterRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
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
            $json = $this->templating->render('collecte/newRefArticleByQuantiteRefContent.html.twig', [
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
            $json = $this->templating->render('collecte/newRefArticleByQuantiteArticleContent.html.twig', [
                'articles' => $articles,
            ]);
        } else {
            $json = false; //TODO gérer erreur retour
        }

        return $json;
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

    /**
     * @return array
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getCollecteArticleOrNoByRefArticle($refArticle, $modifieRefArticle)
    {
        $articleFournisseur = $this->articleFournisseurRepository->getByRefArticle($refArticle);
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if ($modifieRefArticle === true) {
                $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
            } else {
                $data = false;
            }

            $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);
            $json = $this->templating->render('collecte/newRefArticleByQuantiteRefContent.html.twig', [
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
            //TODOO
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_INACTIF);
            $articles = $this->articleRepository->getByAFAndInactif($articleFournisseur, $statut);
            if (count($articles) < 1) {
                $articles[] = [
                    'id' => '',
                    'reference' => 'aucun article disponible',
                ];
            }
            $json = $this->templating->render('collecte/newRefArticleByQuantiteArticleContent.html.twig', [
                'articles' => $articles,
            ]);
        } else {
            $json = false; //TODO gérer erreur retour
        }

        return $json;
    }

    /**
     * @return array
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getLivraisonArticleOrNoByRefArticle($refArticle, $modifieRefArticle)
    {
        $articleFournisseur = $this->articleFournisseurRepository->getByRefArticle($refArticle);
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if ($modifieRefArticle === true) {
                $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
            } else {
                $data = false;
            }

            $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);
            $json = $this->templating->render('demande/newRefArticleByQuantiteRefContent.html.twig', [
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
            //TODOO
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
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

    public function editArticle($data)
    {
        $entityManager = $this->em;
        $article = $this->articleRepository->find($data['article']);
        if ($article) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $data['actif'] ? Article::STATUT_ACTIF : Article::STATUT_INACTIF);
            $article
                ->setLabel($data['label'])
                ->setConform(!$data['conform'])
                ->setStatut($statut)
                ->setCommentaire($data['commentaire']);
            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champ) {

                if (gettype($champ) === 'integer') {
                    $valeurChampLibre = $this->valeurChampsLibreRepository->findOneByArticleANDChampsLibre($article->getId(), $champ);
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampsLibre();
                        $valeurChampLibre
                            ->addArticle($article)
                            ->setChampLibre($this->champsLibreRepository->find($champ));
                    }
                    $valeurChampLibre->setValeur($data[$champ]);
                    $entityManager->persist($valeurChampLibre);
                    $entityManager->flush();
                }
            }
            $entityManager->flush();
            return true;
        } else {
            return false;
        }
    }
}
