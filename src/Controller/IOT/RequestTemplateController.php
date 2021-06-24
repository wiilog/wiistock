<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\RequestTemplateLine;
use App\Entity\Menu;

use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Service\RequestTemplateService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/modele-demande")
 */
class RequestTemplateController extends AbstractController
{

    /**
     * @Route("/", name="request_template_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE})
     */
    public function index(EntityManagerInterface $manager): Response
    {
        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);

        $typeRepository = $manager->getRepository(Type::class);
        $freeFieldsRepository = $manager->getRepository(FreeField::class);
        $handlingTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
        $deliveryTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $collectTypes = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);

        return $this->render("request_template/index.html.twig", [
            "new_request_template" => new class extends RequestTemplate {

            },
            'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY),
            "fields_param" => $fieldsParam,
            "handling_free_fields_types" => array_map(function (Type $type) use ($freeFieldsRepository) {
                $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_HANDLING);
                return [
                    "typeLabel" => $type->getLabel(),
                    "typeId" => $type->getId(),
                    "freeFields" => $freeFields,
                ];
            }, $handlingTypes),
            "delivery_free_fields_types" => array_map(function (Type $type) use ($freeFieldsRepository) {
                $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);
                return [
                    "typeLabel" => $type->getLabel(),
                    "typeId" => $type->getId(),
                    "freeFields" => $freeFields,
                ];
            }, $deliveryTypes),
            "collect_free_fields_types" => array_map(function (Type $type) use ($freeFieldsRepository) {
                $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_COLLECTE);
                return [
                    "typeLabel" => $type->getLabel(),
                    "typeId" => $type->getId(),
                    "freeFields" => $freeFields,
                ];
            }, $collectTypes),
        ]);
    }

    /**
     * @Route("/api", name="request_template_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response
    {
        $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);

        $queryResult = $requestTemplateRepository->findByParamsAndFilters($request->request);

        $requestTemplates = $queryResult["data"];

        $rows = [];
        foreach ($requestTemplates as $requestTemplate) {
            $rows[] = [
                "actions" => $this->renderView("request_template/datatable/actions.html.twig", [
                    "request_template" => $requestTemplate
                ]),
                "name" => $requestTemplate->getName(),
                "type" => FormatHelper::type($requestTemplate->getType()),
            ];
        }

        return $this->json([
            "data" => $rows,
            "recordsFiltered" => $queryResult["count"],
            "recordsTotal" => $queryResult["total"],
        ]);
    }

    /**
     * @Route("/creer", name="request_template_new", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request, EntityManagerInterface $manager, RequestTemplateService $service): Response
    {
        if (!($data = json_decode($request->getContent(), true))) {
            $data = $request->request->all();
        }

        $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);

        $sameName = $requestTemplateRepository->findOneBy(["name" => $data["name"]]);
        if ($sameName) {
            return $this->json([
                "success" => false,
                "msg" => "Un modèle de demande avec le même nom existe déjà",
            ]);
        }

        $requestTemplate = $service->createRequestTemplate($data["type"]);
        $service->updateRequestTemplate($requestTemplate, $request);

        $manager->persist($requestTemplate);
        $manager->flush();
        $response = [
            "success" => true,
            "msg" => "Le modèle de demande {$requestTemplate->getName()} a bien été créé",
        ];
        if ($requestTemplate instanceof CollectRequestTemplate || $requestTemplate instanceof DeliveryRequestTemplate) {
            $response['redirect'] = $this->generateUrl('request_template_show', [
                'requestTemplate' => $requestTemplate->getId()
            ]);
        }
        return $this->json($response);
    }

    /**
     * @Route("/api-modifier", name="request_template_edit_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $manager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);
            $requestTemplate = $requestTemplateRepository->find($data['id']);

            $fieldsParamRepository = $manager->getRepository(FieldsParam::class);
            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);

            return $this->json($this->renderView("request_template/forms/form.html.twig", [
                "request_template" => $requestTemplate,
                "fields_param" => $fieldsParam,
                'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY),
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="request_template_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $manager, RequestTemplateService $service): Response
    {
        if (!($data = json_decode($request->getContent(), true))) {
            $data = $request->request->all();
        }

        $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);

        $requestTemplate = $requestTemplateRepository->find($data["id"]);
        if ($requestTemplate) {
            $service->updateRequestTemplate($requestTemplate, $request);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Le modèle de demande {$requestTemplate->getName()} a bien été modifié",
            ]);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/api-supprimer", name="request_template_delete_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function deleteApi(Request $request, EntityManagerInterface $manager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);
            $requestTemplate = $requestTemplateRepository->find($data["id"]);

            return $this->json($this->renderView("request_template/delete_content.html.twig", [
                "request_template" => $requestTemplate
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="request_template_delete", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response
    {
        $data = json_decode($request->getContent(), true);

        $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);

        $requestTemplate = $requestTemplateRepository->find($data["id"]);
        if ($requestTemplate && $requestTemplate->getTriggerActions()->count() > 0) {
            return $this->json([
                "success" => false,
                "msg" => "Vous ne pouvez pas supprimer ce modèle de demande car il est utilisé par un actionneur",
            ]);
        } else if ($requestTemplate) {
            $manager->remove($requestTemplate);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Modèle de demande supprimé avec succès",
            ]);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/voir/{requestTemplate}", name="request_template_show")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE})
     */
    public function show(RequestTemplateService $service, RequestTemplate $requestTemplate): Response
    {
        if ($requestTemplate instanceof HandlingRequestTemplate) {
            return $this->render("securite/access_denied.html.twig");
        }

        return $this->render("request_template/show.html.twig", [
            "request_template" => $requestTemplate,
            "new_line" => new RequestTemplateLine(),
            "details" => $service->createHeaderDetailsConfig($requestTemplate),
            "quantityText" => $requestTemplate instanceof DeliveryRequestTemplate ? "Quantité à livrer" : "Quantité à prélever"
        ]);
    }

    /**
     * @Route("/ligne/api/{requestTemplate}", name="request_template_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function articleApi(Request $request, EntityManagerInterface $manager, RequestTemplate $requestTemplate): Response
    {
        $requestTemplateLineRepository = $manager->getRepository(RequestTemplateLine::class);

        $lines = $requestTemplateLineRepository->findByParams($requestTemplate, $request->request);

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = [
                "actions" => $this->renderView("request_template/datatable/lines.html.twig", [
                    "line" => $line
                ]),
                "reference" => $line->getReference()->getReference(),
                "label" => $line->getReference()->getLibelle(),
                "location" => FormatHelper::location($line->getReference()->getEmplacement()),
                "quantity" => $line->getQuantityToTake(),
            ];
        }

        return $this->json([
            "data" => $rows,
            "recordsFiltered" => count($lines),
            "recordsTotal" => count($lines),
        ]);
    }

    /**
     * @Route("/ligne/{requestTemplate}/creer", name="request_template_line_new", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function newLine(Request $request, EntityManagerInterface $manager,
                            RequestTemplateService $service, RequestTemplate $requestTemplate): Response
    {
        if (!($data = json_decode($request->getContent(), true))) {
            $data = $request->request->all();
        }

        $line = new RequestTemplateLine();
        $line->setRequestTemplate($requestTemplate);

        $service->updateRequestTemplateLine($line, $data);

        $manager->persist($line);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "L'article a été ajouté au modèle de demande",
        ]);
    }

    /**
     * @Route("/ligne/api-modifier", name="request_template_line_edit_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function editLineApi(Request $request, EntityManagerInterface $manager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $requestTemplateLineRepository = $manager->getRepository(RequestTemplateLine::class);
            $line = $requestTemplateLineRepository->find($data["id"]);

            return $this->json($this->renderView("request_template/forms/line.html.twig", [
                "line" => $line,
                "quantityText" => $line->getDeliveryRequestTemplate() ? "Quantité à livrer" : "Quantité à prélever"
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ligne/edit", name="request_template_line_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function editLine(Request $request, EntityManagerInterface $manager, RequestTemplateService $service): Response
    {
        if (!($data = json_decode($request->getContent(), true))) {
            $data = $request->request->all();
        }

        $requestTemplateRepository = $manager->getRepository(RequestTemplateLine::class);

        $line = $requestTemplateRepository->find($data["id"]);
        if ($line) {
            $service->updateRequestTemplateLine($line, $data);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "La ligne a bien été modifiée",
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/line/supprimer/{line}", name="request_template_line_remove", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function deleteLine(EntityManagerInterface $manager, RequestTemplateLine $line): Response
    {
        $manager->remove($line);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "La ligne article a été retirée du modèle de demande",
        ]);
    }

}
