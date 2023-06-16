<?php


namespace App\Service;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\PurchaseRequestScheduleRule;
use App\Entity\StorageRule;
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

    public function treatRequestRule(PurchaseRequestScheduleRule $purchaseRequestRule): void
    {
        $storageRuleRepository = $this->em->getRepository(StorageRule::class);

        // getting storage rules by the purchase rule, quantity rule applied here
        $storageRules = $storageRuleRepository->findByPurchaseRequestRuleWithStockQuantity($purchaseRequestRule);
        $purchaseRequests = [];
        $suppliersByRefArticle = [];

        /** @var StorageRule $storageRule */
        foreach ($storageRules as $storageRule) {
            $refArticle = $storageRule->getReferenceArticle();
            $refArticleId = $refArticle->getId();

            if(!isset($suppliersByRefArticle[$refArticleId])){
                $suppliersByRefArticle[$refArticleId] = Stream::from($refArticle->getArticlesFournisseur())
                        ->map(fn(ArticleFournisseur $supplierArticle) => $supplierArticle->getFournisseur())
                        ->unique()
                        ->filter(fn(Fournisseur $supplier) => $purchaseRequestRule->getSuppliers()->contains($supplier))
                        ->toArray();
            }

            /** @var Fournisseur $supplier */
            foreach($suppliersByRefArticle[$refArticleId] as $supplier){
                $supplierId = $supplier->getId();
                $purchaseRequestLine = $this->purchaseRequestService->createPurchaseRequestLine($refArticle, $storageRule->getConditioningQuantity(), [
                    "supplier" => $supplier,
                    "location" => $storageRule->getLocation(),
                ]);
                if (isset($purchaseRequests[$supplierId])) {
                    $purchaseRequest = $purchaseRequests[$supplierId];
                } else {
                    $purchaseRequest = $this->purchaseRequestService->createPurchaseRequest($purchaseRequestRule->getStatus(), $purchaseRequestRule->getRequester(), ["supplier" => $supplier]);
                    $purchaseRequests[$supplierId] = $purchaseRequest;
                }

                $purchaseRequest->addPurchaseRequestLine($purchaseRequestLine);
                $this->em->persist($purchaseRequestLine);
                $this->em->persist($purchaseRequest);
            }

            $this->em->flush();
        }


        foreach ($purchaseRequests as $purchaseRequest) {
            $this->purchaseRequestService->sendMailsAccordingToStatus($this->em, $purchaseRequest, ["customSubject" => $purchaseRequestRule->getEmailSubject()]);
        }
    }
}


