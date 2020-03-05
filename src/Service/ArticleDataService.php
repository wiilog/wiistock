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
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Entity\CategorieCL;

use App\Repository\ArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\ReceptionReferenceArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\CategorieCLRepository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

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
     * @var Twig_Environment
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

	/**
	 * @var ReceptionReferenceArticleRepository
	 */
    private $receptionReferenceArticleRepository;

	/**
	 * @var MailerService
	 */
    private $mailerService;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    public function __construct(FiltreSupRepository $filtreSupRepository,
                                ReceptionReferenceArticleRepository $receptionReferenceArticleRepository,
                                MailerService $mailerService,
                                ParametreRoleRepository $parametreRoleRepository,
                                ParametreRepository $parametreRepository,
                                SpecificService $specificService,
                                EmplacementRepository $emplacementRepository,
                                RouterInterface $router,
                                UserService $userService,
                                CategorieCLRepository $categorieCLRepository,
                                RefArticleDataService $refArticleDataService,
                                ArticleRepository $articleRepository,
                                ArticleFournisseurRepository $articleFournisseurRepository,
                                TypeRepository $typeRepository,
                                StatutRepository $statutRepository,
                                EntityManagerInterface $em,
                                ValeurChampLibreRepository $valeurChampLibreRepository,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ChampLibreRepository $champLibreRepository,
                                FiltreRefRepository $filtreRefRepository,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage)
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
        $this->mailerService = $mailerService;
        $this->receptionReferenceArticleRepository = $receptionReferenceArticleRepository;
        $this->filtreSupRepository = $filtreSupRepository;
    }

	/**
	 * @param ReferenceArticle $refArticle
	 * @param string $demande
	 * @param bool $modifieRefArticle
	 * @param bool $byRef
	 * @return bool|string
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 * @throws NonUniqueResultException
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
            $statut = $this->statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $articleStatut);
            if ($demande === 'collecte') {
                $articles = $this->articleRepository->findByRefArticleAndStatut($refArticle, $statut);
            } else if ($demande === 'demande') {
                $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statut);
            } else {
                $articles = [];
            }
            if (count($articles) < 1) {
                $articles[] = [
                    'id' => '',
                    'reference' => 'aucun article disponible',
                ];
            }
            $quantity = $this->refArticleDataService->getAvailableQuantityForRef($refArticle);
			if ($byRef && $demande == 'demande') {
				$json = $this->templating->render('demande/choiceContent.html.twig', [
					'maximum' => $quantity
				]);
			} else {
				$json = $this->templating->render($demande . '/newRefArticleByQuantiteArticleContent.html.twig', [
					'articles' => $articles,
					'maximum' => $quantity
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
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
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
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getLivraisonArticlesByRefArticle($refArticle)
    {
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('demande/newRefArticleByQuantiteRefContent.html.twig'),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $statutArticleActif = $this->statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
            $articles = $this->articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif);
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
            $availableQuantity = $this->refArticleDataService->getAvailableQuantityForRef($refArticle);
            $byRef = $paramQuantite->getValue() == Parametre::VALUE_PAR_REF;
            if ($byRef) {
            	$data = [
            	    'selection' => $this->templating->render('demande/choiceContent.html.twig', [
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
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getViewEditArticle($article,
                                       $isADemand = false)
    {
        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $typeArticle = $refArticle->getType();
        $typeArticleLabel = $typeArticle->getLabel();

        $champsLibresComplet = $this->champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
        $champsLibres = [];
        foreach ($champsLibresComplet as $champLibre) {
            $valeurChampArticle = $this->valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champLibre);

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

        return $this->templating->render('article/modalModifyArticleContent.html.twig', [
            'typeChampsLibres' => $typeChampsLibres,
            'typeArticle' => $typeArticleLabel,
            'typeArticleId' => $typeArticle->getId(),
            'article' => $article,
            'statut' => $statut,
            'isADemand' => $isADemand
        ]);
    }

    public function editArticle($data)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $entityManager = $this->em;
        $price = max(0, $data['prix']);
        $article = $this->articleRepository->find($data['article']);
        if ($article) {
            if ($this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                $article
                    ->setPrixUnitaire($price)
                    ->setLabel($data['label'])
                    ->setConform(!$data['conform'])
                    ->setQuantite($data['quantite'] ? max($data['quantite'], 0) : 0)// protection contre quantités négatives
                    ->setCommentaire($data['commentaire']);

                if (isset($data['statut'])) { // si on est dans une demande (livraison ou collecte), pas de champ statut
                    $statut = $this->statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $data['statut']);
                    if ($statut) $article->setStatut($statut);
                }
                if ($data['emplacement']) {
                    $article->setEmplacement($this->emplacementRepository->find($data['emplacement']));
                }
            }

            $champLibresKey = array_keys($data);
            foreach ($champLibresKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $this->champLibreRepository->find($champ);
                    $valeurChampLibre = $this->valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champ);
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addArticle($article)
                            ->setChampLibre($champLibre);
                    }
                    $valeurChampLibre->setValeur(is_array($data[$champ]) ? implode(";", $data[$champ]) : $data[$champ]);
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

	/**
	 * @param array $data
	 * @param Demande $demande
	 * @param Reception $reception
	 * @return Article
	 * @throws NonUniqueResultException
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function newArticle($data, $demande = null, $reception = null)
    {
        $entityManager = $this->em;
        $statusLabel = isset($data['statut']) ? ($data['statut'] === Article::STATUT_ACTIF ? Article::STATUT_ACTIF : Article::STATUT_INACTIF) : Article::STATUT_ACTIF;
        $statut = $this->statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $statusLabel);
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $formattedDate = $date->format('ym');
        $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
        $refReferenceArticle = $refArticle->getReference();
        $references = $this->articleRepository->getReferencesByRefAndDate($refReferenceArticle, $formattedDate);

        $highestCpt = 0;
        foreach ($references as $reference) {
        	$cpt = (int)substr($reference, -5, 5);
        	if ($cpt > $highestCpt) $highestCpt = $cpt;
		}

        $i = $highestCpt + 1;
        $cpt = sprintf('%05u', $i);

        $toInsert = new Article();
        $price = isset($data['prix']) ? max(0, $data['prix']) : null;
        $type = $this->articleFournisseurRepository->find($data['articleFournisseur'])->getReferenceArticle()->getType();
        if (isset($data['emplacement'])) {
			$location = $this->emplacementRepository->find($data['emplacement']);
		} else {
        	$location = $this->emplacementRepository->findOneByLabel(Emplacement::LABEL_A_DETERMINER);
        	if (!$location) {
        		$location = new Emplacement();
        		$location
					->setLabel(Emplacement::LABEL_A_DETERMINER);
        		$entityManager->persist($location);
			}
        	$location->setIsActive(true);
        	$entityManager->flush();
		}

        $toInsert
            ->setLabel(isset($data['libelle']) ? $data['libelle'] : $refArticle->getLibelle())
            ->setConform(isset($data['conform']) ? !$data['conform'] : true)
            ->setStatut($statut)
            ->setCommentaire(isset($data['commentaire']) ? $data['commentaire'] : null)
            ->setPrixUnitaire($price)
            ->setReference($refReferenceArticle . $formattedDate . $cpt)
            ->setQuantite(max((int)$data['quantite'], 0))// protection contre quantités négatives
            ->setEmplacement($location)
            ->setArticleFournisseur($this->articleFournisseurRepository->find($data['articleFournisseur']))
            ->setType($type)
			->setBarCode($this->generateBarCode());
        $entityManager->persist($toInsert);

        $champLibreKey = array_keys($data);
        foreach ($champLibreKey as $champ) {
            if (gettype($champ) === 'integer') {
                    $valeurChampLibre = new ValeurChampLibre();
                    $valeurChampLibre
                        ->addArticle($toInsert)
                        ->setChampLibre($this->champLibreRepository->find($champ));
                    $entityManager->persist($valeurChampLibre);
                $valeurChampLibre->setValeur(is_array($data[$champ]) ? implode(";", $data[$champ]) : $data[$champ]);
                $entityManager->flush();
            }
        }

        // optionnel : ajout dans une demande
		if ($demande) {
			$demande->addArticle($toInsert);
		}

		// optionnel : ajout dans une réception
		if ($reception) {
			$noCommande = isset($data['noCommande']) ? $data['noCommande'] : null;
			$rra = $this->receptionReferenceArticleRepository->findOneByReceptionAndCommandeAndRefArticleId($reception, $noCommande, $refArticle->getId());
			$toInsert->setReceptionReferenceArticle($rra);
			$entityManager->flush();
			$mailContent = $this->templating->render('mails/mailArticleUrgentReceived.html.twig', [
                'article' => $toInsert,
                'title' => 'Votre article urgent a bien été réceptionné.',
            ]);
			// gestion des urgences
			if ($refArticle->getIsUrgent()) {
				// on envoie un mail aux demandeurs
				$this->mailerService->sendMail(
					'FOLLOW GT // Article urgent réceptionné', $mailContent,
					$demande ? $demande->getUtilisateur() ? $demande->getUtilisateur()->getEmail() : '' : ''
				);
				// on retire l'urgence
				$refArticle->setIsUrgent(false);
			}
		}
        $entityManager->flush();
        return $toInsert;
    }

    public function getDataForDatatable($params = null, $user)
    {
		$data = $this->getArticleDataByParams($params, $user);
        return $data;
    }

    public function getDataForDatatableByReceptionLigne($ligne, $user)
    {
        if ($ligne) {
            $data = $this->getArticleDataByReceptionLigne($ligne);
        } else {
			$data = $this->getArticleDataByParams(null, $user);
        }
        return $data;
    }

	/**
	 * @param ReceptionReferenceArticle $ligne
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 * @throws DBALException
	 */
    public function getArticleDataByReceptionLigne(ReceptionReferenceArticle $ligne)
    {
        $articles = $ligne->getArticles();
        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowRefArticle($article);
        }
        return ['data' => $rows];
    }

	/**
	 * @param null $params
	 * @param Utilisateur $user
	 * @return array
	 * @throws DBALException
	 * @throws ORMException
	 * @throws OptimisticLockException
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function getArticleDataByParams($params = null, $user)
    {
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARTICLE, $user);

		// l'utilisateur qui n'a pas le droit de modifier le stock ne doit pas voir les articles inactifs
		if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
			$filters = [[
				'field' => FiltreSup::FIELD_STATUT,
				'value' => Article::STATUT_ACTIF . ',' . Article::STATUT_EN_TRANSIT
			]];
		}
		$queryResult = $this->articleRepository->findByParamsAndFilters($params, $filters, $user);

        $articles = $queryResult['data'];
        $listId = $queryResult['allArticleDataTable'];
        $articlesString = [];
        foreach ($listId as $id) {
            $articlesString[] = is_array($id) ? $id[0]->getId() : $id->getId();
        }

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowRefArticle(is_array($article) ? $article[0] : $article);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $this->articleRepository->countAll(),
            'listId' => $articlesString,
        ];
    }

    /**
     * @param Article $article
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     * @throws DBALException
     */
    public function dataRowRefArticle($article)
    {
        $rows = $this->valeurChampLibreRepository->getLabelCLAndValueByArticle($article);
        $rowCL = [];
        foreach ($rows as $row) {
            $rowCL[$row['label']] = $row['valeur'];
        }
        $url['edit'] = $this->router->generate('demande_article_edit', ['id' => $article->getId()]);
        if ($this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
			$status = $article->getStatut() ? $article->getStatut()->getNom() : 'Non défini';
		} else {
        	$status = '';
		}

		$criteriaFactory = Criteria::create();
		$exprFactory = Criteria::expr();
		$mouvementsFiltered = $article
			->getMouvements()
			->matching(
				$criteriaFactory
					->andWhere($exprFactory->eq('type', MouvementStock::TYPE_ENTREE))
					->orderBy(['date' => Criteria::DESC])
			);

		/** @var MouvementStock $mouvementEntree */
		$mouvementEntree = $mouvementsFiltered->count() > 0 ? $mouvementsFiltered->first() : null;

		$row = [
			'id' => $article->getId() ?? 'Non défini',
			'Référence' => $article->getReference() ?? 'Non défini',
			'Statut' => $status,
			'Libellé' => $article->getLabel() ?? 'Non défini',
			'Date et heure' => ($mouvementEntree && $mouvementEntree->getDate()) ? $mouvementEntree->getDate()->format('Y/m/d H:i:s') : '',
			'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : 'Non défini'),
            'Quantité' => $article->getQuantite() ?? 0,
			'Type' => $article->getType() ? $article->getType()->getLabel() : '',
			'Emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : ' Non défini',
			'Commentaire' => $article->getCommentaire(),
			'Prix unitaire' => $article->getPrixUnitaire(),
			'Code barre' => $article->getBarCode() ?? 'Non défini',
			'Actions' => $this->templating->render('article/datatableArticleRow.html.twig', [
				'url' => $url,
				'articleId' => $article->getId(),
				'demandeId' => $article->getDemande() ? $article->getDemande()->getId() : null
			]),
		];

        $rows = array_merge($rowCL, $row);
        return $rows;
    }

	/**
	 * @return string
	 * @throws NonUniqueResultException
	 */
	public function generateBarCode()
	{
		$now = new \DateTime('now');
		$dateCode = $now->format('ym');

		$highestBarCode = $this->articleRepository->getHighestBarCodeByDateCode($dateCode);
		$highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;

		$newCounter =  sprintf('%08u', $highestCounter+1);
		$newBarcode = Article::BARCODE_PREFIX . $dateCode . $newCounter;

		return $newBarcode;
	}

    public function getBarcodeConfig(Article $article, bool $wantBL = false): array {
        $articles = $this->articleRepository->getRefAndLabelRefAndArtAndBarcodeAndBLById($article->getId());
        $wantedIndex = 0;
        foreach ($articles as $key => $articleWithCL) {
            if ($articleWithCL['cl'] === ChampLibre::SPECIC_COLLINS_BL) {
                $wantedIndex = $key;
                break;
            }
        }
        $articleArray = $articles[$wantedIndex];

        $articleFournisseur = $article->getArticleFournisseur();
        $refArticle = isset($articleFournisseur) ? $articleFournisseur->getReferenceArticle() : null;
        $refRefArticle = isset($refArticle) ? $refArticle->getReference() : null;
        $labelRefArticle = isset($refArticle) ? $refArticle->getLibelle() : null;
        $labelArticle = $article->getLabel();
        $blLabel = (($wantBL && ($articleArray['cl'] === ChampLibre::SPECIC_COLLINS_BL))
            ? $articleArray['bl']
            : '');

        $labels = [
            !empty($labelRefArticle) ? ('L/R : ' . $labelRefArticle) : '',
            !empty($refRefArticle) ? ('C/R : ' . $refRefArticle) : '',
            !empty($labelArticle) ? ('L/A : ' . $labelArticle) : '',
            !empty($blLabel) ? ('BL : ' . $blLabel) : ''
        ];
        return [
            'code' => $article->getBarCode(),
            'labels' => array_filter($labels, function (string $label) {
                return !empty($label);
            })
        ];
    }
}
