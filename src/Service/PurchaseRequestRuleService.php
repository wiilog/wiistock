<?php


namespace App\Service;

use App\Entity\ArticleFournisseur;
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

        // getting storage rules by the purchase rule, quantity rule applied here
        $storageRules = $storageRuleRepository->findByPurchaseRequestRuleWithStockQuantity($rule);
        $rulesWithSeveralSuppliers = [];
        $purchaseRequests = [];

        /** @var StorageRule $storageRule */
        foreach ($storageRules as $storageRule) {
            $refArticle = $storageRule->getReferenceArticle();

            if ($refArticle->getArticlesFournisseur()->count() > 1) {
                $rulesWithSeveralSuppliers[] = $storageRule;
            } else {
                // one purchase request per supplier
                if (isset($purchaseRequests[$supplierId])) {
                    $purchaseRequest = $purchaseRequests[$supplierId];
                } else {
                    $purchaseRequest = $this->purchaseRequestService->createPurchaseRequest($rule->getStatus(), $rule->getRequester(), ["supplier" => $supplier]);
                    $purchaseRequests[$supplierId] = $purchaseRequest;
                }

                // one purchase request line per reference art
                $purchaseRequestLine = $this->purchaseRequestService->createPurchaseRequestLine($refArticle, $storageRule->getSecurityQuantity(), [
                    "supplier" => $supplier,
                    "location" => $storageRule->getLocation(),
                ]);
                $purchaseRequest->addPurchaseRequestLine($purchaseRequestLine);

                $this->em->persist($purchaseRequestLine);
                $this->em->persist($purchaseRequest);
                $this->em->flush();
            }
        }

        // storage rules with multiple supplier all in the same purchase request
        if (!empty($rulesWithSeveralSuppliers)) {
            foreach ($rulesWithSeveralSuppliers as $severalSuppliersRule) {
                $purchaseRequestLine = $this->purchaseRequestService->createPurchaseRequestLine($severalSuppliersRule->getReferenceArticle(), $severalSuppliersRule->getConditioningQuantity());
                $purchaseRequest = $this->purchaseRequestService->createPurchaseRequest($rule->getStatus(), $rule->getRequester());
                $purchaseRequests[] = $purchaseRequest;
                $purchaseRequest->addPurchaseRequestLine($purchaseRequestLine);

                $this->em->persist($purchaseRequestLine);
                $this->em->persist($purchaseRequest);
                $this->em->flush();
            }
        }

        foreach ($purchaseRequests as $purchaseRequest) {
            $this->purchaseRequestService->sendMailsAccordingToStatus($purchaseRequest, ["customSubject" => $rule->getEmailSubject()]);
        }
    }
}


