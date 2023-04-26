<?php

namespace App\Service\ShippingRequest;

use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\Utilisateur;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use App\Service\LanguageService;
use App\Service\MailerService;
use App\Service\TrackingMovementService;
use App\Service\TranslationService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class ShippingRequestService {

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public UserService $userService;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public FreeFieldService $freeFieldService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public TrackingMovementService $trackingMovementService;

    #[Required]
    public VisibleColumnService $visibleColumnService;

    #[Required]
    public Security $security;

    public function getVisibleColumnsConfig(EntityManagerInterface $entityManager, Utilisateur $currentUser): array {
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $columnsVisible = $currentUser->getVisibleColumns()['shippingRequest'];
        $freeFields = $champLibreRepository->findByCategoryTypeAndCategoryCL(CategoryType::DEMANDE_SHIPPING, CategorieCL::DEMANDE_SHIPPING);

        $columns = [
            ['title' => 'Numéro', 'name' => 'trackingNumber'],
            ['title' => 'Statut', 'name' => 'status'],
            ['title' => 'Date de création', 'name' => 'createdAt'],
            ['title' => 'Date de prise en charge souhaitée', 'name' => 'requestCaredAt'],
            ['title' => 'Date de validation', 'name' => 'validatedAt'],
            ['title' => 'Date de planification', 'name' => 'plannedAt'],
            ['title' => 'Date d\'enlèvement prévu', 'name' => 'expectedPickedAt'],
            ['title' => 'Date d\'expédition', 'name' => 'treatedAt'],
            ['title' => 'Demandeur', 'name' => 'requesters'],
            ['title' => 'N° commande client', 'name' => 'customerOrderNumber'],
            ['title' => 'Client', 'name' => 'customerName'],
            ['title' => 'Transporteur', 'name' => 'carrier'],
        ];
        // ajouter tous les autres champs

        return $this->visibleColumnService->getArrayConfig($columns, $freeFields, $columnsVisible);
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager, Request $request) {
        return [];
        $shippingRepository = $entityManager->getRepository(ShippingRequest::class);

        $queryResult = $shippingRepository->findByParamsAndFilters();

        $shippingRequests = $queryResult['data'];

        $rows = [];
        foreach ($shippingRequests as $shipping) {
            $rows[] = $this->dataRowShipping($shipping[0], [
                'totalWeight' => $shipping['totalWeight'],
                'packsCount' => $shipping['packsCount'],
                'packsInDispatchCount' => $shipping['dispatchedPacksCount']
            ]);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    public function dataRowShipping(ShippingRequest $shipping, array $options = []): array
    {
        $row = [

        ];

        return $row;
    }
}
