<?php

namespace App\Actions\Indicators;

use App\Actions\Users\SaveUser;
use App\Enums\IndicatorStatus;
use App\Enums\Role;
use App\Models\RegionalIndicator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Cria ou atualiza um indicador regional. Quando "has_access" está ligado,
 * cria (ou reativa) o usuário com perfil indicador; desligado, desativa o acesso.
 */
final class SaveIndicator
{
    public function __construct(private SaveUser $saveUser) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{indicator: RegionalIndicator, reset_link_sent: bool}
     */
    public function handle(?RegionalIndicator $indicator, array $data, bool $hasAccess, ?UploadedFile $photo = null, ?User $actor = null): array
    {
        if ($hasAccess && empty($data['email'])) {
            throw ValidationException::withMessages(['form.email' => 'Para dar acesso ao sistema, informe o e-mail do indicador.']);
        }

        $oldPhoto = $indicator?->photo_path;
        $newPhoto = $photo?->store('photos/indicators', 'local');
        $resetSent = false;

        try {
            $indicator = DB::transaction(function () use ($indicator, $data, $hasAccess, $newPhoto, $actor, &$resetSent) {
                $indicator ??= new RegionalIndicator;
                $indicator->fill($data);

                if ($newPhoto) {
                    $indicator->photo_path = $newPhoto;
                }

                $active = $indicator->status === IndicatorStatus::Active;

                if ($hasAccess) {
                    $result = $this->saveUser->handle($indicator->user, [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'phone' => $data['whatsapp'] ?? $data['phone'] ?? null,
                        'active' => $active,
                    ], Role::Indicator, $actor);
                    $indicator->user_id = $result['user']->id;
                    $resetSent = $result['reset_link_sent'];
                } elseif ($indicator->user_id) {
                    $indicator->user()->update(['active' => false]);
                }

                $indicator->save();

                return $indicator;
            });
        } catch (\Throwable $e) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            }
            throw $e;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('local')->delete($oldPhoto);
        }

        return ['indicator' => $indicator, 'reset_link_sent' => $resetSent];
    }
}
