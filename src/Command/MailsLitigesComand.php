<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 07/06/2019
 * Time: 16:23
 */

namespace App\Command;


use App\Entity\Litige;

use App\Repository\ArrivageRepository;
use App\Repository\LitigeRepository;
use App\Repository\ParamClientRepository;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
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

    /**
     * @var ArrivageRepository
     */
    private $arrivageRepository;


    public function __construct(ArrivageRepository $arrivageRepository, ParamClientRepository $paramClientRepository, LoggerInterface $logger, LitigeRepository $litigeRepository, MailerService $mailerService, \Twig_Environment $templating)
    {
        parent::__construct();
        $this->paramClientRepository = $paramClientRepository;
        $this->litigeRepository = $litigeRepository;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
        $this->logger = $logger;
        $this->arrivageRepository = $arrivageRepository;
    }

    protected function configure()
    {
        $this->setName('app:mails-litiges');

        $this->setDescription('envoi de mails aux acheteurs pour les litiges non soldés');

//    $this->addArgument('arg', InputArgument::OPTIONAL, 'question');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $litiges = $this->litigeRepository->findByStatutLabel(Litige::ATTENTE_ACHETEUR);
        $count = 0;

        foreach ($litiges as $litige) {

            $arrivage = $this->arrivageRepository->findOneByLitige($litige);


            foreach ($arrivage->getAcheteurs() as $acheteur) {
                $title = 'Récapitulatif de vos litiges';

                $this->mailerService->sendMail(
                    'FOLLOW GT // Litige sur arrivage',
                    $this->templating->render('mails/mailLitige.html.twig', [
                        'litige' => $litige,
                        'title' => $title,
                        'urlSuffix' => 'arrivage'
                    ]),
                    $acheteur->getEmail()
                );
                $count++;
            }
        }

        $output->writeln($count . ' mails ont été envoyés.');
    }

}