<?php

namespace App\Command;

use App\Entity\IOT\SensorWrapper;
use App\Service\MailerService;
use App\Service\TranslationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

#[AsCommand(
    name: InactiveSensorsCommand::COMMAND_NAME,
    description: 'This command checks inactive pairings.'
)]
class InactiveSensorsCommand extends Command {
    public const COMMAND_NAME = "app:iot:check-sensors-inactivity";

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public TranslationService $translationService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $wrapperRepository = $this->entityManager->getRepository(SensorWrapper::class);
        $wrappers = $wrapperRepository->findInactives();

        foreach ($wrappers as $wrapper) {
            if (!$wrapper->isInactivityAlertSent() && $wrapper->getManager() && $wrapper->getInactivityAlertThreshold()) {
                $sensor = $wrapper->getSensor();
                $this->mailerService->sendMail(
                    $this->entityManager,
                    $this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . 'Aucune donnée capteur détectée',
                    $this->templating->render('mails/contents/iot/mailSensorInactive.html.twig', [
                        'sensorCode' => $sensor->getCode(),
                        'sensorName' => $wrapper->getName(),
                        'inactivityAlertThreshold' => $wrapper->getInactivityAlertThreshold(),
                    ]),
                    $wrapper->getManager()
                );
                $wrapper->setInactivityAlertSent(true);
            }
            $this->entityManager->flush();
        }
        return 0;
    }
}
