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
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Menu;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\ValeurChampLibre;
use App\Entity\CategorieCL;

use App\Repository\ArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\CategorieCLRepository;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
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

	/**
	 * @var ChampLibreRepository
	 */
    private $champLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

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

    /**
     * @var SpecificService
     */
    private $specificService;

    /**
     * @var ParametreRepository
     */
    private $parametreRepository;

    /**
     * @var ParametreRoleRepository
     */
    private $parametreRoleRepository;

    private $em;

    public function __construct(ParametreRoleRepository $parametreRoleRepository, ParametreRepository $parametreRepository, SpecificService $specificService, EmplacementRepository $emplacementRepository, RouterInterface $router, UserService $userService, CategorieCLRepository $categorieCLRepository, RefArticleDataService $refArticleDataService, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository, TypeRepository $typeRepository, StatutRepository $statutRepository, EntityManagerInterface $em, ValeurChampLibreRepository $valeurChampLibreRepository, ReferenceArticleRepository $referenceArticleRepository, ChampLibreRepository $champLibreRepository, FiltreRefRepository $filtreRefRepository, \Twig_Environment $templating, TokenStorageInterface $tokenStorage)
    {
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->statutRepository = $statutRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
        $this->filtreRefRepository = $filtreRefRepository;
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
        $this->specificService = $specificService;
        $this->parametreRepository = $parametreRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
    }

    /**
     * @param ReferenceArticle $refArticle
     * @param string $demande
     * @param bool $modifieRefArticle
     * @param bool $byRef
     * @return bool|string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getArticleOrNoByRefArticle($refArticle, $demande, $modifieRefArticle, $byRef)
    {
        if ($demande === 'livraison') {
            $articleStatut = Article::STATUT_ACTIF;
            $demande = 'demande';
        } elseif ($demande === 'collecte') {
            $articleStatut = Article::STATUT_INACTIF;
        } else {
            $articleStatut = null;
        }

        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            if ($modifieRefArticle === true) {
                $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
            } else {
                $data = false;
            }

            $statuts = $this->statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);

            $json = $this->templating->render($demande . '/newRefArticleByQuantiteRefContent.html.twig', [
                'articleRef' => $refArticle,
                'articles' => $this->articleFournisseurRepository->findByRefArticle($refArticle->getId()),
                'statut' => ($refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                'types' => $this->typeRepository->findByCategoryLabel(CategoryType::ARTICLE),
                'statuts' => $statuts,
                'modifieRefArticle' => $modifieRefArticle,
                'valeurChampLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                'articlesFournisseur' => ($data ? $data['listArticlesFournisseur'] : ''),
                'totalQuantity' => ($data['totalQuantity'] ? $data['totalQuantity'] : ''),
            ]);
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $articleStatut);
            if ($demande === 'collecte') {
                $articles = $this->articleRepository->findByRefArticleAndStatut($refArticle, $statut);
            } else if ($demande === 'demande') {
                $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statut);
            } else {
                $articles = [];
            }

            $totalQuantity = 0;
            if (count($articles) < 1) {
                $articles[] = [
                    'id' => '',
                    'reference' => 'aucun article disponible',
                ];
            } else {
                foreach ($articles as $article) {
					$totalQuantity += $article->getQuantite();
                }
            }
			$availableQuantity = $totalQuantity - $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($refArticle);

			if ($byRef && $demande == 'demande') {
				$json = $this->templating->render('demande/choiceContent.html.twig', [
					'maximum' => $availableQuantity
				]);
			} else {
				$json = $this->templating->render($demande . '/newRefArticleByQuantiteArticleContent.html.twig', [
					'articles' => $articles,
					'maximum' => $availableQuantity
				]);
			}


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
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('collecte/newRefArticleByQuantiteRefContent.html.twig'),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $data = [
                'selection' => $this->templating->render('collecte/newRefArticleByQuantiteRefContentTemp.html.twig'),
            ];
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
    public function getLivraisonArticlesByRefArticle($refArticle)
    {
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('demande/newRefArticleByQuantiteRefContent.html.twig'),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $statutArticleActif = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
            $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif);

			$totalQuantity = $this->articleRepository->getTotalQuantiteByRefAndStatusLabel($refArticle, Article::STATUT_ACTIF);
			$role = $this->user->getRole();
            $param = $this->parametreRepository->findOneBy(['label' => Parametre::LABEL_AJOUT_QUANTITE]);
            $paramQuantite = $this->parametreRoleRepository->findOneByRoleAndParam($role, $param);

            // si le paramétrage n'existe pas pour ce rôle, on le crée (valeur par défaut)
            if (!$paramQuantite) {
                $paramQuantite = new ParametreRole();
                $paramQuantite
                    ->setValue($param->getDefaultValue())
                    ->setRole($role)
                    ->setParametre($param);
                $this->em->persist($paramQuantite);
                $this->em->flush();
            }
            $availableQuantity = $totalQuantity - $this->referenceArticleRepository->getTotalQuantityReservedByRefArticle($refArticle);

            $byRef = $paramQuantite->getValue() == Parametre::VALUE_PAR_REF;
            if ($byRef) {
            	$data = ['selection' => $this->templating->render('demande/choiceContent.html.twig', [
            		'maximum' => $availableQuantity
				])];
			} else {
            	$data = ['selection' => $this->templating->render('demande/newRefArticleByQuantiteArticleContent.html.twig', [
					'articles' => $articles,
					'maximum' => $availableQuantity,
				])];
			}
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
            $valeurChampLibre = $this->valeurChampLibreRepository->getByArticleAndType($article->getId(), $type->getId());
        } else {
            $valeurChampLibre = [];
        }
        return $data = [
            'valeurChampLibre' => $valeurChampLibre
        ];
    }

    /**
     * @param Article $article
     * @param bool $isADemand
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getViewEditArticle($article, $isADemand = false)
    {
        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $typeArticle = $refArticle->getType();
        $typeArticleLabel = $typeArticle->getLabel();

        $champsLibresComplet = $this->champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
        $champsLibres = [];
        foreach ($champsLibresComplet as $champLibre) {
            $valeurChampArticle = $this->valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champLibre);
//			$labelChampLibre = strtolower($champLibre->getLabel());
//			$isCEA = $this->specificService->isCurrentClientNameFunction(ParamClient::CEA_LETI);

//            // spécifique CEA : on vide les champs 'Code projet' et 'Destinataire' dans le cas d'une demande
//			if ($isCEA
//			&& ($labelChampLibre == 'code projet' || $labelChampLibre == 'destinataire')
//			&& $isADemand) {
//				$valeurChampArticle = null;
//			}
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

        $typeChampsLibres =
            [
                'type' => $typeArticleLabel,
                'champsLibres' => $champsLibres,
            ];

        $statut = $article->getStatut()->getNom();

        $view = $this->templating->render('article/modalModifyArticleContent.html.twig', [
            'typeChampsLibres' => $typeChampsLibres,
            'typeArticle' => $typeArticleLabel,
            'typeArticleId' => $typeArticle->getId(),
            'article' => $article,
            'statut' => $statut,
            'isADemand' => $isADemand
        ]);
        return $view;
    }

    public function editArticle($data)
    {
//		// spécifique CEA : accès pour tous aux champs libres 'Code projet' et 'Destinataire'
//		$isCea = $this->specificService->isCurrentClientNameFunction(ParamClient::CEA_LETI);
//		if (!$isCea) {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }
//		}

        $entityManager = $this->em;
        $price = max(0, $data['prix']);
        $article = $this->articleRepository->find($data['article']);
        if ($article) {
            if ($this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
                $article
                    ->setPrixUnitaire($price)
                    ->setLabel($data['label'])
                    ->setConform(!$data['conform'])
                    ->setQuantite($data['quantite'] ? max($data['quantite'], 0) : 0)// protection contre quantités négatives
                    ->setCommentaire($data['commentaire']);

                if (isset($data['statut'])) { // si on est dans une demande (livraison ou collecte), pas de champ statut
                    $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $data['statut']);
                    if ($statut) $article->setStatut($statut);
                }
                if ($data['emplacement']) {
                    $article->setEmplacement($this->emplacementRepository->find($data['emplacement']));
                }
            }

            $champLibresKey = array_keys($data);
            foreach ($champLibresKey as $champ) {
                if (gettype($champ) === 'integer') {
//                    // spécifique CEA : accès pour tous aux champs libres 'Code projet' et 'Destinataire'
//					$isCea = $this->specificService->isCurrentClientNameFunction(ParamClient::CEA_LETI);

                    $champLibre = $this->champLibreRepository->find($champ);
//					$labelCL = strtolower($champLibre->getLabel());
//                    if ($this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT) || ($isCea && ($labelCL == 'code projet' || $labelCL == 'destinataire'))) {
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champ);
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addArticle($article)
                            ->setChampLibre($champLibre);
                    }
                    $valeurChampLibre->setValeur($data[$champ]);
                    $entityManager->persist($valeurChampLibre);
                    $entityManager->flush();
//                    }
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
        $formattedDate = $date->format('ym');

        $referenceArticle = $this->referenceArticleRepository->find($data['refArticle'])->getReference();
        $references = $this->articleRepository->getReferencesByRefAndDate($referenceArticle, $formattedDate);

        $highestCpt = 0;
        foreach ($references as $reference) {
        	$cpt = (int)substr($reference, -5, 5);
        	if ($cpt > $highestCpt) $highestCpt = $cpt;
		}

        $i = $highestCpt + 1;
        $cpt = sprintf('%05u', $i);

        $toInsert = new Article();
        $price = max(0, $data['prix']);
        $type = $this->articleFournisseurRepository->find($data['articleFournisseur'])->getReferenceArticle()->getType();
        $toInsert
            ->setLabel($data['libelle'])
            ->setConform(!$data['conform'])
            ->setStatut($statut)
            ->setCommentaire($data['commentaire'])
            ->setPrixUnitaire($price)
            ->setReference($referenceArticle . $formattedDate . $cpt)
            ->setQuantite(max((int)$data['quantite'], 0))// protection contre quantités négatives
            ->setEmplacement($this->emplacementRepository->find($data['emplacement']))
            ->setArticleFournisseur($this->articleFournisseurRepository->find($data['articleFournisseur']))
            ->setType($type);
        $entityManager->persist($toInsert);

        $champLibreKey = array_keys($data);
        foreach ($champLibreKey as $champ) {
            if (gettype($champ) === 'integer') {
                    $valeurChampLibre = new ValeurChampLibre();
                    $valeurChampLibre
                        ->addArticle($toInsert)
                        ->setChampLibre($this->champLibreRepository->find($champ));
                    $entityManager->persist($valeurChampLibre);
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
        return $data;
    }

    public function getDataForDatatableByReceptionLigne($ligne)
    {
        if ($ligne) {
            $data = $this->getArticleDataByReceptionLigne($ligne);
        } else {
            $data = $this->getArticleDataByParams();
        }
        return $data;
    }

    /**
     * @param ReceptionReferenceArticle $ligne
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getArticleDataByReceptionLigne(ReceptionReferenceArticle $ligne)
    {
        $articleRef = $this->referenceArticleRepository->findOneByLigneReception($ligne);

        $listArticleFournisseur = $this->articleFournisseurRepository->findByRefArticle($articleRef);
        $articles = [];
        foreach ($listArticleFournisseur as $articleFournisseur) {
            foreach ($this->articleRepository->findByListAF($articleFournisseur) as $article) {
                if ($article->getReception() && $ligne->getReception() && $article->getReception() === $ligne->getReception()) $articles[] = $article;
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
            $statutLabel = null;
        } else {
            $statutLabel = Article::STATUT_ACTIF;
        }

        $queryResult = $this->articleRepository->findByParamsAndStatut($params, $statutLabel);

        $articles = $queryResult['data'];
        $listId = $queryResult['allArticleDataTable'];

        $articlesString = [];
        foreach ($listId as $id) {
            $articlesString[] = $id->getId();
        }

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowRefArticle($article);
        }
        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
            'listId' => $articlesString,
        ];
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
                    'Statut' => '',
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
