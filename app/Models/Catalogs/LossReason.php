<?php

namespace App\Models\Catalogs;

/**
 * Motivo de encerramento/perda. "applies_to" indica onde ele aparece:
 * opportunity (funil), referral (indicações) ou both.
 *
 * @property string $applies_to
 */
class LossReason extends CatalogItem
{
    protected $table = 'loss_reasons';

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'sort_order', 'active', 'applies_to'];

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true, 'sort_order' => 0, 'applies_to' => 'both'];
}
