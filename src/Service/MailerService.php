<?php

namespace App\Service;


use App\Entity\MailerServer;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class MailerService
{
    /** @Required */
    public ?EntityManagerInterface $entityManager = null;

    public function sendMail(string $subject,
                             string $content,
                             array|Utilisateur|string $to,
                             array $attachments = []): bool {
        if (isset($_SERVER['APP_NO_MAIL']) && $_SERVER['APP_NO_MAIL'] == 1) {
            return true;
        }

        if (!is_array($to)) {
            $to = [$to];
        }

        $to = Stream::from($to)
            ->filter(fn($user) => $user && (is_string($user) || $user->getStatus()))
            ->flatMap(fn($user) => is_string($user) ? [$user] : $user->getMainAndSecondaryEmails())
            ->unique()
            ->filter(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->toArray();

        if (empty($to)) {
            return false;
        }

        $mailerServerRepository = $this->entityManager->getRepository(MailerServer::class);
        $mailerServer = $mailerServerRepository->findOneBy([]);

        if ($mailerServer) {
            $user = $mailerServer->getUser() ?? '';
            $password = $mailerServer->getPassword() ?? '';
            $host = $mailerServer->getSmtp() ?? '';
            $port = $mailerServer->getPort() ?? '';
            $protocole = $mailerServer->getProtocol() ?? '';
            $senderName = $mailerServer->getSenderName() ?? '';
            $senderMail = $mailerServer->getSenderMail() ?? '';
        } else {
            return false;
        }

        if (empty($user) || empty($password) || empty($host) || empty($port)) {
            return false;
        }

        //protection dev
        if (!isset($_SERVER['APP_ENV']) || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] !== 'prod')) {
            $content .= '<p>DESTINATAIRES : ';
            if (!is_array($to)) {
                $content .= $to;
            } else {
                foreach ($to as $dest) {
                    $content .= $dest . ', ';
                }
            }
            $content .= '</p>';
            $to = ['test@wiilog.fr'];
        }

        $transport = (new \Swift_SmtpTransport($host, $port, $protocole))
            ->setUsername($user)
            ->setPassword($password);

        $message = (new \Swift_Message());

        $message
            ->setFrom($senderMail, $senderName)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($content)
            ->setContentType('text/html');

        foreach ($attachments as $attachment) {
            $message->attach(\Swift_Attachment::fromPath($attachment));
        }

        $mailer = (new \Swift_Mailer($transport));
        $mailer->send($message);
        return true;
    }
}
