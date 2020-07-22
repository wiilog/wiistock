<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 12/04/2019
 * Time: 10:37
 */

namespace App\Twig;

use Symfony\Component\HttpKernel\KernelInterface;
use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;

class FileExtension extends AbstractExtension
{
    /** @var string */
    private $projectDir;

    public function __construct(KernelInterface $kernel) {
        $this->projectDir = $kernel->getProjectDir();
    }

    public function getFilters()
    {
        return [
            new TwigFilter('fileToBase64Data', [$this, 'fileToBase64DataFunction'])
        ];
    }

	public function fileToBase64DataFunction(string $relativeFilePath): string {
        $absoluteFilePath = $this->projectDir . '/' . $relativeFilePath;
        $type = pathinfo($absoluteFilePath, PATHINFO_EXTENSION);
        $data = file_get_contents($absoluteFilePath);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}
