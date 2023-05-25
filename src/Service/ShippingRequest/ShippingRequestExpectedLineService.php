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
            $referenceColumn = $this->formService->macro("select", "referenceArticle", null, true, [
                "type" => "reference",
                "minLength" => 0,
                "error" => "global",
                "additionalAttributes" => [
                    ["name" => "data-field-label", 'value' => "Référence"],
                    ["name" => "data-other-params"],
                    ["name" => "data-other-params-ignored-shipping-request", "value" => $shippingRequest?->getId()],
                    ["name" => "data-other-params-status", "value" => ReferenceArticle::STATUT_ACTIF],
                    ["name" => "data-other-params-new-item", "value" => 1],
                ],
            ]);
            $labelColumn = '<span class="label-wrapper"></span>';
        }

        $total = $line?->getQuantity() && $line?->getPrice()
            ? ($line->getQuantity() * $line->getPrice())
            : '';

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
            "information" => "<i class='dangerous wii-icon wii-icon-dangerous-goods wii-icon-20px".($line?->getReferenceArticle()->isDangerousGoods() ? "" : " d-none")."'></i>",
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
                "error" => "global",
                "additionalAttributes" => [
                    ["name" => "data-field-label", 'value' => "Quantité"],
                ],
            ]),
            "price" => $this->formService->macro("input", "price", null, true, $line?->getPrice(), [
                "type" => "number",
                "min" => 0,
                "error" => "global",
                "additionalAttributes" => [
                    ["name" => "data-field-label", 'value' => "Prix unitaire"],
                ],
            ]),
            "weight" => $this->formService->macro("input", "weight", null, true, $line?->getWeight(), [
                "type" => "number",
                "min" => 0,
                "error" => "global",
                "additionalAttributes" => [
                    ["name" => "data-field-label", 'value' => "Poids net"],
                ],
            ]),
            "total" => '<span class="total-wrapper">' . $total . '</span>',
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
            ->setPrice($price)
            ->setWeight($weight);

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
                        ],

                    ],
                ]);
                return [
                    'actions' => $actions,
                    'reference' => $reference->getReference() . ($reference->isDangerousGoods() ? "<i class='dangerous wii-icon wii-icon-dangerous-goods wii-icon-20px'></i>" : ''),
                    'label' => $reference->getLibelle(),
                    'quantity' => $expectedLine->getQuantity(),
                    'price' => $expectedLine->getPrice(),
                    'weight' => $expectedLine->getWeight(),
                    'total' => $expectedLine->getQuantity() * $expectedLine->getPrice(),
                ];
            })
            ->toArray();
    }
}
