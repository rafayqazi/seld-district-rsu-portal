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
$missingFilteredAssignments = array_values(array_filter($filteredAssignments, fn($assignment) =>
    !isset($responseBySemis[(string)($assignment['semis_code'] ?? '')])
));
$noticeSchools = [];
foreach ($missingFilteredAssignments as $assignment) {
    $semis = trim((string)($assignment['semis_code'] ?? ''));
    $school = ExcelDB::getSchoolBySemis($semis) ?? [];
    $noticeSchools[] = [
        'semis_code' => $semis,
        'school_name' => (string)($school['school_name'] ?? $assignment['school_name'] ?? ''),
        'head_master' => (string)($school['head_master'] ?? 'Head Master / Head Mistress'),
        'cnic' => (string)($school['cnic'] ?? 'N/A'),
        'taluka' => (string)($school['taluka'] ?? ''),
    ];
}
$noticeDataJson = json_encode($noticeSchools, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$noticeDataJson = $noticeDataJson === false ? '[]' : $noticeDataJson;
$districtName = ExcelDB::getSetting('district', APP_DISTRICT);
$noticeDate = date('F j, Y');
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
  <style>
    body{font-family:'Inter',system-ui,sans-serif}.sidebar-link{transition:background .15s}.sidebar-link:hover{background:rgba(255,255,255,.08)}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E}#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1)}#overlay{transition:opacity .25s}
    .form-notice-page{box-sizing:border-box;min-height:265mm;padding:8mm 4mm;color:#172033;background:#fff;font-family:Arial,sans-serif;page-break-after:always;break-after:page}
    .form-notice-page:last-child{page-break-after:auto;break-after:auto}
    .form-notice-header{text-align:center;border-bottom:2px solid #123B63;padding-bottom:12px}
    .form-notice-meta{display:flex;justify-content:space-between;gap:12px;margin:16px 0;font-size:11px}
    .form-notice-addressee{background:#f8fafc;border:1px solid #cbd5e1;padding:12px;margin-bottom:14px;font-size:12px}
    .form-notice-body{font-size:12px;line-height:1.65;text-align:justify}
    .form-notice-directive{margin-top:16px;padding:12px;border:1px solid #fecaca;background:#fef2f2;font-size:12px;line-height:1.6}
    .form-notice-signatures{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:55px;text-align:center;font-size:11px}
    .form-notice-signatures>div{border-top:1px solid #64748b;padding-top:8px}
    @media print{
      @page{size:A4 portrait;margin:10mm}
      html,body{margin:0!important;padding:0!important;background:#fff!important}
      body *{visibility:hidden!important}
      #form-notice-modal,#form-notice-modal *{visibility:visible!important}
      #form-notice-modal{position:absolute!important;inset:0!important;display:block!important;width:100%!important;height:auto!important;padding:0!important;background:#fff!important}
      #form-notice-dialog{width:100%!important;max-width:none!important;max-height:none!important;margin:0!important;padding:0!important;overflow:visible!important;border:0!important;box-shadow:none!important}
      #form-notice-print-container{display:block!important}
      .form-notice-page{min-height:265mm;padding:0 2mm}
      .no-print{display:none!important}
    }
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
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div><a href="<?= BASE_URL ?>/admin/forms.php" class="text-xs text-primary hover:underline">← All forms</a><h1 class="text-xl font-bold mt-1"><?= e($form['title']) ?></h1><p class="text-xs text-muted mt-1"><?= e($form['description'] ?? '') ?></p></div>
        <div class="flex flex-wrap items-center gap-2">
          <?php if ($missingFilteredAssignments): ?>
            <button type="button" onclick="openFormShowCauseNotices()" class="px-3 py-2 bg-red-700 hover:bg-red-800 text-white rounded-lg text-xs font-semibold">Print Show Cause Notices (<?= count($missingFilteredAssignments) ?>)</button>
          <?php endif; ?>
          <a href="?id=<?= urlencode($formId) ?>&amp;export=csv" class="px-3 py-2 bg-white border border-border rounded-lg text-xs font-semibold hover:bg-slate-50">Export responses CSV</a>
        </div>
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
              <tr class="align-top hover:bg-slate-50/60" data-semis="<?= e($semis) ?>" data-response-status="<?= $response ? 'submitted' : 'missing' ?>">
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
<div id="form-notice-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 p-3 sm:p-6">
  <div id="form-notice-dialog" class="mx-auto my-4 max-w-4xl max-h-[90vh] overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
    <div class="no-print mb-5 flex items-center justify-between gap-3">
      <div><h2 class="font-bold text-lg">Show Cause Notices</h2><p class="text-xs text-muted">One print-ready letter per school that has not submitted this form.</p></div>
      <div class="flex gap-2">
        <button type="button" onclick="printFormShowCauseNotices()" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white">Print notices</button>
        <button type="button" onclick="closeFormShowCauseNotices()" class="rounded-lg border border-border px-4 py-2 text-xs font-semibold">Close</button>
      </div>
    </div>
    <div id="form-notice-print-container" class="space-y-5"></div>
  </div>
</div>
<script id="form-notice-data" type="application/json"><?= $noticeDataJson ?></script>
<script>
const formNoticeSchools = JSON.parse(document.getElementById('form-notice-data').textContent);
const formNoticeMeta = {
  formId: <?= json_encode($formId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  formTitle: <?= json_encode((string)$form['title'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  closesAt: <?= json_encode(!empty($form['closes_at']) ? date('F j, Y g:i A', strtotime($form['closes_at'])) : 'Not specified', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  district: <?= json_encode($districtName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  date: <?= json_encode($noticeDate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
function formNoticeEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);
}
function openFormShowCauseNotices() {
  const pendingSemis = new Set(Array.from(document.querySelectorAll('tr[data-response-status="missing"]')).map(row => row.dataset.semis));
  const schools = formNoticeSchools.filter(school => pendingSemis.has(school.semis_code));
  if (!schools.length) {
    showToast('There are no matching schools without a response to print notices for.', 'error');
    return;
  }
  const container = document.getElementById('form-notice-print-container');
  container.innerHTML = schools.map(school => {
    const semis = formNoticeEscape(school.semis_code);
    const schoolName = formNoticeEscape(school.school_name);
    const hmName = formNoticeEscape(school.head_master || 'Head Master / Head Mistress');
    const cnic = formNoticeEscape(school.cnic || 'N/A');
    const taluka = formNoticeEscape(school.taluka || '—');
    return `<article class="form-notice-page">
      <header class="form-notice-header">
        <div style="font-size:11px;font-weight:bold;letter-spacing:2px">GOVERNMENT OF SINDH</div>
        <div style="font-size:17px;font-weight:800;margin-top:3px">SCHOOL EDUCATION &amp; LITERACY DEPARTMENT</div>
        <div style="font-size:12px;font-weight:700;margin-top:3px">DISTRICT REFORM SUPPORT UNIT (RSU) &bull; ${formNoticeEscape(formNoticeMeta.district).toUpperCase()}</div>
        <div style="font-size:10px;color:#64748b;margin-top:4px">LSU/RSU Education Portal &bull; Form Compliance</div>
      </header>
      <div class="form-notice-meta"><span><strong>Ref No:</strong> SELD/RSU-TAY/SC-FORM/${formNoticeEscape(formNoticeMeta.formId)}/${semis}</span><span><strong>Date:</strong> ${formNoticeEscape(formNoticeMeta.date)}</span></div>
      <section class="form-notice-addressee">
        <div><strong>To:</strong> The Head Master / Head Mistress</div>
        <div style="font-size:14px;font-weight:bold;margin-top:3px">${schoolName}</div>
        <div style="margin-top:5px;color:#475569">SEMIS Code: <strong>${semis}</strong> &nbsp;|&nbsp; Taluka: <strong>${taluka}</strong><br>Head: <strong>${hmName}</strong> &nbsp;|&nbsp; CNIC: <strong>${cnic}</strong></div>
      </section>
      <div style="font-size:12px;border-bottom:1px solid #cbd5e1;padding-bottom:8px"><strong>SUBJECT: SHOW CAUSE NOTICE — NON-SUBMISSION OF DISTRICT FORM: ${formNoticeEscape(formNoticeMeta.formTitle).toUpperCase()}</strong></div>
      <div class="form-notice-body">
        <p>District RSU records indicate that your school was assigned the above form, but a response has not been received as of the date of this notice.</p>
        <p>You are directed to submit the completed form through the School Portal within seven (07) calendar days of receiving this notice. If the portal no longer accepts responses because the form deadline has passed, contact the District RSU promptly to request reopening and provide a written explanation for the delay.</p>
        <p>Failure to comply or provide a satisfactory explanation may result in escalation to the competent district education authorities for further action.</p>
      </div>
      <section class="form-notice-directive"><strong>FORM DETAILS:</strong> ${formNoticeEscape(formNoticeMeta.formTitle)}<br><strong>Recorded portal deadline:</strong> ${formNoticeEscape(formNoticeMeta.closesAt)}<br><strong>Required action:</strong> Submit the form through the School Portal or provide a written explanation within seven (07) calendar days.</section>
      <footer class="form-notice-signatures">
        <div><strong>District RSU Coordinator</strong><br>Reform Support Unit (RSU), ${formNoticeEscape(formNoticeMeta.district)}<br>SELD &bull; Government of Sindh</div>
        <div><strong>District Education Officer (DEO)</strong><br>School Education &amp; Literacy Department<br>District ${formNoticeEscape(formNoticeMeta.district)}</div>
      </footer>
    </article>`;
  }).join('');
  document.getElementById('form-notice-modal').classList.remove('hidden');
}
function closeFormShowCauseNotices() {
  document.getElementById('form-notice-modal').classList.add('hidden');
}
function printFormShowCauseNotices() {
  const pages = document.getElementById('form-notice-print-container').innerHTML;
  if (!pages.trim()) {
    showToast('There are no notices to print.', 'error');
    return;
  }
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    showToast('Your browser blocked the print window. Allow pop-ups for this portal and try again.', 'error');
    return;
  }

  const printDocument = `<!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Show Cause Notices — ${formNoticeEscape(formNoticeMeta.formTitle)}</title>
      <style>
        @page{size:A4 portrait;margin:12mm}
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:#fff;color:#172033;font-family:Arial,sans-serif}
        .print-pages{width:100%}
        .form-notice-page{width:100%;min-height:265mm;padding:4mm 2mm;background:#fff;font-family:Arial,sans-serif;page-break-after:always;break-after:page}
        .form-notice-page:last-child{page-break-after:auto;break-after:auto}
        .form-notice-header{text-align:center;border-bottom:2px solid #123B63;padding-bottom:12px}
        .form-notice-meta{display:flex;justify-content:space-between;gap:12px;margin:16px 0;font-size:11px}
        .form-notice-addressee{background:#f8fafc;border:1px solid #cbd5e1;padding:12px;margin-bottom:14px;font-size:12px}
        .form-notice-body{font-size:12px;line-height:1.65;text-align:justify}
        .form-notice-directive{margin-top:16px;padding:12px;border:1px solid #fecaca;background:#fef2f2;font-size:12px;line-height:1.6}
        .form-notice-signatures{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:55px;text-align:center;font-size:11px}
        .form-notice-signatures>div{border-top:1px solid #64748b;padding-top:8px}
        @media print{html,body{width:100%;height:auto}.form-notice-page{min-height:265mm}}
      </style>
    </head>
    <body><main class="print-pages">${pages}</main></body>
    </html>`;
  printWindow.document.open();
  printWindow.document.write(printDocument);
  printWindow.document.close();
  window.setTimeout(() => {
    if (!printWindow.closed) {
      printWindow.focus();
      printWindow.print();
    }
  }, 250);
}
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});
</script>
</body>
</html>
