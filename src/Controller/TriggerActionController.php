<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\IOT\TriggerAction;
use App\Entity\Menu;
use App\Entity\IOT\AlertTemplate;
use App\Entity\RequestTemplate\DeliveryRequestTemplateTriggerAction;
use App\Entity\RequestTemplate\DeliveryRequestTemplateTypeEnum;
use App\Entity\RequestTemplate\RequestTemplate;
use App\Service\IOT\IOTService;
use App\Service\TriggerActionService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use WiiCommon\Helper\Stream;

#[Route("/iot/actionneurs")]
class TriggerActionController extends AbstractController
{
    #[Route("/liste", name: "trigger_action_index")]
    #[HasPermission([Menu::IOT, Action::DISPLAY_TRIGGER])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $sensorWrappers= $entityManager->getRepository(SensorWrapper::class)->findBy([
            'deleted' => false
        ],["name"=>"ASC"]);
        return $this->render('trigger_action/index.html.twig', [
            "sensorWrappers" => $sensorWrappers,
        ]);
    }

    #[Route("/api", name: "trigger_action_api", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::IOT, Action::DISPLAY_TRIGGER])]
    public function api(Request $request,
                        TriggerActionService $triggerActionService): Response {
        $data = $triggerActionService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    #[Route("/creer", name: "trigger_action_new", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::IOT, Action::CREATE], mode: HasPermission::IN_JSON)]
    public function new(EntityManagerInterface $entityManager,
                        Request $request,
                        TriggerActionService $triggerActionService): Response
    {
        if($data = json_decode($request->getContent(), true)){
            $sensorWrapperRepository = $entityManager->getRepository(SensorWrapper::class);
            if($data['sensorWrapper']){
                $name = $data['sensorWrapper'];
                $sensorWrapper = $sensorWrapperRepository->findOneBy(["id" => $name, 'deleted' => false]);
            } else {
                $sensorWrapper = null;
            }

            $profile = $sensorWrapper->getSensor()->getProfile();
            $profileName = $profile->getName();
            if ($sensorWrapper && $profile->getMaxTriggers() > $sensorWrapper->getTriggerActions()->count()) {
                if (in_array($profileName, [
                    IOTService::INEO_SENS_ACS_HYGRO,
                    IOTService::INEO_SENS_ACS_TEMP_HYGRO,
                    IOTService::INEO_SENS_ACS_TEMP,
                    IOTService::KOOVEA_TAG,
                    IOTService::YOKOGAWA_XS550_XS110A,
                    IOTService::ENGINKO_LW22CCM,
                ])) {
                    // temp and temp && hygro
                    $triggerActionConfigs = [
                        "sensorHygrometryLimitLower" => [
                            "limit" => TriggerAction::LOWER,
                            "type" => TriggerAction::ACTION_TYPE_HYGROMETRY,
                            "templateType" => "templateTypeLowerHygro",
                            "template" => "templatesForLowerHygro",
                        ],
                        "sensorHygrometryLimitHigher" => [
                            "limit" => TriggerAction::HIGHER,
                            "type" => TriggerAction::ACTION_TYPE_HYGROMETRY,
                            "templateType" => "templateTypeHigherHygro",
                            "template" => "templatesForHigherHygro",
                        ],
                        "sensorTemperatureLimitLower" => [
                            "limit" => TriggerAction::LOWER,
                            "type" => TriggerAction::ACTION_TYPE_TEMPERATURE,
                            "templateType" => "templateTypeLowerTemp",
                            "template" => "templatesForLowerTemp",
                        ],
                        "sensorTemperatureLimitHigher" => [
                            "limit" => TriggerAction::HIGHER,
                            "type" => TriggerAction::ACTION_TYPE_TEMPERATURE,
                            "templateType" => "templateTypeHigherTemp",
                            "template" => "templatesForHigherTemp",
                        ],
                    ];

                    foreach ($triggerActionConfigs as $key => $config) {
                        $limitValue = $data[$key] ?? false;
                        if ($limitValue || $limitValue === 0) {
                            $triggerAction = $triggerActionService->createTriggerActionByTemplateType(
                                $entityManager,
                                $sensorWrapper,
                                $data[$config["templateType"]],
                                $data[$config["template"]],
                                [
                                    'limit' => $config["limit"],
                                    $config["type"] => $limitValue,
                                ]
                            );
                            $entityManager->persist($triggerAction);
                        }
                    }
                } else if (in_array($profileName, [
                    IOTService::DEMO_ACTION,
                    IOTService::INEO_SENS_ACS_BTN,
                    IOTService::SYMES_ACTION_MULTI,
                    IOTService::SYMES_ACTION_SINGLE,
                ])) {
                    // button
                    $buttonIndex = $data['buttonIndex'] ?? false;

                    $valid = $buttonIndex
                        ? $sensorWrapper->getTriggerActions()->filter(fn(TriggerAction $trigger)  => (
                            ($trigger->getConfig()['buttonIndex'] ?? null) === intval($data['buttonIndex'])
                        ))->isEmpty()
                        : true;
                    if (!$valid) {
                        return $this->json([
                            'success' => false,
                            'msg' => "Il existe déjà un actionneur pour ce capteur et ce numéro de bouton."
                        ]);
                    }

                    $triggerAction = $triggerActionService->createTriggerActionByTemplateType(
                        $entityManager,
                        $sensorWrapper,
                        $data["templateType"],
                        $data["templates"] ?? null,
                        [
                            ...isset($data['buttonIndex']) ? ["buttonIndex" => $data['buttonIndex']] : [],
                            ...isset($data["dropOnLocation"]) ? ["dropOnLocation" => $data["dropOnLocation"]] : [],
                        ]
                    );
                    $entityManager->persist($triggerAction);
                } else if ($profileName == IOTService::INEO_TRK_TRACER) {
                    // tracer
                    if(isset($data['zoneId'])) {
                        $triggerAction = $triggerActionService->createTriggerActionByTemplateType(
                            $entityManager,
                            $sensorWrapper,
                            $data["templateType"],
                            $data["templates"] ?? null,
                            [
                                $data["action"] => $data["zoneId"],
                                ...isset($data["dropOnLocation"]) ? ["dropOnLocation" => $data["dropOnLocation"]] : [],
                            ]
                        );

                        if ($data['lastTrigger'] ?? false) {
                            $triggerAction->setLastTrigger((new DateTime())->setTimestamp($data['lastTrigger']));
                        }

                        $entityManager->persist($triggerAction);
                    }
                }

                if (!isset($triggerAction)) {
                    return $this->json([
                        'success' => false,
                        'msg' => "Aucun actionneur n'a été créé, veuillez remplir les champs"
                    ]);
                }

                try {
                    $entityManager->flush();
                } /** @noinspection PhpRedundantCatchClauseInspection */
                catch (UniqueConstraintViolationException $e) {
                    return $this->json([
                        'success' => false,
                        'msg' => "Erreur lors de la création de l'actionneur"
                    ]);
                }
            } else {
                return $this->json([
                    'success' => false,
                    'msg' => "Le capteur choisi ne peut avoir plus de "
                        . $sensorWrapper->getSensor()->getProfile()->getMaxTriggers() . " action(s) associée(s)."
                ]);
            }
            return $this->json([
                'success' => true,
                'msg' => "L'actionneur a bien été créé",
            ]);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/supprimer", name: "trigger_action_delete", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::IOT, Action::DELETE], mode: HasPermission::IN_JSON)]
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $triggerActionRepository = $entityManager->getRepository(TriggerAction::class);
            $triggerAction = $triggerActionRepository->find($data['request']);

            $entityManager->remove($triggerAction);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => "L'actionneur a bien été supprimé"
            ]);

        }
        throw new BadRequestHttpException();

    }

    #[Route("/api-modifier", name: "trigger_action_api_edit", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::IOT, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $triggerActionRepository = $entityManager->getRepository(TriggerAction::class);

            $triggerAction = $triggerActionRepository->find($data['id']);

            $sensor = $triggerAction->getSensorWrapper()->getSensor();

            $alertTemplate = $triggerAction->getAlertTemplate();
            $requestTemplate = $triggerAction->getRequestTemplate();
            if ($alertTemplate) {
                $alertTemplateRepository = $entityManager->getRepository(AlertTemplate::class);
                $templates = $alertTemplateRepository->findAll();
                $templateId = $alertTemplate->getId();
            } else if ($requestTemplate) {
                $requestTemplateRepository = $entityManager->getRepository(RequestTemplate::class);
                $templates = $requestTemplateRepository->findAll();
                $templateId = $requestTemplate->getId();
            }

            $templateTypes = TriggerAction::TEMPLATE_TYPES;
            if (in_array($triggerAction->getActionType(), [TriggerAction::ACTION_TYPE_ZONE_ENTER, TriggerAction::ACTION_TYPE_ZONE_EXIT])) {
                $templateTypes[TriggerAction::DROP_ON_LOCATION] = 'Dépose sur emplacement';
            }

            $json = $this->renderView('trigger_action/edit_content_modal.html.twig', [
                'triggerAction' => $triggerAction,
                'templateTypes' => $templateTypes,
                'profile' => $sensor ? $sensor->getProfile()->getName() : "",
                'templates' => $templates ?? [],
                'templateId' => $templateId ?? null,
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    #[Route("/modifier", name: "trigger_action_edit", options: ["expose" => true], methods: ["POST"], condition: "request.isXmlHttpRequest()")]
    #[HasPermission([Menu::IOT, Action::EDIT], mode: HasPermission::IN_JSON)]
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         TriggerActionService $triggerActionService): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $triggerActionRepository = $entityManager->getRepository(TriggerAction::class);
            $requestTemplateRepository= $entityManager->getRepository(RequestTemplate::class);
            $alertTemplateRepository= $entityManager->getRepository(AlertTemplate::class);

            $triggerAction = $triggerActionRepository->find($data['id']);

            if ($triggerAction->getSensorWrapper()->getSensor()->getProfile()->getName() === IOTService::INEO_TRK_TRACER ) {
                // this simplest way to edit the triggerAction is to delete it and create a new one
                $sensorWrapper = $triggerAction->getSensorWrapper();
                $sensorWrapper->removeTriggerAction($triggerAction);
                $entityManager->remove($triggerAction);
                $creationResponce = $this->new($entityManager, $request, $triggerActionService);

                return json_decode($creationResponce->getContent(), true)["success"]
                    ? new JsonResponse([
                        'success' => true,
                        'msg' => "L'actionneur a bien été modifié",
                    ])
                    : $creationResponce;
            }

            $type = $data['templateType'];

            if($type === TriggerAction::ALERT){
                $alertTemplate = $alertTemplateRepository->findOneBy(["id" => $data['templatesForAction'] ?? $data['templates']]);
                $triggerAction
                    ->setRequestTemplate(null)
                    ->setAlertTemplate($alertTemplate);
            } else if ($type === TriggerAction::REQUEST) {
                $requestTemplate = $requestTemplateRepository->findOneBy(["id" => $data['templatesForAction'] ?? $data['templates']]);
                $triggerAction
                    ->setAlertTemplate(null)
                    ->setRequestTemplate($requestTemplate);
            }

            if(isset($data['zone'])){
                $json = ['zone' => $data['zone']];
                if(isset($data['buttonIndex'])){
                    $valid = $triggerAction->getSensorWrapper()->getTriggerActions()->filter(
                        function(TriggerAction $trigger) use ($data, $triggerAction) {
                            $buttonIndex = $trigger->getConfig()['buttonIndex'] ?? null;
                            return $trigger->getId() !== $triggerAction->getId() && intval($buttonIndex) === intval($data['buttonIndex']);
                        }
                    )->isEmpty();
                    if (!$valid) {
                        return $this->json([
                            'success' => false,
                            'msg' => "Il existe déjà un actionneur pour ce capteur et ce numéro de bouton."
                        ]);
                    }
                    $json['buttonIndex'] = $data['buttonIndex'];
                }
                $triggerAction->setConfig($json);
            }

            if(isset($data['sensorDataLimit'])){
                $triggerAction->setConfig([
                    'limit' => $data['comparators'],
                    $data['actionType'] => $data['sensorDataLimit'],
                ]);
            }

            $entityManager->flush();

            return $this->json([
                'success' => true,
                'msg' => "L'actionneur a bien été modifié",
            ]);

        }
        throw new BadRequestHttpException();

    }

    #[Route('/get-templates', name: 'get_templates', options: ['expose' => true], methods: ['GET'], condition: "request.isXmlHttpRequest()")]
    public function getTemplates(EntityManagerInterface $entityManager,
                                 Request $request): Response {

        $alertTemplateRepository = $entityManager->getRepository(AlertTemplate::class);
        $requestTemplateRepository = $entityManager->getRepository(RequestTemplate::class);

        $query = $request->query;

        $type = $query->get('type');

        if($type === TriggerAction::ALERT) {
            $templates = $alertTemplateRepository->getTemplateForSelect();
        } else {
            $templates = $requestTemplateRepository->getTemplateForSelect($entityManager);
        }

        return $this->json([
            "results" => $templates
        ]);
    }

    #[Route("/get-sensor-by-name", name: "get_sensor_by_name", options: ["expose" => true], methods: ["GET", "POST"], condition: "request.isXmlHttpRequest()")]
    public function getSensorByName(EntityManagerInterface $entityManager,
                                    Request $request): Response {

        $sensorWrapperRepository = $entityManager->getRepository(SensorWrapper::class);
        $sensorRepository = $entityManager->getRepository(Sensor::class);

        $query = $request->query;
        $type = "";
        $sensor = null;
        if ($query->has('name')){
            $name = $query->get('name');
            $sensorWrapper = $sensorWrapperRepository->find($name);
            $sensor = $sensorWrapper->getSensor();
        } else if ($query->has('code')){
            $code = $query->get('code');
            $sensor = $sensorRepository->findOneBy(["code" => $code]);
        }
        $type = $sensor?->getType();
        $typeLabel = $type?->getLabel();

        $html = "";
        if(!isset($sensorWrapper) && !isset($sensor)){
            return $this->json([
                "success" => false,
                "msg" => "Ce capteur n'existe pas",
            ]);
        } else if((isset($sensorWrapper) || isset($sensor)) && $typeLabel === Sensor::ACTION){
            $html = $this->renderView('trigger_action/modalButton.html.twig', [
                "profile" => $sensor ? $sensor->getProfile()->getName() : "",
                "templateTypes" => [
                    TriggerAction::DROP_ON_LOCATION => "Dépose sur emplacement",
                    ...TriggerAction::TEMPLATE_TYPES,
                ]
            ]);
        } else if((isset($sensorWrapper) || isset($sensor)) && $typeLabel === Sensor::TEMPERATURE){
            $html = $this->renderView('trigger_action/modalMultipleTemperatures.html.twig', [
                "templateTypes" => TriggerAction::TEMPLATE_TYPES,
            ]);
        } else if((isset($sensorWrapper) || isset($sensor)) && $typeLabel === Sensor::TEMPERATURE_HYGROMETRY) {
            $html = $this->renderView('trigger_action/modalTemperatureHygrometry.html.twig', [
                "templateTypes" => TriggerAction::TEMPLATE_TYPES,
            ]);
        } else if((isset($sensorWrapper) || isset($sensor)) && $typeLabel === Sensor::HYGROMETRY) {
            $html = $this->renderView('trigger_action/modalMultipleHygrometry.html.twig', [
                "templateTypes" => TriggerAction::TEMPLATE_TYPES,
            ]);
        } else if((isset($sensorWrapper) || isset($sensor)) && $typeLabel === Sensor::TRACER) {
            $html = $this->renderView('trigger_action/modalTracer.html.twig', [
                "templateTypes" => [
                    TriggerAction::DROP_ON_LOCATION => "Dépose sur emplacement",
                    ...TriggerAction::TEMPLATE_TYPES,
                ]
            ]);
        }
        return $this->json($html);
    }
}
