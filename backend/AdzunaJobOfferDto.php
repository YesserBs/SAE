<?php

declare(strict_types=1);

class AdzunaJobOfferDto
{
    public function __construct(
        public string $externalId,
        public string $source,

        public string $title,
        public string $description,
        public string $redirectUrl,

        public ?string $companyName,

        public ?string $locationName,
        public ?string $country,
        public ?string $region,
        public ?string $city,
        public ?float $latitude,
        public ?float $longitude,

        public ?string $categoryLabel,
        public ?string $categoryTag,

        public ?string $contractType,
        public ?string $contractTime,

        public ?float $salaryMin,
        public ?float $salaryMax,
        public bool $salaryIsPredicted,

        public ?string $sourceCreatedAt,
        public bool $isActive = true
    ) {}
}