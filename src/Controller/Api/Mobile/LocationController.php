<?php

namespace App\Controller\Api\Mobile;

use App\Controller\AbstractController;
use App\Entity\Emplacement;
use App\Entity\Fields\FixedFieldEnum;
use App\Service\EmplacementDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Annotation as Wii;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/api/mobile")]
class LocationController extends AbstractController {

    #[Route("/emplacement", methods: [self::POST], condition: self::IS_XML_HTTP_REQUEST)]
    #[Wii\RestVersionChecked]
    public function addEmplacement(Request $request,
                                   EntityManagerInterface $entityManager,
                                   EmplacementDataService $emplacementDataService): Response {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if (!$emplacementRepository->findOneBy(['label' => $request->request->get('label')])) {
            $toInsert = $emplacementDataService->persistLocation($entityManager, [
                FixedFieldEnum::name->name => $request->request->get('label'),
                FixedFieldEnum::isDeliveryPoint->name => $request->request->getBoolean('isDelivery'),
            ]);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => $toInsert->getId(),
            ]);
        } else {
            throw new BadRequestHttpException("Un emplacement portant ce nom existe déjà");
        }
    }

}
