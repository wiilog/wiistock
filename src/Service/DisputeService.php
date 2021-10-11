<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\DisputeHistoryRecord;
use App\Entity\FiltreSup;
use App\Entity\Dispute;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;
use App\Repository\DisputeRepository;
use Exception;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DisputeService {

    public const CATEGORY_ARRIVAGE = 'un arrivage';
    public const CATEGORY_RECEPTION = 'une réception';
    public const PUT_LINE_ARRIVAL = 'arrival';
    public const PUT_LINE_RECEPTION = 'reception';

    /** @Required */
    public AttachmentService $attachmentService;

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var UserService
     */
    private $userService;

    private $security;

    private $entityManager;
    private $translator;
    private $mailerService;
    private $visibleColumnService;
    private $CSVExportService;

    public function __construct(UserService $userService,
                                RouterInterface $router,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating,
                                TranslatorInterface $translator,
                                MailerService $mailerService,
                                CSVExportService $CSVExportService,
                                VisibleColumnService $visibleColumnService,
                                Security $security) {
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->router = $router;
        $this->userService = $userService;
        $this->security = $security;
        $this->mailerService = $mailerService;
        $this->visibleColumnService = $visibleColumnService;
        $this->CSVExportService = $CSVExportService;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws Exception
     */
    public function getDataForDatatable($params = null) {

        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $disputeRepository = $this->entityManager->getRepository(Dispute::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DISPUTE, $this->security->getUser());

        $queryResult = $disputeRepository->findByParamsAndFilters($params, $filters);
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

    /**
     * @param array $dispute
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowDispute($dispute) {
        $disputeRepository = $this->entityManager->getRepository(Dispute::class);

        $disputeId = $dispute['id'];
        $acheteursArrivage = $disputeRepository->getAcheteursArrivageByDisputeId($disputeId, 'username');
        $acheteursReception = $disputeRepository->getAcheteursReceptionByDisputeId($disputeId, 'username');

        $lastHistoryRecordDate = $dispute['lastHistoryRecord_date'];
        $lastHistoryRecordComment = $dispute['lastHistoryRecord_comment'];

        $lastHistoryRecordStr = ($lastHistoryRecordDate && $lastHistoryRecordComment)
            ? (FormatHelper::datetime($lastHistoryRecordDate) . ' : ' . nl2br($lastHistoryRecordComment))
            : '';

        $commands = $disputeRepository->getCommandesByDisputeId($disputeId);

        $references = $disputeRepository->getReferencesByDisputeId($disputeId);

        $isNumeroBLJson = !empty($dispute['arrivageId']);
        $numerosBL = isset($dispute['numCommandeBl'])
            ? ($isNumeroBLJson
                ? implode(', ', json_decode($dispute['numCommandeBl'], true))
                : $dispute['numCommandeBl'])
            : '';

        $row = [
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
            'creationDate' => $dispute['creationDate'] ? $dispute['creationDate']->format('d/m/Y H:i') : '',
            'updateDate' => $dispute['updateDate'] ? $dispute['updateDate']->format('d/m/Y H:i') : '',
            'status' => $dispute['status'] ?? '',
            'urgence' => $dispute['emergencyTriggered']
        ];
        return $row;
    }

    public function getLitigeOrigin(): array {
        return [
            Dispute::ORIGIN_ARRIVAGE => $this->translator->trans('arrivage.arrivage'),
            Dispute::ORIGIN_RECEPTION => $this->translator->trans('réception.réception')
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
            $translatedCategory = $isArrival ? $category : $this->translator->trans('réception.une réception');
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
                    'urlSuffix' => ($isArrival ? '/arrivage' : '/reception')
                ]),
                $recipients
            );
        }

    }

    public function getColumnVisibleConfig(Utilisateur $currentUser): array {
        $columnsVisible = $currentUser->getColumnsVisibleForLitige();
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

    public function putDisputeLine(string            $mode,
                                                     $handle,
                                   DisputeRepository $disputeRepository,
                                   Dispute           $dispute) {

        if (!in_array($mode, [self::PUT_LINE_ARRIVAL, self::PUT_LINE_RECEPTION])) {
            throw new \InvalidArgumentException('Invalid mode');
        }

        if ($mode === self::PUT_LINE_ARRIVAL) {
            $colis = $dispute->getPacks();
            foreach ($colis as $coli) {
                $colis = $dispute->getPacks();
                /** @var Arrivage $arrivage */
                $arrivage = ($colis->count() > 0 && $colis->first()->getArrivage())
                    ? $colis->first()->getArrivage()
                    : null;
                $acheteurs = $arrivage->getAcheteurs()->toArray();
                $buyersMailsStr = implode('/', array_map(function(Utilisateur $acheteur) {
                    return $acheteur->getEmail();
                }, $acheteurs));

                $row = $dispute->serialize();

                $row[] = $coli->getCode();
                $row[] = ' ';
                $row[] = '';

                $row[] = $arrivage ? $arrivage->getNumeroArrivage() : '';

                $numeroCommandeList = $arrivage ? $arrivage->getNumeroCommandeList() : [];
                $row[] = implode(' / ', $numeroCommandeList); // N° de commandes
                $row[] = FormatHelper::user($dispute->getReporter());
                $fournisseur = $arrivage ? $arrivage->getFournisseur() : null;
                $row[] = $fournisseur ? $fournisseur->getNom() : '';
                $row[] = ''; // N° de ligne
                $row[] = $buyersMailsStr;
                $lastHistoryRecord = $dispute->getLastHistoryRecord();
                if ($lastHistoryRecord) {
                    $row[] = FormatHelper::datetime($lastHistoryRecord->getDate());
                    $row[] = FormatHelper::user($lastHistoryRecord->getUser());
                    $row[] = $lastHistoryRecord->getComment();
                }
                $this->CSVExportService->putLine($handle, $row);
            }
        } else if ($mode === self::PUT_LINE_RECEPTION) {
            $articles = $dispute->getArticles();
            foreach ($articles as $article) {
                $buyers = $dispute->getBuyers()->toArray();
                $buyersMailsStr = implode('/', array_map(function(Utilisateur $acheteur) {
                    return $acheteur->getEmail();
                }, $buyers));

                $row = $dispute->serialize();

                $referencesStr = implode(', ', $disputeRepository->getReferencesByDisputeId($dispute->getId()));

                $row[] = $referencesStr;

                /** @var Article $firstArticle */
                $firstArticle = ($articles->count() > 0 ? $articles->first() : null);
                $qteArticle = $article->getQuantite();
                $receptionRefArticle = isset($firstArticle) ? $firstArticle->getReceptionReferenceArticle() : null;
                $reception = isset($receptionRefArticle) ? $receptionRefArticle->getReception() : null;
                $row[] = $article->getBarCode();
                $row[] = $qteArticle;
                $row[] = (isset($reception) ? $reception->getNumber() : '');

                $row[] = (isset($reception) ? $reception->getOrderNumber() : null);

                $row[] = FormatHelper::user($dispute->getReporter());
                $fournisseur = (isset($reception) ? $reception->getFournisseur() : null);
                $row[] = isset($fournisseur) ? $fournisseur->getNom() : '';

                $row[] = implode(', ', $disputeRepository->getCommandesByDisputeId($dispute->getId()));
                $row[] = $buyersMailsStr;

                $lastHistoryRecord = $dispute->getLastHistoryRecord();
                if ($lastHistoryRecord) {
                    $row[] = FormatHelper::datetime($lastHistoryRecord->getDate());
                    $row[] = FormatHelper::user($lastHistoryRecord->getUser());
                    $row[] = $lastHistoryRecord->getComment();
                }
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

}
