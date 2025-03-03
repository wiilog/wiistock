<?php

namespace App\Command;

use App\Entity\Fields\FixedFieldEnum;
use App\Entity\RequestTemplate\DeliveryRequestTemplateUsageEnum;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: 'app:update:enums',
    description: 'This commands generate js output with fixed fields.'
)]
class UpdateEnumsCommand extends Command {

    public function __construct(
        private KernelInterface $kernel
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->generateJSOutput();
        $output->writeln("Updated fixed fields file");

        return 0;
    }

    public function generateJSOutput(): void {
        $enums = [
            FixedFieldEnum::class => FixedFieldEnum::cases(),
            DeliveryRequestTemplateUsageEnum::class => DeliveryRequestTemplateUsageEnum::cases(),
        ];

        $outputDirectory = "{$this->kernel->getProjectDir()}/assets/generated";

        foreach ($enums as $class => $enumContent) {
            $fixedFields = Stream::from($enumContent)
                ->map(static function ($fixedField) {
                    $name = $fixedField->name;
                    $value = $fixedField->value;

                    return "\tstatic $name = {name: \"$name\", value: \"$value\"};";
                })
                ->join("\n");

            $className = $this->getClassName($class);
            $filename =  $this->getFilename($className);

            $content = "export default class $className { \n$fixedFields\n }\n";

            file_put_contents("$outputDirectory/$filename.js", $content);
        }
    }

    private function getClassName(string $class): string {
        return substr($class, strrpos($class, '\\') + 1);
    }

    private function getFilename(string $className): string {

        return strtolower(
            preg_replace(
                '/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/',
                '-',
                $className
            )
        );
    }
}
