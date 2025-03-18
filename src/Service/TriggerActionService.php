<?php


namespace App\Service;

use App\Entity\IOT\SensorWrapper;
use App\Entity\IOT\TriggerAction;
use App\Entity\IOT\AlertTemplate;
use App\Entity\RequestTemplate\RequestTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class TriggerActionService
{
    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public FormatService $formatService;

    /**
     * @var Twig_Environment
     */
    private $templating;

    public function __construct(Twig_Environment $templating) {
        $this->templating = $templating;
    }

    public function getDataForDatatable($params = null): array {

        $queryResult = $this->em->getRepository(TriggerAction::class)
            ->findByParamsAndFilters($params);

        $requests = $queryResult['data'];

        $rows = [];
        foreach ($requests as $request) {
            $rows[] = $this->dataRowTriggerAction($request);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowTriggerAction(TriggerAction $triggerAction): array {
        return [
            'id' => $triggerAction->getId(),
            'sensorWrapper' => $triggerAction->getSensorWrapper() ? $triggerAction->getSensorWrapper()->getName() : "",
            'template' => ($triggerAction->getAlertTemplate() ? $triggerAction->getAlertTemplate()->getName() : ($triggerAction->getRequestTemplate() ? $triggerAction->getRequestTemplate()->getName() : " ")),
            'actions' => $this->templating->render('trigger_action/actions.html.twig', [
                'triggerId' => $triggerAction->getId(),
            ]),
            'templateType' => $this->formatService->triggerActionTemplateType($triggerAction),
            'threshold' => $this->formatService->triggerActionThreshold($triggerAction),
            'lastTrigger' => $this->formatService->datetime($triggerAction->getLastTrigger()),
        ];
    }

    public function createTriggerActionByTemplateType(EntityManagerInterface $entityManager,
                                                      SensorWrapper $sensorWrapper,
                                                      string $triggerActionType,
                                                      ?string $requestTemplateId,
                                                      array $config): TriggerAction {
        $triggerAction = new TriggerAction();

        $requestTemplateRepository = $entityManager->getRepository(RequestTemplate::class);
        $alertTemplateRepository = $entityManager->getRepository(AlertTemplate::class);

        if ($triggerActionType === TriggerAction::REQUEST) {
            $requestTemplate = $requestTemplateRepository->findOneBy(['id' => $requestTemplateId]);
            $triggerAction->setRequestTemplate($requestTemplate);
        } elseif ($triggerActionType === TriggerAction::ALERT) {
            $alertTemplate = $alertTemplateRepository->findOneBy(['id' => $requestTemplateId]);
            $triggerAction->setAlertTemplate($alertTemplate);
        }

        $triggerAction
            ->setConfig($config)
            ->setSensorWrapper($sensorWrapper);

        return $triggerAction;
    }

}
