<?php
// At every minute
// * * * * *

namespace App\Command\Cron;

use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Service\PurchaseRequestPlanService;
use App\Service\ScheduleRuleService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: ScheduledPurchaseRequestCommand::COMMAND_NAME,
    description: 'This command executes scheduled purchase resquests.'
)]
class ScheduledPurchaseRequestCommand extends Command
{

    public const COMMAND_NAME = "app:launch:scheduled-purchase-request";

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public PurchaseRequestPlanService $purchaseRequestPlanService;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $purchaseRequestRuleRepository = $this->em->getRepository(PurchaseRequestPlan::class);

        // todo adrien: get from cache
        $purchaseRequestRules = $purchaseRequestRuleRepository->findScheduled();
        foreach ($purchaseRequestRules as $rule) {
            $now = new DateTime();
            $now->setTime($now->format('H'), $now->format('i'), 0, 0);

            $nextExecutionDate = $this->scheduleRuleService->calculateNextExecution($rule, $now);

            // test if we can calculate a next execution date with the rule
            // AND if $now (date + hour + minute) is on same than this calculated execution date
            if (isset($nextExecutionDate) && $now >= $nextExecutionDate) {
                $this->purchaseRequestPlanService->treatRequestPlan($this->em, $rule);

                $rule->setLastRun($nextExecutionDate);
            }
        }
        $this->em->flush();

        return 0;
    }
}
