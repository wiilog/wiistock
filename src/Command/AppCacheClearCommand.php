<?php

namespace App\Command;

use App\Service\Cache\CacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:cache:clear',
    description: 'Clear wiilog cache.'
)]
class AppCacheClearCommand extends Command {

    #[Required]
    public CacheService $cacheService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->cacheService->clear();

        return 0;
    }
}
