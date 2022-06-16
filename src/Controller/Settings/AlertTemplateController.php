<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\AlertTemplate;
use App\Entity\Menu;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage")
 */
class AlertTemplateController extends AbstractController
{

    /**
     * @Route("/modele-alerte/header/{template}", name="settings_alert_template_header", options={"expose"=true})
     */
    public function alertTemplateHeader(Request        $request,
                                        ?AlertTemplate $template = null): Response {

        $edit = $request->query->getBoolean("edit");
        $category = $template?->getType();

        if ($edit) {
            $name = $template?->getName();
            $data = [[
                "type" => "hidden",
                "name" => "alertTemplate",
                "class" => "data",
                "value" => 1,
            ]];

            if ($template) {
                $data[] = [
                    "type" => "hidden",
                    "class" => "data",
                    "name" => "entity",
                    "value" => $template->getId(),
                ];
                $data[] = [
                    "class" => "col-md-4",
                    "label" => "Nom du modèle*",
                    "value" => "<input name='name' class='data form-control' value='$name' required>",
                ];

                $data[] = [
                    "class" => "col-md-8",
                    "label" => "Type d'alerte",
                    "value" => AlertTemplate::TEMPLATE_TYPES[$template->getType()]." <input type='hidden' name='type' class='data form-control' value='{$template->getType()}' >",
                ];
            }
            else {
                $data[] = [
                    "label" => '',
                    "class" => "col-md-12",
                    "value" => $this->renderView('settings/notifications/new.html.twig', [
                        'templateTypes' => AlertTemplate::TEMPLATE_TYPES
                    ]),
                ];
            }

            if ($category === AlertTemplate::PUSH) {
                $content = $template?->getConfig()['content'] ?? '';
                $image = $template?->getConfig()['image'] ?? '';

                $data[] = [
                    "class" => "col-md-4",
                    "label" => "Texte de notification*",
                    "value" => "<textarea class='data form-control' name='content' required style='min-height: 100px;'>$content</textarea>",
                ];

                $data[] = [
                    "label" => 'Image de notification',
                    "value" => $this->renderView('image_input.html.twig', [
                        'name' => "image",
                        'label' => '',
                        'required' => false,
                        'image' => $image ? 'uploads/attachements/' . $image : '',
                        'options' => []
                    ]),
                ];


            }
            else if ($category === AlertTemplate::MAIL) {
                $subject = $template?->getConfig()['subject'];
                $rawContent = $template?->getConfig()['content'];
                $image = $template?->getConfig()['image'] ?? '';
                $users = "";
                if($template && isset($template->getConfig()['receivers'])) {
                    $emails = explode(',', $template->getConfig()['receivers']);
                    foreach ($emails as $email) {
                        $users .= "<option value='$email' selected>$email</option>";
                    }
                }

                $data[] = [
                    "class" => "col-md-4",
                    "label" => "Destinataire*",
                    "value" => "<select data-s2 data-parent='body' data-editable multiple name='receivers' class='data form-control' required>$users</select>",
                ];

                $data[] = [
                    "label" => "Objet*",
                    "class" => "col-md-4",
                    "value" => "<input name='subject' class='data form-control' required value='$subject'>",
                ];

                $data[] = [
                    "label" => 'Image de début de mail',
                    "class" => "col-md-4",
                    "value" => $this->renderView('image_input.html.twig', [
                        'name' => "image",
                        'label' => "",
                        'required' => false,
                        'image' => $image ? 'uploads/attachements/' . $image : '',
                        'options' => []
                    ]),
                ];

                $data[] = [
                    "class" => "col-md-8",
                    "label" => "Corps de l'email*",
                    "value" => "<div class='editor-container data' name='content' data-wysiwyg>$rawContent</div>",
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "value" => $this->renderView('variables_dictionary.html.twig', [
                        'dictionary' => 'ALERT_DICTIONARY'
                    ]),
                ];

            }
            else if ($category === AlertTemplate::SMS) {
                $rawContent = $template?->getConfig()['content'];
                $users = "";
                if($template && isset($template->getConfig()['receivers'])) {
                    $emails = explode(',', $template->getConfig()['receivers']);
                    foreach ($emails as $email) {
                        $users .= "<option value='$email' selected>$email</option>";
                    }
                }

                $data[] = [
                    "noFullWidth" => true,
                    "class" => "col-md-12",
                    "label" => "Destinataires*",
                    "value" => $this->renderView('settings/notifications/alerts_phone.html.twig', [
                        'phoneNumbers' => json_decode($template->getConfig()['receivers']),
                        'edit' => true,
                    ]),
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "label" => "SMS*",
                    "value" => "<textarea class='data form-control' name='content' required style='min-height: 100px;'>$rawContent</textarea>",
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "value" => $this->renderView('variables_dictionary.html.twig', [
                        'dictionary' => 'ALERT_DICTIONARY'
                    ]),
                ];
            }
        } else if ($template) {
            $data = [];
            if ($category === AlertTemplate::PUSH) {
                $content = $template?->getConfig()['content'] ?? '';
                $image = $template?->getConfig()['image'] ?? '';
                $data[] = [
                    "label" => "Type d'alerte",
                    "value" => AlertTemplate::TEMPLATE_TYPES[AlertTemplate::PUSH],
                ];

                $data[] = [
                    "label" => "Texte de notification",
                    "value" => $content,
                ];
                if ($image) {
                    $src = $_SERVER['APP_URL'] . '/uploads/attachements/' . $image;
                    $data[] = [
                        "label" => "Image de notification",
                        "value" => "<img src='$src' alt='' width='85px' height='75px' style='margin: auto'>"
                    ];
                }
            } else if ($category === AlertTemplate::SMS) {
                $content = $template?->getConfig()['content'] ?? '';
                $data[] = [
                    "class" => "col-md-12",
                    "noFullWidth" => true,
                    "label" => "Type d'alerte",
                    "value" => AlertTemplate::TEMPLATE_TYPES[AlertTemplate::SMS],
                ];

                $data[] = [
                    "noFullWidth" => true,
                    "class" => "col-md-4",
                    "label" => "Destinataires",
                    "value" => $this->renderView('settings/notifications/alerts_phone.html.twig', [
                        'phoneNumbers' => json_decode($template->getConfig()['receivers']),
                        'edit' => false,
                    ]),
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "label" => "SMS",
                    "value" => $content,
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "value" => $this->renderView('variables_dictionary.html.twig', [
                        'dictionary' => 'ALERT_DICTIONARY'
                    ]),
                ];
            } else if ($category === AlertTemplate::MAIL) {
                $content = $template?->getConfig()['content'] ?? '';
                $image = $template?->getConfig()['image'] ?? '';
                $data[] = [
                    "class" => "col-md-12",
                    "noFullWidth" => true,
                    "label" => "Type d'alerte",
                    "value" => AlertTemplate::TEMPLATE_TYPES[AlertTemplate::MAIL],
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "label" => "Destinataires",
                    "value" => $template->getConfig()['receivers'],
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "label" => "Objet",
                    "value" => $template->getConfig()['subject'],
                ];

                if ($image) {
                    $src = $_SERVER['APP_URL'] . '/uploads/attachements/' . $image;
                    $data[] = [
                        "label" => "Image de début de mail",
                        "value" => "<img src='$src' alt='' width='85px' height='75px' style='margin: auto'>"
                    ];
                }

                $data[] = [
                    "class" => "col-md-8",
                    "label" => "Corps de l'email",
                    "value" => strip_tags($content),
                ];

                $data[] = [
                    "class" => "col-md-4",
                    "value" => $this->renderView('variables_dictionary.html.twig', [
                        'dictionary' => 'ALERT_DICTIONARY'
                    ]),
                ];
            }
        }

        return $this->json([
            "success" => true,
            "data" => $data ?? [],
        ]);
    }

