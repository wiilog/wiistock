<?php


namespace App\Service\IOT;

use App\Entity\IOT\AlertTemplate;
use App\Helper\PostHelper;
use App\Service\AttachmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use RuntimeException;

class AlertTemplateService
{
    /** @Required */
    public Environment $templating;

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public AttachmentService $attachmentService;

    public function updateAlertTemplate(Request $request, $entityManager, bool $creation) {
        $post = $request->request;

        $name = PostHelper::string($post, 'name');
        /** @var AlertTemplate $alertTemplate */
        $alertTemplate = PostHelper::entity($entityManager, $post, 'entity', AlertTemplate::class);

        $alertTemplate
            ->setName($name);

        $config = [];
        if($alertTemplate->getType() === AlertTemplate::MAIL) {

            $receivers = PostHelper::string($post, 'receivers');

            $counter = 0;
            foreach (explode(',', $receivers) as $receiver) {
                if(!filter_var($receiver, FILTER_VALIDATE_EMAIL)) {
                    $counter++;
                }
            }

            if($counter !== 0) {
                throw new RuntimeException("Une ou plusieurs adresses email ne sont pas valides dans votre saisie");
            }

            $hasFile = $request->files->has('image');
            $fileName = [];
            if($request->files->has('image')) {
                $file = $request->files->get('image');
                $name = $file->getClientOriginalName();
                $fileName = $this->attachmentService->saveFile($file);
            }

            $config = [
                'receivers' => PostHelper::string($post, 'receivers'),
                'subject' => PostHelper::string($post, 'subject'),
                'content' => PostHelper::string($post, 'content'),
                'image' => $hasFile ? $fileName[$name] : ''
            ];
        } else if($alertTemplate->getType() === AlertTemplate::SMS) {

            $config = [
                'receivers' => json_encode(explode(',', PostHelper::string($post, 'receivers'))),
                'content' => PostHelper::string($post, 'content'),
            ];
        } else if ($alertTemplate->getType() === AlertTemplate::PUSH) {

            $hasFile = $request->files->has('image');
            $fileName = [];
            $originalName = '';
            if($request->files->has('image')) {
                $file = $request->files->get('image');
                $originalName = $file->getClientOriginalName();
                $fileName = $this->attachmentService->saveFile($file);
            }

            $config = [
                'content' => PostHelper::string($post, 'content'),
                'image' => $hasFile ? $fileName[$originalName] : ''
            ];
        }
        $alertTemplate->setConfig($config);

        $entityManager->flush();

        $name = $alertTemplate->getName();
    }

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
