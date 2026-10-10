<?php
/**
 * school/forms.php — View and submit district forms assigned to this school.
 */
require_once __DIR__ . '/auth_guard.php';
require_once dirname(__DIR__) . '/includes/forms.php';

if (!is_school()) {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

$active_page = 'forms';
$page_title = 'District Forms — ' . $school_name;
$flash_error = '';
$flash_success = '';
$selectedFormId = trim((string)($_GET['form'] ?? $_POST['form_id'] ?? ''));
$allForms = ExcelDB::all('forms');
$formById = [];
foreach ($allForms as $row) $formById[$row['form_id'] ?? ''] = $row;
$allAssignments = ExcelDB::all('form_assignments');
$myAssignments = array_values(array_filter($allAssignments, fn($row) =>
    trim((string)($row['semis_code'] ?? '')) === trim($school_semis)
));
$assignmentByForm = [];
foreach ($myAssignments as $assignment) $assignmentByForm[$assignment['form_id'] ?? ''] = $assignment;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Security validation failed. Please refresh and try again.';
    } else {
        $form = $formById[$selectedFormId] ?? null;
        $assignment = $assignmentByForm[$selectedFormId] ?? null;
        if (!$form || !$assignment) {
            $flash_error = 'This form is not assigned to your school.';
        } elseif (!lsu_form_is_open($form)) {
            $flash_error = 'The form is closed. Responses can no longer be submitted or updated.';
        } else {
            $validation = lsu_form_validate_answers(lsu_form_questions($form), is_array($_POST['answers'] ?? null) ? $_POST['answers'] : []);
            if (!$validation['valid']) {
                $flash_error = $validation['error'];
            } else {
                $responses = ExcelDB::all('form_responses');
                $existingIndex = null;
                $maxId = 0;
                foreach ($responses as $index => $response) {
                    $maxId = max($maxId, (int)($response['id'] ?? 0));
                    if (($response['form_id'] ?? '') === $selectedFormId && trim((string)($response['semis_code'] ?? '')) === trim($school_semis)) {
                        $existingIndex = $index;
                    }
                }
                $now = date('Y-m-d H:i:s');
                $response = [
                    'id' => $existingIndex === null ? (string)($maxId + 1) : (string)$responses[$existingIndex]['id'],
                    'form_id' => $selectedFormId,
                    'semis_code' => $school_semis,
                    'school_name' => $school_name,
                    'answers_json' => json_encode($validation['answers'], JSON_UNESCAPED_UNICODE),
                    'submitted_at' => $existingIndex === null ? $now : ($responses[$existingIndex]['submitted_at'] ?? $now),
                    'updated_at' => $now,
                ];
                if ($existingIndex === null) $responses[] = $response;
                else $responses[$existingIndex] = $response;

                if (ExcelDB::writeTable('form_responses', $responses)) {
                    header('Location: ' . BASE_URL . '/school/forms.php?form=' . urlencode($selectedFormId) . '&submitted=1');
                    exit;
                }
                $flash_error = 'Your response could not be saved. Please try again.';
            }
        }
    }
}

$responses = ExcelDB::all('form_responses');
$myResponses = [];
foreach ($responses as $response) {
    if (trim((string)($response['semis_code'] ?? '')) === trim($school_semis)) {
        $myResponses[$response['form_id'] ?? ''] = $response;
    }
}

if ($selectedFormId !== '' && isset($assignmentByForm[$selectedFormId])) {
    $updatedAssignments = ExcelDB::all('form_assignments');
    $changed = false;
    foreach ($updatedAssignments as &$assignment) {
        if (($assignment['form_id'] ?? '') === $selectedFormId && trim((string)($assignment['semis_code'] ?? '')) === trim($school_semis)) {
            if (($assignment['unread_school'] ?? '0') === '1') {
                $assignment['unread_school'] = '0';
                $changed = true;
            }
            break;
        }
    }
    unset($assignment);
    if ($changed && !ExcelDB::writeTable('form_assignments', $updatedAssignments)) {
        $flash_error = 'The form was opened, but its notification could not be marked as read.';
    }
}

$selectedForm = $selectedFormId !== '' ? ($formById[$selectedFormId] ?? null) : null;
$selectedAssignment = $assignmentByForm[$selectedFormId] ?? null;
if ($selectedFormId !== '' && (!$selectedForm || !$selectedAssignment)) {
    $selectedForm = null;
    $flash_error = 'This form is not assigned to your school.';
}
$selectedResponse = $myResponses[$selectedFormId] ?? null;
$selectedQuestions = $selectedForm ? lsu_form_questions($selectedForm) : [];
$savedAnswers = $selectedResponse ? (json_decode((string)($selectedResponse['answers_json'] ?? ''), true) ?: []) : [];
$postedAnswers = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
$displayAnswers = $postedAnswers ?: $savedAnswers;
$selectedOpen = $selectedForm ? lsu_form_is_open($selectedForm) : false;

