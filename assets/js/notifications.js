/**
 * LSU Portal — Live Notification & Audio Chime Engine
 * 
 * Synthesizes crisp "tling" bell notifications via Web Audio API
 * and keeps sidebar & header unread badges synchronized in real-time
 * for both GRM Complaints and Admin Direct Messages.
 *
 * Audio Note: AudioContext must be created/resumed after a user gesture.
 * We pre-create it on first pointerdown/keydown/click/touchstart so it
 * is always ready when a chime needs to fire (fixes admin silent notifications).
 */

(function() {
    let lastUnreadCount = null;
    let audioContext = null;
    let audioUnlocked = false;

    // Pre-create the AudioContext on the first user gesture (any type)
    // so it is already in 'running' state when a notification arrives.
    function initAudioContext() {
        if (audioUnlocked) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!audioContext) {
                audioContext = new AudioCtx();
            }
            if (audioContext.state === 'suspended') {
                audioContext.resume().catch(() => {});
            }
            audioUnlocked = true;
        } catch(e) {}
    }

    // Synthesize realistic bell "tling" chime using Web Audio API
    window.playNotificationChime = function() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!audioContext) {
                audioContext = new AudioCtx();
            }
            // Always attempt resume — critical for admin portal where context
            // may have been created before the page fully received user focus.
            const playWhenReady = () => {
                const now = audioContext.currentTime;

                // Tone 1: High crisp initial attack (C6 → E6)
                const osc1 = audioContext.createOscillator();
                const gain1 = audioContext.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(1046.5, now); // C6
                osc1.frequency.exponentialRampToValueAtTime(1318.51, now + 0.08); // E6
                gain1.gain.setValueAtTime(0.25, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.7);

                // Tone 2: Warm undertone (A5)
                const osc2 = audioContext.createOscillator();
                const gain2 = audioContext.createGain();
                osc2.type = 'triangle';
                osc2.frequency.setValueAtTime(880, now); // A5
                gain2.gain.setValueAtTime(0.18, now);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.9);

                // Connect
                osc1.connect(gain1);
                osc2.connect(gain2);
                gain1.connect(audioContext.destination);
                gain2.connect(audioContext.destination);

                osc1.start(now);
                osc2.start(now);
                osc1.stop(now + 0.7);
                osc2.stop(now + 0.9);
            };

            if (audioContext.state === 'suspended') {
                audioContext.resume().then(playWhenReady).catch(() => {});
            } else {
                playWhenReady();
            }
        } catch (e) {
            console.log('Web Audio notification error:', e);
        }
    };

    // Show floating toast notification on new complaints/messages
    function showNotificationToast(alertType, title, message, link) {
        let toastContainer = document.getElementById('lsu-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'lsu-toast-container';
            toastContainer.className = 'fixed bottom-5 right-5 z-[99999] flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4 sm:px-0';
            document.body.appendChild(toastContainer);
        }

        const isMessage = (alertType === 'message');
        const badgeColor = isMessage ? 'bg-blue-600/30 border-blue-500/40 text-blue-400' : 'bg-emerald-600/30 border-emerald-500/40 text-emerald-400';
        const tagText = isMessage ? 'New Direct Message' : 'New Grievance Alert';
        const iconSvg = isMessage
            ? `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="12" y2="13"/></svg>`
            : `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>`;

        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-slate-900 text-white rounded-xl shadow-2xl p-4 border border-slate-700 flex items-start gap-3 transform transition-all duration-300 translate-y-4 opacity-0';
        toast.innerHTML = `
            <div class="w-9 h-9 rounded-lg ${badgeColor} border flex items-center justify-center flex-shrink-0 mt-0.5">
                ${iconSvg}
            </div>
            <div class="flex-1 overflow-hidden">
                <div class="text-[10px] font-bold ${isMessage ? 'text-blue-400' : 'text-emerald-400'} uppercase tracking-wider">${tagText}</div>
                <div class="text-xs font-bold text-white mt-0.5 truncate">${escapeHtml(title)}</div>
                <div class="text-[11px] text-slate-300 mt-0.5 line-clamp-2">${escapeHtml(message)}</div>
                ${link ? `<a href="${link}" class="inline-block mt-2 text-xs ${isMessage ? 'text-blue-300' : 'text-emerald-300'} font-semibold hover:underline">View & Reply &rarr;</a>` : ''}
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white p-1" aria-label="Close">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        `;

        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.classList.remove('translate-y-4', 'opacity-0');
        }, 50);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-x-4');
            setTimeout(() => toast.remove(), 300);
        }, 7000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
        });
    }

    // Update UI badge elements across both sidebars and headers
    function updateBadges(totalCount, data) {
        const complaintsCount = data.unread_complaints || 0;
        const messagesCount   = data.unread_messages || 0;

        // 1. Complaints sidebar badges
        document.querySelectorAll('.complaints-badge-count').forEach(el => {
            if (complaintsCount > 0) {
                el.textContent = complaintsCount;
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });

        // 2. Direct Messages sidebar badges
        document.querySelectorAll('.messages-badge-count').forEach(el => {
            if (messagesCount > 0) {
                el.textContent = messagesCount;
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });

        // 3. Header notification dots & counts
        document.querySelectorAll('.complaints-header-dot, .header-unread-dot').forEach(el => {
            if (totalCount > 0) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });

        document.querySelectorAll('.complaints-header-count, .header-unread-count').forEach(el => {
            if (totalCount > 0) {
                el.textContent = totalCount + ' New';
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }

    // Poll API
    function checkNotifications() {
        const baseUrl = window.LSU_BASE_URL || '';
        fetch(`${baseUrl}/api/check-complaints.php`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const currentTotal = data.unread_count || 0;

            if (lastUnreadCount !== null && currentTotal > lastUnreadCount) {
                // New notification detected! Play chime!
                window.playNotificationChime();
                const latest = data.notifications && data.notifications[0];
                if (latest) {
                    if (latest.type === 'message') {
                        const viewUrl = data.role === 'admin'
                            ? `${baseUrl}/admin/message-thread.php?thread=${encodeURIComponent(latest.thread_id)}`
                            : `${baseUrl}/school/message-thread.php?thread=${encodeURIComponent(latest.thread_id)}`;
                        showNotificationToast('message', `Thread ${latest.thread_id}: ${latest.school_name || 'RSU'}`, latest.subject, viewUrl);
                    } else {
                        const viewUrl = data.role === 'admin' 
                            ? `${baseUrl}/admin/complaint-details.php?ticket=${encodeURIComponent(latest.ticket_no)}`
                            : `${baseUrl}/school/complaint-details.php?ticket=${encodeURIComponent(latest.ticket_no)}`;
                        showNotificationToast('complaint', `${latest.ticket_no}: ${latest.school_name}`, latest.subject, viewUrl);
                    }
                }
            }

            lastUnreadCount = currentTotal;
            updateBadges(currentTotal, data);
        })
        .catch(err => {
            // Silently handle network polling fails
        });
    }

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        checkNotifications();
        // Poll every 8 seconds
        setInterval(checkNotifications, 8000);

        // Unlock AudioContext on the very first user gesture of any type.
        // This is critical for Admin portal — the notification sound was
        // silently failing because AudioContext was created before any interaction.
        const unlockEvents = ['click', 'pointerdown', 'keydown', 'touchstart'];
        unlockEvents.forEach(evt => {
            document.addEventListener(evt, initAudioContext, { once: false, passive: true });
        });
    });
})();
