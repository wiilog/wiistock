<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 12/04/2019
 * Time: 10:37
 */

namespace App\Twig;

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

        $matches = [];
        preg_match('/^<svg [^>]*width="([^"]+)"/', $svg, $matches);
        $svgWidth = count($matches) === 2 ? $matches[1] : 0;

        $matches = [];
        preg_match('/^<svg [^>]*height="([^"]+)"/', $svg, $matches);
        $svgHeight = count($matches) === 2 ? $matches[1] : 0;

        return $this->addDomAttribute($svg, 'viewBox', "0 0 $svgWidth $svgHeight");
    }

    private function removeXmlTag($dom): string {
        $matches = [];
        preg_match('/.*(<svg)([\s\S]*)/', $dom, $matches);
        return count($matches) === 3 ? ($matches[1] . $matches[2]) : $dom;
    }


    private function addDomAttribute($dom, string $attrName, string $value): ?string {
        if (isset($dom)) {
            $matches = [];

            $clearedDom = preg_replace("/[\t\n]/", "", trim($dom));

            preg_match(
                '/^<([[:alnum:]]+)([^>]*)>(.*)<\/([[:alnum:]]+)>/',
                $clearedDom,
                $matches
            );

            // if dom is like <X>...</X>
            if (count($matches) === 5) {
                $beginTag = $matches[1];
                $attributes = $matches[2];
                $content = $matches[3];
                $endTag = $matches[4];

                return "<$beginTag $attributes $attrName=\"$value\">$content</$endTag>";
            }
            else {
                $matches = [];
                preg_match(
                    '/^<([[:alnum:]]+)([^>\/]*)/>/',
                    $clearedDom,
                    $matches
                );

                // if dom is like <X .../>
                if (count($matches) === 3) {
                    $beginTag = $matches[1];
                    $attributes = $matches[2];

                    return "<$beginTag $attributes $attrName=\"$value\"/>";
                }
            }
        }

        return $dom;
    }
}
