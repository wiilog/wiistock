# Wiistock

## Installing

Créer le fichier `config/generated.yaml` avec le contenu suivant
```json
{"parameters": {"session_lifetime": 1440}}
```
###Postal Code
You have to add a value to the env variable : __.env.local__
For example, for Lyon city : (a string variable mandatory)
```
POSTAL_CODE_METROPOLIS='69003,69029,69033,69034,69040,69044,69046,69063,69068,69069,69071,69072,69081,69085,69087, 69088, 69089, 69091,69096,69100,69116,69117,69123,69127,69142,69143,69149,69152,69153,69163,69168,69191,69194,69199,69202,69204,69205,69207,69233,69244,69250,69256,69259,69260,69266,69271,69273,69275,69276,69278,69279,69282,69283,69284,69286,69290,69292,69293,69296'
```

## Security checker
The Local PHP Security Checker is a command line tool that checks if your PHP application depends on PHP packages with known security vulnerabilities.
It uses the Security Advisories Database behind the scenes. Download a binary from
the [releases page on Github](https://github.com/fabpot/local-php-security-checker/releases), rename it to local-php-security-checker and make it
executable. From the application directory, run the binary without argument or flags to check for known vulnerabilities :

```
local-php-security-checker
```

## Best practices

### Relations

#### OneToOne

```php
class Entity {

    #[OneToOne(targetEntity: Example::class, inversedBy: "entity")]
    private ?Example $example = null;

    public function getExample(): ?Example {
        return $this->example;
    }
    
    public function setExample(?Example $example): self {
        if($this->example && $this->example->getEntity() !== $this) {
            $oldExample = $this->example;
            $this->example = null;
            $oldExample->setEntity(null);
        }
        $this->example = $example;
        if($this->example && $this->example->getEntity() !== $this) {
            $this->example->setEntity($this);
        }
    
        return $this;
    }
    
}
```

#### ManyToOne

```php
class Entity {

    #[ManyToOne(targetEntity: Example::class, inversedBy: "entities")]
    private ?Example $example = null;

    public function getExample(): ?Example {
        return $this->example;
    }
    
    public function setExample(?Example $example): self {
        if($this->example && $this->example !== $example) {
            $this->example->removeEntity($this);
        }
        $this->example = $example;
        $example?->addEntity($this);
    
        return $this;
    }
    
}
```

#### OneToMany

```php
class Entity {

    #[OneToMany(targetEntity: Example::class, mappedBy: "entity")]
    private Collection $examples;
    
    public function __construct() {
        $this->examples = new ArrayCollection();
    }

    /**
     * @return Collection<int, Example>
     */
    public function getExamples(): Collection {
        return $this->examples;
    }

    public function addExample(Example $example): self {
        if (!$this->examples->contains($example)) {
            $this->examples[] = $example;
            $example->setEntity($this);
        }

        return $this;
    }

    public function removeExample(Example $example): self {
        if ($this->examples->removeElement($example)) {
            if ($example->getEntity() === $this) {
                $example->setEntity(null);
            }
        }

        return $this;
    }

    public function setExamples(?array $examples): self {
        foreach($this->getExamples()->toArray() as $example) {
            $this->removeExample($example);
        }

        $this->examples = new ArrayCollection();
        foreach($examples as $example) {
            $this->addExample($example);
        }
        
        return $this;
    }
}
```

### ManyToMany

```php
class Entity {

    #[ManyToMany(targetEntity: Example::class, mappedBy: "entities")]
    private Collection $examples;

    /**
     * @return Collection|Example[]
     */
    public function getExamples(): Collection {
        return $this->examples;
    }

    public function addExample(Example $example): self {
        if (!$this->examples->contains($example)) {
            $this->examples[] = $example;
            $example->addEntity($this);
        }

        return $this;
    }

    public function removeExample(Example $example): self {
        if ($this->examples->removeElement($example)) {
            $example->removeEntity($this);
        }

        return $this;
    }

    public function setExamples(?array $examples): self {
        foreach($this->getExamples()->toArray() as $example) {
            $this->removeExample($example);
        }

        $this->examples = new ArrayCollection();
        foreach($examples as $example) {
            $this->addExample($example);
        }
        
        return $this;
    }
    
}
```

## Selects in datatables
Select2 should have the attribute `data-parent="body"` when used in datatables
