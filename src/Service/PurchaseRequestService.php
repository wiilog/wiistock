<?php


namespace App\Service;

use App\Entity\FiltreSup;
use App\Entity\PurchaseRequest;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;

class PurchaseRequestService
{
    /** @Required */
    public Twig_Environment $templating;

    /** @Required */
    public RouterInterface $router;

    /** @Required */
    public EntityManagerInterface $em;

    /** @Required */
    public UniqueNumberService $uniqueNumberService;

    private $user;

    public function __construct(TokenStorageInterface $tokenStorage) {
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function getDataForDatatable($params = null)
    {
        $filters = $this->em->getRepository(FiltreSup::class)
            ->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PURCHASE_REQUEST, $this->user);

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
        $now =  new DateTime("now", new DateTimeZone("Europe/Paris"));
        $purchase = new PurchaseRequest();
        $purchaseRequestNumber = $this->uniqueNumberService->createUniqueNumber($entityManager, PurchaseRequest::NUMBER_PREFIX, PurchaseRequest::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);
        $purchase
            ->setCreationDate($now)
            ->setStatus($status)
            ->setRequester($requester)
            ->setComment($comment)
            ->setNumber($purchaseRequestNumber)
            ->setValidationDate($validationDate);

        if($buyer) {
            $purchase->setBuyer($buyer);
        }

        return $purchase;
    }
}
