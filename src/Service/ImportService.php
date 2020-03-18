<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\InventoryCategory;
use App\Entity\PieceJointe;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ImportService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    private $em;
    private $user;
    private $articleDataService;
    private $refArticleDataService;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage,
								ArticleDataService $articleDataService,
								RefArticleDataService $refArticleDataService
	)
    {
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->router = $router;
        $this->articleDataService = $articleDataService;
        $this->refArticleDataService = $refArticleDataService;
    }

	/**
	 * @param null $params
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function getDataForDatatable($params = null)
    {
		$importRepository = $this->em->getRepository(Import::class);
		$filtreSupRepository = $this->em->getRepository(FiltreSup::class);

		$filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_IMPORT, $this->user);

		$queryResult = $importRepository->findByParamsAndFilters($params, $filters);

		$imports = $queryResult['data'];

		$rows = [];
		foreach ($imports as $import) {
			$rows[] = $this->dataRowImport($import);
		}
		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
    }

	/**
	 * @param Import $import
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowImport($import)
    {
        $importId = $import->getId();
        $url['edit'] = $this->router->generate('fournisseur_edit', ['id' => $importId]);
        $row = [
               'startDate' => $import->getStartDate() ? $import->getStartDate()->format('d/m/Y H:i') : '',
               'endDate' => $import->getEndDate() ? $import->getEndDate()->format('d/m/Y H:i') : '',
               'label' => $import->getLabel(),
               'newEntries' => $import->getNewEntries() ?? '',
               'updatedEntries' => $import->getUpdatedEntries(),
               'nbErrors' => $import->getNbErrors(),
               'status' => $import->getStatus() ? $import->getStatus()->getNom() : '',
               'user' => $import->getUser() ? $import->getUser()->getUsername() : '',
               'actions' => $this->templating->render('import/datatableImportRow.html.twig', [
                             'url' => $url,
                             'fournisseurId' => $importId
                    ]),
                    ];
        return $row;
    }

	/**
	 * @param PieceJointe $attachment
	 * @return array
	 */
    public function readFile($attachment)
	{
		$path = "../public/uploads/attachements/" . $attachment->getFileName();
		$file = fopen($path, "r");

		$headers = array_map('utf8_encode', fgetcsv($file, 1000, ";"));
		$firstRow = array_map('utf8_encode', fgetcsv($file, 1000, ";"));

		return ['headers' => $headers, 'firstRow' => $firstRow];
	}

	public function loadData($importId)
	{
		$import = $this->em->getRepository(Import::class)->find($importId);

		if ($import) {
			$csvFile = $import->getCsvFile();

			$path = "../public/uploads/attachements/" . $csvFile->getFileName();
			$file = fopen($path, "r");

			$columnsToFields = $import->getColumnToField();
			$corresp = array_flip($columnsToFields);

			// colonnes fournisseurs
			$colCodeReference = isset($corresp['codeReference']) ? $corresp['codeReference'] : null;
			$colNom = isset($corresp['nom']) ? $corresp['nom'] : null;
			// colonnes références
			$colLibelle = isset($corresp['libelle']) ? $corresp['libelle'] : null;
			$colQuantiteStock = isset($corresp['quantiteStock']) ? $corresp['quantiteStock'] : null;
			$colLimitSecurity = isset($corresp['limitSecurity']) ? $corresp['limitSecurity'] : null;
			$colLimitWarning = isset($corresp['limitWarning']) ? $corresp['limitWarning'] : null;
			$colTypeQuantite = isset($corresp['typeQuantite']) ? $corresp['typeQuantite'] : null;
			// colonnes articles
			$colLabel = isset($corresp['label']) ? $corresp['label'] : null;
			$colQuantite = isset($corresp['quantite']) ? $corresp['quantite'] : null;
			$colPrixUnitaire = isset($corresp['prixUnitaire']) ? $corresp['prixUnitaire'] : null;
			$colReference = isset($corresp['reference']) ? $corresp['reference'] : null;
			// colonnes champs libres
			$colChampsLibres = array_filter($corresp, function($elem) { return is_int($elem); }, ARRAY_FILTER_USE_KEY);
			// liens clés étrangères
			$colType = isset($corresp['type']) ? $corresp['type'] : null;
			$colArtFou = isset($corresp['référence article fournisseur']) ? $corresp['référence article fournisseur'] : null;
			$colRef = isset($corresp['référence article de référence']) ? $corresp['référence article de référence'] : null;
			$colFournisseur = isset($corresp['référence fournisseur']) ? $corresp['référence fournisseur'] : null;
			$colEmplacement = isset($corresp['emplacement']) ? $corresp['emplacement'] : null;
			$colCatInventaire = isset($corresp['catégorie d\'inventaire']) ? $corresp['catégorie d\'inventaire'] : null;

			$firstRow = true;

			while (($data = fgetcsv($file, 1000, ';')) !== false) {
				if ($firstRow) {
					$firstRow = false;
				} else {
					$row = array_map('utf8_encode', $data);
                    $data = [];
                    //TODO CG alimenter fichier log avec vérif champs obligatoires non vides
                    //TODO CG alimenter fichier log avec vérif champs (fixes et libres) au bon format
					switch ($import->getEntity()) {
						case Import::ENTITY_FOU:
						    if (!is_null($colCodeReference)) {
                                $data['code'] = $row[$colCodeReference];
                            }
						    if (!is_null($colNom)) {
                                $data['nom'] = $row[$colNom];
                            }

							$this->importFournisseurEntity($data);
							break;

						case Import::ENTITY_ART_FOU:
						    if (!is_null($colReference)) {
							    $data['reference'] = $row[$colReference];
                            }
						    if (!is_null($colLabel)) {
							    $data['label'] = $row[$colLabel];
                            }
						    if (!is_null($colRef)) {
							    $data['referenceReference'] = $row[$colRef];
                            }
						    if (!is_null($colFournisseur)) {
							    $data['fournisseurReference'] = $row[$colFournisseur];
                            }

							$this->importArticleFournisseurEntity($data);
							break;

						case Import::ENTITY_REF:
						    if (!is_null($colLibelle)) {
						        $data['libelle'] = $row[$colLibelle];
                            }
						    if (!is_null($colReference)) {
						        $data['reference'] = $row[$colReference];
                            }
						    if (!is_null($colQuantiteStock)) {
						        $data['quantiteStock'] = $row[$colQuantiteStock];
                            }
						    if (!is_null($colPrixUnitaire)) {
						        $data['prixUnitaire'] = $row[$colPrixUnitaire];
                            }
						    if (!is_null($colLimitSecurity)) {
						        $data['limitSecurity'] = $row[$colLimitSecurity];
                            }
						    if (!is_null($colLimitWarning)) {
						        $data['limitWarning'] = $row[$colLimitWarning];
                            }
						    if (!is_null($colTypeQuantite)) {
						        $data['typeQuantite'] = $row[$colTypeQuantite];
                            }
						    if (!is_null($colType)) {
						        $data['typeLabel'] = $row[$colType];
                            }
						    if (!is_null($colEmplacement)) {
						        $data['emplacement'] = $row[$colEmplacement];
                            }
						    if (!is_null($colCatInventaire)) {
						        $data['catInv'] = $row[$colCatInventaire];
                            }

							$this->importReferenceEntity($data, $colChampsLibres, $row);
							break;

						case Import::ENTITY_ART:
						    if (!is_null($colLabel)) {
							    $data['label'] = $row[$colLabel];
                            }
						    if (!is_null($colQuantite)) {
							    $data['quantite'] = $row[$colQuantite];
                            }
						    if (!is_null($colPrixUnitaire)) {
							    $data['prixUnitaire'] = $row[$colPrixUnitaire];
                            }
						    if (!is_null($colReference)) {
							    $data['reference'] = $row[$colReference];
                            }
                            if (!is_null($colType)) {
                                $data['typeLabel'] = $row[$colType];
                            }
                            if (!is_null($colArtFou)) {
                                $data['articleFournisseurReference'] = $row[$colArtFou];
                            }
                            if (!is_null($colArtFou)) {
                                $data['referenceReference'] = $row[$colRef];
                            }
                            if (!is_null($colFournisseur)) {
                                $data['fournisseurReference'] = $row[$colFournisseur];
                            }
                            if (!is_null($colEmplacement)) {
                                $data['emplacement'] = $row[$colEmplacement];
                            }

							$this->importArticleEntity($data, $colChampsLibres, $row);
							break;
					}
				}
			}
			$this->em->flush();
		}
	}

    /**
     * @param array $data
     */
    private function importFournisseurEntity(array $data): void
    {
        $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($data['code']);

        if (!$fournisseur) {
            $fournisseur = new Fournisseur();
            $this->em->persist($fournisseur);
        }
        if (isset($data['code'])) {
            $fournisseur->setCodeReference($data['code']);
        }
        if (isset($data['nom'])) {
            $fournisseur->setNom($data['nom']);
        }
    }

    /**
     * @param array $data
     * @throws NonUniqueResultException
     */
    private function importArticleFournisseurEntity(array $data): void
    {
        $articleFournisseur = $this->em->getRepository(ArticleFournisseur::class)->findOneBy(['reference' => $data['reference']]);

        if (!$articleFournisseur) {
            $articleFournisseur = new ArticleFournisseur();
            $this->em->persist($articleFournisseur);
        }

        if (isset($data['reference'])) {
            $articleFournisseur->setReference($data['reference']);
        }
        if (isset($data['label'])) {
            $articleFournisseur->setLabel($data['label']);
        }

        if (!empty($data['referenceReference'])) {
            $refArticle = $this->em->getRepository(ReferenceArticle::class)->findOneByReference($data['referenceReference']);

            if (empty($refArticle)) {
                // TODO CG alimente fichier log
            } else {
                $articleFournisseur->setReferenceArticle($refArticle);
            }
        }

        if (!empty($data['fournisseurReference'])) {
            $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($data['fournisseurReference']);

            if (empty($fournisseur)) {
                // TODO CG alimente fichier log
            } else {
                $articleFournisseur->setFournisseur($fournisseur);
            }
        }
    }

    /**
     * @param array $data
     * @param array $colChampsLibres
     * @param array $row
     * @throws NonUniqueResultException
     */
    private function importReferenceEntity(array $data, array $colChampsLibres, array $row): void
    {
        $newEntity = false;
        $refArt = $this->em->getRepository(ReferenceArticle::class)->findOneByReference($data['reference']);

        if (!$refArt) {
            $refArt = new ReferenceArticle();
            $this->em->persist($refArt);
            $newEntity = true;
        }

        if (isset($data['libelle'])) {
            $refArt->setLibelle($data['libelle']);
        }
        if (isset($data['reference'])) {
            $refArt->setReference($data['reference']);
        }
        if (isset($data['quantiteStock']) || $newEntity) {
            $refArt->setQuantiteStock($data['quantiteStock'] ?? 0);
        }
        if (isset($data['prixUnitaire'])) {
            $refArt->setPrixUnitaire($data['prixUnitaire']);
        }
        if (isset($data['limitSecurity'])) {
            $refArt->setLimitSecurity($data['limitSecurity']);
        }
        if (isset($data['limitWarning'])) {
            $refArt->setLimitWarning($data['limitWarning']);
        }
        if (isset($data['typeQuantite']) || $newEntity) {
            $refArt->setTypeQuantite($data['typeQuantite'] ?? ReferenceArticle::TYPE_QUANTITE_REFERENCE);
        }

        $refArt
            ->setStatut($this->em->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, ReferenceArticle::STATUT_ACTIF))
            ->setIsUrgent(false)
            ->setBarCode($this->refArticleDataService->generateBarCode());

        // liaison type
        $type = $this->em->getRepository(Type::class)->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, $data['typeLabel'] ?? Type::LABEL_STANDARD);
        if (empty($type)) {
            $categoryType = $this->em->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::ARTICLE]);
            $type = new Type();
            $type
                ->setLabel($data['typeLabel'])
                ->setCategory($categoryType);
            $this->em->persist($type);
            $this->em->flush();
        }
        $refArt->setType($type);

        // liaison emplacement
        if (!empty(['emplacement'])) {
            if (empty($data['emplacement'])) {
                $emplacement = null;
            } else {
                $emplacement = $this->em->getRepository(Emplacement::class)->findOneByLabel($data['emplacement']);
                if (empty($emplacement)) {
                    $emplacement = new Emplacement();
                    $emplacement
                        ->setLabel($data['emplacement'])
                        ->setIsActive(true)
                        ->setIsDeliveryPoint(false);
                    $this->em->persist($emplacement);
                    $this->em->flush();
                }
            }
            $refArt->setEmplacement($emplacement);
        }

        // liaison catégorie inventaire
        if (!empty($data['catInv'])) {
            $catInv = $this->em->getRepository(InventoryCategory::class)->findOneBy(['label' => $data['catInv']]);
            if (empty($catInv)) {
                // TODO CG alimente fichier log
            } else {
                $refArt->setCategory($catInv);
            }
        }

        // champs libres
        foreach ($colChampsLibres as $clId => $col) {
            $champLibre = $this->em->getRepository(ChampLibre::class)->find($clId);
            $valeurCL = new ValeurChampLibre();
            $valeurCL
                ->setChampLibre($champLibre)
                ->setValeur($row[$col])
                ->addArticleReference($refArt);
            $this->em->persist($valeurCL);
        }
    }

    /**
     * @param array $data
     * @param array $colChampsLibres
     * @param array $row
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    private function importArticleEntity(array $data, array $colChampsLibres, array $row): void
    {
        // liaison article fournisseur
        $refArticle = $this->em->getRepository(ReferenceArticle::class)->findOneByReference($data['referenceReference']);
        if (empty($refArticle)) {
            //TODO CG alimente fichier log
        } else if ($refArticle->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            //TODO CG alimente fichier log
        } else {
            $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($data['fournisseurReference']);
            if (empty($fournisseur)) {
                $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference(Fournisseur::REF_A_DEFINIR);
                if (empty($fournisseur)) {
                    $fournisseur = new Fournisseur();
                    $fournisseur
                        ->setNom(Fournisseur::REF_A_DEFINIR)
                        ->setCodeReference(Fournisseur::REF_A_DEFINIR);
                    $this->em->persist($fournisseur);
                    $this->em->flush();
                }
            }

            $articleFournisseur = $this->em->getRepository(ArticleFournisseur::class)->findOneBy([
                'reference' => $data['articleFournisseurReference'],
                'referenceArticle' => $refArticle,
                'fournisseur' => $fournisseur
            ]);

            if (empty($articleFournisseur)) {
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setLabel($refArticle->getLibelle() . ' / ' . $fournisseur->getNom())
                    ->setReferenceArticle($refArticle)
                    ->setFournisseur($fournisseur)
                    ->setReference($refArticle->getReference() . ' / ' . $fournisseur->getCodeReference());
                $this->em->persist($articleFournisseur);
                $this->em->flush();
            }

            $newEntity = false;
            $article = $this->em->getRepository(Article::class)->countByReference($data['reference']);
            if (!$article) {
                $article = new Article();
                $this->em->persist($article);
                $newEntity = true;
            }

            if (isset($data['label'])) {
                $article->setLabel($data['label']);
            }
            if (isset($data['quantite']) || $newEntity) {
                $article->setQuantite($data['quantite'] ?? 0);
            }
            if (isset($data['prixUnitaire'])) {
                $article->setPrixUnitaire($data['prixUnitaire']);
            }
            if (isset($data['reference'])) {
                $article->setReference($data['reference']);
            }

            $article
                ->setStatut($this->em->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF))
                ->setBarCode($this->articleDataService->generateBarCode())
                ->setArticleFournisseur($articleFournisseur)
                ->setConform(true);

            // liaison type
            $type = $this->em->getRepository(Type::class)->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, $data['typeLabel'] ?? Type::LABEL_STANDARD);
            if (empty($type)) {
                $type = new Type();
                $categoryType = $this->em->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::ARTICLE]);
                $type
                    ->setLabel($data['typeLabel'])
                    ->setCategory($categoryType);
                $this->em->persist($type);
                $this->em->flush();
            }
            $article->setType($type);

            // liaison emplacement
            if (!empty(['emplacement'])) {
                $emplacement = $this->em->getRepository(Emplacement::class)->findOneByLabel($data['emplacement']);
                if (empty($emplacement)) {
                    $emplacement = new Emplacement();
                    $emplacement
                        ->setLabel($data['emplacement'])
                        ->setIsActive(true)
                        ->setIsDeliveryPoint(false);
                    $this->em->persist($emplacement);
                    $this->em->flush();
                }
                $article->setEmplacement($emplacement);
            }

            // champs libres
            foreach ($colChampsLibres as $clId => $col) {
                $champLibre = $this->em->getRepository(ChampLibre::class)->find($clId);
                $valeurCL = new ValeurChampLibre();
                $valeurCL
                    ->setChampLibre($champLibre)
                    ->setValeur($row[$col])
                    ->addArticle($article);
                $this->em->persist($valeurCL);
            }
        }
    }
}


