<?php


namespace App\Command;

use App\Entity\Article;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\PurchaseRequestScheduleRule;
use App\Entity\ReferenceArticle;
use App\Entity\StorageRule;
use App\Entity\Zone;
use App\Service\PurchaseRequestRuleService;
use App\Service\ScheduleRuleService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class ScheduledPurchaseRequestCommand extends Command
{

    private const DEFAULT_NAME = "app:launch:scheduled-purchase-request";

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public PurchaseRequestRuleService $purchaseRequestRuleService;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    protected function configure()
    {
        $this->setName(self::DEFAULT_NAME)
            ->setDescription("This command executes scheduled purchase resquests.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $purchaseRequestRuleRepository = $this->em->getRepository(PurchaseRequestScheduleRule::class);

        $purchaseRequestRules = $purchaseRequestRuleRepository->findAll();
        foreach ($purchaseRequestRules as $rule) {
            $now = new DateTime();
            $now->setTime($now->format('H'), $now->format('i'), 0, 0);

            $nextExecutionDate = $this->scheduleRuleService->calculateNextExecutionDate($rule, true);

            if ($now >= $nextExecutionDate) {
                $this->purchaseRequestRuleService->treatRequestRule($rule);
            }
        }

        throw new \Exception();
        return 0;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->em->isOpen()
            ? $this->em
            : EntityManager::create($this->em->getConnection(), $this->em->getConfiguration());
    }

}
