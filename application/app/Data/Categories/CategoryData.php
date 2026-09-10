<?php

namespace App\Data\Categories;

class CategoryData
{
    public function __construct(
        public string $name,
        public string $description,
    )
    {}
}
