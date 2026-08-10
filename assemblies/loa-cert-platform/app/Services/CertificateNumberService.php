<?php

namespace App\Services;

use App\Models\CertificateSequence;
use Illuminate\Support\Facades\DB;

class CertificateNumberService
{
    public function generate(string $organizationId, string $pattern): string
    {
        return DB::transaction(function () use ($organizationId, $pattern) {
            $sequence = CertificateSequence::lockForUpdate()
                ->firstOrCreate(
                    ['organization_id' => $organizationId, 'pattern' => $pattern],
                    ['next_value' => 1]
                );

            $value = $sequence->next_value;

            CertificateSequence::where('organization_id', $organizationId)
                ->where('pattern', $pattern)
                ->update(['next_value' => $value + 1]);

            $width = substr_count($pattern, '#');
            $paddedValue = str_pad($value, $width, '0', STR_PAD_LEFT);

            $number = str_replace('####', $paddedValue, $pattern);
            $number = str_replace('YYYY', date('Y'), $number);

            return $number;
        });
    }
}
