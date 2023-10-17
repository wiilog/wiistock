<?php
// At 8:00
// 0 8 * * *

namespace App\Command;


use App\Entity\Dispute;

use App\Entity\Utilisateur;
use App\Service\LanguageService;
use App\Service\MailerService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:mails-litiges',
    description: 'envoi de mails aux acheteurs pour les litiges non soldés'
)]
class MailsLitigesComand extends Command
{

    #[Required]
    public RouterInterface $router;

    #[Required]
    public MailerService $mailerService;

    #[Required]
    public LoggerInterface $logger;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public LanguageService $languageService;

    #[Required]
    public TranslationService $translationService;

    public function __construct() {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int {
        $disputeRepository = $this->entityManager->getRepository(Dispute::class);
        $userRepository = $this->entityManager->getRepository(Utilisateur::class);

        /** @var Dispute[] $disputes */
        $disputes = $disputeRepository->findByStatutSendNotifToBuyer();

        $disputesByBuyer = [];
        foreach ($disputes as $dispute) {
            /** @var  $buyers */
            $buyers = $userRepository->getDisputeBuyers($dispute);
            foreach ($buyers as $buyer) {
                $buyerId = $buyer->getId();
                $disputeId = $dispute->getId();
                if (!isset($disputesByBuyer[$buyerId])) {
                    $disputesByBuyer[$buyerId] = [
                        'disputes' => [$dispute],
                        'disputeIds' => [$disputeId],
                        'buyer' => $buyer
                    ];
                }
                else if (!in_array($disputeId, $disputesByBuyer[$buyerId]['disputeIds'])) {
                    $disputesByBuyer[$buyerId]['disputeIds'][] = $disputeId;
                    $disputesByBuyer[$buyerId]['disputes'][] = $dispute;
                }
            }
        }

        $listEmails = '';

        $subject = ["Traçabilité", "Arrivages UL", "Email litige", "FOLLOW GT // Récapitulatif de vos litiges", false];
        $title = ["Traçabilité", "Arrivages UL", "Email litige", "Récapitulatif de vos litiges", false];

        foreach ($disputesByBuyer as $res) {
            $disputes = $res['disputes'];
            $buyer = $res['buyer'];
            $this->mailerService->sendMail(
                $subject,
                [
                    'name' => 'mails/contents/mailLitigesArrivage.html.twig',
                    'context' => [
                        'disputes' => $disputes,
                        'title' => $title,
                        'urlSuffix' => $this->router->generate('arrivage_index')
                    ]
                ],
                $buyer
            );
            $listEmails .= $buyer->getEmail() . ', ';
        }

        $nbMails = count($disputesByBuyer);

        $output->writeln($nbMails . ' mails ont été envoyés');
        $this->logger->info('ENVOI DE ' . $nbMails . ' MAILS RECAP LITIGES : ' . $listEmails);
        return 0;
    }
}
