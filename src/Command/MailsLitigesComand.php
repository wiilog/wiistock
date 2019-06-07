<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 07/06/2019
 * Time: 16:23
 */

namespace App\Command;


use App\Repository\LitigeRepository;
use App\Service\MailerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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


  public function __construct(LitigeRepository $litigeRepository, MailerService $mailerService, \Twig_Environment $templating)
  {
    parent::__construct();
    $this->litigeRepository = $litigeRepository;
    $this->mailerService = $mailerService;
    $this->templating = $templating;
  }

  protected function configure()
  {
    $this->setName('app:mails-litiges');

    $this->setDescription('envoi de mails aux acheteurs avec les infos sur leurs arrivages avec litiges');

//    $this->addArgument('arg', InputArgument::OPTIONAL, 'question');
  }

  public function execute(InputInterface $input, OutputInterface $output)
  {
    $litiges = $this->litigeRepository->findAll();


    $litigesByAcheteur = [];
    foreach($litiges as $litige) {
      $arrivage = $litige->getArrivage();
      $acheteurs = $arrivage->getAcheteurs();

      foreach ($acheteurs as $acheteur) {
        $litigesByAcheteur[$acheteur->getEmail()][] = $litige;
      }
    }

    foreach ($litigesByAcheteur as $email => $litiges) {
      $this->mailerService->sendMail(
        'FOLLOW GT // Récapitulatif de vos litiges',
        $this->templating->render('mails/mailLitiges.html.twig', ['litiges' => $litiges]),
        $email
      );
    }

    $output->writeln(count($litigesByAcheteur) . ' mails ont été envoyés');
    //TODO enregistrer dans fichier de log

  }

}