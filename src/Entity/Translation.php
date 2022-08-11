<?php

namespace App\Entity;

use App\Repository\TranslationRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity(TranslationRepository::class)]
class Translation {

    #[Id]
    #[GeneratedValue]
    #[Column(type: "integer")]
    private ?int $id = null;

    #[ManyToOne(targetEntity: Language::class, inversedBy: "translations")]
    private ?Language $language = null;

    #[ManyToOne(targetEntity: TranslationSource::class, inversedBy: "translations")]
    private ?TranslationSource $source = null;

    #[Column(type: "text")]
    private ?string $translation = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getLanguage(): ?Language {
        return $this->language;
    }

    public function setLanguage(?Language $language): self {
        if($this->language && $this->language !== $language) {
            $this->language->removeTranslation($this);
        }
        $this->language = $language;
        $language?->addTranslation($this);

        return $this;
    }

    public function getSource(): ?TranslationSource {
        return $this->source;
    }

    public function setSource(?TranslationSource $source): self {
        if($this->source && $this->source !== $source) {
            $this->source->removeTranslation($this);
        }
        $this->source = $source;
        $source?->addTranslation($this);

        return $this;
    }

    public function getTranslation(): ?string {
        return $this->translation;
    }

    public function setTranslation(?string $translation): self {
        $this->translation = $translation;
        return $this;
    }

}
