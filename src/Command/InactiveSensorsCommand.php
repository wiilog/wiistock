<?php

namespace App\Command;

use App\Entity\IOT\SensorWrapper;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig_Environment;


class InactiveSensorsCommand extends Command {

    protected static $defaultName = "app:iot:check-sensors-inactivity";

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public MailerService $mailerService;

    /** @Required */
    public Twig_Environment $templating;

    protected function configure() {
        $this->setDescription("Checks inactive pairings");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $wrapperRepository = $this->entityManager->getRepository(SensorWrapper::class);
        $wrappers = $wrapperRepository->findBy([
            'deleted' => false
        ]);
        $nowMinus48Hours = new \DateTime();
        $nowMinus48Hours->modify('-2 day');
        /**
         * @var SensorWrapper $wrapper
         */
        foreach ($wrappers as $wrapper) {
            $sensor = $wrapper->getSensor();
            $lastMessage = $sensor->getLastMessage();

            if ($lastMessage && $lastMessage->getDate() < $nowMinus48Hours && $wrapper->getManager()) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // Aucune donnée capteur détectée',
                    $this->templating->render('mails/contents/iot/mailSensorInactive.html.twig', [
                        'sensorCode' => $sensor->getCode(),
                        'sensorName' => $wrapper->getName(),
                    ]),
                    $wrapper->getManager()
                );
            }
        }
        return 0;
    }
}
