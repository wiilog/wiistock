<?php

namespace App\Controller;


use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emergency\Emergency;
use App\Entity\Type\CategoryType;
use App\Entity\Emergency\StockEmergency;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use App\Entity\Transporteur;
use App\Entity\Type\Type;
use App\Exceptions\FormException;
use App\Service\EmergencyService;
use App\Entity\Menu;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route('/emergency', name: 'emergency_')]
class EmergencyController extends AbstractController {

    #[Route('/', name: 'index', methods: [self::GET])]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_EMERGENCY])]
    public function index(EntityManagerInterface $entityManager,
                          UserService            $userService,
                          EmergencyService       $emergencyService): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $carrierRepository = $entityManager->getRepository(Transporteur::class);
        $currentUser = $userService->getUser();
        $columns = $emergencyService->getVisibleColumnsConfig($entityManager, $currentUser);

        $emergencyTypes = $typeRepository->findByCategoryLabels([CategoryType::TRACKING_EMERGENCY, CategoryType::STOCK_EMERGENCY], null, [
            'onlyActive' => true,
        ]);

        return $this->render('emergency/index.html.twig', [
            "initial_visible_columns" => $columns,
            'types' => Stream::from($emergencyTypes)
                ->map(static fn(Type $type) => [
                    'id' => $type->getId(),
                    'label' => $type->getLabel(),
                ]),
            "carriers" => $carrierRepository->findAllSorted(),
            'modalNewEmergencyConfig' => $emergencyService->getEmergencyConfig($entityManager),
        ]);
    }

    #[Route('/linked-reference-article', name: 'linked_reference_article_api', options: ['expose' => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::QUALI, Action::CREATE_EMERGENCY])]
    public function linkedReferenceArticleApi(EntityManagerInterface $entityManager,
                                              Request                $request): Response {

        $supplierId = $request->query->get('supplier');
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $supplierRepository = $entityManager->getRepository(Fournisseur::class);

        $supplier = $supplierId
            ? $supplierRepository->find($supplierId)
            : null;

        if (!$supplierId) {
            throw new RuntimeException("Valid supplier required");
        }

        $referenceArticles = $referenceArticleRepository->findBySupplier($supplier);

        return $this->json([
            "data" => Stream::from($referenceArticles)
                ->map(static fn(ReferenceArticle $referenceArticle) => [
                    "reference" => $referenceArticle->getReference(),
                    "label"     => $referenceArticle->getLibelle(),
                    "barcode"   => $referenceArticle->getBarCode(),
                ])
                ->toArray(),
        ]);
    }

    #[Route('/new', name: 'new', options: ['expose' => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::QUALI, Action::CREATE_EMERGENCY], mode: HasPermission::IN_JSON)]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        EmergencyService       $emergencyService): JsonResponse {
        $typeRepository = $entityManager->getRepository(Type::class);

        $type = $typeRepository->find($request->request->get(FixedFieldEnum::type->name));

        $isStockEmergency = $type->getCategory()->getLabel() === CategoryType::STOCK_EMERGENCY;
        $emergency = $isStockEmergency
            ? new StockEmergency()
            : new TrackingEmergency();

        $emergencyService->updateEmergency($entityManager, $emergency, $request);

        $entityManager->persist($emergency);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "L'urgence a été créée avec succès.",
        ]);
    }

    #[Route('/modifier-api/{emergency}', name: 'edit_api', options: ['expose' => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::QUALI, Action::CREATE_EMERGENCY], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface $entityManager,
                            EmergencyService       $emergencyService,
                            Emergency              $emergency): JsonResponse {
        if ($emergency->getClosedAt()) {
            throw new FormException("L'urgence est cloturée, vous ne pouvez pas la modifier.");
        }

        return $this->json([
            'html' => $this->renderView('emergency/form.html.twig', $emergencyService->getEmergencyConfig($entityManager, $emergency)),
        ]);
    }

    #[Route('/edit', name: 'edit', options: ['expose' => true], methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::QUALI, Action::CREATE_EMERGENCY], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         EmergencyService       $emergencyService): JsonResponse {
        $emergencyRepository = $entityManager->getRepository(Emergency::class);
        $emergency = $emergencyRepository->find($request->request->getInt(FixedFieldEnum::id->name));

        if ($emergency->getClosedAt()) {
            throw new FormException("L'urgence est cloturée, vous ne pouvez pas la modifier.");
        }
        $emergencyService->updateEmergency($entityManager, $emergency, $request);

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "L'urgence a été modifiée avec succès.",
        ]);
    }

    #[Route("/api-list", name: "api_list", options: ['expose' => true], methods: [self::POST], condition:  self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_EMERGENCY])]
    public function apiList(EntityManagerInterface $entityManager,
                            Request                $request,
                            EmergencyService       $emergencyService): JsonResponse {
        $data = $emergencyService->getDataForDatatable($entityManager, $request->request);

        return $this->json($data);
    }

    #[Route("/close/{emergency}", name: "close", options: ['expose' => true], methods: [self::POST], condition:  self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_EMERGENCY])]
    public function close(EntityManagerInterface $entityManager,
                          Emergency              $emergency,
                          EmergencyService       $emergencyService): JsonResponse {
        $emergencyService->closeEmergency($entityManager, $emergency);

        return $this->json([
            "success" => true,
        ]);
    }
}
