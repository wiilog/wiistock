<?php

namespace App\Command;

use Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand as SymfonyCacheClearCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class CacheClearCommand extends SymfonyCacheClearCommand {

    private $kernel;

    public function __construct(KernelInterface $kernel, CacheClearerInterface $cacheClearer, Filesystem $filesystem = null) {
        parent::__construct($cacheClearer, $filesystem);
        $this->kernel = $kernel;
    }

    protected function configure() {
        parent::configure();
        $this->setName(self::$defaultName);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $name = $this->kernel->getProjectDir() . "/.env";
        if(!file_exists($name)) {
            return 0; //abort if .env doesn't exist
        }

        $env = file($name);
        $now = time();

        foreach($env as $line => $content) {
            if(str_starts_with($content, "APP_LAST_CACHE_CLEAR")) {
                $found = true;
                $env[$line] = "APP_LAST_CACHE_CLEAR=$now\n";
                break;
            }
        }

        if(!isset($found)) {
            $env[count($env)] = "APP_LAST_CACHE_CLEAR=$now\n";
        }

        file_put_contents($name, $env);

        return parent::execute($input, $output);
    }

}
