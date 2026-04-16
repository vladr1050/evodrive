<?php

namespace App\Http\Controllers\Admin;

use App\Models\RenterContractDocument;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenterContractDocumentDownloadController
{
    public function __invoke(RenterContractDocument $renterContractDocument): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        abort_unless($user->canAccessResource('rental_vehicles'), 403);

        abort_unless(
            RenterContractDocument::isStoredPathAllowedForRenter(
                $renterContractDocument->stored_path,
                (int) $renterContractDocument->renter_id
            ),
            404
        );

        $disk = Storage::disk('renter_contracts');
        abort_unless($disk->exists($renterContractDocument->stored_path), 404);

        return $disk->download(
            $renterContractDocument->stored_path,
            $renterContractDocument->safeDownloadFilename(),
            ['Content-Type' => $renterContractDocument->mime_type ?: 'application/octet-stream']
        );
    }
}
