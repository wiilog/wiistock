<?php

namespace App\Service\Document;

use PhpOffice\PhpWord\TemplateProcessor;
use SGK\BarcodeBundle\Generator\Generator as BarcodeGenerator;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;

class TemplateDocumentService {

    #[Required]
    public KernelInterface $kernel;

    private BarcodeGenerator $barcodeGenerator;

    public function __construct(BarcodeGenerator $barcodeGenerator) {
        $this->barcodeGenerator = $barcodeGenerator;
    }

    /**
     * @return string Path of the generated docx
     */
    public function generateDocx(string $templatePath,
                                 array  $variables,
                                 array  $options): string {
        $templateProcessor = new TemplateProcessor($templatePath);

        $barcodeVariables = $options['barcodes'] ?? [];
        $availableVariables = $templateProcessor->getVariables();
        foreach ($variables as $name => $value) {
            if (in_array($name, $availableVariables)) {
                if (is_array($value)) {
                    $templateProcessor->cloneRowAndSetValues($name, $value);
                }
                else {
                    if (in_array($name, $barcodeVariables)) {
                        $templateDocumentImagePath = $this->generateBarcodeTmpImage($value);
                        $templateProcessor->setImageValue($name, $templateDocumentImagePath);
                        unlink($templateDocumentImagePath);
                    }
                    else {
                        $value = $this->docxTextMapper($value);
                    }
                    $templateProcessor->setValue($name, $value);
                }
            }
        }

        return $templateProcessor->save();
    }

    private function docxTextMapper($value): string {
        return $value
            ? str_replace("\n", "<w:br/>", $value)
            : '';
    }

    /**
     * @return string Temp filename
     */
    private function generateBarcodeTmpImage(string $barcode): string {
        $templateDocumentImage = tempnam(sys_get_temp_dir(), "TemplateDocumentImage");

        $handle = fopen($templateDocumentImage, "w");

        $barcode = base64_decode($this->barcodeGenerator->generate([
            'code' => $barcode,
            'type' => 'qrcode',
            'format' => 'png',
            'width' => 10,
            'height' => 10,
            'color' => [0, 0, 0],
        ]));
        fwrite($handle, $barcode);
        fclose($handle);

        return $templateDocumentImage;
    }

}
