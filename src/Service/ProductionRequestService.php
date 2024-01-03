<?php

namespace App\Service;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\ProductionRequest;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class ProductionRequestService
{

    #[Required]
    public VisibleColumnService $visibleColumnService;
    #[Required]
    public Security $security;
    #[Required]
    public FormatService $formatService;
    #[Required]
    public RouterInterface $router;
    #[Required]
    public Twig_Environment $templating;
    #[Required]
    public FreeFieldService $freeFieldService;
    #[Required]
    public EntityManagerInterface $entityManager;
    private ?array $freeFieldsConfig = null;

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::PRODUCTION, CategorieCL::PRODUCTION_REQUEST);
        $columnsVisible = $currentUser->getVisibleColumns()['productionRequest'];

        $columns = [
            ['name' => 'actions', 'alwaysVisible' => true, 'orderable' => false, 'class' => 'noVis'],
            ['title' => 'Numéro de demande', 'name' => 'number'],
            ['title' => 'Date de création', 'name' => 'createdAt'],
            ['title' => 'Traité par', 'name' => 'treatedBy'],
            ['title' => 'Type', 'name' => 'type'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Date de demande', 'name' => 'expectedAt'],
            ['title' => 'Emplacement', 'name' => 'dropLocation'],
            ['title' => 'Numéro de ligne', 'name' => 'lineNumber'],
            ['title' => 'Numéro OF', 'name' => 'manufacturingOrderNumber'],
            ['title' => 'Code article', 'name' => 'productArticleCode'],
            ['title' => 'Quantité', 'name' => 'quantity'],
            ['title' => 'Urgence', 'name' => 'emergency'],
            ['title' => 'Numéro de projet', 'name' => 'projectNumber'],
            ['title' => 'Commentaire', 'name' => 'comment'],
        ];

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request) : array{
        $productionRepository = $entityManager->getRepository(ProductionRequest::class);

        /*
        todo WIIS-10759
        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_SHIPPING, $this->security->getUser());
        */

        $queryResult = $productionRepository->findByParamsAndFilters(
            $request->request,
            /* todo : todo WIIS-10759 $filters */ [],
            $this->visibleColumnService,
            [
                'user' => $this->security->getUser(),
            ]
        );

        $shippingRequests = $queryResult['data'];

        $rows = [];
        foreach ($shippingRequests as $shipping) {
            $rows[] = $this->dataRowProduction($shipping);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowProduction(ProductionRequest $productionRequest): array
    {
        $formatService = $this->formatService;
        $typeColor = $productionRequest->getType()->getColor();

        if (!isset($this->freeFieldsConfig)) {
            $this->freeFieldsConfig = $this->freeFieldService->getListFreeFieldConfig($this->entityManager, CategorieCL::PRODUCTION_REQUEST, CategoryType::PRODUCTION);
        }

        $url = $this->router->generate('production_request_show', [
            "id" => $productionRequest->getId()
        ]);

        $row = [
            "actions" => $this->templating->render('production_request/actions.html.twig', [
                'url' => $url,
            ]),
            "number" => $productionRequest->getNumber() ?? '',
            "createdAt" => $formatService->datetime($productionRequest->getCreatedAt()),
            'treatedBy' => $this->formatService->user($productionRequest->getTreatedBy()),
            'type' => "
                <div class='d-flex align-items-center'>
                    <span class='dt-type-color mr-2' style='background-color: $typeColor;'></span>
                    {$this->formatService->type($productionRequest->getType())}
                </div>
            ",
            "status" => $formatService->status($productionRequest->getStatus()),
            "expectedAt" => $formatService->datetime($productionRequest->getExpectedAt()),
            "dropLocation" => $formatService->location($productionRequest->getDropLocation()),
            "lineNumber" => $productionRequest->getLineNumber(),
            "manufacturingOrderNumber" => $productionRequest->getManufacturingOrderNumber(),
            "productArticleCode" => $productionRequest->getProductArticleCode(),
            "quantity" => $productionRequest->getQuantity(),
            "emergency" => $productionRequest->getEmergency() ? $productionRequest->getEmergency() : 'Non',
            "projectNumber" => $productionRequest->getProjectNumber(),
            "comment" => $productionRequest->getComment(),
        ];

        foreach ($this->freeFieldsConfig as $freeFieldId => $freeField) {
            $freeFieldName = $this->visibleColumnService->getFreeFieldName($freeFieldId);
            $freeFieldValue = $productionRequest->getFreeFieldValue($freeFieldId);
            $row[$freeFieldName] = $this->formatService->freeField($freeFieldValue, $freeField);
        }

        return $row;
    }

}
