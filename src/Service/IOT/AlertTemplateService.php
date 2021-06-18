<?php


namespace App\Service\IOT;

use App\Entity\IOT\AlertTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class AlertTemplateService
{
    /** @Required */
    public Environment $templating;

    /** @Required */
    public EntityManagerInterface $entityManager;

    public function getDataForDatatable($params = null)
    {
        $alertTemplateRepository = $this->entityManager->getRepository(AlertTemplate::class);
        $queryResult = $alertTemplateRepository->findByParams($params);

        $alertTemplates = $queryResult['data'];

        $rows = [];
        foreach ($alertTemplates as $alertTemplate) {
            $rows[] = $this->dataRowAlertTemplate($alertTemplate);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowAlertTemplate(AlertTemplate $alertTemplate) {
        return [
            'id' => $alertTemplate->getId(),
            'name' => $alertTemplate->getName(),
            'type' => AlertTemplate::TEMPLATE_TYPES[$alertTemplate->getType()],
            'actions' => $this->templating->render('alert_template/actions.html.twig', [
                'alert_template' => $alertTemplate,
            ]),
        ];
    }
}
