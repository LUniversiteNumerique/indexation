<?php

namespace App\Service\Converter;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class PrefixNameConverter extends CamelCaseToSnakeCaseNameConverter
{
    private int $prefixSize;
    public function __construct(private readonly string $prefixName,
                                private readonly ?array $attributes = null,
                                private readonly bool   $lowerCamelCase = true,) {
        parent::__construct($this->attributes, $this->lowerCamelCase);
        $this->prefixSize = strlen($this->prefixName);
    }

    public function normalize(string $propertyName): string
    {
        return $this->prefixName.parent::normalize($propertyName);
    }

    public function denormalize(string $propertyName): string
    {
        return parent::denormalize(str_starts_with($propertyName, $this->prefixName) ? substr($propertyName, $this->prefixSize) : $propertyName);
    }
}