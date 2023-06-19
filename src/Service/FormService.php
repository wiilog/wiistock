<?php


namespace App\Service;


use InvalidArgumentException;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;

class FormService {

    #[Required]
    public Twig_Environment $templating;

    public function validateDate($value, $errorMessage = ''): void {
        $valueStr = $value ?: '';
        preg_match('/(\d{2})\/(\d{2})\/(\d+)$/', $valueStr, $matches);
        $dayIndex = 1;
        $monthIndex = 2;
        $yearIndex = 3;
        if (empty($matches)
            || count($matches) !== 4
            || !checkdate($matches[$monthIndex], $matches[$dayIndex], $matches[$yearIndex])
            || strlen($matches[$yearIndex]) > 4) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

    /**
     * See template/form.html.twig for macro signature
     */
    public function macro(string $macro, ...$params): string {
        return $this->templating->render('form.html.twig', [
            "macroName" => $macro,
            "macroParams" => $params
        ]);
    }


    public function editableAddRow(array $form): array {
        return Stream::from($form)
            ->keymap(fn($_, $key) => [$key, ""])
            ->set("createRow", true)
            ->set("actions", "
                <span class='d-flex justify-content-start align-items-center add-row'>
                    <span class='wii-icon wii-icon-plus'></span>
                </span>
            ")
            ->toArray();
    }
}
