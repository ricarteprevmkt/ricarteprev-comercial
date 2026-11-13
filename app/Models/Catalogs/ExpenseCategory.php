<?php

namespace App\Models\Catalogs;

/**
 * Categoria de despesa. A natureza define se entra na margem de contribuição:
 * "variable" entra, "fixed" não.
 *
 * @property string $nature
 */
class ExpenseCategory extends CatalogItem
{
    protected $table = 'expense_categories';

    /** @var list<string> */
    protected $fillable = ['name', 'slug', 'sort_order', 'active', 'nature'];

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true, 'sort_order' => 0, 'nature' => 'variable'];
}
