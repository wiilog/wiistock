<?php

namespace App\Command;

use App\Service\FixedFieldService;
use App\Service\TranslationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:update:fixed-fields',
    description: 'This commands generate js output with fixed fields.'
)]
class UpdateFixedFieldsCommand extends Command {

    #[Required]
    public TranslationService $translationService;

    #[Required]
    public FixedFieldService $fixedFieldService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->fixedFieldService->generateJSOutput();
        $output->writeln("Updated fixed fields file");

        return 0;
    }

}
