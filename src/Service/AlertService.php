<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\FreeField;
use App\Entity\ParametrageGlobal;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use App\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Twig\Environment;

class AlertService {

    private $mailer;
    private $templating;

    public function __construct(MailerService $mailer, Environment $templating) {
        $this->mailer = $mailer;
        $this->templating = $templating;
    }

    public function generateAlerts(EntityManagerInterface $manager) {
        $now = new DateTime("now", new \DateTimeZone("Europe/Paris"));
        $parametrage = $manager->getRepository(ParametrageGlobal::class);

        $expiry = $parametrage->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY);

        $expired = $manager->getRepository(Article::class)->findExpiredToGenerate($expiry);
        $noLongerExpired = $manager->getRepository(Alert::class)->findNoLongerExpired();

        foreach($noLongerExpired as $alert) {
            $manager->remove($alert);
        }

        $managers = [];
        /** @var Article $article */
        foreach($expired as $article) {
            $hasExistingAlert = !(
            Stream::from($article->getAlerts())
                ->filter(function(Alert $alert) {
                    return $alert->getType() === Alert::EXPIRY;
                })->isEmpty()
            );

            if(!$hasExistingAlert) {
                $alert = new Alert();
                $alert->setArticle($article);
                $alert->setType(Alert::EXPIRY);
                $alert->setDate($now);

                $manager->persist($alert);
            }
            $recipients = $article->getArticleFournisseur()
                ->getReferenceArticle()
                ->getManagers();

            foreach($recipients as $recipient) {
                $this->addArticle($managers, $recipient->getEmail(), $article);
                $this->addArticle($managers, $recipient->getSecondaryEmails(), $article);
            }
        }

        foreach($managers as $managerString => $articles) {
            $this->sendExpiryMails($managerString, $articles, $expiry);
        }

        $manager->flush();
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

    /**
     * @param ReferenceArticle $reference
     * @param EntityManagerInterface $entityManager
     * @throws NonUniqueResultException
     */
    public function sendThresholdMails(ReferenceArticle $reference, EntityManagerInterface $entityManager) {
        $freeField = $entityManager->getRepository(FreeField::class)
            ->findOneByLabel(FreeField::MACHINE_PDT_FREE_FIELD);
        $freeFieldValue = $freeField ? $reference->getFreeFieldValue($freeField->getId()) : "";
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
            ->each(function($email) use ($reference, $type, $freeFieldValue) {
                $content = $this->templating->render("mails/contents/mailThresholdReached.html.twig", [
                    "reference" => $reference,
                    "type" => $type,
                    'machinePDTValue' => $freeFieldValue
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

    public function putLineAlert(EntityManagerInterface $entityManager,
                                 SpecificService $specificService,
                                 CSVExportService $CSVExportService,
                                 $output,
                                 Alert $alert) {
        $serializedAlert = $alert->serialize();

        [$reference, $article] = $alert->getLinkedArticles();

        if($specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI)) {
            $freeFieldRepository = $entityManager->getRepository(FreeField::class);
            $freeFieldMachinePDT = $freeFieldRepository->findOneBy(['label' => 'Machine (PDT)']);

            if(($article || $reference)) {
                $freeFields = $reference->getFreeFields();
                if($freeFieldMachinePDT
                    && $freeFields
                    && $freeFields[(string)$freeFieldMachinePDT->getId()]) {
                    $freeFieldMachinePDTValue = $freeFields[(string)$freeFieldMachinePDT->getId()];
                } else {
                    $freeFieldMachinePDTValue = '';
                }

                $supplierArticles = $article
                    ? [$article->getArticleFournisseur()]
                    : $reference->getArticlesFournisseur()->toArray();

                if(!empty($supplierArticles)) {
                    /** @var ArticleFournisseur $supplierArticle */
                    foreach($supplierArticles as $supplierArticle) {
                        $supplier = $supplierArticle->getFournisseur();
                        $row = array_merge(array_values($serializedAlert), [
                            $supplier->getNom(),
                            $supplierArticle->getReference(),
                            $freeFieldMachinePDTValue
                        ]);
                        $CSVExportService->putLine($output, $row);
                    }
                } else {
                    $row = array_merge(array_values($serializedAlert), [
                        '', //supplier name
                        '', //supplier article reference
                        $freeFieldMachinePDTValue
                    ]);
                    $CSVExportService->putLine($output, $row);
                }
            }
        } else {
            $CSVExportService->putLine($output, $serializedAlert);
        }
    }

}
