<?php
/**
 * Shared facility availability calculations for the District Facility Map.
 */

function lsu_facility_map_catalog(): array {
    return [
        'facility_water' => 'Drinking Water',
        'facility_electricity' => 'Electricity',
        'has_building' => 'Building',
        'facility_boundary_wall' => 'Boundary Wall',
        'facility_toilets' => 'Toilets',
        'facility_handwash' => 'Hand Wash',
        'facility_soap' => 'Soap',
        'facility_mhm_disposal' => 'MHM Disposal',
        'facility_science_lab' => 'Science Lab',
        'facility_library' => 'Library',
        'facility_computer_lab' => 'Computer Lab',
        'facility_playground' => 'Playground',
        'drainage_sewerage' => 'Drainage / Sewerage',
        'furniture_condition' => 'Furniture / Equipment',
        'facility_internet' => 'Internet',
        'building_condition' => 'Building Condition',
    ];
}

function lsu_facility_map_taluka_key(string $taluka): string {
    $key = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', ' ', $taluka)));
    $key = trim((string)preg_replace('/\s+/', ' ', $key));

    if (in_array($key, ['chambar', 'chamber'], true)) {
        return 'chambar';
    }
    if (in_array($key, ['tando allahyar', 'tando allah yar'], true)) {
        return 'tando allahyar';
    }

    return $key;
}

function lsu_facility_map_school_status(array $school, string $field): string {
    $value = strtolower(trim((string)($school[$field] ?? '')));
    if ($value === '') {
        return 'unknown';
    }

    if (in_array($field, [
        'facility_handwash',
        'facility_soap',
        'facility_mhm_disposal',
        'facility_science_lab',
        'facility_library',
        'facility_computer_lab',
        'facility_playground',
        'has_building',
    ], true)) {
        if (in_array($value, ['yes', 'available', 'present'], true)) {
            return 'available';
        }
        if (in_array($value, ['no', 'unavailable', 'absent'], true)) {
            return 'unavailable';
        }
        return 'unknown';
    }

    if ($field === 'building_condition') {
        if (str_contains($value, 'dangerous') || str_contains($value, 'unsafe') || str_contains($value, 'condemned')) {
            return 'unavailable';
        }
        if (str_contains($value, 'repair')) {
            return 'limited';
        }
        if (str_contains($value, 'satisfactory') || str_contains($value, 'good')) {
            return 'available';
        }
        return 'unknown';
    }

    if ($field === 'facility_boundary_wall' && (
        str_contains($value, 'partial')
        || str_contains($value, 'damaged')
        || str_contains($value, 'construction')
    )) {
        return 'limited';
    }

    if ($field === 'facility_toilets' && str_contains($value, 'repair')) {
        return 'limited';
    }

    if ($field === 'drainage_sewerage' && str_contains($value, 'partial')) {
        return 'limited';
    }

    if ($field === 'furniture_condition' && str_contains($value, 'shortage')) {
        return 'limited';
    }

    if (in_array($field, ['facility_water', 'facility_electricity'], true)) {
        if (str_contains($value, 'unavailable') || str_contains($value, 'none') || $value === 'no') {
            return 'unavailable';
        }
        if (in_array($value, ['solar + grid', 'grid only', 'solar only', 'filtered plant', 'handpump / tap', 'water supply line'], true)) {
            return 'available';
        }
        return 'unknown';
    }

    if ($field === 'facility_internet') {
        if (str_contains($value, 'unavailable') || str_contains($value, 'none')) {
            return 'unavailable';
        }
        if (str_contains($value, 'partial') || str_contains($value, 'mobile data')) {
            return 'limited';
        }
        return str_contains($value, 'broadband') || str_contains($value, '4g') ? 'available' : 'unknown';
    }

    if ($field === 'facility_boundary_wall') {
        if (str_contains($value, 'unavailable') || str_contains($value, 'none')) {
            return 'unavailable';
        }
        return str_contains($value, 'secured') && str_contains($value, 'complete') ? 'available' : 'unknown';
    }

    if ($field === 'facility_toilets') {
        if (str_contains($value, 'unavailable') || str_contains($value, 'none')) {
            return 'unavailable';
        }
        return str_contains($value, 'functional') ? 'available' : 'unknown';
    }

    if ($field === 'drainage_sewerage') {
        if (str_contains($value, 'broken') || str_contains($value, 'none')) {
            return 'unavailable';
        }
        return str_contains($value, 'functional') ? 'available' : 'unknown';
    }

    if ($field === 'furniture_condition') {
        if (str_contains($value, 'none')) {
            return 'unavailable';
        }
        return str_contains($value, 'adequate') ? 'available' : 'unknown';
    }

    return 'unknown';
}

function lsu_facility_map_summarize(array $schools, string $field): array {
    $officialTalukas = [
        'tando allahyar' => 'Tando Allahyar',
        'jhando mari' => 'Jhando Mari',
        'chambar' => 'Chambar',
    ];
    $groups = [];
    foreach ($officialTalukas as $key => $name) {
        $groups[$key] = [
            'key' => $key,
            'name' => $name,
            'official_boundary' => true,
            'total' => 0,
            'available' => 0,
            'limited' => 0,
            'unavailable' => 0,
            'unknown' => 0,
        ];
    }

    foreach ($schools as $school) {
        $taluka = trim((string)($school['taluka'] ?? ''));
        $key = lsu_facility_map_taluka_key($taluka);
        if ($key === '') {
            $key = 'unspecified';
        }
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'name' => $taluka !== '' ? $taluka : 'Taluka not specified',
                'official_boundary' => false,
                'total' => 0,
                'available' => 0,
                'limited' => 0,
                'unavailable' => 0,
                'unknown' => 0,
            ];
        }

        $groups[$key]['total']++;
        $groups[$key][lsu_facility_map_school_status($school, $field)]++;
    }

    foreach ($groups as &$group) {
        $group['assessed'] = $group['available'] + $group['limited'] + $group['unavailable'];
        $group['availability_pct'] = $group['assessed'] > 0
            ? (int)round($group['available'] / $group['assessed'] * 100)
            : null;
        $group['coverage_pct'] = $group['total'] > 0
            ? (int)round($group['assessed'] / $group['total'] * 100)
            : 0;
    }
    unset($group);

    return $groups;
}
