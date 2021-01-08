<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Type;
use App\Service\AlertService;
use App\Service\CSVExportService;
use App\Service\RefArticleDataService;
use App\Service\SpecificService;
use App\Service\UserService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Throwable;

/**
 * @Route("/alerte")
 */
class AlertController extends AbstractController
{

    /**
     * @var object|string
     */
    private $user;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->user = $tokenStorage->getToken()->getUser();
    }

    /**
     * @Route("/liste", name="alerte_index", methods="GET|POST", options={"expose"=true})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ALER})
     */
    public function index(UserService $userService): Response
    {
        $typeRepository = $this->getDoctrine()->getRepository(Type::class);
        return $this->render('alerte_reference/index.html.twig', [
            "types" => $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]),
            "alerts" => [
                "security" => "Seuil de sécurité",
                "alert" => "Seuil d'alerte",
                "expiration" => "Péremption",
            ]
        ]);
    }

    /**
     * @Route("/api", name="alerte_ref_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ALER}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        RefArticleDataService $refArticleDataService,
                        UserService $userService): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = $refArticleDataService->getAlerteDataByParams($request->request, $this->getUser());
            return new JsonResponse($data);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="alert_export",options={"expose"=true}, methods="GET|POST" )
     * @HasPermission({Menu::STOCK, Action::EXPORT_ALER})
     */
    public function export(Request $request,
                           AlertService $alertService,
                           SpecificService $specificService,
                           EntityManagerInterface $entityManager,
                           CSVExportService $CSVExportService): Response
    {
        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");
        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (!empty($dateTimeMin) && !empty($dateTimeMax)) {
            $today = new DateTime();
            $todayStr = $today->format("d-m-Y-H-i-s");

            $header = [
                "type d'alerte",
                "date d'alerte",
                "libellé",
                "référence",
                "code barre",
                "quantite disponible",
                "type quantité",
                "seuil d'alerte",
                "seuil de sécurité",
                "date de péremption",
                "gestionnaire(s)"
            ];

            if ($specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI)) {
                $specificHeader = [
                    'Nom fournisseur',
                    'Réf art Fournisseur',
                    'Machine (PDT)'
                ];
                $header = array_merge($header, $specificHeader);
            }

            return $CSVExportService->streamResponse(function ($output) use ($alertService, $specificService, $entityManager, $CSVExportService, $dateTimeMin, $dateTimeMax) {
                $alertRepository = $entityManager->getRepository(Alert::class);

                $alerts = $alertRepository->iterateBetween($dateTimeMin, $dateTimeMax);
                /** @var Alert $alert */
                foreach ($alerts as $alert) {
                    $alertService->putLineAlert($entityManager, $specificService, $CSVExportService, $output, $alert);
                }
            }, "export_alert_${todayStr}.csv", $header);
        } else {
            throw new BadRequestHttpException();
        }
    }
}
