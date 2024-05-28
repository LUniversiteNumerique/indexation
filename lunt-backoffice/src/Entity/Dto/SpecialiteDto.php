<?php

namespace App\Entity\Dto;


use JMS\Serializer\Annotation\{SerializedName,XmlList};

class PropertyDto
{
    public function __construct(
        #[SerializedName('key')]
        public string $key,
        #[SerializedName('value')]
        public string $value,
    ) {}
}

class SpecialiteDto
{
    public function __construct(
        /**
         * @var PropertyDto
         * @XmlList(inline=true, entry="property")
         */
        public array $properties = [],

        /**
         * @var SpecialiteDto[]
         * @XmlList(inline=true, entry="item")
         */
        public array $children = [],
    ){}
}

class TableDto
{
    public function __construct(
        /**
         * @var SpecialiteDto[]
         * @XmlList(inline=true, entry="item")
         */
        public array $items = []
    )
    {}
}