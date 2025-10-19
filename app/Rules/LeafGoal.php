<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Goal;

class LeafGoal implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isLeaf = Goal::query()
            ->whereKey($value)
            ->whereDoesntHave('children') // بدون فرزند
            ->exists();

        if (!$isLeaf) {
            $fail('تنها برای اهداف بدون زیرمجموعه می‌توانید تسک بسازید.');
        }
    }
}
