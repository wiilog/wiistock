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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: 'app:launch:scheduled-purchase-request',
    description: 'This command executes scheduled purchase resquests.'
)]
class ScheduledPurchaseRequestCommand extends Command
{

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public PurchaseRequestRuleService $purchaseRequestRuleService;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $purchaseRequestRuleRepository = $this->em->getRepository(PurchaseRequestScheduleRule::class);

        $purchaseRequestRules = $purchaseRequestRuleRepository->findAll();
        foreach ($purchaseRequestRules as $rule) {
            $now = new DateTime();
            $now->setTime($now->format('H'), $now->format('i'), 0, 0);

            $nextExecutionDate = $this->scheduleRuleService->calculateNextExecutionDate($rule, true);

            if (isset($nextExecutionDate) && $now >= $nextExecutionDate) {
                $this->purchaseRequestRuleService->treatRequestRule($rule);

                $rule->setLastRun($nextExecutionDate);
            }
        }
        $this->em->flush();

        return 0;
    }
}
