(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function stripSourceCitations(text) {
        var cleaned = String(text || '').replace(/\(\s*sumber\s*:\s*[^)]+\)/gi, '');
        cleaned = cleaned.replace(/[ \t]{2,}/g, ' ');
        cleaned = cleaned.replace(/[ \t]+([,.;:!?])/g, '$1');
        cleaned = cleaned.replace(/[ \t]+\n/g, '\n');
        cleaned = cleaned.replace(/\n{3,}/g, '\n\n');
        return cleaned.trim();
    }

    function renderInline(text) {
        return text
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^\*])\*(?!\s)([^*]+?)\*(?!\*)/g, '$1<em>$2</em>');
    }

    function renderTableBlock(block) {
        var rows = block.trim().split('\n').filter(Boolean);
        if (rows.length < 2) {
            return escapeHtml(block);
        }

        function cells(line) {
            return line.replace(/^\||\|$/g, '').split('|').map(function (cell) {
                return cell.trim();
            });
        }

        var headers = cells(rows[0]);
        var bodyRows = rows.slice(2);
        var html = '<div class="agent-chat-table-wrap"><table class="agent-chat-table"><thead><tr>';

        headers.forEach(function (header) {
            html += '<th>' + renderInline(header) + '</th>';
        });
        html += '</tr></thead><tbody>';

        bodyRows.forEach(function (row) {
            html += '<tr>';
            cells(row).forEach(function (cell) {
                html += '<td>' + renderInline(cell) + '</td>';
            });
            html += '</tr>';
        });

        return html + '</tbody></table></div>';
    }

    function renderMarkdown(source) {
        var text = escapeHtml(String(source || '').replace(/\r\n/g, '\n'));
        var placeholders = [];

        text = text.replace(/```(?:[a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, function (_, code) {
            var token = '%%MD_BLOCK_' + placeholders.length + '%%';
            placeholders.push('<pre class="agent-chat-pre"><code>' + code.replace(/^\n+|\n+$/g, '') + '</code></pre>');
            return '\n' + token + '\n';
        });

        text = text.replace(/(^|\n)((?:\|.+\|\s*\n)+)/g, function (_, lead, table) {
            var lines = table.trim().split('\n');
            if (lines.length < 2 || !/^\|?\s*:?-{3,}/.test(lines[1])) {
                return lead + table;
            }
            var token = '%%MD_BLOCK_' + placeholders.length + '%%';
            placeholders.push(renderTableBlock(table));
            return lead + token + '\n';
        });

        var parts = text.split(/\n{2,}/);
        var html = parts.map(function (part) {
            var trimmed = part.trim();
            if (!trimmed) {
                return '';
            }
            if (/^%%MD_BLOCK_\d+%%$/.test(trimmed)) {
                return trimmed;
            }
            if (/^---+$/.test(trimmed)) {
                return '<hr>';
            }
            if (/^#{1,3} /.test(trimmed)) {
                return trimmed.split('\n').map(function (line) {
                    var heading = line.match(/^(#{1,3}) (.+)$/);
                    if (!heading) {
                        return '<p>' + renderInline(line) + '</p>';
                    }
                    var tag = heading[1].length === 1 ? 'h3' : 'h4';
                    return '<' + tag + '>' + renderInline(heading[2]) + '</' + tag + '>';
                }).join('');
            }
            if (/^[-*] /.test(trimmed)) {
                var items = trimmed.split('\n').filter(function (line) {
                    return /^[-*] /.test(line);
                }).map(function (line) {
                    return '<li>' + renderInline(line.replace(/^[-*] /, '')) + '</li>';
                }).join('');
                return '<ul>' + items + '</ul>';
            }
            return '<p>' + renderInline(trimmed).replace(/\n/g, '<br>') + '</p>';
        }).join('');

        placeholders.forEach(function (block, index) {
            html = html.replace('%%MD_BLOCK_' + index + '%%', block);
        });

        return html || '<p></p>';
    }

    ready(function () {
        var root = document.getElementById('agent-chat-root');
        if (!root) return;

        document.body.classList.add('has-agent-chat');
        if (document.querySelector('.floating-footer')) {
            document.body.classList.add('has-agent-chat-with-footer');
        }

        var chatUrl = root.dataset.chatUrl;
        var confirmUrl = root.dataset.confirmUrl;
        var mascotUrl = root.dataset.mascotUrl || '';
        var newUrl = root.dataset.newUrl;
        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

        var fab = document.getElementById('agent-chat-fab');
        var panel = document.getElementById('agent-chat-panel');
        var closeBtn = document.getElementById('agent-chat-close');
        var newBtn = document.getElementById('agent-chat-new');
        var fullscreenBtn = document.getElementById('agent-chat-fullscreen');
        var form = document.getElementById('agent-chat-form');
        var input = document.getElementById('agent-chat-input');
        var messagesEl = document.getElementById('agent-chat-messages');
        var suggestionsEl = document.getElementById('agent-chat-suggestions');
        var typingEl = document.getElementById('agent-chat-typing');
        var sendBtn = document.getElementById('agent-chat-send');
        var voiceBtn = document.getElementById('agent-chat-voice');
        var historyBtn = document.getElementById('agent-chat-history-btn');
        var historyEl = document.getElementById('agent-chat-history');
        var historyListEl = document.getElementById('agent-chat-history-list');
        var historyCloseBtn = document.getElementById('agent-chat-history-close');
        var messagesUrlTpl = root.dataset.messagesUrl || '';
        var listUrl = root.dataset.listUrl || '';
        var tourStopUrl = root.dataset.tourStopUrl || '';
        var TOUR_PENDING_KEY = 'agent_tour_pending';
        var welcomeHtml = messagesEl ? messagesEl.innerHTML : '';
        var panelCloseTimer = null;

        var FULLSCREEN_KEY = 'agent_chat_fullscreen';
        var conversationId = localStorage.getItem('agent_chat_conversation_id') || null;
        var panelOpen = localStorage.getItem('agent_chat_open') === '1';
        var panelFullscreen = false;
        try {
            panelFullscreen = localStorage.getItem(FULLSCREEN_KEY) === '1';
        } catch (e) {
            panelFullscreen = false;
        }
        var lastSpokenText = '';
        var lastOutgoing = null;
        var historyOpen = false;
        var threadRequest = null;

        function prefersReducedMotion() {
            return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        }

        function formatChatTime(from) {
            var date = from instanceof Date ? from : new Date();
            if (isNaN(date.getTime())) {
                date = new Date();
            }
            var opts = {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            };
            var now = new Date();
            var sameDay = date.getFullYear() === now.getFullYear()
                && date.getMonth() === now.getMonth()
                && date.getDate() === now.getDate();
            if (!sameDay) {
                opts.day = 'numeric';
                opts.month = 'short';
            }
            try {
                return new Intl.DateTimeFormat('id-ID', opts).format(date);
            } catch (e) {
                var hours = String(date.getHours()).padStart(2, '0');
                var minutes = String(date.getMinutes()).padStart(2, '0');
                return hours + '.' + minutes;
            }
        }

        function hideWelcome() {
            var welcome = document.getElementById('agent-chat-welcome');
            if (welcome) {
                welcome.classList.add('d-none');
            }
        }

        function appendTimestamp(parent, iso) {
            var date = iso ? new Date(iso) : new Date();
            if (iso && isNaN(date.getTime())) {
                date = new Date();
            }
            var time = document.createElement('time');
            time.className = 'agent-chat-time';
            time.dateTime = date.toISOString();
            time.textContent = formatChatTime(date);
            parent.appendChild(time);
            return time;
        }

        function speech() {
            return window.AgentSpeech || null;
        }

        function tourOwnsSpeech() {
            var tourActive = window.AgentTour && window.AgentTour.isActive();
            var chatHidden = panel.classList.contains('d-none');
            return !!(tourActive && chatHidden);
        }

        function syncChatVoiceButton() {
            if (!voiceBtn) {
                return;
            }

            var api = speech();
            var supported = !!(api && api.supported());
            voiceBtn.classList.toggle('d-none', !supported);
            if (!supported) {
                return;
            }

            var on = api.isOn();
            var hasId = api.hasIndonesian();
            var hint = api.HINT_NO_ID || '';
            var icon = voiceBtn.querySelector('i');
            if (icon) {
                icon.className = on ? 'ti ti-volume' : 'ti ti-volume-off';
            }
            voiceBtn.classList.toggle('is-on', on);
            voiceBtn.classList.toggle('is-fallback', on && !hasId);
            voiceBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on) {
                voiceBtn.title = hasId
                    ? 'Matikan suara balasan'
                    : 'Matikan suara balasan. ' + hint;
            } else {
                voiceBtn.title = hasId
                    ? 'Nyalakan suara balasan'
                    : 'Nyalakan suara balasan. ' + hint;
            }
            voiceBtn.setAttribute('aria-label', voiceBtn.title);
        }

        function maybeSpeakAssistant(text) {
            var api = speech();
            if (!api || !api.supported() || !api.isOn()) {
                return;
            }
            if (tourOwnsSpeech()) {
                return;
            }
            lastSpokenText = String(text || '');
            api.speak(lastSpokenText);
        }

        function speakTitanieGreeting() {
            var api = speech();
            var tourActive = !!(window.AgentTour && window.AgentTour.isActive());
            if (!api || !api.supported() || tourActive) {
                return;
            }
            var line = api.GREETING || 'Halo, aku Titanie. Asisten gudang kamu, siap bantu.';
            lastSpokenText = line;
            if (typeof api.speakGreeting === 'function') {
                api.speakGreeting();
                return;
            }
            api.speak(line);
        }

        if (voiceBtn) {
            if (speech()) {
                speech().bind();
                speech().onPreferenceChange(syncChatVoiceButton);
                speech().onSpeakingChange(function (on) {
                    voiceBtn.classList.toggle('is-speaking', !!on);
                });
                speech().onVoicesReady(function (info) {
                    var api = speech();
                    var tourActive = !!(window.AgentTour && window.AgentTour.isActive());
                    if (
                        !api
                        || !api.isOn()
                        || tourActive
                        || !lastSpokenText
                        || !info
                        || !info.voice
                        || info.key === info.previousKey
                        || !info.isIndonesian
                        || info.previousWasId
                    ) {
                        return;
                    }
                    api.speak(lastSpokenText);
                });
                voiceBtn.addEventListener('click', function () {
                    var api = speech();
                    if (!api || !api.supported()) {
                        return;
                    }
                    var next = !api.isOn();
                    api.setOn(next);
                    if (next) {
                        api.resume();
                        speakTitanieGreeting();
                    }
                });
            }
            syncChatVoiceButton();
        }

        function syncFullscreenButton() {
            if (!fullscreenBtn) {
                return;
            }

            var icon = fullscreenBtn.querySelector('i');
            if (icon) {
                icon.className = panelFullscreen ? 'ti ti-arrows-minimize' : 'ti ti-arrows-maximize';
            }
            fullscreenBtn.classList.toggle('is-on', panelFullscreen);
            fullscreenBtn.setAttribute('aria-pressed', panelFullscreen ? 'true' : 'false');
            var label = panelFullscreen ? 'Keluar layar penuh' : 'Layar penuh';
            fullscreenBtn.title = label;
            fullscreenBtn.setAttribute('aria-label', label);
        }

        function applyPanelFullscreen() {
            panel.classList.toggle('is-fullscreen', panelFullscreen);
            root.classList.toggle('is-chat-fullscreen', !!(panelFullscreen && panelOpen));
            syncFullscreenButton();
        }

        function setPanelFullscreen(on, options) {
            options = options || {};
            panelFullscreen = !!on;
            if (options.persist !== false) {
                try {
                    localStorage.setItem(FULLSCREEN_KEY, panelFullscreen ? '1' : '0');
                } catch (e) {
                    // ignore quota / private mode
                }
            }
            applyPanelFullscreen();
        }

        function setPanelOpen(open, options) {
            options = options || {};
            panelOpen = open;
            if (options.persist !== false) {
                localStorage.setItem('agent_chat_open', open ? '1' : '0');
            }
            if (panelCloseTimer) {
                window.clearTimeout(panelCloseTimer);
                panelCloseTimer = null;
            }
            fab.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                root.classList.remove('has-unread');
                panel.classList.remove('d-none');
                panel.setAttribute('aria-hidden', 'false');
                placePanel();
                applyPanelFullscreen();
                if (options.instant || prefersReducedMotion()) {
                    panel.classList.add('is-open');
                } else {
                    window.requestAnimationFrame(function () {
                        panel.classList.add('is-open');
                    });
                }
            } else {
                if (historyOpen) {
                    setHistoryOpen(false);
                }
                panel.classList.remove('is-open');
                panel.setAttribute('aria-hidden', 'true');
                applyPanelFullscreen();
                if (options.instant || prefersReducedMotion()) {
                    panel.classList.add('d-none');
                } else {
                    panelCloseTimer = window.setTimeout(function () {
                        panelCloseTimer = null;
                        if (!panelOpen) {
                            panel.classList.add('d-none');
                        }
                    }, 200);
                }
            }
        }

        function hasPendingTour() {
            try {
                return !!sessionStorage.getItem(TOUR_PENDING_KEY);
            } catch (e) {
                return false;
            }
        }

        function hideChatForTour() {
            setPanelOpen(false, { persist: false, instant: true });
        }

        function reopenChatAfterTour() {
            setPanelOpen(true);
            window.setTimeout(function () {
                if (input && !input.disabled) {
                    input.focus();
                }
                scrollToBottom();
            }, 50);
        }

        function normalizeTourText(text) {
            return String(text || '')
                .trim()
                .toLowerCase()
                .replace(/[?.!,]+$/g, '')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function isTourControlMessage(text) {
            var controls = [
                'lanjut',
                'next',
                'kembali',
                'back',
                'stop',
                'cukup',
                'lewati',
                'selesai',
                'sebelumnya',
                'ruang berikutnya',
                'ruang sebelumnya',
                'stop tur',
                'stop tour',
                'hentikan tur',
                'hentikan turnya',
                'berhentiin turnya',
                'berhentikan turnya',
            ];

            return controls.indexOf(normalizeTourText(text)) !== -1;
        }

        function clearPendingTour() {
            try {
                sessionStorage.removeItem(TOUR_PENDING_KEY);
            } catch (e) {
                // ignore
            }
        }

        function cancelAllSpeech() {
            if (window.AgentTour && typeof window.AgentTour.cancelSpeech === 'function') {
                window.AgentTour.cancelSpeech();
            }
            if (speech()) {
                speech().cancel();
            } else if (typeof window.speechSynthesis !== 'undefined') {
                try {
                    window.speechSynthesis.cancel();
                } catch (e) {
                    // ignore
                }
            }
        }

        function stopTourUi() {
            clearPendingTour();
            if (window.AgentTour) {
                window.AgentTour.clear();
            }
            cancelAllSpeech();
            reopenChatAfterTour();
        }

        function tourRecapText(reason) {
            return reason === 'finish'
                ? 'Tur selesai. Kalau mau, tanya halaman ini atau mulai tur lagi.'
                : 'Tur dihentikan. Kalau mau, tanya halaman ini atau mulai tur lagi.';
        }

        function silentStopTour(reason) {
            if (!tourStopUrl) {
                return;
            }

            fetch(tourStopUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    reason: reason === 'finish' ? 'finish' : 'skip',
                    page_path: pageContext().path,
                    page_title: pageContext().title,
                    page_menu: pageContext().menu,
                }),
            }).catch(function () {
                // Overlay already closed; cache will expire or reset on the next non-control message.
            });
        }

        function finishTour(reason) {
            var recap = tourRecapText(reason);
            stopTourUi();
            setSuggestionsVisible(false);
            appendAssistantMessage(recap);
            silentStopTour(reason);
        }

        function startNewChat() {
            stopTourUi();
            setHistoryOpen(false);
            persistConversationId(null);
            lastOutgoing = null;
            showWelcome();
            scrollToBottom();
            if (input) {
                input.value = '';
                if (!input.disabled) {
                    input.focus();
                }
            }
        }

        if (panelOpen && !hasPendingTour()) {
            setPanelOpen(true, { instant: true });
        }

        var POSITION_KEY = 'agent_chat_fab_pos';
        var dragState = {
            active: false,
            moved: false,
            pointerId: null,
            startX: 0,
            startY: 0,
            originLeft: 0,
            originTop: 0,
        };

        function clamp(value, min, max) {
            return Math.min(max, Math.max(min, value));
        }

        function placePanel() {
            var fabHeight = fab.offsetHeight || 92;
            var spaceAbove = root.offsetTop;
            var spaceBelow = window.innerHeight - (root.offsetTop + fabHeight);
            var preferAbove = spaceAbove >= 300 || spaceAbove >= spaceBelow;
            var preferLeft = (root.offsetLeft + fab.offsetWidth / 2) < (window.innerWidth / 2);

            panel.classList.toggle('is-below', !preferAbove);
            panel.classList.toggle('is-left', preferLeft);
        }

        function applyFabPosition(left, top, persist) {
            var maxLeft = window.innerWidth - (fab.offsetWidth || 92) - 8;
            var maxTop = window.innerHeight - (fab.offsetHeight || 92) - 8;
            left = clamp(left, 8, Math.max(8, maxLeft));
            top = clamp(top, 8, Math.max(8, maxTop));
            root.style.left = left + 'px';
            root.style.top = top + 'px';
            root.style.right = 'auto';
            root.style.bottom = 'auto';
            placePanel();

            if (persist) {
                localStorage.setItem(POSITION_KEY, JSON.stringify({ left: left, top: top }));
            }

            return { left: left, top: top };
        }

        function restoreFabPosition() {
            try {
                var saved = JSON.parse(localStorage.getItem(POSITION_KEY) || 'null');
                if (saved && typeof saved.left === 'number' && typeof saved.top === 'number') {
                    applyFabPosition(saved.left, saved.top, false);
                }
            } catch (e) {
                // keep CSS default
            }

            placePanel();
        }

        restoreFabPosition();
        window.addEventListener('resize', function () {
            var left = root.style.left ? parseFloat(root.style.left) : root.getBoundingClientRect().left;
            var top = root.style.top ? parseFloat(root.style.top) : root.getBoundingClientRect().top;
            if (root.style.left) {
                applyFabPosition(left, top, true);
            } else {
                placePanel();
            }
        });

        fab.addEventListener('pointerdown', function (event) {
            if (event.button !== 0 && event.pointerType === 'mouse') {
                return;
            }

            var rect = root.getBoundingClientRect();
            dragState.active = true;
            dragState.moved = false;
            dragState.pointerId = event.pointerId;
            dragState.startX = event.clientX;
            dragState.startY = event.clientY;
            dragState.originLeft = rect.left;
            dragState.originTop = rect.top;
            fab.setPointerCapture(event.pointerId);
        });

        fab.addEventListener('pointermove', function (event) {
            if (!dragState.active || event.pointerId !== dragState.pointerId) {
                return;
            }

            var dx = event.clientX - dragState.startX;
            var dy = event.clientY - dragState.startY;

            if (!dragState.moved && (Math.abs(dx) > 6 || Math.abs(dy) > 6)) {
                dragState.moved = true;
                root.classList.add('is-dragging');
            }

            if (dragState.moved) {
                applyFabPosition(dragState.originLeft + dx, dragState.originTop + dy, false);
            }
        });

        function endFabDrag(event) {
            if (!dragState.active || (event && event.pointerId !== dragState.pointerId)) {
                return;
            }

            if (dragState.moved) {
                var rect = root.getBoundingClientRect();
                applyFabPosition(rect.left, rect.top, true);
            }

            dragState.active = false;
            dragState.pointerId = null;
            root.classList.remove('is-dragging');

            if (dragState.moved) {
                window.setTimeout(function () {
                    dragState.moved = false;
                }, 80);
            }
        }

        fab.addEventListener('pointerup', endFabDrag);
        fab.addEventListener('pointercancel', endFabDrag);

        fab.addEventListener('click', function (event) {
            if (dragState.moved) {
                event.preventDefault();
                event.stopImmediatePropagation();
                dragState.moved = false;
                return;
            }

            setPanelOpen(!panelOpen);
            if (panelOpen) {
                placePanel();
                input.focus();
            }
        });

        closeBtn.addEventListener('click', function () {
            setHistoryOpen(false);
            setPanelOpen(false);
        });

        newBtn.addEventListener('click', function () {
            startNewChat();
        });

        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', function () {
                setPanelFullscreen(!panelFullscreen);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || event.defaultPrevented) {
                return;
            }
            if (!panelOpen || !panelFullscreen) {
                return;
            }
            if (window.AgentTour && window.AgentTour.isActive()) {
                return;
            }
            event.preventDefault();
            setPanelFullscreen(false);
            if (fullscreenBtn) {
                fullscreenBtn.focus();
            }
        });

        applyPanelFullscreen();

        if (historyBtn) {
            historyBtn.addEventListener('click', function () {
                setHistoryOpen(!historyOpen);
            });
        }

        if (historyCloseBtn) {
            historyCloseBtn.addEventListener('click', function () {
                setHistoryOpen(false);
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });

        if (suggestionsEl) {
            suggestionsEl.addEventListener('click', function (event) {
                var chip = event.target.closest('.agent-chat-suggestion');
                if (!chip || chip.disabled || suggestionsEl.classList.contains('d-none')) {
                    return;
                }

                var prompt = (chip.getAttribute('data-prompt') || chip.textContent || '').trim();
                if (!prompt) {
                    return;
                }

                input.value = prompt;
                sendMessage();
            });
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function appendUserMessage(text, options) {
            options = options || {};
            hideWelcome();
            var wrap = document.createElement('div');
            wrap.className = 'agent-chat-message agent-chat-message-user';
            var col = document.createElement('div');
            col.className = 'agent-chat-message-col';
            var bubble = document.createElement('div');
            bubble.className = 'agent-chat-bubble';
            bubble.textContent = text;
            col.appendChild(bubble);
            appendTimestamp(col, options.createdAt);
            wrap.appendChild(col);
            messagesEl.appendChild(wrap);
            scrollToBottom();
        }

        function appendAssistantMessage(text, attachments, options) {
            options = options || {};
            text = stripSourceCitations(text);
            var wrap = document.createElement('div');
            wrap.className = 'agent-chat-message agent-chat-message-assistant';
            if (mascotUrl) {
                var avatar = document.createElement('img');
                avatar.src = mascotUrl;
                avatar.alt = '';
                avatar.className = 'agent-chat-avatar';
                wrap.appendChild(avatar);
            }
            var bubble = document.createElement('div');
            bubble.className = 'agent-chat-bubble agent-chat-bubble-md';
            bubble.innerHTML = renderMarkdown(text);
            var col = document.createElement('div');
            col.className = 'agent-chat-message-col';
            col.appendChild(bubble);
            wrap.appendChild(col);

            if (attachments && attachments.length) {
                attachments.forEach(function (attachment) {
                    if (attachment.type === 'table') {
                        bubble.appendChild(renderTable(attachment));
                    }
                    if (attachment.type === 'action_card') {
                        bubble.appendChild(renderActionCard(attachment));
                    }
                    if (attachment.type === 'tour_highlight' && attachment.active && !window.AgentTour) {
                        bubble.appendChild(renderTourNav(attachment));
                    }
                });
                applyTourFromAttachments(attachments);
                applyPageNavigation(attachments);
            }

            if (options.retry && lastOutgoing) {
                var retryWrap = document.createElement('div');
                retryWrap.className = 'agent-chat-retry mt-2';
                var retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'btn btn-sm btn-outline-primary agent-chat-retry-btn';
                retryBtn.textContent = 'Coba lagi';
                retryBtn.addEventListener('click', function () {
                    if (!lastOutgoing || retryBtn.disabled) {
                        return;
                    }
                    retryBtn.disabled = true;
                    sendMessage(lastOutgoing, { skipUserBubble: true });
                });
                retryWrap.appendChild(retryBtn);
                bubble.appendChild(retryWrap);
            }

            messagesEl.appendChild(wrap);
            appendTimestamp(col, options.createdAt);
            scrollToBottom();
            if (!options.silent) {
                maybeSpeakAssistant(text);
            }
            if (!panelOpen && !options.silent) {
                root.classList.add('has-unread');
            }
        }

        function renderTable(attachment) {
            var wrap = document.createElement('div');
            wrap.className = 'agent-chat-table-wrap';
            var table = document.createElement('table');
            table.className = 'agent-chat-table';

            var thead = document.createElement('thead');
            var headRow = document.createElement('tr');
            (attachment.headers || []).forEach(function (header) {
                var th = document.createElement('th');
                th.textContent = header;
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            table.appendChild(thead);

            var tbody = document.createElement('tbody');
            (attachment.rows || []).forEach(function (row) {
                var tr = document.createElement('tr');
                row.forEach(function (cell) {
                    var td = document.createElement('td');
                    td.textContent = cell;
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            wrap.appendChild(table);

            return wrap;
        }

        function renderActionCard(attachment) {
            var card = document.createElement('div');
            var actionName = String(attachment.action || attachment.kind || '');
            var titleText = attachment.title || 'Konfirmasi';
            var confirmText = attachment.confirm_label || 'Konfirmasi';
            var variantHint = (actionName + ' ' + titleText + ' ' + confirmText).toLowerCase();
            card.className = 'agent-chat-action-card';
            if (/hapus|delete/.test(variantHint)) {
                card.classList.add('is-danger');
            } else if (/sale|penjualan/.test(variantHint)) {
                card.classList.add('is-sale');
            }

            var badge = document.createElement('div');
            badge.className = 'agent-chat-action-badge';
            badge.textContent = card.classList.contains('is-danger')
                ? 'Perlu konfirmasi'
                : (card.classList.contains('is-sale') ? 'Penjualan tunai' : 'Menunggu konfirmasi');
            card.appendChild(badge);

            var title = document.createElement('div');
            title.className = 'agent-chat-action-title';
            title.textContent = titleText;
            card.appendChild(title);

            if (attachment.body) {
                var body = document.createElement('div');
                body.className = 'agent-chat-action-body';
                var chunks = String(attachment.body).split(' · ');
                if (chunks.length > 1) {
                    chunks.forEach(function (chunk) {
                        var row = document.createElement('div');
                        row.className = 'agent-chat-action-row';
                        row.textContent = chunk.trim();
                        body.appendChild(row);
                    });
                } else {
                    body.textContent = attachment.body;
                }
                card.appendChild(body);
            }

            var actions = document.createElement('div');
            actions.className = 'agent-chat-action-buttons';

            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-sm btn-outline-secondary';
            cancelBtn.textContent = attachment.cancel_label || 'Batal';

            var confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.className = 'btn btn-sm btn-primary';
            confirmBtn.textContent = attachment.confirm_label || 'Konfirmasi';

            cancelBtn.addEventListener('click', function () {
                submitActionDecision(attachment, 'cancel', [confirmBtn, cancelBtn], card);
            });
            confirmBtn.addEventListener('click', function () {
                submitActionDecision(attachment, 'confirm', [confirmBtn, cancelBtn], card);
            });

            actions.appendChild(cancelBtn);
            actions.appendChild(confirmBtn);
            card.appendChild(actions);

            return card;
        }

        function submitActionDecision(attachment, decision, buttons, card) {
            if (!confirmUrl || !conversationId) {
                appendAssistantMessage('Percakapan belum siap. Kirim pesan dulu lalu coba lagi.');
                return;
            }

            buttons.forEach(function (button) {
                button.disabled = true;
            });
            setLoading(true);

            fetch(confirmUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    token: attachment.token,
                    decision: decision,
                }),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    var data = result.data || {};
                    if (!result.ok || !data.success) {
                        buttons.forEach(function (button) {
                            button.disabled = false;
                        });
                        appendAssistantMessage(data.message || 'Aksi gagal.');
                        return;
                    }
                    card.classList.add('is-resolved');
                    appendAssistantMessage(data.reply && data.reply.content
                        ? data.reply.content
                        : (data.message || 'Aksi selesai.'));
                })
                .catch(function () {
                    buttons.forEach(function (button) {
                        button.disabled = false;
                    });
                    appendAssistantMessage('Gagal memproses konfirmasi. Coba lagi.');
                })
                .finally(function () {
                    setLoading(false);
                    input.focus();
                });
        }

        function scrollToBottom() {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function setSuggestionsVisible(visible) {
            if (!suggestionsEl) {
                return;
            }

            suggestionsEl.classList.toggle('d-none', !visible);
        }

        function setLoading(loading) {
            if (loading && speech() && !tourOwnsSpeech()) {
                speech().cancel();
            }
            typingEl.classList.toggle('d-none', !loading);
            sendBtn.disabled = loading;
            input.disabled = loading;

            if (suggestionsEl) {
                var chips = suggestionsEl.querySelectorAll('.agent-chat-suggestion');
                chips.forEach(function (chip) {
                    chip.disabled = loading;
                });
            }

            if (window.AgentTour && window.AgentTour.isActive()) {
                window.AgentTour.setLoading(loading);
            }
        }

        function pageContext() {
            var actives = document.querySelectorAll('#layout-menu .menu-item.active > a.menu-link');
            var activeMenu = actives.length ? actives[actives.length - 1] : null;
            var menuText = '';

            if (activeMenu) {
                var label = activeMenu.querySelector('[data-i18n], div');
                menuText = ((label && label.textContent) || activeMenu.getAttribute('data-menu') || activeMenu.textContent || '').trim();
            }

            return {
                path: window.location.pathname || '/',
                title: (document.title || '').trim(),
                menu: menuText,
            };
        }

        function renderTourNav(attachment) {
            var wrap = document.createElement('div');
            wrap.className = 'agent-chat-tour-nav';

            if (attachment.step && attachment.total) {
                var meta = document.createElement('div');
                meta.className = 'agent-chat-tour-meta';
                meta.textContent = 'Ruang ' + attachment.step + ' dari ' + attachment.total;
                wrap.appendChild(meta);
            }

            if (attachment.has_prev) {
                var backBtn = document.createElement('button');
                backBtn.type = 'button';
                backBtn.className = 'agent-chat-suggestion';
                backBtn.textContent = 'Kembali';
                backBtn.addEventListener('click', function () {
                    sendMessage('kembali');
                });
                wrap.appendChild(backBtn);
            }

            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'agent-chat-suggestion';
            if (attachment.is_last) {
                nextBtn.textContent = 'Selesai';
                nextBtn.addEventListener('click', function () {
                    finishTour('finish');
                });
            } else {
                nextBtn.textContent = 'Lanjut';
                nextBtn.addEventListener('click', function () {
                    sendMessage('lanjut');
                });
            }

            var stopBtn = document.createElement('button');
            stopBtn.type = 'button';
            stopBtn.className = 'agent-chat-suggestion';
            stopBtn.textContent = 'Lewati';
            stopBtn.addEventListener('click', function () {
                finishTour('skip');
            });

            wrap.appendChild(nextBtn);
            if (!attachment.is_last) {
                wrap.appendChild(stopBtn);
            }

            return wrap;
        }

        function tourHandlers() {
            return {
                onNext: function () {
                    sendMessage('lanjut');
                },
                onPrev: function () {
                    sendMessage('kembali');
                },
                onSkip: function () {
                    finishTour('skip');
                },
                onFinish: function () {
                    finishTour('finish');
                },
            };
        }

        function applyTourFromAttachments(attachments) {
            var highlight = null;
            (attachments || []).forEach(function (attachment) {
                if (attachment && attachment.type === 'tour_highlight') {
                    highlight = attachment;
                }
            });

            if (highlight) {
                applyTourHighlight(highlight);
            }
        }

        function applyPageNavigation(attachments) {
            var nav = null;
            var tourNavigating = false;

            (attachments || []).forEach(function (attachment) {
                if (!attachment) {
                    return;
                }
                if (attachment.type === 'tour_highlight' && attachment.navigate_url && attachment.active) {
                    tourNavigating = true;
                }
                if ((attachment.type === 'page_navigation' || attachment.needs_navigation) && attachment.url) {
                    nav = attachment;
                }
            });

            if (tourNavigating || !nav || !isSafeInternalUrl(nav.url)) {
                return;
            }

            var dest;
            try {
                dest = new URL(nav.url, window.location.origin);
            } catch (e) {
                return;
            }

            if (dest.origin !== window.location.origin) {
                return;
            }

            var next = dest.pathname + dest.search;
            var here = window.location.pathname + window.location.search;
            var wantNewTab = nav.new_tab === true;

            if (next === here && !wantNewTab) {
                return;
            }

            if (wantNewTab) {
                var opened = window.open(next, '_blank');
                if (opened) {
                    opened.opener = null;
                    return;
                }
            }

            if (next === here) {
                return;
            }

            window.location.assign(next);
        }

        function isSafeInternalUrl(url) {
            if (!url || typeof url !== 'string') {
                return false;
            }

            if (url.indexOf('://') !== -1 || url.indexOf('//') === 0) {
                try {
                    return new URL(url, window.location.origin).origin === window.location.origin;
                } catch (e) {
                    return false;
                }
            }

            return url.charAt(0) === '/';
        }

        function applyTourHighlight(attachment) {
            if (!attachment || attachment.operation === 'stop' || attachment.active === false) {
                if (window.AgentTour) {
                    window.AgentTour.clear();
                }
                reopenChatAfterTour();
                return;
            }

            var navUrl = attachment.navigate_url;
            if (navUrl && isSafeInternalUrl(navUrl)) {
                var dest = new URL(navUrl, window.location.origin);
                if (dest.pathname !== window.location.pathname) {
                    try {
                        sessionStorage.setItem(TOUR_PENDING_KEY, JSON.stringify(attachment));
                    } catch (e) {
                        // ignore quota
                    }
                    hideChatForTour();
                    window.location.assign(dest.pathname + dest.search);
                    return;
                }
            }

            hideChatForTour();
            if (window.AgentTour) {
                window.AgentTour.show(attachment, tourHandlers());
            }
        }

        function persistConversationId(id) {
            conversationId = id || null;
            if (conversationId) {
                localStorage.setItem('agent_chat_conversation_id', conversationId);
            } else {
                localStorage.removeItem('agent_chat_conversation_id');
            }
        }

        function showWelcome() {
            if (messagesEl) {
                messagesEl.innerHTML = welcomeHtml;
            }
            setSuggestionsVisible(true);
        }

        function agentGetJson(url) {
            return fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, status: response.status, data: data || {} };
                }).catch(function () {
                    return { ok: false, status: response.status, data: {} };
                });
            });
        }

        function setHistoryOpen(open) {
            historyOpen = !!open;
            if (!historyEl) {
                return;
            }
            historyEl.classList.toggle('d-none', !historyOpen);
            if (historyOpen) {
                historyEl.removeAttribute('hidden');
            } else {
                historyEl.setAttribute('hidden', '');
            }
            if (historyBtn) {
                historyBtn.classList.toggle('is-on', historyOpen);
                historyBtn.setAttribute('aria-pressed', historyOpen ? 'true' : 'false');
                historyBtn.setAttribute('aria-expanded', historyOpen ? 'true' : 'false');
                historyBtn.title = historyOpen ? 'Tutup riwayat chat' : 'Riwayat chat';
                historyBtn.setAttribute('aria-label', historyBtn.title);
            }
            if (historyOpen) {
                loadConversationList();
            }
        }

        function historyStatus(text) {
            if (!historyListEl) {
                return;
            }
            historyListEl.innerHTML = '';
            var p = document.createElement('p');
            p.className = 'agent-chat-history-status';
            p.textContent = text;
            historyListEl.appendChild(p);
        }

        function loadConversationList() {
            if (!listUrl || !historyListEl) {
                historyStatus('Riwayat tidak tersedia.');
                return;
            }

            historyStatus('Memuat riwayat…');

            agentGetJson(listUrl)
                .then(function (result) {
                    var items = (result.data && result.data.conversations) || [];
                    if (!result.ok || !result.data.success) {
                        historyStatus(result.data.message || 'Gagal memuat riwayat.');
                        return;
                    }
                    renderHistoryList(Array.isArray(items) ? items : []);
                })
                .catch(function () {
                    historyStatus('Gagal memuat riwayat. Coba lagi.');
                });
        }

        function renderHistoryList(items) {
            if (!historyListEl) {
                return;
            }

            historyListEl.innerHTML = '';

            if (!items.length) {
                var empty = document.createElement('p');
                empty.className = 'agent-chat-history-empty';
                empty.textContent = 'Belum ada percakapan. Kirim pesan untuk mulai.';
                historyListEl.appendChild(empty);
                return;
            }

            items.forEach(function (item) {
                var id = String(item.id || '');
                if (!id) {
                    return;
                }

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'agent-chat-history-item' + (id === conversationId ? ' is-current' : '');
                btn.setAttribute('role', 'listitem');

                var title = document.createElement('span');
                title.className = 'agent-chat-history-item-title';
                title.textContent = item.snippet || item.title || 'Percakapan';
                btn.appendChild(title);

                var meta = document.createElement('span');
                meta.className = 'agent-chat-history-item-meta';
                if (id === conversationId) {
                    var mark = document.createElement('span');
                    mark.className = 'agent-chat-history-item-current';
                    mark.textContent = 'Sedang dibuka';
                    meta.appendChild(mark);
                    if (item.updated_label) {
                        meta.appendChild(document.createTextNode(' · ' + item.updated_label));
                    }
                } else {
                    meta.textContent = item.updated_label || '';
                }
                btn.appendChild(meta);

                btn.addEventListener('click', function () {
                    openConversation(id);
                });
                historyListEl.appendChild(btn);
            });
        }

        function openConversation(id) {
            if (!id) {
                return;
            }
            if (id === conversationId && messagesEl && !document.getElementById('agent-chat-welcome')) {
                setHistoryOpen(false);
                return;
            }
            persistConversationId(id);
            setHistoryOpen(false);
            loadConversationHistory();
        }

        function renderThread(messages) {
            if (!messagesEl) {
                return;
            }

            messagesEl.innerHTML = '';
            setSuggestionsVisible(false);
            (messages || []).forEach(function (row) {
                if (!row) {
                    return;
                }
                if (row.role === 'user') {
                    appendUserMessage(row.content || '', { createdAt: row.created_at });
                } else if (row.role === 'assistant' && row.content) {
                    appendAssistantMessage(row.content, [], {
                        silent: true,
                        createdAt: row.created_at,
                    });
                }
            });
            scrollToBottom();
        }

        function loadConversationHistory() {
            if (!conversationId || !messagesUrlTpl) {
                return Promise.resolve();
            }

            if (threadRequest && typeof threadRequest.abort === 'function') {
                threadRequest.abort();
            }

            var url = messagesUrlTpl.replace('__ID__', encodeURIComponent(conversationId));
            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            threadRequest = controller;

            setSuggestionsVisible(false);

            return fetch(url, {
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, status: response.status, data: data || {} };
                    }).catch(function () {
                        return { ok: false, status: response.status, data: {} };
                    });
                })
                .then(function (result) {
                    if (controller && threadRequest !== controller) {
                        return;
                    }

                    if (result.status === 404) {
                        persistConversationId(null);
                        showWelcome();
                        return;
                    }

                    var messages = (result.data && result.data.messages) || [];
                    if (!result.ok || !result.data.success || !messages.length) {
                        if (!messagesEl || !messagesEl.querySelector('.agent-chat-message')) {
                            showWelcome();
                        }
                        return;
                    }

                    renderThread(messages);
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    if (!messagesEl || !messagesEl.querySelector('.agent-chat-message')) {
                        showWelcome();
                    }
                });
        }

        function restorePendingTour() {
            try {
                var raw = sessionStorage.getItem(TOUR_PENDING_KEY);
                if (!raw) {
                    return;
                }
                sessionStorage.removeItem(TOUR_PENDING_KEY);
                var pending = JSON.parse(raw);
                if (pending && pending.type === 'tour_highlight') {
                    hideChatForTour();
                    window.setTimeout(function () {
                        if (window.AgentTour) {
                            window.AgentTour.show(pending, tourHandlers());
                        }
                    }, 450);
                }
            } catch (e) {
                // ignore
            }
        }

        loadConversationHistory().then(restorePendingTour);

        function sendMessage(presetText, sendOptions) {
            sendOptions = sendOptions || {};
            var fromInput = presetText == null;
            var text = fromInput ? (input.value || '').trim() : String(presetText).trim();
            if (!text) return;

            var tourActive = !!(window.AgentTour && window.AgentTour.isActive());
            var tourControl = isTourControlMessage(text);
            var fromOverlay = !fromInput && tourActive && tourControl;

            if ((tourActive || hasPendingTour()) && !tourControl) {
                stopTourUi();
            }

            lastOutgoing = text;
            setHistoryOpen(false);
            setSuggestionsVisible(false);
            if (!fromOverlay && !sendOptions.skipUserBubble) {
                appendUserMessage(text);
            }
            if (fromInput) {
                input.value = '';
            }
            setLoading(true);

            fetch(chatUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    message: text,
                    conversation_id: conversationId,
                    page_path: pageContext().path,
                    page_title: pageContext().title,
                    page_menu: pageContext().menu,
                }),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    var data = result.data;

                    if (data.conversation_id) {
                        persistConversationId(data.conversation_id);
                    }

                    if (!result.ok || !data.success) {
                        appendAssistantMessage(data.message || 'Gagal memproses pesan.');
                        return;
                    }

                    var reply = data.reply || {};
                    appendAssistantMessage(reply.content || 'Selesai.', reply.attachments || []);
                })
                .catch(function () {
                    appendAssistantMessage('Terjadi kesalahan jaringan. Coba lagi.', [], { retry: true });
                })
                .finally(function () {
                    setLoading(false);
                    if (!window.AgentTour || !window.AgentTour.isActive()) {
                        input.focus();
                    }
                });
        }
    });
})();
