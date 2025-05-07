<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\Attachment;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\Customer;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\FreeField\FreeField;
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
use App\Entity\ScheduledTask\Import;
use App\Entity\ScheduledTask\ScheduleRule;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Entity\Zone;
use App\Exceptions\FTPException;
use App\Exceptions\ImportException;
use App\Service\ProductionRequest\ProductionRequestService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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


    private const STATISTICS_CREATIONS = "creations";
    private const STATISTICS_UPDATES = "updates";
    private const STATISTICS_ERRORS = "errors";

    public const FIELDS_TO_ASSOCIATE = [
        Import::ENTITY_ART => [
            "barCode",
            "label",
            "referenceReference",
            "articleFournisseurReference",
            "fournisseurReference",
            "commentaire",
            "emplacement",
            "quantite",
            "stockEntryDate",
            "expiryDate",
            "dateLastInventory",
            "batch",
            "prixUnitaire",
            "rfidTag",
        ],
        Import::ENTITY_REF => [
            "catInv",
            "commentaire",
            "dateLastInventory",
            "emplacement",
            "libelle",
            "prixUnitaire",
            "quantiteStock",
            "buyer",
            "reference",
            "limitWarning",
            "limitSecurity",
            "statut",
            "needsMobileSync",
            "type",
            "typeQuantite",
            "stockManagement",
            "managers",
            "visibilityGroups",
            "outFormatEquipment",
            "manufacturerCode",
            "volume",
            "weight",
            "associatedDocumentTypes",
            "dangerousGoods",
            "onuCode",
            "productClass",
            "supplierName",
            "supplierCode",
            "supplierArticleReference",
            "supplierArticleLabel",
        ],
        Import::ENTITY_FOU => [
            'nom',
            'codeReference',
            'possibleCustoms',
            FixedFieldEnum::urgent->name,
            FixedFieldEnum::address->name,
            FixedFieldEnum::phoneNumber->name,
            FixedFieldEnum::email->name,
            FixedFieldEnum::receiver->name,
        ],
        Import::ENTITY_RECEPTION => [
            "orderNumber",
            "expectedDate",
            "orderDate",
            "référence",
            "storageLocation",
            "location",
            "fournisseur",
            "transporteur",
            "commentaire",
            "manualUrgent",
            "anomalie",
            "quantité à recevoir",
        ],
        Import::ENTITY_ART_FOU => [
            "label",
            "reference",
            "referenceReference",
            "fournisseurReference",
        ],
        Import::ENTITY_USER => [
            "role",
            "username",
            "email",
            "secondaryEmail",
            "lastEmail",
            "mobileLoginKey",
            "deliveryTypes",
            "dispatchTypes",
            "handlingTypes",
            "dropzone",
            "visibilityGroup",
            "status",
            'signatoryCode',
            "address",
            "phone",
            "deliverer",
        ],
        Import::ENTITY_DELIVERY => [
            "requester",
            "destination",
            "type",
            "status",
            "commentaire",
            "articleReference",
            "quantityDelivery",
            "articleCode",
            "targetLocationPicking",
        ],
        Import::ENTITY_LOCATION => [
            FixedFieldEnum::name->name,
            FixedFieldEnum::description->name,
            FixedFieldEnum::isDeliveryPoint->name,
            FixedFieldEnum::isOngoingVisibleOnMobile->name,
            FixedFieldEnum::maximumTrackingDelay->name,
            FixedFieldEnum::status->name,
            FixedFieldEnum::allowedNatures->name,
            FixedFieldEnum::allowedTemperatures->name,
            FixedFieldEnum::signatories->name,
            FixedFieldEnum::email->name,
            FixedFieldEnum::zone->name,
            FixedFieldEnum::managers->name,
            FixedFieldEnum::sendEmailToManagers->name,
            FixedFieldEnum::allowedDeliveryTypes->name,
            FixedFieldEnum::allowedCollectTypes->name,
            FixedFieldEnum::startTrackingTimerOnPicking->name,
            FixedFieldEnum::stopTrackingTimerOnDrop->name,
            FIxedFieldEnum::pauseTrackingTimerOnDrop->name,
            FixedFieldEnum::newNatureOnPick->name,
            FixedFieldEnum::newNatureOnDrop->name,
            'newNatureOnPickEnabled',
            'newNatureOnDropEnabled'
        ],
        Import::ENTITY_CUSTOMER => [
            "name",
            "address",
            "recipient",
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
        Import::ENTITY_PRODUCTION => [
            FixedFieldEnum::createdBy->name,
            FixedFieldEnum::type->name,
            FixedFieldEnum::status->name,
            FixedFieldEnum::expectedAt->name,
            FixedFieldEnum::dropLocation->name,
            FixedFieldEnum::destinationLocation->name,
            FixedFieldEnum::lineCount->name,
            FixedFieldEnum::manufacturingOrderNumber->name,
            FixedFieldEnum::productArticleCode->name,
            FixedFieldEnum::quantity->name,
            FixedFieldEnum::emergency->name,
            FixedFieldEnum::projectNumber->name,
            FixedFieldEnum::comment->name,
        ],
        Import::ENTITY_DISPATCH => [
            FixedFieldEnum::type->name,
            FixedFieldEnum::dropLocation->name,
            FixedFieldEnum::pickLocation->name,
            FixedFieldEnum::orderNumber->name,
            FixedFieldEnum::carrierTrackingNumber->name,
            FixedFieldEnum::destination->name,
            FixedFieldEnum::carrier->name,
            FixedFieldEnum::requester->name,
            FixedFieldEnum::receivers->name,
            FixedFieldEnum::emergency->name,
            FixedFieldEnum::businessUnit->name,
            FixedFieldEnum::projectNumber->name,
            FixedFieldEnum::comment->name,
            FixedFieldEnum::emails->name,
            FixedFieldEnum::customerName->name,
            FixedFieldEnum::customerPhone->name,
            FixedFieldEnum::customerRecipient->name,
            FixedFieldEnum::customerAddress->name,
        ],
    ];

    private Import $currentImport;

    private array $importStatistics = [];

    private array $scalarCache = [];

    private array $entityCache = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmplacementDataService $emplacementDataService,
        private SettingsService $settingService,
        private Twig_Environment $templating,
        private ArticleDataService $articleDataService,
        private RefArticleDataService $refArticleDataService,
        private MouvementStockService $mouvementStockService,
        private LoggerInterface $logger,
        private ExceptionLoggerService $exceptionLoggerService,
        private AttachmentService $attachmentService,
        private ReceptionService $receptionService,
        private DeliveryRequestService $demandeLivraisonService,
        private ArticleFournisseurService $articleFournisseurService,
        private UserService $userService,
        private UniqueNumberService $uniqueNumberService,
        private TranslationService $translationService,
        private FormatService $formatService,
        private LanguageService $languageService,
        private ReceptionLineService $receptionLineService,
        private UserPasswordHasherInterface $encoder,
        private FTPService $FTPService,
        private ScheduledTaskService $scheduledTaskService,
        private ProductionRequestService $productionRequestService,
        private DispatchService $dispatchService,
        private FreeFieldService $freeFieldService,
        private SettingsService $settingsService
    )
    {
        $this->entityManager->getConnection()->getConfiguration()->setMiddlewares([new Middleware(new NullLogger())]);
        $this->resetCache();
    }

    public function getDataForDatatable(Utilisateur $user, $params = null): array
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

    public function dataRowImport(Import $import): array
    {
        if ($import->getType()?->getLabel() === Type::LABEL_UNIQUE_IMPORT
            && $import->getStatus()?->getCode() === Import::STATUS_UPCOMING) {
            $information = htmlspecialchars(
                $import->isForced()
                    ? "L'import sera réalisé dans moins de 30 minutes."
                    : "L'import sera réalisé la nuit suivante."
            );
        }
        else {
            $information = false;
        }

        $frequencyToString = [
            ScheduleRule::ONCE => 'une fois',
            ScheduleRule::DAILY => 'chaque jour',
            ScheduleRule::WEEKLY => 'chaque semaine',
            ScheduleRule::MONTHLY => 'chaque mois',
            ScheduleRule::HOURLY => 'chaque heure'
        ];

        $lastErrorMessage = $import->getLastErrorMessage();
        $scheduleRule = $import->getScheduleRule();

        $nextExecution = $this->scheduledTaskService->calculateTaskNextExecution($import, new DateTime("now"));

        return [
            'id' => $import->getId(),
            "information" => $information
                ? " <span class='has-tooltip d-flex align-items-center'
                          title='{$information}'>
                        <i class='wii-icon wii-icon-info wii-icon-13px bg-black'></i>
                    </span>"
                : null,
            'lastErrorMessage' => $lastErrorMessage
                ? '<div class="d-flex">
                       <img src="/svg/urgence.svg"
                            alt="Erreur"
                            class="has-tooltip"
                            width="15px"
                            title="' . $lastErrorMessage . '">
                    </div>'
                : "",
            'createdAt' => $this->formatService->datetime($import->getCreateAt()),
            'startDate' => $this->formatService->datetime($import->getStartDate()),
            'endDate' => $this->formatService->datetime($import->getEndDate()),
            'frequency' => $frequencyToString[$scheduleRule?->getFrequency()] ?? '',
            'label' => $import->getLabel(),
            'newEntries' => $import->getNewEntries(),
            'updatedEntries' => $import->getUpdatedEntries(),
            'nbErrors' => $import->getNbErrors(),
            'status' => $this->formatService->status($import->getStatus()),
            'user' => $this->formatService->user($import->getUser()),
            'type' => $this->formatService->type($import->getType()),
            "nextExecution" => $this->formatService->datetime($nextExecution),
            'entity' => Import::ENTITY_LABEL[$import->getEntity()] ?? "Non défini",
            'actions' => $this->templating->render('settings/donnees/import/row.html.twig', [
                'import' => $import,
            ]),
        ];
    }

    public function treatImport(EntityManagerInterface $entityManager,
                                Import                 $import,
                                int                    $mode = self::IMPORT_MODE_PLAN): int
    {
        $this->currentImport = $import;
        $this->entityManager = $entityManager;
        $this->resetCache();
        $this->resetImportStatistics();

        $now = new DateTime('now');

        $importModeChosen = $mode;

        $statusRepository = $this->entityManager->getRepository(Statut::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $this->scalarCache['countReferenceArticleSyncNomade'] = $referenceArticleRepository->count(['needsMobileSync' => true]);

        // we check mode validity
        if (!in_array($mode, [self::IMPORT_MODE_RUN, self::IMPORT_MODE_FORCE_PLAN, self::IMPORT_MODE_PLAN])) {
            throw new Exception('Invalid import mode');
        }

        $file = $this->fopenImportFile();

        if($file) {
            $columnsToFields = $this->currentImport->getColumnToField();
            $matches = Stream::from($columnsToFields)
                ->filter()
                ->flip()
                ->toArray();

            $colChampsLibres = array_filter($matches, function ($elem) {
                return is_int($elem);
            }, ARRAY_FILTER_USE_KEY);
            $dataToCheck = $this->getDataToCheck($this->currentImport->getEntity(), $matches);

            $refToUpdate = [];

            [
                "rowCount" => $rowCount,
                "firstRows" => $firstRows,
                "headersLog" => $headersLog,
            ] = $this->extractDataFromCSVFiles($file);

            // le fichier fait moins de MAX_LINES_FLASH_IMPORT lignes
            $smallFile = ($rowCount <= self::MAX_LINES_FLASH_IMPORT);

            if (!$smallFile
                && ($mode !== self::IMPORT_MODE_RUN)) {
                if (!$this->currentImport->isFlash()
                    && !$this->currentImport->isForced()) {
                    $upcomingStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_UPCOMING);
                    $importForced = (
                        ($rowCount <= self::MAX_LINES_AUTO_FORCED_IMPORT)
                        || ($mode === self::IMPORT_MODE_FORCE_PLAN)
                    );
                    $importModeChosen = $importForced
                        ? self::IMPORT_MODE_FORCE_PLAN
                        : self::IMPORT_MODE_RUN;
                    $this->currentImport
                        ->setForced($importForced)
                        ->setStatus($upcomingStatus);
                    $this->entityManager->flush();
                } else {
                    $importModeChosen = self::IMPORT_MODE_NONE;
                }
            }
            else {
                $importModeChosen = self::IMPORT_MODE_RUN;
                if ($smallFile) {
                    $this->currentImport->setFlash(true);
                }

                if (empty($headersLog)
                    || empty($firstRows)) {
                    $this->currentImport->setLastErrorMessage("Le fichier source à importer était vide. Il doit comporter au moins deux lignes dont une ligne d'entête.");
                } else {
                    // les premières lignes <= MAX_LINES_AUTO_FORCED_IMPORT
                    $index = 0;

                    ['resource' => $logFile, 'fileName' => $logFileName] = $this->fopenLogFile();
                    $logAttachment = $this->persistLogAttachment($logFileName);

                    $this->attachmentService->putCSVLines($logFile, [$headersLog], $this->scalarCache["logFileMapper"]);

                    $this->currentImport->setLogFile($logAttachment);

                    if (!$this->currentImport->getStartDate()) {
                        $this->currentImport->setStartDate($now);
                    }

                    $this->entityManager->flush();

                    $this->eraseGlobalDataBefore();

                    foreach ($firstRows as $row) {
                        $logRow = $this->treatImportRow(
                            $row,
                            $headersLog,
                            $dataToCheck,
                            $colChampsLibres,
                            $refToUpdate,
                            false,
                            $index,
                        );
                        $index++;
                        $this->attachmentService->putCSVLines($logFile, [$logRow], $this->scalarCache["logFileMapper"]);
                    }

                    $this->clearEntityManagerAndRetrieveImport();
                    if (!$smallFile) {
                        while (($row = fgetcsv($file, 0, ';')) !== false) {
                            $logRow = $this->treatImportRow(
                                $row,
                                $headersLog,
                                $dataToCheck,
                                $colChampsLibres,
                                $refToUpdate,
                                ($index % self::NB_ROW_WITHOUT_CLEARING === 0),
                                $index,
                            );
                            $index++;
                            $this->attachmentService->putCSVLines($logFile, [$logRow], $this->scalarCache["logFileMapper"]);
                        }
                    }

                    $this->eraseGlobalDataAfter();
                    $this->entityManager->flush();

                    @fclose($logFile);

                    // mise à jour des quantités sur références par article
                    $this->refArticleDataService->updateRefArticleQuantities($this->entityManager, $refToUpdate);
                }

                $statusFinished = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED);
                if ($this->currentImport->getStatus()?->getCode() !== Import::STATUS_UPCOMING) {
                    $this->currentImport
                        ->setNewEntries($this->importStatistics[self::STATISTICS_CREATIONS])
                        ->setUpdatedEntries($this->importStatistics[self::STATISTICS_UPDATES])
                        ->setNbErrors($this->importStatistics[self::STATISTICS_ERRORS])
                        ->setStatus($statusFinished)
                        ->setForced(false)
                        ->setEndDate($now);
                    $this->entityManager->flush();
                }
            }

            $this->entityManager->flush();
            $this->cleanImportFile($file);
        }

        return $importModeChosen;
    }

    private function treatImportRow(array $row,
                                    array $headers,
                                          $dataToCheck,
                                          $colChampsLibres,
                                    array &$refToUpdate,
                                    bool  $needsUnitClear,
                                    int   $rowIndex,
                                    int   $retry = 0): array
    {
        try {
            $emptyCells = count(array_filter($row, fn(string $value) => $value === ""));
            if ($emptyCells !== count($row)) {
                $verifiedData = $this->checkFieldsAndFillArrayBeforeImporting($this->currentImport->getEntity(), $dataToCheck, $row, $headers);
                $data = array_map('trim', $verifiedData);

                $isCreation = null;

                switch ($this->currentImport->getEntity()) {
                    case Import::ENTITY_FOU:
                        $this->importFournisseurEntity($data, $isCreation);
                        break;
                    case Import::ENTITY_ART_FOU:
                        $this->importArticleFournisseurEntity($data, $isCreation);
                        break;
                    case Import::ENTITY_REF:
                        $this->importReferenceEntity($data, $colChampsLibres, $row, $dataToCheck, $isCreation);
                        break;
                    case Import::ENTITY_RECEPTION:
                        $this->importReceptionEntity($data, $this->currentImport->getUser(), $isCreation);
                        break;
                    case Import::ENTITY_ART:
                        $referenceArticle = $this->importArticleEntity($data, $colChampsLibres, $row, $rowIndex, $isCreation);
                        $refToUpdate[$referenceArticle->getId()] = $referenceArticle;
                        break;
                    case Import::ENTITY_USER:
                        $this->importUserEntity($data, $isCreation);
                        break;
                    case Import::ENTITY_DELIVERY:
                        $insertedDelivery = $this->importDeliveryEntity($data, $this->currentImport->getUser(), $refToUpdate, $colChampsLibres, $row, $isCreation);
                        break;
                    case Import::ENTITY_LOCATION:
                        $this->importLocationEntity($data, $isCreation);
                        break;
                    case Import::ENTITY_CUSTOMER:
                        $this->importCustomerEntity($data, $isCreation);
                        break;
                    case Import::ENTITY_PROJECT:
                        $this->importProjectEntity($data, $isCreation);
                        break;
                    case Import::ENTITY_REF_LOCATION:
                        $this->importRefLocationEntity($data, $isCreation);
                        break;
                    case Import::ENTITY_PRODUCTION:
                        $this->productionRequestService->importProductionRequest(
                            $this->entityManager,
                            $data,
                            $this->currentImport->getUser(),
                            $isCreation
                        );
                        break;
                    case Import::ENTITY_DISPATCH:
                        $this->dispatchService->importDispatch($this->entityManager, $data, $this->currentImport->getUser(), $colChampsLibres, $row, $isCreation);
                        break;
                }

                $this->entityManager->flush();
                if (!empty($insertedDelivery)) {
                    $this->entityCache['deliveries'][$insertedDelivery->getUtilisateur()->getId() . '-' . $insertedDelivery->getDestination()->getId()] = $insertedDelivery;
                }

                if ($isCreation !== null) {
                    $statisticKey = $isCreation ? self::STATISTICS_CREATIONS : self::STATISTICS_UPDATES;
                    $this->importStatistics[$statisticKey]++;
                }

                if ($needsUnitClear) {
                    $this->clearEntityManagerAndRetrieveImport();
                }
            }

            $message = 'OK';
        } catch (Throwable $throwable) {
            // On réinitialise l'entity manager car il a été fermé
            if (!$this->entityManager->isOpen()) {
                $this->entityManager = new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
                $this->entityManager->getConnection()->getConfiguration()->setMiddlewares([new Middleware(new NullLogger())]);
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
                $this->exceptionLoggerService->sendLog($throwable);
            }

            $this->importStatistics[self::STATISTICS_ERRORS]++;
        }
        if (!empty($message)) {
            $headersLength = count($headers);
            $rowLength = count($row);
            $placeholdersColumns = ($rowLength < $headersLength)
                ? array_fill(0, $headersLength - $rowLength, '')
                : [];
            $resRow = array_merge($row, $placeholdersColumns, [$message]);
        } else {
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

    private function fopenLogFile()
    {
        $fileName = uniqid() . '.csv';
        $completeFileName = $this->attachmentService->getAttachmentDirectory() . '/' . $fileName;
        return [
            'fileName' => $fileName,
            'resource' => fopen($completeFileName, 'w'),
        ];
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
            if (is_array($fieldName)) {
                $fieldName = $this->translationService->translate(...$fieldName);
            }

            if ($originalDataToCheck['value'] === null && $originalDataToCheck['needed']) {
                $message = "La colonne $fieldName est manquante.";
                throw new ImportException($message);
            } else if (empty($row[$originalDataToCheck['value']]) && $originalDataToCheck['needed']) {
                $columnIndex = $headers[$originalDataToCheck['value']];
                $message = "La valeur renseignée pour le champ $fieldName dans la colonne $columnIndex ne peut être vide.";
                throw new ImportException($message);
            } else if (isset($row[$originalDataToCheck['value']]) && strlen($row[$originalDataToCheck['value']])) {
                $data[$column] = $row[$originalDataToCheck['value']];
            }
        }
        return $data;
    }

    private function importFournisseurEntity(array $data, ?bool &$isCreation): void
    {
        if (!isset($data['codeReference'])) {
            throw new ImportException("Le code fournisseur est obligatoire");
        }

        $supplierRepository = $this->entityManager->getRepository(Fournisseur::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);
        $existingSupplier = $supplierRepository->findOneBy(['codeReference' => $data['codeReference']]);

        $supplier = $existingSupplier ?? new Fournisseur();

        if (!$supplier->getId()) {
            $supplier->setCodeReference($data['codeReference']);
        }

        $allowedValues = ['oui', 'non'];
        $possibleCustoms = isset($data["possibleCustoms"]) ? strtolower($data["possibleCustoms"]) : null;
        if (isset($data["possibleCustoms"]) && !in_array($possibleCustoms, $allowedValues)) {
            throw new ImportException("La valeur du champ Douane possible n'est pas correcte (oui ou non)");
        } else {
            $supplier->setPossibleCustoms($possibleCustoms === 'oui');
        }

        $urgent = isset($data["urgent"]) ? strtolower($data["urgent"]) : null;
        if (isset($data["urgent"]) && !in_array($urgent, $allowedValues)) {
            throw new ImportException("La valeur du champ Urgent n'est pas correcte (oui ou non)");
        } else {
            $supplier->setUrgent($urgent === 'oui');
        }

        if(isset($data["receiver"])) {
            // check if the user exists to create the relation
            $receiver = $userRepository->findOneBy(["username" => $data["receiver"]]);
            if(!$receiver) {
                throw new ImportException("L'utilisateur renseigné en tant que destinataire n'existe pas.");
            }
            $supplier->setReceiver($receiver);
        }

        $supplier
            ->setNom($data['nom'])
            ->setEmail($data['email'] ?? null)
            ->setPhoneNumber($data['phoneNumber'] ?? null)
            ->setAddress($data['address'] ?? null);

        $this->entityManager->persist($supplier);

        $isCreation = !$supplier->getId();
    }

    private function importArticleFournisseurEntity(array $data, ?bool &$isCreation): void
    {
        $newEntity = false;

        if (empty($data['reference'])) {
            throw new ImportException('La colonne référence ne doit pas être vide');
        }

        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $eraseData = $this->currentImport->isEraseData();

        if (!empty($data['referenceReference'])) {
            $refArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
            $refArticle = $refArticleRepository->findOneBy(['reference' => $data['referenceReference']]);
        }

        if (empty($refArticle)) {
            throw new ImportException("La valeur renseignée pour la référence de l'article de référence ne correspond à aucune référence connue.");
        }

        $supplierArticle = $articleFournisseurRepository->findOneBy(['reference' => $data['reference']]);
        if (empty($supplierArticle)) {
            $newEntity = true;
            $supplierArticle = new ArticleFournisseur();
            $supplierArticle->setReference(trim($data['reference']));
        }

        if (isset($data['label'])) {
            $supplierArticle->setLabel(trim($data['label']));
        }

        if (!empty($data['fournisseurReference'])) {
            $fournisseur = $this->entityManager->getRepository(Fournisseur::class)->findOneBy(['codeReference' => $data['fournisseurReference']]);
        }

        if (empty($fournisseur)) {
            throw new ImportException("La valeur renseignée pour le code du fournisseur ne correspond à aucun fournisseur connu.");
        }

        $supplierArticle
            ->setReferenceArticle($refArticle)
            ->setFournisseur($fournisseur)
            ->setVisible(true);

        $this->entityManager->persist($supplierArticle);

        if ($eraseData) {
            $this->entityCache["resetSupplierArticles"] = $this->entityCache["resetSupplierArticles"] ?? [
                "supplierArticles" => [],
                "referenceArticles" => [],
            ];

            $this->entityCache["resetSupplierArticles"]["supplierArticles"][] = $supplierArticle->getReference();
            $this->entityCache["resetSupplierArticles"]["referenceArticles"][] = $refArticle->getId();
        }

        $isCreation = $newEntity;
    }

    private function importReceptionEntity(array        $data,
                                           ?Utilisateur $user,
                                           ?bool        &$isCreation): void
    {
        $refArtRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        if (!isset($this->entityCache['receptions'])) {
            $this->entityCache['receptions'] = [];
        }

        if ($user) {
            $user = $userRepository->find($user->getId());
        }

        $uniqueReceptionConstraint = [
            'orderNumber' => $data['orderNumber'] ?? null,
            'expectedDate' => $data['expectedDate'] ?? null,
        ];

        $reception = $this->receptionService->getAlreadySavedReception(
            $this->entityManager,
            $this->entityCache['receptions'],
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
                $this->receptionService->setAlreadySavedReception($this->entityCache['receptions'], $uniqueReceptionConstraint, $reception);
            }
        }

        $newEntity = !isset($reception);
        try {
            if ($newEntity) {
                $reception = $this->receptionService->persistReception($this->entityManager, $user, $data, ['import' => true]);
                $this->receptionService->setAlreadySavedReception($this->entityCache['receptions'], $uniqueReceptionConstraint, $reception);
            } else {
                $this->receptionService->updateReception($this->entityManager, $reception, $data, [
                    'import' => true,
                    'update' => true,
                ]);
            }
        } catch (InvalidArgumentException $exception) {
            switch ($exception->getMessage()) {
                case ReceptionService::INVALID_EXPECTED_DATE:
                    throw new ImportException('La date attendue n\'est pas au bon format (dd/mm/yyyy)');
                case ReceptionService::INVALID_ORDER_DATE:
                    throw new ImportException('La date commande n\'est pas au bon format (dd/mm/yyyy)');
                case ReceptionService::INVALID_LOCATION:
                    throw new ImportException('Emplacement renseigné invalide');
                case ReceptionService::INVALID_STORAGE_LOCATION:
                    throw new ImportException('Emplacement de stockage renseigné invalide');
                case ReceptionService::INVALID_CARRIER:
                    throw new ImportException('Transporteur renseigné invalide');
                case ReceptionService::INVALID_PROVIDER:
                    throw new ImportException('Fournisseur renseigné invalide');
                default:
                    throw $exception;
            }
        }

        if (!empty($data['référence'])) {
            if (empty($uniqueReceptionConstraint['orderNumber'])) {
                throw new ImportException("Le numéro de commande doit être renseigné.");
            }

            $referenceArticle = $refArtRepository->findOneBy(['reference' => $data['référence']]);
            if (!$referenceArticle) {
                throw new ImportException('La référence article n\'existe pas.');
            }

            if (!isset($data['quantité à recevoir'])) {
                throw new ImportException('La quantité à recevoir doit être renseignée.');
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
            } else {
                throw new ImportException("La ligne de réception existe déjà pour cette référence et ce numéro de commande");
            }

            $this->entityManager->flush();
        }

        $isCreation = $newEntity;
    }

    private function importReferenceEntity(array $data,
                                           array $colChampsLibres,
                                           array $row,
                                           array $dataToCheck,
                                           ?bool &$isCreation)
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
                throw new ImportException('La valeur saisie pour le champ libellé ne doit pas dépasser 255 caractères');
            } else {
                $refArt->setLibelle($data['libelle']);
            }
        }

        if (isset($data['needsMobileSync'])) {
            $value = strtolower($data['needsMobileSync']);
            if ($value !== 'oui' && $value !== 'non') {
                throw new ImportException('La valeur saisie pour le champ synchronisation nomade est invalide (autorisé : "oui" ou "non")');
            } else {
                $neddsMobileSync = $value === 'oui';
                if ($neddsMobileSync && $this->scalarCache['countReferenceArticleSyncNomade'] > ReferenceArticle::MAX_NOMADE_SYNC) {
                    throw new ImportException('Le nombre maximum de synchronisations nomade a été atteint.');
                } else {
                    $this->scalarCache['countReferenceArticleSyncNomade']++;
                    $refArt->setNeedsMobileSync($neddsMobileSync);
                }
            }
        }

        if (isset($data['visibilityGroups'])) {
            $visibilityGroup = $visibilityGroupRepository->findOneBy(['label' => $data['visibilityGroups']]);
            if (!isset($visibilityGroup)) {
                throw new ImportException("Le groupe de visibilité {$data['visibilityGroups']} n'existe pas");
            }
            $refArt->setProperties(['visibilityGroup' => $visibilityGroup]);
        }

        if (isset($data['managers'])) {
            $usernames = Stream::explode([";", ","], $data["managers"])
                ->unique()
                ->map(fn(string $username) => trim($username))
                ->filter()
                ->toArray();

            $managers = !empty($usernames)
                ? $userRepository->findByUsernames($usernames)
                : [];
            foreach ($managers as $manager) {
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
        if (isset($data['dateLastInventory'])) {
            try {
                $refArt->setDateLastInventory(DateTime::createFromFormat('d/m/Y', $data['dateLastInventory']) ?: null);
            } catch (Exception $e) {
                throw new ImportException('La date de dernier inventaire doit être au format JJ/MM/AAAA.');
            }
        }
        if ($isNewEntity) {
            if (empty($data['typeQuantite'])
                || !in_array($data['typeQuantite'], [ReferenceArticle::QUANTITY_TYPE_REFERENCE, ReferenceArticle::QUANTITY_TYPE_ARTICLE])) {
                throw new ImportException('Le type de gestion de la référence est invalide (autorisé : "article" ou "reference")');
            }

            // interdiction de modifier le type quantité d'une réf existante
            $refArt->setTypeQuantite($data['typeQuantite']);
        }
        if (isset($data['prixUnitaire'])) {
            if (!is_numeric($data['prixUnitaire'])) {
                $message = 'Le prix unitaire doit être un nombre.';
                throw new ImportException($message);
            }
            $refArt->setPrixUnitaire($data['prixUnitaire']);
        }

        if (isset($dataToCheck["limitSecurity"]) && $dataToCheck["limitSecurity"]["value"] !== null) {
            $limitSecurity = $data['limitSecurity'] ?? null;
            if ($limitSecurity === "") {
                $refArt->setLimitSecurity(null);
            } else if ($limitSecurity !== null && !is_numeric($limitSecurity)) {
                $message = 'Le seuil de sécurité doit être un nombre.';
                throw new ImportException($message);
            } else {
                $refArt->setLimitSecurity($limitSecurity);
            }
        }

        if (isset($dataToCheck["limitWarning"]) && $dataToCheck["limitWarning"]["value"] !== null) {
            $limitWarning = $data['limitWarning'] ?? null;
            if ($limitWarning === "") {
                $refArt->setLimitWarning(null);
            } else if ($limitWarning !== null && !is_numeric($limitWarning)) {
                $message = 'Le seuil d\'alerte doit être un nombre. ';
                throw new ImportException($message);
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
                ->setBarCode($this->refArticleDataService->generateBarCode())
                ->setType($type);
        } else if (isset($data['type']) && $refArt->getType()?->getLabel() !== $data['type']) {
            throw new ImportException("La modification du type d'une référence n'est pas autorisée");
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
                throw new ImportException($message);
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
                throw new ImportException($message);
            } else {
                $refArt->setCategory($catInv);
            }
        }

        $this->entityManager->persist($refArt);

        // quantité
        if (isset($data['quantiteStock'])) {
            if (!is_numeric($data['quantiteStock'])) {
                $message = 'La quantité doit être un nombre.';
                throw new ImportException($message);
            } else if ($data['quantiteStock'] < 0) {
                $message = 'La quantité doit être positive.';
                throw new ImportException($message);
            } else if ($refArt->getTypeQuantite() === ReferenceArticle::QUANTITY_TYPE_REFERENCE) {
                if (isset($data['quantiteStock']) && $data['quantiteStock'] < $refArt->getQuantiteReservee()) {
                    $message = 'La quantité doit être supérieure à la quantité réservée (' . $refArt->getQuantiteReservee() . ').';
                    throw new ImportException($message);
                }
                $this->checkAndCreateMvtStock($refArt, $refArt->getQuantiteStock(), $data['quantiteStock'], $isNewEntity);
                $refArt->setQuantiteStock($data['quantiteStock']);
                $refArt->setQuantiteDisponible($refArt->getQuantiteStock() - $refArt->getQuantiteReservee());
            }
        }
        if (isset($data['dangerousGoods'])) {
            $dangerousGoods = (
                filter_var($data['dangerousGoods'], FILTER_VALIDATE_BOOLEAN)
                || in_array($data['dangerousGoods'], self::POSITIVE_ARRAY)
            );

            $refArt
                ->setDangerousGoods($dangerousGoods);
        }

        if (isset($data['onuCode'])) {
            $refArt->setOnuCode($data['onuCode'] ?: null);
        }
        if (isset($data['productClass'])) {
            $refArt->setProductClass($data['productClass'] ?: null);
        }

        if ($refArt->isDangerousGoods()
            && !$refArt->getOnuCode()) {
            throw new ImportException("Le code ONU est requis");
        }

        if ($refArt->isDangerousGoods()
            && !$refArt->getProductClass()) {
            throw new ImportException("La classe projet est requise");
        }

        $original = $refArt->getDescription() ?? [];

        $outFormatEquipmentData = isset($data['outFormatEquipment'])
            ? (int)(
                filter_var($data['outFormatEquipment'], FILTER_VALIDATE_BOOLEAN)
                || in_array($data['outFormatEquipment'], self::POSITIVE_ARRAY)
            )
            : null;

        $outFormatEquipment = $outFormatEquipmentData ?? $original['outFormatEquipment'] ?? null;
        $volume = $data['volume'] ?? $original['volume'] ?? null;
        $weight = $data['weight'] ?? $original['weight'] ?? null;
        $associatedDocumentTypesStr = $data['associatedDocumentTypes'] ?? null;
        $associatedDocumentTypes = $associatedDocumentTypesStr
            ? Stream::explode(',', $associatedDocumentTypesStr)
                ->filter()
            : Stream::from([]);

        if (!empty($volume) && !is_numeric($volume)) {
            throw new ImportException('Champ volume non valide.');
        }
        if (!empty($weight) && !is_numeric($weight)) {
            throw new ImportException('Champ poids non valide.');
        }

        $invalidAssociatedDocumentType = $associatedDocumentTypes
            ->find(fn(string $type) => !in_array($type, $this->scalarCache[Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES]));
        if (!empty($invalidAssociatedDocumentType)) {
            throw new ImportException("Le type de document n'est pas valide : $invalidAssociatedDocumentType");
        }

        $supplierStream = Stream::from(["supplierName", "supplierCode", "supplierArticleReference", "supplierArticleLabel"]);
        if ($supplierStream->some(static fn($field) => isset($data[$field]))) {

            $missingFields = $supplierStream->filter(static fn(string $field) => empty($data[$field]));
            if(!$missingFields->isEmpty()) {
                $joinedFields = $missingFields
                    ->map(static fn(string $field) => Import::FIELDS_ENTITY["default"][$field])
                    ->join(", ");

                $start = $missingFields->count() > 1
                    ? "Les champs $joinedFields sont"
                    : "Le champ $joinedFields est";

                throw new ImportException("$start requis pour pouvoir ajouter un article fournisseur sur la référence.");
            }

            $supplierArticleReference = $data["supplierArticleReference"] ?? null;
            $supplierCode = $data["supplierCode"] ?? null;
            $supplier = $this->checkAndCreateProvider($supplierCode, $data["supplierName"]);

            try {
                $this->articleFournisseurService->createArticleFournisseur([
                    "fournisseur" => $supplier,
                    "article-reference" => $refArt,
                    "label" => $data["supplierArticleLabel"],
                    "reference" => $supplierArticleReference,
                ], false, $this->entityManager);
            } catch (Throwable $throwable) {
                match ($throwable->getMessage()) {
                    ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS => throw new ImportException("La référence $supplierArticleReference existe déjà pour un article fournisseur."),
                    default => throw $throwable,
                };
            }
        }

        $description = [
            "outFormatEquipment" => $outFormatEquipment,
            "manufacturerCode" => $data['manufacturerCode'] ?? $original['manufacturerCode'] ?? null,
            "volume" => $volume,
            "weight" => $weight,
        ];
        $refArt
            ->setDescription($description);

        // champs libres
        $this->freeFieldService->manageImportFreeFields($this->entityManager, $colChampsLibres, $refArt, $isNewEntity, $row);

        $isCreation = $isNewEntity;
    }

    private function importArticleEntity(array $data,
                                         array $colChampsLibres,
                                         array $row,
                                         int   $rowIndex,
                                         ?bool &$isCreation): ReferenceArticle
    {
        if (!empty($data['barCode'])) {
            $articleRepository = $this->entityManager->getRepository(Article::class);
            $article = $articleRepository->findOneBy(['barCode' => $data['barCode']]);
            if (!$article) {
                throw new ImportException('Le code barre donné est invalide.');
            }
            $isNewEntity = false;
            $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
        } else {
            if (!empty($data['referenceReference'])) {
                $refArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
                $refArticle = $refArticleRepository->findOneBy(['reference' => $data['referenceReference']]);
                if (empty($refArticle)) {
                    $message = "La valeur renseignée pour la référence de l'article de référence ne correspond à aucune référence connue.";
                    throw new ImportException($message);
                }
            } else {
                $message = "Veuillez saisir la référence de l'article de référence.";
                throw new ImportException($message);
            }

            $article = new Article();
            $isNewEntity = true;
        }
        if (isset($data['label'])) {
            $article->setLabel($data['label']);
        }

        if (isset($data['prixUnitaire'])) {
            if (!is_numeric($data['prixUnitaire'])) {
                throw new ImportException('Le prix unitaire doit être un nombre.');
            }
            $article->setPrixUnitaire($data['prixUnitaire']);
        }

        if (isset($data['rfidTag'])) {
            $articleRepository = $this->entityManager->getRepository(Article::class);
            $rfidTag = $data['rfidTag'] ?: null;
            $existingArticle = $rfidTag
                ? $articleRepository->findOneBy(['RFIDtag' => $data['rfidTag']])
                : null;
            if ($existingArticle) {
                throw new ImportException("Le tag RFID $rfidTag est déjà utilisé.");
            }
            $article->setRFIDtag($rfidTag);
        }

        if (isset($data['batch'])) {
            $article->setBatch($data['batch']);
        }

        if (isset($data['expiryDate'])) {
            if (str_contains($data['expiryDate'], ' ')) {
                $date = DateTime::createFromFormat("d/m/Y H:i", $data['expiryDate']);
            } else {
                $date = DateTime::createFromFormat("d/m/Y", $data['expiryDate']);
            }

            $article->setExpiryDate($date);
        }

        if (isset($data['stockEntryDate'])) {
            if (str_contains($data['stockEntryDate'], ' ')) {
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
                ->setBarCode($this->articleDataService->generateBarcode())
                ->setConform(true);
        }

        $articleFournisseurReference = $data['articleFournisseurReference'] ?? null;
        if (!$refArticle && empty($articleFournisseurReference)) {
            throw new ImportException('La colonne référence article de référence ou la colonne référence article fournisseur doivent être renseignées.');
        }

        if (!$refArticle || !empty($articleFournisseurReference)) {
            try {
                $articleFournisseur = $this->checkAndCreateArticleFournisseur(
                    $data['articleFournisseurReference'] ?? null,
                    $data['fournisseurReference'] ?? null,
                    $refArticle
                );
                $article->setArticleFournisseur($articleFournisseur);
            } catch (Exception $exception) {
                if ($exception->getMessage() === ArticleFournisseurService::ERROR_REFERENCE_ALREADY_EXISTS) {
                    throw new ImportException('La référence article fournisseur existe déjà');
                } else {
                    throw $exception;
                }
            }
        } else {
            $articleFournisseur = $article->getArticleFournisseur();
        }

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
                throw new ImportException('La quantité doit être un nombre.');
            }
            $this->checkAndCreateMvtStock($article, $article->getQuantite(), $data['quantite'], $isNewEntity);
            $article->setQuantite($data['quantite']);
        }
        $this->entityManager->persist($article);
        // champs libres
        $this->freeFieldService->manageImportFreeFields($this->entityManager,$colChampsLibres, $article, $isNewEntity, $row);

        $isCreation = $isNewEntity;

        return $refArticle;
    }

    private function importUserEntity(array $data, ?bool &$isCreation): void
    {

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
        if ($role) {
            $user->setRole($role);
        } else {
            throw new ImportException("Le rôle {$data['role']} n'existe pas");
        }

        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }

        if (!isset($userAlreadyExists)) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ImportException('Le format de l\'adresse email est incorrect');
            }
            $user
                ->setEmail($data['email'])
                ->setPassword("");
        }

        if (isset($data['secondaryEmail']) && isset($data['lastEmail'])) {
            if (!filter_var($data['secondaryEmail'], FILTER_VALIDATE_EMAIL)
                && !filter_var($data['lastEmail'], FILTER_VALIDATE_EMAIL)) {
                throw new ImportException('Le format des adresses email 2 et 3 est incorrect');
            }
            $user->setSecondaryEmails([$data['secondaryEmail'], $data['lastEmail']]);
        } else if (isset($data['secondaryEmail'])) {
            if (!filter_var($data['secondaryEmail'], FILTER_VALIDATE_EMAIL)) {
                throw new ImportException('Le format de l\'adresse email 2 est incorrect');
            }
            $user->setSecondaryEmails([$data['secondaryEmail']]);
        } else if (isset($data['lastEmail'])) {
            if (!filter_var($data['lastEmail'], FILTER_VALIDATE_EMAIL)) {
                throw new ImportException('Le format de l\'adresse email 3 est incorrect');
            }
            $user->setSecondaryEmails([$data['lastEmail']]);
        }

        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        if (isset($data['mobileLoginKey'])) {
            $minMobileKeyLength = UserService::MIN_MOBILE_KEY_LENGTH;
            $maxMobileKeyLength = UserService::MAX_MOBILE_KEY_LENGTH;

            if (strlen($data['mobileLoginKey']) < UserService::MIN_MOBILE_KEY_LENGTH
                || strlen($data['mobileLoginKey']) > UserService::MAX_MOBILE_KEY_LENGTH) {
                throw new ImportException("La clé de connexion doit faire entre {$minMobileKeyLength} et {$maxMobileKeyLength} caractères");
            }

            $userWithExistingKey = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['mobileLoginKey' => $data['mobileLoginKey']]);
            if (!isset($userWithExistingKey) || $userWithExistingKey->getId() === $user->getId()) {
                $user->setMobileLoginKey($data['mobileLoginKey']);
            } else {
                throw new ImportException('Cette clé de connexion est déjà utilisée par un autre utilisateur');
            }
        } else if (!isset($userAlreadyExists)) {
            $mobileLoginKey = $this->userService->createUniqueMobileLoginKey($this->entityManager);
            $user->setMobileLoginKey($mobileLoginKey);
        }

        if (isset($data['address'])) {
            $user->setAddress($data['address']);
        }

        if (!empty($data['deliverer'])) {
            $value = strtolower($data['deliverer']);
            if ($value !== 'oui' && $value !== 'non') {
                throw new ImportException('La valeur saisie pour le champ Livreur est invalide (autorisé : "oui" ou "non")');
            } else {
                $user->setDeliverer($value === 'oui');
            }
        }

        if (isset($data['deliveryTypes'])) {
            $deliveryTypesRaw = array_map('trim', explode(',', $data['deliveryTypes']));
            $deliveryCategory = $this->entityManager->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_LIVRAISON]);
            $deliveryTypes = $this->entityManager->getRepository(Type::class)->findBy([
                'label' => $deliveryTypesRaw,
                'category' => $deliveryCategory,
            ]);

            $deliveryTypesLabel = Stream::from($deliveryTypes)->map(fn(Type $type) => $type->getLabel())->toArray();
            $invalidTypes = Stream::diff($deliveryTypesLabel, $deliveryTypesRaw, false, true)->toArray();
            if (!empty($invalidTypes)) {
                $invalidTypesStr = implode(", ", $invalidTypes);
                throw new ImportException("Les types de " . mb_strtolower($this->translationService->translate("Demande", "Livraison", "Demande de livraison", false)) . " suivants sont invalides : $invalidTypesStr");
            }

            foreach ($user->getDeliveryTypes() as $type) {
                $user->removeDeliveryType($type);
            }

            foreach ($deliveryTypes as $deliveryType) {
                $user->addDeliveryType($deliveryType);
            }
        }

        if (isset($data['dispatchTypes'])) {
            $dispatchTypesRaw = array_map('trim', explode(',', $data['dispatchTypes']));
            $dispatchCategory = $this->entityManager->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_DISPATCH]);
            $dispatchTypes = $this->entityManager->getRepository(Type::class)->findBy([
                'label' => $dispatchTypesRaw,
                'category' => $dispatchCategory,
            ]);

            $dispatchTypesLabel = Stream::from($dispatchTypes)->map(fn(Type $type) => $type->getLabel())->toArray();
            $invalidTypes = Stream::diff($dispatchTypesLabel, $dispatchTypesRaw, false, true)->toArray();
            if (!empty($invalidTypes)) {
                $invalidTypesStr = implode(", ", $invalidTypes);
                throw new ImportException("Les types d'acheminements suivants sont invalides : $invalidTypesStr");
            }

            foreach ($user->getDispatchTypes() as $type) {
                $user->removeDispatchType($type);
            }

            foreach ($dispatchTypes as $dispatchType) {
                $user->addDispatchType($dispatchType);
            }
        }

        if (isset($data['handlingTypes'])) {
            $handlingTypesRaw = array_map('trim', explode(',', $data['handlingTypes']));
            $handlingCategory = $this->entityManager->getRepository(CategoryType::class)->findOneBy(['label' => CategoryType::DEMANDE_HANDLING]);
            $handlingTypes = $this->entityManager->getRepository(Type::class)->findBy([
                'label' => $handlingTypesRaw,
                'category' => $handlingCategory,
            ]);

            $handlingTypesLabel = Stream::from($handlingTypes)->map(fn(Type $type) => $type->getLabel())->toArray();
            $invalidTypes = Stream::diff($handlingTypesLabel, $handlingTypesRaw, false, true)->toArray();
            if (!empty($invalidTypes)) {
                $invalidTypesStr = implode(", ", $invalidTypes);
                throw new ImportException("Les types de services suivants sont invalides : $invalidTypesStr");
            }

            foreach ($user->getHandlingTypes() as $type) {
                $user->removeHandlingType($type);
            }

            foreach ($handlingTypes as $handlingType) {
                $user->addHandlingType($handlingType);
            }
        }

        if (isset($data['dropzone'])) {
            $locationRepository = $this->entityManager->getRepository(Emplacement::class);
            $locationGroupRepository = $this->entityManager->getRepository(LocationGroup::class);
            $dropzone = $locationRepository->findOneBy(['label' => $data['dropzone']])
                ?: $locationGroupRepository->findOneBy(['label' => $data['dropzone']]);
            if ($dropzone) {
                $user->setDropzone($dropzone);
            } else {
                throw new ImportException("La dropzone {$data['dropzone']} n'existe pas");
            }
        }
        foreach ($user->getVisibilityGroups() as $visibilityGroup) {
            $visibilityGroup->removeUser($user);
        }
        if (isset($data['visibilityGroup'])) {
            $visibilityGroups = Stream::explode([";", ","], $data["visibilityGroup"])
                ->filter()
                ->unique()
                ->map(fn(string $visibilityGroup) => trim($visibilityGroup))
                ->map(function ($label) use ($visibilityGroupRepository) {
                    $visibilityGroup = $visibilityGroupRepository->findOneBy(['label' => ltrim($label)]);
                    if (!$visibilityGroup) {
                        throw new ImportException('Le groupe de visibilité ' . $label . ' n\'existe pas.');
                    }
                    return $visibilityGroup;
                })
                ->toArray();
            foreach ($visibilityGroups as $visibilityGroup) {
                $user->addVisibilityGroup($visibilityGroup);
            }
        }

        if (isset($data['status'])) {
            if (!in_array(strtolower($data['status']), ['actif', 'inactif'])) {
                throw new ImportException('La valeur du champ Statut est incorrecte (actif ou inactif)');
            }
            $status = strtolower($data['status']) === 'actif' ? 1 : 0;
            $user->setStatus($status);
        }

        if (!empty($data['signatoryCode'])) {
            $plainSignatoryPassword = $data['signatoryCode'];
            if (strlen($plainSignatoryPassword) < 4) {
                throw new ImportException("Le code signataire doit contenir au moins 4 caractères");
            }

            $signatoryPassword = $this->encoder->hashPassword($user, $plainSignatoryPassword);
            $user->setSignatoryPassword($signatoryPassword);
        }

        $this->entityManager->persist($user);

        $isCreation = !$user->getId();
    }

    private function importCustomerEntity(array $data, ?bool &$isCreation)
    {

        $customerAlreadyExists = $this->entityManager->getRepository(Customer::class)->findOneBy(['name' => $data['name']]);
        $customer = $customerAlreadyExists ?? new Customer();

        if (isset($data['name'])) {
            $customer->setName($data['name']);
        }

        if (isset($data['address'])) {
            $customer->setAddress($data['address']);
        }

        if (isset($data['recipient'])) {
            $customer->setRecipient($data['recipient']);
        }

        if (isset($data['phone'])) {
            if (!preg_match(StringHelper::PHONE_NUMBER_REGEX, $data['phone'])) {
                throw new ImportException('Le format du numéro de téléphone est incorrect');
            }
            $customer->setPhoneNumber($data['phone']);
        }

        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ImportException('Le format de l\'adresse email est incorrect');
            }
            $customer->setEmail($data['email']);
        }

        if (isset($data['fax'])) {
            if (!preg_match(StringHelper::PHONE_NUMBER_REGEX, $data['fax'])) {
                throw new ImportException('Le format du numéro de fax est incorrect');
            }
            $customer->setFax($data['fax']);
        }

        $this->entityManager->persist($customer);

        $isCreation = !$customerAlreadyExists;
    }

    private function importDeliveryEntity(array       $data,
                                          Utilisateur $utilisateur,
                                          array       &$refsToUpdate,
                                          array       $colChampsLibres,
                                                      $row,
                                          ?bool       &$isCreation): ?Demande
    {
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

        $showTargetLocationPicking = $this->settingService->getValue($this->entityManager,Setting::DISPLAY_PICKING_LOCATION);
        $targetLocationPicking = null;
        if ($showTargetLocationPicking) {
            if (isset($data['targetLocationPicking'])) {
                $targetLocationPickingStr = $data['targetLocationPicking'];
                $targetLocationPicking = $locations->findOneBy(['label' => $targetLocationPickingStr]);
                if (!$targetLocationPicking) {
                    throw new ImportException("L'emplacement cible picking $targetLocationPickingStr n'existe pas.");
                }
            }
        }

        if (!$requester) {
            throw new ImportException('Demandeur inconnu.');
        }

        if (!$destination) {
            throw new ImportException('Destination inconnue.');
        } else if ($type && !$destination->getAllowedDeliveryTypes()->contains($type)) {
            throw new ImportException('Type non autorisé sur l\'emplacement fourni.');
        }
        $deliveryKey = $requester->getId() . '-' . $destination->getId();
        $newEntity = !isset($this->entityCache['deliveries'][$deliveryKey]);
        if (!$newEntity) {
            $request = $this->entityCache['deliveries'][$deliveryKey];
            $request = $this->entityManager->getRepository(Demande::class)->find($request->getId());
            $this->entityCache['deliveries'][$deliveryKey] = $request;
        }
        $request = $newEntity ? new Demande() : $this->entityCache['deliveries'][$deliveryKey];

        if (!$type) {
            throw new ImportException('Type inconnu.');
        } else if(!$type->isActive()) {
            throw new ImportException("Le type n'est pas actif.");
        } else if (!$request->getType()) {
            $request->setType($type);
        }

        if (!in_array(strtolower($data['status']), $availableStatuses)) {
            throw new ImportException('Statut inconnu (valeurs possibles : brouillon, à traiter).');
        } else if (!$request->getStatut()) {
            $request->setStatut($status);
        }

        if (!$quantityDelivery || !is_numeric($quantityDelivery)) {
            throw new ImportException('Quantité fournie non valide.');
        }

        if (!$articleReference || $articleReference->getStatut()?->getCode() === ReferenceArticle::STATUT_INACTIF) {
            throw new ImportException('Article de référence inconnu ou inactif.');
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
                            throw new ImportException("Article déjà présent dans la demande. ($barcode)");
                        }
                    } else {
                        $quantity = $article->getQuantite();
                        throw new ImportException("Quantité superieure à celle de l'article. ($quantity)");
                    }
                } else {
                    throw new ImportException('Article inconnu.');
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
                            ->setTargetLocationPicking($targetLocationPicking)
                            ->setDeliveryRequestReferenceLine($line);
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
                    throw new ImportException("Référence déjà présente dans la demande. ($reference)");
                }
            } else {
                $quantity = $articleReference->getQuantiteDisponible();
                throw new ImportException("Quantité superieure à celle de l'article de référence. ($quantity)");
            }
        }

        if (!$request->getCommentaire()) {
            $request->setCommentaire($commentaire);
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
                throw new ImportException($response['msg']);
            }
        }

        $this->freeFieldService->manageImportFreeFields($this->entityManager, $colChampsLibres, $request, $newEntity, $row);

        $isCreation = $newEntity;

        return $request;
    }

    private function importLocationEntity(array $data, ?bool &$isCreation) {
        $locationRepository = $this->entityManager->getRepository(Emplacement::class);
        $natureRepository = $this->entityManager->getRepository(Nature::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

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
                    throw new ImportException("La valeur saisie pour le champ nom ne doit pas dépasser 24 caractères");
                } elseif (!preg_match('/' . SettingsService::CHARACTER_VALID_REGEX . '/', $data['name'])) {
                    throw new ImportException("Le champ nom ne doit pas contenir de caractères spéciaux");
                } else {
                    $location->setLabel($data['name']);
                }
            } else {
                throw new ImportException("Le champ nom est obligatoire lors de la création d'un emplacement");
            }

            if (isset($data['description'])) {
                if ((strlen($data['description'])) > 255) {
                    throw new ImportException("La valeur saisie pour le champ description ne doit pas dépasser 255 caractères");
                } else {
                    $location->setDescription($data['description']);
                }
            } else {
                throw new ImportException("Le champ description est obligatoire lors de la création d'un emplacement");
            }
        } else {
            if (isset($data['description'])) {
                if ((strlen($data['description'])) > 255) {
                    throw new ImportException("La valeur saisie pour le champ description ne doit pas dépasser 255 caractères");
                }
            }
        }
        if (isset($data['dateMaxTime'])) {
            if (preg_match("/^\d+:[0-5]\d$/", $data['dateMaxTime'])) {
                $location->setDateMaxTime($data['dateMaxTime']);
            } else {
                throw new ImportException("Le champ Délai traça HH:MM ne respecte pas le bon format");
            }
        }

        if (isset($data['allowedPackNatures'])) {
            $elements = Stream::explode([";", ","], $data['allowedPackNatures'])
                ->filter()
                ->toArray();
            $natures = $natureRepository->findBy(['label' => $elements]);
            $natureLabels = Stream::from($natures)
                ->map(fn(Nature $nature) => $this->formatService->nature($nature))
                ->toArray();

            $diff = Stream::diff($elements, $natureLabels, true);
            if (!$diff->isEmpty()) {
                throw new ImportException("Les natures suivantes n'existent pas : {$diff->join(", ")}");
            } else {
                $location->setAllowedNatures($natures);
            }
        }

        if (isset($data['allowedDeliveryTypes'])) {
            $elements = Stream::explode([";", ","], $data['allowedDeliveryTypes'])
                ->filter()
                ->toArray();
            $allowedDeliveryTypes = $typeRepository->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], $elements);
            $allowedDeliveryTypesLabels = Stream::from($allowedDeliveryTypes)
                ->map(fn(Type $type) => $type->getLabel())
                ->toArray();

            $diff = Stream::diff($elements, $allowedDeliveryTypesLabels, true);
            if (!$diff->isEmpty()) {
                throw new ImportException("Les types de " . mb_strtolower($this->translationService->translate("Demande", "Livraison", "Demande de livraison", false)) . " suivants n'existent pas : {$diff->join(", ")}");
            } else {
                $location->setAllowedDeliveryTypes($typeRepository->findBy(['label' => $elements]));
            }
        }

        if (isset($data['allowedCollectTypes'])) {
            $elements = Stream::explode([";", ","], $data['allowedCollectTypes'])
                ->filter()
                ->toArray();
            $allowedCollectTypes = $typeRepository->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_COLLECTE], $elements);
            $allowedCollectTypesLabels = Stream::from($allowedCollectTypes)
                ->map(fn(Type $type) => $type->getLabel())
                ->toArray();

            $diff = Stream::diff($elements, $allowedCollectTypesLabels, true);
            if (!$diff->isEmpty()) {
                throw new ImportException("Les types de demandes de collectes suivants n'existent pas : {$diff->join(", ")}");
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
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ImportException('Le format de l\'adresse email est incorrect');
            }
            $location->setEmail($data['email']);
        }

        if (isset($data['isActive'])) {
            $value = strtolower($data['isActive']);
            if ($value !== 'oui' && $value !== 'non') {
                throw new ImportException('La valeur saisie pour Actif est invalide (autorisé : "oui" ou "non")');
            } else {
                $location->setIsActive($value === 'oui');
            }
        }

        $this->treatLocationZone($data, $location);

        $this->entityManager->persist($location);

        $isCreation = $isNewEntity;

        return $location;
    }

    private function importProjectEntity(array $data, ?bool &$isCreation): void
    {
        $projectAlreadyExists = $this->entityManager->getRepository(Project::class)->findOneBy(['code' => $data['code']]);
        $project = $projectAlreadyExists ?? new Project();

        if (!$projectAlreadyExists && isset($data['code'])) {
            $project->setCode($data['code']);
        }

        if (isset($data['description'])) {
            if ((strlen($data['description'])) > 255) {
                throw new ImportException("La valeur saisie pour le champ description ne doit pas dépasser 255 caractères");
            } else {
                $project->setDescription($data['description']);
            }
        }

        if (isset($data['projectManager'])) {
            $projectManager = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['username' => $data['projectManager']]);

            if (!isset($projectManager)) {
                throw new ImportException('Aucun utilisateur ne correspond au nom d\'utilisateur saisi dans la colonne Chef de projet');
            } else {
                $project->setProjectManager($projectManager);
            }
        }

        if (isset($data['isActive'])) {
            $value = strtolower($data['isActive']);
            if ($value !== 'oui' && $value !== 'non') {
                throw new ImportException('La valeur saisie pour Actif est invalide (autorisé : "oui" ou "non")');
            } else {
                $project->setActive($data['isActive']);
            }
        }

        $this->entityManager->persist($project);

        $isCreation = !$projectAlreadyExists;
    }

    private function importRefLocationEntity(array $data, ?bool &$isCreation): void
    {
        $refLocationAlreadyExists = $this->entityManager->getRepository(StorageRule::class)->findOneByReferenceAndLocation($data['reference'], $data['location']);
        $refLocation = $refLocationAlreadyExists ?? new StorageRule();

        if (!$refLocationAlreadyExists && isset($data['reference'])) {
            $reference = $this->entityManager->getRepository(ReferenceArticle::class)->findOneBy(['reference' => $data['reference']]);
            if ($reference) {
                $refLocation->setReferenceArticle($reference);
            } else {
                throw new ImportException("La référence saisie n'existe pas.");
            }
        }

        if (!$refLocationAlreadyExists && isset($data['location'])) {
            $location = $this->entityManager->getRepository(Emplacement::class)->findOneBy(['label' => $data['location']]);
            if ($location) {
                $refLocation->setLocation($location);
            } else {
                throw new ImportException("L'emplacement saisi n'existe pas.");
            }
        }

        if (isset($data['securityQuantity'])) {
            if (!is_numeric($data['securityQuantity'])) {
                throw new ImportException('La quantité de sécurité doit être un nombre.');
            } else {
                $refLocation->setSecurityQuantity($data['securityQuantity']);
            }
        }

        if (isset($data['conditioningQuantity'])) {
            if (!is_numeric($data['conditioningQuantity'])) {
                throw new ImportException('La quantité de conditionnement doit être un nombre.');
            } else {
                $refLocation->setConditioningQuantity($data['conditioningQuantity']);
            }
        }

        $this->entityManager->persist($refLocation);

        $isCreation = !$refLocationAlreadyExists;
    }

    private function checkAndCreateMvtStock($refOrArt, int $formerQuantity, int $newQuantity, bool $isNewEntity)
    {
        $diffQuantity = $isNewEntity ? $newQuantity : ($newQuantity - $formerQuantity);

        $mvtIn = $isNewEntity ? MouvementStock::TYPE_ENTREE : MouvementStock::TYPE_INVENTAIRE_ENTREE;
        if ($diffQuantity != 0) {
            $typeMvt = $diffQuantity > 0 ? $mvtIn : MouvementStock::TYPE_INVENTAIRE_SORTIE;

            $emplacement = $refOrArt->getEmplacement();
            $mvtStock = $this->mouvementStockService->createMouvementStock($this->currentImport->getUser(), $emplacement, abs($diffQuantity), $refOrArt, $typeMvt);
            $this->mouvementStockService->finishStockMovement($mvtStock, new DateTime('now'), $emplacement);
            $mvtStock->setImport($this->currentImport);
            $this->entityManager->persist($mvtStock);
        }
    }

    private function checkAndCreateProvider(string $code, string $name = null)
    {
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $provider = $fournisseurRepository->findOneBy(['codeReference' => $code]);

        if (empty($provider)) {
            $provider = new Fournisseur();
            $provider
                ->setCodeReference($code)
                ->setNom($name ?: $code);
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
            throw new ImportException($message);
        } else {
            $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
            $location = $emplacementRepository->findOneBy(['label' => $data['emplacement']]);
            if (empty($location)) {
                // check if we already try to get standard Zone in cache memory, only one iterate by import file
                if (!array_key_exists('defaultZoneLocation', $this->entityCache)) {
                    $zoneRepository = $this->entityManager->getRepository(Zone::class);
                    $this->entityCache['defaultZoneLocation'] = $zoneRepository->findOneBy(['name' => Zone::ACTIVITY_STANDARD_ZONE_NAME]);
                }
                $defaultZoneLocation = $this->entityCache['defaultZoneLocation'];
                if (empty($defaultZoneLocation)) {
                    throw new ImportException('Erreur lors de la création de l\'emplacement : ' . $data['emplacement'] . '. La zone ' . Zone::ACTIVITY_STANDARD_ZONE_NAME . ' n\'est pas définie.');
                }
                $location = $this->emplacementDataService->persistLocation($this->entityManager, [
                    FixedFieldEnum::name->name => $data['emplacement'],
                    FixedFieldEnum::status->name => true,
                    FixedFieldEnum::isDeliveryPoint->name => false,
                    FixedFieldEnum::zone->name => $defaultZoneLocation,
                ]);
            }
            $articleOrRef->setEmplacement($location);
        }
    }

    private function checkAndCreateArticleFournisseur(?string           $articleFournisseurReference,
                                                      ?string           $fournisseurReference,
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
                    throw new ImportException(
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
                            throw new ImportException("Veuillez renseigner une référence de fournisseur correspondant à celle de l'article fournisseur renseigné.");
                        }
                    } else {
                        throw new ImportException("Veuillez renseigner une référence de fournisseur connue.");
                    }
                }

                if (!empty($referenceArticle)
                    && ($articleFournisseur->getReferenceArticle()->getId() !== $referenceArticle->getId())) {
                    throw new ImportException("Veuillez renseigner une référence d'article fournisseur correspondant à la référence d'article fournie.");
                }
            }
        } // cas où la ref d'article fournisseur n'est pas renseignée
        else {
            if (empty($referenceArticle)) {
                throw new ImportException("Vous n'avez pas renseigné de référence d'article fournisseur. Dans ce cas, veuillez fournir une référence d'article de référence connue.");
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

    private function clearEntityManagerAndRetrieveImport()
    {
        $this->entityManager->clear();
        $this->entityCache = [];
        $this->currentImport = $this->entityManager->find(Import::class, $this->currentImport->getId());
    }

    public function createPreselection(array $headers, array $fieldsToCheck, ?array $sourceColumnToField)
    {
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
            } else {
                if (!empty($sourceColumnToField[$headerIndex])) {
                    $preselection[$header] = $sourceColumnToField[$headerIndex];
                }
            }
        }
        return $preselection;
    }

    public function getFieldsToAssociate(EntityManagerInterface $entityManager,
                                         string                 $entityCode): array
    {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $fieldsToAssociate = Stream::from(self::FIELDS_TO_ASSOCIATE[$entityCode] ?? []);

        if ($entityCode === Import::ENTITY_DELIVERY) {
            $showTargetLocationPicking = $this->settingService->getValue($entityManager, Setting::DISPLAY_PICKING_LOCATION);
            if (!$showTargetLocationPicking) {
                $fieldsToAssociate = $fieldsToAssociate->filter(fn(string $key) => ($key !== "targetLocationPicking"));
            }
        }

        $fieldsToAssociate = $fieldsToAssociate
            ->keymap(static fn(string $key) => [
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
            Import::ENTITY_DISPATCH => CategorieCL::DEMANDE_DISPATCH,
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

    public function resetCache(): void
    {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $associatedDocumentTypesStr = $this->settingsService->getValue($this->entityManager,Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES);
        $associatedDocumentTypes = $associatedDocumentTypesStr
            ? Stream::explode(',', $associatedDocumentTypesStr)
                ->filter()
                ->toArray()
            : [];

        $wantsUFT8 = $this->settingsService->getValue($this->entityManager, Setting::USES_UTF8) ?? true;

        $this->entityCache = [];
        $this->scalarCache = [
            Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES => $associatedDocumentTypes,
            "logFileMapper" => fn($row) => !$wantsUFT8
                ? array_map('utf8_decode', $row)
                : $row
        ];
    }

    private function resetImportStatistics(): void {
        $this->importStatistics = [
            self::STATISTICS_CREATIONS => 0,
            self::STATISTICS_UPDATES   => 0,
            self::STATISTICS_ERRORS    => 0,
        ];
    }

    private function treatLocationZone(array $data, Emplacement $location): void
    {
        $zoneRepository = $this->entityManager->getRepository(Zone::class);
        if (isset($data['zone'])) {
            $zone = $zoneRepository->findOneBy(['name' => trim($data['zone'])]);
            if ($zone) {
                $location->setProperty("zone", $zone);
            } else {
                throw new ImportException('La zone ' . $data['zone'] . ' n\'existe pas dans la base de données');
            }
        } else {
            if (!isset($this->scalarCache['totalZone'])) {
                $zoneRepository = $this->entityManager->getRepository(Zone::class);
                $this->scalarCache['totalZone'] = $zoneRepository->count([]);
            }
            if ($this->scalarCache['totalZone'] === 0) {
                throw new ImportException("Aucune zone existante. Veuillez créer au moins une zone");
            } else if ($this->scalarCache['totalZone'] === 1) {
                $zone = $zoneRepository->findOneBy([]);
                $location->setProperty("zone", $zone);
            } else {
                throw new ImportException("Le champ zone doit être renseigné");
            }
        }
    }

    private function eraseGlobalDataBefore(): void
    {
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

    private function eraseGlobalDataAfter(): void
    {
        if ($this->currentImport->isEraseData()) {
            switch ($this->currentImport->getEntity()) {
                case Import::ENTITY_ART_FOU:
                    if (!empty($this->entityCache["resetSupplierArticles"]['supplierArticles'])
                        && !empty($this->entityCache["resetSupplierArticles"]['referenceArticles'])) {
                        $supplierArticleRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
                        $supplierArticleRepository->deleteSupplierArticles(
                            $this->entityCache["resetSupplierArticles"]['supplierArticles'],
                            $this->entityCache["resetSupplierArticles"]['referenceArticles']
                        );
                    }
                    break;
                default:
                    break;
            }
        }
    }

    public function getImportSecondModalConfig(EntityManagerInterface $entityManager,
                                               ParameterBag           $post,
                                               Import                 $import): array
    {

        $fixedFieldStandardRepository = $entityManager->getRepository(FixedFieldStandard::class);
        $importRepository = $entityManager->getRepository(Import::class);

        $fileImportConfig = $this->getFileImportConfig($import->getCsvFile());

        if ($post->get('importId')) {
            $copiedImport = $importRepository->find($post->get('importId'));
            $columnsToFields = $copiedImport->getColumnToField();
        }

        $entity = $import->getEntity();
        $fieldsToAssociate = $this->getFieldsToAssociate($entityManager, $entity);
        natcasesort($fieldsToAssociate);

        $preselection = [];
        if (isset($fileImportConfig['headers'])) {
            $headers = $fileImportConfig['headers'];

            $fieldsToCheck = array_merge($fieldsToAssociate);
            $sourceImportId = $post->get('sourceImport');
            if (isset($sourceImportId)) {
                $sourceImport = $importRepository->find($sourceImportId);
                if (isset($sourceImport)) {
                    $sourceColumnToField = $sourceImport->getColumnToField();
                }
            }

            $preselection = $this->createPreselection($headers, $fieldsToCheck, $sourceColumnToField ?? null);
        }
        $fieldsNeeded = Import::FIELDS_NEEDED[$entity];

        if ($entity === Import::ENTITY_RECEPTION) {
            foreach ($fieldsToAssociate as $field) {
                $fieldParamCode = Import::IMPORT_FIELDS_TO_FIELDS_PARAM[$field] ?? null;
                if ($fieldParamCode) {
                    $fieldParam = $fixedFieldStandardRepository->findOneBy([
                        'fieldCode' => $fieldParamCode,
                        'entityCode' => FixedFieldStandard::ENTITY_CODE_RECEPTION,
                    ]);
                    if ($fieldParam && $fieldParam->isRequiredCreate()) {
                        $fieldsNeeded[] = $field;
                    }
                }
            }
        }

        return [
            'data' => $fileImportConfig,
            'fields' => $fieldsToAssociate ?? [],
            'preselection' => $preselection ?? [],
            'fieldsNeeded' => $fieldsNeeded,
            'fieldPK' => Import::FIELD_PK[$entity],
            'columnsToFields' => $columnsToFields ?? null,
            'fromExistingImport' => !empty($sourceColumnToField)
        ];
    }

    public function getImport(): ?Import {
        return $this->currentImport;
    }

    public function getFileImportConfig(Attachment $attachment): ?array
    {
        $path = $this->attachmentService->getServerPath($attachment);

        $file = fopen($path, "r");

        $headers = fgetcsv($file, 0, ";");
        $firstRow = fgetcsv($file, 0, ";");

        $res = null;
        if ($headers && $firstRow) {
            $csvContent = file_get_contents($path);
            $res = [
                'headers' => $headers,
                'firstRow' => $firstRow,
                'isUtf8' => mb_check_encoding($csvContent, 'UTF-8')
            ];
        }

        fclose($file);

        return $res;
    }

    #[ArrayShape([
        'success' => "boolean",
        'message' => "string",
    ])]
    public function validateImportAttachment(Attachment $attachment,
                                             bool $isUnique): array {

        $fileConfig = $this->getFileImportConfig($attachment);
        if (!$fileConfig) {
            $success = false;
            if ($isUnique) {
                $message = 'Format du fichier incorrect. Il doit au moins contenir une ligne d\'en-tête et une ligne à importer.';
            } else {
                $message = 'Format du fichier incorrect. Il doit au moins contenir une ligne d\'en-tête et une ligne d\'exemple.';
            }
        } else if (!$fileConfig["isUtf8"]) {
            $success = false;
            $message = 'Veuillez charger un fichier encodé en UTF-8';
        } else {
            $success = true;
        }

        return [
            "success" => $success,
            "message" => $message ?? ""
        ];
    }

    /**
     * @return resource|null
     */
    public function fopenImportFile(): mixed {
        $errorMessage = false;
        if ($this->currentImport->getType()?->getLabel() === Type::LABEL_SCHEDULED_IMPORT) {
            $absoluteFilePath = $this->currentImport->getFilePath();

            $FTPConfig = $this->currentImport->getFTPConfig();

            if ($FTPConfig) {
                // file is on an external server
                $name = uniqid() . ".csv";
                $path = "/tmp/$name";
                $this->scalarCache['importFilePath'] = $path;

                try {
                    $this->FTPService->try($FTPConfig);
                    $data = $this->FTPService->get([
                        'host' => $FTPConfig['host'],
                        'port' => $FTPConfig['port'],
                        'user' => $FTPConfig['user'],
                        'pass' => $FTPConfig['pass'],
                    ], $absoluteFilePath);
                    file_put_contents($path, $data);
                } catch (FTPException $FTPException) {
                    $errorMessage = $FTPException->getMessage();
                } catch (Throwable $throwable) {
                    $errorMessage = "Erreur lors de requête FTP : {$throwable->getMessage()}\n{$throwable->getTraceAsString()}";
                }
            }
            else {
                // file is on an external Symfony server
                $path = $this->currentImport->getFilePath();
                $this->scalarCache['importFilePath'] = $path;
            }
        } else {
            $csvFile = $this->currentImport->getCsvFile();
            $this->scalarCache["importFilePath"] = $csvFile;
            $path = $this->attachmentService->getServerPath($csvFile);
        }

        if (empty($errorMessage)) {
            try {
                $file = fopen($path, "r") ?: null;
            } catch (Throwable) {
                $file = null;
            }

            if (!$file) {
                $errorMessage = "Le fichier source n'existe pas, ou vous n'avez pas les droits. Veuillez vérifier le chemin suivant : $path";
            } else if (is_dir($path)) {
                $errorMessage = "Le chemin enregistré dans l'import indique un répertoire : $path";
                $file = null;
            }
        }

        if ($errorMessage) {
            $this->currentImport->setLastErrorMessage($errorMessage);
        }

        return $file ?? null;
    }

    public function cleanImportFile(mixed $file): void {
        $importFilePath = $this->scalarCache["importFilePath"] ?? null;

        if ($file) {
            @fclose($file);
        }

        if ($importFilePath &&
            $this->currentImport->getType()?->getLabel() === Type::LABEL_SCHEDULED_IMPORT) {
            @unlink($importFilePath);
        }
    }

    /**
     * @param resource $file
     */
    #[ArrayShape([
        "rowCount" => "number",
        "firstRows" => "array",
        "headersLog" => "array|null",
    ])]
    private function extractDataFromCSVFiles(mixed $file): array {

        $rowCount = 0;
        $firstRows = [];
        $headersLog = null;

        while (($row = fgetcsv($file, 0, ';')) !== false
            && $rowCount <= self::MAX_LINES_AUTO_FORCED_IMPORT) {

            if (empty($headersLog)) {
                $headersLog = [...$row, 'Statut import'];
            } else {
                $firstRows[] = $row;
                $rowCount++;
            }
        }

        return [
            "rowCount" => $rowCount,
            "firstRows" => $firstRows,
            "headersLog" => $headersLog,
        ];
    }

}
