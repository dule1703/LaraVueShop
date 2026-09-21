<?php

namespace App\Rules;

use App\Support\Isbn;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIsbn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isbn = is_string($value) ? Isbn::normalize($value) : null;

        if ($isbn === null || ! Isbn::isValid($isbn)) {
            $fail('ISBN nije ispravan (proveri broj cifara i kontrolnu cifru).');
        }
    }
}
