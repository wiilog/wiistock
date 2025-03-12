<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\FreeField\FreeField;
use App\Entity\ReferenceArticle;
use App\Entity\Setting;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use WiiCommon\Helper\Stream;

class AlertService {

    public function __construct(
        private MailerService $mailer,
        private SettingsService $settingsService,
        private TranslationService $translationService,
        private Environment $templating,
    ) {
    }

    public function generateAlerts(EntityManagerInterface $entityManager): void {
        $now = new DateTime("now");

        $expiry = $this->settingsService->getValue($entityManager, Setting::STOCK_EXPIRATION_DELAY);

        $expired = $expiry ? $entityManager->getRepository(Article::class)->findExpiredToGenerate($expiry) : [];
        $noLongerExpired = $entityManager->getRepository(Alert::class)->findNoLongerExpired();

        foreach($noLongerExpired as $alert) {
            $entityManager->remove($alert);
        }

        $managers = [];
        /** @var Article $article */
        foreach($expired as $article) {
            $hasExistingAlert = !(
                Stream::from($article->getAlerts())
                    ->filter(fn(Alert $alert) => $alert->getType() === Alert::EXPIRY)
                    ->isEmpty()
            );

            if(!$hasExistingAlert) {
                $alert = new Alert();
                $alert->setArticle($article);
                $alert->setType(Alert::EXPIRY);
                $alert->setDate($now);

                $entityManager->persist($alert);
            }
            $recipients = $article->getArticleFournisseur()
                ->getReferenceArticle()
                ->getManagers();

            foreach($recipients as $recipient) {
                $id = $recipient->getId();
                if(!isset($managers[$id])) {
                    $managers[$id] = [
                        'articles' => [],
                        'user' => $recipient
                    ];
                }

                $managers[$id]['articles'][] = $article;
            }
        }

        foreach($managers as $emailConfig) {
            $this->sendExpiryMails($entityManager, $emailConfig['user'], $emailConfig['articles'], $expiry);
        }

        $entityManager->flush();
    }

    public function sendThresholdMails(ReferenceArticle $reference, EntityManagerInterface $entityManager): void {
        $freeField = $entityManager->getRepository(FreeField::class)
            ->findOneBy(["label" => FreeField::MACHINE_PDT_FREE_FIELD]);
        $freeFieldValue = $freeField ? $reference->getFreeFieldValue($freeField->getId()) : "";
        if($reference->getLimitSecurity() >= $reference->getQuantiteStock()) {
            $type = "Seuil de sécurité";
        } else if($reference->getLimitWarning() >= $reference->getQuantiteStock()) {
            $type = "Seuil d'alerte";
        } else {
            return;
        }

        $content = $this->templating->render("mails/contents/mailThresholdReached.html.twig", [
            "reference" => $reference,
            "type" => $type,
            'machinePDTValue' => $freeFieldValue
        ]);

        $appName = $this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR;
        $this->mailer->sendMail($entityManager, "$appName $type atteint", $content, $reference->getManagers()->toArray());
    }

    public function sendExpiryMails(EntityManagerInterface $entityManager,
                                    $manager,
                                    $articles,
                                    $delay): void {
        if(!is_array($articles)) {
            $articles = [$articles];
        }

        $content = $this->templating->render('mails/contents/mailExpiredArticle.html.twig', [
            "articles" => $articles,
            "delay" => $delay,
        ]);

        $this->mailer->sendMail($entityManager, $this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SEPARATOR . 'Seuil de péremption atteint', $content, $manager);
    }

    public function putLineAlert(EntityManagerInterface $entityManager,
                                 SpecificService $specificService,
                                 CSVExportService $CSVExportService,
                                 $output,
                                 Alert $alert): void {
        $serializedAlert = $alert->serialize();

        /** @var ReferenceArticle $reference */
        /** @var Article $article */
        [$reference, $article] = $alert->getLinkedArticles();

        if ($specificService->isCurrentClientNameFunction(SpecificService::CLIENT_RATATOUILLE)) {
            $freeFieldRepository = $entityManager->getRepository(FreeField::class);
            $freeFieldMachinePDT = $freeFieldRepository->findOneBy(['label' => 'Machine PDT']);

            if (($article || $reference)) {
                $freeFields = $reference->getFreeFields();
                if ($freeFieldMachinePDT
                    && $freeFields
                    && array_key_exists($freeFieldMachinePDT->getId(), $freeFields)
                    && $freeFields[(string)$freeFieldMachinePDT->getId()]) {
                    $freeFieldMachinePDTValue = $freeFields[(string)$freeFieldMachinePDT->getId()];
                } else {
                    $freeFieldMachinePDTValue = '';
                }

                $supplierArticles = $article
                    ? [$article->getArticleFournisseur()]
                    : $reference->getArticlesFournisseur()->toArray();

                if (!empty($supplierArticles)) {
                    /** @var ArticleFournisseur $supplierArticle */
                    foreach ($supplierArticles as $supplierArticle) {
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
        }
        else {
            $CSVExportService->putLine($output, $serializedAlert);
        }
    }

    public function treatArticleAlert(EntityManagerInterface $entityManager,
                                      Article                $article,
                                      int|null               $expiryDelay): void {
        if (is_numeric($expiryDelay) && $article->getExpiryDate()) {
            $now = new DateTime("now");
            $expires = clone $now;
            $expires->modify("$expiryDelay day");

            $existing = $entityManager->getRepository(Alert::class)->findForArticle($article, Alert::EXPIRY);

            //more than one expiry alert is an invalid state, so remove them to reset
            if (count($existing) > 1) {
                foreach ($existing as $alert) {
                    $entityManager->remove($alert);
                }

                $existing = null;
            }

            if ($expires >= $article->getExpiryDate() && !$existing) {
                $alert = new Alert();
                $alert->setArticle($article);
                $alert->setType(Alert::EXPIRY);
                $alert->setDate($now);

                $entityManager->persist($alert);

                if ($article->getStatut()->getCode() !== Article::STATUT_INACTIF) {
                    $managers = $article->getArticleFournisseur()
                        ->getReferenceArticle()
                        ->getManagers()
                        ->toArray();
                    $this->sendExpiryMails($entityManager, $managers, $article, $expiryDelay);
                }
            } else if ($now < $article->getExpiryDate() && $existing) {
                $entityManager->remove($existing[0]);
            }
        }
    }
}
