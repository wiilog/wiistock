<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\RefArticleManager;

class CreateRefArticleCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:create-ref';
    private $refArticleManager;

    public function __construct(RefArticleManager $refArticleManager)
    {
        $this->refArticleManager = $refArticleManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
        // the short description shown while running "php bin/console list"
            ->setDescription('Creates a bunch of ReferenceArticle.')

        // the full command description shown when running the command with
        // the "--help" option
            ->setHelp('This command allows you to create a bunch of ReferenceArticle...');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln([
            'Reference article Creator',
            '============',
            '',
        ]);

    // the value returned by someMethod() can be an iterator (https://secure.php.net/iterator)
    // that generates and returns the messages with the 'yield' PHP keyword
    for ($i = 0; $i < 1000000; $i++) {
        $output->writeln($this->refArticleManager->create($i));
        $output->writeln(memory_get_usage());
    }

    // outputs a message followed by a "\n"
        $output->writeln('Whoa!');

    }

}