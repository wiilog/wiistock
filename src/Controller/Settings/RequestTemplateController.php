<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategoryType;
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
use App\Service\FieldsParamService;
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
     * @Required
     */
    public FieldsParamService $fieldsParamService;

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
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

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
                /**
                 * @var DeliveryRequestTemplate $template
                 */
                $option = "";
                if($template && $template->getDestination()) {
                    $option = "<option value='{$template->getDestination()->getId()}'>{$template->getDestination()->getLabel()}</option>";
                }
                $comment = $template?->getComment();
                $data[] = [
                    "label" => "Type de livraison*",
                    "value" => "<select name='deliveryType' class='data form-control' required>$typeOptions</select>",
                ];

                $data[] = [
                    "label" => "Destination*",
                    "value" => "<select name='destination' data-s2='location' data-parent='body' class='data form-control' required>$option</select>",
                ];

                $data[] = [
                    "label" => "Commentaire",
                    "value" => "<div class='wii-one-line-wysiwyg ql-editor data' data-wysiwyg='comment'>$comment</div>",
                ];
            }
            else if ($category === Type::LABEL_COLLECT) {
                /**
                 * @var CollectRequestTemplate $template
                 */
                $option = "";
                if($template && $template->getCollectPoint()) {
                    $option = "<option value='{$template->getCollectPoint()->getId()}'>{$template->getCollectPoint()->getLabel()}</option>";
                }
                $subject = $template?->getSubject();
                $comment = $template?->getComment();
                $stockCheck = $template?->isStock() ? 'checked' : '';
                $destructCheck = $template?->isDestruct() ? 'checked' : '';
                $data[] = [
                    "label" => "Objet*",
                    "value" => "<input name='subject' class='data form-control' required value='$subject'>",
                ];

                $data[] = [
                    "label" => "Type de la collecte*",
                    "value" => "<select name='collectType' class='data form-control' required>$typeOptions</select>",
                ];

                $data[] = [
                    "label" => "Point de collecte*",
                    "value" => "<select name='collectPoint' data-s2='location' data-parent='body' class='data form-control' required>$option</select>",
                ];

                $data[] = [
                    "label" => "Commentaire",
                    "value" => "<div class='wii-one-line-wysiwyg ql-editor data' data-wysiwyg='comment'>$comment</div>",
                ];
            } else if ($category === Type::LABEL_HANDLING) {
                /**
                 * @var HandlingRequestTemplate $template
                 */
                $subject = $template?->getSubject();
                $delay = $template?->getDelay();
                $source = $template?->getSource();
                $destination = $template?->getDestination();
                $comment = $template?->getComment();
                $carriedOutOperations = $template?->getCarriedOutOperationCount();
                $status = "";
                $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_HANDLING);
                $action = $template ? 'requiredEdit' : 'requiredCreate';
                $emergencyIsNeeded = $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_EMERGENCY, $action);
                $sourceIsNeeded = $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_LOADING_ZONE, $action)
                    ? 'required'
                    : '';
                $destinationIsNeeded = $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_UNLOADING_ZONE, $action)
                    ? 'required'
                    : '';
                $carriedOutOperationsIsNeeded = $this->fieldsParamService->isFieldRequired($fieldsParam, FieldsParam::FIELD_CODE_CARRIED_OUT_OPERATION_COUNT, $action)
                    ? 'required'
                    : '';
                if($template && $template->getRequestStatus()) {
                    $status = "<option value='{$template->getRequestStatus()->getId()}'>{$template->getRequestStatus()->getNom()}</option>";
                }
                $data[] = [
                    "label" => "Type de service*",
                    "value" => "<select name='handlingType' class='data form-control' required>$typeOptions</select>",
                ];
                $data[] = [
                    "label" => "Objet*",
                    "value" => "<input name='subject' class='data form-control' required value='$subject'>",
                ];
                $data[] = [
                    "label" => "Statut*",
                    "value" => "<select name='status' data-s2='status' data-parent='body' class='data form-control' data-include-params-parent='.main-entity-content-form' data-include-params='select[name=handlingType]' required>$status</select>",
                ];
                $data[] = [
                    "label" => "Date attendue*",
                    "value" => "
                        <div class='d-flex align-items-center'>
                            <span style='white-space: nowrap'>H +</span>
                            <input class='form-control data needed mx-2'
                                   style='width: 70px'
                                   type='number'
                                   value='$delay'
                                   name='delay'
                                   step='1'
                                   max='2000000'>
                            <span style='white-space: nowrap'>à la création</span>
                        </div>
                    ",
                ];

                $data[] = [
                    "label" => "Urgence",
                    "value" => $this->renderView('settings/trace/services/handling_emergency_template.html.twig', [
                        'request_template' => $template,
                        'emergencies' => $fieldsParamRepository->getElements(FieldsParam::ENTITY_CODE_HANDLING, FieldsParam::FIELD_CODE_EMERGENCY),
                        'required' => $emergencyIsNeeded
                    ]),
                ];

                $data[] = [
                    "label" => "Chargement" . ($sourceIsNeeded === 'required' ? "*" : ''),
                    "value" => "<input name='source' class='data form-control' $sourceIsNeeded value='$source'>",
                ];

                $data[] = [
                    "label" => "Déchargement" . ($destinationIsNeeded === 'required' ? "*" : ''),
                    "value" => "<input name='destination' class='data form-control' $destinationIsNeeded value='$destination'>",
                ];

                $data[] = [
                    "label" => "Nombre d'opération(s) réalisée(s)" . ($carriedOutOperationsIsNeeded === 'required' ? "*" : ''),
                    "value" => "<input name='carriedOutOperationCount' class='data form-control' $carriedOutOperationsIsNeeded value='$carriedOutOperations'>",
                ];
            }

            $freeFieldTemplate = $twig->createTemplate('
                    <div data-type="{{ free_field.type.id }}" class="inline-select">
                        {% include "free_field/freeFieldsEdit.html.twig" with {
                            freeFields: [free_field],
                            freeFieldValues: value,
                            colType: "col-12",
                            actionType: "edit",
                            disabledNeeded: true,
                            showLabels: false,
                        } %}
                    </div>');

            foreach ($types as $type) {

                $freeFields = $freeFieldsRepository->findByType($type->getId());
                $filteredFreeField = Stream::from($freeFields)
                    ->filter(fn(FreeField $freeField) => (!$template && $freeField->getDisplayedCreate()) || $template)
                    ->toArray();

                /** @var FreeField $freeField */
                foreach ($filteredFreeField as $freeField) {
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
            if ($category === Type::LABEL_COLLECT) {
                $data[] = [
                    "label" => "Destination*",
                    "value" =>
                        "
                                <div class='wii-switch bigger' data-title='Destination'>
                                    <input type='radio' class='data' name='destination' value='0' content='Destruction' required $destructCheck>
                                    <input type='radio' class='data' name='destination' value='1' content='Mise en stock' required $stockCheck>
                                </div>
                            "
                ];
            } else if ($category === Type::LABEL_HANDLING) {
                $data[] = [
                    "label" => "Commentaire",
                    "wide" => true,
                    "value" => "<div class='wii-one-line-wysiwyg ql-editor data' data-wysiwyg='comment'>$comment</div>",
                ];
                $data[] = [
                    "label" => "",
                    "wide" => true,
                    "value" => $this->renderView('attachment/attachment.html.twig', [
                        'isNew' => false,
                        'attachments' => $template?->getAttachments(),
                        'editAttachments' => true,
                        'fieldNameClass' => 'wii-field-name',
                    ])
                ];
            }
        }
        else if($template) {
            $data = [];
            if ($template instanceof DeliveryRequestTemplate) {
                $data[] = [
                    "label" => "Type de livraison",
                    "value" => FormatHelper::type($template->getRequestType()),
                ];
                $data[] = [
                    "label" => "Destination",
                    "value" => FormatHelper::location($template->getDestination()),
                ];
            } else if ($template instanceof CollectRequestTemplate) {
                $data[] = [
                    "label" => "Objet",
                    "value" => $template->getSubject(),
                ];
                $data[] = [
                    "label" => "Type de la collecte",
                    "value" => FormatHelper::type($template->getRequestType()),
                ];
                $data[] = [
                    "label" => "Point de collecte",
                    "value" => FormatHelper::location($template->getCollectPoint()),
                ];
                $data[] = [
                    "label" => "Destination",
                    "value" => $template->isStock() ? 'Mise en stock' : 'Destruction',
                ];
            } else if ($template instanceof HandlingRequestTemplate) {
                $data[] = [
                    "label" => "Type de service",
                    "value" => FormatHelper::type($template->getRequestType()),
                ];
                $data[] = [
                    "label" => "Objet",
                    "value" => $template->getSubject(),
                ];
                $data[] = [
                    "label" => "Statut",
                    "value" => FormatHelper::status($template->getRequestStatus()),
                ];
                $data[] = [
                    "label" => "Date attendue",
                    "value" => 'H+' . $template->getDelay() . ' à la création',
                ];

                $data[] = [
                    "label" => "Urgence",
                    "value" => $template->getEmergency() ?: 'Non urgent',
                ];

                $data[] = [
                    "label" => "Chargement",
                    "value" => $template->getSource() ?: '-',
                ];

                $data[] = [
                    "label" => "Déchargement",
                    "value" => $template->getDestination() ?: '-',
                ];

                $data[] = [
                    "label" => "Nombre d'opération(s) réalisée(s)",
                    "value" => $template->getCarriedOutOperationCount() ?: '-',
                ];

            }


            $freeFieldValues = $freeFieldService->getFilledFreeFieldArray(
                $entityManager,
                $template,
                ['type' => $template->getRequestType()]
            );

            $data = array_merge($data, $freeFieldValues);

            if ($template instanceof HandlingRequestTemplate) {
                $data[] = [
                    "label" => "",
                    "value" => $this->renderView('attachment/attachment.html.twig', [
                        'isNew' => false,
                        'attachments' => $template?->getAttachments(),
                        'editAttachments' => false,
                        'fieldNameClass' => 'wii-field-name',
                        'bigger' => ''
                    ])
                ];
            }
            if (strip_tags($template->getComment())) {
                $data[] = [
                    "label" => "Commentaire",
                    "value" => "<div class='ql-editor'>{$template->getComment()}</div>",
                ];
            }
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
                    "reference" => "<select name='reference' data-s2='reference' data-parent='body' class='$class' required>$option</select>",
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
     * @Route("/verification/demande/{requestTemplate}", name="settings_request_template_check_delete", methods={"GET"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
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
