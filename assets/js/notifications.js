/**
 * LSU Portal — Live Notification & Audio Chime Engine
 * 
 * Synthesizes crisp "tling" bell notifications via Web Audio API
 * and keeps sidebar & header unread badges synchronized in real-time.
 */

(function() {
    let lastUnreadCount = null;
    let audioContext = null;

    // Synthesize realistic bell "tling" chime using Web Audio API
    window.playNotificationChime = function() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            if (!audioContext) {
                audioContext = new AudioCtx();
            }
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            const now = audioContext.currentTime;

            // Tone 1: High crisp initial attack (E6 -> 1318.5 Hz)
            const osc1 = audioContext.createOscillator();
            const gain1 = audioContext.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(1046.5, now); // C6
            osc1.frequency.exponentialRampToValueAtTime(1318.51, now + 0.08); // E6
            gain1.gain.setValueAtTime(0.25, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.7);

            // Tone 2: Warm undertone (A5 -> 880 Hz)
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
        } catch (e) {
            console.log('Web Audio notification error:', e);
        }
    };

    // Show floating toast notification on new complaints/replies
    function showNotificationToast(title, message, link) {
        let toastContainer = document.getElementById('lsu-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'lsu-toast-container';
            toastContainer.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none';
            document.body.appendChild(toastContainer);
        }

        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-slate-900 text-white rounded-xl shadow-2xl p-4 border border-slate-700 flex items-start gap-3 transform transition-all duration-300 translate-y-4 opacity-0';
        toast.innerHTML = `
            <div class="w-9 h-9 rounded-lg bg-emerald-600/30 border border-emerald-500/40 flex items-center justify-center text-emerald-400 flex-shrink-0 mt-0.5">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <div class="flex-1 overflow-hidden">
                <div class="text-xs font-bold text-emerald-400 uppercase tracking-wide">New Grievance Alert</div>
                <div class="text-sm font-semibold text-white mt-0.5 truncate">${escapeHtml(title)}</div>
                <div class="text-xs text-slate-300 mt-0.5 line-clamp-2">${escapeHtml(message)}</div>
                ${link ? `<a href="${link}" class="inline-block mt-2 text-xs text-emerald-300 font-semibold hover:underline">View Ticket &rarr;</a>` : ''}
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

    // Update UI badge elements
    function updateBadges(count, data) {
        const sidebarBadges = document.querySelectorAll('.complaints-badge-count');
        const headerBadgeDot = document.querySelectorAll('.complaints-header-dot');
        const headerBadgeCount = document.querySelectorAll('.complaints-header-count');

        sidebarBadges.forEach(el => {
            if (count > 0) {
                el.textContent = count;
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });

        headerBadgeDot.forEach(el => {
            if (count > 0) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });

        headerBadgeCount.forEach(el => {
            if (count > 0) {
                el.textContent = count;
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
    }

    // Poll API
    function checkComplaints() {
        const baseUrl = window.LSU_BASE_URL || '';
        fetch(`${baseUrl}/api/check-complaints.php`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const currentCount = data.unread_count || 0;

            if (lastUnreadCount !== null && currentCount > lastUnreadCount) {
                // New complaint/reply detected! Play chime!
                window.playNotificationChime();
                const latest = data.notifications && data.notifications[0];
                if (latest) {
                    const viewUrl = data.role === 'admin' 
                        ? `${baseUrl}/admin/complaint-details.php?ticket=${encodeURIComponent(latest.ticket_no)}`
                        : `${baseUrl}/school/complaint-details.php?ticket=${encodeURIComponent(latest.ticket_no)}`;
                    showNotificationToast(`${latest.ticket_no}: ${latest.school_name}`, latest.subject, viewUrl);
                }
            }

            lastUnreadCount = currentCount;
            updateBadges(currentCount, data);
        })
        .catch(err => {
            // Silently handle network polling fails
        });
    }

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        checkComplaints();
        // Poll every 12 seconds
        setInterval(checkComplaints, 12000);

        // Resume AudioContext on first user interaction if blocked
        const enableAudio = () => {
            if (audioContext && audioContext.state === 'suspended') {
                audioContext.resume();
            }
            document.removeEventListener('click', enableAudio);
        };
        document.addEventListener('click', enableAudio, { once: true });
    });
})();
