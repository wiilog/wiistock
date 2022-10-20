<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Alert;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\AlertService;
use App\Service\CSVExportService;
use App\Service\NotificationService;
use App\Service\RefArticleDataService;
use App\Service\SpecificService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

/**
 * @Route("/alerte")
 */
class AlertController extends AbstractController {

    /**
     * @Route("/liste", name="alerte_index", methods="GET|POST", options={"expose"=true})
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ALER})
     */
    public function index(Request $request, EntityManagerInterface $manager): Response
    {
        $query = $request->query;

        $referenceTypes = $query->has('referenceTypes') ? $query->get('referenceTypes', '') : '';
        $managers = $query->has('managers') ? $query->get('managers', '') : '';
        $typeRepository = $manager->getRepository(Type::class);
        $utilisateurRepository = $manager->getRepository(Utilisateur::class);
        if (!empty($managers)) {
            $managersIds = explode(',', $managers);
            $managersFilter = !empty($managersIds)
                ? $utilisateurRepository->findBy(['id' => $managersIds])
                : [];
        } else {
            $managersFilter = [];
        }
        return $this->render('alerte_reference/index.html.twig', [
            "types" => $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]),
            "referenceTypes" => $referenceTypes,
            "managers" => $managers,
            "managersFilter" => $managersFilter,
            "alerts" => [
                "security" => "Seuil de sécurité",
                "alert" => "Seuil d'alerte",
                "expiration" => "Péremption",
            ]
        ]);
    }

    /**
     * @Route("/notifications/liste", name="notifications_index", methods="GET|POST", options={"expose"=true})
     */
    public function indexNotifications(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $user->clearNotifications();
        $entityManager->flush();
        return $this->render('notifications/index.html.twig');
    }

    /**
     * @Route("/notifications/api", name="notifications_api", methods="GET|POST", options={"expose"=true})
     */
    public function apiNotifications(Request $request, NotificationService $notificationService, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $user->clearNotifications();
        $entityManager->flush();
        $data = $notificationService->getNotificationDataByParams($entityManager, $request->request, $this->getUser());
        return new JsonResponse($data);
    }

    /**
     * @Route("/notifications/abonnement/{token}", name="register_topic", methods="POST", options={"expose"=true})
     */
    public function subscribeToToken(string $token, NotificationService $notificationService): Response
    {
        try {
            $notificationService->subscribeClientToTopic($token);
            return new JsonResponse();
        } catch (\Exception $exception) {
            return new JsonResponse();
        }
    }

    /**
     * @Route("/api", name="alerte_ref_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_ALER}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        RefArticleDataService $refArticleDataService): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $refArticleDataService->getAlerteDataByParams($request->request, $user);
        return new JsonResponse($data);
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
                "gestionnaire(s)",
                "groupe(s) de visibilité"
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

                /** @var Utilisateur $user */
                $user = $this->getUser();

                $alerts = $alertRepository->iterateBetween($dateTimeMin, $dateTimeMax, $user, [ReferenceArticle::STATUT_ACTIF]);
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
