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
use App\Entity\MouvementStock;
use App\Entity\PieceJointe;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use App\Exceptions\ImportException;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
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
    private $mouvementStockService;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage,
                                ArticleDataService $articleDataService,
                                RefArticleDataService $refArticleDataService,
                                MouvementStockService $mouvementStockService
    )
    {
        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->router = $router;
        $this->articleDataService = $articleDataService;
        $this->refArticleDataService = $refArticleDataService;
        $this->mouvementStockService = $mouvementStockService;
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

    /**
     * @param $importId
     * @throws NonUniqueResultException
     */
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
            $colChampsLibres = array_filter($corresp, function ($elem) {
                return is_int($elem);
            }, ARRAY_FILTER_USE_KEY);
            // liens foreign keys
            $colType = isset($corresp['type']) ? $corresp['type'] : null;
            $colArtFou = isset($corresp['référence article fournisseur']) ? $corresp['référence article fournisseur'] : null;
            $colRef = isset($corresp['référence article de référence']) ? $corresp['référence article de référence'] : null;
            $colFournisseur = isset($corresp['référence fournisseur']) ? $corresp['référence fournisseur'] : null;
            $colEmplacement = isset($corresp['emplacement']) ? $corresp['emplacement'] : null;
            $colCatInventaire = isset($corresp['catégorie d\'inventaire']) ? $corresp['catégorie d\'inventaire'] : null;
            $dataToCheckForFou = [
                'codeReference' => [
                    'needed' => $this->fieldIsNeeded('codeReference', Import::ENTITY_FOU),
                    'value' => isset($corresp['codeReference']) ? $corresp['codeReference'] : null
                ],
                'nom' => [
                    'needed' => $this->fieldIsNeeded('nom', Import::ENTITY_FOU),
                    'value' => isset($corresp['nom']) ? $corresp['nom'] : null
                ],
            ];
            $dataToCheckForArtFou = [
                'reference' => [
                    'needed' => $this->fieldIsNeeded('reference', Import::ENTITY_ART_FOU),
                    'value' => $colReference
                ],
                'label' => [
                    'needed' => $this->fieldIsNeeded('label', Import::ENTITY_ART_FOU),
                    'value' => $colLabel
                ],
                'referenceReference' => [
                    'needed' => $this->fieldIsNeeded('referenceReference', Import::ENTITY_ART_FOU),
                    'value' => $colRef
                ],
                'fournisseurReference' => [
                    'needed' => $this->fieldIsNeeded('fournisseurReference', Import::ENTITY_ART_FOU),
                    'value' => $colFournisseur
                ],
            ];
            $dataToCheckForRef = [
                'libelle' => [
                    'needed' => $this->fieldIsNeeded('libelle', Import::ENTITY_REF),
                    'value' => $colLibelle,
                ],
                'reference' => [
                    'needed' => $this->fieldIsNeeded('reference', Import::ENTITY_REF),
                    'value' => $colReference,
                ],
                'quantiteStock' => [
                    'needed' => $this->fieldIsNeeded('quantiteStock', Import::ENTITY_REF),
                    'value' => $colQuantiteStock,
                ],
                'prixUnitaire' => [
                    'needed' => $this->fieldIsNeeded('prixUnitaire', Import::ENTITY_REF),
                    'value' => $colPrixUnitaire,
                ],
                'limitSecurity' => [
                    'needed' => $this->fieldIsNeeded('limitSecurity', Import::ENTITY_REF),
                    'value' => $colLimitSecurity,
                ],
                'limitWarning' => [
                    'needed' => $this->fieldIsNeeded('limitWarning', Import::ENTITY_REF),
                    'value' => $colLimitWarning,
                ],
                'typeQuantite' => [
                    'needed' => $this->fieldIsNeeded('typeQuantite', Import::ENTITY_REF),
                    'value' => $colTypeQuantite,
                ],
                'typeLabel' => [
                    'needed' => $this->fieldIsNeeded('typeLabel', Import::ENTITY_REF),
                    'value' => $colType,
                ],
                'emplacement' => [
                    'needed' => $this->fieldIsNeeded('emplacement', Import::ENTITY_REF),
                    'value' => $colEmplacement,
                ],
                'catInv' => [
                    'needed' => $this->fieldIsNeeded('catInv', Import::ENTITY_REF),
                    'value' => $colCatInventaire,
                ],
            ];
            $dataToCheckForArt = [
                'label' => [
                    'needed' => $this->fieldIsNeeded('catInv', Import::ENTITY_ART),
                    'value' => $colLabel,
                ],
                'quantite' => [
                    'needed' => $this->fieldIsNeeded('quantite', Import::ENTITY_ART),
                    'value' => $colQuantite,
                ],
                'prixUnitaire' => [
                    'needed' => $this->fieldIsNeeded('prixUnitaire', Import::ENTITY_ART),
                    'value' => $colPrixUnitaire,
                ],
                'reference' => [
                    'needed' => $this->fieldIsNeeded('reference', Import::ENTITY_ART),
                    'value' => $colReference,
                ],
                'articleFournisseurReference' => [
                    'needed' => $this->fieldIsNeeded('articleFournisseurReference', Import::ENTITY_ART),
                    'value' => $colArtFou,
                ],
                'referenceReference' => [
                    'needed' => $this->fieldIsNeeded('referenceReference', Import::ENTITY_ART),
                    'value' => $colRef,
                ],
                'fournisseurReference' => [
                    'needed' => $this->fieldIsNeeded('fournisseurReference', Import::ENTITY_ART),
                    'value' => $colFournisseur,
                ],
                'emplacement' => [
                    'needed' => $this->fieldIsNeeded('emplacement', Import::ENTITY_ART),
                    'value' => $colEmplacement,
                ],
            ];
            $firstRow = true;
            $headers = [];
            $rowIndex = 0;
            $csvErrors = [];
            while (($data = fgetcsv($file, 1000, ';')) !== false) {
                $rowIndex++;
                $row = array_map('utf8_encode', $data);
                try {
                    if ($firstRow) {
                        $firstRow = false;
                        $headers = $row;
                        $csvErrors[] = array_merge($headers, ['Erreur']);
                    } else {
                        switch ($import->getEntity()) {
                            case Import::ENTITY_FOU:
                                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($dataToCheckForFou, $row, $headers, $rowIndex);
                                $this->importFournisseurEntity($verifiedData);
                                break;
                            case Import::ENTITY_ART_FOU:
                                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($dataToCheckForArtFou, $row, $headers, $rowIndex);
                                $this->importArticleFournisseurEntity($verifiedData, $rowIndex);
                                break;
                            case Import::ENTITY_REF:
                                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($dataToCheckForRef, $row, $headers, $rowIndex);
                                $this->importReferenceEntity($verifiedData, $colChampsLibres, $row, $rowIndex);
                                break;
                            case Import::ENTITY_ART:
                                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($dataToCheckForArt, $row, $headers, $rowIndex);
                                $this->importArticleEntity($verifiedData, $colChampsLibres, $row, $rowIndex);
                                break;
                        }
                    }
                } catch (ImportException $exception) {
                    $csvErrors[] = array_merge($row, [$exception->getMessage()]);
                }
            }
            $createdLogFile = $this->buildErrorFile($csvErrors);
            $pieceJointeForLogFile = new PieceJointe();
            $pieceJointeForLogFile
                ->setOriginalName($createdLogFile)
                ->setFileName($createdLogFile)
                ->setImportLog($import);
            $this->em->persist($pieceJointeForLogFile);
            $this->em->flush();
        }
    }

    private function buildErrorFile(array $csvErrors)
    {
        $logCsvFilePath = "../public/uploads/attachements/" . uniqid() . '.csv';
        $logCsvFilePathOpened = fopen($logCsvFilePath, 'w');
        foreach ($csvErrors as $csvError) {
            fputcsv($logCsvFilePathOpened, $csvError, ';');
        }
        fclose($logCsvFilePathOpened);
        return $logCsvFilePath;
    }

    /**
     * @param array $originalDatasToCheck
     * @param array $row
     * @param array $headers
     * @param int $rowIndex
     * @return array
     * @throws ImportException
     */
    private function checkFieldsAndFillArrayBeforeImporting(array $originalDatasToCheck, array $row, array $headers, int $rowIndex): array
    {
        $data = [];
        foreach ($originalDatasToCheck as $column => $originalDataToCheck) {
            if (is_null($originalDataToCheck['value']) && $originalDataToCheck['needed']) {
                $message = 'La colonne ' . $column
                    . ' est manquante. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            } else if (empty($row[$originalDataToCheck['value']]) && $originalDataToCheck['needed']) {
                $message =
                    'La valeure renseignée pour le champ ' . $column . ' dans la colonne '
                    . $headers[$originalDataToCheck['value']]
                    . ' ne peut être vide. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            } else if (!is_null($originalDataToCheck['value']) && !empty($row[$originalDataToCheck['value']])) {
                $data[$column] = $row[$originalDataToCheck['value']];
            }
        }
        return $data;
    }

    /**
     * @param array $data
     * @throws NonUniqueResultException
     */
    private function importFournisseurEntity(array $data): void
    {
        $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($data['codeReference']);
        if (!$fournisseur) {
            $fournisseur = new Fournisseur();
            $this->em->persist($fournisseur);
        }
        if (isset($data['codeReference'])) {
            $fournisseur->setCodeReference($data['codeReference']);
        }
        if (isset($data['nom'])) {
            $fournisseur->setNom($data['nom']);
        }
        $this->em->flush();
    }

    /**
     * @param array $data
     * @param int $rowIndex
     * @throws NonUniqueResultException
     * @throws ImportException
     */
    private function importArticleFournisseurEntity(array $data, int $rowIndex): void
    {
        if (isset($data['reference'])) {
            $articleFournisseur = $this->em->getRepository(ArticleFournisseur::class)->findOneBy(['reference' => $data['reference']]);
            if (!$articleFournisseur) {
                $articleFournisseur = new ArticleFournisseur();
            }
            $articleFournisseur->setReference($data['reference']);
        } else {
            $articleFournisseur = new ArticleFournisseur();
        }
        if (isset($data['label'])) {
            $articleFournisseur->setLabel($data['label']);
        }

        if (!empty($data['referenceReference'])) {
            $refArticle = $this->em->getRepository(ReferenceArticle::class)->findOneByReference($data['referenceReference']);

            if (empty($refArticle)) {
                $message = "La valeure renseignée pour la référence de
                l'article de référence ne correspond à aucune référence connue.
                Erreur à la ligne " . $rowIndex;
                $this->throwError($message);
            } else {
                $articleFournisseur->setReferenceArticle($refArticle);
            }
        }

        if (!empty($data['fournisseurReference'])) {
            $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($data['fournisseurReference']);

            if (empty($fournisseur)) {
                $message = "La valeure renseignée pour le code de référence
                du fournisseur ne correspond à aucun fournisseur connu.
                Erreur à la ligne " . $rowIndex;
                $this->throwError($message);
            } else {
                $articleFournisseur->setFournisseur($fournisseur);
            }
        }
        $this->em->persist($articleFournisseur);
        $this->em->flush();
    }

    /**
     * @param $message
     * @throws ImportException
     */
    public function throwError($message)
    {
        throw new ImportException($message);
    }

    /**
     * @param array $data
     * @param array $colChampsLibres
     * @param array $row
     * @param int $rowIndex
     * @throws NonUniqueResultException
     * @throws ImportException
     * @throws Exception
     */
    private function importReferenceEntity(array $data, array $colChampsLibres, array $row, int $rowIndex): void
    {
        $newEntity = false;
        $refArt = $this->em->getRepository(ReferenceArticle::class)->findOneByReference($data['reference']);

        if (!$refArt) {
            $refArt = new ReferenceArticle();
            $newEntity = true;
        }

        if (isset($data['libelle'])) {
            $refArt->setLibelle($data['libelle']);
        }
        if (isset($data['reference'])) {
            $refArt->setReference($data['reference']);
        }
        if (isset($data['typeQuantite']) || $newEntity) {
            $refArt->setTypeQuantite($data['typeQuantite'] ?? ReferenceArticle::TYPE_QUANTITE_REFERENCE);
        }
        if (isset($data['prixUnitaire'])) {
            if (!is_numeric($data['prixUnitaire'])) {
                $message = 'Le prix unitaire ne peux être autre chose qu\'un nombre. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            }
            $refArt->setPrixUnitaire($data['prixUnitaire']);
        }
        if (isset($data['limitSecurity'])) {
            if (!is_numeric($data['limitSecurity'])) {
                $message = 'La limite de sécurité ne peux être autre chose qu\'un nombre. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            }
            $refArt->setLimitSecurity($data['limitSecurity']);
        }
        if (isset($data['limitWarning'])) {
            if (!is_numeric($data['limitWarning'])) {
                $message = 'La limite de d\'alerte ne peux être autre chose qu\'un nombre. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            }
            $refArt->setLimitWarning($data['limitWarning']);
        }

        if ($newEntity) {
            $refArt
                ->setStatut($this->em->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, ReferenceArticle::STATUT_ACTIF))
                ->setIsUrgent(false)
                ->setBarCode($this->refArticleDataService->generateBarCode());
        }

        // liaison type
        $type = $this->em->getRepository(Type::class)->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, $data['typeLabel'] ?? Type::LABEL_STANDARD);
        if (empty($type)) {
            $categoryType = $this->em->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::ARTICLE]);
            $type = new Type();
            $type
                ->setLabel($data['typeLabel'])
                ->setCategory($categoryType);
            $this->em->persist($type);
        }
        $refArt->setType($type);
        $this->em->flush();
        // liaison emplacement
        $this->checkEmplacement($data, $rowIndex, $refArt);
        $this->em->flush();

        // liaison catégorie inventaire
        if (!empty($data['catInv'])) {
            $catInv = $this->em->getRepository(InventoryCategory::class)->findOneBy(['label' => $data['catInv']]);
            if (empty($catInv)) {
                $message = "La valeure renseignée pour la catégorie d'inventaire ne correspond
                à aucune catégorie connue.
                Erreur à la ligne " . $rowIndex;
                $this->throwError($message);
            } else {
                $refArt->setCategory($catInv);
            }
        }
        $this->em->persist($refArt);
        if (isset($data['quantiteStock']) || $newEntity) {
            if (!is_numeric($data['quantiteStock'])) {
                $message = 'La quantité ne peux être autre chose qu\'un nombre. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            }
            if ($refArt->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                if (!$newEntity) {
                    $this->checkAndCreateMvtStock($refArt, $data['quantiteStock']);
                }
                $refArt->setQuantiteStock($data['quantiteStock'] ?? 0);
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
        $this->em->flush();
    }

    /**
     * @param array $data
     * @param array $colChampsLibres
     * @param array $row
     * @param int $rowIndex
     * @throws NonUniqueResultException
     * @throws ImportException
     */
    private function importArticleEntity(array $data, array $colChampsLibres, array $row, int $rowIndex): void
    {
        $refArticle = null;
        if (!empty($data['referenceReference'])) {
            $refArticle = $this->em->getRepository(ReferenceArticle::class)->findOneByReference($data['referenceReference']);
            if (empty($refArticle)) {
                $message = "La valeure renseignée pour la référence de
                                    l'article de référence ne correspond à aucune référence connue.
                                    Erreur à la ligne " . $rowIndex;
                $this->throwError($message);
            }
        }
        $newEntity = false;
        if (isset($data['reference'])) {
            $article = $this->em->getRepository(Article::class)->findOneByReference($data['reference']);
            if (!$article) {
                $article = new Article();
                $newEntity = true;
            }
            $article->setReference($data['reference']);
        } else {
            $article = new Article();
            $newEntity = true;
        }


        if (isset($data['label'])) {
            $article->setLabel($data['label']);
        }
        if (isset($data['quantite']) || $newEntity) {
            if (!is_numeric($data['quantite'])) {
                $message = 'La quantité ne peux être autre chose qu\'un nombre. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            }
            $article->setQuantite($data['quantite'] ?? 0);
        }
        if (isset($data['prixUnitaire'])) {
            if (!is_numeric($data['prixUnitaire'])) {
                $message = 'La quantité ne peux être autre chose qu\'un nombre. '
                    . 'L\'erreur est survenue à la ligne ' . $rowIndex;
                $this->throwError($message);
            }
            $article->setPrixUnitaire($data['prixUnitaire']);
        }

        if ($newEntity) {
            $article
                ->setStatut($this->em->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF))
                ->setBarCode($this->articleDataService->generateBarCode())
                ->setConform(true);
        }

        // liaison article fournisseur
        if (!empty($data['articleFournisseurReference'])) {
            $articleFournisseur = $this->em->getRepository(ArticleFournisseur::class)->findOneBy(['reference' => $data['articleFournisseurReference']]);

            if (empty($articleFournisseur)) {
                if (!$refArticle) {
                    $message = "Vous avez renseignez une référence d'article fournisseur qui ne correspond à aucun article fournisseur connu.
                    Dans ce cas, veuillez fournir une référence d'article de référence connue.
                                    Erreur à la ligne " . $rowIndex;
                    $this->throwError($message);
                }
                $articleFournisseur = new ArticleFournisseur();
                $this->em->persist($articleFournisseur);

                if (!empty($data['fournisseurReference'])) {
                    $fournisseur = $this->checkAndCreateProvider($data['fournisseurReference']);
                } else {
                    $fournisseur = $this->checkAndCreateProvider(Fournisseur::REF_A_DEFINIR);
                }

                $articleFournisseur
                    ->setFournisseur($fournisseur)
                    ->setReference($data['articleFournisseurReference'])
                    ->setReferenceArticle($refArticle)
                    ->setLabel($refArticle->getLibelle() . ' / ' . $fournisseur->getNom());

            } else {
                // vérif que l'article fournisseur correspond au couple référence article / fournisseur
                if (!empty($data['fournisseurReference'])) {
                    $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($data['fournisseurReference']);
                    if (!empty($fournisseur)) {
                        if ($articleFournisseur->getFournisseur()->getId() !== $fournisseur->getId()) {
                            $message = "Veuillez renseigner une référence de fournisseur correspondant à celle de l'article fournisseur renseigné.
                                    Erreur à la ligne " . $rowIndex;
                            $this->throwError($message);
                        }
                    } else {
                        $message = "Veuillez renseigner une référence de fournisseur connue.
                                    Erreur à la ligne " . $rowIndex;
                        $this->throwError($message);
                    }
                }
                if ($refArticle && $articleFournisseur->getReferenceArticle()->getId() !== $refArticle->getId()) {
                    $message = "Veuillez renseigner une référence d'article fournisseur correspondant à la référence d'article fournie.
                                    Erreur à la ligne " . $rowIndex;
                    $this->throwError($message);
                }
            }

            // cas où l'article fournisseur n'est pas renseigné
        } else {
            if (!$refArticle) {
                $message = "Vous n'avez pas renseigner de référence d'article fournisseur.
                    Dans ce cas, veuillez fournir une référence d'article de référence connue.
                                    Erreur à la ligne " . $rowIndex;
                $this->throwError($message);
            }
            if (!empty($data['fournisseurReference'])) {
                $fournisseur = $this->checkAndCreateProvider($data['fournisseurReference']);
            } else {
                $fournisseur = $this->checkAndCreateProvider(Fournisseur::REF_A_DEFINIR);
            }

            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setFournisseur($fournisseur)
                ->setReference($refArticle->getReference() . ' / ' . $fournisseur->getCodeReference())
                ->setReferenceArticle($refArticle)
                ->setLabel($refArticle->getLibelle() . ' / ' . $fournisseur->getNom());
            $this->em->persist($articleFournisseur);
        }
        $article->setArticleFournisseur($articleFournisseur);
        $article->setType($articleFournisseur->getReferenceArticle()->getType());

        // liaison emplacement
        $this->checkEmplacement($data, $rowIndex, $article);
        $this->em->flush();
        $this->em->persist($article);
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
        $this->em->flush();
    }

    /**
     * @param int $newQuantity
     * @param ReferenceArticle $refArt
     */
    private function checkAndCreateMvtStock(ReferenceArticle $refArt, int $newQuantity)
    {
        $diffQuantity = $newQuantity - $refArt->getQuantiteStock();
        if ($diffQuantity != 0) {
            $typeMvt = $diffQuantity > 0 ? MouvementStock::TYPE_INVENTAIRE_ENTREE : MouvementStock::TYPE_INVENTAIRE_SORTIE;
            $mvtStock = $this->mouvementStockService->createMouvementStock($this->user, null, abs($diffQuantity), $refArt, $typeMvt);
            $this->em->persist($mvtStock);
        }
    }

    /**
     * @param string $ref
     * @return Fournisseur
     * @throws NonUniqueResultException
     */
    private function checkAndCreateProvider($ref)
    {
        $provider = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($ref);
        if (empty($provider)) {
            $provider = new Fournisseur();
            $provider
                ->setCodeReference($ref)
                ->setNom($ref);
            $this->em->persist($provider);
        }

        return $provider;
    }

    private function fieldIsNeeded(string $field, string $entity): bool
    {
        return (
            in_array(Import::FIELDS_ENTITY[$field], Import::FIELDS_NEEDED[$entity]) ||
            in_array($field, Import::FIELDS_NEEDED[Import::ENTITY_FOU])
        );
    }

    /**
     * @param array $data
     * @param int $rowIndex
     * @param Article|ReferenceArticle $articleOrRef
     * @throws ImportException
     * @throws NonUniqueResultException
     */
    private function checkEmplacement(array $data, int $rowIndex, $articleOrRef): void
    {
        if (empty($data['emplacement'])) {
            $message = 'La valeure saisie pour emplacement ne peut être vide. '
                . 'L\'erreur est survenue à la ligne ' . $rowIndex;
            $this->throwError($message);
        } else {
            $emplacement = $this->em->getRepository(Emplacement::class)->findOneByLabel($data['emplacement']);
            if (empty($emplacement)) {
                $emplacement = new Emplacement();
                $emplacement
                    ->setLabel($data['emplacement'])
                    ->setIsActive(true)
                    ->setIsDeliveryPoint(false);
                $this->em->persist($emplacement);
            }
            $articleOrRef->setEmplacement($emplacement);
        }
    }


}


