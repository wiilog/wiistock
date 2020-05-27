<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 07/06/2019
 * Time: 16:23
 */

namespace App\Command;


use App\Entity\Litige;

use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment as Twig_Environment;

class MailsLitigesComand extends Command
{

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $entityManager;

    public function __construct(LoggerInterface $logger,
                                EntityManagerInterface $entityManager,
                                MailerService $mailerService,
                                Twig_Environment $templating)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('app:mails-litiges');

        $this->setDescription('envoi de mails aux acheteurs pour les litiges non soldés');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $litigeRepository = $this->entityManager->getRepository(Litige::class);

        /** @var Litige[] $litiges */
        $litiges = $litigeRepository->findByStatutSendNotifToBuyer();

        $litigesByAcheteur = [];
        foreach ($litiges as $litige) {
            /** @var  $acheteursEmail */
            $acheteursEmail = $litigeRepository->getAcheteursArrivageByLitigeId($litige->getId());
            foreach ($acheteursEmail as $email) {
                $litigesByAcheteur[$email][] = $litige;
            }
        }

        $listEmails = '';

        foreach ($litigesByAcheteur as $email => $litiges) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Récapitulatif de vos litiges',
                $this->templating->render('mails/mailLitigesArrivage.html.twig', [
                    'litiges' => $litiges,
                    'title' => 'Récapitulatif de vos litiges',
                    'urlSuffix' => 'arrivage'
                ]),
                $email
            );
            $listEmails .= $email . ', ';
        }

        $nbMails = count($litigesByAcheteur);

        $output->writeln($nbMails . ' mails ont été envoyés');
        $this->logger->info('ENVOI DE ' . $nbMails . ' MAILS RECAP LITIGES : ' . $listEmails);
    }
}
