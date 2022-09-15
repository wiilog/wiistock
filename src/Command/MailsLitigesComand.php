<?php
// At 8:00
// 0 8 * * *

namespace App\Command;


use App\Entity\Dispute;

use App\Service\LanguageService;
use App\Service\MailerService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;

class MailsLitigesComand extends Command
{

    /** @Required */
    public RouterInterface $router;

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

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public TranslationService $translationService;

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
        $disputeRepository = $this->entityManager->getRepository(Dispute::class);

        /** @var Dispute[] $disputes */
        $disputes = $disputeRepository->findByStatutSendNotifToBuyer();

        $disputesByBuyer = [];
        foreach ($disputes as $dispute) {
            /** @var  $acheteursEmail */
            $acheteursEmail = $disputeRepository->getAcheteursArrivageByDisputeId($dispute->getId());
            foreach ($acheteursEmail as $email) {
                $disputesByBuyer[$email][] = $dispute;
            }
        }

        $listEmails = '';

        $defaultSlugLanguage = $this->languageService->getDefaultSlug();
        $slug = $this->languageService->getReverseDefaultLanguage($defaultSlugLanguage);

        $subject = $this->translationService->translateIn($slug, $defaultSlugLanguage, true, "Traçabilité", "Flux - Arrivages", "Email litige", "FOLLOW GT // Récapitulatif de vos litiges", false);
        $title = $this->translationService->translateIn($slug, $defaultSlugLanguage, true, "Traçabilité", "Flux - Arrivages", "Email litige", "Récapitulatif de vos litiges", false);

        foreach ($disputesByBuyer as $email => $disputes) {
            $this->mailerService->sendMail(
                $subject,
                $this->templating->render('mails/contents/mailLitigesArrivage.html.twig', [
                    'disputes' => $disputes,
                    'title' => $title,
                    'urlSuffix' => $this->router->generate('arrivage_index')
                ]),
                $email
            );
            $listEmails .= $email . ', ';
        }

        $nbMails = count($disputesByBuyer);

        $output->writeln($nbMails . ' mails ont été envoyés');
        $this->logger->info('ENVOI DE ' . $nbMails . ' MAILS RECAP LITIGES : ' . $listEmails);
        return 0;
    }
}
