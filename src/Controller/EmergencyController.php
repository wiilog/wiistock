<?php

namespace App\Controller;


use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Emergency\Emergency;
use App\Entity\CategoryType;
use App\Entity\Emergency\StockEmergency;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Type;
use App\Service\EmergencyService;
use App\Entity\Menu;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/emergency', name: 'emergency_')]
class EmergencyController extends AbstractController {

    #[Route('/', name: 'index', methods: [self::GET])]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_EMERGENCY])]
    public function index(EntityManagerInterface $entityManager,
                          UserService            $userService,
                          EmergencyService       $emergencyService): Response {
        $currentUser = $userService->getUser();
        $columns = $emergencyService->getVisibleColumnsConfig($entityManager, $currentUser);
        return $this->render('emergency/index.html.twig', [
            "initial_visible_columns" => $columns,
            'modalNewEmergencyConfig' => $emergencyService->getEmergencyConfig($entityManager),
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
}
