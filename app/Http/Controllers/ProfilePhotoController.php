<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Core\Pide\Providers\PideProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProfilePhotoController extends Controller
{
    public function __invoke(Request $request, PideProvider $provider): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        // Never accept a document or another user's ID from the client.
        $dni = trim((string) $user->usrio?->nu_docu);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if (!preg_match('/^\d{8}$/D', $dni)) {
            return response('', 204, $headers);
        }

        // Private filesystem cache; no public files named with identity documents.
        $cache = Cache::store('file');
        $key = 'profile-photo.v1.'.hash('sha256', $user->getAuthIdentifier().'|'.$dni);
        $photo = $cache->get($key);
        if ($photo === null) {
            $photo = [];
            try {
                // Use the provider directly: PideCore may persist citizen data.
                $encoded = $provider->buscarDni($dni)?->foto;
                if (is_string($encoded) && strlen($encoded) <= 2_000_000) {
                    $encoded = preg_replace('/^data:image\/(?:jpeg|png|gif);base64,/i', '', $encoded);
                    $bytes = base64_decode($encoded, true);
                    $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
                    if ($info && in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif'], true)
                        && $info[0] > 0 && $info[1] > 0 && $info[0] <= 4096 && $info[1] <= 4096) {
                        $photo = ['bytes' => $bytes, 'mime' => $info['mime']];
                    }
                }
            } catch (Throwable) {
                // Do not expose provider URLs, credentials or personal data in errors.
            }
            $cache->put($key, $photo, $photo ? now()->addHours(12) : now()->addMinutes(15));
        }

        return empty($photo['bytes'])
            ? response('', 204, $headers)
            : response($photo['bytes'], 200, $headers + ['Content-Type' => $photo['mime']]);
    }
}
