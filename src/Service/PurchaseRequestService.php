<?php


namespace App\Service;

use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\PurchaseRequest;
use App\Entity\Statut;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;

class PurchaseRequestService
{
    private $templating;
    private $router;
    private $user;
    private $em;
    private $userService;
    private $uniqueNumberService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                UniqueNumberService $uniqueNumberService,
                                RouterInterface $router,
                                UserService $userService,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating) {
        $this->templating = $templating;
        $this->uniqueNumberService = $uniqueNumberService;
        $this->em = $entityManager;
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->userService = $userService;
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
            $rows[] = $this->dataRowTransfer($request);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowTransfer(PurchaseRequest $request) {
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

    public function createHeaderDetailsConfig(PurchaseRequest $request): array {
        return [
            ['label' => 'Numero', 'value' => $request->getNumber()],
            ['label' => 'Demandeur', 'value' => FormatHelper::user($request->getRequester())],
            ['label' => 'Acheteur', 'value' => FormatHelper::user($request->getBuyer())],
            ['label' => 'Statut', 'value' => FormatHelper::status($request->getStatus())],
            ['label' => 'Origine', 'value' => FormatHelper::location($request->getOrigin())],
            ['label' => 'Date de création', 'value' => FormatHelper::datetime($request->getCreationDate())],
            ['label' => 'Date de traitement', 'value' => FormatHelper::datetime($request->getProcessingDate())],
            ['label' => 'Date de validation', 'value' => FormatHelper::datetime($request->getValidationDate())],
            ['label' => 'Date de prise en compte', 'value' => FormatHelper::datetime($request->getConsiderationDate())],
        ];
    }
}
