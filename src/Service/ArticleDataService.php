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
use App\Entity\CategoryType;
use App\Entity\FreeField;
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
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Utilisateur;
use App\Entity\CategorieCL;
use App\Helper\Stream;
use DateTime;
use DateTimeZone;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Json;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;
use Twig\Environment as Twig_Environment;

class ArticleDataService
{
    private $templating;
    private $router;
    private $refArticleDataService;
    private $userService;
    private $specificService;
    private $mailerService;
    private $entityManager;
    private $wantCLOnLabel;
	private $clWantedOnLabel;
	private $clIdWantedOnLabel;
	private $typeCLOnLabel;
	private $freeFieldService;
    private $mouvementStockService;

    public function __construct(FreeFieldService $champLibreService,
                                MailerService $mailerService,
                                SpecificService $specificService,
                                RouterInterface $router,
                                UserService $userService,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager,
                                MouvementStockService $mouvementStockService,
                                Twig_Environment $templating) {
        $this->refArticleDataService = $refArticleDataService;
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->userService = $userService;
        $this->router = $router;
        $this->specificService = $specificService;
        $this->mailerService = $mailerService;
        $this->freeFieldService = $champLibreService;
        $this->mouvementStockService = $mouvementStockService;
    }

    /**
     * @param ReferenceArticle $refArticle
     * @param string $demande
     * @param bool $byRef
     * @return bool|string
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getArticleOrNoByRefArticle($refArticle, $demande, $byRef)
    {
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        if ($demande === 'livraison') {
            $demande = 'demande';
        }

        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $json = $this->templating->render('reference_article/newRefArticleByQuantiteRefContent.html.twig', [
                'maximum' => $demande === 'demande' ? $refArticle->getQuantiteDisponible() : null,
                'needsQuantity' => $demande !== 'transfert'
            ]);
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $articleRepository = $this->entityManager->getRepository(Article::class);
            if ($demande === 'collecte') {
                $articles = $articleRepository->findByRefArticleAndStatut($refArticle, [Article::STATUT_INACTIF]);
            } else if ($demande === 'demande') {
                $articles = $articleRepository->findActifByRefArticleWithoutDemand($refArticle);
            } else if ($demande === 'transfert') {
                $articles = $articleRepository->findByRefArticleAndStatut($refArticle, [Article::STATUT_ACTIF]);
            } else {
                $articles = [];
            }
            if (empty($articles)) {
                $articles[] = [
                    'id' => '',
                    'barCode' => 'aucun article disponible',
                ];
            }

            $quantity = $refArticle->getQuantiteDisponible();
            if ($byRef && $demande == 'demande') {
				$json = $this->templating->render('demande/choiceContent.html.twig', [
					'maximum' => $quantity
				]);
			} else {
				$json = $this->templating->render('reference_article/newRefArticleByQuantiteArticleContent.html.twig', [
					'articles' => $articles,
					'maximum' => $demande === 'transfert' ? null : $quantity
				]);
			}


        } else {
            $json = false; //TODO gérer erreur retour
        }

        return $json;
    }


    //TODOO les méthode getCollecteArticleOrNoByRefArticle() et getLivraisonArticleOrNoByRefArticle() ont le même fonctionnement la seul différence et le statut de l'article (actif/ inactif)

    /**
     * @param ReferenceArticle $refArticle
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
     * @param ReferenceArticle $refArticle
     * @param Utilisateur $user
     * @return array
     *
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getLivraisonArticlesByRefArticle(ReferenceArticle $refArticle, Utilisateur $user)
    {
        if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $data = [
                'modif' => $this->refArticleDataService->getViewEditRefArticle($refArticle, true),
                'selection' => $this->templating->render('demande/newRefArticleByQuantiteRefContent.html.twig', [
                    'maximum' => $refArticle->getQuantiteDisponible()
                ]),
            ];
        } elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $articleRepository = $this->entityManager->getRepository(Article::class);
            $parametreRoleRepository = $this->entityManager->getRepository(ParametreRole::class);

            $articles = $articleRepository->findActifByRefArticleWithoutDemand($refArticle);
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
                $management = $refArticle->getStockManagement();
                $articleToPreselect = null;
                if ($management) {
                    $articles = Stream::from($articles)
                        ->sort(function (Article $article1, Article $article2) use ($management) {
                            $datesToCompare = [];
                            if ($management === ReferenceArticle::STOCK_MANAGEMENT_FIFO) {
                                $datesToCompare[0] = $article1->getStockEntryDate() ? $article1->getStockEntryDate()->format('Y-m-d') : null;
                                $datesToCompare[1] = $article2->getStockEntryDate() ? $article2->getStockEntryDate()->format('Y-m-d') : null;
                            } else if ($management === ReferenceArticle::STOCK_MANAGEMENT_FEFO) {
                                $datesToCompare[0] = $article1->getExpiryDate() ? $article1->getExpiryDate()->format('Y-m-d') : null;
                                $datesToCompare[1] = $article2->getExpiryDate() ? $article2->getExpiryDate()->format('Y-m-d') : null;
                            }
                            if ($datesToCompare[0] && $datesToCompare[1]) {
                                if (strtotime($datesToCompare[0]) === strtotime($datesToCompare[1])) {
                                    return 0;
                                }
                                return strtotime($datesToCompare[0]) < strtotime($datesToCompare[1]) ? -1 : 1;
                            } else if ($datesToCompare[0]) {
                                return -1;
                            } else if ($datesToCompare[1]) {
                                return 1;
                            }
                            return 0;
                        })->toArray();
                }
                $data = [
                    'selection' => $this->templating->render('demande/newRefArticleByQuantiteArticleContent.html.twig', [
                        'articles' => $articles,
                        'preselect' => isset($management),
                        'maximum' => $availableQuantity,
                    ])
                ];
            }
        } else {
            $data = false; //TODO gérer erreur retour
        }

        return $data;
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
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);

        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        $typeArticle = $refArticle->getType();
        $typeArticleLabel = $typeArticle->getLabel();

		$champsLibresComplet = $champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
        $champsLibres = [];
        foreach ($champsLibresComplet as $champLibre) {

            $champsLibres[] = [
                'id' => $champLibre->getId(),
                'label' => $champLibre->getLabel(),
                'typage' => $champLibre->getTypage(),
                'requiredCreate' => $champLibre->getRequiredCreate(),
                'requiredEdit' => $champLibre->getRequiredEdit(),
                'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                'defaultValue' => $champLibre->getDefaultValue()
            ];
        }

        return $this->templating->render('article/modalArticleContent.html.twig', [
            'typeChampsLibres' => [
                'type' => $typeArticleLabel,
                'champsLibres' => $champsLibres,
            ],
            'typeArticle' => $typeArticleLabel,
            'typeArticleId' => $typeArticle->getId(),
            'article' => $article,
            'statut' => $article->getStatut() ? $article->getStatut()->getNom() : '',
            'isADemand' => $isADemand,
            'invCategory' => $refArticle->getCategory()
        ]);
    }

    /**
     * @param $data
     * @return bool|RedirectResponse
     */
    public function editArticle($data)
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            return new RedirectResponse($this->router->generate('access_denied'));
        }

        $articleRepository = $this->entityManager->getRepository(Article::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);


        $article = $articleRepository->find($data['article']);
        if ($article) {
            if ($this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {

                $expiryDate = !empty($data['expiry']) ? DateTime::createFromFormat("Y-m-d", $data['expiry']) : null;
                $price = max(0, $data['prix'] ?? 0);
                if (isset($data['label'])) {
                    $article
                        ->setPrixUnitaire($price)
                        ->setLabel($data['label'])
                        ->setConform(!$data['conform'])
                        ->setBatch($data['batch'] ?? null)
                        ->setExpiryDate($expiryDate ? $expiryDate : null)
                        ->setCommentaire($data['commentaire']);

                    if (isset($data['statut'])) { // si on est dans une demande (livraison ou collecte), pas de champ statut
                        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, $data['statut']);
                        if ($statut) {
                            $article->setStatut($statut);
                        }
                    }
                }
            }

            $this->freeFieldService->manageFreeFields($article, $data, $this->entityManager);
            $this->entityManager->flush();
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $data
     * @param Demande|null $demande
     * @return Article
     *
     * @throws Exception
     */
    public function newArticle($data, Demande $demande = null) {
        $entityManager = $this->entityManager;
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $statusLabel = (!isset($data['statut']) || ($data['statut'] === Article::STATUT_ACTIF)) ? Article::STATUT_ACTIF : Article::STATUT_INACTIF;
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
		}

        $quantity = max((int)$data['quantite'], 0); // protection contre quantités négatives
        $toInsert
            ->setLabel(isset($data['libelle']) ? $data['libelle'] : $refArticle->getLibelle())
            ->setConform(isset($data['conform']) ? !$data['conform'] : true)
            ->setStatut($statut)
            ->setCommentaire(isset($data['commentaire']) ? $data['commentaire'] : null)
            ->setPrixUnitaire($price)
            ->setReference($refReferenceArticle . $formattedDate . $cpt)
            ->setQuantite($quantity)
            ->setEmplacement($location)
            ->setArticleFournisseur($articleFournisseurRepository->find($data['articleFournisseur']))
            ->setType($type)
            ->setBarCode($this->generateBarCode())
            ->setStockEntryDate(new DateTime("now", new DateTimeZone("Europe/Paris")));

        if (isset($data['batch'])) {
            $toInsert->setBatch($data['batch']);
        }

        if (isset($data['expiry'])) {
            $toInsert->setExpiryDate($data['expiry'] ? DateTime::createFromFormat("Y-m-d", $data['expiry']) : null);
        }
        $entityManager->persist($toInsert);
        $this->freeFieldService->manageFreeFields($toInsert, $data, $entityManager);
        // optionnel : ajout dans une demande
        if ($demande) {
            $demande->addArticle($toInsert);
            $toInsert->setQuantiteAPrelever($toInsert->getQuantite());

            if (count($demande->getPreparations()) > 0) {
                $toInsert->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                $toInsert->setQuantitePrelevee($toInsert->getQuantite());
                $demande->getPreparations()[0]->addArticle($toInsert);
            }
        }

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
     */
    public function getArticleDataByReceptionLigne(ReceptionReferenceArticle $ligne)
    {
        $articles = $ligne->getArticles();
        $reception = $ligne->getReception();
        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowArticle($article, $reception);
        }
        return ['data' => $rows];
    }

    /**
     * @param null $params
     * @param Utilisateur $user
     * @return array
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
                'value' => $this->getActiveArticleFilterValue()
            ]];
        }

        $champs = $this->freeFieldService->getFreeFieldLabelToId($this->entityManager, CategorieCL::ARTICLE, CategoryType::ARTICLE);

        $queryResult = $articleRepository->findByParamsAndFilters($params, $filters, $user, $champs);

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
     * @param Reception|null $reception
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function dataRowArticle($article, Reception $reception = null)
    {
        $categorieCLRepository = $this->entityManager->getRepository(CategorieCL::class);
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);
        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE);

        $category = CategoryType::ARTICLE;
        $champs = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        $rowCL = [];
        /** @var FreeField $champ */
        foreach ($champs as $champ) {
            $rowCL[$champ['label']] = $this->freeFieldService->serializeValue([
                'valeur' => $article->getFreeFieldValue($champ['id']),
                "typage" => $champ['typage'],
            ]);
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
            "Lot" => $article->getBatch(),
            "Date d'entrée en stock" => $article->getStockEntryDate() ? $article->getStockEntryDate()->format('d/m/Y H:i') : '',
            "Date de péremption" => $article->getExpiryDate() ? $article->getExpiryDate()->format('d/m/Y') : '',
            'Actions' => $this->templating->render('article/datatableArticleRow.html.twig', [
                'url' => $url,
                'articleId' => $article->getId(),
                'demandeId' => $article->getDemande() ? $article->getDemande()->getId() : null,
                'articleFilter' => $article->getBarCode(),
                'fromReception' => isset($reception),
                'receptionId' => $reception ? $reception->getId() : null
            ]),
        ];

        $rows = array_merge($rowCL, $row);
        return $rows;
    }

    /**
     * @return string
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
     * @param Reception|null $reception
     * @return array
     */
    public function getBarcodeConfig(Article $article, Reception $reception = null): array {
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);

        if (!isset($this->wantCLOnLabel)
            && !isset($this->clWantedOnLabel)
            && !isset($this->typeCLOnLabel)) {

            $champLibreRepository = $this->entityManager->getRepository(FreeField::class);
            $categoryCLRepository = $this->entityManager->getRepository(CategorieCL::class);
            $this->clWantedOnLabel = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CL_USED_IN_LABELS);
            $this->wantCLOnLabel = (bool) $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);

            if (isset($this->clWantedOnLabel)) {
                $champLibre = $champLibreRepository->findOneBy([
                    'categorieCL' => $categoryCLRepository->findOneByLabel(CategoryType::ARTICLE),
                    'label' => $this->clWantedOnLabel
                ]);

                $this->typeCLOnLabel = isset($champLibre) ? $champLibre->getTypage() : null;
                $this->clIdWantedOnLabel = isset($champLibre) ? $champLibre->getId() : null;
            }
        }

        $articleFournisseur = $article->getArticleFournisseur();
        $refArticle = isset($articleFournisseur) ? $articleFournisseur->getReferenceArticle() : null;
        $refRefArticle = isset($refArticle) ? $refArticle->getReference() : null;
        $labelRefArticle = isset($refArticle) ? $refArticle->getLibelle() : null;

        $quantityArticle = $article->getQuantite();
        $labelArticle = $article->getLabel();
        $champLibreValue = $this->clIdWantedOnLabel ? $article->getFreeFieldValue($this->clIdWantedOnLabel) : '';
        $batchArticle = $article->getBatch() ?? '';
        $expirationDateArticle = $article->getExpiryDate() ? $article->getExpiryDate()->format('d/m/Y') : '';

        $wantsRecipient = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL);
        $wantsRecipientDropzone = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL);
        $wantDestinationLocation = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL);

        // Récupération du username & dropzone de l'utilisateur
        $articleReception = $article->getReceptionReferenceArticle() ? $article->getReceptionReferenceArticle()->getReception() : '';
        $articleReceptionRecipient = $articleReception ? $articleReception->getUtilisateur() : '';
        $articleReceptionRecipientUsername = ($articleReceptionRecipient && $wantsRecipient) ? $articleReceptionRecipient->getUsername() : '';
        $articleReceptionRecipientDropzone = $articleReceptionRecipient ? $articleReceptionRecipient->getDropzone() : '';
        $articleReceptionRecipientDropzoneLabel = ($articleReceptionRecipientDropzone && $wantsRecipientDropzone) ? $articleReceptionRecipientDropzone->getLabel() : '';

        $articleLinkedToTransferRequestToTreat = $article->getTransferRequests()->map(function (TransferRequest $transferRequest) use ($reception) {
            if ($transferRequest->getStatus()->getNom() === TransferOrder::TO_TREAT) {
                $transferRequestLocation = $reception->getStorageLocation() ? $reception->getStorageLocation()->getLabel() : '';
            } else {
                $transferRequestLocation = '';
            }
            return $transferRequestLocation;
        });

        if (isset($reception) && $wantDestinationLocation && !empty($articleLinkedToTransferRequestToTreat[0])) {
            $location = $reception->getStorageLocation() ? $reception->getStorageLocation()->getLabel() : '';
        }
        else if (isset($reception) && $wantDestinationLocation) {
            $location = $article->getDemande() ? $article->getDemande()->getDestination()->getLabel() : '';
        }
        else if ($wantsRecipientDropzone
                && $articleReceptionRecipient
                && isset($reception)
                && !$wantDestinationLocation) {
            $location = $articleReceptionRecipientDropzoneLabel;
        }
        else {
            $location = '';
        }

        if ($wantsRecipient && isset($reception) && !$reception->getDemandes()->isEmpty()) {
            $username = $articleReceptionRecipientUsername;
        }
        else {
            $username = '';
        }

        $separator = ($location && $username) ? ' / ' : '';

        $labels = [
            $username . $separator . $location,
            !empty($labelRefArticle) ? ('L/R : ' . $labelRefArticle) : '',
            !empty($refRefArticle) ? ('C/R : ' . $refRefArticle) : '',
            !empty($labelArticle) ? ('L/A : ' . $labelArticle) : '',
            (!empty($this->typeCLOnLabel) && !empty($champLibreValue)) ? ($champLibreValue) : '',
        ];

        $wantsQTT = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_QTT_IN_LABEL);
        $wantsBatchArticle = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL);
        $wantsExpirationDateArticle = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL);

        if ($wantsBatchArticle) {
            $labels[] = !empty($batchArticle) ? ('N° lot : '. $batchArticle) : '';
        }

        if ($wantsExpirationDateArticle) {
            $labels[] = !empty($expirationDateArticle) ? ('Date péremption : '. $expirationDateArticle) : '';
        }

        if ($wantsQTT) {
            $labels[] = !empty($quantityArticle) ? ('Qte : '. $quantityArticle) : '';
        }

        return [
            'code' => $article->getBarCode(),
            'labels' => array_filter($labels, function (string $label) {
                return !empty($label);
            })
        ];
    }

    public function getActiveArticleFilterValue(): string {
        return Article::STATUT_ACTIF . ',' . Article::STATUT_EN_TRANSIT . ',' . Article::STATUT_EN_LITIGE;
    }

    public function articleCanBeAddedInDispute(Article $article): bool {
        return in_array($article->getStatut()->getNom(), [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE]);
    }
}
