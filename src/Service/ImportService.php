<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\InventoryCategory;
use App\Entity\MouvementStock;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;
use App\Exceptions\ImportException;
use Doctrine\ORM\EntityManager;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ImportService
{
    public const MAX_LINES_FLASH_IMPORT = 500;

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
    private $logger;

    public function __construct(RouterInterface $router,
                                LoggerInterface $logger,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                TokenStorageInterface $tokenStorage,
                                ArticleDataService $articleDataService,
                                RefArticleDataService $refArticleDataService,
                                MouvementStockService $mouvementStockService) {

        $this->templating = $templating;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->em = $em;
        $this->router = $router;
        $this->articleDataService = $articleDataService;
        $this->refArticleDataService = $refArticleDataService;
        $this->mouvementStockService = $mouvementStockService;
        $this->logger = $logger;
    }

    /**
     * @param null $params
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws NonUniqueResultException
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
     * @throws NonUniqueResultException
     */
    public function dataRowImport($import)
    {
        $statutRepository = $this->em->getRepository(Statut::class);

        $importId = $import->getId();
        $url['edit'] = $this->router->generate('fournisseur_edit', ['id' => $importId]);
        $status = $import->getStatus() ? $import->getStatus()->getNom() : '';

        return [
            'id' => $import->getId(),
            'startDate' => $import->getStartDate() ? $import->getStartDate()->format('d/m/Y H:i') : '',
            'endDate' => $import->getEndDate() ? $import->getEndDate()->format('d/m/Y H:i') : '',
            'label' => $import->getLabel(),
            'newEntries' => $import->getNewEntries(),
            'updatedEntries' => $import->getUpdatedEntries(),
            'nbErrors' => $import->getNbErrors(),
            'status' => '<span class="status-' . $status . ' cursor-default" data-id="' . $importId . '">' . $status . '</span>',
            'user' => $import->getUser() ? $import->getUser()->getUsername() : '',
            'actions' => $this->templating->render('import/datatableImportRow.html.twig', [
                'url' => $url,
                'importId' => $importId,
                'fournisseurId' => $importId,
                'canCancel' => $import->getStatus() == $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_PLANNED),
                'logFile' => $import->getLogFile() ? $import->getLogFile()->getFileName() : null
            ]),
        ];
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
     * @param Import $import
     * @param bool $force
     * @return bool
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    public function loadData(Import $import, $force = false): bool
    {
        $csvFile = $import->getCsvFile();

        $path = "../public/uploads/attachements/" . $csvFile->getFileName();
        $file = fopen($path, "r");

        $columnsToFields = $import->getColumnToField();
        $corresp = array_flip($columnsToFields);
        $colChampsLibres = array_filter($corresp, function ($elem) {return is_int($elem);}, ARRAY_FILTER_USE_KEY);

        $dataToCheck = $this->getDataToCheck($import->getEntity(), $corresp);

        $headers = null;
        $logRows = [];
        $refToUpdate = [];
        $stats = ['news' => 0, 'updates' => 0, 'errors' => 0];

        $rowCount = 0;
        $firstRows = [];

        while (($data = fgetcsv($file, 1000, ';')) !== false
               && $rowCount <= self::MAX_LINES_FLASH_IMPORT) {
            $row = array_map('utf8_encode', $data);

            if (empty($headers)) {
                $headers = $row;
                $logRows[] = array_merge($headers, ['Import']);
            }
            else {
                $firstRows[] = $row;
                $rowCount++;
            }
        }

        // le fichier fait moins de MAX_LINES_FLASH_IMPORT lignes
        $smallFile = ($rowCount <= self::MAX_LINES_FLASH_IMPORT);

        // si + de 500 ligne && !force -> planification
        if (!$smallFile && !$force) {
            $importDone = false;

            $statutRepository = $this->em->getRepository(Statut::class);
            $statusPlanned = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_PLANNED);
            $import->setStatus($statusPlanned);
            $this->em->flush();
        }
        else {
            $importDone = true;

            // les premières lignes <= MAX_LINES_FLASH_IMPORT
            foreach ($firstRows as $row) {
                $logRows[] = $this->treatImportRow($row, $import, $headers, $dataToCheck, $colChampsLibres, $refToUpdate, $stats);
            }

            if (!$smallFile) {
                // on fait la suite du fichier
                while (($data = fgetcsv($file, 1000, ';')) !== false) {
                    $row = array_map('utf8_encode', $data);
                    $logRows[] = $this->treatImportRow($row, $import, $headers, $dataToCheck, $colChampsLibres, $refToUpdate, $stats);
                }
            }

            // mise à jour des quantités sur références par article
            $uniqueRefToUpdate = array_unique($refToUpdate);
            foreach ($uniqueRefToUpdate as $ref) {
                $this->refArticleDataService->updateRefArticleQuantities($ref);
            }

            // flush update quantities
            $this->em->flush();

            // création du fichier de log
            $pieceJointeForLogFile = $this->persistLogFilePieceJointe($logRows);

            $statutRepository = $this->em->getRepository(Statut::class);
            $statusFinished = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED);

            // we reset local entities because entitymanager could be closed
            $import = $this->em->find(Import::class, $import->getId());
            $statusFinished = $this->em->find(Statut::class, $statusFinished->getId());

            $import
                ->setLogFile($pieceJointeForLogFile)
                ->setNewEntries($stats['news'])
                ->setUpdatedEntries($stats['updates'])
                ->setNbErrors($stats['errors'])
                ->setStatus($statusFinished)
                ->setEndDate(new DateTime('now'));
            $this->em->flush();
        }

        fclose($file);

        return $importDone;
    }

    /**
     * @param array $row
     * @param Import $import
     * @param array $headers
     * @param $dataToCheck
     * @param $colChampsLibres
     * @param array $refToUpdate
     * @param array $stats
     * @return array
     * @throws ORMException
     */
    private function treatImportRow(array $row,
                                    Import $import,
                                    array $headers,
                                    $dataToCheck,
                                    $colChampsLibres,
                                    array &$refToUpdate,
                                    array &$stats): array {
        $message = 'OK';
        try {
            $this->em->transactional(function () use ($import, $dataToCheck, $row, $headers, $colChampsLibres, $refToUpdate, &$stats) {
                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($dataToCheck, $row, $headers);

                switch ($import->getEntity()) {
                    case Import::ENTITY_FOU:
                        $this->importFournisseurEntity($verifiedData, $stats);
                        break;
                    case Import::ENTITY_ART_FOU:
                        $this->importArticleFournisseurEntity($verifiedData, $stats);
                        break;
                    case Import::ENTITY_REF:
                        $this->importReferenceEntity($import, $verifiedData, $colChampsLibres, $row, $stats);
                        break;
                    case Import::ENTITY_ART:
                        $refToUpdate[] = $this->importArticleEntity($import, $verifiedData, $colChampsLibres, $row, $stats);
                        break;
                }
            });
        }
        catch (Throwable $throwable) {
            // On réinitialise l'entity manager car il a été fermé
            if (!$this->em->isOpen()) {
                $this->em = EntityManager::Create($this->em->getConnection(), $this->em->getConfiguration());
            }

            if ($throwable instanceof ImportException) {
                $message = $throwable->getMessage();
            }
            else {
                $message = 'Une erreur est survenue.';
                $file = $throwable->getFile();
                $line = $throwable->getLine();
                $logMessage = $throwable->getMessage();
                $trace = $throwable->getTraceAsString();
                $importId = $import->getId();
                $this->logger->error("IMPORT ERROR : import n°$importId | $logMessage | File $file:$line | $trace");
            }

            $stats['errors']++;
        }

        return !empty($message)
            ? array_merge($row, [$message])
            : $row;
    }

    /**
     * @param string $entity
     * @param array $corresp
     * @return array
     */
    private function getDataToCheck($entity, $corresp)
    {
        switch ($entity) {
            case Import::ENTITY_FOU:
                $dataToCheck = [
                    'codeReference' => [
                        'needed' => $this->fieldIsNeeded('codeReference', Import::ENTITY_FOU),
                        'value' => isset($corresp['codeReference']) ? $corresp['codeReference'] : null
                    ],
                    'nom' => [
                        'needed' => $this->fieldIsNeeded('nom', Import::ENTITY_FOU),
                        'value' => isset($corresp['nom']) ? $corresp['nom'] : null
                    ],
                ];
                break;
            case Import::ENTITY_ART_FOU:
                $dataToCheck = [
                    'reference' => [
                        'needed' => $this->fieldIsNeeded('reference', Import::ENTITY_ART_FOU),
                        'value' => isset($corresp['reference']) ? $corresp['reference'] : null
                    ],
                    'label' => [
                        'needed' => $this->fieldIsNeeded('label', Import::ENTITY_ART_FOU),
                        'value' => isset($corresp['label']) ? $corresp['label'] : null
                    ],
                    'referenceReference' => [
                        'needed' => $this->fieldIsNeeded('referenceReference', Import::ENTITY_ART_FOU),
                        'value' => isset($corresp['référence article de référence']) ? $corresp['référence article de référence'] : null
                    ],
                    'fournisseurReference' => [
                        'needed' => $this->fieldIsNeeded('fournisseurReference', Import::ENTITY_ART_FOU),
                        'value' => isset($corresp['référence fournisseur']) ? $corresp['référence fournisseur'] : null
                    ],
                ];
                break;
            case Import::ENTITY_REF:
                $dataToCheck = [
                    'libelle' => [
                        'needed' => $this->fieldIsNeeded('libelle', Import::ENTITY_REF),
                        'value' => isset($corresp['libelle']) ? $corresp['libelle'] : null,
                    ],
                    'reference' => [
                        'needed' => $this->fieldIsNeeded('reference', Import::ENTITY_REF),
                        'value' => isset($corresp['reference']) ? $corresp['reference'] : null,
                    ],
                    'quantiteStock' => [
                        'needed' => $this->fieldIsNeeded('quantiteStock', Import::ENTITY_REF),
                        'value' => isset($corresp['quantiteStock']) ? $corresp['quantiteStock'] : null,
                    ],
                    'prixUnitaire' => [
                        'needed' => $this->fieldIsNeeded('prixUnitaire', Import::ENTITY_REF),
                        'value' => isset($corresp['prixUnitaire']) ? $corresp['prixUnitaire'] : null,
                    ],
                    'limitSecurity' => [
                        'needed' => $this->fieldIsNeeded('limitSecurity', Import::ENTITY_REF),
                        'value' => isset($corresp['limitSecurity']) ? $corresp['limitSecurity'] : null,
                    ],
                    'limitWarning' => [
                        'needed' => $this->fieldIsNeeded('limitWarning', Import::ENTITY_REF),
                        'value' => isset($corresp['limitWarning']) ? $corresp['limitWarning'] : null,
                    ],
                    'typeQuantite' => [
                        'needed' => $this->fieldIsNeeded('typeQuantite', Import::ENTITY_REF),
                        'value' => isset($corresp['typeQuantite']) ? $corresp['typeQuantite'] : null,
                    ],
                    'typeLabel' => [
                        'needed' => $this->fieldIsNeeded('typeLabel', Import::ENTITY_REF),
                        'value' => isset($corresp['type']) ? $corresp['type'] : null,
                    ],
                    'emplacement' => [
                        'needed' => $this->fieldIsNeeded('emplacement', Import::ENTITY_REF),
                        'value' => isset($corresp['emplacement']) ? $corresp['emplacement'] : null,
                    ],
                    'catInv' => [
                        'needed' => $this->fieldIsNeeded('catInv', Import::ENTITY_REF),
                        'value' => isset($corresp['catégorie d\'inventaire']) ? $corresp['catégorie d\'inventaire'] : null,
                    ],
                    'commentaire' => [
                        'needed' => $this->fieldIsNeeded('commentaire', Import::ENTITY_REF),
                        'value' => isset($corresp['commentaire']) ? $corresp['commentaire'] : null,
                    ],
                    'emergencyComment' => [
                        'needed' => $this->fieldIsNeeded('emergencyComment', Import::ENTITY_REF),
                        'value' => isset($corresp['emergencyComment']) ? $corresp['emergencyComment'] : null,
                    ],
                    'dateLastInventory' => [
                        'needed' => $this->fieldIsNeeded('dateLastInventory', Import::ENTITY_REF),
                        'value' => isset($corresp['dateLastInventory']) ? $corresp['dateLastInventory'] : null,
                    ],
                    'status' => [
                        'needed' => $this->fieldIsNeeded('status', Import::ENTITY_REF),
                        'value' => isset($corresp['status']) ? $corresp['status'] : null,
                    ],
                ];
                break;
            case Import::ENTITY_ART:
                $dataToCheck = [
                    'label' => [
                        'needed' => $this->fieldIsNeeded('catInv', Import::ENTITY_ART),
                        'value' => isset($corresp['label']) ? $corresp['label'] : null,
                    ],
                    'quantite' => [
                        'needed' => $this->fieldIsNeeded('quantite', Import::ENTITY_ART),
                        'value' => isset($corresp['quantite']) ? $corresp['quantite'] : null,
                    ],
                    'prixUnitaire' => [
                        'needed' => $this->fieldIsNeeded('prixUnitaire', Import::ENTITY_ART),
                        'value' => isset($corresp['prixUnitaire']) ? $corresp['prixUnitaire'] : null,
                    ],
                    'reference' => [
                        'needed' => $this->fieldIsNeeded('reference', Import::ENTITY_ART),
                        'value' => isset($corresp['reference']) ? $corresp['reference'] : null,
                    ],
                    'articleFournisseurReference' => [
                        'needed' => $this->fieldIsNeeded('articleFournisseurReference', Import::ENTITY_ART),
                        'value' => isset($corresp['référence article fournisseur']) ? $corresp['référence article fournisseur'] : null,
                    ],
                    'referenceReference' => [
                        'needed' => $this->fieldIsNeeded('referenceReference', Import::ENTITY_ART),
                        'value' => isset($corresp['référence article de référence']) ? $corresp['référence article de référence'] : null,
                    ],
                    'fournisseurReference' => [
                        'needed' => $this->fieldIsNeeded('fournisseurReference', Import::ENTITY_ART),
                        'value' => isset($corresp['référence fournisseur']) ? $corresp['référence fournisseur'] : null,
                    ],
                    'emplacement' => [
                        'needed' => $this->fieldIsNeeded('emplacement', Import::ENTITY_ART),
                        'value' => isset($corresp['emplacement']) ? $corresp['emplacement'] : null,
                    ],
                ];
                break;
            default:
                $dataToCheck = [];
        }

        return $dataToCheck;
    }

    /**
     * @param array $logRows
     * @return string
     * @throws NonUniqueResultException
     */
    private function buildErrorFile(array $logRows)
    {
        $fileName = uniqid() . '.csv';
        $logCsvFilePath = "../public/uploads/attachements/" . $fileName;
        $logCsvFilePathOpened = fopen($logCsvFilePath, 'w');
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
        $wantsUFT8 = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::USES_UTF8) ?? true;
        foreach ($logRows as $row) {
            if (!$wantsUFT8) {
                $row =  array_map('utf8_decode', $row);
            }
            fputcsv($logCsvFilePathOpened, $row, ';');
        }
        fclose($logCsvFilePathOpened);
        return $fileName;
    }

    /**
     * @param array $logRows
     * @return PieceJointe
     * @throws NonUniqueResultException
     */
    private function persistLogFilePieceJointe(array $logRows)
    {
        $createdLogFile = $this->buildErrorFile($logRows);

        $pieceJointeForLogFile = new PieceJointe();
        $pieceJointeForLogFile
            ->setOriginalName($createdLogFile)
            ->setFileName($createdLogFile);

        $this->em->persist($pieceJointeForLogFile);

        return $pieceJointeForLogFile;
    }

    /**
     * @param array $originalDatasToCheck
     * @param array $row
     * @param array $headers
     * @return array
     * @throws ImportException
     */
    private function checkFieldsAndFillArrayBeforeImporting(array $originalDatasToCheck, array $row, array $headers): array
    {
        $data = [];
        foreach ($originalDatasToCheck as $column => $originalDataToCheck) {
            $fieldName = Import::FIELDS_ENTITY[$column] ?? $column;

            if (is_null($originalDataToCheck['value']) && $originalDataToCheck['needed']) {
                $message = "La colonne $fieldName est manquante.";
                $this->throwError($message);
            }
            else if (empty($row[$originalDataToCheck['value']]) && $originalDataToCheck['needed']) {
                $columnIndex = $headers[$originalDataToCheck['value']];
                $message = "La valeur renseignée pour le champ $fieldName dans la colonne $columnIndex ne peut être vide.";
                $this->throwError($message);
            }
            else if (!is_null($originalDataToCheck['value']) && !empty($row[$originalDataToCheck['value']])) {
                $data[$column] = $row[$originalDataToCheck['value']];
            }
        }
        return $data;
    }

    /**
     * @param array $data
     * @param array $stats
     * @throws ImportException
     * @throws NonUniqueResultException
     */
    private function importFournisseurEntity(array $data, array &$stats): void
    {
        $newEntity = false;

        if (empty($data['codeReference'])) {
            $this->throwError("La colone de référence n'est pas valide.");
        }

        $fournisseurRepository = $this->em->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->findOneByCodeReference($data['codeReference']);

        if (empty($fournisseur)) {
            $fournisseur = new Fournisseur();
            $fournisseur->setCodeReference($data['codeReference']);

            $this->em->persist($fournisseur);
            $newEntity = true;
        }

        if (isset($data['nom'])) {
            $fournisseur->setNom($data['nom']);
        }

        $this->updateStats($stats, $newEntity);
    }

    /**
     * @param array $data
     * @param array $stats
     * @throws ImportException
     * @throws NonUniqueResultException
     */
    private function importArticleFournisseurEntity(array $data, array &$stats): void
    {
        $newEntity = false;

        if (empty($data['reference'])) {
            $this->throwError('La colonne référence ne doit pas être vide');
        }

        $articleFournisseurRepository = $this->em->getRepository(ArticleFournisseur::class);
        $articleFournisseur = $articleFournisseurRepository->findOneBy(['reference' => $data['reference']]);

        if (empty($articleFournisseur)) {
            $newEntity = true;
            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur->setReference($data['reference']);
        }

        if (isset($data['label'])) {
            $articleFournisseur->setLabel($data['label']);
        }

        if (!empty($data['referenceReference'])) {
            $refArticleRepository = $this->em->getRepository(ReferenceArticle::class);
            $refArticle = $refArticleRepository->findOneByReference($data['referenceReference']);
        }

        if (empty($refArticle)) {
            $this->throwError("La valeur renseignée pour la référence de l'article de référence ne correspond à aucune référence connue.");
        } else {
            $articleFournisseur->setReferenceArticle($refArticle);
        }

        if (!empty($data['fournisseurReference'])) {
            $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($data['fournisseurReference']);
        }

        if (empty($fournisseur)) {
            $this->throwError("La valeur renseignée pour le code du fournisseur ne correspond à aucun fournisseur connu.");
        }
        else {
            $articleFournisseur->setFournisseur($fournisseur);
        }

        $this->em->persist($articleFournisseur);
        $this->updateStats($stats, $newEntity);
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
     * @param array $stats
     * @throws ImportException
     * @throws NonUniqueResultException
     */
    private function importReferenceEntity(Import $import,
                                           array $data,
                                           array $colChampsLibres,
                                           array $row,
                                           array &$stats)
    {
        $isNewEntity = false;
        $refArtRepository = $this->em->getRepository(ReferenceArticle::class);
        $refArt = $refArtRepository->findOneByReference($data['reference']);

        if (!$refArt) {
            $refArt = new ReferenceArticle();
            $isNewEntity = true;
        }

        if (isset($data['libelle'])) {
            $refArt->setLibelle($data['libelle']);
        }
        if (isset($data['reference'])) {
            $refArt->setReference($data['reference']);
        }
        if (isset($data['commentaire'])) {
            $refArt->setCommentaire($data['commentaire']);
        }
        if (isset($data['emergencyComment'])) {
            $refArt->setEmergencyComment($data['emergencyComment']);
        }
        if (isset($data['dateLastInventory'])) {
            try {
                $refArt->setDateLastInventory(new DateTime($data['dateLastInventory']));
            } catch (Exception $e) {
                $message = 'La date de dernier inventaire n\'est pas dans un format date.';
                $this->throwError($message);
            };
        }
        if ($isNewEntity) {
            if (empty($data['typeQuantite'])
                || !in_array($data['typeQuantite'], [ReferenceArticle::TYPE_QUANTITE_REFERENCE, ReferenceArticle::TYPE_QUANTITE_ARTICLE])) {
                $this->throwError('Le type de gestion de la référence est invalide (autorisé : "article" ou "reference")');
            }

            // interdiction de modifier le type quantité d'une réf existante
            $refArt->setTypeQuantite($data['typeQuantite']);
        }
        if (isset($data['prixUnitaire'])) {
            if (!is_numeric($data['prixUnitaire'])) {
                $message = 'Le prix unitaire doit être un nombre.';
                $this->throwError($message);
            }
            $refArt->setPrixUnitaire($data['prixUnitaire']);
        }
        if (isset($data['limitSecurity'])) {
            if (!is_numeric($data['limitSecurity'])) {
                $message = 'Le seuil de sécurité doit être un nombre.';
                $this->throwError($message);
            }
            $refArt->setLimitSecurity($data['limitSecurity']);
        }
        if (isset($data['limitWarning'])) {
            if (!is_numeric($data['limitWarning'])) {
                $message = 'Le seuil d\'alerte doit être un nombre. ';
                $this->throwError($message);
            }
            $refArt->setLimitWarning($data['limitWarning']);
        }

        if ($isNewEntity) {
            $statusRepository = $this->em->getRepository(Statut::class);
            $status = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, ReferenceArticle::STATUT_ACTIF);
            $refArt
                ->setStatut($status)
                ->setIsUrgent(false)
                ->setBarCode($this->refArticleDataService->generateBarCode());
        }

        // liaison type
        $typeRepository = $this->em->getRepository(Type::class);

        $type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, $data['typeLabel'] ?? Type::LABEL_STANDARD);
        if (empty($type)) {
            $categoryType = $this->em->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::ARTICLE]);

            $type = new Type();
            $type
                ->setLabel($data['typeLabel'])
                ->setCategory($categoryType);
            $this->em->persist($type);
        }
        $refArt->setType($type);

        // liaison emplacement
        if ($refArt->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $this->checkAndCreateEmplacement($data, $refArt);
        }

        // liaison statut
        if (!empty($data['statut'])) {
            $status = $this->em->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, $data['statut']);
            if (empty($status)) {
                $message = "La valeur renseignée pour le statut ne correspond à aucun statut connu.";
                $this->throwError($message);
            } else {
                $refArt->setStatut($status);
            }
        }

        // liaison catégorie inventaire
        if (!empty($data['catInv'])) {
            $catInvRepository = $this->em->getRepository(InventoryCategory::class);
            $catInv = $catInvRepository->findOneBy(['label' => $data['catInv']]);
            if (empty($catInv)) {
                $message = "La valeur renseignée pour la catégorie d'inventaire ne correspond à aucune catégorie connue.";
                $this->throwError($message);
            } else {
                $refArt->setCategory($catInv);
            }
        }

        $this->em->persist($refArt);

        // quantité
        if (isset($data['quantiteStock'])) {
            if (!is_numeric($data['quantiteStock'])) {
                $message = 'La quantité doit être un nombre.';
                $this->throwError($message);
            } else if ($data['quantiteStock'] < 0) {
                $message = 'La quantité doit être positive.';
                $this->throwError($message);
            }
            else if ($refArt->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                if (isset($data['quantiteStock']) && $data['quantiteStock'] < $refArt->getQuantiteReservee()) {
                    $message = 'La quantité doit être supérieure à la quantité réservée (' . $refArt->getQuantiteReservee(). ').';
                    $this->throwError($message);
                }
                $this->checkAndCreateMvtStock($import, $refArt, $refArt->getQuantiteStock(), $data['quantiteStock'], $isNewEntity);
                $refArt->setQuantiteStock($data['quantiteStock']);
                $refArt->setQuantiteDisponible($refArt->getQuantiteStock() - $refArt->getQuantiteReservee());
            }
        }

        // champs libres
        $this->checkAndSetChampsLibres($colChampsLibres, $refArt, $isNewEntity, $row);

        $this->updateStats($stats, $isNewEntity);
    }

    /**
     * @param Import $import
     * @param array $data
     * @param array $colChampsLibres
     * @param array $row
     * @param array $stats
     * @return ReferenceArticle
     * @throws ImportException
     * @throws NonUniqueResultException
     */
    private function importArticleEntity(Import $import,
                                         array $data,
                                         array $colChampsLibres,
                                         array $row,
                                         array &$stats): ReferenceArticle
    {
        $refArticle = null;
        if (!empty($data['referenceReference'])) {
            $refArticleRepository = $this->em->getRepository(ReferenceArticle::class);
            $refArticle = $refArticleRepository->findOneByReference($data['referenceReference']);
            if (empty($refArticle)) {
                $message = "La valeur renseignée pour la référence de l'article de référence ne correspond à aucune référence connue.";
                $this->throwError($message);
            }
        }
        $isNewEntity = false;
        if (!empty($data['reference'])) {
            $articleRepository = $this->em->getRepository(Article::class);
            $article = $articleRepository->findOneByReference($data['reference']);
            if (!$article) {
                $this->throwError('La référence donnée est invalide.');
            }
            $article->setReference($data['reference']);
        } else {
            $article = new Article();
            $isNewEntity = true;
        }

        if (isset($data['label'])) {
            $article->setLabel($data['label']);
        }
        if (isset($data['quantite'])) {
            if (!is_numeric($data['quantite'])) {
                $this->throwError('La quantité doit être un nombre.');
            }
            $this->checkAndCreateMvtStock($import, $article, $article->getQuantite(), $data['quantite'], $isNewEntity);
            $article->setQuantite($data['quantite']);
        }

        if (isset($data['prixUnitaire'])) {
            if (!is_numeric($data['prixUnitaire'])) {
                $this->throwError('La quantité doit être un nombre.');
            }
            $article->setPrixUnitaire($data['prixUnitaire']);
        }

        if ($isNewEntity) {
            $statutRepository = $this->em->getRepository(Statut::class);
            $article
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF))
                ->setBarCode($this->articleDataService->generateBarCode())
                ->setConform(true);
        }

        $articleFournisseur = $this->checkAndCreateArticleFournisseur(
            $data['articleFournisseurReference'] ?? null,
            $data['fournisseurReference'] ?? null,
            $refArticle
        );

        $article->setArticleFournisseur($articleFournisseur);
        $article->setType($articleFournisseur->getReferenceArticle()->getType());

        // liaison emplacement
        $this->checkAndCreateEmplacement($data, $article);
        $this->em->persist($article);

        // champs libres
        $this->checkAndSetChampsLibres($colChampsLibres, $article, $isNewEntity, $row);

        $this->updateStats($stats, $isNewEntity);

        return $refArticle;
    }

    /**
     * @param array $colChampsLibres
     * @param ReferenceArticle|Article $refOrArt
     * @param bool $isNewEntity
     * @param array $row
     * @throws ImportException
     */
    private function checkAndSetChampsLibres($colChampsLibres, $refOrArt, $isNewEntity, $row)
    {
        $champLibreRepository = $this->em->getRepository(ChampLibre::class);
        $missingCL = [];

        $categoryCL = $refOrArt instanceof ReferenceArticle ? CategorieCL::REFERENCE_ARTICLE : CategorieCL::ARTICLE;
        $mandatoryCLs = $champLibreRepository->getMandatoryByTypeAndCategorieCLLabel($refOrArt->getType(), $categoryCL, $isNewEntity);
        $champsLibresId = array_keys($colChampsLibres);
        foreach ($mandatoryCLs as $cl) {
            if (!in_array($cl->getId(), $champsLibresId)) {
                $missingCL[] = $cl->getLabel();
            }
        }

        if (!empty($missingCL)) {
            $message = count($missingCL) > 1
                ? 'Les champs ' . implode($missingCL, ', ') . ' sont obligatoires'
                : 'Le champ ' . $missingCL[0] . ' est obligatoire';
            $message .= ' à la ' . ($isNewEntity ? 'création.' : 'modification.');
            $this->throwError($message);
        }
        foreach ($colChampsLibres as $clId => $col) {
            $champLibre = $champLibreRepository->find($clId);

            switch ($champLibre->getTypage()) {
                case ChampLibre::TYPE_BOOL:
                    $value = in_array($row[$col], ['Oui', 'oui', 1, '1']);
                    break;
                case ChampLibre::TYPE_DATE:
                case ChampLibre::TYPE_DATETIME:
                    try {
                        $date = DateTime::createFromFormat('d/m/Y', $row[$col]);
                        $value = $date->format('Y-m-d');
                    } catch (Exception $e) {
                        $message = 'La date du champ "' . $champLibre->getLabel() . '" n\'est pas au format jj/mm/AAAA.';
                        $this->throwError($message);
                        $value = null;
                    };
                    break;
                default:
                    $value = $row[$col];
            }

            $valeurCLRepository = $this->em->getRepository(ValeurChampLibre::class);

            if ($refOrArt instanceof ReferenceArticle) {
                $valeurCL = $valeurCLRepository->findOneByRefArticleAndChampLibre($refOrArt, $champLibre);
            } else if ($refOrArt instanceof  Article) {
                $valeurCL = $valeurCLRepository->findOneByArticleAndChampLibre($refOrArt, $champLibre);
            } else {
                $valeurCL = null;
            }

            if (empty($valeurCL)) {
                $valeurCL = new ValeurChampLibre();
                $valeurCL->setChampLibre($champLibre);
                $this->em->persist($valeurCL);
            }
            $valeurCL->setValeur($value);
            $refOrArt->addValeurChampLibre($valeurCL);
        }
    }

    /**
     * @param Import $import
     * @param ReferenceArticle|Article $refOrArt
     * @param int $formerQuantity
     * @param int $newQuantity
     * @param bool $isNewEntity
     * @throws Exception
     */
    private function checkAndCreateMvtStock(Import $import, $refOrArt, int $formerQuantity, int $newQuantity, bool $isNewEntity)
    {
        $diffQuantity = $isNewEntity ? $newQuantity : ($newQuantity - $formerQuantity);

        $mvtIn = $isNewEntity ? MouvementStock::TYPE_ENTREE : MouvementStock::TYPE_INVENTAIRE_ENTREE;
        if ($diffQuantity != 0) {
            $typeMvt = $diffQuantity > 0 ? $mvtIn : MouvementStock::TYPE_INVENTAIRE_SORTIE;

            $emplacement = $refOrArt->getEmplacement();
            $mvtStock = $this->mouvementStockService->createMouvementStock($this->user, $emplacement, abs($diffQuantity), $refOrArt, $typeMvt);
            $this->mouvementStockService->finishMouvementStock($mvtStock, new DateTime('now'), $emplacement);
            $import->addMouvement($mvtStock);
            $this->em->persist($mvtStock);
        }
    }

    /**
     * @param string $ref
     * @return Fournisseur
     * @throws NonUniqueResultException
     */
    private function checkAndCreateProvider($ref) {
        $fournisseurRepository = $this->em->getRepository(Fournisseur::class);
        $provider = $fournisseurRepository->findOneByCodeReference($ref);

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
        return in_array($field, Import::FIELDS_NEEDED[$entity]);
    }

    /**
     * @param array $data
     * @param Article|ReferenceArticle $articleOrRef
     * @throws ImportException
     * @throws NonUniqueResultException
     */
    private function checkAndCreateEmplacement(array $data,
                                               $articleOrRef): void {
        if (empty($data['emplacement'])) {
            $message = 'La valeur saisie pour l\'emplacement ne peut être vide.';
            $this->throwError($message);
        }
        else {
            $emplacementRepository = $this->em->getRepository(Emplacement::class);
            $location = $emplacementRepository->findOneByLabel($data['emplacement']);
            if (empty($location)) {
                $location = new Emplacement();
                $location
                    ->setLabel($data['emplacement'])
                    ->setIsActive(true)
                    ->setIsDeliveryPoint(false);

                $this->em->persist($location);
            }

            $articleOrRef->setEmplacement($location);
        }
    }

    /**
     * @param string|null $articleFournisseurReference
     * @param string|null $fournisseurReference
     * @param ReferenceArticle|null $referenceArticle
     * @return ArticleFournisseur|null
     * @throws ImportException
     * @throws NonUniqueResultException
     */
    private function checkAndCreateArticleFournisseur(?string $articleFournisseurReference,
                                                      ?string $fournisseurReference,
                                                      ?ReferenceArticle $referenceArticle): ?ArticleFournisseur {
        $articleFournisseurRepository = $this->em->getRepository(ArticleFournisseur::class);
        // liaison article fournisseur
        if (!empty($articleFournisseurReference)) {
            // on essaye de récupérer l'article fournisseur avec le champ donné
            $articleFournisseur = $articleFournisseurRepository->findOneBy(['reference' => $articleFournisseurReference]);

            // Si on a pas trouvé d'article fournisseur donc on le créé
            if (empty($articleFournisseur)) {
                if (empty($referenceArticle)) {
                    $this->throwError(
                        "Vous avez renseigné une référence d'article fournisseur qui ne correspond à aucun article fournisseur connu. " .
                        "Dans ce cas, veuillez fournir une référence d'article de référence connue."
                    );
                }
                $fournisseur = $this->checkAndCreateProvider(!empty($fournisseurReference) ? $fournisseurReference : Fournisseur::REF_A_DEFINIR);

                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setFournisseur($fournisseur)
                    ->setReference($articleFournisseurReference)
                    ->setReferenceArticle($referenceArticle)
                    ->setLabel($referenceArticle->getLibelle() . ' / ' . $fournisseur->getNom());
                $this->em->persist($articleFournisseur);

            }
            else {
                // on a réussi à trouver un article fournisseur
                // vérif que l'article fournisseur correspond au couple référence article / fournisseur
                if (!empty($fournisseurReference)) {
                    $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneByCodeReference($fournisseurReference);

                    if (!empty($fournisseur)) {
                        if ($articleFournisseur->getFournisseur()->getId() !== $fournisseur->getId()) {
                            $this->throwError("Veuillez renseigner une référence de fournisseur correspondant à celle de l'article fournisseur renseigné.");
                        }
                    } else {
                        $this->throwError("Veuillez renseigner une référence de fournisseur connue.");
                    }
                }

                if (!empty($referenceArticle)
                    && ($articleFournisseur->getReferenceArticle()->getId() !== $referenceArticle->getId())) {
                    $this->throwError("Veuillez renseigner une référence d'article fournisseur correspondant à la référence d'article fournie.");
                }
            }
        }
        // cas où la ref d'article fournisseur n'est pas renseigné
        else {
            if (empty($referenceArticle)) {
                $this->throwError("Vous n'avez pas renseigné de référence d'article fournisseur. Dans ce cas, veuillez fournir une référence d'article de référence connue.");
            }

            $fournisseur = $this->checkAndCreateProvider(!empty($fournisseurReference) ? $fournisseurReference : Fournisseur::REF_A_DEFINIR);
            $articleFournisseur = $articleFournisseurRepository->findOneBy([
                'referenceArticle' => $referenceArticle,
                'fournisseur' => $fournisseur
            ]);
            if (empty($articleFournisseur)) {
                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setFournisseur($fournisseur)
                    ->setReference($referenceArticle->getReference() . ' / ' . $fournisseur->getCodeReference())
                    ->setReferenceArticle($referenceArticle)
                    ->setLabel($referenceArticle->getLibelle() . ' / ' . $fournisseur->getNom());
                $this->em->persist($articleFournisseur);
            }
        }

        return $articleFournisseur;
    }

    /**
     * @param array $stats
     * @param boolean $newEntity
     */
    private function updateStats(&$stats, $newEntity) {
        if ($newEntity) {
            $stats['news']++;
        } else {
            $stats['updates']++;
        }
    }
}
