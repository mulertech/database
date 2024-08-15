<?php

namespace MulerTech\Database\NonRelational\DocumentStore\FileContent;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

class AttributeReader
{
    /**
     * @throws ReflectionException
     */
    public function getClassAttributes(string $class): array
    {
        return (new ReflectionClass($class))->getAttributes();
    }

    /**
     * @param string $class
     * @param class-string $attributeClassName
     * @return ReflectionAttribute|null
     * @throws ReflectionException
     */
    public function getClassAttributeNamed(string $class, string $attributeClassName): ?ReflectionAttribute
    {
        return (new ReflectionClass($class))->getAttributes($attributeClassName)[0] ?? null;
    }

    /**
     * @param string $class
     * @param class-string $attributeClassName
     * @return object|null
     * @throws ReflectionException
     */
    public function getInstanceOfClassAttributeNamed(string $class, string $attributeClassName): ?object
    {
        return $this->getClassAttributeNamed($class, $attributeClassName)?->newInstance();
    }

    /**
     * @throws ReflectionException
     */
    public function getPropertiesAttributes(string $class): array
    {
        return (new ReflectionClass($class))->getProperties();
    }

    /**
     * @param string $class
     * @param string $attributeClassName
     * @return array
     * @throws ReflectionException
     */
    public function getInstanceOfPropertiesAttributesNamed(string $class, string $attributeClassName): array
    {
        $properties = $this->getPropertiesAttributes($class);

        $result = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes($attributeClassName);


            if (!isset($attributes[0])) {
//                $result[$property->getName()] = null;
                continue;
            }

            $result[$property->getName()] = $attributes[0]->newInstance();
        }
//        var_dump($result);

        return $result;
    }
}