<?php

namespace App\Services\Pharmacy\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ReportFilters
{
    public static function defaults(?string $location): array
    {
        return ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(),
            'location' => $location ?? '', 'fund' => '', 'medicine' => '', 'expiry' => 'near', 'days' => 90,
            'group_equivalents' => '0'];
    }

    public static function validate(array $input, ?string $ownLocation, bool $canFilterLocation, array $funds): array
    {
        $defaults = self::defaults($ownLocation);
        $data = array_intersect_key($input, $defaults) + $defaults;
        $data = Validator::make($data, [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'location' => ['required', 'integer', 'min:1'],
            'fund' => ['nullable', 'string', Rule::in($funds)],
            'medicine' => ['nullable', 'string', 'max:150'],
            'expiry' => ['required', Rule::in(['near', 'expired', 'all', 'unknown'])],
            'days' => ['required', 'integer', 'min:1', 'max:730'],
            'group_equivalents' => ['required', Rule::in(['0', '1'])],
        ])->validate();

        abort_unless($canFilterLocation || ($ownLocation !== null && (string) $data['location'] === $ownLocation), 403);
        $data['medicine'] = trim($data['medicine'] ?? '');
        $data['fund'] = $data['fund'] ?? '';
        $data['group_equivalents'] = (string) $data['group_equivalents'];

        return $data;
    }

    public static function bounds(array $filters): array
    {
        return [CarbonImmutable::parse($filters['from'])->startOfDay()->format('Y-m-d H:i:s'),
            CarbonImmutable::parse($filters['to'])->addDay()->startOfDay()->format('Y-m-d H:i:s')];
    }
}
