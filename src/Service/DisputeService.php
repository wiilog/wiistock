<?php

namespace App\Service;

use App\Entity\DisputeHistoryRecord;
use App\Entity\FiltreSup;
use App\Entity\Dispute;
use App\Entity\ReceiptAssociation;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Repository\DisputeRepository;
use App\Serializer\SerializerUsageEnum;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\StringHelper;

class DisputeService {

    public const CATEGORY_ARRIVAGE = 'un arrivage';
    public const CATEGORY_RECEPTION = 'une réception';

    public function __construct(
        private RouterInterface        $router,
        private FormatService          $formatService,
        private Twig_Environment       $templating,
        private Security               $security,
        private EntityManagerInterface $entityManager,
        private TranslationService     $translation,
        private MailerService          $mailerService,
        private FieldModesService      $fieldModesService,
        private CSVExportService       $CSVExportService,
        private NormalizerInterface    $normalizer,
    ) {}

    public function getDataForDatatable($params = null, bool $fromDashboard = false, array $preFilledFilters = []): array {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $disputeRepository = $this->entityManager->getRepository(Dispute::class);

        if (!$fromDashboard) {
            $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPUTE, $this->security->getUser());
        } else {
            $filters = $preFilledFilters;
        }

        $queryResult = $disputeRepository->findByParamsAndFilters($params, $filters, $this->security->getUser(), $this->fieldModesService);
        $disputes = $queryResult['data'];

