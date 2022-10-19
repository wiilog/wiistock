<?php
// At 23:00 on Sunday
// 0 23 * * 0

namespace App\Command;

use App\Service\InventoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class MissionCommand extends Command {

    protected static $defaultName = "app:generate:mission";

    #[Required]
    public InventoryService $inventoryService;

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->inventoryService->generateMissions();
        return 0;
    }

}