    /**
     * @Route("/verification/{alertTemplate}", name="settings_alert_template_check_delete", methods={"GET"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public
    function checkAlertTemplateCanBeDeleted(AlertTemplate $alertTemplate): Response
    {
        if ($alertTemplate->getTriggers()->isEmpty()) {
            $success = true;
            $message = "Vous êtes sur le point de supprimer le modèle de demande <strong>{$alertTemplate->getName()}</strong>";
        }
        else {
            $success = false;
            $message = 'Ce modèle de demande est utilisé par un ou plusieurs actionneurs ou, vous ne pouvez pas le supprimer';
        }

        return $this->json([
            'success' => $success,
            'message' => $message
        ]);
    }

    /**
     * @Route("/modele-alerte/supprimer/{alertTemplate}", name="settings_alert_template_delete", options={"expose"=true})
     * @HasPermission({Menu::PARAM, Action::DELETE})
     */
    public function deleteAlertTemplate(EntityManagerInterface $manager,
                                   AlertTemplate        $alertTemplate): JsonResponse
    {

        if ($alertTemplate->getTriggers()->isEmpty()) {
            $success = true;
            $message = 'Le modèle de demande a bien été supprimé';
            foreach ($alertTemplate->getNotifications() as $notification) {
                $notification->setTemplate(null);
            }

            $manager->remove($alertTemplate);
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

    /**
     * @Route("/toggle-template", name="alert_template_toggle_template", options={"expose"=true}, methods={"GET"})
     */
    public function toggleTemplate(Request $request) {
        $query = $request->query;
        $type = $query->has('type') ? $query->get('type') : '';

        $html = '';
        if($type === AlertTemplate::SMS) {
            $html = $this->renderView('settings/notifications/templates/sms.html.twig');
        } else if ($type === AlertTemplate::MAIL) {
            $html = $this->renderView('settings/notifications/templates/mail.html.twig');
        } else if ($type === AlertTemplate::PUSH) {
            $html = $this->renderView('settings/notifications/templates/push.html.twig');
        }

        return $this->json($html);
    }
}
