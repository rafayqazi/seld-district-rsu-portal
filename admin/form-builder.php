<?php
/**
 * admin/form-builder.php — Draft and issue school forms.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/forms.php';
require_admin();

$active_page = 'forms';
$formId = trim((string)($_GET['id'] ?? $_POST['form_id'] ?? ''));
$form = $formId !== '' ? ExcelDB::find('forms', 'form_id', $formId) : null;
$flash_error = '';

if ($formId !== '' && (!$form || ($form['status'] ?? '') !== 'Draft')) {
    header('Location: ' . BASE_URL . '/admin/forms.php');
    exit;
}

$allSchools = ExcelDB::all('schools');
usort($allSchools, fn($a, $b) => strcasecmp($a['school_name'] ?? '', $b['school_name'] ?? ''));
$validLevels = ['Primary', 'Middle', 'Secondary', 'Higher Secondary'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'save_draft');
        $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
        $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
        $targetType = is_string($_POST['target_type'] ?? null) ? $_POST['target_type'] : '';
        $questionsJson = is_string($_POST['questions_json'] ?? null) ? $_POST['questions_json'] : '';
        $questionsResult = lsu_form_validate_questions($questionsJson);
        $errors = [];

        if ($title === '' || mb_strlen($title) > 160) $errors[] = 'Enter a form title of up to 160 characters.';
        if (mb_strlen($description) > 2000) $errors[] = 'The form description must be 2,000 characters or fewer.';
        if (!$questionsResult['valid']) $errors[] = $questionsResult['error'];

        $targetValue = '';
        $recipientSchools = [];
        if ($targetType === 'level') {
            $targetValue = is_string($_POST['target_level'] ?? null) ? trim($_POST['target_level']) : '';
            if (!in_array($targetValue, $validLevels, true)) {
                $errors[] = 'Choose a valid school level.';
            } else {
                foreach ($allSchools as $school) {
                    if (strcasecmp(trim((string)($school['level'] ?? '')), $targetValue) === 0) {
                        $recipientSchools[] = $school;
                    }
                }
            }
        } elseif ($targetType === 'schools') {
            $selectedSemis = $_POST['target_semis'] ?? [];
            if (!is_array($selectedSemis)) {
                $errors[] = 'Choose one or more valid schools.';
                $selectedSemis = [];
            }
            foreach ($selectedSemis as $code) {
                if (!is_scalar($code)) {
                    $errors[] = 'One or more selected schools are invalid.';
                    continue;
                }
                $normalizedCode = trim((string)$code);
                if ($normalizedCode !== '') $recipientSchools[] = ExcelDB::getSchoolBySemis($normalizedCode) ?: [];
            }
            $recipientSchools = array_values(array_filter($recipientSchools));
            $selectedSemis = array_values(array_unique(array_filter(array_map(fn($code) => is_scalar($code) ? trim((string)$code) : '', $selectedSemis))));
            if (count($recipientSchools) !== count($selectedSemis)) {
                $errors[] = 'One or more selected schools could not be found.';
                $recipientSchools = [];
            }
            if (!$selectedSemis) $errors[] = 'Select at least one school.';
            $targetValue = json_encode($selectedSemis);
        } else {
            $errors[] = 'Choose whether to issue the form by school level or selected schools.';
        }

        if ($action === 'publish') {
            $durationDays = filter_var($_POST['duration_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
            if ($durationDays === false) $errors[] = 'Before publishing, choose a closing period between 1 and 365 days.';
            if (!$recipientSchools) $errors[] = 'No schools match this target. The form cannot be published.';
        } elseif ($action !== 'save_draft') {
            $errors[] = 'The requested action is invalid.';
        }

        if (!$errors) {
            $now = date('Y-m-d H:i:s');
            if ($form) {
                $form['title'] = $title;
                $form['description'] = $description;
                $form['questions_json'] = json_encode($questionsResult['questions'], JSON_UNESCAPED_UNICODE);
                $form['target_type'] = $targetType;
                $form['target_value'] = $targetValue;
                $form['updated_at'] = $now;
                $formId = (string)$form['form_id'];
                $forms = ExcelDB::all('forms');
                foreach ($forms as &$row) {
                    if (($row['form_id'] ?? '') === $formId && ($row['status'] ?? '') === 'Draft') {
                        $row = array_merge($row, $form);
                        break;
                    }
                }
                unset($row);
            } else {
                do {
                    $formId = 'FORM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                } while (ExcelDB::find('forms', 'form_id', $formId));
                $form = [
                    'form_id' => $formId,
                    'title' => $title,
                    'description' => $description,
                    'questions_json' => json_encode($questionsResult['questions'], JSON_UNESCAPED_UNICODE),
                    'target_type' => $targetType,
                    'target_value' => $targetValue,
                    'status' => 'Draft',
                    'created_by' => $_SESSION['lsu_username'] ?? 'District RSU',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'published_at' => '',
                    'closes_at' => '',
                ];
                $forms = ExcelDB::all('forms');
                $forms[] = $form;
            }

            if (!ExcelDB::writeTable('forms', $forms)) {
                $flash_error = 'The form draft could not be saved. Please try again.';
            } elseif ($action === 'save_draft') {
                header('Location: ' . BASE_URL . '/admin/forms.php?saved=1');
                exit;
            } else {
                $form['status'] = 'Published';
                $form['published_at'] = $now;
                $form['closes_at'] = date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days', strtotime($now)));
                $form['updated_at'] = $now;
                $publishedForms = $forms;
                foreach ($publishedForms as &$row) {
                    if (($row['form_id'] ?? '') === $formId) {
                        $row = array_merge($row, $form);
                        break;
                    }
                }
                unset($row);

                $assignments = ExcelDB::all('form_assignments');
                $maxAssignmentId = 0;
                foreach ($assignments as $assignment) {
                    $maxAssignmentId = max($maxAssignmentId, (int)($assignment['id'] ?? 0));
                }
                foreach ($recipientSchools as $school) {
                    $assignments[] = [
                        'id' => (string)++$maxAssignmentId,
                        'form_id' => $formId,
                        'semis_code' => trim((string)($school['semis_code'] ?? '')),
                        'school_name' => (string)($school['school_name'] ?? ''),
                        'assigned_at' => $now,
                        'unread_school' => '1',
                    ];
                }

                if (!ExcelDB::writeTable('form_assignments', $assignments)) {
                    $flash_error = 'Form recipients could not be saved. The form is still a draft.';
                } elseif (!ExcelDB::writeTable('forms', $publishedForms)) {
                    $allAssignments = ExcelDB::all('form_assignments');
                    $allAssignments = array_values(array_filter($allAssignments, fn($row) => ($row['form_id'] ?? '') !== $formId));
                    if (!ExcelDB::writeTable('form_assignments', $allAssignments)) {
                        $flash_error = 'Publishing failed and recipient cleanup also failed. Contact the system administrator before retrying.';
                    } else {
                        $flash_error = 'The form could not be published because its record could not be saved. It remains a draft.';
                    }
                } else {
                    header('Location: ' . BASE_URL . '/admin/form-report.php?id=' . urlencode($formId) . '&published=1');
                    exit;
                }
            }
        } else {
            $flash_error = implode(' ', array_unique($errors));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $flash_error !== '') {
    $postedTargetType = (string)($_POST['target_type'] ?? 'level');
    $postedTargetValue = $postedTargetType === 'schools'
        ? json_encode(array_values(array_unique(array_filter(array_map(fn($code) => is_scalar($code) ? trim((string)$code) : '', is_array($_POST['target_semis'] ?? null) ? $_POST['target_semis'] : [])))))
        : (is_string($_POST['target_level'] ?? null) ? trim($_POST['target_level']) : 'Primary');
    $form = array_merge($form ?? [], [
        'title' => (string)($_POST['title'] ?? ''),
        'description' => (string)($_POST['description'] ?? ''),
        'questions_json' => (string)($_POST['questions_json'] ?? ''),
        'target_type' => $postedTargetType,
        'target_value' => $postedTargetValue,
    ]);
}

if (!$form && $formId !== '') {
    $form = ExcelDB::find('forms', 'form_id', $formId);
}
$targetType = (string)($form['target_type'] ?? 'level');
$targetValue = (string)($form['target_value'] ?? '');
$selectedSemis = $targetType === 'schools' ? (json_decode($targetValue, true) ?: []) : [];
$selectedLevel = $targetType === 'level' ? $targetValue : 'Primary';
$questions = $form ? lsu_form_questions($form) : [];
$page_title = ($form ? 'Edit Form Draft' : 'Create Form') . ' — ' . APP_NAME;
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
      <div class="flex items-center justify-between gap-3">
        <div><a href="<?= BASE_URL ?>/admin/forms.php" class="text-xs text-primary hover:underline">← Forms</a><h1 class="text-xl font-bold mt-1"><?= $form ? 'Edit Draft' : 'Create a Form' ?></h1><p class="text-xs text-muted mt-1">Write questions, choose the schools and publish when ready.</p></div>
      </div>
      <?php if ($flash_error !== ''): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($flash_error) ?></div><?php endif; ?>
      <form id="formBuilder" method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($form): ?><input type="hidden" name="form_id" value="<?= e($form['form_id']) ?>"><?php endif; ?>
        <input type="hidden" name="questions_json" id="questionsJson" value="">
        <section class="bg-white rounded-xl border border-border p-5 space-y-4 shadow-sm">
          <div><label class="block text-xs font-semibold mb-1">Form title <span class="text-danger">*</span></label><input name="title" required maxlength="160" value="<?= e($form['title'] ?? '') ?>" placeholder="e.g. Monthly School Facilities Report" class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary"></div>
          <div><label class="block text-xs font-semibold mb-1">Description / instructions</label><textarea name="description" rows="3" maxlength="2000" placeholder="Explain what schools need to provide..." class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary"><?= e($form['description'] ?? '') ?></textarea></div>
        </section>

        <section class="bg-white rounded-xl border border-border p-5 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div><h2 class="font-bold text-sm">Issue to schools</h2><p class="text-xs text-muted mt-1">Pick one school level or select one/more individual schools.</p></div>
          </div>
          <div class="flex gap-5 mt-4 text-xs">
            <label class="flex items-center gap-2"><input type="radio" name="target_type" value="level" <?= $targetType === 'level' ? 'checked' : '' ?>> By school level</label>
            <label class="flex items-center gap-2"><input type="radio" name="target_type" value="schools" <?= $targetType === 'schools' ? 'checked' : '' ?>> Selected schools</label>
          </div>
          <div id="levelTarget" class="mt-3 <?= $targetType === 'level' ? '' : 'hidden' ?>">
            <label class="block text-xs font-semibold mb-1">School level</label>
            <select name="target_level" class="w-full sm:w-80 border border-border rounded-lg px-3 py-2 text-sm bg-white">
              <?php foreach ($validLevels as $level): ?><option value="<?= e($level) ?>" <?= $selectedLevel === $level ? 'selected' : '' ?>><?= e($level) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div id="schoolsTarget" class="mt-3 <?= $targetType === 'schools' ? '' : 'hidden' ?>">
            <label for="schoolSearch" class="block text-xs font-semibold mb-1">Find a school</label>
            <input id="schoolSearch" type="search" placeholder="Search by school name or SEMIS code..." class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
            <div id="schoolList" class="mt-2 max-h-60 overflow-y-auto border border-border rounded-lg divide-y divide-border">
              <?php foreach ($allSchools as $school):
                $semis = (string)($school['semis_code'] ?? '');
                $checked = in_array($semis, $selectedSemis, true);
              ?>
                <label class="school-choice flex items-center gap-3 px-3 py-2.5 hover:bg-slate-50 text-xs" data-search="<?= e(strtolower(($school['school_name'] ?? '') . ' ' . $semis)) ?>">
                  <input type="checkbox" name="target_semis[]" value="<?= e($semis) ?>" <?= $checked ? 'checked' : '' ?> class="rounded">
                  <span class="flex-1"><?= e($school['school_name'] ?? '') ?></span><span class="font-mono text-muted"><?= e($semis) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <p id="selectedSchoolCount" class="text-[11px] text-muted mt-1"></p>
          </div>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3"><div><h2 class="font-bold text-sm">Questions</h2><p class="text-xs text-muted mt-1">Add up to 50 questions. Choice options go one per line.</p></div><button id="addQuestion" type="button" class="px-3 py-2 bg-primary/10 text-primary rounded-lg text-xs font-bold hover:bg-primary/20">+ Add question</button></div>
          <div id="questionList" class="space-y-3"></div>
        </section>

        <section class="bg-blue-50 border border-blue-100 rounded-xl p-4">
          <label for="durationDays" class="block text-xs font-bold text-primary">Close this form after how many days? <span class="text-danger">*</span></label>
          <p class="text-[11px] text-muted mt-1">Required when publishing. Schools can submit or update one response per school until the deadline. Use 1–365 days.</p>
          <div class="flex items-center gap-2 mt-3"><input id="durationDays" name="duration_days" type="number" min="1" max="365" value="7" class="w-24 border border-border rounded-lg px-3 py-2 text-sm"><span class="text-xs text-muted">days from publishing</span></div>
        </section>
        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
          <button type="submit" name="action" value="save_draft" class="px-4 py-2 rounded-lg border border-border bg-white text-sm font-semibold hover:bg-slate-50">Save as draft</button>
          <button type="submit" name="action" value="publish" class="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-semibold">Publish to selected schools</button>
        </div>
      </form>
    </main>
    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>
<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
function toggleNotif(){document.getElementById('notif-dropdown').classList.toggle('hidden');}
document.addEventListener('click',function(e){const b=document.getElementById('notif-btn');const d=document.getElementById('notif-dropdown');if(b&&d&&!b.contains(e.target)&&!d.contains(e.target))d.classList.add('hidden');});

const questionTypes = [
  ['short_text','Short answer'],['long_text','Paragraph'],['number','Number'],['date','Date'],
  ['radio','Multiple choice'],['checkbox','Checkboxes'],['dropdown','Dropdown']
];
const initialQuestions = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const questionList = document.getElementById('questionList');
function renderQuestions(items) {
  questionList.replaceChildren();
  items.forEach((item, index) => {
    const card = document.createElement('div');
    card.className = 'question-card bg-white rounded-xl border border-border p-4 shadow-sm';
    card.dataset.index = String(index);
    const top = document.createElement('div');
    top.className = 'flex gap-2 items-start';
    const label = document.createElement('input');
    label.type = 'text'; label.maxLength = 250; label.required = true; label.placeholder = 'Question'; label.value = item.label || '';
    label.className = 'question-label flex-1 border-b-2 border-slate-200 focus:border-primary outline-none px-1 py-2 text-sm font-semibold';
    const type = document.createElement('select');
    type.className = 'question-type border border-border rounded-lg px-2 py-2 text-xs bg-white';
    questionTypes.forEach(([value,text]) => { const option=document.createElement('option');option.value=value;option.textContent=text;type.appendChild(option); });
    type.value = item.type || 'short_text';
    const moveUp = document.createElement('button');moveUp.type='button';moveUp.textContent='↑';moveUp.title='Move up';moveUp.className='px-2 py-2 text-muted hover:text-primary';
    const moveDown = document.createElement('button');moveDown.type='button';moveDown.textContent='↓';moveDown.title='Move down';moveDown.className='px-2 py-2 text-muted hover:text-primary';
    const remove = document.createElement('button');remove.type='button';remove.textContent='Remove';remove.className='px-2 py-2 text-xs text-red-600 hover:bg-red-50 rounded';
    top.append(label,type,moveUp,moveDown,remove);
    const options = document.createElement('textarea');
    options.className='question-options mt-3 w-full border border-border rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-primary';
    options.rows=3;options.placeholder='Enter one option per line';options.value=Array.isArray(item.options)?item.options.join('\n'):'';
    const choice = ['radio','checkbox','dropdown'].includes(type.value);
    options.classList.toggle('hidden',!choice);
    const bottom=document.createElement('div');bottom.className='flex items-center justify-between mt-2';
    const requiredLabel=document.createElement('label');requiredLabel.className='flex items-center gap-2 text-xs text-muted';
    const required=document.createElement('input');required.type='checkbox';required.className='question-required';required.checked=item.required !== false;
    requiredLabel.append(required,document.createTextNode('Required'));
    const counter=document.createElement('span');counter.className='text-[11px] text-muted';counter.textContent=`Question ${index+1}`;
    bottom.append(requiredLabel,counter);
    card.append(top,options,bottom);questionList.appendChild(card);
    type.addEventListener('change',()=>options.classList.toggle('hidden',!['radio','checkbox','dropdown'].includes(type.value)));
    remove.addEventListener('click',()=>{if(questionList.children.length>1){card.remove();updateQuestionLabels();}else{label.value='';type.value='short_text';options.value='';required.checked=true;options.classList.add('hidden');}});
    moveUp.addEventListener('click',()=>{if(card.previousElementSibling)questionList.insertBefore(card,card.previousElementSibling);updateQuestionLabels();});
    moveDown.addEventListener('click',()=>{if(card.nextElementSibling)questionList.insertBefore(card.nextElementSibling,card);updateQuestionLabels();});
  });
  updateQuestionLabels();
}
function updateQuestionLabels(){questionList.querySelectorAll('.question-card').forEach((card,i)=>card.querySelector('span').textContent=`Question ${i+1}`);}
function serializeQuestions(){
  return [...questionList.querySelectorAll('.question-card')].map(card=>({
    label:card.querySelector('.question-label').value.trim(),
    type:card.querySelector('.question-type').value,
    required:card.querySelector('.question-required').checked,
    options:card.querySelector('.question-options').value.split(/\r?\n/).map(value=>value.trim()).filter(Boolean)
  }));
}
renderQuestions(initialQuestions.length ? initialQuestions : [{label:'',type:'short_text',required:true,options:[]}]);
document.getElementById('addQuestion').addEventListener('click',()=>{if(questionList.children.length>=50){showToast('A form can have at most 50 questions.','error');return;}renderQuestions([...serializeQuestions(),{label:'',type:'short_text',required:true,options:[]}]);});
document.querySelectorAll('input[name="target_type"]').forEach(input=>input.addEventListener('change',()=>{
  document.getElementById('levelTarget').classList.toggle('hidden',input.value!=='level');
  document.getElementById('schoolsTarget').classList.toggle('hidden',input.value!=='schools');
}));
const schoolSearch=document.getElementById('schoolSearch');
schoolSearch.addEventListener('input',()=>document.querySelectorAll('.school-choice').forEach(row=>row.classList.toggle('hidden',!row.dataset.search.includes(schoolSearch.value.trim().toLowerCase()))));
function updateSelectedCount(){document.getElementById('selectedSchoolCount').textContent=`${document.querySelectorAll('input[name="target_semis[]"]:checked').length} school(s) selected`;}
document.querySelectorAll('input[name="target_semis[]"]').forEach(input=>input.addEventListener('change',updateSelectedCount));updateSelectedCount();
document.getElementById('formBuilder').addEventListener('submit',event=>{
  const questions=serializeQuestions();
  document.getElementById('questionsJson').value=JSON.stringify(questions);
  if(event.submitter && event.submitter.value==='publish'){
    const days=Number(document.getElementById('durationDays').value);
    if(!Number.isInteger(days)||days<1||days>365){event.preventDefault();showToast('Choose a closing period between 1 and 365 days before publishing.','error');}
  }
});
</script>
</body>
</html>
