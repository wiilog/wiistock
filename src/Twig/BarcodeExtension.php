<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use SGK\BarcodeBundle\Generator\Generator as BarcodeGenerator;

class BarcodeExtension extends AbstractExtension {

    public function __construct(
        #[Autowire("@sgk_barcode.generator")] private  BarcodeGenerator $barcodeGenerator
    ) {}

    public function getFunctions(): array {
        return [
            new TwigFunction('printBarcode', $this->printBarcodeFunction(...))
        ];
    }

	public function printBarcodeFunction($options = []): string {
        $svg = $this->removeXmlTag($this->barcodeGenerator->generate($options));

        $crawler = new Crawler($svg);
        $svgNode = $crawler->filter('svg')->getNode(0);

        $width = $svgNode->getAttribute('width');
        $height = $svgNode->getAttribute('height');
        $svgNode->setAttribute('viewBox', "0 0 $width $height");

        $svgNode->removeAttribute('width');
        $svgNode->removeAttribute('height');

        return $crawler->html();
    }

    private function removeXmlTag(string $dom): string {
        $matches = [];
        preg_match('/.*(<svg)([\s\S]*)/', $dom, $matches);
        return count($matches) === 3 ? ($matches[1] . $matches[2]) : $dom;
    }
}
