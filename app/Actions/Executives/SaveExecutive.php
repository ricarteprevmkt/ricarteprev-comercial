<?php

namespace App\Actions\Executives;

use App\Actions\Users\SaveUser;
use App\Enums\Role;
use App\Models\Executive;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cria ou atualiza um executivo e o usuário de acesso vinculado (perfil executivo).
 */
final class SaveExecutive
{
    public function __construct(private SaveUser $saveUser) {}

    /**
     * @param  array{name: string, email: string, phone: string|null, password?: string|null, cpf: string|null,
     *     city_id: int|null, region_id: int|null, hired_at: string|null, status: string,
     *     monthly_visits_target: int|null, monthly_contracts_target: int|null,
     *     cost_allowance: string|null, compensation_notes: string|null}  $data
     * @return array{executive: Executive, reset_link_sent: bool}
     */
    public function handle(?Executive $executive, array $data, ?UploadedFile $photo = null, ?User $actor = null): array
    {
        $creating = $executive === null;
        $oldPhoto = $executive?->photo_path;
        $newPhoto = $photo?->store('photos/executives', 'local');

        try {
            $result = DB::transaction(function () use ($executive, $data, $newPhoto, $actor) {
                $userResult = $this->saveUser->handle($executive?->user, [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $data['password'] ?? null,
                    'active' => $executive?->user->active ?? true,
                ], Role::Executive, $actor);

                $executive ??= new Executive;
                $executive->fill([
                    'user_id' => $userResult['user']->id,
                    'city_id' => $data['city_id'],
                    'region_id' => $data['region_id'],
                    'hired_at' => $data['hired_at'],
                    'status' => $data['status'],
                    'monthly_visits_target' => $data['monthly_visits_target'],
                    'monthly_contracts_target' => $data['monthly_contracts_target'],
                    'cost_allowance' => $data['cost_allowance'],
                    'compensation_notes' => $data['compensation_notes'],
                ]);
                $executive->setCpf($data['cpf']);

                if ($newPhoto) {
                    $executive->photo_path = $newPhoto;
                }

                $executive->save();

                return ['executive' => $executive, 'reset_link_sent' => $userResult['reset_link_sent']];
            });
        } catch (\Throwable $e) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            }
            throw $e;
        }

        if ($newPhoto && $oldPhoto && ! $creating) {
            Storage::disk('local')->delete($oldPhoto);
        }

        return $result;
    }
}
