<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 24/04/2019
 * Time: 10:00
 */

namespace App\Service;


use App\Entity\MailerServer;
use App\Repository\MailerServerRepository;

class MailerService
{
    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;


    public function __construct(MailerServerRepository $mailerServerRepository)
    {
        $this->mailerServerRepository = $mailerServerRepository;
    }

    public function sendMail($subject, $message, $to)
    {
        $mailerServer = $this->mailerServerRepository->getOneMailerServer(); /** @var MailerServer $mailerServer */

        $from = $mailerServer->getUser();
        $password = $mailerServer->getPassword();
        $host = $mailerServer->getSmtp();
        $port = $mailerServer->getPort();
        $protocole = $mailerServer->getProtocol();

        if (empty($from) || empty($password) || empty($host) || empty($port) || empty($protocole)) {
            return false;
        }

        //TODO CG
        $to = 'cecile.gazaniol@wiilog.fr';

        $transport = (new \Swift_SmtpTransport($host, $port, $protocole))
                ->setUsername($from)
                ->setPassword($password);

        $message = (new \Swift_Message())
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($message);

        $mailer = (new \Swift_Mailer($transport));
        $mailer->send($message);
    }
}