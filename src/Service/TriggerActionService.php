<?php


namespace App\Service;

use App\Entity\IOT\TriggerAction;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class TriggerActionService
{
    /** @Required */
    public EntityManagerInterface $em;

    /**
     * @var Twig_Environment
     */
    private $templating;

    public function __construct(Twig_Environment $templating) {
        $this->templating = $templating;
    }

    public function getDataForDatatable($params = null)
    {

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

    public function dataRowTriggerAction(TriggerAction $triggerAction) {
        return [
            'id' => $triggerAction->getId(),
            'sensorWrapper' => $triggerAction->getSensorWrapper() ? $triggerAction->getSensorWrapper()->getName() : "",
            'template' => ($triggerAction->getAlertTemplate() ? $triggerAction->getAlertTemplate()->getName() : ($triggerAction->getRequestTemplate() ? $triggerAction->getRequestTemplate()->getName() : " ")),
            'actions' => $this->templating->render('trigger_action/actions.html.twig', [
                'triggerId' => $triggerAction->getId(),
            ]),
        ];
    }

}
