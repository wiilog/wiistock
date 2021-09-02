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
use App\Entity\ReceptionReferenceArticle;
use App\Entity\Attachment;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Exceptions\ImportException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Throwable;
use WiiCommon\Helper\Stream;
use Closure;
use DateTime;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;


class ImportService
{
    public const MAX_LINES_FLASH_IMPORT = 100;
    public const MAX_LINES_AUTO_FORCED_IMPORT = 500;

    public const IMPORT_MODE_RUN = 1; // réaliser l'import maintenant
    public const IMPORT_MODE_FORCE_PLAN = 2; // réaliser l'import rapidement (dans le cron qui s'exécute toutes les 30min)
    public const IMPORT_MODE_PLAN = 3; // réaliser l'import dans la nuit (dans le cron à 23h59)
    public const IMPORT_MODE_NONE = 4; // rien n'a été réalisé sur l'import

    private Twig_Environment $templating;

    private RouterInterface $router;

    private EntityManagerInterface $em;
    private ArticleDataService $articleDataService;
    private RefArticleDataService $refArticleDataService;
    private MouvementStockService $mouvementStockService;
    private LoggerInterface $logger;
    private AttachmentService $attachmentService;
    private ReceptionService $receptionService;
    private ArticleFournisseurService $articleFournisseurService;
    private UserService $userService;

    private Import $currentImport;

