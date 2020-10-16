<?php

namespace App\Command;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ParametrageGlobal;
use App\Entity\Utilisateur;
use App\Helper\Stream;
use App\Service\MailerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

class GenerateAlertsCommand extends Command {

    private $manager;
    private $mailer;
    private $templating;
    private $expiryDelay;

    public function __construct(EntityManagerInterface $manager, MailerService $mailer, Environment $templating) {
        parent::__construct();

        $this->manager = $manager;
        $this->mailer = $mailer;
        $this->templating = $templating;

        $this->expiryDelay = $manager->getRepository(ParametrageGlobal::class)
            ->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY) ?: "0h";
        $this->expiryDelay = str_replace("s", "week", $this->expiryDelay);
        $this->expiryDelay = str_replace("j", "day", $this->expiryDelay);
        $this->expiryDelay = str_replace("h", "hour", $this->expiryDelay);
    }

    protected function configure() {
        $this->setName("app:generate:alerts");
        $this->setDescription("Génère les alertes pour les dates de péremption");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

    }

    private function treatArticleThresholds() {

    }

    private function treatExpiredArticles() {
        $now = new DateTime("now", new \DateTimeZone("Europe/Paris"));

        $expired = $this->manager->getRepository(Article::class)->findExpiredToGenerate($this->expiryDelay);
        $managers = [];

        /** @var Article $article */
        foreach($expired as $article) {
            $alert = new Alert();
            $alert->setArticle($article);
            $alert->setType(Alert::EXPIRY);
            $alert->setDate($now);

            $this->manager->persist($alert);

            $recipients = $article->getArticleFournisseur()
                ->getReferenceArticle()
                ->getManagers();

            foreach($recipients as $recipient) {
                $this->addArticle($managers, $recipient->getEmail(), $article);
                $this->addArticle($managers, $recipient->getSecondaryEmails(), $article);
            }
        }

        foreach($managers as $manager => $articles) {
            /** @var Article[] $articles */
            $content = $this->templating->render('mails/contents/mailExpiredArticle.html.twig', [
                "articles" => $articles,
                "delay" => $this->displayExpiry(),
            ]);

            $this->mailer->sendMail('FOLLOW GT // Seuil de péremption atteint', $content, $manager);
        }
    }

    private function addArticle(array &$emails, $recipients, Article $article) {
        if(!is_array($recipients)) {
            $recipients = [$recipients];
        }

        foreach($recipients as $email) {
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if(!isset($emails[$email])) {
                $emails[$email] = [];
            }

            $emails[$email][] = $article;
        }
    }

    private function displayExpiry() {
        $expiry = $this->expiryDelay;
        $expiry = str_replace("week", " semaines", $expiry);
        $expiry = str_replace("day", " jours", $expiry);
        $expiry = str_replace("hour", " heures", $expiry);

        return $expiry;
    }

}
