<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Customer;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\FreeField;
use App\Entity\Import;
use App\Entity\Inventory\InventoryCategory;
use App\Entity\LocationGroup;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Project;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\ReferenceArticle;
use App\Entity\Role;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\Zone;
use App\Exceptions\FormException;
use App\Exceptions\ImportException;
use App\Repository\ZoneRepository;
use Closure;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;


class ImportService
{
    public const MAX_LINES_FLASH_IMPORT = 100;
    public const MAX_LINES_AUTO_FORCED_IMPORT = 500;
    public const NB_ROW_WITHOUT_CLEARING = 50;

    public const IMPORT_MODE_RUN = 1; // réaliser l'import maintenant
    public const IMPORT_MODE_FORCE_PLAN = 2; // réaliser l'import rapidement (dans le cron qui s'exécute toutes les 30min)
    public const IMPORT_MODE_PLAN = 3; // réaliser l'import dans la nuit (dans le cron à 23h59)
    public const IMPORT_MODE_NONE = 4; // rien n'a été réalisé sur l'import

    public const POSITIVE_ARRAY = ['oui', 'Oui', 'OUI'];

    public const FIELDS_TO_ASSOCIATE = [
        Import::ENTITY_ART => [
            "commentaire",
            "barCode",
            "stockEntryDate",
            "expiryDate",
            "dateLastInventory",
            "emplacement",
            "label",
            "batch",
            "prixUnitaire",
            "quantite",
            "referenceReference",
            "articleFournisseurReference",
            "fournisseurReference",
            "rfidTag",
        ],
        Import::ENTITY_REF => [
            "buyer",
            "catInv",
            "commentaire",
            "emergencyComment",
            "dateLastInventory",
            "emplacement",
            "stockManagement",
            "managers",
            "visibilityGroups",
            "libelle",
            "prixUnitaire",
            "quantiteStock",
            "reference",
            "limitWarning",
            "limitSecurity",
            "statut",
            "needsMobileSync",
            "type",
            "typeQuantite",
            "outFormatEquipment",
            "manufacturerCode",
            "volume",
            "weight",
            "associatedDocumentTypes",
        ],
        Import::ENTITY_FOU => [
            'nom',
            'codeReference',
            'possibleCustoms',
            'urgent',
        ],
        Import::ENTITY_RECEPTION => [
            "anomalie",
            "commentaire",
            "expectedDate",
            "orderDate",
            "location",
            "storageLocation",
            "fournisseur",
            "orderNumber",
            "quantité à recevoir",
            "référence",
            "transporteur",
            "manualUrgent",
        ],
        Import::ENTITY_ART_FOU => [
            "label",
            "reference",
            "referenceReference",
            "fournisseurReference",
        ],
        Import::ENTITY_USER => [
            "address",
            "mobileLoginKey",
            "dropzone",
            "email",
            "secondaryEmail",
            "lastEmail",
            "visibilityGroup",
            "username",
            "phone",
            "role",
            "status",
            "dispatchTypes",
            "deliveryTypes",
            "handlingTypes",
            "deliverer",
            'signatoryCode',
        ],
        Import::ENTITY_DELIVERY => [
            "articleCode",
            "commentaire",
            "requester",
            "destination",
            "targetLocationPicking",
            "quantityDelivery",
            "articleReference",
            "status",
            "type",
            "targetLocationPicking",
        ],
        Import::ENTITY_LOCATION => [
            "isActive",
            "description",
            "dateMaxTime",
            "isOngoingVisibleOnMobile",
            "allowedPackNatures",
            "name",
            "isDeliveryPoint",
            "allowedCollectTypes",
            "allowedDeliveryTypes",
            "signatories",
            "email",
            "zone",
        ],
        Import::ENTITY_CUSTOMER => [
            "name",
            "address",
            "phone",
            "email",
            "fax",
        ],
        Import::ENTITY_PROJECT => [
            "code",
            "description",
            "projectManager",
            "isActive",
        ],
        Import::ENTITY_REF_LOCATION => [
            "reference",
            "location",
            "securityQuantity",
            "conditioningQuantity",
        ],
    ];

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public ArticleDataService $articleDataService;

    #[Required]
    public RefArticleDataService $refArticleDataService;

    #[Required]
    public MouvementStockService $mouvementStockService;

    #[Required]
    public LoggerInterface $logger;

    #[Required]
    public AttachmentService $attachmentService;

    #[Required]
    public ReceptionService $receptionService;

    #[Required]
    public DemandeLivraisonService $demandeLivraisonService;

    #[Required]
    public ArticleFournisseurService $articleFournisseurService;

    #[Required]
    public UserService $userService;

    #[Required]
    public FormService $formService;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public ReceptionLineService $receptionLineService;

    #[Required]
    public UserPasswordHasherInterface $encoder;

    private EmplacementDataService $emplacementDataService;

    private Import $currentImport;
    private EntityManagerInterface $entityManager;
    private array $importCache = [];

    private array $cache = [];

    public function __construct(EntityManagerInterface $entityManager, EmplacementDataService $emplacementDataService) {
        $this->entityManager = $entityManager;
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->emplacementDataService = $emplacementDataService;
        $this->resetCache();
    }

    public function getDataForDatatable(Utilisateur $user, $params = null)
    {
        $importRepository = $this->entityManager->getRepository(Import::class);
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);

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
        $url['edit'] = $this->router->generate('supplier_edit', ['id' => $importId]);

        $importStatus = $import->getStatus();
        $statusLabel = isset($importStatus) ? $this->formatService->status($importStatus) : null;
        $statusCode = $importStatus?->getCode();
        $statusTitle = $statusCode === Import::STATUS_PLANNED
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
            'actions' => $this->templating->render('settings/donnees/import/datatableImportRow.html.twig', [
                'url' => $url,
                'importId' => $importId,
                'fournisseurId' => $importId,
                'canCancel' => ($statusCode === Import::STATUS_PLANNED),
                'logFile' => $import->getLogFile() ? $import->getLogFile()->getFileName() : null,
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
                'isUtf8' => mb_check_encoding($csvContent, 'UTF-8'),
            ];
        } else {
            $res = null;
        }

        fclose($file);

