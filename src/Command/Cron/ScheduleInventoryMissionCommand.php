<?php
// At every minute
// * * * * *

namespace App\Command\Cron;

use App\Entity\Inventory\InventoryMissionRule;
use App\Service\InvMissionService;
use App\Service\ScheduleRuleService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: ScheduleInventoryMissionCommand::COMMAND_NAME,
    description: 'This command executes scheduled export.'
)]
class ScheduleInventoryMissionCommand extends Command
{
    public const COMMAND_NAME = 'app:inventory:generate-mission';

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    #[Required]
    public InvMissionService $invMissionService;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $invMissionRuleRepository = $this->getEntityManager()->getRepository(InventoryMissionRule::class);

        $rules = $invMissionRuleRepository->findBy(['active' => true]);

        foreach ($rules as $rule) {
            $now = new DateTime();
            $now->setTime($now->format('H'), $now->format('i'), 0, 0);

            $nextExecutionDate = $this->scheduleRuleService->calculateNextExecutionDate($rule, $now);

            // test if we can calculate a next execution date with the rule
            // AND if $now (date + hour + minute) is on same than this calculated execution date
            if (isset($nextExecutionDate) && $now >= $nextExecutionDate) {
                $this->invMissionService->generateMission($rule);
            }
        }
        return 0;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->em->isOpen()
            ? $this->em
            : new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
    }

}
