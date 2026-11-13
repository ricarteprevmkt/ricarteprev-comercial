<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos configuráveis pelo administrador. Cada um é uma tabela própria
 * para que as chaves estrangeiras dos módulos garantam integridade.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['lead_sources', 'loss_reasons', 'visit_reasons', 'expense_categories', 'referral_channels'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);

                if ($name === 'expense_categories') {
                    // variável: entra na margem de contribuição; fixa: não entra.
                    $table->string('nature', 10)->default('variable');
                }

                if ($name === 'loss_reasons') {
                    $table->string('applies_to', 20)->default('opportunity'); // opportunity|referral|both
                }

                $table->timestamps();
                $table->index(['active', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
