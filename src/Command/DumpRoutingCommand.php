<?php

namespace App\Command;

use FOS\JsRoutingBundle\Command\DumpCommand;
use FOS\JsRoutingBundle\Extractor\ExposedRoutesExtractorInterface;
use FOS\JsRoutingBundle\Response\RoutesResponse;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DumpRoutingCommand extends DumpCommand {

    public function __construct(
        #[Autowire("@fos_js_routing.routes_response")] RoutesResponse            $routesResponse,
        #[Autowire("@fos_js_routing.extractor")] ExposedRoutesExtractorInterface $extractor,
        #[Autowire("@serializer")] SerializerInterface                           $serializer,
        #[Autowire("%kernel.project_dir%")] string                               $projectDir) {
        parent::__construct($routesResponse, $extractor, $serializer, $projectDir);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $url = explode("://", $_SERVER["APP_URL"]);
        $target = "assets/generated/routes.json";

        $input->setOption("format", "json");
        $input->setOption("target", $target);

        $code = parent::execute($input, $output);
        if($code === 0) {
            $output = json_decode(file_get_contents($target));
            $output->scheme = $url[0];
            $output->host = $url[1];

            file_put_contents($target, json_encode($output));
        }

        return $code;
    }

}
