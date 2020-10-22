<?php

namespace App\Command;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ParametrageGlobal;
use App\Helper\Stream;
use App\Service\AlertService;
use App\Service\MailerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

class GenerateAlertsCommand extends Command {

    private $manager;
    private $service;

    private $sendSecurity;
    private $sendWarning;
    private $sendExpiry;
    private $expiryDelay;

    public function __construct(EntityManagerInterface $manager, AlertService $service) {
        parent::__construct();

        $this->manager = $manager;
        $this->service = $service;

        $parametrage = $manager->getRepository(ParametrageGlobal::class);
        $this->sendSecurity = $parametrage->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_SECURITY_THRESHOLD);
        $this->sendWarning = $parametrage->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_WARNING_THRESHOLD);

        if($expiry = $parametrage->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY)) {
            $this->sendExpiry = true;
            $this->expiryDelay = $expiry;
            $this->expiryDelay = str_replace("s", " semaines", $this->expiryDelay);
            $this->expiryDelay = str_replace("j", " jours", $this->expiryDelay);
            $this->expiryDelay = str_replace("h", " heures", $this->expiryDelay);
        } else {
            $this->sendExpiry = false;
        }
    }

    protected function configure() {
        $this->setName("app:generate:alerts");
        $this->setDescription("Génère les alertes pour les dates de péremption");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $now = new DateTime("now", new \DateTimeZone("Europe/Paris"));

        $expired = $this->manager->getRepository(Article::class)->findExpiredToGenerate($this->expiryDelay);
        $noLongerExpired = $this->manager->getRepository(Alert::class)->findNoLongerExpired();

        foreach ($noLongerExpired as $alert) {
            $this->manager->remove($alert);
        }

        $managers = [];
        /** @var Article $article */
        foreach($expired as $article) {
            $hasExistingAlert = !(
                Stream::from($article->getAlerts())
                    ->filter(function (Alert $alert) {
                        return $alert->getType() === Alert::EXPIRY;
                    })->isEmpty()
            );

            if (!$hasExistingAlert) {
                $alert = new Alert();
                $alert->setArticle($article);
                $alert->setType(Alert::EXPIRY);
                $alert->setDate($now);

                $this->manager->persist($alert);
            }
            $recipients = $article->getArticleFournisseur()
                ->getReferenceArticle()
                ->getManagers();

            foreach ($recipients as $recipient) {
                $this->addArticle($managers, $recipient->getEmail(), $article);
                $this->addArticle($managers, $recipient->getSecondaryEmails(), $article);
            }
        }

        foreach($managers as $manager => $articles) {
            $this->service->sendExpiryMails($manager, $articles, $this->expiryDelay);
        }
        $this->manager->flush();
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
