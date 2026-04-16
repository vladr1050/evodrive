<?php

namespace Tests\Feature;

use App\Models\Renter;
use App\Models\RenterContractDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RenterContractDocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_download_contract_document(): void
    {
        $renter = Renter::factory()->create();
        $doc = RenterContractDocument::factory()->for($renter)->create();

        $this->get(route('admin.renter-contract-documents.download', $doc))
            ->assertRedirect();
    }

    public function test_admin_with_rental_access_can_download_contract_document(): void
    {
        Storage::fake('renter_contracts');

        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $renter = Renter::factory()->create();
        $relativePath = $renter->id.'/test-doc.pdf';
        Storage::disk('renter_contracts')->put($relativePath, '%PDF-1.4 fake');

        $doc = RenterContractDocument::factory()->for($renter)->create([
            'stored_path' => $relativePath,
            'mime_type' => 'application/pdf',
            'description' => 'Lease agreement',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.renter-contract-documents.download', $doc));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('lease-agreement.pdf', strtolower($response->headers->get('content-disposition')));
    }

    public function test_manager_without_rental_permission_cannot_download(): void
    {
        Storage::fake('renter_contracts');

        $manager = User::factory()->create([
            'role' => 'manager',
            'allowed_resources' => ['leads'],
            'password' => Hash::make('password'),
        ]);

        $renter = Renter::factory()->create();
        $relativePath = $renter->id.'/x.pdf';
        Storage::disk('renter_contracts')->put($relativePath, 'x');

        $doc = RenterContractDocument::factory()->for($renter)->create([
            'stored_path' => $relativePath,
        ]);

        $this->actingAs($manager)
            ->get(route('admin.renter-contract-documents.download', $doc))
            ->assertForbidden();
    }
}
