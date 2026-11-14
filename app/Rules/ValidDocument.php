<?php

namespace App\Rules;

use App\Enums\LeadType;
use App\Support\Document;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDocument implements ValidationRule
{
    public function __construct(private LeadType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Document::isValid($value, $this->type)) {
            $fail("O {$this->type->documentLabel()} informado não é válido.");
        }
    }
}
