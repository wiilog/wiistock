<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 12/04/2019
 * Time: 10:37
 */

namespace App\Twig;

use DOMNode;
use Symfony\Component\DomCrawler\Crawler;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use SGK\BarcodeBundle\Generator\Generator as BarcodeGenerator;

class BarcodeExtension extends AbstractExtension
{

    private $barcodeGenerator;

    public function __construct(BarcodeGenerator $barcodeGenerator) {
        $this->barcodeGenerator = $barcodeGenerator;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('printBarcode', [$this, 'printBarcodeFunction'])
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



//        $matches = [];
//        preg_match('/^<svg [^>]*width="([^"]+)"/', $svg, $matches);
//        $svgWidth = count($matches) === 2 ? $matches[1] : 0;
//
//        $matches = [];
//        preg_match('/^<svg [^>]*height="([^"]+)"/', $svg, $matches);
//        $svgHeight = count($matches) === 2 ? $matches[1] : 0;
//
//        return $this->addDomAttribute($svg, 'viewBox', "0 0 $svgWidth $svgHeight");
        return $crawler->html();
    }

    private function removeXmlTag($dom): string {
        $matches = [];
        preg_match('/.*(<svg)([\s\S]*)/', $dom, $matches);
        return count($matches) === 3 ? ($matches[1] . $matches[2]) : $dom;
    }
}
