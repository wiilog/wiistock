<?php

namespace App\Service\ShippingRequest;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\DeliveryRequest\DeliveryRequestArticleLine;
use App\Entity\DeliveryRequest\DeliveryRequestReferenceLine;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Entity\ShippingRequest\ShippingRequestExpectedLine;
use App\Entity\SubLineFieldsParam;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Exceptions\FormException;
use App\Service\FormatService;
use App\Service\FormService;
use App\Service\UserService;
use App\Service\VisibleColumnService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Keep\User;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\Attribute\Required;
class ShippingRequestExpectedLineService {

    #[Required]
    public FormService $formService;

    #[Required]
    public RouterInterface $router;

    #[Required]
    public UserService $userService;

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
                "additionalAttributes" => [
                    ["name" => "data-other-params"],
                    ["name" => "data-other-params-ignored-shipping-request", "value" => $shippingRequest?->getId()],
                    ["name" => "data-other-params-status", "value" => ReferenceArticle::STATUT_ACTIF],
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
            "information" => "<i class='dangerous wii-icon wii-icon-dangerous-goods wii-icon-25px".($line?->getReferenceArticle()->isDangerousGoods() ? "" : " d-none")."'></i>",
            "editAction" => $hasRightToEdit
                ? ("<a title='Ajouter une FDS' class='editAction ".($line ? "" : "d-none")."' href='$editUrl' target='_blank'><button class='btn btn-primary px-2'><i class='wii-icon wii-icon-edit-form'></i></button></a>")
                : "",
            "reference" => $referenceColumn,
            "label" => $labelColumn,
            "quantity" => $this->formService->macro("input", "quantity", null, true, $line?->getQuantity(), [
                "type" => "number",
                "min" => 1,
                "step" => 1,
            ]),
            "price" => $this->formService->macro("input", "price", null, true, $line?->getPrice(), [
                "type" => "number",
                "min" => 0,
            ]),
            "weight" => $this->formService->macro("input", "weight", null, true, $line?->getWeight(), [
                "type" => "number",
                "min" => 0,
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
}
