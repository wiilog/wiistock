<?php

namespace App\Command;

use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class UpdateTranslationsCommand extends Command {

    #[Required]
    public TranslationService $translationService;

    protected function configure() {
		$this->setName("app:update:translations");
		$this->setDescription("This commands generate the yaml translations.");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->translationService->generateCache();
        $this->translationService->generateJavascripts();
        $output->writeln("Updated translation files");

        return 0;
    }

}
