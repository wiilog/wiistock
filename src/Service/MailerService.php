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
use App\Repository\ParamClientRepository;

class MailerService
{
    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

	/**
	 * @var ParamClientRepository
	 */
    private $paramClientRepository;

    /**
     * @var \Twig_Environment
     */
    private $templating;


    public function __construct(ParamClientRepository $paramClientRepository, MailerServerRepository $mailerServerRepository, \Twig_Environment $templating)
    {
        $this->mailerServerRepository = $mailerServerRepository;
        $this->paramClientRepository = $paramClientRepository;
        $this->templating = $templating;
    }

    public function sendMail($subject, $content, $to, $title = '', $url = '')
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

            $content .= '<p>DESTINATAIRES : ';
            if (!is_array($to)) {
                $content .= $to;
            } else {
                foreach($to as $dest) {
                    $content .= $dest . ', ';
                }
            }
            $content .= '</p>';
            $to = ['clara.tuco@wiilog.fr', 'cecile.gazaniol@wiilog.fr', 'matteo.hevin@wiilog.fr'];
        }

        $transport = (new \Swift_SmtpTransport($host, $port, $protocole))
            ->setUsername($from)
            ->setPassword($password);

        $message = (new \Swift_Message());

        //        $image = $message->embed(\Swift_Image::fromPath('img/Logo-FollowGTpetit.png'));

		$contentWithTemplate = $this->templating->render('mails/template.html.twig', [
			'title' => $title,
			'content' => $content,
			'url' => $this->paramClientRepository->findOne()->getDomainName() . $url
		]);

        $message
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($contentWithTemplate)
            ->setContentType('text/html');
        $mailer = (new \Swift_Mailer($transport));
        $mailer->send($message);
    }
}
