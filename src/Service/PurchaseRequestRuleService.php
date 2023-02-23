<?php


namespace App\Service;

use App\Entity\Article;
use App\Entity\Fournisseur;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\PurchaseRequestScheduleRule;
use App\Entity\Setting;
use App\Entity\StorageRule;
use App\Entity\Zone;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class PurchaseRequestRuleService
{

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public UniqueNumberService $uniqueNumberService;

    #[Required]
    public PurchaseRequestService $purchaseRequestService;

    #[Required]
    public UserService $userService;

    #[Required]
    public MailerService $mailerService;

    public function treatRequestRule(PurchaseRequestScheduleRule $rule): void
    {
        $storageRuleRepository = $this->em->getRepository(StorageRule::class);
        $purchaseRequestLineRepository = $this->em->getRepository(PurchaseRequestLine::class);

        // vérification des quantités dans le repo
        $storageRules = $storageRuleRepository->getByPuchaseRequestRuleWithStockQuantity($rule);

        // ajout à une demande d'achat
        $rulesWithSeveralSuppliers = [];
        $refAdded = [];
        $purchaseRequests = [];
        /** @var StorageRule $storageRule */
        foreach ($storageRules as $storageRule) {
            $refArticle = $storageRule->getReferenceArticle();
            $supplier = $refArticle->getArticlesFournisseur()[0]->getFournisseur();

            if ($refArticle->getArticlesFournisseur()->count() > 1) {
                $rulesWithSeveralSuppliers[] = $storageRule;
            } else {
                if (isset($purchaseRequests[$supplier->getId()])) {
                    $purchaseRequest = $purchaseRequests[$supplier->getId()];
                } else {
                    $purchaseRequest = $this->purchaseRequestService->createPurchaseRequest($rule->getStatus(), $rule->getRequester(), ["supplier" => $supplier]);
                    $purchaseRequests[$supplier->getId()] = $purchaseRequest;
                }

                // on ajoute une ligne de demande d'achat par ref
                if (!in_array($refArticle->getId(), $refAdded)) {
                    $purchaseRequestLine = $this->purchaseRequestService->createPurchaseRequestLine($refArticle, $storageRule->getConditioningQuantity(), ["supplier" => $supplier]);
                    $purchaseRequest->addPurchaseRequestLine($purchaseRequestLine);
                    $refAdded[] = $refArticle->getId();
                } else {
                    $purchaseRequestLine = $purchaseRequestLineRepository->findOneBy(["purchaseRequest" => $purchaseRequest, "reference" => $refArticle]);
                    $purchaseRequestLine->setRequestedQuantity($purchaseRequestLine->getRequestedQuantity() + $storageRule->getConditioningQuantity());
                }

                $this->em->persist($purchaseRequestLine);
                $this->em->persist($purchaseRequest);

                $this->purchaseRequestService->sendMailsAccordingToStatus($purchaseRequest);
            }
        }

        if (!empty($rulesWithSeveralSuppliers)) {
            foreach ($rulesWithSeveralSuppliers as $severalSuppliersRule) {
                $purchaseRequestLine = $this->purchaseRequestService->createPurchaseRequestLine($severalSuppliersRule->getReferenceArticle(), $severalSuppliersRule->getConditioningQuantity());
                $purchaseRequest = $this->purchaseRequestService->createPurchaseRequest($rule->getStatus(), $rule->getRequester());
                $purchaseRequest->addPurchaseRequestLine($purchaseRequestLine);

                $this->em->persist($purchaseRequestLine);
                $this->em->persist($purchaseRequest);

                $this->purchaseRequestService->sendMailsAccordingToStatus($purchaseRequest);
            }
        }

        $this->em->flush();



    }
}


