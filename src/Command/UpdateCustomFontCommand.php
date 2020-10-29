<?php

namespace App\Command;

use App\Service\GlobalParamService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCustomFontCommand extends Command {

    private $service;

    public function __construct(GlobalParamService $service) {
        parent::__construct();
        $this->service = $service;
    }

    protected function configure() {
        $this->setName('app:update:custom-font');
        $this->setDescription('This commands generate the scss for custom font.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->service->generateScssFile();
        $output->writeln("\"assets/scss/_customFont.scss\" generated");
        $output->writeln("");
    }

}
