<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DateAssignmentLetter implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $startDate = Carbon::createFromFormat('d/m/Y', request('assignment_letter_start_date'));
        $endDate = Carbon::createFromFormat('d/m/Y', request('assignment_letter_end_date'));

        if ($startDate->gt($endDate)) {
            $fail('Tanggal mulai dinas tidak boleh lebih besar dari tanggal selesai dinas.');
        }
    }
}
