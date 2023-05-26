<?php

namespace App\Service\ShippingRequest;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Exceptions\FormException;
use App\Service\FormService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use Twig\Environment as Twig_Environment;

class ShippingRequestExpectedLineService {

    #[Required]
    public FormService $formService;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public UserService $userService;

    #[Required]
    public Twig_Environment $templating;

    public function editatableLineForm(ShippingRequest|null             $shippingRequest,
                                       ShippingRequestExpectedLine|null $line = null): array {
        if (isset($line)) {
            $referenceColumn = ($line->getReferenceArticle()?->getReference() ?: '') .
                $this->formService->macro('hidden', 'lineId', $line->getId());
            $labelColumn = $line->getReferenceArticle()?->getLibelle();
        }
        else {
            $referenceColumn = Stream::from([
                $this->formService->macro("select", "referenceArticle", null, true, [
                    "type" => "reference",
                    "minLength" => 0,
                    "additionalAttributes" => [
                        ["name" => "data-field-label", 'value' => "Référence"],
                        ["name" => "data-other-params"],
                        ["name" => "data-other-params-ignored-shipping-request", "value" => $shippingRequest?->getId()],
                        ["name" => "data-other-params-status", "value" => ReferenceArticle::STATUT_ACTIF],
                        ["name" => "data-other-params-new-item", "value" => 1],
                    ],
                ]),
                $this->formService->macro("hidden", "lineId")
            ])->join('');
            $labelColumn = '<span class="label-wrapper"></span>';
        }

        $actionId = 'data-id="' . ($line?->getId() ?: '') . '"';
        $editUrl = $line ? $this->router->generate('reference_article_edit_page', ['reference' => $line->getReferenceArticle()?->getId()]) : '';
        $hasRightToEdit = $this->userService->hasRightFunction(Menu::STOCK, Action::EDIT);

        return [
            "actions" => "
                <span class='d-flex justify-content-start align-items-center delete-row'
                      $actionId>
                    <span class='wii-icon wii-icon-trash'></span>
                </span>
            ",
            "information" => "<i title='Matière dangereuse' class='dangerous wii-icon wii-icon-dangerous-goods wii-icon-20px".($line?->getReferenceArticle()->isDangerousGoods() ? "" : " d-none")."'></i>",
            "editAction" => $hasRightToEdit
                ? (
                    "<a title='Ajouter une FDS'
                        class='editAction btn btn-primary px-2 " . ($line ? "" : "d-none") . "'
                        href='$editUrl'
                        target='_blank'>
                            <i class='wii-icon wii-icon-edit-form'></i>
                     </a>"
                )
                : "",
            "reference" => $referenceColumn,
            "label" => $labelColumn,
            "quantity" => $this->formService->macro("input", "quantity", null, true, $line?->getQuantity(), [
                "type" => "number",
                "min" => 1,
                "step" => 1,
                "additionalAttributes" => [
                    ["name" => "data-field-label", 'value' => "Quantité"],
                ],
            ]),
            "price" => $this->formService->macro("input", "price", null, true, $line?->getUnitPrice(), [
                "type" => "number",
                "min" => 0,
                "additionalAttributes" => [
                    ["name" => "data-field-label", 'value' => "Prix unitaire"],
                ],
            ]),
            "weight" => $this->formService->macro("input", "weight", null, true, $line?->getUnitWeight(), [
                "type" => "number",
                "min" => 0,
                "additionalAttributes" => [
                    ["name" => "data-field-label", 'value' => "Poids net"],
                ],
            ]),
            "total" => '<span class="total-wrapper">' . $line?->getTotalPrice() . '</span>',
        ];
    }


    public function persist(EntityManagerInterface $entityManager, array $options): ShippingRequestExpectedLine {
        $referenceArticle = $options['referenceArticle'] ?? null;
        $request = $options['request'] ?? null;
        $quantity = $options['quantity'] ?? null;
        $price = $options['price'] ?? null;
        $weight = $options['weight'] ?? null;

        if (!$referenceArticle) {
            throw new FormException('Formulaire invalide');
        }

        if (!$request) {
            throw new FormException('Formulaire invalide');
        }

        $line = (new ShippingRequestExpectedLine())
            ->setReferenceArticle($referenceArticle)
            ->setRequest($request)
            ->setQuantity($quantity)
            ->setUnitPrice($price)
            ->setUnitWeight($weight);

        $this->updateTotalPrice($line);
        $this->updateTotalWeight($line);

        $entityManager->persist($line);
        return $line;
    }

    public function getDataForDetailsTable($shippingRequest): array {
        return Stream::from($shippingRequest->getExpectedLines())
            ->map(function (ShippingRequestExpectedLine $expectedLine) {
                $reference = $expectedLine->getReferenceArticle();
                $actions = $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                    'actions' => [
                        [
                            'hasRight' => $this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE),
                            'title' => 'Voir la référence',
                            'icon' => 'fa fa-eye',
                            'attributes' => [
                                'onclick' => "window.location.href = '{$this->router->generate('reference_article_show_page', ['id' => $reference->getId()])}'",
                            ]
                        ],[
                            'hasRight' => $this->userService->hasRightFunction(Menu::STOCK, Action::EDIT),
                            'title' => 'Modifier la référence',
                            'icon' => 'fa fa-pen',
                            'attributes' => [
                                'onclick' => "window.location.href = '{$this->router->generate('reference_article_edit_page', ['reference' => $reference->getId()])}'",
                            ]
                        ],

                    ],
                ]);
                return [
                    'actions' => $actions,
                    'reference' => '<div class="d-flex align-items-center">' . $reference->getReference() . ($reference->isDangerousGoods() ? "<i title='Matière dangereuse' class='dangerous wii-icon wii-icon-dangerous-goods wii-icon-20px ml-2'></i>" : '') . '</div>',
                    'label' => $reference->getLibelle(),
                    'quantity' => $expectedLine->getQuantity(),
                    'price' => $expectedLine->getUnitPrice(),
                    'weight' => $expectedLine->getUnitWeight(),
                    'total' => $expectedLine->getTotalPrice(),
                ];
            })
            ->toArray();
    }

    public function updateTotalWeight(ShippingRequestExpectedLine $expectedLine): void {
        $expectedLine->setTotalWeight(
            $expectedLine->getQuantity() && $expectedLine->getUnitWeight() ? $expectedLine->getQuantity() * $expectedLine->getUnitWeight() : 0
        );
    }

    public function updateTotalPrice(ShippingRequestExpectedLine $expectedLine): void {
        $expectedLine->setTotalPrice(
            $expectedLine->getQuantity() && $expectedLine->getUnitPrice() ? $expectedLine->getQuantity() * $expectedLine->getUnitPrice() : 0
        );
    }
}
