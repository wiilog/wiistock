<?php

namespace App\Command;

use App\Service\TranslationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:update:translations',
    description: 'This commands generate the yaml translations.'
)]
class UpdateTranslationsCommand extends Command {

    #[Required]
    public TranslationService $translationService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->translationService->generateCache(null, true);
        $this->translationService->generateJavascripts();
        $output->writeln("Updated translation files");

        return 0;
    }

}
