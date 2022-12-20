<?php


namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\StringHelper;

class PurchaseRequestService {

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TokenStorageInterface $tokenStorage;

    #[Required]
    public FormatService $formatService;

    public function getDataForDatatable($params = null)
    {
        $filters = $this->em->getRepository(FiltreSup::class)
            ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PURCHASE_REQUEST, $this->tokenStorage->getToken()->getUser());

        $queryResult = $this->em->getRepository(PurchaseRequest::class)
            ->findByParamsAndFilters($params, $filters);

        $requests = $queryResult['data'];

        $rows = [];
        foreach ($requests as $request) {
            $rows[] = $this->dataRowPurchaseRequest($request);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowPurchaseRequest(PurchaseRequest $request) {
        $url = $this->router->generate('purchase_request_show', [
            "id" => $request->getId()
        ]);

        return [
            'id' => $request->getId(),
            'number' => $request->getNumber(),
            'status' => FormatHelper::status($request->getStatus()),
            'requester' => FormatHelper::user($request->getRequester()),
            'buyer' => FormatHelper::user($request->getBuyer()),
            'creationDate' => FormatHelper::datetime($request->getCreationDate()),
            'processingDate' => FormatHelper::datetime($request->getProcessingDate()),
            'validationDate' => FormatHelper::datetime($request->getValidationDate()),
            'considerationDate' => FormatHelper::datetime($request->getConsiderationDate()),
            'actions' => $this->templating->render('purchase_request/actions.html.twig', [
                'url' => $url,
            ]),
        ];
    }

    public function putPurchaseRequestLine($handle,
                                           CSVExportService $CSVExportService,
                                           array $request,
                                           array $line = []) {
        $CSVExportService->putLine($handle, [
            $request['number'] ?? '',
            $request['statusName'] ?? '',
            $request['requester'] ?? '',
            $request['buyer'] ?? '',
            FormatHelper::datetime($request['creationDate'] ?? null),
            FormatHelper::datetime($request['validationDate'] ?? null),
            FormatHelper::datetime($request['considerationDate'] ?? null),
            FormatHelper::datetime($request['processingDate'] ?? null),
            FormatHelper::html($request['comment'] ?? null),
            $line['reference'] ?? '',
            $line['barcode'] ?? '',
            $line['label'] ?? '',
        ]);
    }

    public function createHeaderDetailsConfig(PurchaseRequest $request): array {
        return [
            ['label' => 'Statut', 'value' => FormatHelper::status($request->getStatus())],
            ['label' => 'Demandeur', 'value' => FormatHelper::user($request->getRequester())],
            ['label' => 'Acheteur', 'value' => FormatHelper::user($request->getBuyer())],
            ['label' => 'Date de création', 'value' => FormatHelper::datetime($request->getCreationDate())],
            ['label' => 'Date de validation', 'value' => FormatHelper::datetime($request->getValidationDate())],
            ['label' => 'Date de prise en compte', 'value' => FormatHelper::datetime($request->getConsiderationDate())],
            ['label' => 'Date de traitement', 'value' => FormatHelper::datetime($request->getProcessingDate())],
            [
                'label' => 'Commentaire',
                'value' => $request->getComment() ?: "",
                'isRaw' => true,
                'colClass' => 'col-sm-6 col-12',
                'isScrollable' => true,
                'isNeededNotEmpty' => true
            ],
            [
                'label' => 'Pièces jointes',
                'value' => $request->getAttachments()->toArray(),
                'isAttachments' => true,
                'isNeededNotEmpty' => true
            ]
        ];
    }

    public function createPurchaseRequest(EntityManagerInterface $entityManager,
                                          ?Statut $status,
                                          ?Utilisateur $requester,
                                          ?string $comment = null,
                                          ?DateTime $validationDate = null,
                                          ?Utilisateur $buyer = null): PurchaseRequest {
        $now =  new DateTime("now");
        $purchase = new PurchaseRequest();
        $purchaseRequestNumber = $this->uniqueNumberService->create($entityManager, PurchaseRequest::NUMBER_PREFIX, PurchaseRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $purchase
            ->setCreationDate($now)
            ->setStatus($status)
            ->setRequester($requester)
            ->setComment(StringHelper::cleanedComment($comment))
            ->setNumber($purchaseRequestNumber)
            ->setValidationDate($validationDate);

        if($buyer) {
            $purchase->setBuyer($buyer);
        }

        return $purchase;
    }

    public function sendMailsAccordingToStatus(PurchaseRequest $purchaseRequest) {
        /** @var Statut $status */
        $status = $purchaseRequest->getStatus();
        $buyerAbleToReceivedMail = $status->getSendNotifToBuyer();
        $requesterAbleToReceivedMail = $status->getSendNotifToDeclarant();

        if (isset($buyerAbleToReceivedMail) || isset($requesterAbleToReceivedMail)) {

            $requester = $purchaseRequest->getRequester() ?? null;
            $buyer = $purchaseRequest->getBuyer() ?? null;

            $mail = ($status->isNotTreated() && $buyerAbleToReceivedMail) ? $buyer : $requester;

            $subject = $status->isTreated()
                ? 'Traitement d\'une demande d\'achat'
                : ($status->isNotTreated()
                    ? 'Création d\'une demande d\'achat'
                    : 'Changement de statut d\'une demande d\'achat');

            $statusName = $this->formatService->status($status);
            $number = $purchaseRequest->getNumber();
            $processingDate = FormatHelper::datetime($purchaseRequest->getProcessingDate(), "", true);
            $title = $status->isTreated()
                ? "Demande d'achat ${number} traitée le ${processingDate} avec le statut ${statusName}"
                : ($status->isNotTreated()
                    ? 'Une demande d\'achat vous concerne'
                    : 'Changement de statut d\'une demande d\'achat vous concernant');

            if (isset($requester)) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // ' . $subject,
                    $this->templating->render('mails/contents/mailPurchaseRequestEvolution.html.twig', [
                        'title' => $title,
                        'purchaseRequest' => $purchaseRequest,
                    ]),
                    $mail
                );
            }
        }
    }

    public function getDataForReferencesDatatable($params = null) {
        $demande = $this->em->find(PurchaseRequest::class, $params);
        $referenceLines = $demande->getPurchaseRequestLines();

        $rows = [];
        foreach ($referenceLines as $referenceLine) {
            $rows[] = $this->dataRowReference($referenceLine);
        }

        return [
            'data' => $rows,
            'recordsTotal' => count($rows),
        ];
    }

    public function dataRowReference(PurchaseRequestLine $line) {
        return [
            'reference' => $line->getReference()->getReference(),
            'libelle' => $line->getReference()->getLibelle(),
            'quantity' => $line->getRequestedQuantity(),
        ];
    }
}
