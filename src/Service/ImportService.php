<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\InventoryCategory;
use App\Entity\MouvementStock;
use App\Entity\ParametrageGlobal;
use App\Entity\Attachment;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\ImportException;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class ImportService
{
    public const MAX_LINES_FLASH_IMPORT = 100;
    public const MAX_LINES_AUTO_FORCED_IMPORT = 500;

    public const IMPORT_MODE_RUN = 1; // réaliser l'import maintenant
    public const IMPORT_MODE_FORCE_PLAN = 2; // réaliser l'import rapidement (dans le cron qui s'exécute toutes les 30min)
    public const IMPORT_MODE_PLAN = 3; // réaliser l'import dans la nuit (dans le cron à 23h59)
    public const IMPORT_MODE_NONE = 4; // rien n'a été réalisé sur l'import

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    private $em;
    private $articleDataService;
    private $refArticleDataService;
    private $mouvementStockService;
    private $logger;
    private $attachmentService;
    private $articleFournisseurService;

    /** @var Import */
    private $currentImport;

    public function __construct(RouterInterface $router,
                                LoggerInterface $logger,
                                AttachmentService $attachmentService,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ArticleDataService $articleDataService,
                                RefArticleDataService $refArticleDataService,
                                ArticleFournisseurService $articleFournisseurService,
                                MouvementStockService $mouvementStockService)
    {

        $this->templating = $templating;
        $this->em = $em;
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->router = $router;
        $this->articleDataService = $articleDataService;
        $this->refArticleDataService = $refArticleDataService;
        $this->mouvementStockService = $mouvementStockService;
        $this->logger = $logger;
        $this->attachmentService = $attachmentService;
        $this->articleFournisseurService = $articleFournisseurService;
    }

    /**
     * @param null $params
     * @param Utilisateur $user
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getDataForDatatable(Utilisateur $user, $params = null)
    {
        $importRepository = $this->em->getRepository(Import::class);
        $filtreSupRepository = $this->em->getRepository(FiltreSup::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_IMPORT, $user);

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

        $importStatus = $import->getStatus();
        $statusLabel = isset($importStatus) ? $importStatus->getNom() : null;
        $statusTitle = (!empty($statusLabel) && ($statusLabel === Import::STATUS_PLANNED))
            ? ($import->isForced() ? 'L\'import sera réalisé dans moins de 30 min' : 'L\'import sera réalisé la nuit suivante')
            : '';

        $statusClass = "user-select-none status-$importStatus cursor-default";
        if (!empty($statusTitle)) {
            $statusClass .= ' has-tooltip';
        }

        return [
            'id' => $import->getId(),
            'startDate' => $import->getStartDate() ? $import->getStartDate()->format('d/m/Y H:i') : '',
            'endDate' => $import->getEndDate() ? $import->getEndDate()->format('d/m/Y H:i') : '',
            'label' => $import->getLabel(),
            'newEntries' => $import->getNewEntries(),
            'updatedEntries' => $import->getUpdatedEntries(),
            'nbErrors' => $import->getNbErrors(),
            'status' => '<span class="' . $statusClass . '" data-id="' . $importId . '" title="' . $statusTitle . '">' . $statusLabel . '</span>',
            'user' => $import->getUser() ? $import->getUser()->getUsername() : '',
            'actions' => $this->templating->render('import/datatableImportRow.html.twig', [
                'url' => $url,
                'importId' => $importId,
                'fournisseurId' => $importId,
                'canCancel' => ($statusLabel === Import::STATUS_PLANNED),
                'logFile' => $import->getLogFile() ? $import->getLogFile()->getFileName() : null
            ]),
        ];
    }

    /**
     * @param Attachment $attachment
     * @return array
     */
    public function getImportConfig(Attachment $attachment)
    {
        $path = $this->attachmentService->getServerPath($attachment);

        $file = fopen($path, "r");

        $headers = fgetcsv($file, 0, ";");
        $firstRow = fgetcsv($file, 0, ";");

        if ($headers && $firstRow) {
            $csvContent = file_get_contents($path);
            $res = [
                'headers' => $headers,
                'firstRow' => $firstRow,
                'isUtf8' => mb_check_encoding($csvContent, 'UTF-8')
            ];
        } else {
            $res = null;
        }

        fclose($file);

        return $res;
    }

    /**
     * @param Import $import
     * @param int $mode IMPORT_MODE_RUN ou IMPORT_MODE_FORCE_PLAN ou IMPORT_MODE_PLAN
     * @return int Used mode
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     * @throws Exception
     */
    public function treatImport(Import $import, int $mode = self::IMPORT_MODE_PLAN): int
    {
        $this->currentImport = $import;

        $csvFile = $this->currentImport->getCsvFile();

        // we check mode validity
        if (!in_array($mode, [self::IMPORT_MODE_RUN, self::IMPORT_MODE_FORCE_PLAN, self::IMPORT_MODE_PLAN])) {
            throw new Exception('Invalid import mode');
        }

        $path = $this->attachmentService->getServerPath($csvFile);
        $file = fopen($path, "r");

        $columnsToFields = $this->currentImport->getColumnToField();
        $corresp = array_flip($columnsToFields);
        $colChampsLibres = array_filter($corresp, function ($elem) {
            return is_int($elem);
        }, ARRAY_FILTER_USE_KEY);

        $dataToCheck = $this->getDataToCheck($this->currentImport->getEntity(), $corresp);

        $headers = null;
        $logRows = [];
        $refToUpdate = [];
        $stats = [
            'news' => 0,
            'updates' => 0,
            'errors' => 0
        ];

        $rowCount = 0;
        $firstRows = [];

        while (($row = fgetcsv($file, 0, ';')) !== false
            && $rowCount <= self::MAX_LINES_AUTO_FORCED_IMPORT) {

            if (empty($headers)) {
                $headers = $row;
                $logRows[] = array_merge($headers, ['Statut import']);
            } else {
                $firstRows[] = $row;
                $rowCount++;
            }
        }

        // le fichier fait moins de MAX_LINES_FLASH_IMPORT lignes
        $smallFile = ($rowCount <= self::MAX_LINES_FLASH_IMPORT);

        // si + de MAX_LINES_FLASH_IMPORT lignes
        // ET que c'est pas un import planifié
        if (!$smallFile
            && ($mode !== self::IMPORT_MODE_RUN)) {
            if (!$this->currentImport->isFlash() && !$this->currentImport->isForced()) {
                $importForced = (
                    ($rowCount <= self::MAX_LINES_AUTO_FORCED_IMPORT)
                    || ($mode === self::IMPORT_MODE_FORCE_PLAN)
                );
                $importModeChoosen = $importForced ? self::IMPORT_MODE_FORCE_PLAN : self::IMPORT_MODE_RUN;
                $this->currentImport->setForced($importForced);

                $statutRepository = $this->em->getRepository(Statut::class);
                $statusPlanned = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_PLANNED);
                $this->currentImport->setStatus($statusPlanned);
                $this->em->flush();
            } else {
                $importModeChoosen = self::IMPORT_MODE_NONE;
            }
        } else {
            $importModeChoosen = self::IMPORT_MODE_RUN;
            if ($smallFile) {
                $this->currentImport->setFlash(true);
            }

            // les premières lignes <= MAX_LINES_AUTO_FORCED_IMPORT
            $index = 0;
            foreach ($firstRows as $row) {
                $logRows[] = $this->treatImportRow($row, $headers, $dataToCheck, $colChampsLibres, $refToUpdate, $stats, false, $index);
                $index++;
            }
            $this->clearEntityManagerAndRetrieveImport();
            if (!$smallFile) {
                // on fait la suite du fichier
                while (($row = fgetcsv($file, 0, ';')) !== false) {
                    $logRows[] = $this->treatImportRow(
                        $row,
                        $headers,
                        $dataToCheck,
                        $colChampsLibres,
                        $refToUpdate,
                        $stats,
                        ($index % 500 === 0),
                        $index
                    );
                    $index++;
                }
            }

            // mise à jour des quantités sur références par article
            foreach ($refToUpdate as $ref) {
                $this->refArticleDataService->updateRefArticleQuantities($ref);
            }

            // flush update quantities
            $this->em->flush();

            // création du fichier de log
            $pieceJointeForLogFile = $this->persistLogFilePieceJointe($logRows);

            $statutRepository = $this->em->getRepository(Statut::class);
            $statusFinished = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED);

            $this->currentImport
                ->setLogFile($pieceJointeForLogFile)
                ->setNewEntries($stats['news'])
                ->setUpdatedEntries($stats['updates'])
                ->setNbErrors($stats['errors'])
                ->setStatus($statusFinished)
                ->setEndDate(new DateTime('now'));
            $this->em->flush();
        }

        fclose($file);

        return $importModeChoosen;
    }

    /**
     * @param array $row
     * @param array $headers
     * @param $dataToCheck
     * @param $colChampsLibres
     * @param array $refToUpdate
     * @param array $stats
     * @param bool $needsUnitClear
     * @param int $rowIndex
     * @return array
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    private function treatImportRow(array $row,
                                    array $headers,
                                    $dataToCheck,
                                    $colChampsLibres,
                                    array &$refToUpdate,
                                    array &$stats,
                                    bool $needsUnitClear,
                                    int $rowIndex): array
    {
        try {
            $this->em->transactional(function () use ($dataToCheck, $row, $headers, $colChampsLibres, &$refToUpdate, &$stats, $rowIndex) {
                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($dataToCheck, $row, $headers);

                switch ($this->currentImport->getEntity()) {
                    case Import::ENTITY_FOU:
                        $this->importFournisseurEntity($verifiedData, $stats);
                        break;
                    case Import::ENTITY_ART_FOU:
                        $this->importArticleFournisseurEntity($verifiedData, $stats);
                        break;
                    case Import::ENTITY_REF:
                        $this->importReferenceEntity($verifiedData, $colChampsLibres, $row, $stats);
                        break;
                    case Import::ENTITY_ART:
                        $referenceArticle = $this->importArticleEntity($verifiedData, $colChampsLibres, $row, $stats, $rowIndex);
                        $refToUpdate[$referenceArticle->getId()] = $referenceArticle;
                        break;
                }
            });
            if ($needsUnitClear) {
                $this->clearEntityManagerAndRetrieveImport();
            }
            $message = 'OK';
        } catch (Throwable $throwable) {
            // On réinitialise l'entity manager car il a été fermé
            if (!$this->em->isOpen()) {
                $this->em = EntityManager::Create($this->em->getConnection(), $this->em->getConfiguration());
                $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
                $this->currentImport = $this->em->find(Import::class, $this->currentImport->getId());
            }

            if ($throwable instanceof ImportException) {
                $message = $throwable->getMessage();
            } else {
                $message = 'Une erreur est survenue.';
                $file = $throwable->getFile();
                $line = $throwable->getLine();
                $logMessage = $throwable->getMessage();
                $trace = $throwable->getTraceAsString();
                $importId = $this->currentImport->getId();
                $this->logger->error("IMPORT ERROR : import n°$importId | $logMessage | File $file($line) | $trace");
                throw $throwable;
            }

            $stats['errors']++;
        }

        if (!empty($message)) {
            $headersLength = count($headers);
            $rowLength = count($row);
            $placeholdersColumns = ($rowLength < $headersLength)
                ? array_fill (0, $headersLength - $rowLength, '')
                : [];
            $resRow = array_merge($row, $placeholdersColumns, [$message]);
        }
        else {
            $resRow = $row;
        }

        return $resRow;
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
                        'value' => isset($corresp['commentaire']) ? $corresp['commentaire'] : null
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
                    'needsMobileSync' => [
                        'needed' => $this->fieldIsNeeded('needsMobileSync', Import::ENTITY_REF),
                        'value' => isset($corresp['needsMobileSync']) ? $corresp['needsMobileSync'] : null,
                    ]
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
                    'barCode' => [
                        'needed' => $this->fieldIsNeeded('barCode', Import::ENTITY_ART),
                        'value' => isset($corresp['barCode']) ? $corresp['barCode'] : null,
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
    private function buildLogFile(array $logRows)
    {
        $fileName = uniqid() . '.csv';

        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);

        $wantsUFT8 = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::USES_UTF8) ?? true;

        $this->attachmentService->saveCSVFile($fileName, $logRows, function ($row) use ($wantsUFT8) {
            return !$wantsUFT8
                ? array_map('utf8_decode', $row)
                : $row;
        });
        return $fileName;
    }

    /**
     * @param array $logRows
     * @return Attachment
     * @throws NonUniqueResultException
     */
    private function persistLogFilePieceJointe(array $logRows)
    {
        $createdLogFile = $this->buildLogFile($logRows);

        $pieceJointeForLogFile = new Attachment();
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
            } else if (empty($row[$originalDataToCheck['value']]) && $originalDataToCheck['needed']) {
                $columnIndex = $headers[$originalDataToCheck['value']];
                $message = "La valeur renseignée pour le champ $fieldName dans la colonne $columnIndex ne peut être vide.";
                $this->throwError($message);
            } else if (!is_null($originalDataToCheck['value']) && !empty($row[$originalDataToCheck['value']])) {
                $data[$column] = $row[$originalDataToCheck['value']];
            }
        }
        return $data;
    }

    /**
     * @param array $data
     * @param array $stats
     * @throws ImportException
     */
    private function importFournisseurEntity(array $data, array &$stats): void
    {
        $newEntity = false;

        if (empty($data['codeReference'])) {
            $this->throwError("La colone de référence n'est pas valide.");
        }

        $fournisseurRepository = $this->em->getRepository(Fournisseur::class);
        $fournisseur = $fournisseurRepository->findOneBy(['codeReference' => $data['codeReference']]);

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
            $refArticle = $refArticleRepository->findOneBy(['reference' => $data['referenceReference']]);
        }

        if (empty($refArticle)) {
            $this->throwError("La valeur renseignée pour la référence de l'article de référence ne correspond à aucune référence connue.");
        } else {
            $articleFournisseur->setReferenceArticle($refArticle);
        }

        if (!empty($data['fournisseurReference'])) {
            $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneBy(['codeReference' => $data['fournisseurReference']]);
        }

        if (empty($fournisseur)) {
            $this->throwError("La valeur renseignée pour le code du fournisseur ne correspond à aucun fournisseur connu.");
        } else {
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
     * @throws Exception
     */
    private function importReferenceEntity(array $data,
                                           array $colChampsLibres,
                                           array $row,
                                           array &$stats)
    {
        $isNewEntity = false;
        $refArtRepository = $this->em->getRepository(ReferenceArticle::class);
        $refArt = $refArtRepository->findOneBy(['reference' => $data['reference']]);

        if (!$refArt) {
            $refArt = new ReferenceArticle();
            $isNewEntity = true;
        }
        if (isset($data['libelle'])) {
            $refArt->setLibelle($data['libelle']);
        }
        if (isset($data['needsMobileSync'])) {
            $value = strtolower($data['needsMobileSync']);
            if ($value !== 'oui' && $value !== 'non') {
                $this->throwError('La valeur saisie pour le champ synchronisation nomade est invalide (autorisé : "oui" ou "non")');
            } else {
                $refArt->setNeedsMobileSync($value === 'oui');
            }
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
                $refArt->setDateLastInventory(DateTime::createFromFormat('d/m/Y', $data['dateLastInventory']));
            } catch (Exception $e) {
                $this->throwError('La date de dernier inventaire doit être au format JJ/MM/AAAA.');
            }
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
            } else if ($refArt->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                if (isset($data['quantiteStock']) && $data['quantiteStock'] < $refArt->getQuantiteReservee()) {
                    $message = 'La quantité doit être supérieure à la quantité réservée (' . $refArt->getQuantiteReservee() . ').';
                    $this->throwError($message);
                }
                $this->checkAndCreateMvtStock($refArt, $refArt->getQuantiteStock(), $data['quantiteStock'], $isNewEntity);
                $refArt->setQuantiteStock($data['quantiteStock']);
                $refArt->setQuantiteDisponible($refArt->getQuantiteStock() - $refArt->getQuantiteReservee());
            }
        }

        // champs libres
        $this->checkAndSetChampsLibres($colChampsLibres, $refArt, $isNewEntity, $row);

        $this->updateStats($stats, $isNewEntity);
    }

    /**
     * @param array $data
     * @param array $colChampsLibres
     * @param array $row
     * @param array $stats
     * @param int $rowIndex
     * @return ReferenceArticle
     * @throws ImportException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function importArticleEntity(array $data,
                                         array $colChampsLibres,
                                         array $row,
                                         array &$stats,
                                         int $rowIndex): ReferenceArticle
    {
        $refArticle = null;
        if (!empty($data['barCode'])) {
            $articleRepository = $this->em->getRepository(Article::class);
            $article = $articleRepository->findOneBy(['barCode' => $data['barCode']]);
            if (!$article) {
                $this->throwError('Le code barre donné est invalide.');
            }
            $isNewEntity = false;
            $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        } else {
            if (!empty($data['referenceReference'])) {
                $refArticleRepository = $this->em->getRepository(ReferenceArticle::class);
                $refArticle = $refArticleRepository->findOneBy(['reference' => $data['referenceReference']]);
                if (empty($refArticle)) {
                    $message = "La valeur renseignée pour la référence de l'article de référence ne correspond à aucune référence connue.";
                    $this->throwError($message);
                }
            } else {
                $message = "Veuillez saisir la référence de l'article de référence.";
                $this->throwError($message);
            }
            $article = new Article();
            $isNewEntity = true;
        }
        if (isset($data['label'])) {
            $article->setLabel($data['label']);
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
        if ($isNewEntity) {
            $refReferenceArticle = $refArticle->getReference();
            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $formattedDate = $date->format('YmdHis');
            $article->setReference($refReferenceArticle . $formattedDate . $rowIndex);
        }
        $article->setType($articleFournisseur->getReferenceArticle()->getType());

        // liaison emplacement
        $this->checkAndCreateEmplacement($data, $article);

        if (isset($data['quantite'])) {
            if (!is_numeric($data['quantite'])) {
                $this->throwError('La quantité doit être un nombre.');
            }
            $this->checkAndCreateMvtStock($article, $article->getQuantite(), $data['quantite'], $isNewEntity);
            $article->setQuantite($data['quantite']);
        }
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
        $champLibreRepository = $this->em->getRepository(FreeField::class);
        $missingCL = [];

        $categoryCL = $refOrArt instanceof ReferenceArticle ? CategorieCL::REFERENCE_ARTICLE : CategorieCL::ARTICLE;
        if ($refOrArt->getType() && $refOrArt->getType()->getId()) {
            $mandatoryCLs = $champLibreRepository->getMandatoryByTypeAndCategorieCLLabel($refOrArt->getType(), $categoryCL, $isNewEntity);
        } else {
            $mandatoryCLs = [];
        }
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

        $freeFieldsToInsert = [];

        foreach ($colChampsLibres as $clId => $col) {
            /** @var FreeField $champLibre */
            $champLibre = $champLibreRepository->find($clId);

            switch ($champLibre->getTypage()) {
                case FreeField::TYPE_BOOL:
                    $value = in_array($row[$col], ['Oui', 'oui', 1, '1']);
                    break;
                case FreeField::TYPE_DATE:
                    $value = $this->checkDate($row[$col], 'd/m/Y', 'Y-m-d', 'jj/mm/AAAA', $champLibre);
                    break;
                case FreeField::TYPE_DATETIME:
                    $value = $this->checkDate($row[$col], 'd/m/Y H:i', 'Y-m-d\TH:i', 'jj/mm/AAAA HH:MM', $champLibre);
                    break;
                case FreeField::TYPE_LIST:
                    $value = $this->checkList($row[$col], $champLibre, false);
                    break;
                case FreeField::TYPE_LIST_MULTIPLE:
                    $value = $this->checkList($row[$col], $champLibre, true);
                    break;
                default:
                    $value = $row[$col];
                    break;
            }
            $freeFieldsToInsert[$champLibre->getId()] = strval(is_bool($value) ? intval($value) : $value);
        }

        $refOrArt->setFreeFields($freeFieldsToInsert);
    }

    /**
     * @param string $dateString
     * @param string $format
     * @param string $outputFormat
     * @param string $errorFormat
     * @param FreeField $champLibre
     * @return string
     * @throws ImportException
     */
    private function checkDate(string $dateString, string $format, string $outputFormat, string $errorFormat, FreeField $champLibre): ?string
    {
        $response = null;
        if ($dateString !== "") {
            try {
                $date = DateTime::createFromFormat($format, $dateString);
                if (!$date) {
                    throw new Exception('Invalid format');
                }
                $response = $date->format($outputFormat);
            } catch (Exception $ignored) {
                $message = 'La date fournie pour le champ "' . $champLibre->getLabel() . '" doit être au format ' . $errorFormat . '.';
                $this->throwError($message);
            }
        }
        return $response;
    }

    /**
     * @param string $element
     * @param FreeField $champLibre
     * @param bool $isMultiple
     * @return string
     * @throws ImportException
     */
    private function checkList(string $element, FreeField $champLibre, bool $isMultiple): ?string
    {
        $response = null;
        if ($element !== "") {
            $elements = $isMultiple ? explode(";", $element) : [$element];
            foreach ($elements as $listElement) {
                if (!in_array($listElement, $champLibre->getElements())) {
                    $this->throwError('La ou les valeurs fournies pour le champ "' . $champLibre->getLabel() . '"'
                        . 'doivent faire partie des valeurs du champ libre ('
                        . implode(",", $champLibre->getElements()) . ').');
                }
            }
            $response = $element;
        }
        return $response;
    }

    /**
     * @param ReferenceArticle|Article $refOrArt
     * @param int $formerQuantity
     * @param int $newQuantity
     * @param bool $isNewEntity
     * @throws Exception
     */
    private function checkAndCreateMvtStock($refOrArt, int $formerQuantity, int $newQuantity, bool $isNewEntity)
    {
        $diffQuantity = $isNewEntity ? $newQuantity : ($newQuantity - $formerQuantity);

        $mvtIn = $isNewEntity ? MouvementStock::TYPE_ENTREE : MouvementStock::TYPE_INVENTAIRE_ENTREE;
        if ($diffQuantity != 0) {
            $typeMvt = $diffQuantity > 0 ? $mvtIn : MouvementStock::TYPE_INVENTAIRE_SORTIE;

            $emplacement = $refOrArt->getEmplacement();
            $mvtStock = $this->mouvementStockService->createMouvementStock($this->currentImport->getUser(), $emplacement, abs($diffQuantity), $refOrArt, $typeMvt);
            $this->mouvementStockService->finishMouvementStock($mvtStock, new DateTime('now'), $emplacement);
            $mvtStock->setImport($this->currentImport);
            $this->em->persist($mvtStock);
        }
    }

    /**
     * @param string $ref
     * @return Fournisseur
     */
    private function checkAndCreateProvider($ref)
    {
        $fournisseurRepository = $this->em->getRepository(Fournisseur::class);
        $provider = $fournisseurRepository->findOneBy(['codeReference' => $ref]);

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
     */
    private function checkAndCreateEmplacement(array $data,
                                               $articleOrRef): void
    {
        if (empty($data['emplacement'])) {
            $message = 'La valeur saisie pour l\'emplacement ne peut être vide.';
            $this->throwError($message);
        } else {
            $emplacementRepository = $this->em->getRepository(Emplacement::class);
            $location = $emplacementRepository->findOneBy(['label' => $data['emplacement']]);
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
     * @throws Exception
     */
    private function checkAndCreateArticleFournisseur(?string $articleFournisseurReference,
                                                      ?string $fournisseurReference,
                                                      ?ReferenceArticle $referenceArticle): ?ArticleFournisseur
    {
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

                $articleFournisseur = $this->articleFournisseurService->createArticleFournisseur([
                    'fournisseur' => $fournisseur,
                    'reference' => $articleFournisseurReference,
                    'article-reference' => $referenceArticle,
                    'label' => ($referenceArticle->getLibelle() . ' / ' . $fournisseur->getNom())
                ]);
            } else {
                // on a réussi à trouver un article fournisseur
                // vérif que l'article fournisseur correspond au couple référence article / fournisseur
                if (!empty($fournisseurReference)) {
                    $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneBy(['codeReference' => $fournisseurReference]);

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
        } // cas où la ref d'article fournisseur n'est pas renseignée
        else {
            if (empty($referenceArticle)) {
                $this->throwError("Vous n'avez pas renseigné de référence d'article fournisseur. Dans ce cas, veuillez fournir une référence d'article de référence connue.");
            }

            $fournisseur = $this->checkAndCreateProvider(!empty($fournisseurReference) ? $fournisseurReference : Fournisseur::REF_A_DEFINIR);

            $articleFournisseur = $this->articleFournisseurService->findSimilarArticleFournisseur(
                $referenceArticle,
                !empty($fournisseurReference) ? $fournisseur : null
            );
            if (empty($articleFournisseur)) {
                $articleFournisseur = $this->articleFournisseurService->createArticleFournisseur([
                    'label' => $referenceArticle->getLibelle() . ' / ' . $fournisseur->getNom(),
                    'article-reference' => $referenceArticle,
                    'reference' => $referenceArticle->getReference() . ' / ' . $fournisseur->getCodeReference(),
                    'fournisseur' => $fournisseur
                ]);
            }
        }

        return $articleFournisseur;
    }

    /**
     * @param array $stats
     * @param boolean $newEntity
     */
    private function updateStats(&$stats, $newEntity)
    {
        if ($newEntity) {
            $stats['news']++;
        } else {
            $stats['updates']++;
        }
    }

    private function clearEntityManagerAndRetrieveImport()
    {
        $this->em->clear();
        $this->currentImport = $this->em->find(Import::class, $this->currentImport->getId());
    }
}
