<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FieldsParamRepository")
 */
class FieldsParam
{
    const ENTITY_CODE_RECEPTION = 'réception';

    const FIELD_CODE_FOURNISSEUR = 'fournisseur';
    const FIELD_CODE_NUM_COMMANDE = 'numCommande';
    const FIELD_CODE_DATE_ATTENDUE = 'dateAttendue';
    const FIELD_CODE_DATE_COMMANDE = 'dateCommande';
    const FIELD_CODE_COMMENTAIRE = 'commentaire';
    const FIELD_CODE_UTILISATEUR = 'utilisateur';
    const FIELD_CODE_NUM_RECEPTION = 'numeroReception';
    const FIELD_CODE_TRANSPORTEUR = 'transporteur';
    const FIELD_CODE_EMPLACEMENT = 'emplacement';
    const FIELD_CODE_ANOMALIE = 'anomalie';

    const FIELD_LABEL_FOURNISSEUR = 'fournisseur';
    const FIELD_LABEL_NUM_COMMANDE = 'numéro de commande';
    const FIELD_LABEL_DATE_ATTENDUE = 'date attendue';
    const FIELD_LABEL_DATE_COMMANDE = 'date commande';
    const FIELD_LABEL_COMMENTAIRE = 'commentaire';
    const FIELD_LABEL_UTILISATEUR = 'utilisateur';
    const FIELD_LABEL_NUM_RECEPTION = 'numéro de réception';
	const FIELD_LABEL_TRANSPORTEUR = 'transporteur';
	const FIELD_LABEL_EMPLACEMENT = 'emplacement';
	const FIELD_LABEL_ANOMALIE = 'anomalie';

	const ENTITY_CODE_ARRIVAGE = 'arrivage';

    const FIELD_CODE_PROVIDER_ARRIVAGE = 'fournisseur';
    const FIELD_CODE_CARRIER_ARRIVAGE = 'transporteur';
    const FIELD_CODE_CHAUFFEUR_ARRIVAGE = 'chauffeur';
    const FIELD_CODE_NUMERO_TRACKING_ARRIVAGE = 'noTracking';
    const FIELD_CODE_NUM_COMMANDE_ARRIVAGE = 'numeroCommandeList';
    const FIELD_CODE_TARGET_ARRIVAGE = 'destinataire';
    const FIELD_CODE_BUYERS_ARRIVAGE = 'acheteurs';
    const FIELD_CODE_PRINT_ARRIVAGE = 'imprimerArrivage';
    const FIELD_CODE_COMMENTAIRE_ARRIVAGE = 'commentaire';
    const FIELD_CODE_PJ_ARRIVAGE = 'pj';

    const FIELD_LABEL_PROVIDER_ARRIVAGE = 'fournisseur';
    const FIELD_LABEL_CARRIER_ARRIVAGE = 'transporteur';
    const FIELD_LABEL_CHAUFFEUR_ARRIVAGE = 'chauffeur';
    const FIELD_LABEL_NUMERO_TRACKING_ARRIVAGE = 'numéro tracking transporteur';
    const FIELD_LABEL_NUM_BL_ARRIVAGE = 'n° commande / BL';
    const FIELD_LABEL_TARGET_ARRIVAGE = 'destinataire';
    const FIELD_LABEL_BUYERS_ARRIVAGE = 'acheteurs';
    const FIELD_LABEL_PRINT_ARRIVAGE = 'imprimer arrivage';
    const FIELD_LABEL_COMMENTAIRE_ARRIVAGE = 'commentaire';
    const FIELD_LABEL_PJ_ARRIVAGE = 'Pièces jointes';

	/**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $entityCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fieldCode;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fieldLabel;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mustToCreate;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $mustToModify;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $displayed;

    /**
     * @ORM\Column(type="boolean", nullable=true, options={"default": false})
     */
    private $fieldRequiredHidden;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityCode(): ?string
    {
        return $this->entityCode;
    }

    public function setEntityCode(string $entityCode): self
    {
        $this->entityCode = $entityCode;

        return $this;
    }

    public function getFieldCode(): ?string
    {
        return $this->fieldCode;
    }

    public function setFieldCode(string $fieldCode): self
    {
        $this->fieldCode = $fieldCode;

        return $this;
    }

    public function getMustToCreate(): ?bool
    {
        return $this->mustToCreate;
    }

    public function setMustToCreate(?bool $mustToCreate): self
    {
        $this->mustToCreate = $mustToCreate;

        return $this;
    }

    public function getMustToModify(): ?bool
    {
        return $this->mustToModify;
    }

    public function setMustToModify(?bool $mustToModify): self
    {
        $this->mustToModify = $mustToModify;

        return $this;
    }

    public function getFieldLabel(): ?string
    {
        return $this->fieldLabel;
    }

    public function setFieldLabel(string $fieldLabel): self
    {
        $this->fieldLabel = $fieldLabel;

        return $this;
    }

    public function getDisplayed(): ?bool
    {
        return $this->displayed;
    }

    public function setDisplayed(?bool $displayed): self
    {
        $this->displayed = $displayed;

        return $this;
    }

    public function getFieldRequiredHidden(): ?bool
    {
        return $this->fieldRequiredHidden;
    }

    public function setFieldRequiredHidden(?bool $fieldRequiredHidden): self
    {
        $this->fieldRequiredHidden = $fieldRequiredHidden;

        return $this;
    }
}
