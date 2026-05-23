<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pets\Pet;
use App\Services\QrCodeSvgService;
use Illuminate\Http\Response;

class PetQrCodeController extends Controller
{
    public function show(Pet $pet, QrCodeSvgService $qrCodes): Response
    {
        $this->authorize('view', $pet);

        return response($qrCodes->svg(route('pets.show', $pet)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function download(Pet $pet, QrCodeSvgService $qrCodes): Response
    {
        $this->authorize('view', $pet);

        return response($qrCodes->svg(route('pets.show', $pet)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$pet->getRouteKey().'-qr.svg"',
        ]);
    }
}
