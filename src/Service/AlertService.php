<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\Stream;
use Twig\Environment;

class AlertService {

    private $mailer;
    private $templating;

    public function __construct(MailerService $mailer, Environment $templating) {
        $this->mailer = $mailer;
        $this->templating = $templating;
    }

    public function sendThresholdMails(ReferenceArticle $reference) {
        if($reference->getLimitSecurity() >= $reference->getQuantiteDisponible()) {
            $type = "Seuil de sécurité";
        } else if($reference->getLimitWarning() >= $reference->getQuantiteDisponible()) {
            $type = "Seuil d'alerte";
        } else {
            return;
        }

        Stream::from($reference->getManagers())
            ->map(function(Utilisateur $manager) {
                return [$manager->getEmail(), $manager->getSecondaryEmails()];
            })
            ->flatten()
            ->filter(function($email) {
                return !empty($email);
            })
            ->unique()
            ->each(function($email) use ($reference, $type) {
                $content = $this->templating->render("mails/contents/mailThresholdReached.html.twig", [
                    "reference" => $reference,
                    "type" => $type
                ]);

                $this->mailer->sendMail("FOLLOW GT // $type atteint", $content, $email);
            });
    }

    public function sendExpiryMails($manager, $articles, $delay) {
        if(!is_array($articles)) {
            $articles = [$articles];
        }

        $content = $this->templating->render('mails/contents/mailExpiredArticle.html.twig', [
            "articles" => $articles,
            "delay" => $delay,
        ]);

        $this->mailer->sendMail('FOLLOW GT // Seuil de péremption atteint', $content, $manager);
    }

}
