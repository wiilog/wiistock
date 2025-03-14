<?php

namespace App\Service;


use App\Entity\Attachment;
use App\Entity\Setting;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use Traversable;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class MailerService
{

    private const TEST_EMAIL = 'recette@wiilog.fr';
    public const PORT_SSL = 587;
    public const NO_MAIL_DOMAINS = [
        "nomail.fr",
        "nomail.com",
    ];

    public const OBJECT_SERPARATOR = ' // ';

    // visit https://i.stack.imgur.com/dmsiJ.png for the details
    public const EMAIL_SLICING_REGEX = "/^(?<Email>.*@)?(?<Protocol>\w+:\/\/)?(?<SubDomain>(?:[\w-]{2,63}\.){0,127}?)?(?<DomainWithTLD>(?<Domain>[\w-]{2,63})\.(?<TopLevelDomain>[\w-]{2,63}?)(?:\.(?<CountryCode>[a-z]{2}))?)(?:[:](?<Port>\d+))?(?<Path>(?:[\/]\w*)+)?(?<QString>(?<QSParams>(?:[?&=][\w-]*)+)?(?:[#](?<Anchor>\w*))*)?$/";

    #[Required]
    public Environment $templating;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public ?EntityManagerInterface $entityManager = null;

    #[Required]
    public ExceptionLoggerService $exceptionLoggerService;

    /**
     * @param string|array $template string|['path' => string, 'options' => array]
     */
    public function sendMail(callable|string|array    $subject,
                             string|array             $template,
                             array|string|Utilisateur $to,
                             array                    $attachments = []): bool {
        if (isset($_SERVER['APP_NO_MAIL']) && $_SERVER['APP_NO_MAIL'] == 1) {
            return true;
        }

        $filteredRecipients = Stream::from(!is_array($to) ? [$to] : $to)
            ->filter(static fn($user) => $user && (is_string($user) || $user->getStatus()));

        $contents = $this->createContents($filteredRecipients, $subject, $template);

        if (empty($contents)) {
            return false;
        }

        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $mailerServerSettings = $settingRepository->findByLabel([
            Setting::MAILER_URL,
            Setting::MAILER_PORT,
            Setting::MAILER_USER,
            Setting::MAILER_PASSWORD,
            Setting::MAILER_IS_TLS_PROTOCOL,
            Setting::MAILER_SENDER_NAME,
            Setting::MAILER_SENDER_MAIL,
        ]);

        $user = ($mailerServerSettings[Setting::MAILER_USER] ?? null)?->getValue();
        $password = ($mailerServerSettings[Setting::MAILER_PASSWORD] ?? null)?->getValue();
        $host = ($mailerServerSettings[Setting::MAILER_URL] ?? null)?->getValue();
        $port = ($mailerServerSettings[Setting::MAILER_PORT] ?? null)?->getValue();
        $isTLSProtocol = ($mailerServerSettings[Setting::MAILER_IS_TLS_PROTOCOL] ?? null)?->getValue();
        $senderName = ($mailerServerSettings[Setting::MAILER_SENDER_NAME] ?? null)?->getValue();
        $senderMail = ($mailerServerSettings[Setting::MAILER_SENDER_MAIL] ?? null)?->getValue();

        if (empty($user) || empty($password) || empty($host) || empty($port)) {
            return false;
        }

        //protection dev
        $redirectToTest = !isset($_SERVER['APP_ENV']) || $_SERVER['APP_ENV'] !== 'prod';

        $transport = (new EsmtpTransport($host, $port, $isTLSProtocol))
            ->setUsername($user)
            ->setPassword($password);

        foreach ($contents as $content) {
            $message = new Email();

            $body = $content['content'];
            $body .= $redirectToTest ? $this->getRecipientsLabel($content['to']) : "";

            $message
                ->from(new Address($senderMail, $senderName))
                ->subject($content['subject'])
                ->html($body);

            $to = $redirectToTest ? [self::TEST_EMAIL] : $content['to'];

            $to = (is_array($to) || $to instanceof Traversable) ? $to : [$to];
            foreach ($to as $email) {
                $message->addTo($email);
            }

            foreach ($attachments as $attachment) {
                if ($attachment instanceof Attachment) {
                    $path = "{$this->kernel->getProjectDir()}/public{$attachment->getFullPath()}";
                    $fileName = $attachment->getOriginalName() ?? basename($path);
                } else {
                    $path = $attachment;
                    $fileName = basename($path);
                }
                $file = fopen($path, 'r');
                if($file){
                    $message->attach(
                        $file,
                        $fileName,
                    );
                }
            }

            $mailer = (new Mailer($transport));
            try {
                $mailer->send($message);
            } catch (Throwable $e) {
                $this->exceptionLoggerService->sendLog($e);
            }
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
    private function createContents(Stream                $recipients,
                                    callable|string|array $subject,
                                    string|array          $template): array {
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
                // ignore if the domain is in NO_MAIL_DOMAINS
                $email = strtolower($email);
                preg_match(self::EMAIL_SLICING_REGEX, $email, $slicedAddress);
                if (filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array($slicedAddress["DomainWithTLD"] ?? null, self::NO_MAIL_DOMAINS)){
                    if (!isset($contents[$slug])) {
                        if (!is_string($template)) {
                            $context = $template['context'];
                            $context['language'] = $slug;
                            if (isset($context['title'])
                                && (is_array($context['title']) || is_callable($context['title']))) {
                                $context['title'] = is_callable($context['title'])
                                    ? $this->translationService->translateIn($slug, ...($context['title']($slug)))
                                    : $this->translationService->translateIn($slug, ...$context['title']);
                            }
                            $content = $this->templating->render($template['name'], $context);
                        }
                        else {
                            $content = $template;
                        }

                        $subject = match(true) {
                            is_callable($subject) => $this->translationService->translateIn($slug, ...($subject($slug))),
                            is_array($subject)    => $this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SERPARATOR . $this->translationService->translateIn($slug, ...$subject),
                            default               => $subject
                        };

                        $contents[$slug] = [
                            'to' => [$email],
                            'content' => $content,
                            'subject' => $subject
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