        $rows = [];
        foreach ($disputes as $dispute) {
            $rows[] = $this->dataRowDispute($dispute);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowDispute(array $dispute): array {
        $disputeRepository = $this->entityManager->getRepository(Dispute::class);
        $receptionReferenceArticleRepository = $this->entityManager->getRepository(ReceptionReferenceArticle::class);

        $disputeId = $dispute['id'];
        $acheteursArrivage = $disputeRepository->getAcheteursArrivageByDisputeId($disputeId, 'username');
        $acheteursReception = $disputeRepository->getAcheteursReceptionByDisputeId($disputeId, 'username');

        $lastHistoryRecordDate = $dispute['lastHistoryRecord_date'];
        $lastHistoryRecordComment = $dispute['lastHistoryRecord_comment'];
        $user = $this->security->getUser();
        $format = $user && $user->getDateFormat() ? ($user->getDateFormat() . ' H:i') : (Utilisateur::DEFAULT_DATE_FORMAT . ' H:i');

        $lastHistoryRecordStr = ($lastHistoryRecordDate && $lastHistoryRecordComment)
            ? ($this->formatService->datetime($lastHistoryRecordDate, "", false, $this->security->getUser()) . ' : ' . nl2br($lastHistoryRecordComment))
            : '';

        $commands = $receptionReferenceArticleRepository->getAssociatedIdAndOrderNumbers($disputeId)[$disputeId] ?? '';
        $references = $receptionReferenceArticleRepository->getAssociatedIdAndReferences($disputeId)[$disputeId] ?? '';

        $numerosBL = isset($dispute['numCommandeBl'])
            ? (implode(', ', json_decode($dispute['numCommandeBl'], true)))
            : '';

        return [
            'actions' => $this->templating->render('litige/datatableLitigesRow.html.twig', [
                'disputeId' => $dispute['id'],
                'arrivageId' => $dispute['arrivageId'],
                'receptionId' => $dispute['receptionId'],
                'isArrivage' => !empty($dispute['arrivageId']) ? 1 : 0,
                'disputeNumber' => $dispute['disputeNumber']
            ]),
            'type' => $dispute['type'] ?? '',
            'arrivalNumber' => $this->templating->render('litige/datatableLitigesRowFrom.html.twig', [
                'arrivalNb' => $dispute['arrivalNumber'] ?? '',
                'arrivalId' => $dispute['arrivageId']
            ]),
            'receptionNumber' => $this->templating->render('litige/datatableLitigesRowFrom.html.twig', [
                'receptionNb' => $dispute['receptionNumber'] ?? '',
                'receptionId' => $dispute['receptionId']
            ]),
            'disputeNumber' => $dispute['disputeNumber'],
            'references' => $references,
            'reporter' => $dispute['reporterUsername'],
            'command' => $commands,
            'numCommandeBl' => $numerosBL,
            'buyers' => Stream::from($acheteursArrivage, $acheteursReception)
                ->unique()
                ->join(", "),
            'provider' => $dispute['provider'] ?? '',
            'lastHistoryRecord' => $lastHistoryRecordStr,
            'creationDate' => $dispute['creationDate'] ? $dispute['creationDate']->format($format) : '',
            'updateDate' => $dispute['updateDate'] ? $dispute['updateDate']->format($format) : '',
            'status' => $dispute['status'] ?? '',
            'urgence' => $dispute['emergencyTriggered']
        ];
    }

    public function getLitigeOrigin(): array {
        return [
            Dispute::ORIGIN_ARRIVAGE => $this->translation->translate("Traçabilité", "Arrivages UL", "Divers", "Arrivage UL", false),
            Dispute::ORIGIN_RECEPTION => $this->translation->translate("Ordre", "Réceptions", "Réception", false)
        ];
    }

    public function sendMailToAcheteursOrDeclarant(Dispute $dispute, string $category, $isUpdate = false) {
        $wantSendToBuyersMailStatusChange = $dispute->getStatus()->getSendNotifToBuyer();
        $wantSendToDeclarantMailStatusChange = $dispute->getStatus()->getSendNotifToDeclarant();
        $recipients = [];
        $isArrival = ($category === self::CATEGORY_ARRIVAGE);
        if ($wantSendToBuyersMailStatusChange) {
            $userRepository = $this->entityManager->getRepository(Utilisateur::class);
            $recipients = $isArrival
                ? $userRepository->getDisputeBuyers($dispute)
                : $dispute->getBuyers()->toArray();
        }

        if ($wantSendToDeclarantMailStatusChange && $dispute->getReporter()) {
            $recipients[] = $dispute->getReporter();
        }

        if (!empty($recipients)) {
            $translatedCategory = $isArrival
                ? ["Traçabilité", "Arrivages UL", "Email litige", "un arrivage UL", false]
                : ["Ordre", "Réceptions", "une réception", false];
            $title = fn(string $slug) => (
                !$isUpdate
                    ? ["Traçabilité", "Arrivages UL", "Email litige", "Un litige a été déclaré sur {1} vous concernant :", false, [
                        1 => $this->translation->translateIn($slug, ...$translatedCategory)
                    ]]
                    : ["Traçabilité", "Arrivages UL", "Email litige", "Changement de statut d'un litige sur {1} vous concernant :", false, [
                        1 => $this->translation->translateIn($slug, ...$translatedCategory)
                    ]]
            );
            $subject = fn(string $slug) => (
                !$isUpdate
                    ? ["Traçabilité", "Arrivages UL", "Email litige", "Litige sur {1}", false, [
                        1 => $this->translation->translateIn($slug, ...$translatedCategory)
                    ]]
                    : ["Traçabilité", "Arrivages UL", "Email litige", "Changement de statut d'un litige sur {1}", false, [
                        1 => $this->translation->translateIn($slug, ...$translatedCategory)
                    ]]
            );

            $this->mailerService->sendMail(
                $subject,
                [
                    "name" => 'mails/contents/' . ($isArrival ? 'mailLitigesArrivage' : 'mailLitigesReception') . '.html.twig',
                    "context" => [
                        'disputes' => [$dispute],
                        'title' => $title,
                        'urlSuffix' => $isArrival
                            ? $this->router->generate('arrivage_index')
                            : $this->router->generate('reception_index'),
                    ]
                ],
                $recipients
            );
        }

    }

    public function getColumnVisibleConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getFieldModes('dispute');
        return $this->fieldModesService->getArrayConfig(
            [
                ["name" => "actions", "class" => "noVis", "orderable" => false, "alwaysVisible" => true],
                ["name" => 'disputeNumber', 'title' => $this->translation->translate('Qualité', 'Litiges', 'Numéro de litige')],
                ["name" => 'type', 'title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Détails arrivage UL - Liste des litiges', 'Type')],
                ["name" => 'arrivalNumber', 'title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Divers', 'N° d\'arrivage UL')],
                ["name" => 'receptionNumber', 'title' => $this->translation->translate('Traçabilité', 'Association BR', 'N° de réception')],
                ["name" => 'buyers', 'title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Acheteur(s)')],
                ["name" => 'numCommandeBl', 'title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'N° commande / BL')],
                ["name" => 'reporter', 'title' => $this->translation->translate('Qualité', 'Litiges', 'Déclarant')],
                ["name" => 'command', 'title' => 'N° ligne'],
                ["name" => 'provider', 'title' => $this->translation->translate('Traçabilité', 'Arrivages UL', 'Champs fixes', 'Fournisseur')],
                ["name" => 'references', 'title' => 'Référence'],
                ["name" => 'lastHistoryRecord', 'title' => $this->translation->translate('Qualité', 'Litiges', 'Dernier historique')],
                ["name" => 'creationDate', 'title' => $this->translation->translate('Qualité', 'Litiges', 'Créé le')],
                ["name" => 'updateDate', 'title' => $this->translation->translate('Qualité', 'Litiges', 'Modifié le')],
                ["name" => 'status', 'title' => $this->translation->translate('Qualité', 'Litiges', 'Statut')],
            ],
            [],
            $columnsVisible
        );
    }

    public function createDisputeHistoryRecord(Dispute     $dispute,
                                               Utilisateur $user,
                                               array       $commentPart): DisputeHistoryRecord {

        $comment = Stream::from($commentPart)
            ->filterMap(fn(?string $part) => ($part ? trim($part) : null))
            ->join("\n");

        $historyRecord = new DisputeHistoryRecord();
        $historyRecord
            ->setDate(new DateTime('now'))
            ->setComment(empty($comment) ? $dispute->getStatus()?->getComment() : $comment)
            ->setDispute($dispute)
            ->setUser($user);

        if ($dispute->getStatus()) {
            // set french status name to translate it after
            $historyRecord->setStatusLabel($dispute->getStatus()->getNom());
        }

        if ($dispute->getType()) {
            // set french type label to translate it after
            $historyRecord->setTypeLabel($dispute->getType()->getLabel());
        }

        $dispute->setLastHistoryRecord($historyRecord);

        return $historyRecord;
    }

    /**
     * @return string[]
     */
    public function getCsvHeader(): array {
        return [
            'Numéro de litige',
            'Type',
            'Statut',
            'Date création',
            'Date modification',
            'Déclarant',
            'Historique',
            'Unité logistiques / Réferences',
            'Code barre',
            'QteArticle',
            'Ordre arrivage / réception',
            'N° Commande / BL',
            'Fournisseur',
            'N° ligne',
            'Acheteur(s)',
        ];
    }

    public function getExportGenerator(EntityManagerInterface $entityManager,
                                       DateTime               $dateTimeMin,
                                       DateTime               $dateTimeMax,
                                       array                  $statusIds = []): callable {
        $disputeRepository = $entityManager->getRepository(Dispute::class);
        $disputes = $disputeRepository->iterateBetween($dateTimeMin, $dateTimeMax, $statusIds);

        return function ($handle) use ($entityManager, $disputes) {
            foreach ($disputes as $dispute) {
                $this->putDisputeLine($handle, $dispute);
            }
        };
    }

    public function putDisputeLine($output,
                                   Dispute $dispute,
                                   array $context): void {
        $rows = $this->normalizer->normalize($dispute, null, [
            "usage" => SerializerUsageEnum::CSV_EXPORT,
            ... $context,
        ]);

        foreach ($rows as $row) {
            $this->CSVExportService->putLine($output, $row);
        }
    }

}
