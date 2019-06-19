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

    /**
     * @var \Twig_Environment
     */
    private $templating;


    public function __construct(MailerServerRepository $mailerServerRepository, \Twig_Environment $templating)
    {
        $this->mailerServerRepository = $mailerServerRepository;
        $this->templating = $templating;
    }

    public function sendMail($subject, $content, $to)
    {
        $mailerServer = $this->mailerServerRepository->findOneMailerServer();
        /** @var MailerServer $mailerServer */
        if ($mailerServer) {
            $from = $mailerServer->getUser() ? $mailerServer->getUser() : '';
            $password = $mailerServer->getPassword() ? $mailerServer->getPassword() : '';
            $host = $mailerServer->getSmtp() ? $mailerServer->getSmtp() : '';
            $port = $mailerServer->getPort() ?  $mailerServer->getPort() : '';
            $protocole = $mailerServer->getProtocol() ? $mailerServer->getProtocol() : '';
        } else {
            return false;
        }

        if (empty($from) || empty($password) || empty($host) || empty($port) || empty($protocole)) {
            return false;
        }

        //protection dev
        if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] == 'dev') {
            $to = ['matteo.hevin@wiilog.fr', 'cecile.gazaniol@wiilog.fr'];
        }

        $transport = (new \Swift_SmtpTransport($host, $port, $protocole))
            ->setUsername($from)
            ->setPassword($password);

        $message = (new \Swift_Message());

        //        $image = $message->embed(\Swift_Image::fromPath('img/Logo-FollowGTpetit.png'));

        $message
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($content)
            ->setContentType('text/html');
        $mailer = (new \Swift_Mailer($transport));
        $mailer->send($message);
    }
}
