<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Attach to a virtual attribute (one not backed by a real input, e.g.
 * "identifier") rather than to one of the fields it checks -- the failure
 * isn't "about" any single one of them, so it shouldn't be misattributed
 * to whichever field happens to hold the rule. Fails once, with the
 * caller's own message, when every field in $fields is empty.
 */
class RequiresAtLeastOneOf implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  array<int, string>  $fields
     */
    public function __construct(
        private readonly array $fields,
        private readonly string $message,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ($this->fields as $field) {
            if (filled($this->data[$field] ?? null)) {
                return;
            }
        }

        $fail($this->message);
    }
}
