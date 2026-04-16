<?php

namespace Database\Factories;

use App\Models\RenterContractDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RenterContractDocument>
 */
class RenterContractDocumentFactory extends Factory
{
    protected $model = RenterContractDocument::class;

    public function definition(): array
    {
        return [
            'renter_id' => \App\Models\Renter::factory(),
            'description' => fake()->words(3, true),
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_by_id' => null,
            'stored_path' => '', // filled in configure() when missing
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RenterContractDocument $document): void {
            if ($document->stored_path !== '' && $document->stored_path !== null) {
                return;
            }
            $document->stored_path = $document->renter_id.'/'.Str::uuid().'.pdf';
        });
    }
}
