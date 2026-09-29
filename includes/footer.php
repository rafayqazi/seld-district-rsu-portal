<?php
/**
 * includes/footer.php
 * Reusable footer partial.
 */
?>
<footer class="bg-surface border-t border-border px-6 py-4 text-xs text-muted flex flex-col sm:flex-row items-center justify-between gap-2">
  <div>
    <span class="font-medium text-textMain">District Reform Support Unit</span>
    &mdash; <?= APP_DEPARTMENT ?>
  </div>
  <div>
    <span>&copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; <?= APP_DISTRICT ?>. All Rights Reserved.</span>
  </div>
</footer>

<!-- ─── LSU Portal Toast Notifications (Bottom-Right) ────────────────────────── -->
<div id="lsu-toast-container" class="fixed bottom-5 right-5 z-[99999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"></div>

<!-- ─── LSU Portal Custom Confirmation Modal (No "localhost says" popups) ──────── -->
<div id="lsu-confirm-modal" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-[99999] hidden items-center justify-center p-4">
  <div class="bg-surface border border-border rounded-xl max-w-md w-full p-5 sm:p-6 shadow-2xl relative animate-in fade-in zoom-in-95 duration-150">
    <div class="flex items-start gap-3.5">
      <div id="lsu-confirm-icon-wrap" class="w-10 h-10 rounded-full bg-red-50 border border-red-200 text-danger flex items-center justify-center flex-shrink-0">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      </div>
      <div class="flex-1 min-w-0">
        <h3 id="lsu-confirm-title" class="text-sm font-bold text-textMain">Confirm Action</h3>
        <p id="lsu-confirm-message" class="text-xs text-muted mt-1 leading-relaxed">Are you sure you want to proceed?</p>
      </div>
    </div>
    <div class="mt-5 pt-3 border-t border-border flex items-center justify-end gap-2">
      <button type="button" id="lsu-confirm-cancel" onclick="closeCustomConfirm()" class="btn-secondary px-3.5 py-1.5 rounded text-xs font-semibold">Cancel</button>
      <button type="button" id="lsu-confirm-ok" class="px-4 py-1.5 rounded text-xs font-semibold bg-danger hover:bg-red-700 text-white transition">Confirm</button>
    </div>
  </div>
</div>

<script>
// ─── Global Toast Notification Engine ───────────────────────────────────────
function showToast(message, type = 'success', duration = 4000) {
  const container = document.getElementById('lsu-toast-container');
  if (!container) return;

  const isSuccess = (type === 'success');
  const toast = document.createElement('div');
  toast.className = `pointer-events-auto flex items-start gap-3 p-4 rounded-xl border shadow-xl transition-all duration-300 transform translate-x-8 opacity-0 ` +
    (isSuccess 
      ? 'bg-white border-l-4 border-emerald-500 border-border text-textMain' 
      : 'bg-white border-l-4 border-red-500 border-border text-textMain');

  const iconSvg = isSuccess 
    ? `<div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0 mt-0.5"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg></div>`
    : `<div class="w-7 h-7 rounded-full bg-red-100 text-red-700 flex items-center justify-center flex-shrink-0 mt-0.5"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="17"/></svg></div>`;

  const titleText = isSuccess ? 'Success' : 'Notice';

  toast.innerHTML = `
    ${iconSvg}
    <div class="flex-1 min-w-0 pr-2">
      <div class="text-xs font-bold ${isSuccess ? 'text-emerald-800' : 'text-red-800'}">${titleText}</div>
      <div class="text-xs text-textMain mt-0.5 leading-snug">${escapeHtml(message)}</div>
    </div>
    <button type="button" class="text-muted hover:text-textMain text-sm p-0.5 leading-none" onclick="dismissToast(this.parentElement)">&times;</button>
  `;

  container.appendChild(toast);

  // Trigger enter animation
  requestAnimationFrame(() => {
    toast.classList.remove('translate-x-8', 'opacity-0');
    toast.classList.add('translate-x-0', 'opacity-100');
  });

  // Auto dismiss
  if (duration > 0) {
    setTimeout(() => {
      dismissToast(toast);
    }, duration);
  }
}

function dismissToast(toast) {
  if (!toast) return;
  toast.classList.remove('translate-x-0', 'opacity-100');
  toast.classList.add('translate-x-8', 'opacity-0');
  setTimeout(() => {
    if (toast.parentElement) toast.parentElement.removeChild(toast);
  }, 300);
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text || '';
  return div.innerHTML;
}

// ─── Custom Tailwind Confirmation Dialog (Replaces native confirm) ────────────
let _lsuConfirmCallback = null;

function customConfirm(message, onConfirm, options = {}) {
  const modal = document.getElementById('lsu-confirm-modal');
  if (!modal) {
    if (window.confirm(message)) onConfirm();
    return;
  }

  document.getElementById('lsu-confirm-message').textContent = message;
  document.getElementById('lsu-confirm-title').textContent = options.title || 'Confirm Action';

  const okBtn = document.getElementById('lsu-confirm-ok');
  okBtn.textContent = options.okText || 'Confirm';

  const iconWrap = document.getElementById('lsu-confirm-icon-wrap');
  if (options.isDanger === false) {
    okBtn.className = 'btn-primary px-4 py-1.5 rounded text-xs font-semibold';
    iconWrap.className = 'w-10 h-10 rounded-full bg-blue-50 border border-blue-200 text-primary flex items-center justify-center flex-shrink-0';
  } else {
    okBtn.className = 'px-4 py-1.5 rounded text-xs font-semibold bg-danger hover:bg-red-700 text-white transition';
    iconWrap.className = 'w-10 h-10 rounded-full bg-red-50 border border-red-200 text-danger flex items-center justify-center flex-shrink-0';
  }

  modal.classList.remove('hidden');
  modal.classList.add('flex');
  _lsuConfirmCallback = onConfirm;

  okBtn.onclick = function() {
    closeCustomConfirm();
    if (_lsuConfirmCallback) _lsuConfirmCallback();
  };
}

function closeCustomConfirm() {
  const modal = document.getElementById('lsu-confirm-modal');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
  _lsuConfirmCallback = null;
}

// Helper for inline form submission confirmation:
// onsubmit="return confirmFormSubmit(event, this, 'Are you sure?')"
function confirmFormSubmit(event, form, message, options = {}) {
  if (event && event.preventDefault) {
    event.preventDefault();
  }
  customConfirm(message, () => {
    form.submit();
  }, options);
  return false;
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeCustomConfirm();
  }
});
</script>

<?php
// Auto-emit toast for active flash / notification messages
$page_toast_msg = $alert_message ?? $notification ?? $message ?? '';
$page_toast_type = $alert_type ?? $message_type ?? 'success';
if (!empty($page_toast_msg)):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  showToast(<?= json_encode($page_toast_msg) ?>, <?= json_encode($page_toast_type) ?>);
});
</script>
<?php endif; ?>


