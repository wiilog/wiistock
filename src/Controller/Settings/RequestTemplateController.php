<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\RequestTemplateLine;
use App\Entity\Menu;
use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Service\FreeFieldService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage")
 */
class RequestTemplateController extends AbstractController {

    /**
     * @Route("/modele-demande/{category}/header/{template}", name="settings_request_template_header", options={"expose"=true})
     */
    public function requestTemplateHeader(Request $request,
                                          string $category,
                                          Environment $twig,
                                          EntityManagerInterface $entityManager,
                                          FreeFieldService $freeFieldService,
                                          ?RequestTemplate $template = null): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);

        $edit = $request->query->getBoolean("edit");

        if($edit) {
            $name = $template ? $template->getName() : "";

            if($category === Type::LABEL_DELIVERY) {
                $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
            } else if($category === Type::LABEL_COLLECT) {
                $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]);
            } else if($category === Type::LABEL_HANDLING) {
                $types = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_HANDLING]);
            } else {
                throw new RuntimeException('Invalid category');
            }

            $typeOptions = Stream::from($types)
                ->map(fn(Type $type) => "<option value='{$type->getId()}' " . ($template && $template->getRequestType()->getId() === $type->getId() ? "selected" : "") . ">{$type->getLabel()}</option>")
                ->join("");

            $data = [
                [
                    "type" => "hidden",
                    "name" => "entity",
                    "class" => "category",
                    "value" => $category,
                ],
                [
                    "label" => "Nom du modèle*",
                    "value" => "<input name='name' class='data form-control' value='$name' required>",
                ],
            ];

            if($category === Type::LABEL_DELIVERY) {
                $option = "";
                if($template && $template->getDestination()) {
                    $option = "<option value='{$template->getDestination()->getId()}'>{$template->getDestination()->getLabel()}</option>";
                }

                $data[] = [
                    "label" => "Type de livraison*",
                    "value" => "<select name='deliveryType' class='data form-control' required>$typeOptions</select>",
                ];

                $data[] = [
                    "label" => "Destination*",
                    "value" => "<select name='destination' data-s2='location' class='data form-control' required>$option</select>",
                ];
            }

            if($category === Type::LABEL_DELIVERY) {
                $freeFieldTemplate = $twig->createTemplate('
                    <div data-type="{{ free_field.type.id }}">
                        {% include "free_field/freeFieldsEdit.html.twig" with {
                            freeFields: [free_field],
                            freeFieldValues: value,
                            colType: "col-12",
                            requiredType: "requiredCreate",
                            actionType: "new",
                            disabledNeeded: true,
                            showLabels: false,
                        } %}
                    </div>');

                foreach($types as $type) {
                    $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::DEMANDE_LIVRAISON);

                    /** @var FreeField $freeField */
                    foreach($freeFields as $freeField) {
                        $data[] = [
                            "label" => $freeField->getLabel(),
                            "value" => $freeFieldTemplate->render([
                                "free_field" => $freeField,
                                "value" => $template ? $template->getFreeFields() : [],
                            ]),
                            "data" => [
                                "type" => $freeField->getType()->getId(),
                            ],
                            "hidden" => true,
                        ];
                    }
                }
            }
        }
        else if($template) {
            $data = [[
                "label" => "Type de livraison",
                "value" => FormatHelper::type($template->getRequestType()),
            ]];

            if($template instanceof DeliveryRequestTemplate) {
                $data[] = [
                    "label" => "Destination",
                    "value" => FormatHelper::location($template->getDestination()),
                ];
            }

            $freeFieldValues = $freeFieldService->getFilledFreeFieldArray(
                $entityManager,
                $template,
                null,
                $template->getRequestType()->getCategory()->getLabel()
            );

            $data = array_merge($data, $freeFieldValues);
        }

        return $this->json([
            "success" => true,
            "data" => $data ?? [],
        ]);
    }

    /**
     * @Route("/modele-demande/api/{template}", name="settings_request_template_api", options={"expose"=true})
     */
    public function requestTemplateApi(Request $request, ?RequestTemplate $template = null): Response {
        $edit = filter_var($request->query->get("edit"), FILTER_VALIDATE_BOOLEAN);

        $class = "form-control data";

        if($template instanceof DeliveryRequestTemplate || $template instanceof CollectRequestTemplate) {
            $lines = $template->getLines();
        }

        $rows = [];
        foreach($lines ?? [] as $line) {
            $location = FormatHelper::location($line->getReference()->getEmplacement());
            $actions = "
                <input type='hidden' class='$class' name='id' value='{$line->getId()}'>
                <button class='btn btn-silent delete-row' data-id='{$line->getId()}'>
                    <i class='wii-icon wii-icon-trash text-primary'></i>
                </button>
            ";
            if($edit) {
                $option = "<option value='{$line->getReference()->getId()}'>{$line->getReference()->getReference()}</option>";

                $rows[] = [
                    "id" => $line->getId(),
                    "actions" => $actions,
                    "reference" => "<select name='reference' data-s2='reference' class='$class' required>$option</select>",
                    "label" => "<div class='template-label'>{$line->getReference()->getLibelle()}</div>",
                    "location" => "<div class='template-location'>{$location}</div>",
                    "quantityToTake" => "<input type='number' name='quantityToTake' class='$class' value='{$line->getQuantityToTake()}' required/>",
                ];
            } else {
                $rows[] = [
                    "id" => $line->getId(),
                    "actions" => $actions,
                    "reference" => $line->getReference()->getReference(),
                    "label" => $line->getReference()->getLibelle(),
                    "location" => $location,
                    "quantityToTake" => $line->getQuantityToTake(),
                ];
            }
        }

        if($edit) {
            $rows[] = [
                "actions" => "<span class='d-flex justify-content-start align-items-center add-row'><span class='wii-icon wii-icon-plus'></span></span>",
                "reference" => "",
                "label" => "",
                "location" => "",
                "quantityToTake" => "",
            ];
        }

        return $this->json([
            "data" => $rows,
        ]);
    }

    /**
     * @Route("/modele-demande/ligne/supprimer/{entity}", name="settings_request_template_line_delete", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteRequestTemplateLine(EntityManagerInterface $manager, RequestTemplateLine $entity) {
        $entity->setRequestTemplate(null);
        $manager->remove($entity);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Le modèle de demande a bien été mise à jour",
        ]);
    }
    /**
     * @Route("/verification/{requestTemplate}", name="settings_request_template_check_delete", methods={"GET"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function checkRequestTemplateCanBeDeleted(RequestTemplate $requestTemplate): Response {
        if ($requestTemplate->getTriggerActions()->isEmpty()) {
            $success = true;
            $message = "Vous êtes sur le point de supprimer le modèle de demande <strong>{$requestTemplate->getName()}</strong>";
        }
        else {
            $success = false;
            $message = 'Ce modèle de demande est utilisé par un ou plusieurs actionneurs, vous ne pouvez pas le supprimer';
        }

        return $this->json([
            'success' => $success,
            'message' => $message
        ]);
    }

    /**
     * @Route("/modele-demande/supprimer/{requestTemplate}", name="settings_request_template_delete", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteRequestTemplate(EntityManagerInterface $manager,
                                          RequestTemplate $requestTemplate): JsonResponse {

        if ($requestTemplate->getTriggerActions()->isEmpty()) {
            $success = true;
            $message = 'Le modèle de demande a bien été supprimé';
            $manager->remove($requestTemplate);
            $manager->flush();
        }
        else {
            $success = false;
            $message = 'Ce modèle de demande est utilisé par un ou plusieurs actionneurs, vous ne pouvez pas le supprimer';
        }

        return $this->json([
            'success' => $success,
            'message' => $message
        ]);
    }
}
