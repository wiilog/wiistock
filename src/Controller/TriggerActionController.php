<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\CollectRequestTemplate;
use App\Entity\IOT\DeliveryRequestTemplate;
use App\Entity\IOT\HandlingRequestTemplate;
use App\Entity\IOT\RequestTemplate;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\IOT\TriggerAction;
use App\Entity\Menu;
use App\Entity\Action;
use App\Service\AttachmentService;
use App\Service\TriggerActionService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        $sensorWrappers= $entityManager->getRepository(SensorWrapper::class)->findBy([],["name"=>"ASC"]);

        return $this->render('trigger_action/index.html.twig', [
            "sensorWrappers" => $sensorWrappers,
            "templateTypes" => TriggerAction::TEMPLATE_TYPES,
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
    public function new(EntityManagerInterface $entityManager, Request $request): Response
    {
        if($data = json_decode($request->getContent(), true)) {

            $sensorWrapperRepository= $entityManager->getRepository(SensorWrapper::class);
            $sensorRepository= $entityManager->getRepository(Sensor::class);
            $requestTemplateRepository= $entityManager->getRepository(RequestTemplate::class);
            $alertTemplateRepository= $entityManager->getRepository(AlertTemplate::class);

            $triggerAction = new TriggerAction();

            if($data['sensorWrapper']){
                $name = $data['sensorWrapper'];
                $sensorWrapper = $sensorWrapperRepository->findOneBy(["name" => $name]);
                $triggerAction->setSensorWrapper($sensorWrapper);
            } else if($data['sensor']){
                $code = $data['sensor'];
                $sensor = $sensorRepository->findOneBy(["code" => $code]);
                $sensorWrapper = $sensorWrapperRepository->findOneBy(["sensor" => $sensor]);
                $triggerAction->setSensorWrapper($sensorWrapper);
            }

            if($data['templateType'] === TriggerAction::REQUEST){
                $name = $data['templates'];
                $requestTemplate = $requestTemplateRepository->findOneBy(["id" => $name]);
                $triggerAction->setRequestTemplate($requestTemplate);
            } else if($data['templateType'] === TriggerAction::ALERT){
                $name = $data['templates'];
                $alertTemplate = $alertTemplateRepository->findOneBy(["id" => $name]);
                $triggerAction->setAlertTemplate($alertTemplate);
            }

            if(isset($data['functionZone'])){
                $json = ['description' => $data['functionZone']];
                $triggerAction->setConfig($json);
            }

            if(isset($data['sensorTemperatureLimit'])){
                $json = ['limit' => $data['borneTemperature'], 'temperature' => $data['sensorTemperatureLimit']];
                $triggerAction->setConfig($json);
            }

            $entityManager->persist($triggerAction);
            try {
                $entityManager->flush();
            } /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Erreur lors de la création de l'actionneur"
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
                'redirect' => $this->generateUrl('trigger_action_index'),
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

            $json = $this->renderView('trigger_action/edit_content_modal.html.twig', [
                'triggerAction' => $triggerAction,
                'templateTypes' => TriggerAction::TEMPLATE_TYPES,
                "templateTemperatures" => TriggerAction::TEMPLATE_TEMPERATURE,
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
                $alertTemplate = $alertTemplateRepository->findOneBy(["id" => $data['templates']]);
                $triggerAction->setAlertTemplate($alertTemplate);
            } else {
                $requestTemplate = $requestTemplateRepository->findOneBy(["id" => $data['templates']]);
                $triggerAction->setRequestTemplate($requestTemplate);
            }

            if(isset($data['functionZone'])){
                $json = ['description' => $data['functionZone']];
                $triggerAction->setConfig($json);
            }

            if(isset($data['sensorTemperatureLimit'])){
                $json = ['limit' => $data['borneTemperature'], 'temperature' => $data['sensorTemperatureLimit']];
                $triggerAction->setConfig($json);
            }

            $entityManager->flush();

            return $this->json([
                'success' => true,
                'msg' => "L'actionneur a bien été modifié",
            ]);

        }
        throw new BadRequestHttpException();

    }

    /**
     * @Route("/get-templates", name="get_templates", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
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

        dump($type);

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

        dump($request);
        $type = "";
        if ($query->has('name')){
            $name = $query->get('name');
            $sensorWrapper = $sensorWrapperRepository->findOneBy(["name" => $name]);
            $type =  $sensorWrapper->getSensor() ? $sensorWrapper->getSensor()->getType() : "";
        } else if ($query->has('code')){
            $code = $query->get('code');
            $sensor = $sensorRepository->findOneBy(["code" => $code]);
            $type = $sensor->getType();
        }

        $html = "";
        if(!isset($sensorWrapper) && !isset($sensor)){
            return $this->json([
                "success" => false,
                "msg" => "Ce capteur n'existe pas",
            ]);
        } else if((isset($sensorWrapper) || isset($sensor)) && $type === Sensor::ACTION_TYPE){
            $html = $this->renderView('trigger_action/modalButton.html.twig');
        } else if((isset($sensorWrapper) || isset($sensor)) && $type === Sensor::TEMP_TYPE){
            $html = $this->renderView('trigger_action/modalTemperature.html.twig',[
                "templateTemperatures" => TriggerAction::TEMPLATE_TEMPERATURE,
            ]);
        }

        return $this->json($html);
    }

}
