<?php

namespace App\Service\Document;

use PhpOffice\PhpWord\Element\Table;
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
                    $templateProcessor->cloneRow($name, count($value));

                    foreach ($value as $rowKey => $rowData) {
                        $rowNumber = $rowKey + 1;
                        foreach ($rowData as $macro => $replace) {
                            $this->setTemplateProcessorValue($templateProcessor, $macro . '#' . $rowNumber, $replace, $barcodeVariables);
                        }
                    }
                }
                else {
                    $this->setTemplateProcessorValue($templateProcessor, $name, $value, $barcodeVariables);
                }
            }
        }

        return $templateProcessor->save();
    }

    private function setTemplateProcessorValue(TemplateProcessor $templateProcessor,
                                              string             $name,
                                                                 $value,
                                              array              $barcodeVariables): void {
        if (in_array($name, $barcodeVariables)) {
            $templateDocumentImagePath = $this->generateBarcodeTmpImage($value);
            $templateProcessor->setImageValue($name, $templateDocumentImagePath);
            unlink($templateDocumentImagePath);
        }
        else if (is_array($value)) { // it's a nested array
            $table = new Table(["borderColor" => "000000", "borderSize" => 2]);
            $headerTreated = false;
            foreach ($value as $row) {
                $table->addRow();
                if (!$headerTreated) {
                    $cellStyle = ["bgColor" => "000000", "color" => "FFFFFF"];
                    $headerTreated = true;
                }
                foreach ($row as $cell) {
                    $table
                        ->addCell(null, $cellStyle ?? null)
                        ->addText($this->docxTextMapper($cell));
                }
                $cellStyle = null;
            }
            $templateProcessor->setComplexBlock($name, $table);
        }
        else {
            $templateProcessor->setValue($name, $this->docxTextMapper($value));
        }
    }

    public function docxTextMapper($value): string {
        return $value
            ? str_replace("\n", "</w:t><w:br/><w:t>", $value)
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
