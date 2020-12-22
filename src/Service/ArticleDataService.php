<?php

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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
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
    private $visibleColumnService;

    public function __construct(FreeFieldService $champLibreService,
                                MailerService $mailerService,
                                SpecificService $specificService,
                                RouterInterface $router,
                                UserService $userService,
                                RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager,
                                VisibleColumnService $visibleColumnService,
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
        $this->visibleColumnService = $visibleColumnService;
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
     * @param int|null $deliveryRequestId
     * @return array
     *
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function getLivraisonArticlesByRefArticle(ReferenceArticle $refArticle, Utilisateur $user, ?int $deliveryRequestId)
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
                        'deliveryRequestId' => $deliveryRequestId
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
     * @param EntityManagerInterface $entityManager
     * @param Demande|null $demande
     * @return Article
     *
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    public function newArticle($data, EntityManagerInterface $entityManager, Demande $demande = null) {
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

    public function getDataForDatatable($params, $user)
    {
        return $this->getArticleDataByParams($params, $user);
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

    public function getArticleDataByParams($params, Utilisateur $user) {
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

        $queryResult = $articleRepository->findByParamsAndFilters($params, $filters, $user);

        $articles = $queryResult['data'];

        $rows = [];
        foreach ($articles as $article) {
            $rows[] = $this->dataRowArticle(is_array($article) ? $article[0] : $article);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $articleRepository->countAll()
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
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

        $url['edit'] = $this->router->generate('demande_article_edit', ['id' => $article->getId()]);
        if ($this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
            $status = $article->getStatut() ? $article->getStatut()->getNom() : 'Non défini';
        } else {
            $status = '';
        }

        $supplierArticle = $article->getArticleFournisseur();
        $referenceArticle = $supplierArticle ? $supplierArticle->getReferenceArticle() : null;

        $row = [
            "id" => $article->getId() ?? "Non défini",
            "label" => $article->getLabel() ?? "Non défini",
            "articleReference" => $referenceArticle ? $referenceArticle->getReference() : "Non défini",
            "supplierReference" => $supplierArticle ? $supplierArticle->getReference() : "Non défini",
            "barCode" => $article->getBarCode() ?? "Non défini",
            "type" => $article->getType() ? $article->getType()->getLabel() : '',
            "status" => $status,
            "quantity" => $article->getQuantite() ?? 0,
            "location" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : ' Non défini',
            "unitPrice" => $article->getPrixUnitaire(),
            "dateLastInventory" => $article->getDateLastInventory() ? $article->getDateLastInventory()->format('d/m/Y') : '',
            "batch" => $article->getBatch(),
            "stockEntryDate" => $article->getStockEntryDate() ? $article->getStockEntryDate()->format('d/m/Y H:i') : '',
            "expiryDate" => $article->getExpiryDate() ? $article->getExpiryDate()->format('d/m/Y') : '',
            "comment" => $article->getCommentaire(),
            "actions" => $this->templating->render('article/datatableArticleRow.html.twig', [
                'url' => $url,
                'articleId' => $article->getId(),
                'demandeId' => $article->getDemande() ? $article->getDemande()->getId() : null,
                'articleFilter' => $article->getBarCode(),
                'fromReception' => isset($reception),
                'receptionId' => $reception ? $reception->getId() : null
            ]),
        ];

        foreach ($freeFields as $field) {
            $freeFieldId = $field["id"];
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $row[$freeFieldName] = $this->freeFieldService->serializeValue([
                "valeur" => $article->getFreeFieldValue($freeFieldId),
                "typage" => $field["typage"],
            ]);
        }

        return $row;
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

    public function getColumnVisibleConfig(EntityManagerInterface $entityManager,
                                           Utilisateur $currentUser): array {

        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $categorieCLRepository = $entityManager->getRepository(CategorieCL::class);

        $categorieCL = $categorieCLRepository->findOneByLabel(CategorieCL::ARTICLE);
        $freeFields = $champLibreRepository->getByCategoryTypeAndCategoryCL(CategoryType::ARTICLE, $categorieCL);

        $fieldConfig = [
            ['name' => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true],
            ["title" => "Libellé", "name" => "label", 'searchable' => true],
            ["title" => "Référence article", "name" => "articleReference", 'searchable' => true],
            ["title" => "Référence fournisseur", "name" => "supplierReference", 'searchable' => true],
            ["title" => "Code barre", "name" => "barCode", 'searchable' => true],
            ["title" => "Type", "name" => "type", 'searchable' => true],
            ["title" => "Statut", "name" => "status", 'searchable' => true],
            ["title" => "Quantité", "name" => "quantity", 'searchable' => true],
            ["title" => "Emplacement", "name" => "location", 'searchable' => true],
            ["title" => "Prix unitaire", "name" => "unitPrice"],
            ["title" => "Dernier inventaire", "name" => "dateLastInventory", 'searchable' => true],
            ["title" => "Lot", "name" => "batch"],
            ["title" => "Date d'entrée en stock", "name" => "stockEntryDate"],
            ["title" => "Date d'expiration", "name" => "expiryDate", 'searchable' => true],
            ["title" => "Commentaire", "name" => "comment", 'searchable' => true]
        ];

        return $this->visibleColumnService->getArrayConfig($fieldConfig, $freeFields, $currentUser->getColumnsVisibleForArticle());
    }
}
