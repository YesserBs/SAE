<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AdzunaJobOfferDto.php';

class AdzunaJobOfferRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
    }

    public function save(AdzunaJobOfferDto $dto): void
    {
        if ($dto->externalId === '') {
            throw new Exception("Impossible d'enregistrer une offre sans external_id.");
        }

        if ($dto->redirectUrl === '') {
            throw new Exception("Impossible d'enregistrer une offre sans redirect_url.");
        }

        $sql = "
            INSERT INTO adzuna_job_offer (
                external_id,
                source,
                title,
                description,
                redirect_url,
                company_name,
                location_name,
                country,
                region,
                city,
                latitude,
                longitude,
                category_label,
                category_tag,
                contract_type,
                contract_time,
                salary_min,
                salary_max,
                salary_is_predicted,
                source_created_at,
                is_active
            ) VALUES (
                :external_id,
                :source,
                :title,
                :description,
                :redirect_url,
                :company_name,
                :location_name,
                :country,
                :region,
                :city,
                :latitude,
                :longitude,
                :category_label,
                :category_tag,
                :contract_type,
                :contract_time,
                :salary_min,
                :salary_max,
                :salary_is_predicted,
                :source_created_at,
                :is_active
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                redirect_url = VALUES(redirect_url),
                company_name = VALUES(company_name),
                location_name = VALUES(location_name),
                country = VALUES(country),
                region = VALUES(region),
                city = VALUES(city),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                category_label = VALUES(category_label),
                category_tag = VALUES(category_tag),
                contract_type = VALUES(contract_type),
                contract_time = VALUES(contract_time),
                salary_min = VALUES(salary_min),
                salary_max = VALUES(salary_max),
                salary_is_predicted = VALUES(salary_is_predicted),
                source_created_at = VALUES(source_created_at),
                is_active = VALUES(is_active)
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'external_id' => $dto->externalId,
            'source' => $dto->source,

            'title' => $dto->title,
            'description' => $dto->description,
            'redirect_url' => $dto->redirectUrl,

            'company_name' => $dto->companyName,

            'location_name' => $dto->locationName,
            'country' => $dto->country,
            'region' => $dto->region,
            'city' => $dto->city,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,

            'category_label' => $dto->categoryLabel,
            'category_tag' => $dto->categoryTag,

            'contract_type' => $dto->contractType,
            'contract_time' => $dto->contractTime,

            'salary_min' => $dto->salaryMin,
            'salary_max' => $dto->salaryMax,
            'salary_is_predicted' => $dto->salaryIsPredicted ? 1 : 0,

            'source_created_at' => $dto->sourceCreatedAt,
            'is_active' => $dto->isActive ? 1 : 0,
        ]);
    }
}