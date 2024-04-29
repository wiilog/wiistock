<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\FreeField;
use App\Entity\Setting;
use App\Entity\ReferenceArticle;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class AlertService {

    #[Required]
    public TranslationService $translationService;

    private MailerService $mailer;
    private Environment $templating;

    public function __construct(MailerService $mailer, Environment $templating) {
        $this->mailer = $mailer;
        $this->templating = $templating;
    }

    public function generateAlerts(EntityManagerInterface $manager): void {
        $now = new DateTime("now");
        $parametrage = $manager->getRepository(Setting::class);

        $expiry = $parametrage->getOneParamByLabel(Setting::STOCK_EXPIRATION_DELAY);

        $expired = $expiry ? $manager->getRepository(Article::class)->findExpiredToGenerate($expiry) : [];
        $noLongerExpired = $manager->getRepository(Alert::class)->findNoLongerExpired();

        foreach($noLongerExpired as $alert) {
            $manager->remove($alert);
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

                $manager->persist($alert);
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
            $this->sendExpiryMails($emailConfig['user'], $emailConfig['articles'], $expiry);
        }

        $manager->flush();
    }

    public function sendThresholdMails(ReferenceArticle $reference, EntityManagerInterface $entityManager): void {
        $freeField = $entityManager->getRepository(FreeField::class)
            ->findOneByLabel(FreeField::MACHINE_PDT_FREE_FIELD);
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

        $appName = $this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SERPARATOR;
        $this->mailer->sendMail("$appName $type atteint", $content, $reference->getManagers()->toArray());
    }

    public function sendExpiryMails($manager, $articles, $delay): void {
        if(!is_array($articles)) {
            $articles = [$articles];
        }

        $content = $this->templating->render('mails/contents/mailExpiredArticle.html.twig', [
            "articles" => $articles,
            "delay" => $delay,
        ]);

        $this->mailer->sendMail($this->translationService->translate('Général', null, 'Header', 'Wiilog', false) . MailerService::OBJECT_SERPARATOR . 'Seuil de péremption atteint', $content, $manager);
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

        if ($specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI)) {
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
                    $this->sendExpiryMails($managers, $article, $expiryDelay);
                }
            } else if ($now < $article->getExpiryDate() && $existing) {
                $entityManager->remove($existing[0]);
            }
        }
    }
}
