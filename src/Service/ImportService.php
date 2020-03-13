<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\ChampLibre;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\PieceJointe;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\ValeurChampLibre;
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

			$firstRow = true;

			while (($data = fgetcsv($file, 1000, ';')) !== false) {
				if ($firstRow) {
					$firstRow = false;
				} else {
					$row = array_map('utf8_encode', $data);

					switch ($import->getEntity()) {
						case Import::ENTITY_FOU:
							$code = $colCodeReference ? $row[$colCodeReference] : '';
							$nom = $colNom ? $row[$colNom] : null;

							// test unicité code
							$codeAlreadyExist = $this->em->getRepository(Fournisseur::class)->countByCode($code);
							if ($codeAlreadyExist) {
								//TODO CG alimente fichier log
							} else {
								$fournisseur = new Fournisseur();
								$fournisseur
									->setCodeReference($code)
									->setNom($nom);
								$this->em->persist($fournisseur);
							}
							break;

						case Import::ENTITY_ART_FOU:
							$reference = $colReference ? $row[$colReference] : null;
							$label = $colReference ? $row[$colLabel] : null;

							// test unicité code
							$codeAlreadyExist = $this->em->getRepository(ArticleFournisseur::class)->countByReference($reference);
							if ($codeAlreadyExist) {
								//TODO CG alimente fichier log
							} else {
								$articleFournisseur = new ArticleFournisseur();
								$articleFournisseur
									->setReference($reference)
									->setLabel($label);
								$this->em->persist($articleFournisseur);
							}
							break;

						case Import::ENTITY_REF:
							$libelle = $colLibelle ? $row[$colLibelle] : '';
							$reference = $colReference ? $row[$colReference] : '';
							$quantiteStock = $colQuantiteStock ? $row[$colQuantiteStock] : 0;
							$prixUnitaire = $colPrixUnitaire ? $row[$colPrixUnitaire] : null;
							$limitSecurity = $colLimitSecurity ? $row[$colLimitSecurity] : null;
							$limitWarning = $colLimitWarning ? $row[$colLimitWarning] : null;
							$typeQuantite = $colTypeQuantite ? $row[$colTypeQuantite] : ReferenceArticle::TYPE_QUANTITE_REFERENCE;

							// test unicité référence
							$referenceAlreadyExist = $this->em->getRepository(ReferenceArticle::class)->countByReference($reference);
							if ($referenceAlreadyExist) {
								//TODO CG alimente fichier log
							} else {
								$refArt = new ReferenceArticle();
								$refArt
									->setLibelle($libelle)
									->setReference($reference)
									->setQuantiteStock($quantiteStock)
									->setStatut($this->em->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, ReferenceArticle::STATUT_ACTIF))
									->setPrixUnitaire($prixUnitaire)
									->setIsUrgent(false)
									->setLimitSecurity($limitSecurity)
									->setLimitWarning($limitWarning)
									->setTypeQuantite($typeQuantite)
									->setBarCode($this->refArticleDataService->generateBarCode());
								//TODO CG type
								$this->em->persist($refArt);

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
							break;

						case Import::ENTITY_ART:
							$label = $colLabel ? $row[$colLabel] : null;
							$quantite = $colQuantite ? $row[$colQuantite] : null;
							$prixUnitaire = $colPrixUnitaire ? $row[$colPrixUnitaire] : null;
							$reference = $colReference ? $row[$colReference] : null;

							// test unicité référence
							$referenceAlreadyExist = $this->em->getRepository(Article::class)->countByReference($reference);
							if ($referenceAlreadyExist) {
								//TODO CG alimente fichier log
							} else {
								$article = new Article();
								$article
									->setLabel($label)
									->setQuantite($quantite)
									->setPrixUnitaire($prixUnitaire)
									->setReference($reference)
									->setStatut($this->em->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF))
									//TODO CG type + article fournisseur (à créer si n'existe pas)
									->setBarCode($this->articleDataService->generateBarCode())
									->setConform(true);
								$this->em->persist($article);

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
							break;
					}
				}
			}
			$this->em->flush();
		}


	}
}


