<?php
/**
 * admin/announcements.php — Create and manage school announcements.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/config.php';
require_admin();

$active_page = 'announcements';
$page_title = 'Announcements & Popups — ' . APP_NAME;
$flash_error = '';
$flash_success = '';
$schools = ExcelDB::all('schools');
$levels = array_values(array_unique(array_filter(array_map(
    fn($school) => trim((string)($school['level'] ?? '')),
    $schools
))));
sort($levels);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash_error = 'Security validation failed. Please refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $title = trim((string)($_POST['title'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            $targetType = (string)($_POST['target_type'] ?? '');
            $targetValue = trim((string)($_POST['target_value'] ?? ''));
            $titleLength = preg_match_all('/./us', $title);
            $messageLength = preg_match_all('/./us', $message);
            $targetExists = $targetType === 'school'
                ? count(array_filter($schools, fn($school) => (string)($school['semis_code'] ?? '') === $targetValue)) > 0
                : ($targetType === 'level' && in_array($targetValue, $levels, true));

            if ($title === '' || $titleLength === false || $titleLength > 120) {
                $flash_error = 'Enter an announcement title of no more than 120 characters.';
            } elseif ($message === '' || $messageLength === false || $messageLength > 10000) {
                $flash_error = 'Enter an announcement message of no more than 10,000 characters.';
            } elseif (!$targetExists) {
                $flash_error = 'Choose a valid school or school level to receive this announcement.';
            } else {
                $imageUrl = '';
                $uploadedImagePath = '';
                $upload = $_FILES['announcement_image'] ?? null;
                $uploadError = is_array($upload) ? (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        $flash_error = 'The image upload failed. Please choose another image and try again.';
                    } elseif ((int)($upload['size'] ?? 0) > 5 * 1024 * 1024) {
                        $flash_error = 'Announcement images must be 5 MB or smaller.';
                    } else {
                        $imageTypes = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                            'image/gif' => 'gif',
                        ];
                        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = $fileInfo ? finfo_file($fileInfo, (string)($upload['tmp_name'] ?? '')) : false;
                        if ($fileInfo) finfo_close($fileInfo);
                        $imageInfo = @getimagesize((string)($upload['tmp_name'] ?? ''));
                        if (!is_string($mimeType) || !isset($imageTypes[$mimeType]) || !$imageInfo || ($imageInfo['mime'] ?? '') !== $mimeType) {
                            $flash_error = 'Use a valid JPG, PNG, WEBP, or GIF image.';
                        } else {
                            $uploadDirectory = ROOT_PATH . '/assets/uploads/announcements';
                            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                                $flash_error = 'The image upload directory could not be created.';
                            } else {
                                $fileName = 'announcement_' . bin2hex(random_bytes(12)) . '.' . $imageTypes[$mimeType];
                                $uploadedImagePath = $uploadDirectory . '/' . $fileName;
                                if (move_uploaded_file((string)$upload['tmp_name'], $uploadedImagePath)) {
                                    $imageUrl = BASE_URL . '/assets/uploads/announcements/' . $fileName;
                                } else {
                                    $uploadedImagePath = '';
                                    $flash_error = 'The announcement image could not be saved.';
                                }
                            }
                        }
                    }
                }
                if ($flash_error === '') {
                    $announcements = ExcelDB::all('announcements');
                    $announcements[] = [
                        'announcement_id' => 'ANN-' . strtoupper(bin2hex(random_bytes(5))),
                        'title' => $title,
                        'message' => $message,
                        'image_url' => $imageUrl,
                        'target_type' => $targetType,
                        'target_value' => $targetValue,
                        'status' => 'Active',
                        'created_by' => (string)($_SESSION['lsu_username'] ?? 'District Admin'),
                        'created_at' => date('Y-m-d H:i:s'),
                        'closed_at' => '',
                    ];
                    if (ExcelDB::writeTable('announcements', $announcements)) {
                        header('Location: ' . BASE_URL . '/admin/announcements.php?saved=1');
                        exit;
                    }
                    if ($uploadedImagePath !== '' && is_file($uploadedImagePath)) unlink($uploadedImagePath);
                    $flash_error = 'The announcement could not be saved. Please try again.';
                }
            }
        } elseif ($action === 'close') {
            $announcementId = trim((string)($_POST['announcement_id'] ?? ''));
            $announcements = ExcelDB::all('announcements');
            $found = false;
            foreach ($announcements as &$announcement) {
                if (($announcement['announcement_id'] ?? '') === $announcementId && ($announcement['status'] ?? '') === 'Active') {
                    $announcement['status'] = 'Closed';
                    $announcement['closed_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }
            unset($announcement);
            if (!$found) {
                $flash_error = 'The active announcement could not be found.';
            } elseif (ExcelDB::writeTable('announcements', $announcements)) {
                header('Location: ' . BASE_URL . '/admin/announcements.php?closed=1');
                exit;
            } else {
                $flash_error = 'The announcement could not be closed. Please try again.';
            }
        } elseif ($action === 'delete') {
            $announcementId = trim((string)($_POST['announcement_id'] ?? ''));
            $announcements = ExcelDB::all('announcements');
            $announcementToDelete = null;
            foreach ($announcements as $announcement) {
                if (($announcement['announcement_id'] ?? '') === $announcementId) {
                    $announcementToDelete = $announcement;
                    break;
                }
            }

            if (!$announcementToDelete || ($announcementToDelete['status'] ?? '') !== 'Closed') {
                $flash_error = 'Only closed announcements can be deleted.';
            } else {
                $remainingAnnouncements = array_values(array_filter(
                    $announcements,
                    fn($announcement) => ($announcement['announcement_id'] ?? '') !== $announcementId
                ));
                if (ExcelDB::writeTable('announcements', $remainingAnnouncements)) {
                    $imageUrl = (string)($announcementToDelete['image_url'] ?? '');
                    $uploadUrlPrefix = BASE_URL . '/assets/uploads/announcements/';
                    if (str_starts_with($imageUrl, $uploadUrlPrefix)) {
                        $imageName = substr($imageUrl, strlen($uploadUrlPrefix));
                        if (preg_match('/\Aannouncement_[a-f0-9]{24}\.(?:jpg|png|webp|gif)\z/i', $imageName)) {
                            $imagePath = ROOT_PATH . '/assets/uploads/announcements/' . $imageName;
                            if (is_file($imagePath) && !unlink($imagePath)) {
                                error_log('Could not remove announcement image after deleting announcement ' . $announcementId);
                            }
                        }
                    }
                    header('Location: ' . BASE_URL . '/admin/announcements.php?deleted=1');
                    exit;
                }
                $flash_error = 'The announcement could not be deleted. Please try again.';
            }
        } else {
            $flash_error = 'That action is not available.';
        }
    }
}

if (isset($_GET['saved'])) $flash_success = 'Announcement created and shown to its recipients.';
if (isset($_GET['closed'])) $flash_success = 'Announcement closed. It will no longer appear for schools.';
if (isset($_GET['deleted'])) $flash_success = 'Closed announcement deleted successfully.';

$announcements = ExcelDB::all('announcements');
usort($announcements, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
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
      <div>
        <h1 class="text-xl font-bold">Announcements &amp; Popups</h1>
        <p class="text-xs text-muted mt-1">Send important notices to a school or every school in a selected level.</p>
      </div>
      <?php if ($flash_success !== ''): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= e($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error !== ''): ?><div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= e($flash_error) ?></div><?php endif; ?>

      <section class="bg-white rounded-xl border border-border shadow-sm p-5">
        <h2 class="font-bold text-sm mb-4">Create announcement</h2>
        <?php if (!$schools): ?>
          <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3">Add schools before creating a targeted announcement.</p>
        <?php else: ?>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">
          <div>
            <label for="announcement-title" class="block text-xs font-semibold mb-1">Title</label>
            <input id="announcement-title" name="title" maxlength="120" required class="w-full rounded-lg border border-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="e.g. Important District Announcement">
          </div>
          <div>
            <label for="announcement-message" class="block text-xs font-semibold mb-1">Announcement message</label>
            <textarea id="announcement-message" name="message" rows="5" maxlength="10000" required class="w-full rounded-lg border border-border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Write the details schools need to know..."></textarea>
          </div>
          <div>
            <label for="announcement-image" class="block text-xs font-semibold mb-1">Optional announcement image</label>
            <input id="announcement-image" name="announcement_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full rounded-lg border border-border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold">
            <p class="mt-1 text-xs text-muted">JPG, PNG, WEBP, or GIF; maximum 5 MB.</p>
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label for="target-type" class="block text-xs font-semibold mb-1">Send to</label>
              <select id="target-type" name="target_type" class="w-full rounded-lg border border-border px-3 py-2 text-sm">
                <option value="level">A school level</option>
                <option value="school">One specific school</option>
              </select>
            </div>
            <div>
              <label for="target-value" class="block text-xs font-semibold mb-1">School level</label>
              <select id="target-value" name="target_value" class="w-full rounded-lg border border-border px-3 py-2 text-sm">
                <?php foreach ($levels as $level): ?><option value="<?= e($level) ?>"><?= e($level) ?></option><?php endforeach; ?>
              </select>
              <select id="school-value" class="hidden w-full rounded-lg border border-border px-3 py-2 text-sm">
                <?php foreach ($schools as $school): ?><option value="<?= e($school['semis_code'] ?? '') ?>"><?= e(($school['school_name'] ?? 'School') . ' — ' . ($school['semis_code'] ?? '')) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primaryDark text-white text-sm font-semibold rounded-lg transition">Create &amp; publish popup</button>
        </form>
        <?php endif; ?>
      </section>

      <section class="bg-white rounded-xl border border-border shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-border flex items-center justify-between"><h2 class="font-bold text-sm">Announcement history</h2><span class="text-xs text-muted"><?= count($announcements) ?> total</span></div>
        <?php if (!$announcements): ?><p class="p-8 text-center text-sm text-muted">No announcements have been created.</p>
        <?php else: ?>
        <div class="divide-y divide-border">
          <?php foreach ($announcements as $announcement): ?>
          <article class="p-4 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-sm"><?= e($announcement['title'] ?? '') ?></h3><span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= ($announcement['status'] ?? '') === 'Active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>"><?= e($announcement['status'] ?? 'Unknown') ?></span></div>
              <p class="text-sm text-muted mt-2 whitespace-pre-line"><?= e($announcement['message'] ?? '') ?></p>
              <?php if (!empty($announcement['image_url'])): ?><img src="<?= e($announcement['image_url']) ?>" alt="Announcement attachment" class="mt-3 max-h-40 max-w-xs rounded-lg border border-border object-contain"><?php endif; ?>
              <p class="text-xs text-muted mt-3">Target: <?= e(ucfirst($announcement['target_type'] ?? '')) ?> — <?= e($announcement['target_value'] ?? '') ?> · Created <?= e($announcement['created_at'] ?? '') ?></p>
            </div>
            <?php if (($announcement['status'] ?? '') === 'Active'): ?>
            <form method="POST" class="shrink-0" onsubmit="return confirmFormSubmit(event, this, 'Close this announcement for all schools?', {title:'Close Announcement',okText:'Close announcement'});">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="close"><input type="hidden" name="announcement_id" value="<?= e($announcement['announcement_id'] ?? '') ?>">
              <button type="submit" class="px-3 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50 text-xs font-semibold">Close announcement</button>
            </form>
            <?php elseif (($announcement['status'] ?? '') === 'Closed'): ?>
            <form method="POST" class="shrink-0" onsubmit="return confirmFormSubmit(event, this, 'Permanently delete this closed announcement?', {title:'Delete Announcement',okText:'Delete permanently',isDanger:true});">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="announcement_id" value="<?= e($announcement['announcement_id'] ?? '') ?>">
              <button type="submit" class="px-3 py-2 rounded-lg border border-red-200 text-red-700 hover:bg-red-50 text-xs font-semibold">Delete announcement</button>
            </form>
            <?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>
    </main>
    <?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
  </div>
</div>
<script>
const targetType = document.getElementById('target-type');
const targetValue = document.getElementById('target-value');
const schoolValue = document.getElementById('school-value');
function syncAnnouncementTarget() {
  const useSchool = targetType.value === 'school';
  targetValue.classList.toggle('hidden', useSchool);
  schoolValue.classList.toggle('hidden', !useSchool);
  targetValue.disabled = useSchool;
  schoolValue.disabled = !useSchool;
  schoolValue.name = useSchool ? 'target_value' : '';
}
targetType.addEventListener('change', syncAnnouncementTarget);
syncAnnouncementTarget();
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');const o=document.getElementById('overlay');o.classList.remove('hidden');setTimeout(()=>o.classList.remove('opacity-0'),10);}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');const o=document.getElementById('overlay');o.classList.add('opacity-0');setTimeout(()=>o.classList.add('hidden'),250);}
</script>
</body>
</html>
