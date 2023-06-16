<?php


namespace App\Command;

use App\Entity\Inventory\InventoryMissionRule;
use App\Service\InvMissionService;
use App\Service\ScheduleRuleService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class ScheduleInventoryMissionCommand extends Command
{

    private const DEFAULT_NAME = "app:inventory:generate-mission";

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ScheduleRuleService $scheduleRuleService;

    #[Required]
    public InvMissionService $invMissionService;

    protected function configure()
    {
        $this->setName(self::DEFAULT_NAME)
            ->setDescription("This command executes scheduled export.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $invMissionRuleRepository = $this->getEntityManager()->getRepository(InventoryMissionRule::class);

        $rules = $invMissionRuleRepository->findBy(['active'=> true]);

        foreach ($rules as $rule) {
            $now = new DateTime();
            $now->setTime($now->format('H'), $now->format('i'), 0, 0);

            $nextExecutionDate = $this->scheduleRuleService->calculateNextExecutionDate($rule, true);

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
            : EntityManager::create($this->em->getConnection(), $this->em->getConfiguration());
    }

}
