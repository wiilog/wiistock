<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\TranslationSource;
use App\Entity\Transport\TemperatureRange;
use App\Entity\Type\CategoryType;
use App\Entity\Type\Type;
use App\Service\DateTimeService;
use App\Service\NatureService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/nature", name: "nature_")]
class NatureController extends AbstractController {

    #[Route("/", name: "index", options: ['expose' => true], methods: self::GET)]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PACK_NATURE])]
    public function index(EntityManagerInterface $manager): Response {
        $typeRepository = $manager->getRepository(Type::class);

        $temperatures = $manager->getRepository(TemperatureRange::class)->findBy([]);
        $types = [
            'transportCollect' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::COLLECT_TRANSPORT),
            'transportDelivery' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::DELIVERY_TRANSPORT)
        ];
        return $this->render('nature/index.html.twig', [
            'temperatures' => $temperatures,
            'types' => $types,
            "nature" => new Nature(),
        ]);
    }

    #[Route("/api", name: "api", options: ["expose" => true], methods: self::POST, condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::REFERENTIEL, Action::DISPLAY_PACK_NATURE], mode: HasPermission::IN_JSON)]
    public function api(Request $request, NatureService $natureService): JsonResponse {
        return $this->json($natureService->getDataForDatatable($request->request));
    }

    #[Route("/new", name: "new", options: ["expose" => true], methods: self::POST, condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::REFERENTIEL, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(Request                $request,
                        EntityManagerInterface $entityManager,
                        NatureService          $natureService): JsonResponse {

        $data = $request->request->all();
        $labelTranslationSource = new TranslationSource();
        $entityManager->persist($labelTranslationSource);

        $nature = new Nature();
        $nature->setLabelTranslation($labelTranslationSource);

        $natureService->updateNature($entityManager, $nature, $data);

        $entityManager->persist($nature);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La nature {$nature->getLabel()} a bien été créée."
        ]);
    }

    #[Route("/api-edit", name: "api_edit", options: ["expose" => true], methods: [self::POST, self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function apiEdit(Request                $request,
                            EntityManagerInterface $manager,
                            DateTimeService        $dateTimeService,
                            TranslationService     $translationService): JsonResponse {
        $data = $request->query->all();
        $natureRepository = $manager->getRepository(Nature::class);
        $typeRepository = $manager->getRepository(Type::class);
        $nature = $natureRepository->find($data['id']);

        if ($nature->getLabelTranslation() === null) {
            $translationService->setDefaultTranslation($manager, $nature, $nature->getLabel());
        }

        $temperatures = $manager->getRepository(TemperatureRange::class)->findBy([]);
        $types = [
            'transportCollect' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::COLLECT_TRANSPORT),
            'transportDelivery' => $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::DELIVERY_TRANSPORT)
        ];

        $trackingDelaySegments = $nature->getTrackingDelaySegments();
        $segmentsMax = [];
        $segmentsColors = [];

        Stream::from($trackingDelaySegments ?? [])
            ->each(static function ($trackingDelaySegment) use (&$segmentsMax, &$segmentsColors) {
                $segmentsMax[] = $trackingDelaySegment['segmentMax'];
                $segmentsColors[] = $trackingDelaySegment['segmentColor'];
            });

        $natureDelayInterval = $nature->getTrackingDelay()
            ? $dateTimeService->secondsToDateInterval($nature->getTrackingDelay())
            : null;
        $trackingDelay = $nature->getTrackingDelay()
            ? $this->getFormatter()->delay($natureDelayInterval)
            : null;
        $content = $this->renderView('nature/modal/form.html.twig', [
            "nature" => $nature,
            "temperatures" => $temperatures,
            "types" => $types,
            "trackingDelay" => $trackingDelay,
            "segmentsMax" => $segmentsMax,
            "segmentsColors" => $segmentsColors,
        ]);

        return $this->json([
            'success' => true,
            'html' => $content,
        ]);
    }

    #[Route("/edit", name: "edit", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(Request                $request,
                         EntityManagerInterface $entityManager,
                         NatureService          $natureService): Response {
        $data = $request->request->all();

        $natureRepository = $entityManager->getRepository(Nature::class);
        $currentNature = $natureRepository->find($data['nature']);

        $natureService->updateNature($entityManager, $currentNature, $data);
        $natureLabel = $this->getFormatter()->nature($currentNature);

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'msg' => "La nature $natureLabel a bien été modifiée"
        ]);
    }

    #[Route("check-delete", name: "check_delete", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function checkDelete(Request                $request,
                                EntityManagerInterface $entityManager): Response {
        if ($typeId = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);
            $natureIsUsed = $natureRepository->countUsedById($typeId);

            if (!$natureIsUsed) {
                $delete = true;
                $html = $this->renderView('nature/modal/delete-right.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('nature/modal/delete-wrong.html.twig');
            }

            return new JsonResponse([
                'delete' => $delete,
                'html' => $html
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("delete", name: "delete", options: ["expose" => true], methods: self::POST, condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::REFERENTIEL, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request                $request,
                           EntityManagerInterface $entityManager): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $natureRepository = $entityManager->getRepository(Nature::class);

            $nature = $natureRepository->find($data['nature']);

            $entityManager->remove($nature);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La nature a bien été supprimée.",
            ]);
        }
        throw new BadRequestHttpException();
    }
}
