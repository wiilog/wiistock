<?php

namespace App\Command\SingleUse;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Permet de transformer un fichier excel de traductions en
 * variable PHP qui contient ces traductions.
 *
 * Ne pas supprimer, peut servir plus tard si de gros pans
 * de l'application sont de nouveau traduit d'un coup.
 */
class ImportTranslationCSVCommand extends Command {

    protected function configure(): void {
        $this->setName("app:single-use:import-translations")
            ->setDescription("Initializes the application");
    }

    private function export(mixed $expression): string {
        $export = var_export($expression, true);
        $patterns = [
            "/array \(/" => '[',
            "/^([ ]*)\)(,?)$/m" => '$1]$2',
            "/=>[ ]?\n[ ]+\[/" => '=> [',
            "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
            "/( +)[0-9]+ => /" => '$1',
            "/'(.*)' => \\[/m" => "\"$1\" => [",
            "/'(.*)' => '(.*)',/m" => "\"$1\" => \"$2\",",
            "/__ESCAPE_QUOTE__/" => "\\\"",
            "/__ESCAPE_LINE_FEED__/" => "\\n",
        ];

        return "<?php \n" . '$export = ' . preg_replace(array_keys($patterns), array_values($patterns), $export);
    }

    private function sanitize(string $input): string {
        return str_replace("\"", "__ESCAPE_QUOTE__", str_replace("\n", "__ESCAPE_LINE_FEED__", $input));
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int {
        $file = fopen("translations.csv", "r");
        $output = [];

        while($line = fgetcsv($file, null, ";")) {
            $line = array_map("trim", $line);

            $category = $this->sanitize($line[0]);
            $menu = $this->sanitize($line[1]);
            $submenu = $this->sanitize($line[2]);

            if($submenu) {
                $content = $output[$category][$menu][$submenu] ?? [];
                if($line[3]) {
                    $content["tooltip"] = $this->sanitize($line[3]);
                }
            } else {
                $content = $output[$category][$menu] ?? [];
            }

            $item = [
                "fr" => $this->sanitize($line[4]),
                "en" => $this->sanitize($line[5]),
            ];

            if($line[6]) {
                $item["tooltip"] = $this->sanitize($line[6]);
            }

            $content["content"][] = $item;

            if($submenu) {
                $output[$category][$menu][$submenu] = $content;
            } else {
                $output[$category][$menu] = $content;
            }
        }

        file_put_contents("export.php", $this->export($output));

        return 0;
    }

}
