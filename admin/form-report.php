<?php
/**
 * admin/form-report.php — School-by-school response progress for a form.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/forms.php';
require_admin();

$active_page = 'forms';
$formId = trim((string)($_GET['id'] ?? ''));
$form = $formId !== '' ? ExcelDB::find('forms', 'form_id', $formId) : null;
if (!$form || ($form['status'] ?? '') === 'Draft') {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

$assignments = array_values(array_filter(ExcelDB::all('form_assignments'), fn($row) => ($row['form_id'] ?? '') === $formId));
$responses = array_values(array_filter(ExcelDB::all('form_responses'), fn($row) => ($row['form_id'] ?? '') === $formId));
$responseBySemis = [];
foreach ($responses as $response) $responseBySemis[$response['semis_code'] ?? ''] = $response;
usort($assignments, fn($a, $b) => strcasecmp($a['school_name'] ?? '', $b['school_name'] ?? ''));
$questions = lsu_form_questions($form);
$submittedCount = count($responseBySemis);
$assignedCount = count($assignments);
$percent = $assignedCount > 0 ? (int)round($submittedCount * 100 / $assignedCount) : 0;

if (($_GET['export'] ?? '') === 'csv') {
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$form['title']);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="form-responses-' . ($filename ?: 'form') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    if (!$output) {
        http_response_code(500);
        exit('Unable to export form responses.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    $headers = ['SEMIS Code', 'School Name', 'Submission Status', 'Submitted At'];
    foreach ($questions as $question) $headers[] = (string)($question['label'] ?? 'Question');
    foreach ($headers as &$header) {
        if (preg_match('/^\s*[=+\-@]/', $header)) $header = "'" . $header;
    }
    unset($header);
    fputcsv($output, $headers);
    foreach ($assignments as $assignment) {
        $response = $responseBySemis[$assignment['semis_code'] ?? ''] ?? null;
        $answers = $response ? (json_decode((string)($response['answers_json'] ?? ''), true) ?: []) : [];
        $values = [
            $assignment['semis_code'] ?? '',
            $assignment['school_name'] ?? '',
            $response ? 'Submitted' : 'Not submitted',
            $response['submitted_at'] ?? '',
        ];
        foreach ($questions as $question) {
            $answer = $answers[$question['id'] ?? ''] ?? '';
            $values[] = is_array($answer) ? implode('; ', $answer) : $answer;
        }
        foreach ($values as &$value) {
            $value = (string)$value;
            if (preg_match('/^\s*[=+\-@]/', $value)) $value = "'" . $value;
        }
        unset($value);
        fputcsv($output, $values);
    }
    fclose($output);
    exit;
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = (string)($_GET['response'] ?? '');
$filteredAssignments = array_values(array_filter($assignments, function($assignment) use ($responseBySemis, $search, $statusFilter) {
    $semis = (string)($assignment['semis_code'] ?? '');
    $submitted = isset($responseBySemis[$semis]);
    if ($search !== '' && stripos(($assignment['school_name'] ?? '') . ' ' . $semis, $search) === false) return false;
    if ($statusFilter === 'submitted' && !$submitted) return false;
    if ($statusFilter === 'missing' && $submitted) return false;
    return true;
}));
$page_title = $form['title'] . ' — Form Progress';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
  <style>body{font-family:'Inter',system-ui,sans-serif}.sidebar-link{transition:background .15s}.sidebar-link:hover{background:rgba(255,255,255,.08)}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E}#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1)}#overlay{transition:opacity .25s}</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative"><span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span><span class="hidden sm:block opacity-75"><?= APP_NAME ?></span></div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">
  <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>
    <main class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div><a href="<?= BASE_URL ?>/admin/forms.php" class="text-xs text-primary hover:underline">← All forms</a><h1 class="text-xl font-bold mt-1"><?= e($form['title']) ?></h1><p class="text-xs text-muted mt-1"><?= e($form['description'] ?? '') ?></p></div>
        <a href="?id=<?= urlencode($formId) ?>&amp;export=csv" class="px-3 py-2 bg-white border border-border rounded-lg text-xs font-semibold hover:bg-slate-50">Export responses CSV</a>
      </div>
      <?php if (isset($_GET['published'])): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Form published and notifications sent to <?= $assignedCount ?> school(s).</div><?php endif; ?>
      <section class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-border p-4"><div class="text-xs text-muted">Assigned schools</div><div class="text-2xl font-bold mt-1"><?= $assignedCount ?></div></div>
        <div class="bg-white rounded-xl border border-border p-4"><div class="text-xs text-muted">Submitted</div><div class="text-2xl font-bold mt-1 text-emerald-700"><?= $submittedCount ?></div></div>
        <div class="bg-white rounded-xl border border-border p-4"><div class="text-xs text-muted">Not submitted</div><div class="text-2xl font-bold mt-1 text-amber-700"><?= max(0, $assignedCount - $submittedCount) ?></div></div>
        <div class="bg-white rounded-xl border border-border p-4"><div class="text-xs text-muted">Completion</div><div class="text-2xl font-bold mt-1 text-primary"><?= $percent ?>%</div><div class="h-1.5 bg-slate-100 rounded-full mt-2 overflow-hidden"><div class="h-full bg-emerald-500" style="width:<?= $percent ?>%"></div></div></div>
      </section>
      <section class="bg-white rounded-xl border border-border shadow-sm overflow-hidden">
        <form method="GET" class="p-4 border-b border-border flex flex-col sm:flex-row gap-2">
          <input type="hidden" name="id" value="<?= e($formId) ?>">
          <input type="search" name="search" value="<?= e($search) ?>" placeholder="Find school or SEMIS..." class="flex-1 border border-border rounded-lg px-3 py-2 text-xs">
          <select name="response" class="border border-border rounded-lg px-3 py-2 text-xs bg-white"><option value="">All schools</option><option value="submitted" <?= $statusFilter === 'submitted' ? 'selected' : '' ?>>Submitted</option><option value="missing" <?= $statusFilter === 'missing' ? 'selected' : '' ?>>Not submitted</option></select>
          <button class="px-4 py-2 bg-primary text-white rounded-lg text-xs font-semibold">Filter</button>
        </form>
        <?php if (!$filteredAssignments): ?><div class="p-8 text-center text-xs text-muted">No schools match this filter.</div>
        <?php else: ?>
          <div class="overflow-x-auto"><table class="w-full text-sm">
            <thead><tr class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-muted">            <th class="px-4 py-3">School</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Last response update</th><th class="px-4 py-3">Response details</th></tr></thead>
            <tbody class="divide-y divide-border">
            <?php foreach ($filteredAssignments as $assignment):
              $semis = (string)($assignment['semis_code'] ?? '');
              $response = $responseBySemis[$semis] ?? null;
              $answers = $response ? (json_decode((string)($response['answers_json'] ?? ''), true) ?: []) : [];
            ?>
              <tr class="align-top hover:bg-slate-50/60">
                <td class="px-4 py-3"><div class="font-semibold text-xs"><?= e($assignment['school_name'] ?? '') ?></div><div class="font-mono text-[11px] text-muted mt-1"><?= e($semis) ?></div></td>
                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold <?= $response ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>"><?= $response ? 'Submitted' : 'Not submitted' ?></span></td>
                <td class="px-4 py-3 text-xs text-muted whitespace-nowrap"><?= $response && !empty($response['updated_at']) ? e(date('M d, Y h:i A', strtotime($response['updated_at']))) : '—' ?></td>
                <td class="px-4 py-3 min-w-[250px]">
                  <?php if ($response): ?>
                    <details><summary class="cursor-pointer text-xs font-semibold text-primary hover:underline">View submitted answers</summary>
                      <div class="mt-3 space-y-2">
                        <?php foreach ($questions as $question):
                          $answer = $answers[$question['id'] ?? ''] ?? '';
                          $answerText = is_array($answer) ? implode(', ', $answer) : (string)$answer;
                        ?>
                          <div class="border-l-2 border-slate-200 pl-2"><div class="text-[11px] font-semibold"><?= e($question['label'] ?? '') ?></div><div class="text-xs text-muted whitespace-pre-wrap break-words"><?= $answerText !== '' ? e($answerText) : '—' ?></div></div>
                        <?php endforeach; ?>
                      </div>
                    </details>
                  <?php else: ?><span class="text-xs text-muted">Awaiting response</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </section>
    </main>
    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>
<script>function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});</script>
</body>
</html>
