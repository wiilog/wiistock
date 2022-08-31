<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\AlertTemplate;
use App\Entity\Menu;
use App\Entity\NotificationTemplate;
use App\Helper\PostHelper;

use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * @Route("/parametrage/modele-notification")
 */
class NotificationTemplateController extends AbstractController
{

    /**
     * @Route("/liste", name="notification_template_index")
     */
    public function index(): Response
    {
        return $this->render('settings/notifications/index.html.twig', [
            'templateTypes' => AlertTemplate::TEMPLATE_TYPES
        ]);
    }

    /**
     * @Route("/api", name="notification_template_api", options={"expose"=true}, methods={"POST|GET"}, condition="request.isXmlHttpRequest()")
     */
    public function api(Request $request, EntityManagerInterface $manager): Response
    {
        $notificationTemplateRepository = $manager->getRepository(NotificationTemplate::class);
        $queryResult = $notificationTemplateRepository->findByParams($request->request);

        $notificationTemplates = $queryResult['data'];

        $rows = [];
        /** @var NotificationTemplate $notificationTemplate */
        foreach ($notificationTemplates as $notificationTemplate) {
            $rows[] = [
                "type" => NotificationService::READABLE_TYPES[$notificationTemplate->getType()] ?? '',
                "content" => $this->renderView("settings/notifications/list_content.html.twig", [
                    "id" => $notificationTemplate->getId(),
                    "content" => $notificationTemplate->getContent(),
                ]),
            ];
        }

        return $this->json([
            "data" => $rows,
            "recordsTotal" => $queryResult['total'],
            "recordsFiltered" => $queryResult['count'],
        ]);
    }

    /**
     * @Route("/api-modifier", name="notification_template_edit_api", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT})
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $notificationTemplate = PostHelper::entity($entityManager, $data, "id", NotificationTemplate::class);

            return $this->json($this->renderView("settings/notifications/edit_content.html.twig", [
                "notification_template" => $notificationTemplate,
                "dictionary" => NotificationService::DICTIONARIES[$notificationTemplate->getType()],
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="notification_template_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::PARAM, Action::EDIT})
     */
    public function edit(EntityManagerInterface $entityManager, Request $request): Response
    {
        $post = json_decode($request->getContent(), true);

        $notificationTemplate = PostHelper::entity($entityManager, $post, "id", NotificationTemplate::class);
        $notificationTemplate->setContent(PostHelper::string($post, "content"));

        if (substr_count($notificationTemplate->getContent(), "<p>") > 5) {
            return $this->json([
                "success" => false,
                "msg" => "La notification ne peut pas faire plus de 5 lignes"
            ]);
        } else if (strlen(strip_tags($notificationTemplate->getContent())) > 500) {
            return $this->json([
                "success" => false,
                "msg" => "La notification ne peut pas faire plus de 500 caractères"
            ]);
        }

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le modèle de notification a bien été modifié"
        ]);
    }

}

