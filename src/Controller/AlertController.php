<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\TransferOrder;
use App\Entity\Type;
use App\Helper\Stream;
use App\Service\CSVExportService;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use http\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
     * @param UserService $userService
     * @return Response
     */
    public function index(UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ALER)) {
            return $this->redirectToRoute('access_denied');
        }

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
     * @param Request $request
     * @param RefArticleDataService $refArticleDataService
     * @param UserService $userService
     * @return Response
     */
    public function api(Request $request,
                        RefArticleDataService $refArticleDataService,
                        UserService $userService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_ALER)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $refArticleDataService->getAlerteDataByParams($request->request, $this->getUser());
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="alert_export",options={"expose"=true}, methods="GET|POST" )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return Response
     * @throws \Exception
     */
    public function export(Request $request,
                           EntityManagerInterface $entityManager,
                           CSVExportService $CSVExportService): Response {
        $dateMin = $request->query->get("dateMin");
        $dateMax = $request->query->get("dateMax");

        $dateTimeMin = DateTime::createFromFormat("Y-m-d H:i:s", $dateMin . " 00:00:00");
        $dateTimeMax = DateTime::createFromFormat("Y-m-d H:i:s", $dateMax . " 23:59:59");

        if(isset($dateTimeMin, $dateTimeMax)) {
            $now = new DateTime("now", new DateTimeZone("Europe/Paris"));

            $alertRepository = $entityManager->getRepository(Alert::class);

            $alert = $alertRepository->findByDates($dateTimeMin, $dateTimeMax);

            $header = [
                "type d\'alerte",
                "date d\'alerte",
                "libellé",
                "référence",
                "code barre",
                "quantite disponible",
                "type quantité",
                "seuil d\'alerte",
                "seuil de sécurité",
                "date de péremption",
                "gestionnaire(s)"
            ];

            return $CSVExportService->createBinaryResponseFromData(
                "export_alertes" . $now->format("d_m_Y") . ".csv",
                $alert,
                $header,
                function (Alert $alert) {
                    return $alert->serialize();
                }
            );
        }

        throw new BadRequestHttpException();
    }
}
