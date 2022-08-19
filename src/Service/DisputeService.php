<?php

namespace App\Service;

use App\Entity\DisputeHistoryRecord;
use App\Entity\FiltreSup;
use App\Entity\Dispute;
use App\Entity\Pack;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use App\Service\TranslationService;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;

class DisputeService {

    public const CATEGORY_ARRIVAGE = 'un arrivage';
    public const CATEGORY_RECEPTION = 'une réception';
    public const PUT_LINE_ARRIVAL = 'arrival';
    public const PUT_LINE_RECEPTION = 'reception';

    /** @Required */
    public AttachmentService $attachmentService;

    /** @Required */
    public RouterInterface $router;

    /**
     * @var Twig_Environment
     */
    private $templating;

    private $security;

    private $entityManager;
    private $translation;
    private $mailerService;
    private $visibleColumnService;
    private $CSVExportService;

    public function __construct(EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                TranslationService $translation,
                                MailerService $mailerService,
                                CSVExportService $CSVExportService,
                                VisibleColumnService $visibleColumnService,
                                Security $security) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->translation = $translation;
        $this->security = $security;
        $this->mailerService = $mailerService;
        $this->visibleColumnService = $visibleColumnService;
        $this->CSVExportService = $CSVExportService;
    }

    public function getDataForDatatable($params = null): array {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $disputeRepository = $this->entityManager->getRepository(Dispute::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPUTE, $this->security->getUser());

        $queryResult = $disputeRepository->findByParamsAndFilters($params, $filters, $this->security->getUser(), $this->visibleColumnService);
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
        $format = $user && $user->getDateFormat() ? ($user->getDateFormat() . ' H:i') : 'd/m/Y H:i';

        $lastHistoryRecordStr = ($lastHistoryRecordDate && $lastHistoryRecordComment)
            ? (FormatHelper::datetime($lastHistoryRecordDate, "", false, $this->security->getUser()) . ' : ' . nl2br($lastHistoryRecordComment))
            : '';

        $commands = $receptionReferenceArticleRepository->getAssociatedIdAndOrderNumbers($disputeId)[$disputeId];
        $references = $receptionReferenceArticleRepository->getAssociatedIdAndReferences($disputeId)[$disputeId];

        $isNumeroBLJson = !empty($dispute['arrivageId']);
        $numerosBL = isset($dispute['numCommandeBl'])
            ? ($isNumeroBLJson
                ? implode(', ', json_decode($dispute['numCommandeBl'], true))
                : $dispute['numCommandeBl'])
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
            Dispute::ORIGIN_ARRIVAGE => $this->translation->translate("Traçabilité", "Flux - Arrivages", "Divers", "Arrivage"),
            Dispute::ORIGIN_RECEPTION => "manque la traduction (dans ordre -> reception)"
        ];
    }

    public function sendMailToAcheteursOrDeclarant(Dispute $dispute, string $category, $isUpdate = false) {
        $wantSendToBuyersMailStatusChange = $dispute->getStatus()->getSendNotifToBuyer();
        $wantSendToDeclarantMailStatusChange = $dispute->getStatus()->getSendNotifToDeclarant();
        $recipients = [];
        $isArrival = ($category === self::CATEGORY_ARRIVAGE);
        if ($wantSendToBuyersMailStatusChange) {
            $disputeRepository = $this->entityManager->getRepository(Dispute::class);
            $recipients = $isArrival
                ? $disputeRepository->getAcheteursArrivageByDisputeId($dispute->getId())
                : array_reduce($dispute->getBuyers()->toArray(), function(array $carry, Utilisateur $buyer) {
                    return array_merge(
                        $carry,
                        $buyer->getMainAndSecondaryEmails()
                    );
                }, []);
        }

        if ($wantSendToDeclarantMailStatusChange && $dispute->getReporter()) {
            $recipients = array_merge($recipients, $dispute->getReporter()->getMainAndSecondaryEmails());
        }

        if (!empty($recipients)) {
            $translatedCategory = $isArrival ? $category : $this->translation->trans('réception.une réception');
            $title = !$isUpdate
                ? ('Un litige a été déclaré sur ' . $translatedCategory . ' vous concernant :')
                : ('Changement de statut d\'un litige sur ' . $translatedCategory . ' vous concernant :');
            $subject = !$isUpdate
                ? ('FOLLOW GT // Litige sur ' . $translatedCategory)
                : 'FOLLOW GT // Changement de statut d\'un litige sur ' . $translatedCategory;

            $this->mailerService->sendMail(
                $subject,
                $this->templating->render('mails/contents/' . ($isArrival ? 'mailLitigesArrivage' : 'mailLitigesReception') . '.html.twig', [
                    'disputes' => [$dispute],
                    'title' => $title,
                    'urlSuffix' => $isArrival
                        ? $this->router->generate('arrivage_index')
                        : $this->router->generate('reception_index')
                ]),
                $recipients
            );
        }

    }

    public function getColumnVisibleConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getVisibleColumns()['dispute'];
        return $this->visibleColumnService->getArrayConfig(
            [
                ["name" => 'disputeNumber', 'title' => 'Numéro du litige'],
                ["name" => 'type', 'title' => 'Type'],
                ["name" => 'arrivalNumber', 'title' => 'arrivage.n° d\'arrivage', "translated" => true],
                ["name" => 'receptionNumber', 'title' => 'réception.n° de réception', "translated" => true],
                ["name" => 'buyers', 'title' => 'Acheteur'],
                ["name" => 'numCommandeBl', 'title' => 'N° commande / BL'],
                ["name" => 'reporter', 'title' => 'Déclarant'],
                ["name" => 'command', 'title' => 'N° ligne'],
                ["name" => 'provider', 'title' => 'Fournisseur'],
                ["name" => 'references', 'title' => 'Référence'],
                ["name" => 'lastHistoryRecord', 'title' => 'Dernier historique'],
                ["name" => 'creationDate', 'title' => 'Créé le'],
                ["name" => 'updateDate', 'title' => 'Modifié le'],
                ["name" => 'status', 'title' => 'Statut'],
            ],
            [],
            $columnsVisible
        );
    }

    public function putDisputeLine(EntityManagerInterface $manager,
                                   string $mode,
                                          $handle,
                                   array  $dispute,
                                   array  $associatedIdAndReferences = [],
                                   array  $associatedIdsAndOrderNumbers = [],
                                   array  $articles = []): void
    {
        if (!in_array($mode, [self::PUT_LINE_ARRIVAL, self::PUT_LINE_RECEPTION])) {
            throw new \InvalidArgumentException('Invalid mode');
        }

        $userRepository = $manager->getRepository(Utilisateur::class);
        $buyers = join(" / ", $userRepository->getBuyers($dispute["id"]));

        $row = [
            $dispute["number"],
            $dispute["type"],
            $dispute["status"],
            FormatHelper::date($dispute["creationDate"], null, false, $this->security->getUser()),
            FormatHelper::date($dispute["updateDate"], null, false, $this->security->getUser()),
        ];

        if ($mode === self::PUT_LINE_ARRIVAL) {
            $packs = $manager->getRepository(Pack::class)->findBy(["disputes" => $dispute["id"]]);
            $arrival = ($packs->count() > 0 && $packs->first()->getArrivage())
                ? $packs->first()->getArrivage()
                : null;
            $arrivalNumber = $arrival?->getNumeroArrivage() ?? '';
            $orderNumbers = Stream::from($arrival?->getNumeroCommandeList() ?? [])->join(' / ');
            $supplier = FormatHelper::supplier($arrival?->getFournisseur());

            foreach ($packs as $pack) {
                $packCode = $pack->getCode();

                $row = array_merge($row, [
                    $packCode,
                    '',
                    '',
                    $arrivalNumber,
                    $orderNumbers,
                    $dispute["reporter"],
                    $supplier,
                    '',
                    $buyers,
                    FormatHelper::date($dispute["lastHistoryDate"], null, false, $this->security->getUser()),
                    $dispute["lastHistoryUser"],
                    $dispute["lastHistoryComment"],
                ]);

                $this->CSVExportService->putLine($handle, $row);
            }
        }
        else if ($mode === self::PUT_LINE_RECEPTION) {
            $firstArticle = $articles[0] ?? null;

            $receptionNumber = $firstArticle ? $firstArticle['receptionNumber'] : '';
            $receptionSupplier = $firstArticle ? $firstArticle['supplier'] : '';
            $receptionOrderNumber = $firstArticle ? $firstArticle['receptionOrderNumber'] : '';

            $references = $associatedIdAndReferences[$dispute["id"]];
            $orderNumbers = $associatedIdsAndOrderNumbers[$dispute["id"]];

            foreach ($articles as $article) {
                $row = array_merge($row, [
                    $references,
                    $article['barcode'],
                    $article['quantity'],
                    $receptionNumber,
                    $receptionOrderNumber,
                    $dispute["reporter"],
                    $receptionSupplier,
                    $orderNumbers,
                    $buyers,
                    FormatHelper::date($dispute["lastHistoryDate"], null, false, $this->security->getUser()),
                    $dispute["lastHistoryUser"],
                    $dispute["lastHistoryComment"],
                ]);

                $this->CSVExportService->putLine($handle, $row);
            }
        }
    }

    public function createDisputeAttachments(Dispute                $dispute,
                                             Request                $request,
                                             EntityManagerInterface $entityManager): void {
        $attachments = $this->attachmentService->createAttachements($request->files);
        foreach($attachments as $attachment) {
            $entityManager->persist($attachment);
            $dispute->addAttachment($attachment);
        }
        $entityManager->persist($dispute);
        $entityManager->flush();
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
            ->setComment($comment ?: null)
            ->setDispute($dispute)
            ->setUser($user);

        if ($dispute->getStatus()) {
            $historyRecord->setStatusLabel($dispute->getStatus()->getNom());
        }

        if ($dispute->getType()) {
            $historyRecord->setTypeLabel($dispute->getType()->getLabel());
        }

        $dispute->setLastHistoryRecord($historyRecord);

        return $historyRecord;
    }

}
