<?php


namespace App\Service\IOT;


use App\Entity\IOT\AlertTemplate;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Helper\FormatHelper;
use App\Service\MailerService;
use App\Service\VariableService;
use Ovh\Api;

class AlertService
{

    /** @Required */
    public VariableService $variableService;

    /** @Required */
    public MailerService $mailerService;

    public function trigger(AlertTemplate $template, SensorMessage $message)
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
                "receivers" => $config["recipients"],
                "message" => $content,
                "senderForResponse" => true,
                "validityPeriod" => 2880
            ]);

            if (empty($result["invalidReceivers"])) {
                return true;
            }
        } else if ($template->getType() == AlertTemplate::MAIL) {
            return $this->mailerService->sendMail($config["subject"], $content, $config["recipients"]);
        }
    }
}