if (isset($_GET['submitted'])) $flash_success = 'Your response has been saved. You can update it until the deadline.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#123B63',primaryDark:'#0B2946',secondary:'#0F766E',surface:'#FFFFFF',background:'#F5F7FA',textMain:'#172033',muted:'#64748B',border:'#E2E8F0',success:'#15803D',warning:'#D97706',danger:'#DC2626',govGreen:'#046A38',govNavy:'#113459'},fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
  <style>body{font-family:'Inter',system-ui,sans-serif}.sidebar-link{transition:background .15s}.sidebar-link:hover{background:rgba(255,255,255,.08)}.sidebar-link.active{background:rgba(255,255,255,.14);border-left:3px solid #0F766E}#sidebar{transition:transform .25s cubic-bezier(.4,0,.2,1)}#overlay{transition:opacity .25s}</style>
</head>
<body class="bg-background text-textMain min-h-screen flex flex-col">
<div class="bg-primaryDark text-white text-xs py-1.5 px-4 flex items-center justify-between z-50 relative"><span><?= APP_GOVT ?> &nbsp;|&nbsp; <?= APP_DEPARTMENT ?></span><span class="hidden sm:block opacity-75"><?= APP_NAME ?></span></div>
<div id="overlay" class="fixed inset-0 bg-black/40 z-30 hidden opacity-0" onclick="closeSidebar()"></div>
<div class="flex flex-1 overflow-hidden">
  <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <main class="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">
      <div><h1 class="text-xl font-bold">Forms from District RSU</h1><p class="text-xs text-muted mt-1">Complete forms issued to <?= e($school_name) ?> before their closing date.</p></div>
      <?php if ($flash_success !== ''): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error !== ''): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($flash_error) ?></div><?php endif; ?>
      <?php if (!$myAssignments): ?>
        <div class="bg-white rounded-xl border border-border p-10 text-center"><div class="text-sm font-semibold">No forms assigned</div><p class="text-xs text-muted mt-1">Any forms issued by the District RSU will appear here.</p></div>
      <?php else: ?>
        <div class="grid xl:grid-cols-[minmax(260px,340px)_1fr] gap-4 items-start">
          <section class="bg-white rounded-xl border border-border overflow-hidden">
            <div class="px-4 py-3 border-b border-border font-bold text-sm">Assigned forms <span class="text-xs text-muted font-normal">(<?= count($myAssignments) ?>)</span></div>
            <div class="divide-y divide-border">
              <?php foreach ($myAssignments as $assignment):
                $id = (string)($assignment['form_id'] ?? '');
                $form = $formById[$id] ?? null;
                if (!$form) continue;
                $response = $myResponses[$id] ?? null;
                $isOpen = lsu_form_is_open($form);
                $state = $response ? ($isOpen ? 'Submitted · editable' : 'Submitted') : ($isOpen ? 'Needs response' : 'Closed · not submitted');
                $stateClass = $response ? 'text-emerald-700' : ($isOpen ? 'text-amber-700' : 'text-slate-500');
              ?>
                <a href="<?= BASE_URL ?>/school/forms.php?form=<?= urlencode($id) ?>" class="block px-4 py-3 hover:bg-slate-50 <?= $selectedFormId === $id ? 'bg-emerald-50/70 border-l-4 border-emerald-600' : '' ?>">
                  <div class="text-xs font-semibold"><?= e($form['title'] ?? 'District form') ?></div>
                  <div class="text-[11px] mt-1 <?= $stateClass ?>"><?= e($state) ?></div>
                  <div class="text-[10px] text-muted mt-1">Closes <?= !empty($form['closes_at']) ? e(date('M d, Y h:i A', strtotime($form['closes_at']))) : '—' ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="bg-white rounded-xl border border-border p-5 md:p-7">
            <?php if (!$selectedForm): ?>
              <div class="py-10 text-center text-xs text-muted">Select a form to view its instructions and respond.</div>
            <?php else: ?>
              <?php $statusLabel = lsu_form_status_label($selectedForm); ?>
              <div class="border-b border-border pb-4 mb-5">
                <div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-bold"><?= e($selectedForm['title']) ?></h2><span class="rounded-full px-2.5 py-1 text-[10px] font-bold <?= $selectedOpen ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= $selectedOpen ? 'Open' : e($statusLabel) ?></span></div>
                <?php if (!empty($selectedForm['description'])): ?><p class="text-sm text-muted mt-3 whitespace-pre-wrap"><?= e($selectedForm['description']) ?></p><?php endif; ?>
                <div class="text-[11px] text-muted mt-3">Deadline: <strong><?= !empty($selectedForm['closes_at']) ? e(date('M d, Y h:i A', strtotime($selectedForm['closes_at']))) : '—' ?></strong></div>
                <?php if ($selectedResponse): ?><p class="text-[11px] text-emerald-700 mt-1">Response submitted <?= e(date('M d, Y h:i A', strtotime($selectedResponse['submitted_at'] ?? 'now'))) ?><?= $selectedOpen ? ' · You may update it before the deadline.' : '' ?></p><?php endif; ?>
              </div>
              <?php if (!$selectedOpen && !$selectedResponse): ?>
                <p class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 p-4 text-sm">This form is closed and your school did not submit a response before the deadline.</p>
              <?php elseif ($selectedQuestions): ?>
                <form method="POST" class="space-y-5">
                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="form_id" value="<?= e($selectedFormId) ?>">
                  <?php foreach ($selectedQuestions as $index => $question):
                    $qid = (string)($question['id'] ?? '');
                    $value = $displayAnswers[$qid] ?? '';
                    $required = !empty($question['required']);
                    $requiredAttribute = $required && ($question['type'] ?? '') !== 'checkbox';
                  ?>
                    <fieldset class="space-y-2">
                      <legend class="text-sm font-semibold"><?= ($index + 1) ?>. <?= e($question['label'] ?? '') ?><?= $required ? ' *' : '' ?></legend>
                      <?php if (in_array($question['type'] ?? '', ['short_text','long_text','number','date'], true)): ?>
                        <?php $inputType = ($question['type'] ?? '') === 'number' ? 'number' : (($question['type'] ?? '') === 'date' ? 'date' : 'text'); ?>
                        <?php if (($question['type'] ?? '') === 'long_text'): ?>
                          <textarea name="answers[<?= e($qid) ?>]" rows="4" maxlength="5000" <?= $requiredAttribute ? 'required' : '' ?> <?= !$selectedOpen ? 'disabled' : '' ?> class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary"><?= e(is_array($value) ? '' : $value) ?></textarea>
                        <?php else: ?>
                          <input type="<?= e($inputType) ?>" name="answers[<?= e($qid) ?>]" value="<?= e(is_array($value) ? '' : $value) ?>" <?= $inputType === 'text' ? 'maxlength="250"' : '' ?> <?= $requiredAttribute ? 'required' : '' ?> <?= !$selectedOpen ? 'disabled' : '' ?> class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
                        <?php endif; ?>
                      <?php elseif (($question['type'] ?? '') === 'dropdown'): ?>
                        <select name="answers[<?= e($qid) ?>]" <?= $requiredAttribute ? 'required' : '' ?> <?= !$selectedOpen ? 'disabled' : '' ?> class="w-full border border-border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:border-primary"><option value="">Select an answer</option><?php foreach (($question['options'] ?? []) as $option): ?><option value="<?= e($option) ?>" <?= $value === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select>
                      <?php elseif (in_array($question['type'] ?? '', ['radio','checkbox'], true)): ?>
                        <div class="space-y-2"><?php foreach (($question['options'] ?? []) as $option): $checked = is_array($value) ? in_array($option, $value, true) : $value === $option; ?>
                          <label class="flex items-center gap-2 text-sm"><input type="<?= ($question['type'] ?? '') === 'radio' ? 'radio' : 'checkbox' ?>" name="answers[<?= e($qid) ?>]<?= ($question['type'] ?? '') === 'checkbox' ? '[]' : '' ?>" value="<?= e($option) ?>" <?= $checked ? 'checked' : '' ?> <?= $requiredAttribute ? 'required' : '' ?> <?= !$selectedOpen ? 'disabled' : '' ?>><?= e($option) ?></label>
                        <?php endforeach; ?></div>
                      <?php endif; ?>
                    </fieldset>
                  <?php endforeach; ?>
                  <?php if ($selectedOpen): ?><button type="submit" class="px-5 py-2.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold"><?= $selectedResponse ? 'Update response' : 'Submit response' ?></button><?php endif; ?>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </section>
        </div>
      <?php endif; ?>
    </main>
    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>
<script>function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});</script>
</body>
</html>
