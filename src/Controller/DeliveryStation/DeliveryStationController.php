<?php

namespace App\Controller\DeliveryStation;

use App\Annotation\HasValidToken;
use App\Controller\AbstractController;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\KioskToken;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\VisibilityGroup;
use App\Service\AttachmentService;
use App\Service\Kiosk\KioskService;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Article;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;


/**
 * @Route("/caisse-automatique")
 */
class DeliveryStationController extends AbstractController
{

    #[Route("/", name: "delivery_station_index", options: ["expose" => true])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $type = $entityManager->getRepository(Type::class)->findByCategoryLabelsAndLabels([CategoryType::DEMANDE_LIVRAISON], ['L - Consommable'])[0];
        $visibilityGroup = $entityManager->getRepository(VisibilityGroup::class)->findBy([])[0] ?? null;

        $rawMessage = "Demande de livraison simplifiée sur le flux @typelivraison et groupe de visibilité @groupevisibilite";
        $homeMessage = str_replace('@typelivraison', "<strong>{$type->getLabel()}</strong>", str_replace('@groupevisibilite', "<strong>{$visibilityGroup->getLabel()}</strong>", $rawMessage));

        return $this->render('delivery_station/home.html.twig', [
            'homeMessage' => $homeMessage,
            'type' => $type->getId(),
            'visibilityGroup' => $visibilityGroup?->getId(),
        ]);
    }

    #[Route("/login/{mobileLoginKey}", name: "delivery_station_login", options: ["expose" => true], methods: "POST")]
    public function login(string $mobileLoginKey, EntityManagerInterface $entityManager): JsonResponse {
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['mobileLoginKey' => $mobileLoginKey]);
        return $this->json([
            'success' => !!$user,
        ]);
    }

    #[Route("/formulaire", name: "delivery_station_form", options: ["expose" => true])]
    public function form(Request $request, EntityManagerInterface $entityManager): Response
    {
        $request = $request->query;

        $type = $entityManager->getRepository(Type::class)->find($request->get('type'));
        $visibilityGroup = $entityManager->getRepository(VisibilityGroup::class)->find($request->get('visibilityGroup'));
        $freeFields = [];
        return $this->render('delivery_station/form.html.twig', [
            'freeFields' => $freeFields,
            'form' => true,
            'type' => $type->getLabel(),
            'visibilityGroup' => $visibilityGroup->getLabel(),
        ]);
    }

    #[Route("/get-reference-informations", name: "delivery_station_get_reference_informations", options: ["expose" => true], methods: "GET")]
    public function getReferenceInformations(Request $request,
                                             EntityManagerInterface $entityManager): JsonResponse
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $reference = $referenceArticleRepository->find($request->query->get('reference'));


        return $this->json([
            'values' => [
                'reference' => $reference->getReference(),
                'label' => $reference->getLibelle(),
                'quantity' => $reference->getQuantiteDisponible(),
                'supplierCode' => '',
                'location' => $this->formatService->location($reference->getEmplacement()),
                'image' => $reference->getImage()
                    ? "{$reference->getImage()->getFullPath()}"
                    : "",
            ],
        ]);
    }
}
