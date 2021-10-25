<?php

namespace App\Entity\Traits;

use RuntimeException;

trait LitePropertiesSetterTrait {

    public function setProperty(string $attribute, $value = null): void {
        if(property_exists($this, $attribute)) {
            $this->{$name} = $value;
        } else {
            throw new RuntimeException("Property {$attribute} does not exists for class " . self::class);
        }
    }

    public function setProperties(array $properties): void {
        foreach($properties as $name => $value) {
            $this->setProperty($name, $value);
        }
    }

}
