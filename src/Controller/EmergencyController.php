<?php

namespace App\Controller;


use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Emergency\Emergency;
use App\Entity\Emergency\StockEmergency;
use App\Entity\Emergency\TrackingEmergency;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\Menu;
use App\Entity\Type;
use App\Service\AttachmentService;
use App\Service\EmergencyService;
use App\Service\FixedFieldService;
use App\Service\FormatService;
use App\Service\SpecificService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/urgence')]
class EmergencyController extends AbstractController
{

    #[Route('/', name: 'emergency_index')]
    #[HasPermission([Menu::QUALI, Action::DISPLAY_URGE])]
    public function index(EntityManagerInterface $entityManager,
                          EmergencyService $emergencyService)
    {
        return $this->render('emergency/index.html.twig', [
            'modalNewEmergencyConfig' => $emergencyService->getEmergencyConfig($entityManager),
        ]);
    }

    #[Route('/creer', name: 'emergency_new', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::QUALI, Action::CREATE_EMERGENCY], mode: HasPermission::IN_JSON)]
    public function new(Request                 $request,
                        EntityManagerInterface  $entityManager,
                        EmergencyService        $emergencyService): JsonResponse {
        $typeRepository = $entityManager->getRepository(Type::class);

        $type = $typeRepository->find($request->request->get(FixedFieldEnum::type->name));

        $isStockEmergency = $type->getCategory()->getLabel() === CategoryType::STOCK_EMERGENCY;
        $emergency = $isStockEmergency
            ? new StockEmergency()
            : new TrackingEmergency();


        $emergencyService->updateEmergency($entityManager, $emergency, $request);

        $entityManager->persist($emergency);
        $entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "msg" => "L'urgence a été créée avec succès.",
        ]);
    }

    #[Route('/modifier-api/{emergency}', name: 'emergency_edit_api', options: ['expose' => true], methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::QUALI, Action::CREATE_EMERGENCY], mode: HasPermission::IN_JSON)]
    public function editApi(EntityManagerInterface             $entityManager,
                            EmergencyService                   $emergencyService,
                            Emergency   $emergency): JsonResponse {

        return new JsonResponse([
            'html' => $this->renderView('emergency/form.html.twig', [...$emergencyService->getEmergencyConfig($entityManager, $emergency)]),
        ]);
    }

    #[Route('/modifier', name: 'emergency_edit', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[HasPermission([Menu::QUALI, Action::CREATE_EMERGENCY], mode: HasPermission::IN_JSON)]
    public function edit(Request                 $request,
                        EntityManagerInterface  $entityManager,
                        EmergencyService        $emergencyService): JsonResponse {
        $emergencyRepository = $entityManager->getRepository(Emergency::class);

        $emergency = $emergencyRepository->find($request->query->get('id'));

        $emergencyService->updateEmergency($entityManager, $emergency, $request);

        $entityManager->persist($emergency);
        $entityManager->flush();

        return new JsonResponse([
            "success" => true,
            "msg" => "L'urgence a été modifiée avec succès.",
        ]);
    }
}
