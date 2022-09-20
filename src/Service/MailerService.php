<?php

namespace App\Service;


use App\Entity\MailerServer;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class MailerService
{

    private const TEST_EMAIL = 'test@wiilog.fr';

    #[Required]
    public Environment $templating;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public ?EntityManagerInterface $entityManager = null;

    /**
     * @param string|array $template string|['path' => string, 'options' => array]
     */
    public function sendMail(string|array $subject,
                             string|array $template,
                             array|string|Utilisateur $to): bool {
        if (isset($_SERVER['APP_NO_MAIL']) && $_SERVER['APP_NO_MAIL'] == 1) {
            return true;
        }

        $filteredRecipients = Stream::from(!is_array($to) ? [$to] : $to)
            ->filter(fn($user) => $user && (is_string($user) || $user->getStatus()));

        $contents = $this->createContents($filteredRecipients, $subject, $template);

        if (empty($contents)) {
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
        $redirectToTest = !isset($_SERVER['APP_ENV']) || $_SERVER['APP_ENV'] !== 'prod';

        $transport = (new \Swift_SmtpTransport($host, $port, $protocole))
            ->setUsername($user)
            ->setPassword($password);

        foreach ($contents as $content) {
            $message = (new \Swift_Message());
            $body = $content['content'];
            if ($redirectToTest) {
                $body .= $this->getRecipientsLabel($content['to']);
            }

            $message
                ->setFrom($senderMail, $senderName)
                ->setTo($redirectToTest ? [self::TEST_EMAIL] : $content['to'])
                ->setSubject($content['subject'])
                ->setBody($body)
                ->setContentType('text/html');

            $mailer = (new \Swift_Mailer($transport));
            $mailer->send($message);
        }

        return true;
    }

    /**
     * Create config to send email according to recipient language,
     * Grouping bug slug language
     * @return array result format [
            slugLanguage => [
                'to' => email[],
                'content' => string,
                'subject' => string,
            ]
     *  ]
     */
    private function createContents(Stream       $recipients,
                                    string|array $subject,
                                    string|array $template): array {
        $defaultSlugLanguage = $this->languageService->getDefaultSlug();
        $contents = [];

        foreach($recipients as $recipient) {
            if (is_string($recipient)) {
                $emails = [$recipient];
                $slug = $this->languageService->getReverseDefaultLanguage($defaultSlugLanguage);
            }
            else {
                /** @var Utilisateur $recipient */
                $emails = $recipient->getMainAndSecondaryEmails();
                $slug = $recipient->getLanguage()->getSlug();
            }

            foreach ($emails as $email) {
                // ignore if invalid email
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    if (!isset($contents[$slug])) {
                        if (!is_string($template)) {
                            $context = $template['context'];
                            $context['language'] = $slug;
                            if (isset($context['title']) && is_array($context['title'])) {
                                $context['title'] = $this->translationService->translateIn($slug, ...$context['title']);
                            }
                            $content = $this->templating->render($template['name'], $context);
                        }
                        else {
                            $content = $template;
                        }
                        $contents[$slug] = [
                            'to' => [$email],
                            'content' => $content,
                            'subject' => is_array($subject) ? $this->translationService->translateIn($slug, ...$subject) : $subject
                        ];
                    }
                    else if (!in_array($email, $contents[$slug]['to'])) {
                        $contents[$slug]['to'][] = $email;
                    }
                }
            }
        }

        return $contents;
    }

    private function getRecipientsLabel(array $recipients): string {
        return '<p>DESTINATAIRES : ' . Stream::from($recipients)->join(', ') . '</p>';

    }
}
