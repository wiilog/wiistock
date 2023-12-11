<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ScheduledTask\Export;
use App\Entity\ScheduledTask\ScheduleRule\ExportScheduleRule;
use App\Entity\ScheduledTask\ScheduleRule\ScheduleRule;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Service\ShippingRequest\ShippingRequestService;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class DataExportService
{

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public Security $security;

    #[Required]
    public ArrivageService $arrivalService;

    #[Required]
    public StorageRuleService $storageRuleService;

    #[Required]
    public DispatchService $dispatchService;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public ShippingRequestService $shippingRequestService;

    #[Required]
    public TranslationService $translation;

    public function createReferencesHeader(array $freeFieldsConfig) {
        return array_merge([
            'reference',
            'libellé',
            'quantité',
            'type',
            'acheteur',
            'type quantité',
            'statut',
            'commentaire',
            'emplacement',
            'seuil sécurite',
            'seuil alerte',
            'prix unitaire',
            'code barre',
            'catégorie inventaire',
            'date dernier inventaire',
            'synchronisation nomade',
            'gestion de stock',
            'gestionnaire(s)',
            'Labels Fournisseurs',
            'Codes Fournisseurs',
            'Groupe de visibilité',
            'date de création',
            'crée par',
            'date de dérniere modification',
            'modifié par',
            "date dernier mouvement d'entrée",
            "date dernier mouvement de sortie",
        ], $freeFieldsConfig["freeFieldsHeader"]);
    }

    public function createArticlesHeader(array $freeFieldsConfig) {
        return array_merge([
            'reference',
            'libelle',
            'fournisseur',
            'référence article fournisseur',
            'tag RFID',
            'quantité',
            'type',
            'statut',
            'commentaire',
            'emplacement',
            'code barre',
            'date dernier inventaire',
            'date de disponibilité constatée',
            'date d\'épuisement constaté',
            'lot',
            'date d\'entrée en stock',
            'date de péremption',
            'groupe de visibilité',
            mb_strtolower($this->translation->translate('Référentiel', 'Projet', 'Projet', false)),
            'prix unitaire',
            'numéro de commande',
            'numéro de bon de livraison',
            'pays d\'origine',
            'date de fabrication',
            'date de production',
        ], $freeFieldsConfig['freeFieldsHeader']);
    }

    public function createDeliveryRoundHeader() {
        return [
            'N° Tournée',
            'Date tournée',
            'Transport',
            'Livreur',
            'Immatriculation',
            'Kilomètres',
            'N° dossier patient',
            'N° Demande',
            'Adresse transport',
            'Métropole',
            'Numéro dans la tournée',
            'Urgence',
            'Date de création',
            'Demandeur',
            'Date demandée',
            'Date demande terminée',
            'Objets',
            'Anomalie température',
        ];
    }

    public function createShippingRequestHeader() {
        return [
            "Numéro",
            "Statut",
            "Date de création",
            "Date de validation",
            "Date de planification",
            "Date d'enlèvement prévu",
            "Date d'expédition",
            "Date de prise en charge souhaitée",
            "Demandeur(s)",
            "N° commande client",
            "Livraison à titre gracieux",
            "Articles conformes",
            "Client",
            "A l'attention de",
            "Téléphone client",
            "Adresse livraison",
            "Unité logistique",
            "Nature",
            "Référence",
            "Libellé",
            "Article",
            "Quantité",
            "Prix unitaire ()",
            "Poids nets (kg)",
            "Montant total",
            "Marchandise dangereuse",
            "FDS",
            "Code ONU",
            "Classe produit",
            "Code NDP",
            "Envoi",
            "Port",
            "Nombre de colis",
            "Dimension colis (cm)",
            "Poids net transport (kg)",
            "Poids brut transport (kg)",
            "Valeur total transport",
            "Spécification transport"
        ];
    }

    public function createDispatchesHeader(array $freeFieldsConfig,
                                           array $statusConfig): array {
        return [
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'N° demande', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'N° commande', false),
            $this->translation->translate('Général', null, 'Zone liste', 'Date de création', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Date de validation', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Date de traitement', false),
            "Date échéance CPL",
            "Date livraison ligne initiale",
            "Date livraison ligne modifiée",
            $this->translation->translate('Demande', 'Général', 'Type', false),
            $this->translation->translate('Demande', 'Général', 'Demandeur', false),
            $this->translation->translate('Demande', 'Général', 'Destinataire(s)', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de prise', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Emplacement de dépose', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Destination', false),
            $this->translation->translate('Demande', 'Acheminements', 'Zone liste - Noms de colonnes', 'Nombre d\'UL', false),
            $this->translation->translate('Demande', 'Général', 'Statut', false),
            $this->translation->translate('Demande', 'Général', 'Urgence', false),
            "Pièce(s) jointe(s)",
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Nature', false),
            $this->translation->translate('Demande', 'Acheminements', 'Détails acheminement - Liste des unités logistiques', 'Unité logistique', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Quantité UL', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Quantité à acheminer', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Poids (kg)', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Volume (m3)', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Hauteur (m)', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Largeur (m)', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Longueur (m)', false),
            'Commentaire UL',
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Date dernier mouvement', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Dernier emplacement', false),
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Opérateur', false),
            "Groupe",
            $this->translation->translate('Demande', 'Général', 'Transporteur', false),
            "Numéro de tracking transporteur",
            "Appel",
            "Numéro d'OF",
            $this->translation->translate('Demande', 'Acheminements', 'Général', 'Business unit', false),
            "Numéro de projet",
            $this->translation->translate('Général', null, 'Modale', 'Commentaire', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Traité par', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Client', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Téléphone client', false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', "À l'attention de", false),
            $this->translation->translate('Demande', 'Acheminements', 'Champs fixes', 'Adresse de livraison', false),
            ...($freeFieldsConfig['freeFieldsHeader']),
            ...$statusConfig,
        ];
    }

    public function createArrivalsHeader(EntityManagerInterface $entityManager,
                                         array $columnToExport): array
    {
        $exportableColumns = Stream::from($this->arrivalService->getArrivalExportableColumns($entityManager));
        return Stream::from($columnToExport)
            ->filterMap(function(string $code) use ($exportableColumns) {
                $column = $exportableColumns
                    ->find(fn(array $config) => $config['code'] === $code);
                return $column['label'] ?? null;
            })
            ->toArray();
    }

    public function createStorageRulesHeader(): array
    {
        return [
            'Référence',
            'Emplacement',
            'Quantité sécurité',
            'Quantité de conditionnement',
            'Zone'
        ];
    }

    public function exportReferences(RefArticleDataService $refArticleDataService,
                                     array $freeFieldsConfig,
                                     iterable $data,
                                     mixed $output) {

        foreach($data as $reference) {
            $refArticleDataService->putReferenceLine($output, $reference, $freeFieldsConfig);
        }
    }

    public function exportArticles(ArticleDataService   $articleDataService,
                                   array                $freeFieldsConfig,
                                   iterable             $data,
                                   mixed                $output) {
        foreach($data as $article) {
                $articleDataService->putArticleLine($output, $article, $freeFieldsConfig);
        }
    }

    public function exportTransportRounds(TransportRoundService $transportRoundService, iterable $data, mixed $output, DateTime $begin, DateTime $end) {
        /** @var TransportRound $round */
        foreach ($data as $round) {
            $transportRoundService->putLineRoundAndRequest($output, $round, function(TransportRoundLine $line) use ($begin, $end) {
                $order = $line->getOrder();
                $treatedAt = $order?->getTreatedAt() ?: null;

                return (
                    $treatedAt >= $begin
                    && $treatedAt <= $end
                );
            });
        }
    }

    public function createUniqueExportLine(string $entity, DateTime $from) {
        $type = $this->entityManager->getRepository(Type::class)->findOneByCategoryLabelAndLabel(
            CategoryType::EXPORT,
            Type::LABEL_UNIQUE_EXPORT,
        );

        $status = $this->entityManager->getRepository(Statut::class)->findOneByCategorieNameAndStatutCode(
            CategorieStatut::EXPORT,
            Export::STATUS_FINISHED,
        );

        $to = new DateTime();

        $export = new Export();
        $export->setEntity($entity);
        $export->setType($type);
        $export->setStatus($status);
        $export->setCreator($this->security->getUser());
        $export->setCreatedAt($from);
        $export->setBeganAt($from);
        $export->setEndedAt($to);
        $export->setForced(false);

        $this->entityManager->persist($export);
        $this->entityManager->flush();

        return $export;
    }

    public function exportArrivages(iterable $data,
                                    mixed $output,
                                    array $columnToExport)
    {
        /** @var Arrivage $arrival */
        foreach ($data as $arrival) {
            $this->arrivalService->putArrivalLine($output, $arrival, $columnToExport);
        }
    }

    public function exportShippingRequests(iterable $data,
                                           mixed $output) {
        foreach ($data as $shippingRequestData) {
            $this->shippingRequestService->putShippingRequestLine($output, $shippingRequestData);
        }
    }

    public function exportRefLocation(iterable $data,
                                      mixed $output)
    {
        /** @var StorageRule $storageRule */
        foreach ($data as $storageRule) {
            $this->storageRuleService->putStorageRuleLine($output, $storageRule);
        }
    }

    public function exportDispatch(array $dispatches,
                                   mixed $output,
                                   array $freeFieldsConfig,
                                   array $freeFieldsById,
                                   array $statusConfig): void
    {
        foreach ($dispatches as $dispatch) {
            $this->dispatchService->putDispatchLine($output, $dispatch, $freeFieldsConfig, $freeFieldsById, $statusConfig, true);
        }
    }

    /**
     * @throws \Exception
     */
    public function updateExport(EntityManagerInterface $entityManager,
                                 Export                 $export,
                                 array                  $data): void {
        $userRepository = $entityManager->getRepository(Utilisateur::class);

        $entity = $export->getEntity();

        if(!isset($data["startDate"])){
            throw new FormException("Veuillez choisir une fréquence pour votre export planifié.");
        }

        $export->setDestinationType($data["destinationType"]);
        if($export->getDestinationType() == Export::DESTINATION_EMAIL) {
            $export->setFtpParameters(null);

            $emails = isset($data["recipientEmails"]) && $data["recipientEmails"]
                ? Stream::explode(",", $data["recipientEmails"])
                    ->filter()
                    ->toArray()
                : [];
            if (!empty($emails)) {
                $invalidEmails = Stream::from($emails)
                    ->filter(fn(string $email) => !filter_var($email, FILTER_VALIDATE_EMAIL))
                    ->count();

                if ($invalidEmails === 1) {
                    throw new FormException("Une adresse email n'est pas valide dans votre saisie");
                }
                else if ($invalidEmails > 1) {
                    throw new FormException("Plusieurs adresses email ne sont pas valides dans votre saisie");
                }
            }
            $recipientUserIds = Stream::explode(",", $data["recipientUsers"])
                ->filter()
                ->toArray();
            $export->setRecipientUsers(!empty($recipientUserIds) ? $userRepository->findBy(["id" => $recipientUserIds]) : []);
            $export->setRecipientEmails($emails);

            if($export->getRecipientUsers()->isEmpty() && empty($emails)) {
                throw new FormException("Vous devez renseigner au moins un utilisateur ou une adresse email destinataire");
            }
        } else {
            if (!isset($data["host"])
                || !isset($data["port"])
                || !isset($data["user"])
                || !isset($data["password"])
                || !isset($data["targetDirectory"])) {
                throw new FormException("Veuillez renseigner tous les champs nécessaires au paramétrage du serveur SFTP.");
            }

            $export->setRecipientUsers([]);
            $export->setRecipientEmails([]);

            $export->setFtpParameters([
                "host" => $data["host"],
                "port" => $data["port"],
                "user" => $data["user"],
                "pass" => $data["password"],
                "path" => $data["targetDirectory"],
            ]);
        }

        if($entity === Export::ENTITY_ARRIVAL) {
            $columnToExport = Stream::explode(",", $data["columnToExport"])
                ->filter()
                ->toArray();
            $export->setColumnToExport($columnToExport);
        } else {
            $export->setColumnToExport([]);
        }

        if(in_array($entity, [Export::ENTITY_ARRIVAL,Export::ENTITY_DELIVERY_ROUND, Export::ENTITY_DISPATCH])) {
            $export->setPeriod($data["period"])
                ->setPeriodInterval($data["periodInterval"]);
        } else {
            $export->setPeriod(null)
                ->setPeriodInterval(null);
        }

        if ($entity === Export::ENTITY_ARTICLE) {
            $export
                ->setReferenceTypes($data["referenceTypes"] ? explode(",", $data["referenceTypes"]) : [])
                ->setStatuses($data["statuses"] ? explode(",", $data["statuses"]) : [])
                ->setSuppliers($data["suppliers"] ? explode(",", $data["suppliers"]) : []);

            if (isset($data["scheduled-date-radio"]) && $data["scheduled-date-radio"] === "fixed-date") {
                if (isset($data["scheduledDateMin"]) && isset($data["scheduledDateMax"])) {
                    $export->setStockEntryStartDate(DateTime::createFromFormat('Y-m-d', $data["scheduledDateMin"]))
                        ->setStockEntryEndDate(DateTime::createFromFormat('Y-m-d', $data["scheduledDateMax"]));
                }
            } else {
                if (isset($data["minus-day"]) && isset($data["additional-day"])) {
                    $now = new DateTime("now");
                    $endDate = (clone $now)->modify("-{$data["minus-day"]} days");
                    $startDate = (clone $endDate)->modify("-{$data["additional-day"]} days");

                    $export->setStockEntryStartDate($startDate)
                        ->setStockEntryEndDate($endDate);
                }
            }
        }

        if (!$export->getExportScheduleRule()) {
            $export->setExportScheduleRule(new ExportScheduleRule());
        }

        $begin = FormatHelper::parseDatetime($data["startDate"]);

        if (in_array($data["frequency"], [ScheduleRule::DAILY, ScheduleRule::WEEKLY, ScheduleRule::MONTHLY])) {
            $begin->setTime(0, 0);
        }

        $export->getExportScheduleRule()
            ->setBegin($begin)
            ->setFrequency($data["frequency"] ?? null)
            ->setPeriod($data["repeatPeriod"] ?? null)
            ->setIntervalTime($data["intervalTime"] ?? null)
            ->setIntervalPeriod($data["intervalPeriod"] ?? null)
            ->setMonths(isset($data["months"]) ? explode(",", $data["months"]) : null)
            ->setWeekDays(isset($data["weekDays"]) ? explode(",", $data["weekDays"]) : null)
            ->setMonthDays(isset($data["monthDays"]) ? explode(",", $data["monthDays"]) : null);

        $export
            ->setNextExecution($this->scheduleRuleService->calculateNextExecutionDate($export->getExportScheduleRule()));
    }
}
