<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\FiltreSup;
use App\Entity\Litige;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;
use WiiCommon\Helper\Stream;
use App\Repository\LitigeRepository;
use Exception;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class LitigeService {

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
        $litigeRepository = $this->entityManager->getRepository(Litige::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_LITIGE, $this->security->getUser());

        $queryResult = $litigeRepository->findByParamsAndFilters($params, $filters);
        $litiges = $queryResult['data'];

        $rows = [];
        foreach ($litiges as $litige) {
            $rows[] = $this->dataRowLitige($litige);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param array $litige
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowLitige($litige) {
        $litigeRepository = $this->entityManager->getRepository(Litige::class);

        $litigeId = $litige['id'];
        $acheteursArrivage = $litigeRepository->getAcheteursArrivageByLitigeId($litigeId, 'username');
        $acheteursReception = $litigeRepository->getAcheteursReceptionByLitigeId($litigeId, 'username');

        $lastHistoric = $litigeRepository->getLastHistoricByLitigeId($litigeId);
        $lastHistoricStr = $lastHistoric ? $lastHistoric['date']->format('d/m/Y H:i') . ' : ' . nl2br($lastHistoric['comment']) : '';

        $commands = $litigeRepository->getCommandesByLitigeId($litigeId);

        $references = $litigeRepository->getReferencesByLitigeId($litigeId);

        $isNumeroBLJson = !empty($litige['arrivageId']);
        $numerosBL = isset($litige['numCommandeBl'])
            ? ($isNumeroBLJson
                ? implode(', ', json_decode($litige['numCommandeBl'], true))
                : $litige['numCommandeBl'])
            : '';

        $row = [
            'actions' => $this->templating->render('litige/datatableLitigesRow.html.twig', [
                'litigeId' => $litige['id'],
                'arrivageId' => $litige['arrivageId'],
                'receptionId' => $litige['receptionId'],
                'isArrivage' => !empty($litige['arrivageId']) ? 1 : 0,
                'disputeNumber' => $litige['disputeNumber']
            ]),
            'type' => $litige['type'] ?? '',
            'arrivalNumber' => $this->templating->render('litige/datatableLitigesRowFrom.html.twig', [
                'arrivalNb' => $litige['arrivalNumber'] ?? '',
                'arrivalId' => $litige['arrivageId']
            ]),
            'receptionNumber' => $this->templating->render('litige/datatableLitigesRowFrom.html.twig', [
                'receptionNb' => $litige['receptionNumber'] ?? '',
                'receptionId' => $litige['receptionId']
            ]),
            'disputeNumber' => $litige['disputeNumber'],
            'references' => $references,
            'declarant' => $litige['declarantUsername'],
            'command' => $commands,
            'numCommandeBl' => $numerosBL,
            'buyers' => Stream::from($acheteursArrivage, $acheteursReception)
                ->unique()
                ->join(", "),
            'provider' => $litige['provider'] ?? '',
            'lastHistoric' => $lastHistoricStr,
            'creationDate' => $litige['creationDate'] ? $litige['creationDate']->format('d/m/Y H:i') : '',
            'updateDate' => $litige['updateDate'] ? $litige['updateDate']->format('d/m/Y H:i') : '',
            'status' => $litige['status'] ?? '',
            'urgence' => $litige['emergencyTriggered']
        ];
        return $row;
    }

    public function getLitigeOrigin(): array {
        return [
            Litige::ORIGIN_ARRIVAGE => $this->translator->trans('arrivage.arrivage'),
            Litige::ORIGIN_RECEPTION => $this->translator->trans('réception.réception')
        ];
    }

    public function sendMailToAcheteursOrDeclarant(Litige $litige, string $category, $isUpdate = false) {
        $wantSendToBuyersMailStatusChange = $litige->getStatus()->getSendNotifToBuyer();
        $wantSendToDeclarantMailStatusChange = $litige->getStatus()->getSendNotifToDeclarant();
        $recipients = [];
        $isArrival = ($category === self::CATEGORY_ARRIVAGE);
        if ($wantSendToBuyersMailStatusChange) {
            $litigeRepository = $this->entityManager->getRepository(Litige::class);
            $recipients = $isArrival
                ? $litigeRepository->getAcheteursArrivageByLitigeId($litige->getId())
                : array_reduce($litige->getBuyers()->toArray(), function(array $carry, Utilisateur $buyer) {
                    return array_merge(
                        $carry,
                        $buyer->getMainAndSecondaryEmails()
                    );
                }, []);
        }

        if ($wantSendToDeclarantMailStatusChange && $litige->getDeclarant()) {
            $recipients = array_merge($recipients, $litige->getDeclarant()->getMainAndSecondaryEmails());
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
                    'litiges' => [$litige],
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
                ["name" => 'declarant', 'title' => 'Déclarant'],
                ["name" => 'command', 'title' => 'N° ligne'],
                ["name" => 'provider', 'title' => 'Fournisseur'],
                ["name" => 'references', 'title' => 'Référence'],
                ["name" => 'lastHistoric', 'title' => 'Dernier historique'],
                ["name" => 'creationDate', 'title' => 'Créé le'],
                ["name" => 'updateDate', 'title' => 'Modifié le'],
                ["name" => 'status', 'title' => 'Statut'],
            ],
            [],
            $columnsVisible
        );
    }

    public function putDisputeLine(string $mode,
                                   $handle,
                                   LitigeRepository $litigeRepository,
                                   Litige $dispute) {

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
                $declarant = $dispute->getDeclarant() ? $dispute->getDeclarant()->getUsername() : '';
                $row[] = $declarant;
                $fournisseur = $arrivage ? $arrivage->getFournisseur() : null;
                $row[] = $fournisseur ? $fournisseur->getNom() : '';
                $row[] = ''; // N° de ligne
                $row[] = $buyersMailsStr;
                $disputeHistory = $dispute->getDisputeHistory();
                if (!$disputeHistory->isEmpty()) {
                    $historic = $disputeHistory->last();
                    $row[] = $historic->getDate() ? $historic->getDate()->format('d/m/Y H:i') : '';
                    $row[] = $historic->getUser() ? $historic->getUser()->getUsername() : '';
                    $row[] = $historic->getComment();
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

                $referencesStr = implode(', ', $litigeRepository->getReferencesByLitigeId($dispute->getId()));

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

                $declarant = $dispute->getDeclarant() ? $dispute->getDeclarant()->getUsername() : '';
                $row[] = $declarant;
                $fournisseur = (isset($reception) ? $reception->getFournisseur() : null);
                $row[] = isset($fournisseur) ? $fournisseur->getNom() : '';

                $row[] = implode(', ', $litigeRepository->getCommandesByLitigeId($dispute->getId()));

                $disputeHistory = $dispute->getDisputeHistory();

                $row[] = $buyersMailsStr;
                if (!$disputeHistory->isEmpty()) {
                    $historic = $disputeHistory->last();
                    $row[] = ($historic->getDate() ? $historic->getDate()->format('d/m/Y H:i') : '');
                    $row[] = $historic->getUser() ? $historic->getUser()->getUsername() : '';
                    $row[] = $historic->getComment();
                }
                $this->CSVExportService->putLine($handle, $row);
            }
        }
    }



    public function createDisputeAttachments(Litige                 $dispute,
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
