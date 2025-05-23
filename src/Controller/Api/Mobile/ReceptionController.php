<?php

namespace App\Controller\Api\Mobile;

use App\Annotation as Wii;
use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Exceptions\FormException;
use App\Serializer\SerializerUsageEnum;
use App\Service\mobile\MobileReceptionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;

#[Route("/api/mobile/receptions")]
class ReceptionController extends AbstractController
{

    // With no name route specified, the route is "/"
    #[Route(methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
    public function getReceptions(EntityManagerInterface $entityManager): JsonResponse
    {
        $receptionRepository = $entityManager->getRepository(Reception::class);

        $reception = $receptionRepository->getMobileReceptions();

        $response = [
            "success" => true,
            "data" => Stream::from($reception)
                ->map(static function (array $reception) {
                    $reception["emergency"] = $reception["emergency_articles"] || $reception["emergency_manual"];
                    unset($reception["emergency_articles"]);
                    unset($reception["emergency_manual"]);
                    return $reception;
                })
                ->toArray(),
        ];

        return new JsonResponse($response);
    }

    #[Route("/{reception}", methods: [self::PATCH], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
    public function patchReception(EntityManagerInterface $entityManager,
                                   Request                $request,
                                   Reception              $reception,
                                   MobileReceptionService $mobileReceptionService): JsonResponse
    {
        $payload = @json_decode($request->request->get('receptionReferenceArticles'), true);

        if (!$payload) {
            throw new FormException("Invalid JSON payload");
        }

        if ($reception->getStatut()->getCode() === Reception::STATUT_RECEPTION_TOTALE) {
            throw new FormException("La réception est déjà terminée");
        }

        $now = new DateTime();
        $receptionLine = $reception->getLine(null);
        $receptionReferenceArticles = $receptionLine->getReceptionReferenceArticles();

        $payload = Stream::from($payload)
            ->filter(static fn($row) => $row['receivedQuantity'] > 0)
            ->toArray();

        foreach ($payload as $row) {
            $mobileReceptionService->processReceptionRow(
                $entityManager,
                $reception,
                $receptionReferenceArticles,
                $row,
                $now,
            );
        }

        $mobileReceptionService->updateReceptionStatus($entityManager, $reception);

        $entityManager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    #[Route("/{reception}/lines", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    #[HasPermission([Menu::NOMADE, Action::MODULE_ACCESS_RECEPTION])]
    public function getLines(EntityManagerInterface $entityManager,
                             Reception              $reception,
                             NormalizerInterface    $normalizer): JsonResponse
    {
        $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);
        [
            "total" => $countTotal,
            "data" => $results,
        ] = $receptionLineRepository->getByReception($reception, []);

        return $this->json([
            "total" => $countTotal,
            "success" => true,
            "data" => $normalizer->normalize($results, null, ["usage" => SerializerUsageEnum::RECEPTION_MOBILE]),
        ]);
    }
}
