<?php

namespace App\Actions\Agenda;

use App\Enums\ExpenseStatus;
use App\Models\Attachment;
use App\Models\City;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Lança uma despesa (com comprovante opcional). Toda despesa nasce
 * aguardando aprovação; a aprovação é feita em Custos e resultados.
 */
final class SaveExpense
{
    /**
     * @param  array{description: string, expense_category_id: int, amount: string, spent_on: string,
     *     executive_id?: int|null, city_id?: int|null, visit_id?: int|null, kind?: string}  $data
     */
    public function handle(?Expense $expense, array $data, ?UploadedFile $receipt, ?User $user): Expense
    {
        if ($expense && $expense->status !== ExpenseStatus::Pending) {
            throw ValidationException::withMessages(['expense.amount' => 'Despesa já analisada não pode ser alterada.']);
        }

        $path = $receipt?->store('receipts/'.now()->format('Y/m'), 'local');

        try {
            return DB::transaction(function () use ($expense, $data, $receipt, $path, $user) {
                $expense ??= new Expense(['created_by' => $user?->id]);
                // Região: a do executivo; para despesa geral, a da cidade informada.
                $regionId = isset($data['executive_id']) ? Executive::query()->whereKey($data['executive_id'])->value('region_id') : null;
                $regionId ??= isset($data['city_id']) ? City::query()->whereKey($data['city_id'])->value('region_id') : null;

                $expense->fill($data + ['kind' => 'actual']);
                $expense->region_id = $regionId;
                $expense->save();

                if ($receipt && $path) {
                    $attachment = new Attachment([
                        'path' => $path,
                        'original_name' => mb_substr($receipt->getClientOriginalName(), 0, 255),
                        'mime' => (string) $receipt->getMimeType(),
                        'size' => (int) $receipt->getSize(),
                        'uploaded_by' => $user?->id,
                    ]);
                    $expense->attachments()->save($attachment);
                }

                return $expense;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }
}
