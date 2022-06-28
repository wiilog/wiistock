<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\AlertTemplate;
use App\Entity\Menu;
use App\Helper\PostHelper;

use App\Service\AttachmentService;
use App\Service\IOT\AlertTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * @Route("/modele-alerte")
 */
class AlertTemplateController extends AbstractController
{

    /**
     * @Route("/api", name="alert_template_api", options={"expose"=true}, methods={"POST|GET"}, condition="request.isXmlHttpRequest()")
     */
    public function api(Request $request,
                        AlertTemplateService $alertTemplateService): Response {
        $data = $alertTemplateService->getDataForDatatable($request->request);
        return $this->json($data);
    }

    /**
     * @Route("/supprimer", name="alert_template_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $alertTemplateRepository = $entityManager->getRepository(AlertTemplate::class);
            $alertTemplate = $alertTemplateRepository->find($data['id']);
            $name = $alertTemplate->getName();
            if (!$alertTemplate->getTriggers()->isEmpty()) {
                return $this->json([
                    'success' => false,
                    'msg' => "Le modèle d'alerte <strong>${name}</strong> est lié à un ou plusieurs actionneurs, veuillez les supprimer avant."
                ]);
            }
            foreach ($alertTemplate->getNotifications() as $notification) {
                $notification->setTemplate(null);
            }
            $entityManager->flush();
            $entityManager->remove($alertTemplate);

            $entityManager->flush();

            return $this->json([
                'success' => true,
                'msg' => "Le modèle d'alerte <strong>${name}</strong> a bien été supprimé"
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="alert_template_new", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        AttachmentService $attachmentService): Response
    {
        $post = $request->request;
        $type = PostHelper::string($post, 'type');
        $name = PostHelper::string($post, 'name');

        $alertTemplate = new AlertTemplate();
        $alertTemplate
            ->setName($name)
            ->setType($type);

        $config = [];
        if($type === AlertTemplate::MAIL) {
            $receivers = PostHelper::string($post, 'receivers');

            $counter = 0;
            foreach (explode(',', $receivers) as $receiver) {
                if(!filter_var($receiver, FILTER_VALIDATE_EMAIL)) {
                    $counter++;
                }
            }

            if($counter !== 0) {
                return $this->json([
                    'success' => false,
                    'msg' => $counter === 1
                        ? 'Une adresse email n\'est pas valide dans votre saisie'
                        : 'Plusieurs adresses email ne sont pas valides dans votre saisie'
                ]);
            }

            $hasFile = $request->files->has('image');
            $fileName = [];
            $originalName = '';
            if($request->files->has('image')) {
                $file = $request->files->get('image');
                $originalName = $file->getClientOriginalName();
                $fileName = $attachmentService->saveFile($file);
            }

            $config = [
                'receivers' => PostHelper::string($post, 'receivers'),
                'subject' => PostHelper::string($post, 'subject'),
                'content' => PostHelper::string($post, 'content'),
                'image' => $hasFile ? $fileName[$originalName] : ''
            ];
        } else if($type === AlertTemplate::SMS) {
            $config = [
                'receivers' => PostHelper::string($post, 'receivers'),
                'content' => PostHelper::string($post, 'content'),
            ];
        } else if ($type === AlertTemplate::PUSH) {

            $hasFile = $request->files->has('image');
            $fileName = [];
            $originalName = '';
            if($request->files->has('image')) {
                $file = $request->files->get('image');
                $originalName = $file->getClientOriginalName();
                $fileName = $attachmentService->saveFile($file);
            }

            $config = [
                'content' => PostHelper::string($post, 'content'),
                'image' => $hasFile ? $fileName[$originalName] : ''
            ];
        }

        $alertTemplate->setConfig($config);

        $entityManager->persist($alertTemplate);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'msg' => "Le modèle d'alerte <strong>${name}</strong> a bien été créé"
        ]);
    }

    /**
     * @Route("/modifier", name="alert_template_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::PARAM, Action::EDIT})
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         AttachmentService $attachmentService): Response {

        $post = $request->request;

        $name = PostHelper::string($post, 'name');
        /** @var AlertTemplate $alertTemplate */
        $alertTemplate = PostHelper::entity($entityManager, $post, 'id', AlertTemplate::class);

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
                return $this->json([
                    'success' => false,
                    'msg' => $counter === 1
                        ? 'Une adresse email n\'est pas valide dans votre saisie'
                        : 'Plusieurs adresses email ne sont pas valides dans votre saisie'
                ]);
            }

            $hasFile = $request->files->has('image');
            $fileName = [];
            if($request->files->has('image')) {
                $file = $request->files->get('image');
                $name = $file->getClientOriginalName();
                $fileName = $attachmentService->saveFile($file);
            }

            $config = [
                'receivers' => PostHelper::string($post, 'receivers'),
                'subject' => PostHelper::string($post, 'subject'),
                'content' => PostHelper::string($post, 'content'),
                'image' => $hasFile ? $fileName[$name] : ''
            ];
        } else if($alertTemplate->getType() === AlertTemplate::SMS) {

            $config = [
                'receivers' => PostHelper::string($post, 'receivers'),
                'content' => PostHelper::string($post, 'content'),
            ];
        } else if ($alertTemplate->getType() === AlertTemplate::PUSH) {

            $hasFile = $request->files->has('image');
            $fileName = [];
            $originalName = '';
            if($request->files->has('image')) {
                $file = $request->files->get('image');
                $originalName = $file->getClientOriginalName();
                $fileName = $attachmentService->saveFile($file);
            }

            $config = [
                'content' => PostHelper::string($post, 'content'),
                'image' => $hasFile ? $fileName[$originalName] : ''
            ];
        }

        $alertTemplate->setConfig($config);

        $entityManager->flush();

        $name = $alertTemplate->getName();

        return $this->json([
            'success' => true,
            'msg' => "Le modèle d'alerte <strong>${name}</strong> a bien été modifié"
        ]);
    }


}

