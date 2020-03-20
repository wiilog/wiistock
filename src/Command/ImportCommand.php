<?php


namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{
    protected static $defaultName = 'app:launch:imports';

    protected function configure()
    {
		$this->setDescription('This command executes planified imports.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}