        return $res;
    }

    public function treatImport(Import $import, int $mode = self::IMPORT_MODE_PLAN): int
    {
        $this->currentImport = $import;
        $this->resetCache();
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
        $refToUpdate = [];
        $stats = [
            'news' => 0,
            'updates' => 0,
            'errors' => 0,
        ];

        $rowCount = 0;
        $firstRows = [];

        while (($row = fgetcsv($file, 0, ';')) !== false
            && $rowCount <= self::MAX_LINES_AUTO_FORCED_IMPORT) {

            if (empty($headers)) {
                $headers = $row;
                $headersLog = [...$headers, 'Statut import'];
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

                $statutRepository = $this->entityManager->getRepository(Statut::class);
                $statusPlanned = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_PLANNED);
                $this->currentImport->setStatus($statusPlanned);
                $this->entityManager->flush();
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

            if (isset($headersLog)) {
                $this->attachmentService->putCSVLines($logFile, [$headersLog], $logFileMapper);
            }

            $import->setStartDate(new DateTime());
            $this->entityManager->flush();

            $this->eraseGlobalDataBefore();

            foreach ($firstRows as $row) {
                $headersLog = $this->treatImportRow(
                    $row,
                    $headers,
                    $dataToCheck,
                    $colChampsLibres,
                    $refToUpdate,
                    $stats,
                    false,
                    $index
                );
                $index++;

                $this->attachmentService->putCSVLines($logFile, [$headersLog], $logFileMapper);
            }
            $this->clearEntityManagerAndRetrieveImport();
            if (!$smallFile) {
                while (($row = fgetcsv($file, 0, ';')) !== false) {
                    $headersLog = $this->treatImportRow(
                        $row,
                        $headers,
                        $dataToCheck,
                        $colChampsLibres,
                        $refToUpdate,
                        $stats,
                        ($index % self::NB_ROW_WITHOUT_CLEARING === 0),
                        $index
                    );
                    $index++;
                    $this->attachmentService->putCSVLines($logFile, [$headersLog], $logFileMapper);
                }
            }

            $this->eraseGlobalDataAfter();
            $this->entityManager->flush();

            fclose($logFile);

            // mise à jour des quantités sur références par article
            foreach ($refToUpdate as $ref) {
                $this->refArticleDataService->updateRefArticleQuantities($this->entityManager, $ref);
            }

            // flush update quantities
            $this->entityManager->flush();

            // création du fichier de log
            $logAttachment = $this->persistLogAttachment($logFileName);

            $statutRepository = $this->entityManager->getRepository(Statut::class);
            $statusFinished = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED);

            $this->currentImport
                ->setLogFile($logAttachment)
                ->setNewEntries($stats['news'])
                ->setUpdatedEntries($stats['updates'])
                ->setNbErrors($stats['errors'])
                ->setStatus($statusFinished)
                ->setEndDate(new DateTime('now'));
            $this->entityManager->flush();
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
                                    int $rowIndex,
                                    int $retry = 0): array
    {
        try {
            $emptyCells = count(array_filter($row, fn(string $value) => $value === ""));
            if($emptyCells !== count($row)) {
                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($this->currentImport->getEntity(), $dataToCheck, $row, $headers);
                $data = array_map('trim', $verifiedData);
                switch($this->currentImport->getEntity()) {
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
                        $this->importReceptionEntity($data, $this->currentImport->getUser(), $stats);
                        break;
                    case Import::ENTITY_ART:
                        $referenceArticle = $this->importArticleEntity($data, $colChampsLibres, $row, $stats, $rowIndex);
                        $refToUpdate[$referenceArticle->getId()] = $referenceArticle;
                        break;
                    case Import::ENTITY_USER:
                        $this->importUserEntity($data, $stats);
                        break;
                    case Import::ENTITY_DELIVERY:
                        $insertedDelivery = $this->importDeliveryEntity($data, $stats, $this->currentImport->getUser(), $refToUpdate, $colChampsLibres, $row);
                        break;
                    case Import::ENTITY_LOCATION:
                        $this->importLocationEntity($data, $stats);
                        break;
                    case Import::ENTITY_CUSTOMER:
                        $this->importCustomerEntity($data, $stats);
                        break;
                    case Import::ENTITY_PROJECT:
                        $this->importProjectEntity($data, $stats);
                        break;
                    case Import::ENTITY_REF_LOCATION:
                        $this->importRefLocationEntity($data, $stats);
                        break;
                }

                $this->entityManager->flush();
                if (!empty($insertedDelivery)) {
                    $this->cache['deliveries'][$insertedDelivery->getUtilisateur()->getId() . '-' . $insertedDelivery->getDestination()->getId()] = $insertedDelivery;
                }
                if ($needsUnitClear) {
                    $this->clearEntityManagerAndRetrieveImport();
                }
            }

            $message = 'OK';
        } catch (Throwable $throwable) {
            // On réinitialise l'entity manager car il a été fermé
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = EntityManager::Create($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
                $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
            }

            $this->clearEntityManagerAndRetrieveImport();

            if ($throwable instanceof ImportException) {
                $message = $throwable->getMessage();
            } else if ($throwable instanceof UniqueConstraintViolationException) {
                if ($retry <= UniqueNumberService::MAX_RETRY) {
                    $retry++;
                    return $this->treatImportRow($row,
                        $headers,
                        $dataToCheck,
                        $colChampsLibres,
                        $refToUpdate,
                        $stats,
                        $needsUnitClear,
                        $rowIndex,
                        $retry
                    );
                } else {
                    $message = 'Une autre entité est en cours de création, veuillez réessayer.';
                }
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

    private function getDataToCheck(string $entity, array $corresp): array
    {
        return Stream::from(ImportService::FIELDS_TO_ASSOCIATE[$entity])
            ->keymap(fn(string $field) => [
                $field,
                [
                    'needed' => $this->fieldIsNeeded($field, $entity),
                    'value' => $corresp[$field] ?? null,
                ],
            ])
            ->toArray();
    }

    private function fopenLogFile() {
        $fileName = uniqid() . '.csv';
        $completeFileName = $this->attachmentService->getAttachmentDirectory() . '/' . $fileName;
        return [
            'fileName' => $fileName,
            'resource' => fopen($completeFileName, 'w'),
        ];
    }

    private function getLogFileMapper(): Closure {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $wantsUFT8 = $settingRepository->getOneParamByLabel(Setting::USES_UTF8) ?? true;

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

        $this->entityManager->persist($pieceJointeForLogFile);

        return $pieceJointeForLogFile;
    }

    private function checkFieldsAndFillArrayBeforeImporting(string $entity, array $originalDatasToCheck, array $row, array $headers): array
    {
        $data = [];
        foreach ($originalDatasToCheck as $column => $originalDataToCheck) {
            $fieldName = Import::FIELDS_ENTITY[$entity][$column]
                ?? Import::FIELDS_ENTITY['default'][$column]
                ?? $column;
            if(is_array($fieldName)) {
                $fieldName = $this->translationService->translate(...$fieldName);
            }

            if ($originalDataToCheck['value'] === null && $originalDataToCheck['needed']) {
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
        if (!isset($data['codeReference'])) {
            $this->throwError("Le code fournisseur est obligatoire");
        }

        $supplierRepository = $this->entityManager->getRepository(Fournisseur::class);
        $existingSupplier = $supplierRepository->findOneBy(['codeReference' => $data['codeReference']]);

        $supplier = $existingSupplier ?? new Fournisseur();

        if(!$supplier->getId()) {
            $supplier->setCodeReference($data['codeReference']);
        }

        $allowedValues = ['oui', 'non'];
        $possibleCustoms = isset($data["possibleCustoms"]) ? strtolower($data["possibleCustoms"]) : null;
        if(isset($data["possibleCustoms"]) && !in_array($possibleCustoms, $allowedValues)) {
            $this->throwError("La valeur du champ Douane possible n'est pas correcte (oui ou non)");
        } else {
            $supplier->setPossibleCustoms($possibleCustoms === 'oui');
        }

        $urgent = isset($data["urgent"]) ? strtolower($data["urgent"]) : null;
        if(isset($data["urgent"]) && !in_array($urgent, $allowedValues)) {
            $this->throwError("La valeur du champ Urgent n'est pas correcte (oui ou non)");
        } else {
            $supplier->setUrgent($urgent === 'oui');
        }

        $supplier->setNom($data['nom']);

        $this->entityManager->persist($supplier);

        $this->updateStats($stats, !$supplier->getId());
    }

    private function importArticleFournisseurEntity(array $data, array &$stats): void
    {
        $newEntity = false;

        if (empty($data['reference'])) {
            $this->throwError('La colonne référence ne doit pas être vide');
        }

        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $eraseData = $this->currentImport->isEraseData();

        if (!empty($data['referenceReference'])) {
            $refArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $refArticleRepository->findOneBy(['reference' => $data['referenceReference']]);
        }

        if (empty($refArticle)) {
            $this->throwError("La valeur renseignée pour la référence de l'article de référence ne correspond à aucune référence connue.");
        }

        $supplierArticle = $articleFournisseurRepository->findOneBy(['reference' => $data['reference']]);
        if (empty($supplierArticle)) {
            $newEntity = true;
            $supplierArticle = new ArticleFournisseur();
            $supplierArticle->setReference($data['reference']);
        }

        if (isset($data['label'])) {
            $supplierArticle->setLabel($data['label']);
        }

        if (!empty($data['fournisseurReference'])) {
            $fournisseur = $this->entityManager->getRepository(Fournisseur::class)->findOneBy(['codeReference' => $data['fournisseurReference']]);
        }

        if (empty($fournisseur)) {
            $this->throwError("La valeur renseignée pour le code du fournisseur ne correspond à aucun fournisseur connu.");
        }

        $supplierArticle
            ->setReferenceArticle($refArticle)
            ->setFournisseur($fournisseur)
            ->setVisible(true);

        $this->entityManager->persist($supplierArticle);

        if ($eraseData) {
            $this->cache["resetSupplierArticles"] = $this->cache["resetSupplierArticles"] ?? [
                "supplierArticles" => [],
                "referenceArticles" => [],
            ];

            $this->cache["resetSupplierArticles"]["supplierArticles"][] = $supplierArticle->getReference();
            $this->cache["resetSupplierArticles"]["referenceArticles"][] = $refArticle->getId();
        }

        $this->updateStats($stats, $newEntity);
    }

    public function throwError($message) {
        throw new ImportException($message);
    }

    private function importReceptionEntity(array        $data,
                                           ?Utilisateur $user,
                                           array        &$stats): void {
        $refArtRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        if ($user) {
            $user = $userRepository->find($user->getId());
        }

        $uniqueReceptionConstraint = [
            'orderNumber' => $data['orderNumber'] ?? null,
            'expectedDate' => $data['expectedDate'] ?? null,
        ];

        $reception = $this->receptionService->getAlreadySavedReception(
            $this->entityManager,
            $this->cache['receptions'],
            $uniqueReceptionConstraint
        );

        // retrieve from database if we don't found the reception in the cache
        if (!isset($reception)) {
            $expectedDate = isset($uniqueConstraint['expectedDate'])
                ? DateTime::createFromFormat("d/m/Y", $uniqueConstraint['expectedDate']) ?: null
                : null;

            $receptionRepository = $this->entityManager->getRepository(Reception::class);
            $receptions = $receptionRepository->findBy(
                [
                    "orderNumber" => $uniqueReceptionConstraint['orderNumber'],
                    "dateAttendue" => $expectedDate,
                ],
                ['id' => Criteria::DESC]
            );

            if (!empty($receptions)) {
                $reception = $receptions[0];
                $this->receptionService->setAlreadySavedReception($this->cache['receptions'], $uniqueReceptionConstraint, $reception);
                $this->updateStats($stats, false);
            }
        }

        $newEntity = !isset($reception);
        try {
            if ($newEntity) {
                $reception = $this->receptionService->persistReception($this->entityManager, $user, $data, ['import' => true]);
                $this->receptionService->setAlreadySavedReception($this->cache['receptions'], $uniqueReceptionConstraint, $reception);
            }
            else {
                $this->receptionService->updateReception($this->entityManager, $reception, $data, [
                    'import' => true,
                    'update' => true,
                ]);
            }
        } catch (InvalidArgumentException $exception) {
            switch ($exception->getMessage()) {
                case ReceptionService::INVALID_EXPECTED_DATE:
                    $this->throwError('La date attendue n\'est pas au bon format (dd/mm/yyyy)');
                case ReceptionService::INVALID_ORDER_DATE:
                    $this->throwError('La date commande n\'est pas au bon format (dd/mm/yyyy)');
                case ReceptionService::INVALID_LOCATION:
                    $this->throwError('Emplacement renseigné invalide');
                case ReceptionService::INVALID_STORAGE_LOCATION:
                    $this->throwError('Emplacement de stockage renseigné invalide');
                case ReceptionService::INVALID_CARRIER:
                    $this->throwError('Transporteur renseigné invalide');
                case ReceptionService::INVALID_PROVIDER:
                    $this->throwError('Fournisseur renseigné invalide');
                default:
                    throw $exception;
            }
        }

        if (!empty($data['référence'])) {
            if (empty($uniqueReceptionConstraint['orderNumber'])) {
                $this->throwError("Le numéro de commande doit être renseigné.");
            }

            $referenceArticle = $refArtRepository->findOneBy(['reference' => $data['référence']]);
            if (!$referenceArticle) {
                $this->throwError('La référence article n\'existe pas.');
            }

            if (!isset($data['quantité à recevoir'])) {
                $this->throwError('La quantité à recevoir doit être renseignée.');
            }

            $line = $reception->getLine(null)
                ?? $this->receptionLineService->persistReceptionLine($this->entityManager, $reception, null);

            $receptionRefArticle = $line->getReceptionReferenceArticle($referenceArticle, $uniqueReceptionConstraint['orderNumber']);

            if (!isset($receptionRefArticle)) {
                $receptionRefArticle = new ReceptionReferenceArticle();
                $receptionRefArticle
                    ->setReceptionLine($line)
                    ->setReferenceArticle($referenceArticle)
                    ->setCommande($uniqueReceptionConstraint['orderNumber'])
                    ->setAnomalie($receptionAnomaly ?? false)
                    ->setQuantiteAR($data['quantité à recevoir'])
                    ->setQuantite(0);
                $this->entityManager->persist($receptionRefArticle);
            }
            else {
                $this->throwError("La ligne de réception existe déjà pour cette référence et ce numéro de commande");
            }

            $this->entityManager->flush();
        }

        $this->updateStats($stats, $newEntity);
    }

    private function importReferenceEntity(array $data,
                                           array $colChampsLibres,
                                           array $row,
                                           array $dataToCheck,
                                           array &$stats)
    {
        $isNewEntity = false;
        $refArtRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);
        $visibilityGroupRepository = $this->entityManager->getRepository(VisibilityGroup::class);
        $refArt = $refArtRepository->findOneBy(['reference' => $data['reference']]);
        $currentUser = $this->currentImport->getUser();
        $now = new DateTime();

        if (!$refArt) {
            $refArt = new ReferenceArticle();
            $refArt
                ->setCreatedAt($now)
                ->setCreatedBy($currentUser);
            $isNewEntity = true;
        } else {
            $refArt
                ->setEditedAt($now)
                ->setEditedBy($currentUser);
        }

        if (isset($data['libelle'])) {
            if ((strlen($data['libelle'])) > 255) {
                $this->throwError('La valeur saisie pour le champ libellé ne doit pas dépasser 255 caractères');
            } else {
                $refArt->setLibelle($data['libelle']);
            }
        }
        if (isset($data['needsMobileSync'])) {
            $value = strtolower($data['needsMobileSync']);
            if ($value !== 'oui' && $value !== 'non') {
                $this->throwError('La valeur saisie pour le champ synchronisation nomade est invalide (autorisé : "oui" ou "non")');
            } else {
                $refArt->setNeedsMobileSync($value === 'oui');
            }
        }

        if(isset($data['visibilityGroups'])) {
            $visibilityGroup = $visibilityGroupRepository->findOneBy(['label' => $data['visibilityGroups']]);
            if(!isset($visibilityGroup)) {
                $this->throwError("Le groupe de visibilité ${data['visibilityGroups']} n'existe pas");
            }
            $refArt->setProperties(['visibilityGroup' => $visibilityGroup]);
        }

        if (isset($data['managers'])) {
            $usernames = Stream::explode([";", ","], $data["managers"])
                ->unique()
                ->map(fn(string $username) => trim($username))
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
            $refArt->setCommentaire(StringHelper::cleanedComment($data['commentaire']));
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
                || !in_array($data['typeQuantite'], [ReferenceArticle::QUANTITY_TYPE_REFERENCE, ReferenceArticle::QUANTITY_TYPE_ARTICLE])) {
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
            $statusRepository = $this->entityManager->getRepository(Statut::class);
            $typeRepository = $this->entityManager->getRepository(Type::class);
            $categoryTypeRepository = $this->entityManager->getRepository(CategoryType::class);

            $status = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, ReferenceArticle::STATUT_ACTIF);

            $type = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, $data['type'] ?? Type::LABEL_STANDARD);
            if (empty($type)) {
                $categoryType = $categoryTypeRepository->findOneBy(['label' => CategoryType::ARTICLE]);

                $type = new Type();
                $type
                    ->setLabel($data['type'])
                    ->setColor('#3353D7')
                    ->setCategory($categoryType);
                $this->entityManager->persist($type);
            }

            $refArt
                ->setStatut($status)
                ->setIsUrgent(false)
                ->setBarCode($this->refArticleDataService->generateBarCode())
                ->setType($type);
        }
        else if (isset($data['type']) && $refArt->getType()?->getLabel() !== $data['type']) {
            $this->throwError("La modification du type d'une référence n'est pas autorisée");
        }

        // liaison emplacement
        if ($refArt->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
            $this->checkAndCreateEmplacement($data, $refArt);
        }

        // liaison statut
        if (!empty($data['statut'])) {
            $status = $this->entityManager->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(CategorieStatut::REFERENCE_ARTICLE, $data['statut']);
            if (empty($status)) {
                $message = "La valeur renseignée pour le statut ne correspond à aucun statut connu.";
                $this->throwError($message);
            } else {
                $refArt->setStatut($status);
            }
        }

        // liaison catégorie inventaire
        if (!empty($data['catInv'])) {
            $catInvRepository = $this->entityManager->getRepository(InventoryCategory::class);
            $catInv = $catInvRepository->findOneBy(['label' => $data['catInv']]);
            if (empty($catInv)) {
                $message = "La valeur renseignée pour la catégorie d'inventaire ne correspond à aucune catégorie connue.";
                $this->throwError($message);
            } else {
                $refArt->setCategory($catInv);
            }
        }

        $this->entityManager->persist($refArt);

        // quantité
        if (isset($data['quantiteStock'])) {
            if (!is_numeric($data['quantiteStock'])) {
                $message = 'La quantité doit être un nombre.';
                $this->throwError($message);
            } else if ($data['quantiteStock'] < 0) {
                $message = 'La quantité doit être positive.';
                $this->throwError($message);
            } else if ($refArt->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                if (isset($data['quantiteStock']) && $data['quantiteStock'] < $refArt->getQuantiteReservee()) {
                    $message = 'La quantité doit être supérieure à la quantité réservée (' . $refArt->getQuantiteReservee() . ').';
                    $this->throwError($message);
                }
                $this->checkAndCreateMvtStock($refArt, $refArt->getQuantiteStock(), $data['quantiteStock'], $isNewEntity);
                $refArt->setQuantiteStock($data['quantiteStock']);
                $refArt->setQuantiteDisponible($refArt->getQuantiteStock() - $refArt->getQuantiteReservee());
            }
        }

        $original = $refArt->getDescription() ?? [];

        $outFormatEquipmentData = isset($data['outFormatEquipment'])
            ? (int) (
                filter_var($data['outFormatEquipment'], FILTER_VALIDATE_BOOLEAN)
                || in_array($data['outFormatEquipment'], self::POSITIVE_ARRAY)
            )
            : null;

        $outFormatEquipment = $outFormatEquipmentData ?? $original['outFormatEquipment'] ?? null;
        $volume = $data['volume'] ?? $original['volume'] ?? null;
        $weight = $data['weight'] ?? $original['weight'] ?? null;
        $associatedDocumentTypesStr = $data['associatedDocumentTypes'] ?? $original['associatedDocumentTypes'] ?? null;
        $associatedDocumentTypes = $associatedDocumentTypesStr
            ? Stream::explode(',', $associatedDocumentTypesStr)
                ->filter()
                ->toArray()
            : [];

        if (!empty($volume) && !is_numeric($volume)) {
            $this->throwError('Champ volume non valide.');
        }
        if (!empty($weight) && !is_numeric($weight)) {
            $this->throwError('Champ poids non valide.');
        }

        $invalidAssociatedDocumentType = Stream::from($associatedDocumentTypes)
            ->find(fn(string $type) => !in_array($type, $this->importCache[Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES]));
        if (!empty($invalidAssociatedDocumentType)) {
            $this->throwError("Le type de document n'est pas valide : $invalidAssociatedDocumentType");
        }

        $description = [
            "outFormatEquipment" => $outFormatEquipment,
            "manufacturerCode" => $data['manufacturerCode'] ?? $original['manufacturerCode'] ?? null,
            "volume" => $volume,
            "weight" => $weight,
            "associatedDocumentTypes" => $associatedDocumentTypes,
        ];
        $refArt
            ->setDescription($description);
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
            $articleRepository = $this->entityManager->getRepository(Article::class);
            $article = $articleRepository->findOneBy(['barCode' => $data['barCode']]);
            if (!$article) {
                $this->throwError('Le code barre donné est invalide.');
            }
            $isNewEntity = false;
            $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        } else {
            if (!empty($data['referenceReference'])) {
                $refArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
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

        if (isset($data['rfidTag'])) {
            $article->setRFIDtag($data['rfidTag']);
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
            $statutRepository = $this->entityManager->getRepository(Statut::class);
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
        $this->entityManager->persist($article);
        // champs libres
        $this->checkAndSetChampsLibres($colChampsLibres, $article, $isNewEntity, $row);

        $this->updateStats($stats, $isNewEntity);

        return $refArticle;
    }

    private function importUserEntity(array $data, array &$stats): void {

        $userAlreadyExists = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);
        $visibilityGroupRepository = $this->entityManager->getRepository(VisibilityGroup::class);

        $user = $userAlreadyExists ?? new Utilisateur();

        // on user creation
        if (!isset($userAlreadyExists)) {
            $language = $this->languageService->getNewUserLanguage($this->entityManager);
            $user
                ->setLanguage($language)
                ->setDateFormat(Utilisateur::DEFAULT_DATE_FORMAT);
        }

        $role = $this->entityManager->getRepository(Role::class)->findOneBy(['label' => $data['role']]);
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
                $this->throwError('Le format des adresses email 2 et 3 est incorrect');
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
            $minMobileKeyLength = UserService::MIN_MOBILE_KEY_LENGTH;
            $maxMobileKeyLength = UserService::MAX_MOBILE_KEY_LENGTH;

            if(strlen($data['mobileLoginKey']) < UserService::MIN_MOBILE_KEY_LENGTH
                || strlen($data['mobileLoginKey']) > UserService::MAX_MOBILE_KEY_LENGTH) {
                $this->throwError("La clé de connexion doit faire entre ${minMobileKeyLength} et ${maxMobileKeyLength} caractères");
            }

            $userWithExistingKey = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['mobileLoginKey' => $data['mobileLoginKey']]);
            if(!isset($userWithExistingKey) || $userWithExistingKey->getId() === $user->getId()) {
                $user->setMobileLoginKey($data['mobileLoginKey']);
            } else {
                $this->throwError('Cette clé de connexion est déjà utilisée par un autre utilisateur');
            }
        } else if(!isset($userAlreadyExists)) {
            $mobileLoginKey = $this->userService->createUniqueMobileLoginKey($this->entityManager);
            $user->setMobileLoginKey($mobileLoginKey);
        }

        if(isset($data['address'])) {
            $user->setAddress($data['address']);
        }

        if(!empty($data['deliverer'])) {
            $value = strtolower($data['deliverer']);
            if ($value !== 'oui' && $value !== 'non') {
                $this->throwError('La valeur saisie pour le champ Livreur est invalide (autorisé : "oui" ou "non")');
            } else {
                $user->setDeliverer($value === 'oui');
            }
        }

        if(isset($data['deliveryTypes'])) {
            $deliveryTypesRaw = array_map('trim', explode(',', $data['deliveryTypes']));
            $deliveryCategory = $this->entityManager->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_LIVRAISON]);
            $deliveryTypes = $this->entityManager->getRepository(Type::class)->findBy([
                'label' => $deliveryTypesRaw,
                'category' => $deliveryCategory,
            ]);

            $deliveryTypesLabel = Stream::from($deliveryTypes)->map(fn(Type $type) => $type->getLabel())->toArray();
            $invalidTypes = Stream::diff($deliveryTypesLabel, $deliveryTypesRaw, false, true)->toArray();
            if(!empty($invalidTypes)) {
                $invalidTypesStr = implode(", ", $invalidTypes);
                $this->throwError("Les types de demandes de livraison suivants sont invalides : $invalidTypesStr");
            }

            foreach ($user->getDeliveryTypes() as $type) {
                $user->removeDeliveryType($type);
            }

            foreach ($deliveryTypes as $deliveryType) {
                $user->addDeliveryType($deliveryType);
            }
        }

        if(isset($data['dispatchTypes'])) {
            $dispatchTypesRaw = array_map('trim', explode(',', $data['dispatchTypes']));
            $dispatchCategory = $this->entityManager->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_DISPATCH]);
            $dispatchTypes = $this->entityManager->getRepository(Type::class)->findBy([
                'label' => $dispatchTypesRaw,
                'category' => $dispatchCategory,
            ]);

            $dispatchTypesLabel = Stream::from($dispatchTypes)->map(fn(Type $type) => $type->getLabel())->toArray();
            $invalidTypes = Stream::diff($dispatchTypesLabel, $dispatchTypesRaw, false, true)->toArray();
            if(!empty($invalidTypes)) {
                $invalidTypesStr = implode(", ", $invalidTypes);
                $this->throwError("Les types d'acheminements suivants sont invalides : $invalidTypesStr");
            }

            foreach ($user->getDispatchTypes() as $type) {
                $user->removeDispatchType($type);
            }

            foreach ($dispatchTypes as $dispatchType) {
                $user->addDispatchType($dispatchType);
            }
        }

        if(isset($data['handlingTypes'])) {
            $handlingTypesRaw = array_map('trim', explode(',', $data['handlingTypes']));
            $handlingCategory = $this->entityManager->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_HANDLING]);
            $handlingTypes = $this->entityManager->getRepository(Type::class)->findBy([
                'label' => $handlingTypesRaw,
                'category' => $handlingCategory,
            ]);

            $handlingTypesLabel = Stream::from($handlingTypes)->map(fn(Type $type) => $type->getLabel())->toArray();
            $invalidTypes = Stream::diff($handlingTypesLabel, $handlingTypesRaw, false, true)->toArray();
            if(!empty($invalidTypes)) {
                $invalidTypesStr = implode(", ", $invalidTypes);
                $this->throwError("Les types de services suivants sont invalides : $invalidTypesStr");
            }

            foreach ($user->getHandlingTypes() as $type) {
                $user->removeHandlingType($type);
            }

            foreach ($handlingTypes as $handlingType) {
                $user->addHandlingType($handlingType);
            }
        }

        if(isset($data['dropzone'])) {
            $locationRepository = $this->entityManager->getRepository(Emplacement::class);
            $locationGroupRepository = $this->entityManager->getRepository(LocationGroup::class);
            $dropzone = $locationRepository->findOneBy(['label' => $data['dropzone']])
                ?: $locationGroupRepository->findOneBy(['label' => $data['dropzone']]);
            if($dropzone) {
                $user->setDropzone($dropzone);
            } else {
                $this->throwError("La dropzone ${data['dropzone']} n'existe pas");
            }
        }
        foreach ($user->getVisibilityGroups() as $visibilityGroup) {
            $visibilityGroup->removeUser($user);
        }
        if (isset($data['visibilityGroup'])) {
            $visibilityGroups = Stream::explode([";", ","], $data["visibilityGroup"])
                ->unique()
                ->map(fn(string $visibilityGroup) => trim($visibilityGroup))
                ->map(function($label) use ($visibilityGroupRepository) {
                    $visibilityGroup = $visibilityGroupRepository->findOneBy(['label' => ltrim($label)]);
                    if (!$visibilityGroup) {
                        $this->throwError('Le groupe de visibilité ' . $label . ' n\'existe pas.');
                    }
                    return $visibilityGroup;
                })
                ->toArray();
            foreach($visibilityGroups as $visibilityGroup) {
                $user->addVisibilityGroup($visibilityGroup);
            }
        }

        if(isset($data['status'])) {
            if(!in_array(strtolower($data['status']), ['actif', 'inactif']) ) {
                $this->throwError('La valeur du champ Statut est incorrecte (actif ou inactif)');
            }
            $status = strtolower($data['status']) === 'actif' ? 1 : 0;
            $user->setStatus($status);
        }

        if (!empty($data['signatoryCode'])) {
            $plainSignatoryPassword = $data['signatoryCode'];
            if (strlen($plainSignatoryPassword) < 4) {
                $this->throwError("Le code signataire doit contenir au moins 4 caractères");
            }

            $signatoryPassword = $this->encoder->hashPassword($user, $plainSignatoryPassword);
            $user->setSignatoryPassword($signatoryPassword);
        }

        $this->entityManager->persist($user);

        $this->updateStats($stats, !$user->getId());
    }

    private function importCustomerEntity(array $data, array &$stats) {

        $customerAlreadyExists = $this->entityManager->getRepository(Customer::class)->findOneBy(['name' => $data['name']]);
        $customer = $customerAlreadyExists ?? new Customer();

        if (isset($data['name'])) {
            $customer->setName($data['name']);
        }

        if (isset($data['address'])) {
            $customer->setAddress($data['address']);
        }

        if (isset($data['phone'])) {
            if (!preg_match(StringHelper::PHONE_NUMBER_REGEX, $data['phone'])) {
                $this->throwError('Le format du numéro de téléphone est incorrect');
            }
            $customer->setPhoneNumber($data['phone']);
        }

        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->throwError('Le format de l\'adresse email est incorrect');
            }
            $customer->setEmail($data['email']);
        }

        if (isset($data['fax'])) {
            if (!preg_match(StringHelper::PHONE_NUMBER_REGEX, $data['fax'])) {
                $this->throwError('Le format du numéro de fax est incorrect');
            }
            $customer->setFax($data['fax']);
        }

        $this->entityManager->persist($customer);

        $this->updateStats($stats, !$customerAlreadyExists);
    }

    private function importDeliveryEntity(array $data,
                                          array &$stats,
                                          Utilisateur $utilisateur,
                                          array &$refsToUpdate,
                                          array $colChampsLibres,
                                          $row): ?Demande {
        $users = $this->entityManager->getRepository(Utilisateur::class);
        $locations = $this->entityManager->getRepository(Emplacement::class);
        $types = $this->entityManager->getRepository(Type::class);
        $statusRepository = $this->entityManager->getRepository(Statut::class);
        $references = $this->entityManager->getRepository(ReferenceArticle::class);
        $articles = $this->entityManager->getRepository(Article::class);
        $categorieStatusRepository = $this->entityManager->getRepository(CategorieStatut::class);

        $requester = isset($data['requester']) && $data['requester'] ? $users->findOneBy(['username' => $data['requester']]) : $utilisateur;
        $destination = $data['destination'] ? $locations->findOneBy(['label' => $data['destination']]) : null;
        $categorieStatus = $categorieStatusRepository->findOneBy(["nom" => CategorieStatut::DEM_LIVRAISON]);
        $availableStatuses = Stream::from($statusRepository->findAvailableStatuesForDeliveryImport($categorieStatus))
            ->flatten()
            ->map(fn($status) => strtolower($status))
            ->values();

        $type = $data['type'] ? $types->findOneByCategoryLabelAndLabel(CategoryType::DEMANDE_LIVRAISON, $data['type']) : null;
        $status = $data['status'] ? $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_LIVRAISON, $data['status']) : null;
        $commentaire = $data['commentaire'] ?? null;
        $articleReference = $data['articleReference'] ? $references->findOneBy(['reference' => $data['articleReference']]) : null;
        $article = $data['articleCode'] ?? null;
        $quantityDelivery = $data['quantityDelivery'] ?? null;

        $showTargetLocationPicking = $this->entityManager->getRepository(Setting::class)->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION);
        $targetLocationPicking = null;
        if($showTargetLocationPicking) {
            if(isset($data['targetLocationPicking'])) {
                $targetLocationPickingStr = $data['targetLocationPicking'];
                $targetLocationPicking = $locations->findOneBy(['label' => $targetLocationPickingStr]);
                if(!$targetLocationPicking) {
                    $this->throwError("L'emplacement cible picking $targetLocationPickingStr n'existe pas.");
                }
            }
        }

        if (!$requester) {
            $this->throwError('Demandeur inconnu.');
        }

        if (!$destination) {
            $this->throwError('Destination inconnue.');
        } else if ($type && !$destination->getAllowedDeliveryTypes()->contains($type)) {
            $this->throwError('Type non autorisé sur l\'emplacement fourni.');
        }
        $deliveryKey = $requester->getId() . '-' . $destination->getId();
        $newEntity = !isset($this->cache['deliveries'][$deliveryKey]);
        if (!$newEntity) {
            $request = $this->cache['deliveries'][$deliveryKey];
            $request = $this->entityManager->getRepository(Demande::class)->find($request->getId());
            $this->cache['deliveries'][$deliveryKey] = $request;
        }
        $request = $newEntity ? new Demande() : $this->cache['deliveries'][$deliveryKey];

        if (!$type) {
            $this->throwError('Type inconnu.');
        } else if (!$request->getType()) {
            $request->setType($type);
        }

        if (!in_array(strtolower($data['status']), $availableStatuses)) {
            $this->throwError('Statut inconnu (valeurs possibles : brouillon, à traiter).');
        } else if (!$request->getStatut()) {
            $request->setStatut($status);
        }

        if (!$quantityDelivery || !is_numeric($quantityDelivery)) {
            $this->throwError('Quantité fournie non valide.');
        }

        if (!$articleReference || $articleReference->getStatut()?->getCode() === ReferenceArticle::STATUT_INACTIF) {
            $this->throwError('Article de référence inconnu ou inactif.');
        } else {
            if ($article && $articleReference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_ARTICLE) {
                $article = $articles->findOneBy(['barCode' => $article]);
                if ($article) {
                    if ($article->getQuantite() >= intval($quantityDelivery)) {
                        $existing = Stream::from($request->getArticleLines())
                            ->some(fn(DeliveryRequestArticleLine $line) => $line->getArticle()->getId() === $article->getId());
                        if (!$existing) {
                            $line = $this->demandeLivraisonService->createArticleLine($article, $request, [
                                'quantityToPick' => intval($quantityDelivery),
                                'targetLocationPicking' => $targetLocationPicking,
                            ]);
                            $this->entityManager->persist($line);

                            if (!$request->getPreparations()->isEmpty()) {
                                $preparation = $request->getPreparations()->first();
                                $article->setStatut($statusRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_TRANSIT));
                                $ligneArticlePreparation = $line->createPreparationOrderLine();
                                $ligneArticlePreparation
                                    ->setPreparation($preparation)
                                    ->setPickedQuantity($line->getPickedQuantity());
                                $this->entityManager->persist($ligneArticlePreparation);
                            }
                        } else {
                            $barcode = $article->getBarCode();
                            $this->throwError("Article déjà présent dans la demande. ($barcode)");
                        }
                    } else {
                        $quantity = $article->getQuantite();
                        $this->throwError("Quantité superieure à celle de l'article. ($quantity)");
                    }
                } else {
                    $this->throwError('Article inconnu.');
                }
            } else if ($articleReference->getQuantiteDisponible() >= intval($quantityDelivery)) {
                $existing = Stream::from($request->getReferenceLines())
                    ->some(fn(DeliveryRequestReferenceLine $line) => $line->getReference()->getId() === $articleReference->getId());
                if (!$existing) {
                    $line = new DeliveryRequestReferenceLine();
                    $line
                        ->setReference($articleReference)
                        ->setQuantityToPick($quantityDelivery)
                        ->setTargetLocationPicking($targetLocationPicking);
                    $this->entityManager->persist($line);
                    $request->addReferenceLine($line);
                    if (!$request->getPreparations()->isEmpty()) {
                        $preparation = $request->getPreparations()->first();
                        $lignesArticlePreparation = new PreparationOrderReferenceLine();
                        $lignesArticlePreparation
                            ->setPickedQuantity($line->getPickedQuantity())
                            ->setQuantityToPick($line->getQuantityToPick())
                            ->setReference($articleReference)
                            ->setTargetLocationPicking($targetLocationPicking);
                        $this->entityManager->persist($lignesArticlePreparation);
                        if ($articleReference->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                            $articleReference->setQuantiteReservee(($articleReference->getQuantiteReservee() ?? 0) + $line->getQuantityToPick());
                        } else {
                            $refsToUpdate[] = $articleReference;
                        }
                        $preparation->addReferenceLine($lignesArticlePreparation);
                    }
                } else {
                    $reference = $articleReference->getReference();
                    $this->throwError("Référence déjà présente dans la demande. ($reference)");
                }
            } else {
                $quantity = $articleReference->getQuantiteDisponible();
                $this->throwError("Quantité superieure à celle de l'article de référence. ($quantity)");
            }
        }

        if (!$request->getCommentaire()) {
            $request->setCommentaire(StringHelper::cleanedComment($commentaire));
        }

        $number = $this->uniqueNumberService->create(
            $this->entityManager,
            Demande::NUMBER_PREFIX,
            Demande::class,
            UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT
        );

        $request
            ->setCreatedAt(new DateTime('now', new \DateTimeZone('Europe/Paris')))
            ->setUtilisateur($requester)
            ->setDestination($destination)
            ->setNumero($number);

        $this->entityManager->persist($request);

        if ($request->getStatut()->getCode() === Demande::STATUT_A_TRAITER && $newEntity) {
            $response = $this->demandeLivraisonService->validateDLAfterCheck($this->entityManager, $request, true, false, false);
            if (!$response['success']) {
                $this->throwError($response['msg']);
            }
        }

        $this->checkAndSetChampsLibres($colChampsLibres, $request, $newEntity, $row);

        $this->updateStats($stats, $newEntity);

        return $request;
    }

    private function importLocationEntity(array $data, array &$stats)
    {
        $locationRepository = $this->entityManager->getRepository(Emplacement::class);
        $natureRepository = $this->entityManager->getRepository(Nature::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);
        $zoneRepository = $this->entityManager->getRepository(Zone::class);

        $isNewEntity = false;

        /** @var Emplacement $location */
        $location = $locationRepository->findOneBy(['label' => $data['name']]);

        if (!$location) {
            $location = new Emplacement();
            $isNewEntity = true;
        }

        if ($isNewEntity) {
            if (isset($data['name'])) {
                if ((strlen($data['name'])) > 24) {
                    $this->throwError("La valeur saisie pour le champ nom ne doit pas dépasser 24 caractères");
                } elseif (!preg_match('/' . SettingsService::CHARACTER_VALID_REGEX . '/', $data['name'])) {
                    $this->throwError("Le champ nom ne doit pas contenir de caractères spéciaux");
                } else {
                    $location->setLabel($data['name']);
                }
            } else {
                $this->throwError("Le champ nom est obligatoire lors de la création d'un emplacement");
            }

            if (isset($data['description'])) {
                if ((strlen($data['description'])) > 255) {
                    $this->throwError("La valeur saisie pour le champ description ne doit pas dépasser 255 caractères");
                } else {
                    $location->setDescription($data['description']);
                }
            } else {
                $this->throwError("Le champ description est obligatoire lors de la création d'un emplacement");
            }
        } else {
            if (isset($data['description'])) {
                if ((strlen($data['description'])) > 255) {
                    $this->throwError("La valeur saisie pour le champ description ne doit pas dépasser 255 caractères");
                }
            }
        }
        if (isset($data['dateMaxTime'])) {
            if (preg_match("/^\d+:[0-5]\d$/", $data['dateMaxTime'])) {
                $location->setDateMaxTime($data['dateMaxTime']);
            } else {
                $this->throwError("Le champ Délais traça HH:MM ne respecte pas le bon format");
            }
        }

        if (isset($data['allowedPackNatures'])) {
            $elements = Stream::explode([";", ","], $data['allowedPackNatures'])->toArray();
            $natures = $natureRepository->findBy(['label' => $elements]);
            $natureLabels = Stream::from($natures)
                ->map(fn(Nature $nature) => $this->formatService->nature($nature))
                ->toArray();

            $diff = Stream::diff($elements, $natureLabels, true);
            if (!$diff->isEmpty()) {
                $this->throwError("Les natures suivantes n'existent pas : {$diff->join(", ")}");
            } else {
                $location->setAllowedNatures($natures);
            }
        }

        if (isset($data['allowedDeliveryTypes'])) {
            $elements = Stream::explode([";", ","], $data['allowedDeliveryTypes'])->toArray();
            $allowedDeliveryTypes = $typeRepository->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], $elements);
            $allowedDeliveryTypesLabels = Stream::from($allowedDeliveryTypes)
                ->map(fn(Type $type) => $type->getLabel())
                ->toArray();

            $diff = Stream::diff($elements, $allowedDeliveryTypesLabels, true);
            if (!$diff->isEmpty()) {
                $this->throwError("Les types de demandes de livraison suivants n'existent pas : {$diff->join(", ")}");
            } else {
                $location->setAllowedDeliveryTypes($typeRepository->findBy(['label' => $elements]));
            }
        }

        if (isset($data['allowedCollectTypes'])) {
            $elements =  Stream::explode([";", ","], $data['allowedCollectTypes'])->toArray();
            $allowedCollectTypes = $typeRepository->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_COLLECTE], $elements);
            $allowedCollectTypesLabels = Stream::from($allowedCollectTypes)
                ->map(fn(Type $type) => $type->getLabel())
                ->toArray();

            $diff = Stream::diff($elements, $allowedCollectTypesLabels, true);
            if (!$diff->isEmpty()) {
                $this->throwError("Les types de demandes de collectes suivants n'existent pas : {$diff->join(", ")}");
            } else {
                $location->setAllowedCollectTypes($typeRepository->findBy(['label' => $elements]));
            }
        }

        if (!empty($data['isDeliveryPoint'])) {
            $location->setIsDeliveryPoint(
                filter_var($data['isDeliveryPoint'], FILTER_VALIDATE_BOOLEAN)
                || strtolower($data['isDeliveryPoint']) === "oui"
            );
        }

        if (!empty($data['isOngoingVisibleOnMobile'])) {
            $location->setIsOngoingVisibleOnMobile(
                filter_var($data['isOngoingVisibleOnMobile'], FILTER_VALIDATE_BOOLEAN)
                || strtolower($data['isOngoingVisibleOnMobile']) === "oui"
            );
        }

        if (!empty($data['signatories'])) {
            $signatoryUsernames = Stream::explode(',', $data['signatories'])
                ->filter()
                ->map(fn(string $id) => trim($id))
                ->toArray();
            $signatories = $userRepository->findBy(['username' => $signatoryUsernames]);
            $location->setSignatories($signatories);
        }

        if (!empty($data['email'])) {
            if(!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $this->throwError('Le format de l\'adresse email est incorrect');
            }
            $location->setEmail($data['email']);
        }

        if (isset($data['isActive'])) {
            $value = strtolower($data['isActive']);
            if ($value !== 'oui' && $value !== 'non') {
                $this->throwError('La valeur saisie pour Actif est invalide (autorisé : "oui" ou "non")');
            }
            else {
                $location->setIsActive($value === 'oui');
            }
        }

        $this->treatLocationZone($data, $location, $zoneRepository);

        $this->entityManager->persist($location);

        $this->updateStats($stats, $isNewEntity);

        return $location;
    }

    private function importProjectEntity(array $data, array &$stats) {
        $projectAlreadyExists = $this->entityManager->getRepository(Project::class)->findOneBy(['code' => $data['code']]);
        $project = $projectAlreadyExists ?? new Project();

        if (!$projectAlreadyExists && isset($data['code'])) {
            if ((strlen($data['code'])) > 15) {
                $this->throwError("La valeur saisie pour le code ne doit pas dépasser 15 caractères");
            } else {
                $project->setCode($data['code']);
            }
        }

        if (isset($data['description'])) {
            if ((strlen($data['description'])) > 255) {
                $this->throwError("La valeur saisie pour le champ description ne doit pas dépasser 255 caractères");
            } else {
                $project->setDescription($data['description']);
            }
        }

        if (isset($data['projectManager'])) {
            $projectManager = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['username' => $data['projectManager']]);

            if (!isset($projectManager)) {
                $this->throwError('Aucun utilisateur ne correspond au nom d\'utilisateur saisi dans la colonne Chef de projet');
            } else {
                $project->setProjectManager($projectManager);
            }
        }

        if (isset($data['isActive'])) {
            $value = strtolower($data['isActive']);
            if ($value !== 'oui' && $value !== 'non') {
                $this->throwError('La valeur saisie pour Actif est invalide (autorisé : "oui" ou "non")');
            }
            else {
                $project->setActive($data['isActive']);
            }
        }

        $this->entityManager->persist($project);

        $this->updateStats($stats, !$projectAlreadyExists);
    }

    private function importRefLocationEntity(array $data, array &$stats): void {
        $refLocationAlreadyExists = $this->entityManager->getRepository(StorageRule::class)->findOneByReferenceAndLocation($data['reference'], $data['location']);
        $refLocation = $refLocationAlreadyExists ?? new StorageRule();

        if (!$refLocationAlreadyExists && isset($data['reference'])) {
            $reference = $this->entityManager->getRepository(ReferenceArticle::class)->findOneBy(['reference' => $data['reference']]);
            if ($reference) {
                $refLocation->setReferenceArticle($reference);
            } else {
                $this->throwError("La référence saisie n'existe pas.");
            }
        }

        if (!$refLocationAlreadyExists && isset($data['location'])) {
            $location = $this->entityManager->getRepository(Emplacement::class)->findOneBy(['label' => $data['location']]);
            if ($location) {
                $refLocation->setLocation($location);
            } else {
                $this->throwError("L'emplacement saisi n'existe pas.");
            }
        }

        if (isset($data['securityQuantity'])) {
            if (!is_numeric($data['securityQuantity'])) {
                $this->throwError('La quantité de sécurité doit être un nombre.');
            } else {
                $refLocation->setSecurityQuantity($data['securityQuantity']);
            }
        }

        if (isset($data['conditioningQuantity'])) {
            if (!is_numeric($data['conditioningQuantity'])) {
                $this->throwError('La quantité de conditionnement doit être un nombre.');
            } else {
                $refLocation->setConditioningQuantity($data['conditioningQuantity']);
            }
        }

        $this->entityManager->persist($refLocation);

        $this->updateStats($stats, !$refLocationAlreadyExists);
    }

    private function checkAndSetChampsLibres(array $colChampsLibres,
                                                   $freeFieldEntity,
                                             bool $isNewEntity,
                                             array $row)
    {
        $champLibreRepository = $this->entityManager->getRepository(FreeField::class);
        $missingCL = [];

        $categoryCL = $freeFieldEntity instanceof ReferenceArticle
            ? CategorieCL::REFERENCE_ARTICLE
            : ($freeFieldEntity instanceof Article
                ? CategorieCL::ARTICLE
                : CategorieCL::DEMANDE_LIVRAISON);
        if ($freeFieldEntity->getType() && $freeFieldEntity->getType()->getId()) {
            $mandatoryCLs = $champLibreRepository->getMandatoryByTypeAndCategorieCLLabel($freeFieldEntity->getType(), $categoryCL, $isNewEntity);
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
                ? 'Les champs ' . join(', ', $missingCL) . ' sont obligatoires'
                : 'Le champ ' . $missingCL[0] . ' est obligatoire';
            $message .= ' à la ' . ($isNewEntity ? 'création.' : 'modification.');
            $this->throwError($message);
        }

        $freeFieldsToInsert = $freeFieldEntity->getFreeFields();

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

        $freeFieldEntity->setFreeFields($freeFieldsToInsert);
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
            $this->entityManager->persist($mvtStock);
        }
    }

    private function checkAndCreateProvider(string $ref)
    {
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $provider = $fournisseurRepository->findOneBy(['codeReference' => $ref]);

        if (empty($provider)) {
            $provider = new Fournisseur();
            $provider
                ->setCodeReference($ref)
                ->setNom($ref);
            $this->entityManager->persist($provider);
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
            $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
            $location = $emplacementRepository->findOneBy(['label' => $data['emplacement']]);
            if (empty($location)) {
                $location = $this->emplacementDataService->persistLocation([
                    "label" => $data['emplacement'],
                    "isActive" => true,
                    "isDeliveryPoint" => false,
                ], $this->entityManager);
            }
            $articleOrRef->setEmplacement($location);
        }
    }

    private function checkAndCreateArticleFournisseur(?string $articleFournisseurReference,
                                                      ?string $fournisseurReference,
                                                      ?ReferenceArticle $referenceArticle): ?ArticleFournisseur
    {
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
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
                    'label' => ($referenceArticle->getLibelle() . ' / ' . $fournisseur->getNom()),
                ]);
            } else {
                // on a réussi à trouver un article fournisseur
                // vérif que l'article fournisseur correspond au couple référence article / fournisseur
                if (!empty($fournisseurReference)) {
                    $fournisseur = $this->entityManager->getRepository(Fournisseur::class)->findOneBy(['codeReference' => $fournisseurReference]);

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
                    'fournisseur' => $fournisseur,
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
        $this->entityManager->clear();
        $this->currentImport = $this->entityManager->find(Import::class, $this->currentImport->getId());
    }

    public function createPreselection(array $headers, array $fieldsToCheck, ?array $sourceColumnToField) {
        $preselection = [];
        foreach ($headers as $headerIndex => $header) {
            $closestIndex = null;
            $closestDistance = PHP_INT_MAX;

            if (empty($sourceColumnToField)) {
                foreach ($fieldsToCheck as $fieldIndex => $field) {
                    preg_match("/(.+)\(.+\)/", $field, $fieldMatches);
                    $cleanedField = empty($fieldMatches)
                        ? $field
                        : trim($fieldMatches[1]);
                    preg_match("/(.+)\(.+\)/", $header, $headerMatches);
                    $cleanedHeader = empty($headerMatches)
                        ? $header
                        : trim($headerMatches[1]);
                    $distance = StringHelper::levenshtein($cleanedHeader, $cleanedField ?? '');
                    if ($distance < 5 && $distance < $closestDistance) {
                        $closestIndex = $fieldIndex;
                        $closestDistance = $distance;
                    }
                }

                if (isset($closestIndex)) {
                    $preselection[$header] = $fieldsToCheck[$closestIndex];
                    unset($fieldsToCheck[$closestIndex]);
                }
            }
            else {
                if (!empty($sourceColumnToField[$headerIndex])) {
                    $preselection[$header] = $sourceColumnToField[$headerIndex];
                }
            }
        }
        return $preselection;
    }

    public function getFieldsToAssociate(EntityManagerInterface $entityManager,
                                         string $entityCode): array {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $fieldsToAssociate = Stream::from(self::FIELDS_TO_ASSOCIATE[$entityCode] ?? []);

        if ($entityCode === Import::ENTITY_DELIVERY) {
            $showTargetLocationPicking = $settingRepository->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION);
            if (!$showTargetLocationPicking) {
                $fieldsToAssociate = $fieldsToAssociate->filter(fn(string $key) => ($key !== "targetLocationPicking"));
            }
        }

        $fieldsToAssociate = $fieldsToAssociate
            ->keymap(fn(string $key) => [
                $key,
                Import::FIELDS_ENTITY[$entityCode][$key]
                    ?? Import::FIELDS_ENTITY['default'][$key]
                    ?? $key,
            ])
            ->map(fn(string|array $field) => is_array($field) ? $this->translationService->translate(...$field) : $field)
            ->toArray();

        $categoryCLByEntity = [
            Import::ENTITY_ART => CategorieCL::ARTICLE,
            Import::ENTITY_REF => CategorieCL::REFERENCE_ARTICLE,
        ];

        $categoryCL = $categoryCLByEntity[$entityCode] ?? null;

        if (isset($categoryCL)) {
            $freeFields = $freeFieldRepository->getLabelAndIdByCategory($categoryCL);

            foreach ($freeFields as $freeField) {
                $fieldsToAssociate[$freeField['id']] = $freeField['value'];
            }
        }

        return $fieldsToAssociate;
    }

    public function resetCache(): void {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $associatedDocumentTypesStr = $settingRepository->getOneParamByLabel(Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);
        $associatedDocumentTypes = $associatedDocumentTypesStr
            ? Stream::explode(',', $associatedDocumentTypesStr)
                ->filter()
                ->toArray()
            : [];

        $this->cache = [];
        $this->importCache = [
            Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES => $associatedDocumentTypes,
        ];
    }

    private function treatLocationZone(Array $data, Emplacement $location, ZoneRepository $zoneRepository): void {
        if (isset($data['zone'])) {
            $zone = $zoneRepository->findOneBy(['name' => trim($data['zone'])]);
            if ($zone) {
                $location->setZone($zone);
            } else {
                $this->throwError('La zone ' . $data['zone'] . ' n\'existe pas dans la base de données');
            }
        } else {
            if (!isset($this->cache['totalZone'])) {
                $zoneRepository = $this->entityManager->getRepository(Zone::class);
                $this->cache['totalZone'] = $zoneRepository->count([]);
            }
            if ($this->cache['totalZone'] === 0) {
                $this->throwError("Aucune zone existante. Veuillez créer au moins une zone");
            } else if ($this->cache['totalZone'] === 1 ) {
                $zone = $zoneRepository->findOneBy([]);
                $location->setZone($zone);
            } else {
                $this->throwError("Le champ zone doit être renseigné");
            }
        }
    }

    private function eraseGlobalDataBefore(): void {
        if ($this->currentImport->isEraseData()) {
            switch ($this->currentImport->getEntity()) {
                case Import::ENTITY_REF_LOCATION:
                    $storageRuleRepository = $this->entityManager->getRepository(StorageRule::class);
                    $storageRuleRepository->clearTable();
                    break;
                default:
                    break;
            }
        }
    }

    private function eraseGlobalDataAfter(): void {
        if ($this->currentImport->isEraseData()) {
            switch ($this->currentImport->getEntity()) {
                case Import::ENTITY_ART_FOU:
                    if (!empty($this->cache["resetSupplierArticles"]['supplierArticles'])
                        && !empty($this->cache["resetSupplierArticles"]['referenceArticles'])) {
                        $supplierArticleRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
                        $supplierArticleRepository->deleteSupplierArticles(
                            $this->cache["resetSupplierArticles"]['supplierArticles'],
                            $this->cache["resetSupplierArticles"]['referenceArticles']
                        );
                    }
                    break;
                default:
                    break;
            }
        }
    }

}
