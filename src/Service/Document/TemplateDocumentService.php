<?php

namespace App\Service\Document;

use Exception;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\TemplateProcessor;
use SGK\BarcodeBundle\Generator\Generator as BarcodeGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use WiiCommon\Helper\Stream;

class TemplateDocumentService {

    public function __construct(
        #[Autowire("@sgk_barcode.generator")] private BarcodeGenerator $barcodeGenerator,
    ) {}

    /**
     * @param string $templatePath
     * @param array $variables The array contains all variables to replace in template
     * IF the "pages" key exists it means we need multiple page generation
     * @param array $options ["barcodes"] => Contains the variable who needs to be replaced by a barcode
     * @return string
     */
    public function generateDocx(string $templatePath,
                                         array  $variables,
                                         array  $options = []): string {
        //clone $variables to be able to change its values
        $variables = [...$variables];
        $templateProcessor = new TemplateProcessor($templatePath);

        $barcodeVariables = $options['barcodes'] ?? [];

        $availableVariables = $templateProcessor->getVariables();

        $specialPageBlockPresents = in_array('pages', $availableVariables) && in_array('/pages', $availableVariables);
        if($specialPageBlockPresents && !isset($variables['pages'])){
            throw new Exception('Invalid page config for dotx template.');
        }

        if($specialPageBlockPresents) {
            $templateProcessor->setValue("/pages", '${__wiilog__pageBreak}</w:t></w:r></w:p><w:p><w:r><w:t>${/pages}');
            $templateProcessor->cloneBlock('pages', count($variables['pages']), true, true);

            foreach ($variables["pages"] as $pageIndex => $pageVariables) {
                $pageNumber = $pageIndex + 1;
                $pageId ="#$pageNumber";
                $barcodeVariablesWithPageId = Stream::from($barcodeVariables)
                    ->map(static fn(string $key) => ($key . $pageId))
                    ->toArray();
                $this->replacePageVariables($templateProcessor, $pageVariables, $barcodeVariablesWithPageId, $pageId);


                if($pageIndex < (count($variables['pages']) - 1)) {
                    $pageBreak = new PageBreak();
                    $templateProcessor->setComplexBlock("__wiilog__pageBreak$pageId", $pageBreak);
                } else {
                    $templateProcessor->setValue("__wiilog__pageBreak$pageId", '');
                }
            }

            unset($variables["pages"]);
        }

        $this->replacePageVariables($templateProcessor, $variables, $barcodeVariables);

        /* We update phpword lib to 1.2 and save the document does not work anymore (see issue WIIS-11630)
         * To see the resolution look this issue : https://github.com/PHPOffice/PHPWord/issues/2539
        */
        $tplFile = @tempnam(sys_get_temp_dir(), "TemplateDocumentImage");
        $templateProcessor->saveAs($tplFile);
        return $tplFile;
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

    public function docxTextMapper(mixed $value): string {
        if (is_string($value)) {
            $value = htmlspecialchars($value);
            return str_replace("\n", "</w:t><w:br/><w:t>", $value);
        }

        return $value ?: '';
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

    private function replacePageVariables(TemplateProcessor $templateProcessor,
                                          array $pageVariables,
                                          array $barcodeVariables,
                                          string $pageId = "") : void {
        $availableVariables = $templateProcessor->getVariables();
        foreach ($pageVariables as $name => $value) {
            $name .= $pageId;
            if (in_array($name, $availableVariables)) {
                if (is_array($value)) {
                    $templateProcessor->cloneRow($name, count($value));

                    foreach ($value as $rowKey => $rowData) {
                        $rowNumber = $rowKey + 1;
                        $rowId = "#$rowNumber";
                        $barcodeVariablesWithRowId = Stream::from($barcodeVariables)
                            ->map(static fn(string $key) => ($key . $rowId))
                            ->toArray();
                        foreach ($rowData as $macro => $replace) {
                            $this->setTemplateProcessorValue($templateProcessor, $macro . $pageId . $rowId, $replace, $barcodeVariablesWithRowId);
                        }
                    }
                }
                else {
                    $this->setTemplateProcessorValue($templateProcessor, $name, $value, $barcodeVariables);
                }
            }
        }
    }
}
