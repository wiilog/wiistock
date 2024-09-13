<?php

namespace App\Entity\Traits;

use RuntimeException;

trait LitePropertiesSetterTrait {

    public function setProperty(string $attribute, $value = null): self {
        if(property_exists($this, $attribute)) {
            $this->{$attribute} = $value;
        } else {
            throw new RuntimeException("Property {$attribute} does not exists for class " . self::class);
        }

        return $this;
    }

    public function setProperties(array $properties): self {
        foreach($properties as $name => $value) {
            $this->setProperty($name, $value);
        }

        return $this;
    }

}
