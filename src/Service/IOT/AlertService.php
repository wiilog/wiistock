<?php


namespace App\Service\IOT;


use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\Notification;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use App\Service\MailerService;
use App\Service\NotificationService;
use App\Service\VariableService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Ovh\Api;
use Symfony\Component\HttpKernel\KernelInterface;


class AlertService
{

    /** @Required */
    public VariableService $variableService;

    /** @Required */
    public MailerService $mailerService;

    /** @Required  */
    public NotificationService $notificationService;

    /** @Required */
    public KernelInterface $kernel;

    public function trigger(AlertTemplate $template, SensorMessage $message, EntityManagerInterface $entityManager)
    {
        $config = $template->getConfig();

        $sensor = $message->getSensor();
        $sensorWrapper = $message->getSensor()->getAvailableSensorWrapper();

        $values = [
            VariableService::SENSOR_NAME => $sensorWrapper->getName(),
            VariableService::SENSOR_CODE => $sensor->getCode(),
            VariableService::ALERT_DATE => FormatHelper::datetime($message->getDate()),
            VariableService::DATA => FormatHelper::type($sensor->getType()) === Sensor::TEMPERATURE ? "{$message->getContent()}°C" : null,
        ];

        $content = $this->variableService->replaceVariables($config["content"], $values);
        if ($template->getType() == AlertTemplate::SMS) {
            $conn = new Api($_SERVER["APPLICATION_KEY"], $_SERVER["APPLICATION_SECRET"], "ovh-eu", $_SERVER["CONSUMER_KEY"]);
            $smsServices = $conn->get("/sms");

            $result = $conn->post("/sms/{$smsServices[0]}/jobs", (object)[
                "charset" => "UTF-8",
                "class" => "phoneDisplay",
                "coding" => "7bit",
                "noStopClause" => false,
                "priority" => "high",
                "receivers" => json_decode($config["receivers"]),
                "message" => $content,
                "senderForResponse" => true,
                "validityPeriod" => 2880
            ]);

            if (empty($result["invalidReceivers"])) {
                return true;
            }
        } else if ($template->getType() == AlertTemplate::MAIL) {
            if (isset($config['image']) && !empty($config['image'])) {
                $src = $this->kernel->getProjectDir() . '/public/uploads/attachements/' . $config['image'];
                $type = pathinfo($src, PATHINFO_EXTENSION);
                $imageContent = base64_encode(file_get_contents($src));

                if($type == "svg") {
                    $type = "svg+xml";
                }

                $data = "data:image/$type;base64,$imageContent";
                $content = '<img height="50px" width="50px" src="' . $data . '"><br>' . $content;
            }
            return $this->mailerService->sendMail($config["subject"], $content, explode(",", $config["receivers"]));
        } else if ($template->getType() == AlertTemplate::PUSH) {
            $src = null;
            if (isset($config['image']) && !empty($config['image'])) {
                $src = $_SERVER['APP_URL'] . '/uploads/attachements/' . $config['image'];
            }
            $emitted = new Notification();
            $emitted
                ->setTemplate($template)
                ->setContent($content)
                ->setSource($sensorWrapper->getName())
                ->setTriggered(new DateTime());
            $usersRepository = $entityManager->getRepository(Utilisateur::class);
            $users = $usersRepository->findBy([
                'status' => true
            ]);
            $entityManager->persist($emitted);
            foreach ($users as $user) {
                $user
                    ->addUnreadNotification($emitted);
            }
            $entityManager->flush();
            $this->notificationService->send('notifications', 'Alerte', $content, [
                'image' => $src
            ]);
            $this->notificationService->send('notifications-web', 'Alerte', $content, [
                'title' => 'Alerte',
                'content' => $content,
                'image' => $src
            ], true);
            return true;
        }
    }
}
