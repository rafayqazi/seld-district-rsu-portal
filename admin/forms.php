<?php
/**
 * admin/forms.php — Create and monitor school forms.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/forms.php';
require_admin();

$active_page = 'forms';
$page_title = 'School Forms — ' . APP_NAME;
$flash_error = '';
$flash_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $formId = trim((string)($_POST['form_id'] ?? ''));
        $form = $formId !== '' ? ExcelDB::find('forms', 'form_id', $formId) : null;

        if (!$form) {
            $flash_error = 'The selected form could not be found.';
        } elseif ($action === 'close_form' && ($form['status'] ?? '') === 'Published') {
            $forms = ExcelDB::all('forms');
            foreach ($forms as &$row) {
                if (($row['form_id'] ?? '') === $formId) {
                    $row['status'] = 'Closed';
                    $row['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($row);
            if (ExcelDB::writeTable('forms', $forms)) {
                header('Location: ' . BASE_URL . '/admin/forms.php?closed=1');
                exit;
            } else {
                $flash_error = 'The form could not be closed because its data could not be saved.';
            }
        } elseif ($action === 'extend_form' && in_array($form['status'] ?? '', ['Published', 'Closed'], true)) {
            $days = filter_var($_POST['extend_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
            if ($days === false) {
                $flash_error = 'Enter an extension between 1 and 365 days.';
            } else {
                $now = time();
                $currentClose = strtotime((string)($form['closes_at'] ?? '')) ?: $now;
                $baseTimestamp = max($now, $currentClose);
                $newClose = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $baseTimestamp));
                $forms = ExcelDB::all('forms');
                foreach ($forms as &$row) {
                    if (($row['form_id'] ?? '') === $formId) {
                        $row['status'] = 'Published';
                        $row['closes_at'] = $newClose;
                        $row['updated_at'] = date('Y-m-d H:i:s');
                        break;
                    }
                }
                unset($row);
                if (ExcelDB::writeTable('forms', $forms)) {
                    header('Location: ' . BASE_URL . '/admin/forms.php?extended=' . urlencode($newClose));
                    exit;
                } else {
                    $flash_error = 'The form deadline could not be updated because its data could not be saved.';
                }
            }
        } else {
            $flash_error = 'That action is not available for this form.';
        }
    }
}

if (isset($_GET['saved'])) $flash_success = 'Draft saved successfully.';
if (isset($_GET['closed'])) $flash_success = 'Form closed. Schools can no longer submit or update responses.';
if (isset($_GET['extended'])) $flash_success = 'Form deadline extended successfully.';

$forms = ExcelDB::all('forms');
usort($forms, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
$assignments = ExcelDB::all('form_assignments');
$responses = ExcelDB::all('form_responses');
$responseCounts = [];
foreach ($responses as $response) {
    $responseCounts[$response['form_id'] ?? ''] = ($responseCounts[$response['form_id'] ?? ''] ?? 0) + 1;
}

$totalForms = count($forms);
$publishedForms = count(array_filter($forms, fn($form) => lsu_form_is_open($form)));
$totalAssigned = count($assignments);
$totalSubmitted = count($responses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
  <style>
    body{font-family:'Inter',system-ui,sans-serif}
    .sidebar-link{transition:background .15s}.sidebar-link:hover{background:rgba(255,255,255,.08)}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E}
    #sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1)}#overlay{transition:opacity .25s}
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
        <div>
          <h1 class="text-xl font-bold">School Forms</h1>
          <p class="text-xs text-muted mt-1">Build forms, issue them to schools and track submissions before the deadline.</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/form-builder.php" class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primaryDark text-white text-sm font-semibold rounded-lg transition">
          <span class="text-lg leading-none">+</span> Create Form
        </a>
      </div>

      <?php if ($flash_success !== ''): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error !== ''): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($flash_error) ?></div><?php endif; ?>

      <section class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        <?php foreach ([['All forms', $totalForms, 'text-primary'], ['Currently open', $publishedForms, 'text-emerald-700'], ['School assignments', $totalAssigned, 'text-blue-700'], ['Responses received', $totalSubmitted, 'text-violet-700']] as [$label, $value, $color]): ?>
          <div class="bg-white rounded-xl border border-border p-4 shadow-sm"><div class="text-xs text-muted"><?= e($label) ?></div><div class="text-2xl font-bold mt-1 <?= e($color) ?>"><?= (int)$value ?></div></div>
        <?php endforeach; ?>
      </section>

      <section class="bg-white rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-border flex items-center justify-between">
          <h2 class="font-bold text-sm">Forms &amp; response progress</h2>
          <span class="text-xs text-muted"><?= count($forms) ?> total</span>
        </div>
        <?php if (!$forms): ?>
          <div class="p-10 text-center">
            <div class="text-sm font-semibold">No forms created yet</div>
            <p class="text-xs text-muted mt-1">Create a draft, add questions and publish it to selected schools.</p>
            <a href="<?= BASE_URL ?>/admin/form-builder.php" class="inline-block mt-4 px-4 py-2 bg-primary text-white rounded-lg text-xs font-semibold">Create your first form</a>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead><tr class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-muted">
                <th class="px-4 py-3">Form</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Progress</th><th class="px-4 py-3">Deadline</th><th class="px-4 py-3 text-right">Actions</th>
              </tr></thead>
              <tbody class="divide-y divide-border">
              <?php foreach ($forms as $form):
                $formId = (string)($form['form_id'] ?? '');
                $assignedCount = count(array_filter($assignments, fn($row) => ($row['form_id'] ?? '') === $formId));
                $submittedCount = (int)($responseCounts[$formId] ?? 0);
                $status = lsu_form_status_label($form);
                $statusClass = $status === 'Published' ? 'bg-emerald-50 text-emerald-700' : ($status === 'Draft' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600');
                $targetValue = json_decode((string)($form['target_value'] ?? ''), true);
                $targetLabel = ($form['target_type'] ?? '') === 'level'
                    ? (string)($form['target_value'] ?? '') . ' schools'
                    : (is_array($targetValue) ? count($targetValue) . ' selected schools' : 'Selected schools');
                $percent = $assignedCount > 0 ? (int)round($submittedCount * 100 / $assignedCount) : 0;
              ?>
                <tr class="hover:bg-slate-50/70">
                  <td class="px-4 py-3 min-w-[230px]">
                    <div class="font-semibold text-xs"><?= e($form['title'] ?? 'Untitled form') ?></div>
                    <div class="text-[11px] text-muted mt-1"><?= e($formId) ?> · <?= e($targetLabel) ?></div>
                  </td>
                  <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold <?= e($statusClass) ?>"><?= e($status) ?></span></td>
                  <td class="px-4 py-3 min-w-[180px]">
                    <?php if ($assignedCount > 0): ?>
                      <div class="flex items-center justify-between text-[11px] mb-1"><span class="font-semibold"><?= $submittedCount ?>/<?= $assignedCount ?> schools</span><span class="text-muted"><?= $percent ?>%</span></div>
                      <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-emerald-500 rounded-full" style="width:<?= $percent ?>%"></div></div>
                    <?php else: ?><span class="text-xs text-muted">Not issued</span><?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-xs text-muted whitespace-nowrap"><?= !empty($form['closes_at']) ? e(date('M d, Y h:i A', strtotime($form['closes_at']))) : '—' ?></td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-2 flex-wrap">
                      <?php if ($status === 'Draft'): ?>
                        <a href="<?= BASE_URL ?>/admin/form-builder.php?id=<?= urlencode($formId) ?>" class="px-3 py-1.5 rounded-md bg-primary/10 text-primary text-[11px] font-semibold hover:bg-primary/20">Edit draft</a>
                      <?php else: ?>
                        <a href="<?= BASE_URL ?>/admin/form-report.php?id=<?= urlencode($formId) ?>" class="px-3 py-1.5 rounded-md bg-primary/10 text-primary text-[11px] font-semibold hover:bg-primary/20">View progress</a>
                        <form method="POST" class="flex items-center gap-1">
                          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="form_id" value="<?= e($formId) ?>">
                          <input type="hidden" name="action" value="extend_form">
                          <input aria-label="Extension days" type="number" name="extend_days" min="1" max="365" value="7" class="w-14 px-1.5 py-1 border border-border rounded text-[11px]">
                          <button type="submit" class="px-2.5 py-1.5 rounded-md bg-blue-50 text-blue-700 text-[11px] font-semibold hover:bg-blue-100">Extend days</button>
                        </form>
                        <?php if (($form['status'] ?? '') === 'Published' && lsu_form_is_open($form)): ?>
                          <form method="POST" onsubmit="return confirmFormSubmit(event,this,'Close this form now? Schools will no longer be able to submit or update responses.',{title:'Close Form',okText:'Close Form',isDanger:true})">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="form_id" value="<?= e($formId) ?>"><input type="hidden" name="action" value="close_form">
                            <button type="submit" class="px-2.5 py-1.5 rounded-md bg-red-50 text-red-700 text-[11px] font-semibold hover:bg-red-100">Close now</button>
                          </form>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
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
