<?php
/**
 * admin/facility-map.php — Taluka-level school facility availability.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/facility_map.php';
require_admin();

$active_page = 'facility-map';
$page_title = 'Facility Map — ' . APP_NAME;

$facilityCatalog = lsu_facility_map_catalog();
$requestedFacility = isset($_GET['facility']) && is_string($_GET['facility'])
    ? $_GET['facility']
    : 'facility_water';
$selectedFacility = array_key_exists($requestedFacility, $facilityCatalog)
    ? $requestedFacility
    : 'facility_water';
$selectedFacilityName = $facilityCatalog[$selectedFacility];
$schools = ExcelDB::all('schools');
$groups = lsu_facility_map_summarize($schools, $selectedFacility);

$mapPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'maps' . DIRECTORY_SEPARATOR . 'tando-allahyar-talukas.json';
if (!is_readable($mapPath)) {
    throw new RuntimeException('The Tando Allahyar taluka map data is missing or unreadable.');
}
$mapData = json_decode((string)file_get_contents($mapPath), true, 512, JSON_THROW_ON_ERROR);

$districtSummary = [
    'total' => count($schools),
    'available' => 0,
    'limited' => 0,
    'unavailable' => 0,
    'unknown' => 0,
];
foreach ($groups as $group) {
    foreach (['available', 'limited', 'unavailable', 'unknown'] as $status) {
        $districtSummary[$status] += $group[$status];
    }
}
$districtSummary['assessed'] = $districtSummary['available'] + $districtSummary['limited'] + $districtSummary['unavailable'];
$districtSummary['availability_pct'] = $districtSummary['assessed'] > 0
    ? (int)round($districtSummary['available'] / $districtSummary['assessed'] * 100)
    : null;

$mapFillFor = static function (?int $percent): string {
    if ($percent === null) return '#CBD5E1';
    if ($percent < 25) return '#FCA5A5';
    if ($percent < 50) return '#FDBA74';
    if ($percent < 75) return '#FDE047';
    if ($percent < 90) return '#86EFAC';
    return '#16A34A';
};

$rankedGroups = array_values($groups);
usort($rankedGroups, static function (array $a, array $b): int {
    if ($a['availability_pct'] === null) return $b['availability_pct'] === null ? strcasecmp($a['name'], $b['name']) : 1;
    if ($b['availability_pct'] === null) return -1;
    return $b['availability_pct'] <=> $a['availability_pct'] ?: strcasecmp($a['name'], $b['name']);
});

$availableShare = static function (array $group, string $status): int {
    return $group['assessed'] > 0 ? (int)round($group[$status] / $group['assessed'] * 100) : 0;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
  <style>
    body{font-family:'Inter',system-ui,sans-serif}
    .sidebar-link{transition:background .15s}.sidebar-link:hover{background:rgba(255,255,255,.08)}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E}
    #sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1)}#overlay{transition:opacity .25s}
    .taluka-shape{stroke:#fff;stroke-width:2.3;stroke-linejoin:round;transition:fill .2s,filter .2s}
    .taluka-shape:hover{filter:brightness(.92);stroke:#123B63;stroke-width:4}
    .taluka-label{font-family:Inter,system-ui,sans-serif;font-size:15px;font-weight:700;fill:#172033;paint-order:stroke;stroke:#fff;stroke-width:5px;stroke-linejoin:round;text-anchor:middle;pointer-events:none}
  </style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative"><span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span><span class="hidden sm:block opacity-75"><?= APP_NAME ?></span></div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">
  <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>
    <main class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">
      <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold">Facilities Map</h1>
            <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold"><?= e(APP_DISTRICT) ?> only</span>
          </div>
          <p class="text-xs text-muted mt-1">Compare reported school facility availability across the district's talukas.</p>
        </div>
        <form method="GET" class="bg-white border border-border rounded-xl p-3 shadow-sm flex flex-col sm:flex-row sm:items-center gap-2.5">
          <label for="facility" class="text-xs font-semibold text-textMain whitespace-nowrap">Select facility</label>
          <select id="facility" name="facility" class="min-w-56 text-xs border border-border rounded-lg px-3 py-2 bg-background focus:outline-none focus:border-primary">
            <?php foreach ($facilityCatalog as $field => $label): ?>
              <option value="<?= e($field) ?>" <?= $selectedFacility === $field ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="px-5 py-2 bg-primary hover:bg-primaryDark text-white text-xs font-semibold rounded-lg transition">View Map</button>
        </form>
      </div>

      <section class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-border p-4 shadow-sm"><div class="text-xs text-muted">Schools in district</div><div class="text-2xl font-bold mt-1 text-primary"><?= number_format($districtSummary['total']) ?></div><div class="text-[10px] text-muted mt-1">All registered schools</div></div>
        <div class="bg-white rounded-xl border border-border p-4 shadow-sm"><div class="text-xs text-muted">Reported availability</div><div class="text-2xl font-bold mt-1 text-emerald-700"><?= $districtSummary['availability_pct'] === null ? '—' : $districtSummary['availability_pct'] . '%' ?></div><div class="text-[10px] text-muted mt-1"><?= number_format($districtSummary['available']) ?> available of <?= number_format($districtSummary['assessed']) ?> assessed</div></div>
        <div class="bg-white rounded-xl border border-border p-4 shadow-sm"><div class="text-xs text-muted">Needs attention</div><div class="text-2xl font-bold mt-1 text-amber-600"><?= number_format($districtSummary['limited'] + $districtSummary['unavailable']) ?></div><div class="text-[10px] text-muted mt-1">Limited or unavailable</div></div>
        <div class="bg-white rounded-xl border border-border p-4 shadow-sm"><div class="text-xs text-muted">Not reported</div><div class="text-2xl font-bold mt-1 text-slate-500"><?= number_format($districtSummary['unknown']) ?></div><div class="text-[10px] text-muted mt-1">Excluded from availability rate</div></div>
      </section>

      <section class="bg-white rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="px-4 sm:px-5 py-4 border-b border-border">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
            <div>
              <h2 class="font-bold text-sm"><?= e(strtoupper($selectedFacilityName)) ?> FACILITY</h2>
              <p class="text-[11px] text-muted mt-1">Availability percentage is calculated from schools with a reported status; missing responses are excluded.</p>
            </div>
            <span class="text-[11px] text-muted"><?= number_format($districtSummary['assessed']) ?> of <?= number_format($districtSummary['total']) ?> schools reported</span>
          </div>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 p-4 sm:p-5">
          <div class="xl:col-span-3 rounded-lg border border-border bg-slate-50/70 p-2 sm:p-4">
            <div class="flex items-center justify-between px-2 pt-1">
              <h3 class="text-xs font-bold text-textMain">Tando Allahyar District by Taluka</h3>
              <span class="text-[10px] text-muted">Select facility above to refresh</span>
            </div>
            <svg class="w-full max-h-[680px] mt-2" viewBox="<?= e($mapData['viewBox']) ?>" role="img" aria-label="<?= e($selectedFacilityName) ?> availability across Tando Allahyar District talukas">
              <?php foreach ($mapData['features'] as $boundary):
                $boundaryKey = lsu_facility_map_taluka_key($boundary['name']);
                $group = $groups[$boundaryKey] ?? [
                    'total' => 0, 'assessed' => 0, 'available' => 0,
                    'availability_pct' => null, 'coverage_pct' => 0,
                ];
                $percentageLabel = $group['availability_pct'] === null ? 'No reported data' : $group['availability_pct'] . '%';
                $shapeTitle = $boundary['name'] . ': ' . $percentageLabel
                    . ' availability; ' . $group['available'] . ' available of ' . $group['assessed']
                    . ' assessed; ' . $group['total'] . ' schools total.';
              ?>
                <path class="taluka-shape" d="<?= e($boundary['path']) ?>" fill="<?= e($mapFillFor($group['availability_pct'])) ?>" tabindex="0" aria-label="<?= e($shapeTitle) ?>">
                  <title><?= e($shapeTitle) ?></title>
                </path>
                <text class="taluka-label" x="<?= e((string)$boundary['labelX']) ?>" y="<?= e((string)$boundary['labelY']) ?>"><?= e($boundary['name']) ?><tspan x="<?= e((string)$boundary['labelX']) ?>" dy="19"><?= e($percentageLabel) ?></tspan></text>
              <?php endforeach; ?>
            </svg>
            <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1.5 pt-2 pb-1 text-[10px]">
              <?php foreach ([
                  ['#FCA5A5', '0-24%'],
                  ['#FDBA74', '25-49%'],
                  ['#FDE047', '50-74%'],
                  ['#86EFAC', '75-89%'],
                  ['#16A34A', '90-100%'],
                  ['#CBD5E1', 'No data'],
              ] as [$color, $legend]): ?>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm border border-black/10" style="background:<?= e($color) ?>"></span><?= e($legend) ?></span>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="xl:col-span-2 rounded-lg border border-border p-4">
            <div class="flex items-center justify-between gap-2 mb-4">
              <div>
                <h3 class="text-sm font-bold"><?= e($selectedFacilityName) ?> by Taluka</h3>
                <p class="text-[10px] text-muted mt-0.5">Percent of assessed schools</p>
              </div>
              <span class="text-muted" aria-hidden="true">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="5" r="1.5"/><circle cx="12" cy="5" r="1.5"/><circle cx="19" cy="5" r="1.5"/><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/><circle cx="5" cy="19" r="1.5"/><circle cx="12" cy="19" r="1.5"/><circle cx="19" cy="19" r="1.5"/></svg>
              </span>
            </div>

            <div class="space-y-4">
              <?php foreach ($rankedGroups as $group): ?>
                <div>
                  <div class="flex items-start justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-medium text-textMain">
                      <?= e($group['name']) ?>
                      <?php if (!$group['official_boundary']): ?><span class="block text-[9px] text-amber-700">Stored school label; no official taluka boundary</span><?php endif; ?>
                    </span>
                    <span class="text-[11px] font-bold text-textMain whitespace-nowrap"><?= $group['availability_pct'] === null ? 'No data' : $group['availability_pct'] . '%' ?></span>
                  </div>
                  <?php if ($group['assessed'] > 0): ?>
                    <div class="h-5 w-full rounded-sm bg-slate-100 overflow-hidden flex" role="img" aria-label="<?= e($group['name']) ?>: <?= $group['availability_pct'] ?> percent available, <?= $group['limited'] ?> limited, <?= $group['unavailable'] ?> unavailable.">
                      <?php foreach ([
                          ['available', '#166534'],
                          ['limited', '#F59E0B'],
                          ['unavailable', '#DC2626'],
                      ] as [$status, $color]):
                        $share = $availableShare($group, $status);
                      ?>
                        <?php if ($share > 0): ?>
                          <div class="h-full flex items-center justify-center text-[9px] font-bold text-white" style="width:<?= $share ?>%;background:<?= e($color) ?>" title="<?= e(ucfirst($status) . ': ' . $group[$status]) ?>">
                            <?php if ($share >= 13): ?><?= $share ?>%<?php endif; ?>
                          </div>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="h-5 rounded-sm bg-slate-100 border border-dashed border-slate-300 flex items-center px-2 text-[9px] text-muted">No facility data reported</div>
                  <?php endif; ?>
                  <div class="flex justify-between text-[9px] text-muted mt-1">
                    <span><?= $group['available'] ?> available · <?= $group['limited'] ?> limited · <?= $group['unavailable'] ?> unavailable</span>
                    <span>Reported <?= $group['assessed'] ?>/<?= $group['total'] ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="flex flex-wrap gap-x-3 gap-y-1 mt-5 pt-3 border-t border-border text-[10px] text-muted">
              <span class="inline-flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm bg-green-800"></i>Available</span>
              <span class="inline-flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm bg-amber-500"></i>Limited</span>
              <span class="inline-flex items-center gap-1"><i class="w-2.5 h-2.5 rounded-sm bg-red-600"></i>Unavailable</span>
            </div>
          </div>
        </div>
        <div class="px-4 sm:px-5 py-3 border-t border-border bg-slate-50/70 text-[10px] leading-relaxed text-muted">
          The map shows official taluka boundaries for Tando Allahyar, Jhando Mari and Chambar. Stored school taluka labels without a matching boundary (for example, Nasarpur) remain included in district totals and are listed separately, not assigned to an estimated map area.
          Boundary source: geoBoundaries ADM3, simplified 2017 Pakistan tehsil boundaries, sourced from Pathways Data Pvt. Ltd.; Open Data Commons Open Database License (ODbL) 1.0.
        </div>
      </section>
    </main>
    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>
<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});
</script>
</body>
</html>
