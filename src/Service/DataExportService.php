<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Export;
use App\Entity\ExportScheduleRule;
use App\Entity\ScheduleRule;
use App\Entity\Statut;
use App\Entity\StorageRule;
use App\Entity\Transport\TransportRound;
use App\Entity\Transport\TransportRoundLine;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Helper\FormatHelper;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
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
    public ScheduleRuleService $scheduleRuleService;

    #[Required]
    public FormatService $formatService;

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
            'lot',
            'date d\'entrée en stock',
            'date de péremption',
            'groupe de visibilité',
            'projet',
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

    public function exportRefLocation(iterable $data,
                                      mixed $output)
    {
        /** @var StorageRule $storageRule */
        foreach ($data as $storageRule) {
            $this->storageRuleService->putStorageRuleLine($output, $storageRule);
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

        if($entity === Export::ENTITY_ARRIVAL || $entity === Export::ENTITY_DELIVERY_ROUND) {
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
