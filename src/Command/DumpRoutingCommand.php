<?php

namespace App\Command;

use FOS\JsRoutingBundle\Command\DumpCommand;
use FOS\JsRoutingBundle\Extractor\ExposedRoutesExtractorInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DumpRoutingCommand extends DumpCommand {

    public function __construct(KernelInterface $kernel, ExposedRoutesExtractorInterface $extractor, SerializerInterface $serializer) {
        parent::__construct($extractor, $serializer, $kernel->getProjectDir());
    }

    protected function configure() {
        parent::configure();
        $this->setName(self::$defaultName);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $url = explode("://", $_SERVER["APP_URL"]);
        $target = "public/generated/routes.json";

        $input->setOption("format", "json");
        $input->setOption("target", $target);

        $code = parent::execute($input, $output);
        if($code) {
            $output = json_decode(file_get_contents($target));
            $output["scheme"] = $url[0];
            $output["host"] = $url[1];

            file_put_contents($target, json_encode($output));
        }

        return $code;
    }

}
