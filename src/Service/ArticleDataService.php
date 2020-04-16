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
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\ParametrageGlobal;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Entity\CategorieCL;
use DateTime;
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

    private $templating;
    private $user;
    private $router;

    private $refArticleDataService;
    private $userService;
    private $specificService;
    private $mailerService;

    private $entityManager;

    private $wantCLOnLabel;
	private $clWantedOnLabel;
	private $typeCLOnLabel;

	public function __construct(MailerService $mailerService,
                                SpecificService $specificService,
                                RouterInterface $router,
                                UserService $userService,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating) {
        $this->refArticleDataService = $refArticleDataService;
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->router = $router;
        $this->specificService = $specificService;
        $this->mailerService = $mailerService;
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
	 * @throws DBALException
	 */
    public function getArticleOrNoByRefArticle($refArticle, $demande, $modifieRefArticle, $byRef)
    {
        $statutRepository = $this->entityManager->getRepository(Statut::class);

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

            $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
            $typeRepository = $this->entityManager->getRepository(Type::class);

            $statuts = $statutRepository->findByCategorieName(ReferenceArticle::CATEGORIE);

            $json = $this->templating->render($demande . '/newRefArticleByQuantiteRefContent.html.twig', [
                'articleRef' => $refArticle,
                'articles' => $articleFournisseurRepository->findByRefArticle($refArticle->getId()),
                'statut' => ($refArticle->getStatut()->getNom() == ReferenceArticle::STATUT_ACTIF),
                'types' => $typeRepository->findByCategoryLabel(CategoryType::ARTICLE),
                'statuts' => $statuts,
                'modifieRefArticle' => $modifieRefArticle,
                'valeurChampLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                'articlesFournisseur' => ($data ? $data['listArticlesFournisseur'] : ''),
                'totalQuantity' => ($data['totalQuantity'] ? $data['totalQuantity'] : ''),
            ]);
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $articleRepository = $this->entityManager->getRepository(Article::class);
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $articleStatut);
            if ($demande === 'collecte') {
                $articles = $articleRepository->findByRefArticleAndStatut($refArticle, $statut);
            } else if ($demande === 'demande') {
                $articles = $articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statut);
            } else {
                $articles = [];
            }
            if (count($articles) < 1) {
                $articles[] = [
                    'id' => '',
                    'reference' => 'aucun article disponible',
                ];
            }
            $quantity = $refArticle->getQuantiteDisponible();
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
     * @throws DBALException
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
     * @param ReferenceArticle $refArticle
     * @param Utilisateur $user
     * @return array
     *
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getLivraisonArticlesByRefArticle(ReferenceArticle $refArticle, Utilisateur $user)
    {
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('demande/newRefArticleByQuantiteRefContent.html.twig'),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $articleRepository = $this->entityManager->getRepository(Article::class);
            $statutRepository = $this->entityManager->getRepository(Statut::class);
            $parametreRoleRepository = $this->entityManager->getRepository(ParametreRole::class);

            $statutArticleActif = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
            $articles = $articleRepository->findByRefArticleAndStatutWithoutDemand($refArticle, $statutArticleActif);
            $role = $user->getRole();

            $parametreRepository = $this->entityManager->getRepository(Parametre::class);
            $param = $parametreRepository->findOneBy(['label' => Parametre::LABEL_AJOUT_QUANTITE]);

            $paramQuantite = $parametreRoleRepository->findOneByRoleAndParam($role, $param);

            // si le paramétrage n'existe pas pour ce rôle, on le crée (valeur par défaut)
            if (!$paramQuantite) {
                $paramQuantite = new ParametreRole();
                $paramQuantite
                    ->setValue($param->getDefaultValue())
                    ->setRole($role)
                    ->setParametre($param);
                $this->entityManager->persist($paramQuantite);
                $this->entityManager->flush();
            }
            $availableQuantity = $refArticle->getQuantiteDisponible();
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
        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);

        $type = $article->getType();
        if ($type) {
            $valeurChampLibre = $valeurChampLibreRepository->getByArticleAndType($article->getId(), $type->getId());
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
        $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);
        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);

        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $typeArticle = $refArticle->getType();
        $typeArticleLabel = $typeArticle->getLabel();

		$champsLibresComplet = $champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
        $champsLibres = [];
        foreach ($champsLibresComplet as $champLibre) {
            $valeurChampArticle = $valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champLibre);

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

        return $this->templating->render('article/modalArticleContent.html.twig', [
            'typeChampsLibres' => $typeChampsLibres,
            'typeArticle' => $typeArticleLabel,
            'typeArticleId' => $typeArticle->getId(),
            'article' => $article,
            'statut' => $statut,
            'isADemand' => $isADemand,
			'invCategory' => $refArticle->getCategory()
        ]);
    }

    public function editArticle($data)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $articleRepository = $this->entityManager->getRepository(Article::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);
        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $price = max(0, $data['prix']);

        $article = $articleRepository->find($data['article']);

        if ($article) {
            if ($this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                $article
                    ->setPrixUnitaire($price)
                    ->setLabel($data['label'])
                    ->setConform(!$data['conform'])
                    ->setQuantite($data['quantite'] ? max($data['quantite'], 0) : 0)// protection contre quantités négatives
                    ->setCommentaire($data['commentaire']);

                if (isset($data['statut'])) { // si on est dans une demande (livraison ou collecte), pas de champ statut
                    $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $data['statut']);
                    if ($statut) $article->setStatut($statut);
                }
                if ($data['emplacement']) {
                    $article->setEmplacement($emplacementRepository->find($data['emplacement']));
                }
            }

            $champLibresKey = array_keys($data);
            foreach ($champLibresKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $champLibreRepository->find($champ);
                    $valeurChampLibre = $valeurChampLibreRepository->findOneByArticleAndChampLibre($article, $champ);
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampLibre();
                        $valeurChampLibre
                            ->addArticle($article)
                            ->setChampLibre($champLibre);
                    }
                    $valeurChampLibre->setValeur(is_array($data[$champ]) ? implode(";", $data[$champ]) : $data[$champ]);
                    $this->entityManager->persist($valeurChampLibre);
                    $this->entityManager->flush();
                }
            }
            $this->entityManager->flush();
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
        $entityManager = $this->entityManager;

        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $receptionReferenceArticleRepository = $this->entityManager->getRepository(ReceptionReferenceArticle::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $statusLabel = isset($data['statut']) ? ($data['statut'] === Article::STATUT_ACTIF ? Article::STATUT_ACTIF : Article::STATUT_INACTIF) : Article::STATUT_ACTIF;
        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $statusLabel);
        $date = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $formattedDate = $date->format('ym');

        $refArticle = $referenceArticleRepository->find($data['refArticle']);
        $refReferenceArticle = $refArticle->getReference();
        $references = $articleRepository->getReferencesByRefAndDate($refReferenceArticle, $formattedDate);

        $highestCpt = 0;
        foreach ($references as $reference) {
        	$cpt = (int)substr($reference, -5, 5);
        	if ($cpt > $highestCpt) $highestCpt = $cpt;
		}

        $i = $highestCpt + 1;
        $cpt = sprintf('%05u', $i);

        $toInsert = new Article();
        $price = isset($data['prix']) ? max(0, $data['prix']) : null;

        $type = $articleFournisseurRepository->find($data['articleFournisseur'])->getReferenceArticle()->getType();

        if (isset($data['emplacement'])) {
			$location = $emplacementRepository->find($data['emplacement']);
		} else {
        	$location = $emplacementRepository->findOneByLabel(Emplacement::LABEL_A_DETERMINER);
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
            ->setArticleFournisseur($articleFournisseurRepository->find($data['articleFournisseur']))
            ->setType($type)
			->setBarCode($this->generateBarCode());
        $entityManager->persist($toInsert);

        $champLibreKey = array_keys($data);
        foreach ($champLibreKey as $champ) {
            if (gettype($champ) === 'integer') {
                    $valeurChampLibre = new ValeurChampLibre();
                    $valeurChampLibre
                        ->addArticle($toInsert)
                        ->setChampLibre($champLibreRepository->find($champ));
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
			$rra = $receptionReferenceArticleRepository->findOneByReceptionAndCommandeAndRefArticleId($reception, $noCommande, $refArticle->getId());
			$toInsert->setReceptionReferenceArticle($rra);
			$entityManager->flush();
			// gestion des urgences
			if ($refArticle->getIsUrgent()) {
                $mailContent = $this->templating->render('mails/mailArticleUrgentReceived.html.twig', [
                    'article' => $toInsert,
                    'title' => 'Votre article urgent a bien été réceptionné.',
                ]);
                $destinataires = '';
                if ($refArticle->getUserThatTriggeredEmergency()) {
                    if ($demande && $demande->getUtilisateur()) {
                        $destinataires = [
                            $refArticle->getUserThatTriggeredEmergency()->getEmail(),
                            $demande->getUtilisateur()->getEmail()
                        ];
                    } else {
                        $destinataires = $refArticle->getUserThatTriggeredEmergency()->getEmail();
                    }
                } else {
                    if ($demande && $demande->getUtilisateur()) {
                        $destinataires = $demande->getUtilisateur()->getEmail();
                    }
                }
                // on envoie un mail aux demandeurs
                $this->mailerService->sendMail(
                    'FOLLOW GT // Article urgent réceptionné', $mailContent,
                    $destinataires
                );
                // on retire l'urgence
                $refArticle->setIsUrgent(false);
                $refArticle->setUserThatTriggeredEmergency(null);
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
            $rows[] = $this->dataRowArticle($article);
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
        $articleRepository = $this->entityManager->getRepository(Article::class);
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ARTICLE, $user);

		// l'utilisateur qui n'a pas le droit de modifier le stock ne doit pas voir les articles inactifs
		if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
			$filters = [[
				'field' => FiltreSup::FIELD_STATUT,
				'value' => Article::STATUT_ACTIF . ',' . Article::STATUT_EN_TRANSIT
			]];
		}

		$queryResult = $articleRepository->findByParamsAndFilters($params, $filters, $user);

        $articles = $queryResult['data'];
        $listId = $queryResult['allArticleDataTable'];
        $articlesString = [];
        foreach ($listId as $id) {
            $articlesString[] = is_array($id) ? $id[0]->getId() : $id->getId();
        }

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowArticle(is_array($article) ? $article[0] : $article);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $articleRepository->countAll(),
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
    public function dataRowArticle($article)
    {
        $valeurChampLibreRepository = $this->entityManager->getRepository(ValeurChampLibre::class);

        $rows = $valeurChampLibreRepository->getLabelCLAndValueByArticle($article);
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
			'Commentaire' => $article->getCommentaire() ?? '',
			'Prix unitaire' => $article->getPrixUnitaire(),
			'Code barre' => $article->getBarCode() ?? 'Non défini',
			"Dernier inventaire" => $article->getDateLastInventory() ? $article->getDateLastInventory()->format('d/m/Y') : '',
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
        $articleRepository = $this->entityManager->getRepository(Article::class);

		$now = new DateTime('now');
		$dateCode = $now->format('ym');

		$highestBarCode = $articleRepository->getHighestBarCodeByDateCode($dateCode);
		$highestCounter = $highestBarCode ? (int)substr($highestBarCode, 7, 8) : 0;

		$newCounter =  sprintf('%08u', $highestCounter+1);
		return Article::BARCODE_PREFIX . $dateCode . $newCounter;
	}

    /**
     * @param Article $article
     * @return array
     * @throws NonUniqueResultException
     */
    public function getBarcodeConfig(Article $article): array {
        $articleRepository = $this->entityManager->getRepository(Article::class);

        $articles = $articleRepository->getRefAndLabelRefAndArtAndBarcodeAndBLById($article->getId());

        if (!isset($this->wantCLOnLabel)
            && !isset($this->clWantedOnLabel)
            && !isset($this->typeCLOnLabel)) {
            $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
            $champLibreRepository = $this->entityManager->getRepository(ChampLibre::class);
            $categoryCLRepository = $this->entityManager->getRepository(CategorieCL::class);
            $this->clWantedOnLabel = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CL_USED_IN_LABELS);
            $this->wantCLOnLabel = (bool) $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
            if (isset($this->clWantedOnLabel)) {
                $champLibre = $champLibreRepository->findOneBy([
                    'categorieCL' => $categoryCLRepository->findOneByLabel(CategoryType::ARTICLE),
                    'label' => $this->clWantedOnLabel
                ]);
                $this->typeCLOnLabel = isset($champLibre) ? $champLibre->getTypage() : null;
            }
        }

        $wantedIndex = 0;
        foreach ($articles as $key => $articleWithCL) {
            if ($articleWithCL['cl'] === $this->clWantedOnLabel) {
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
        $champLibre = (($this->wantCLOnLabel && ($articleArray['cl'] === $this->clWantedOnLabel))
            ? $articleArray['bl']
            : '');
        $champLibreValue = (!empty($this->typeCLOnLabel))
            ? $this->getCLValue($champLibre, $this->typeCLOnLabel)
            : null;

        $labels = [
            !empty($labelRefArticle) ? ('L/R : ' . $labelRefArticle) : '',
            !empty($refRefArticle) ? ('C/R : ' . $refRefArticle) : '',
            !empty($labelArticle) ? ('L/A : ' . $labelArticle) : '',
            (!empty($this->typeCLOnLabel) && !empty($champLibreValue)) ? ($champLibreValue) : ''
        ];
        return [
            'code' => $article->getBarCode(),
            'labels' => array_filter($labels, function (string $label) {
                return !empty($label);
            })
        ];
    }

    private function getCLValue($cl, $typage) {
        $res = null;
        switch ($typage) {
            case ChampLibre::TYPE_BOOL:
                $res = !empty($cl) ? 'Oui' : 'Non';
                break;
            case ChampLibre::TYPE_DATE:
                $res = !empty($cl) ? (new DateTime($cl))->format('d/m/Y') : null;
                break;
            case ChampLibre::TYPE_DATETIME:
                $res = !empty($cl) ? (new DateTime($cl))->format('d/m/Y H:i') : null;
                break;
            case ChampLibre::TYPE_LIST_MULTIPLE:
                $res = !empty($cl) ? implode(', ', explode(';', $cl)) : null;
                break;
            default:
                $res = $cl;
                break;
        }
        return $res;
    }
}