    public function __construct(RouterInterface $router,
                                LoggerInterface $logger,
                                AttachmentService $attachmentService,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ArticleDataService $articleDataService,
                                RefArticleDataService $refArticleDataService,
                                ArticleFournisseurService $articleFournisseurService,
                                ReceptionService $receptionService,
                                MouvementStockService $mouvementStockService,
                                UserService $userService)
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
        $this->receptionService = $receptionService;
        $this->userService = $userService;
    }

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

    public function dataRowImport(Import $import)
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
            'entity' => Import::ENTITY_LABEL[$import->getEntity()] ?? "Non défini",
            'actions' => $this->templating->render('import/datatableImportRow.html.twig', [
                'url' => $url,
                'importId' => $importId,
                'fournisseurId' => $importId,
                'canCancel' => ($statusLabel === Import::STATUS_PLANNED),
                'logFile' => $import->getLogFile() ? $import->getLogFile()->getFileName() : null
            ]),
        ];
    }

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

    public function treatImport(Import $import, ?Utilisateur $user, int $mode = self::IMPORT_MODE_PLAN): int
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
        $receptionsWithCommand = [];
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

            ['resource' => $logFile, 'fileName' => $logFileName] = $this->fopenLogFile();
            $logFileMapper = $this->getLogFileMapper();

            foreach ($firstRows as $row) {
                $logRow = $this->treatImportRow(
                    $row,
                    $headers,
                    $dataToCheck,
                    $colChampsLibres,
                    $refToUpdate,
                    $stats,
                    false,
                    $receptionsWithCommand,
                    $user,
                    $index
                );
                $index++;

                $this->attachmentService->putCSVLines($logFile, [$logRow], $logFileMapper);
            }
            $this->clearEntityManagerAndRetrieveImport();
            if (!$smallFile) {
                while (($row = fgetcsv($file, 0, ';')) !== false) {
                    $logRow = $this->treatImportRow(
                        $row,
                        $headers,
                        $dataToCheck,
                        $colChampsLibres,
                        $refToUpdate,
                        $stats,
                        ($index % 500 === 0),
                        $receptionsWithCommand,
                        $user,
                        $index
                    );
                    $index++;
                    $this->attachmentService->putCSVLines($logFile, [$logRow], $logFileMapper);
                }
            }

            fclose($logFile);

            // mise à jour des quantités sur références par article
            foreach ($refToUpdate as $ref) {
                $this->refArticleDataService->updateRefArticleQuantities($this->em, $ref);
            }

            // flush update quantities
            $this->em->flush();

            // création du fichier de log
            $logAttachment = $this->persistLogAttachment($logFileName);

            $statutRepository = $this->em->getRepository(Statut::class);
            $statusFinished = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED);

            $this->currentImport
                ->setLogFile($logAttachment)
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

    private function treatImportRow(array $row,
                                    array $headers,
                                    $dataToCheck,
                                    $colChampsLibres,
                                    array &$refToUpdate,
                                    array &$stats,
                                    bool $needsUnitClear,
                                    array &$receptionsWithCommand,
                                    ?Utilisateur $user,
                                    int $rowIndex): array
    {
        try {
            $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($dataToCheck, $row, $headers);
            $data = array_map('trim', $verifiedData);
            switch ($this->currentImport->getEntity()) {
                case Import::ENTITY_FOU:
                    $this->importFournisseurEntity($data, $stats);
                    break;
                case Import::ENTITY_ART_FOU:
                    $this->importArticleFournisseurEntity($data, $stats);
                    break;
                case Import::ENTITY_REF:
                    $this->importReferenceEntity($data, $colChampsLibres, $row, $dataToCheck, $stats);
                    break;
                case Import::ENTITY_RECEPTION:
                    $this->importReceptionEntity($data, $receptionsWithCommand, $user, $stats, $this->receptionService);
                    break;
                case Import::ENTITY_ART:
                    $referenceArticle = $this->importArticleEntity($data, $colChampsLibres, $row, $stats, $rowIndex);
                    $refToUpdate[$referenceArticle->getId()] = $referenceArticle;
                    break;
                case Import::ENTITY_USER:
                    $this->importUserEntity($data, $stats);
                    break;
            }

            $this->em->flush();
            if ($needsUnitClear) {
                $this->clearEntityManagerAndRetrieveImport();
            }
            $message = 'OK';
        } catch (Throwable $throwable) {
            // On réinitialise l'entity manager car il a été fermé
            if (!$this->em->isOpen()) {
                $this->em = EntityManager::Create($this->em->getConnection(), $this->em->getConfiguration());
                $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
            }

            $this->clearEntityManagerAndRetrieveImport();

            if ($throwable instanceof ImportException) {
                $message = $throwable->getMessage();
            }
            else if ($throwable instanceof UniqueConstraintViolationException) {
                $message = 'Une autre entité est en cours de création, veuillez réessayer.';
            } else {
                $message = 'Une erreur est survenue.';
                $file = $throwable->getFile();
                $line = $throwable->getLine();
                $logMessage = $throwable->getMessage();
                $trace = $throwable->getTraceAsString();
                $importId = $this->currentImport->getId();
                $this->logger->error("IMPORT ERROR : import n°$importId | $logMessage | File $file($line) | $trace");
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

    private function getDataToCheck(string $entity, array $corresp)
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
            case Import::ENTITY_RECEPTION:
                $dataToCheck = [
                    'orderNumber' => [
                        'needed' => $this->fieldIsNeeded('orderNumber', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['orderNumber']) ? $corresp['orderNumber'] : null
                    ],
                    'référence' => [
                        'needed' => $this->fieldIsNeeded('référence', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['référence']) ? $corresp['référence'] : null
                    ],
                    'location' => [
                        'needed' => $this->fieldIsNeeded('location', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['location']) ? $corresp['location'] : null
                    ],
                    'storageLocation' => [
                        'needed' => $this->fieldIsNeeded('storageLocation', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['storageLocation']) ? $corresp['storageLocation'] : null
                    ],
                    'fournisseur' => [
                        'needed' => $this->fieldIsNeeded('fournisseur', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['fournisseur']) ? $corresp['fournisseur'] : null
                    ],
                    'transporteur' => [
                        'needed' => $this->fieldIsNeeded('transporteur', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['transporteur']) ? $corresp['transporteur'] : null
                    ],
                    'commentaire' => [
                        'needed' => $this->fieldIsNeeded('commentaire', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['commentaire']) ? $corresp['commentaire'] : null
                    ],
                    'anomalie' => [
                        'needed' => $this->fieldIsNeeded('anomalie', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['anomalie']) ? $corresp['anomalie'] : null
                    ],
                    'quantité à recevoir' => [
                        'needed' => $this->fieldIsNeeded('quantité à recevoir', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['quantité à recevoir']) ? $corresp['quantité à recevoir'] : null
                    ],
                    'orderDate' => [
                        'needed' => $this->fieldIsNeeded('orderDate', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['orderDate']) ? $corresp['orderDate'] : null
                    ],
                    'manualUrgent' => [
                        'needed' => $this->fieldIsNeeded('manualUrgent', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['manualUrgent']) ? $corresp['manualUrgent'] : null
                    ],
                    'expectedDate' => [
                        'needed' => $this->fieldIsNeeded('expectedDate', Import::ENTITY_RECEPTION),
                        'value' => isset($corresp['expectedDate']) ? $corresp['expectedDate'] : null
                    ]
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
                    'buyer' => [
                        'needed' => $this->fieldIsNeeded('buyer', Import::ENTITY_REF),
                        'value' => isset($corresp['buyer']) ? $corresp['buyer'] : null,
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
                    ],
                    'managers' => [
                        'needed' => $this->fieldIsNeeded('managers', Import::ENTITY_REF),
                        'value' => isset($corresp['managers']) ? $corresp['managers'] : null,
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
                    'batch' => [
                        'needed' => $this->fieldIsNeeded('batch', Import::ENTITY_ART),
                        'value' => $corresp['batch'] ?? null,
                    ],
                    'expiryDate' => [
                        'needed' => $this->fieldIsNeeded('expiryDate', Import::ENTITY_ART),
                        'value' => $corresp['expiryDate'] ?? null,
                    ],
                    'stockEntryDate' => [
                        'needed' => $this->fieldIsNeeded('stockEntryDate', Import::ENTITY_ART),
                        'value' => $corresp['stockEntryDate'] ?? null,
                    ],
                ];
                break;
            case Import::ENTITY_USER:
                $dataToCheck = [
                    'role' => [
                        'needed' => $this->fieldIsNeeded('role', Import::ENTITY_USER),
                        'value' => $corresp['role'] ?? null,
                    ],
                    'username' => [
                        'needed' => $this->fieldIsNeeded('username', Import::ENTITY_USER),
                        'value' => $corresp['username'] ?? null,
                    ],
                    'email' => [
                        'needed' => $this->fieldIsNeeded('email', Import::ENTITY_USER),
                        'value' => $corresp['email'] ?? null,
                    ],
                    'secondaryEmail' => [
                        'needed' => $this->fieldIsNeeded('secondaryEmail', Import::ENTITY_USER),
                        'value' => $corresp['secondaryEmail'] ?? null,
                    ],
                    'lastEmail' => [
                        'needed' => $this->fieldIsNeeded('lastEmail', Import::ENTITY_USER),
                        'value' => $corresp['lastEmail'] ?? null,
                    ],
                    'phone' => [
                        'needed' => $this->fieldIsNeeded('phone', Import::ENTITY_USER),
                        'value' => $corresp['phone'] ?? null,
                    ],
                    'mobileLoginKey' => [
                        'needed' => $this->fieldIsNeeded('mobileLoginKey', Import::ENTITY_USER),
                        'value' => $corresp['mobileLoginKey'] ?? null,
                    ],
                    'address' => [
                        'needed' => $this->fieldIsNeeded('address', Import::ENTITY_USER),
                        'value' => $corresp['address'] ?? null,
                    ],
                    'deliveryTypes' => [
                        'needed' => $this->fieldIsNeeded('deliveryTypes', Import::ENTITY_USER),
                        'value' => $corresp['deliveryTypes'] ?? null,
                    ],
                    'dispatchTypes' => [
                        'needed' => $this->fieldIsNeeded('dispatchTypes', Import::ENTITY_USER),
                        'value' => $corresp['dispatchTypes'] ?? null,
                    ],
                    'handlingTypes' => [
                        'needed' => $this->fieldIsNeeded('handlingTypes', Import::ENTITY_USER),
                        'value' => $corresp['handlingTypes'] ?? null,
                    ],
                    'dropzone' => [
                        'needed' => $this->fieldIsNeeded('dropzone', Import::ENTITY_USER),
                        'value' => $corresp['dropzone'] ?? null,
                    ],
                    'visibilityGroup' => [
                        'needed' => $this->fieldIsNeeded('visibilityGroup', Import::ENTITY_USER),
                        'value' => $corresp['visibilityGroup'] ?? null,
                    ],
                    'status' => [
                        'needed' => $this->fieldIsNeeded('status', Import::ENTITY_USER),
                        'value' => $corresp['status'] ?? null,
                    ],
                ];
                break;
            default:
                $dataToCheck = [];
        }

        return $dataToCheck;
    }

    private function fopenLogFile() {
        $fileName = uniqid() . '.csv';
        $completeFileName = $this->attachmentService->getAttachmentDirectory() . '/' . $fileName;
        return [
            'fileName' => $fileName,
            'resource' => fopen($completeFileName, 'w')
        ];
    }

    private function getLogFileMapper(): Closure {
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
        $wantsUFT8 = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::USES_UTF8) ?? true;

        return function ($row) use ($wantsUFT8) {
            return !$wantsUFT8
                ? array_map('utf8_decode', $row)
                : $row;
        };
    }

    private function persistLogAttachment(string $createdLogFile): Attachment
    {
        $pieceJointeForLogFile = new Attachment();
        $pieceJointeForLogFile
            ->setOriginalName($createdLogFile)
            ->setFileName($createdLogFile);

        $this->em->persist($pieceJointeForLogFile);

        return $pieceJointeForLogFile;
    }

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
            } else if(isset($row[$originalDataToCheck['value']]) && strlen($row[$originalDataToCheck['value']])) {
                $data[$column] = $row[$originalDataToCheck['value']];
            }
        }
        return $data;
    }

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

    public function throwError($message)
    {
        throw new ImportException($message);
    }

    private function importReceptionEntity(array $data,
                                           array &$receptionsWithCommand,
                                           ?Utilisateur $user,
                                           array &$stats,
                                           ReceptionService $receptionService)
    {
        $refArtRepository = $this->em->getRepository(ReferenceArticle::class);

        if ($user) {
            $userRepository = $this->em->getRepository(Utilisateur::class);
            $user = $userRepository->find($user->getId());
        }

        $reception = $receptionService->getAlreadySavedReception($receptionsWithCommand, $data['orderNumber'], $data['expectedDate'], fn() => $this->updateStats($stats, false));
        $newEntity = !isset($reception);
        if (!$reception) {
            try {
                $reception = $this->receptionService->createAndPersistReception($this->em, $user, $data, true);
            }
            catch(InvalidArgumentException $exception) {
                switch ($exception->getMessage()) {
                    case ReceptionService::INVALID_EXPECTED_DATE:
                        $this->throwError('La date attendue n\'est pas au bon format (dd/mm/yyyy)');
                        break;
                    case ReceptionService::INVALID_ORDER_DATE:
                        $this->throwError('La date commande n\'est pas au bon format (dd/mm/yyyy)');
                        break;
                    case ReceptionService::INVALID_LOCATION:
                        $this->throwError('Emplacement renseigné invalide');
                        break;
                    case ReceptionService::INVALID_STORAGE_LOCATION:
                        $this->throwError('Emplacement de stockage renseigné invalide');
                        break;
                    case ReceptionService::INVALID_CARRIER:
                        $this->throwError('Transporteur renseigné invalide');
                        break;
                    case ReceptionService::INVALID_PROVIDER:
                        $this->throwError('Fournisseur renseigné invalide');
                        break;
                    default:
                        throw $exception;
                }
            }
            $this->receptionService->setAlreadySavedReception($receptionsWithCommand, $data['orderNumber'], $data['expectedDate'], $reception);
        }

        if(!empty($data['référence'])) {
            $refArt = $refArtRepository->findOneBy(['reference' => $data['référence']]);

            if($refArt) {
                if(isset($data['quantité à recevoir'])) {
                    $receptionRefArticle = new ReceptionReferenceArticle();
                    $receptionRefArticle
                        ->setReception($reception)
                        ->setReferenceArticle($refArt)
                        ->setQuantiteAR($data['quantité à recevoir'])
                        ->setCommande($reception->getOrderNumber())
                        ->setQuantite(0);
                    $this->em->persist($receptionRefArticle);
                } else {
                    $this->throwError('La quantité à recevoir doit être renseignée.');
                }
            }
            else {
                $this->throwError('La référence article n\'existe pas.');
            }
        }
        if ($newEntity) {
            $this->updateStats($stats, true);
        }
    }

    private function importReferenceEntity(array $data,
                                           array $colChampsLibres,
                                           array $row,
                                           array $dataToCheck,
                                           array &$stats)
    {
        $isNewEntity = false;
        $refArtRepository = $this->em->getRepository(ReferenceArticle::class);
        $userRepository = $this->em->getRepository(Utilisateur::class);
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

        if (isset($data['managers'])) {
            $usernames = Stream::explode([";", ","], $data["managers"])
                ->unique()
                ->map("trim")
                ->toArray();

            $managers = $userRepository->findByUsernames($usernames);
            foreach($managers as $manager) {
                $refArt->addManager($manager);
            }
        }

        if (isset($data['reference'])) {
            $refArt->setReference($data['reference']);
        }
        if (isset($data['buyer'])) {
            $refArt->setBuyer($userRepository->findOneBy(['username' => $data['buyer']]));
        }
        if (isset($data['commentaire'])) {
            $refArt->setCommentaire($data['commentaire']);
        }
        if (isset($data['emergencyComment'])) {
            $refArt->setEmergencyComment($data['emergencyComment']);
        }
        if (isset($data['dateLastInventory'])) {
            try {
                $refArt->setDateLastInventory(DateTime::createFromFormat('d/m/Y', $data['dateLastInventory']) ?: null);
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

        if(isset($dataToCheck["limitSecurity"]) && $dataToCheck["limitSecurity"]["value"] !== null) {
            $limitSecurity = $data['limitSecurity'] ?? null;
            if($limitSecurity === "") {
                $refArt->setLimitSecurity(null);
            } else if($limitSecurity !== null && !is_numeric($limitSecurity)) {
                $message = 'Le seuil de sécurité doit être un nombre.';
                $this->throwError($message);
            } else {
                $refArt->setLimitSecurity($limitSecurity);
            }
        }

        if(isset($dataToCheck["limitWarning"]) && $dataToCheck["limitWarning"]["value"] !== null) {
            $limitWarning = $data['limitWarning'] ?? null;
            if($limitWarning === "") {
                $refArt->setLimitWarning(null);
            } else if($limitWarning !== null && !is_numeric($limitWarning)) {
                $message = 'Le seuil d\'alerte doit être un nombre. ';
                $this->throwError($message);
            } else {
                $refArt->setLimitWarning($limitWarning);
            }
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
                $this->throwError('Le prix unitaire doit être un nombre.');
            }
            $article->setPrixUnitaire($data['prixUnitaire']);
        }

        if (isset($data['batch'])) {
            $article->setBatch($data['batch']);
        }

        if (isset($data['expiryDate'])) {
            if(str_contains($data['expiryDate'], ' ')) {
                $date = DateTime::createFromFormat("d/m/Y H:i", $data['expiryDate']);
            } else {
                $date = DateTime::createFromFormat("d/m/Y", $data['expiryDate']);
            }

            $article->setExpiryDate($date);
        }

        if (isset($data['stockEntryDate'])) {
            if(str_contains($data['stockEntryDate'], ' ')) {
                $date = DateTime::createFromFormat("d/m/Y H:i", $data['stockEntryDate']);
            } else {
                $date = DateTime::createFromFormat("d/m/Y", $data['stockEntryDate']);
            }

            $article->setStockEntryDate($date);
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
            $date = new DateTime('now');
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

    private function importUserEntity(array $data, array &$stats): void {

        $userAlreadyExists = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);

        $user = $userAlreadyExists ?? new Utilisateur();

        $role = $this->em->getRepository(Role::class)->findOneBy(['label' => $data['role']]);
        if($role) {
            $user->setRole($role);
        } else {
            $this->throwError("Le rôle ${data['role']} n'existe pas");
        }

        if(isset($data['username'])) {
            $user->setUsername($data['username']);
        }

        if(!isset($userAlreadyExists)) {
            if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->throwError('Le format de l\'adresse email est incorrect');
            }
            $user
                ->setEmail($data['email'])
                ->setPassword("");
        }

        if(isset($data['secondaryEmail']) && isset($data['lastEmail'])) {
            if(!filter_var($data['secondaryEmail'], FILTER_VALIDATE_EMAIL)
                && !filter_var($data['lastEmail'], FILTER_VALIDATE_EMAIL)) {
                $this->throwError('Le format des adresses email 1 et 2 sont incorrects');
            }
            $user->setSecondaryEmails([$data['secondaryEmail'], $data['lastEmail']]);
        } else if(isset($data['secondaryEmail'])) {
            if(!filter_var($data['secondaryEmail'], FILTER_VALIDATE_EMAIL)) {
                $this->throwError('Le format de l\'adresse email 2 est incorrect');
            }
            $user->setSecondaryEmails([$data['secondaryEmail']]);
        } else if(isset($data['lastEmail'])) {
            if(!filter_var($data['lastEmail'], FILTER_VALIDATE_EMAIL)) {
                $this->throwError('Le format de l\'adresse email 3 est incorrect');
            }
            $user->setSecondaryEmails([$data['lastEmail']]);
        }

        if(isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        if(isset($data['mobileLoginKey'])) {
            $user->setMobileLoginKey($data['mobileLoginKey']);
        } else {
            $mobileLoginKey = $this->userService->createUniqueMobileLoginKey($this->em);
            $user->setMobileLoginKey($mobileLoginKey);
        }

        if(isset($data['address'])) {
            $user->setAddress($data['address']);
        }

        if(isset($data['deliveryTypes'])) {
            $deliveryCategory = $this->em->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_LIVRAISON]);
            $deliveryTypes = $this->em->getRepository(Type::class)->findBy([
                'label' => array_map('trim', explode(',', $data['deliveryTypes'])),
                'category' => $deliveryCategory
            ]);

            foreach ($user->getDeliveryTypes() as $type) {
                $user->removeDeliveryType($type);
            }

            foreach ($deliveryTypes as $deliveryType) {
                $user->addDeliveryType($deliveryType);
            }
        }

        if(isset($data['dispatchTypes'])) {
            $dispatchCategory = $this->em->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_DISPATCH]);
            $dispatchTypes = $this->em->getRepository(Type::class)->findBy([
                'label' => array_map('trim', explode(',', $data['dispatchTypes'])),
                'category' => $dispatchCategory
            ]);

            foreach ($user->getDispatchTypes() as $type) {
                $user->removeDispatchType($type);
            }

            foreach ($dispatchTypes as $dispatchType) {
                $user->addDispatchType($dispatchType);
            }
        }

        if(isset($data['handlingTypes'])) {
            $handlingCategory = $this->em->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_HANDLING]);
            $handlingTypes = $this->em->getRepository(Type::class)->findBy([
                'label' => array_map('trim', explode(',', $data['handlingTypes'])),
                'category' => $handlingCategory
            ]);

            foreach ($user->getHandlingTypes() as $type) {
                $user->removeHandlingType($type);
            }

            foreach ($handlingTypes as $handlingType) {
                $user->addHandlingType($handlingType);
            }
        }

        if(isset($data['dropzone'])) {
            $dropzone = $this->em->getRepository(Emplacement::class)->findOneBy(['label' => $data['dropzone']]);
            if(!isset($dropzone)) {
                $this->throwError("La dropzone ${data['dropzone']} n'existe pas");
            }
            $user->setDropzone($dropzone);
        }

        if(isset($data['visibilityGroup'])) {
            $visibilityGroup = $this->em->getRepository(VisibilityGroup::class)->findOneBy(['label' => $data['visibilityGroup']]);
            if(!isset($visibilityGroup)) {
                $this->throwError("Le groupe de visibilité ${data['visibilityGroup']} n'existe pas");
            }
            $user->setVisibilityGroup($visibilityGroup);
        }

        if(isset($data['status'])) {
            if(!in_array(strtolower($data['status']), ['actif', 'inactif']) ) {
                $this->throwError('La valeur du champ Statut est incorrecte (actif ou inactif)');
            }
            $status = strtolower($data['status']) === 'actif' ? 1 : 0;
            $user->setStatus($status);
        }

        $this->em->persist($user);

        $this->updateStats($stats, !$user->getId());
    }

    private function checkAndSetChampsLibres(array $colChampsLibres,
                                             $refOrArt,
                                             bool $isNewEntity,
                                             array $row)
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

        $freeFieldsToInsert = $refOrArt->getFreeFields();

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

    private function checkAndCreateProvider(string $ref)
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

    private function updateStats(array &$stats, bool $newEntity)
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
