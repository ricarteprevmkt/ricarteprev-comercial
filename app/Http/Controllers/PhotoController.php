<?php

namespace App\Http\Controllers;

use App\Models\Executive;
use App\Models\RegionalIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fotos ficam no disco privado e só são entregues a quem pode ver o cadastro.
 */
class PhotoController extends Controller
{
    public function executive(Request $request, Executive $executive): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $executive->user_id === $user?->id || Gate::any(['executives.view', 'executives.manage', 'agenda.view_all', 'funnel.view_all']),
            403,
        );

        return $this->stream($executive->photo_path);
    }

    public function indicator(Request $request, RegionalIndicator $indicator): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $indicator->user_id === $user?->id || Gate::any(['indicators.manage', 'referrals.view_all']),
            403,
        );

        return $this->stream($indicator->photo_path);
    }

    private function stream(?string $path): StreamedResponse
    {
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
