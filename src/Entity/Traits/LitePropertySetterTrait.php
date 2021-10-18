<?php

namespace App\Entity\Traits;


trait LitePropertySetterTrait {
    public function setProperties(array $properties): void {
        foreach ($properties as $name => $value) {
            if (property_exists($this, $name)) {
                $this->{$name} = $value;
            }
            else {
                throw new \Exception("Property {$name} does not exists for class " . self::class);
            }
        }
    }
}
