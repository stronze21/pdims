<?php

namespace App\Services\Pharmacy\DrugImport;

use Illuminate\Support\Facades\DB;

class DrugGroupSuggester
{
    public function suggest(?string $genericName, int $limit = 5): array
    {
        $genericName = trim((string) $genericName);
        if ($genericName === '') {
            return [];
        }

        return $this->activeGroups()
            ->map(function ($row) use ($genericName) {
                $option = $this->formatOption($row);

                return [
                    ...$option,
                    'generic_name' => trim((string) $row->gendesc),
                    'score' => $this->score($genericName, (string) $row->gendesc),
                ];
            })
            ->filter(fn (array $suggestion) => $suggestion['score'] >= 50)
            ->sortBy([
                ['score', 'desc'],
                ['generic_name', 'asc'],
            ])
            ->unique('id')
            ->take($limit)
            ->values()
            ->all();
    }

    public function options(): array
    {
        return $this->activeGroups()
            ->map(fn ($row) => [
                'id' => trim((string) $row->grpcode),
                'name' => trim((string) $row->gendesc),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function recommendNew(?string $genericName, array $source = []): array
    {
        $genericName = trim((string) $genericName);
        if ($genericName === '') {
            return [];
        }

        $generic = DB::connection('hospital')->table('hgen')->where('genstat', 'A')->get(['gencode', 'gendesc'])
            ->map(fn ($row) => ['id' => trim((string) $row->gencode), 'name' => trim((string) $row->gendesc), 'score' => $this->score($genericName, (string) $row->gendesc)])
            ->sortByDesc('score')->first();
        if (($generic['score'] ?? 0) < 92) {
            $generic = null;
        }

        $levels = [];
        $parent = null;
        foreach ([
            1 => ['table' => 'dmmajor', 'key' => 'dmcode', 'description' => 'dmdesc', 'parent' => null],
            2 => ['table' => 'dmsub1', 'key' => 'dms1key', 'description' => 'dms1desc', 'parent' => 'dmcode'],
            3 => ['table' => 'dmsub2', 'key' => 'dms2key', 'description' => 'dms2desc', 'parent' => 'dms1key'],
            4 => ['table' => 'dmsub3', 'key' => 'dms3key', 'description' => 'dms3desc', 'parent' => 'dms2key'],
        ] as $level => $config) {
            $sourceValue = trim((string) ($source['Level '.$level] ?? ''));
            if ($sourceValue === '' || ($config['parent'] && ! $parent)) {
                break;
            }
            $query = DB::connection('hospital')->table($config['table']);
            if ($config['parent']) {
                $query->where($config['parent'], $parent);
            }
            $match = $query->get([$config['key'], $config['description']])
                ->map(fn ($row) => [
                    'id' => trim((string) $row->{$config['key']}),
                    'name' => trim((string) $row->{$config['description']}),
                    'score' => $this->score($sourceValue, (string) $row->{$config['description']}),
                ])->sortByDesc('score')->first();
            if (($match['score'] ?? 0) < 85) {
                break;
            }
            $levels[$level] = $match;
            $parent = $match['id'];
        }

        $classificationSource = $levels ? 'workbook classification' : null;
        if (! isset($levels[1]) && filled($source['ATC Code'] ?? null)) {
            [$levels, $classificationSource] = $this->classificationFromAtc((string) $source['ATC Code']);
        }

        return [
            'generic_name' => $genericName,
            'generic' => $generic,
            'levels' => $levels,
            'classification' => collect($levels)->pluck('name')->implode(' / '),
            'classification_source' => $classificationSource,
            'can_create_group' => $generic !== null && isset($levels[1]),
        ];
    }

    private function classificationFromAtc(string $atcCode): array
    {
        $atcCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $atcCode));
        for ($length = min(7, strlen($atcCode)); $length >= 3; $length--) {
            $prefix = substr($atcCode, 0, $length);
            $groups = DB::connection('hospital')->table('hdruggrp as grp')
                ->join('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
                ->leftJoin('dmmajor as major', 'major.dmcode', '=', 'grp.dmcode')
                ->leftJoin('dmsub1 as sub1', 'sub1.dms1key', '=', 'grp.dms1key')
                ->leftJoin('dmsub2 as sub2', 'sub2.dms2key', '=', 'grp.dms2key')
                ->leftJoin('dmsub3 as sub3', 'sub3.dms3key', '=', 'grp.dms3key')
                ->where('grp.grpstat', 'A')
                ->whereRaw("REPLACE(UPPER(gen.atccode), ' ', '') LIKE ?", [$prefix.'%'])
                ->get([
                    'grp.dmcode', 'grp.dms1key', 'grp.dms2key', 'grp.dms3key',
                    'major.dmdesc', 'sub1.dms1desc', 'sub2.dms2desc', 'sub3.dms3desc',
                ]);
            if ($groups->isEmpty()) {
                continue;
            }
            $group = $groups->groupBy(fn ($row) => implode('|', [trim((string) $row->dmcode), trim((string) $row->dms1key), trim((string) $row->dms2key), trim((string) $row->dms3key)]))
                ->sortByDesc(fn ($rows) => $rows->count())->first()->first();
            $levels = collect([
                1 => [$group->dmcode, $group->dmdesc], 2 => [$group->dms1key, $group->dms1desc],
                3 => [$group->dms2key, $group->dms2desc], 4 => [$group->dms3key, $group->dms3desc],
            ])->filter(fn ($value) => filled($value[0]))
                ->map(fn ($value) => ['id' => trim((string) $value[0]), 'name' => trim((string) $value[1]), 'score' => min(100, 55 + ($length * 5))])
                ->all();

            return [$levels, "existing PDIMS groups in ATC family {$prefix}"];
        }

        return [[], null];
    }

    public function score(string $source, string $candidate): float
    {
        $source = $this->normalize($source);
        $candidate = $this->normalize($candidate);
        if ($source === '' || $candidate === '') {
            return 0;
        }
        if ($source === $candidate) {
            return 100;
        }

        similar_text($source, $candidate, $similarity);
        $maximumLength = max(mb_strlen($source), mb_strlen($candidate));
        $editScore = $maximumLength > 0
            ? max(0, 100 * (1 - levenshtein($source, $candidate) / $maximumLength))
            : 0;
        $tokenScore = $this->setSimilarity($this->tokens($source), $this->tokens($candidate));
        $phoneticScore = $this->setSimilarity($this->phoneticTokens($source), $this->phoneticTokens($candidate));
        $score = ($similarity * .45) + ($editScore * .25) + ($tokenScore * .20) + ($phoneticScore * .10);

        if (min(mb_strlen($source), mb_strlen($candidate)) >= 4 && (str_contains($source, $candidate) || str_contains($candidate, $source))) {
            $score = max($score, 88);
        }

        return round(min(100, $score), 1);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['antiinfective', 'anti infective'], 'anti-infective', $value);
        $value = preg_replace('/\bfor\s+systemic\s+use\b/u', ' ', $value);
        $value = preg_replace('/\bpreparations?\b/u', ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^a-z0-9]+/u', ' ', $value)));
    }

    private function tokens(string $value): array
    {
        return array_values(array_unique(array_filter(explode(' ', $value), fn (string $token) => mb_strlen($token) > 1)));
    }

    private function phoneticTokens(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $token) => metaphone($token),
            $this->tokens($value)
        ))));
    }

    private function setSimilarity(array $left, array $right): float
    {
        $union = array_unique([...$left, ...$right]);
        if ($union === []) {
            return 0;
        }

        return 100 * count(array_intersect($left, $right)) / count($union);
    }

    private function activeGroups()
    {
        return DB::connection('hospital')->table('hdruggrp as grp')
            ->leftJoin('hgen as gen', 'gen.gencode', '=', 'grp.gencode')
            ->leftJoin('dmmajor as major', 'major.dmcode', '=', 'grp.dmcode')
            ->leftJoin('dmsub1 as sub1', 'sub1.dms1key', '=', 'grp.dms1key')
            ->leftJoin('dmsub2 as sub2', 'sub2.dms2key', '=', 'grp.dms2key')
            ->leftJoin('dmsub3 as sub3', 'sub3.dms3key', '=', 'grp.dms3key')
            ->leftJoin('dmsub4 as sub4', 'sub4.dms4key', '=', 'grp.dms4key')
            ->where('grp.grpstat', 'A')
            ->whereNotNull('gen.gendesc')
            ->get([
                'grp.grpcode', 'gen.gendesc', 'major.dmdesc as major_description',
                'sub1.dms1desc as sub1_description', 'sub2.dms2desc as sub2_description',
                'sub3.dms3desc as sub3_description', 'sub4.dms4desc as sub4_description',
            ]);
    }

    private function formatOption(object $row): array
    {
        $classification = collect([
            $row->major_description, $row->sub1_description, $row->sub2_description,
            $row->sub3_description, $row->sub4_description,
        ])->map(fn ($value) => trim((string) $value))->filter()->unique()->implode(' / ');
        $generic = trim((string) $row->gendesc);

        return [
            'id' => trim((string) $row->grpcode),
            'name' => $generic.($classification !== '' ? ' — '.$classification : ''),
        ];
    }
}
