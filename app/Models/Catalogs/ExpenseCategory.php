<?php

namespace App\Models\Catalogs;

/**
 * Categoria de despesa. A natureza define se entra na margem de contribuição:
 * "variable" entra, "fixed" não.
 *
 * @property string $nature
 * @property bool $is_travel
 */
class ExpenseCategory extends CatalogItem
{
    protected $table = 'expense_categories';

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'sort_order', 'active', 'nature', 'is_travel'];

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true, 'sort_order' => 0, 'nature' => 'variable', 'is_travel' => false];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer', 'is_travel' => 'boolean'];
    }
}
