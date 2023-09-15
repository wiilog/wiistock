<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\IOT\TriggerAction;
use App\Entity\Menu;
use App\Entity\Action;
use App\Service\TriggerActionService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use WiiCommon\Helper\Stream;

/**
 * @Route("/iot/actionneurs")
 */

class TriggerActionController extends AbstractController
{

    /**
     * @Route("/liste", name="trigger_action_index")
     * @HasPermission({Menu::IOT, Action::DISPLAY_TRIGGER})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        $sensorWrappers= $entityManager->getRepository(SensorWrapper::class)->findBy([
            'deleted' => false
        ],["name"=>"ASC"]);
        return $this->render('trigger_action/index.html.twig', [
            "sensorWrappers" => $sensorWrappers,
        ]);
    }

    /**
     * @Route("/api", name="trigger_action_api", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DISPLAY_TRIGGER})
     */
    public function api(Request $request,
                        TriggerActionService $triggerActionService): Response {
        $data = $triggerActionService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="trigger_action_new", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
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
            if ($sensorWrapper && $sensorWrapper->getSensor()->getProfile()->getMaxTriggers() > $sensorWrapper->getTriggerActions()->count()) {
                $triggerActionConfigs = [
                    "sensorHygrometryLimitLower" => [
                        "limit" => TriggerAction::LOWER,
                        "configName" => [TriggerAction::ACTION_TYPE_HYGROMETRY],
                        "templateType" => "templateTypeLowerHygro",
                        "template" => "templatesForLowerHygro",
                    ],
                    "sensorHygrometryLimitHigher" => [
                        "limit" => TriggerAction::HIGHER,
                        "configName" => [TriggerAction::ACTION_TYPE_HYGROMETRY],
                        "templateType" => "templateTypeHigherHygro",
                        "template" => "templatesForHigherHygro",
                    ],
                    "sensorTemperatureLimitLower" => [
                        "limit" => TriggerAction::LOWER,
                        "configValues" => [TriggerAction::ACTION_TYPE_TEMPERATURE],
                        "data" => "sensorTemperatureLimitLower",
                        "templateType" => "templateTypeLowerTemp",
                        "template" => "templatesForLowerTemp",
                    ],
                    "sensorTemperatureLimitHigher" => [
                        "limit" => TriggerAction::HIGHER,
                        "configValues" => [TriggerAction::ACTION_TYPE_TEMPERATURE],
                        "templateType" => "templateTypeHigherTemp",
                        "template" => "templatesForHigherTemp",
                    ],
                    "zone" => [
                        "configValues" => ["zone", "buttonIndex"],
                    ]
                ];

                foreach ($triggerActionConfigs as $key => $triggerActionConfig) {
                    $config = Stream::from($data[$key] ?? [])
                        ->keymap(fn(string $key) => [$key, $data[$key] ?? null])
                        ->toArray();
                    // at least one element defined
                    $valueDefined = Stream::from($config)->some(fn($value) => isset($value));

                    if ($valueDefined) {
                        if (isset($triggerActionConfig["limit"])) {
                            $config['limit'] = $triggerActionConfig["limit"];
                        }

                        if(isset($config['buttonIndex'])) {
                            $buttonIndexNeverSet = $sensorWrapper->getTriggerActions()
                                ->filter(fn(TriggerAction $trigger) => (
                                    intval($trigger->getConfig()['buttonIndex'] ?? null) === intval($config['buttonIndex'])
                                ))
                                ->isEmpty();
                            if (!$buttonIndexNeverSet) {
                                return $this->json([
                                    'success' => false,
                                    'msg' => "Il existe déjà un actionneur pour ce capteur et ce numéro de bouton."
                                ]);
                            }
                        }

                        $triggerAction = $triggerActionService->createTriggerActionByTemplateType(
                            $entityManager,
                            $sensorWrapper,
                            $data[$triggerActionConfig["templateType"]] ?? "",
                            $data[$triggerActionConfig["template"]] ?? "",
                            $config
                        );

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

    /**
     * @Route("/supprimer", name="trigger_action_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
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

    /**
     * @Route("/api-modifier", name="trigger_action_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
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

            $json = $this->renderView('trigger_action/edit_content_modal.html.twig', [
                'triggerAction' => $triggerAction,
                'templateTypes' => TriggerAction::TEMPLATE_TYPES,
                'profile' => $sensor ? $sensor->getProfile()->getName() : "",
                'templates' => $templates ?? [],
                'templateId' => $templateId ?? null,
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="trigger_action_edit", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         TriggerActionService $triggerActionService): Response {

        if ($data = json_decode($request->getContent(), true)) {
            $triggerActionRepository = $entityManager->getRepository(TriggerAction::class);
            $requestTemplateRepository= $entityManager->getRepository(RequestTemplate::class);
            $alertTemplateRepository= $entityManager->getRepository(AlertTemplate::class);

            $triggerAction = $triggerActionRepository->find($data['id']);

            $type = $data['templateType'];
            if($type === TriggerAction::ALERT){
                $alertTemplate = $alertTemplateRepository->findOneBy(["id" => $data['templatesForAction']]);
                $triggerAction->setAlertTemplate($alertTemplate);
            } else {
                $requestTemplate = $requestTemplateRepository->findOneBy(["id" => $data['templatesForAction']]);
                $triggerAction->setRequestTemplate($requestTemplate);
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
            $templates = $requestTemplateRepository->getTemplateForSelect();
        }
        return $this->json([
            "results" => $templates
        ]);
    }

    /**
     * @Route("/get-sensor-by-name", name="get_sensor_by_name", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
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
        $type = isset($sensor) ? $sensor->getType() : null;
        $typeLabel = isset($type) ? $type->getLabel() : null;

        $html = "";
        if(!isset($sensorWrapper) && !isset($sensor)){
            return $this->json([
                "success" => false,
                "msg" => "Ce capteur n'existe pas",
            ]);
        } else if((isset($sensorWrapper) || isset($sensor)) && $typeLabel === Sensor::ACTION){
            $html = $this->renderView('trigger_action/modalButton.html.twig', [
                'profile' => $sensor ? $sensor->getProfile()->getName() : ""
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
        }

        return $this->json($html);
    }
}
