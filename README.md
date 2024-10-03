# Wiistock

## Installing
Créer le fichier `config/generated.yaml` avec le contenu suivant
```json
{"parameters": {"session_lifetime": 1440}}
```

## Run PHPUnit
* Create phpunit.xml
```sh
cp phpunit.xml.dist phpunit.xml
```
* Run command
```sh
php bin/phpunit
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

    public function setExamples(?iterable $examples): self {
        foreach($this->getExamples()->toArray() as $example) {
            $this->removeExample($example);
        }

        $this->examples = new ArrayCollection();
        foreach($examples ?? [] as $example) {
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

    public function setExamples(?iterable $examples): self {
        foreach($this->getExamples()->toArray() as $example) {
            $this->removeExample($example);
        }

        $this->examples = new ArrayCollection();
        foreach($examples ?? [] as $example) {
            $this->addExample($example);
        }
        
        return $this;
    }
    
}
```

## Selects in datatables
Select2 should have the attribute `data-parent="body"` when used in datatables


## Best practices for Cypress
In order to have a clean database for each test, we need to reset the database before each test. You need to include the following code in your tests files.

```js
describe('Setup the environment', () => {
    it('Reset the db', () => {
        cy.startingCypressEnvironnement(/* url to sql file to curl */)
    });
})
```
