<?php


namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\ReferenceArticle;
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

class PurchaseRequestService
{

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

    public function dataRowPurchaseRequest(PurchaseRequest $request)
    {
        $url = $this->router->generate('purchase_request_show', [
            "id" => $request->getId()
        ]);

        return [
            'id' => $request->getId(),
            'number' => $request->getNumber(),
            'status' => $this->formatService->status($request->getStatus()),
            'requester' => $this->formatService->user($request->getRequester()),
            'buyer' => $this->formatService->user($request->getBuyer()),
            'creationDate' => $this->formatService->datetime($request->getCreationDate()),
            'processingDate' => $this->formatService->datetime($request->getProcessingDate()),
            'validationDate' => $this->formatService->datetime($request->getValidationDate()),
            'considerationDate' => $this->formatService->datetime($request->getConsiderationDate()),
            'supplier' => $this->formatService->supplier($request->getSupplier()),
            'actions' => $this->templating->render('purchase_request/actions.html.twig', [
                'url' => $url,
            ]),
        ];
    }

    public function putPurchaseRequestLine($handle,
                                           CSVExportService $CSVExportService,
                                           array $request,
                                           array $line = [])
    {
        $CSVExportService->putLine($handle, [
            $request['number'] ?? '',
            $request['statusName'] ?? '',
            $request['requester'] ?? '',
            $request['buyer'] ?? '',
            $this->formatService->datetime($request['creationDate'] ?? null),
            $this->formatService->datetime($request['validationDate'] ?? null),
            $this->formatService->datetime($request['considerationDate'] ?? null),
            $this->formatService->datetime($request['processingDate'] ?? null),
            $this->formatService->html($request['comment'] ?? null),
            $line['reference'] ?? '',
            $line['barcode'] ?? '',
            $line['label'] ?? '',
            $line['supplierName'] ?? '',
        ]);
    }

    public function createHeaderDetailsConfig(PurchaseRequest $request): array
    {
        return [

            ['label' => 'Statut', 'value' => $this->formatService->status($request->getStatus())],
            ['label' => 'Demandeur', 'value' =>  $this->formatService->user($request->getRequester())],
            ['label' => 'Acheteur', 'value' => $this->formatService->user($request->getBuyer())],
            ['label' => 'Date de création', 'value' => $this->formatService->datetime($request->getCreationDate())],
            ['label' => 'Date de validation', 'value' => $this->formatService->datetime($request->getValidationDate())],
            ['label' => 'Date de prise en compte', 'value' => $this->formatService->datetime($request->getConsiderationDate())],
            ['label' => 'Date de traitement', 'value' => $this->formatService->datetime($request->getProcessingDate())],
            ['label' => 'Fournisseur', 'value' => $this->formatService->supplier($request->getSupplier())],
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

    public function createPurchaseRequest(?Statut      $status,
                                          ?Utilisateur $requester,
                                                       $options = []): PurchaseRequest
    {
        $comment = $options["comment"] ?? null;
        $validationDate = $options["validationDate"] ?? null;
        $buyer = $options["buyer"] ?? null;
        $supplier = $options["supplier"] ?? null;
        $now = new DateTime("now");
        $purchase = new PurchaseRequest();
        $purchaseRequestNumber = $this->uniqueNumberService->create($this->em, PurchaseRequest::NUMBER_PREFIX, PurchaseRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $purchase
            ->setCreationDate($now)
            ->setStatus($status)
            ->setRequester($requester)
            ->setComment(StringHelper::cleanedComment($comment))
            ->setNumber($purchaseRequestNumber)
            ->setSupplier($supplier)
            ->setValidationDate($validationDate);

        if ($buyer) {
            $purchase->setBuyer($buyer);
        }

        return $purchase;
    }

    public function createPurchaseRequestLine(?ReferenceArticle $reference,
                                              ?int              $requestedQuantity,
                                                                $options = []): PurchaseRequestLine
    {
        $supplier = $options["supplier"] ?? null;
        $purchaseRequest = $options["purchaseRequest"] ?? null;
        $purchaseLine = new PurchaseRequestLine();
        $purchaseLine
            ->setReference($reference)
            ->setRequestedQuantity($requestedQuantity)
            ->setSupplier($supplier)
            ->setPurchaseRequest($purchaseRequest);

        return $purchaseLine;
    }

    public function sendMailsAccordingToStatus(PurchaseRequest $purchaseRequest, array $options = []) {
        $customSubject = $options['customSubject'] ?? null;


        /** @var Statut $status */
        $status = $purchaseRequest->getStatus();
        $buyerAbleToReceivedMail = $status->getSendNotifToBuyer();
        $requesterAbleToReceivedMail = $status->getSendNotifToDeclarant();

        if (isset($buyerAbleToReceivedMail) || isset($requesterAbleToReceivedMail)) {

            $requester = $purchaseRequest->getRequester() ?? null;
            $buyer = $purchaseRequest->getBuyer() ?? null;

            $mail = ($status->isNotTreated() && $buyerAbleToReceivedMail && $buyer) ? $buyer : $requester;

            $subject = $customSubject ?: (
                $status->isTreated()
                    ? 'Traitement d\'une demande d\'achat'
                    : ($status->isNotTreated()
                        ? 'Création d\'une demande d\'achat'
                        : 'Changement de statut d\'une demande d\'achat')
            );

            $statusName = $this->formatService->status($status);
            $number = $purchaseRequest->getNumber();
            $processingDate = $this->formatService->datetime($purchaseRequest->getProcessingDate(), "", true);
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

    public function getDataForReferencesDatatable($params = null)
    {
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

    public function dataRowReference(PurchaseRequestLine $line)
    {
        return [
            'reference' => $line->getReference()->getReference(),
            'libelle' => $line->getReference()->getLibelle(),
            'quantity' => $line->getRequestedQuantity(),
        ];
    }
}
