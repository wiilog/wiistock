<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 07/06/2019
 * Time: 16:23
 */

namespace App\Command;


use App\Entity\ParamClient;
use App\Entity\Statut;
use App\Repository\LitigeRepository;
use App\Repository\ParamClientRepository;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
//use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MailsLitigesComand extends Command
{
    /**
     * @var LitigeRepository
     */
    private $litigeRepository;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ParamClientRepository
     */
    private $paramClientRepository;


    public function __construct(ParamClientRepository $paramClientRepository, LoggerInterface $logger, LitigeRepository $litigeRepository, MailerService $mailerService, \Twig_Environment $templating)
    {
        parent::__construct();
        $this->paramClientRepository = $paramClientRepository;
        $this->litigeRepository = $litigeRepository;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('app:mails-litiges');

        $this->setDescription('envoi de mails aux acheteurs pour les litiges non soldés');

//    $this->addArgument('arg', InputArgument::OPTIONAL, 'question');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $litiges = $this->litigeRepository->findByArrivageStatutLabel(Statut::ATTENTE_ACHETEUR);

        $litigesByAcheteur = [];
        foreach ($litiges as $litige) {
            $arrivage = $litige->getArrivage();
            $acheteurs = $arrivage->getAcheteurs();

            foreach ($acheteurs as $acheteur) {
                $litigesByAcheteur[$acheteur->getEmail()][] = $litige;
            }
        }

        $listEmails = '';

        foreach ($litigesByAcheteur as $email => $litiges) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Récapitulatif de vos litiges',
                $this->templating->render('mails/mailLitiges.html.twig', [
                    'litiges' => $litiges,
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