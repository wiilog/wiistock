<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\IOT\RequestTemplate;
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
class RequestTemplateController extends AbstractController {

    /**
     * @Route("/", name="request_template_index")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE})
     */
    public function index(EntityManagerInterface $manager): Response {
        $fieldsParamRepository = $manager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

        return $this->render("request_template/index.html.twig", [
            "new_request_template" => new class extends RequestTemplate {},
            "fields_param" => $fieldsParam,
        ]);
    }

    /**
     * @Route("/api", name="request_template_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);

        $queryResult = $requestTemplateRepository->findByParamsAndFilters($request->request);

        $requestTemplates = $queryResult["data"];

        $rows = [];
        foreach ($requestTemplates as $requestTemplate) {
            $rows[] = [
                "actions" => $this->renderView("request_template/actions.html.twig", [
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
    public function new(Request $request, EntityManagerInterface $manager, RequestTemplateService $service): Response {
        $data = $request->request->all();

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

        return $this->json([
            "success" => true,
            "msg" => "Le modèle de demande {$requestTemplate->getName()} a bien été créé",
        ]);
    }

    /**
     * @Route("/voir", name="request_template_show", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function show(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);
            $requestTemplate = $requestTemplateRepository->find($data["id"]);

            return $this->json($this->renderView("request_template/show_content.html.twig", [
                "request_template" => $requestTemplate
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="request_template_edit_api", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function editApi(Request $request, EntityManagerInterface $manager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);
            $requestTemplate = $requestTemplateRepository->find($data['id']);

            $fieldsParamRepository = $manager->getRepository(FieldsParam::class);
            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

            $typeRepository = $manager->getRepository(Type::class);
            $freeFieldsRepository = $manager->getRepository(FreeField::class);
            $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);

            return $this->json($this->renderView("request_template/forms/form.html.twig", [
                "request_template" => $requestTemplate,
                "fields_param" => $fieldsParam,
                "free_fields_types" => array_map(function (Type $type) use ($freeFieldsRepository) {
                    $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_HANDLING);
                    return [
                        "typeLabel" => $type->getLabel(),
                        "typeId" => $type->getId(),
                        "freeFields" => $freeFields,
                    ];
                }, $types),
            ]));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="request_template_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_REQUEST_TEMPLATE}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request, EntityManagerInterface $manager, RequestTemplateService $service): Response {
        $data = $request->request->all();

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
    public function deleteApi(Request $request, EntityManagerInterface $manager): Response {
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
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $data = json_decode($request->getContent(), true);

        $requestTemplateRepository = $manager->getRepository(RequestTemplate::class);

        $requestTemplate = $requestTemplateRepository->find($data["id"]);
        if($requestTemplate && $requestTemplate->getTriggerActions()->count() > 0) {
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

}
