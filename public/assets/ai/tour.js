(function (window, document) {
    'use strict';

    var PAD = 8;
    var GAP = 14;
    var overlay = null;
    var spotlight = null;
    var tooltip = null;
    var stepEl = null;
    var titleEl = null;
    var copyEl = null;
    var backBtn = null;
    var nextBtn = null;
    var skipBtn = null;
    var voiceBtn = null;
    var targetEl = null;
    var handlers = {};
    var repositionBound = false;
    var keyBound = false;
    var currentIsLast = false;
    var currentNarration = '';
    var speakOnNextShow = false;
    var introSpoken = false;
    var voicesHooked = false;
    var VOICE_PENDING_KEY = 'agent_tour_voice_pending';
    var ICON_MUTED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>';
    var ICON_ON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path></svg>';

    function cssEscape(value) {
        return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function queryFirst(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var selector = selectors[i];
            if (!selector) {
                continue;
            }
            try {
                var el = document.querySelector(selector);
                if (el) {
                    return el;
                }
            } catch (e) {
                // invalid selector from catalog — skip
            }
        }
        return null;
    }

    function splitSelectors(value) {
        if (!value) {
            return [];
        }
        return String(value).split(',').map(function (part) {
            return part.trim();
        }).filter(Boolean);
    }

    function findMenuByNames(names) {
        var needles = (names || []).map(function (name) {
            return String(name || '').trim().toLowerCase();
        }).filter(Boolean);

        if (!needles.length) {
            return null;
        }

        var links = document.querySelectorAll('#layout-menu a.menu-link');
        for (var i = 0; i < links.length; i++) {
            var link = links[i];
            var label = link.querySelector('[data-i18n], div');
            var text = ((label && label.textContent) || link.getAttribute('data-menu') || link.textContent || '')
                .trim()
                .toLowerCase();

            if (!text) {
                continue;
            }

            for (var n = 0; n < needles.length; n++) {
                if (text === needles[n] || text.indexOf(needles[n]) !== -1 || needles[n].indexOf(text) !== -1) {
                    return link;
                }
            }
        }

        return null;
    }

    function closestHighlightable(el) {
        if (!el || !el.closest) {
            return el;
        }

        return el.closest('button, a.btn, .btn, a.menu-link, .menu-link, h4, h5, .card-header, .card, table, form, .floating-footer, .dt-action-buttons, .head-label, .card-datatable') || el;
    }

    function findPageTarget(attachment) {
        var selectors = splitSelectors(attachment.selector);
        var target = queryFirst(selectors);
        if (target) {
            return closestHighlightable(target);
        }

        var key = String(attachment.spot_key || '');
        if (key === 'add') {
            target = queryFirst([
                '.dt-action-buttons .create-new',
                '.content-wrapper .create-new',
                '.dt-action-buttons .ti-plus',
                '.content-wrapper a.btn-primary[href*="insert"]',
            ]);
            return closestHighlightable(target);
        }
        if (key === 'filter') {
            target = queryFirst([
                '.dt-action-buttons .ti-filter',
                'button[data-bs-target="#filterModal"]',
                '.dt-action-buttons .btn-warning',
            ]);
            return closestHighlightable(target);
        }
        if (key === 'table') {
            return queryFirst([
                '.content-wrapper #table',
                '.content-wrapper .card-datatable',
                '.content-wrapper table.table',
                '.content-wrapper .card',
            ]);
        }
        if (key === 'save') {
            return queryFirst([
                '#btn-submit',
                '.floating-footer .btn-primary',
                '.content-wrapper button[type="submit"].btn-primary',
            ]);
        }
        if (key === 'fields') {
            return queryFirst([
                '#postForm .accordion-body',
                '#postForm',
                '.content-wrapper form',
            ]);
        }
        if (key === 'cards') {
            return queryFirst([
                '.content-wrapper .card',
                '.content-wrapper .row .card',
            ]);
        }

        return queryFirst(splitSelectors(attachment.heading_selector).concat([
            '.content-wrapper .head-label h4',
            '.content-wrapper h4.card-title',
            '.content-wrapper h4',
            '.content-wrapper h5',
            '.content-wrapper .card',
        ]));
    }

    function findTourTarget(attachment) {
        if (String(attachment.kind || '') === 'page') {
            return findPageTarget(attachment);
        }

        var selectors = splitSelectors(attachment.selector);
        var names = [].concat(attachment.menu_names || [], attachment.label ? [attachment.label] : []);

        names.forEach(function (name) {
            var safe = cssEscape(name);
            if (safe) {
                selectors.push('#layout-menu a.menu-link[data-menu="' + safe + '"]');
            }
        });

        var url = attachment.navigate_url || attachment.url || '';
        if (url && url.charAt(0) === '/') {
            selectors.push('#layout-menu a.menu-link[href*="' + cssEscape(url) + '"]');
        }

        selectors.push('#layout-menu .menu-item.active > a.menu-link');

        var target = queryFirst(selectors);
        if (target) {
            return target;
        }

        target = findMenuByNames(names);
        if (target) {
            return target;
        }

        return queryFirst(splitSelectors(attachment.heading_selector).concat([
            '.content-wrapper .card-header',
            '.content-wrapper h4',
            '.content-wrapper h5',
            '#layout-navbar',
        ]));
    }

    function ensureSidebarVisible() {
        if (window.innerWidth < 1200) {
            document.documentElement.classList.add('layout-menu-expanded');
        }
    }

    function revealMenuTarget(el) {
        var item = el && el.closest ? el.closest('.menu-item') : null;
        while (item) {
            item.classList.add('open', 'active');
            item = item.parentElement ? item.parentElement.closest('.menu-item') : null;
        }
        if (el && el.scrollIntoView) {
            el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    function speech() {
        return window.AgentSpeech || null;
    }

    function speechSupported() {
        return !!(speech() && speech().supported());
    }

    function isVoiceOn() {
        return !!(speech() && speech().isOn());
    }

    function setVoiceOn(on) {
        if (speech()) {
            speech().setOn(on);
        }
        syncVoiceButton();
    }

    function hasIndonesianVoice() {
        return !!(speech() && speech().hasIndonesian());
    }

    function sanitizeNarration(text) {
        return speech() ? speech().sanitize(text) : String(text || '').replace(/\s+/g, ' ').trim();
    }

    function withCharacterIntro(text) {
        if (introSpoken) {
            return text;
        }
        introSpoken = true;
        if (/(titanie|reddie)/i.test(text)) {
            return text;
        }
        return 'Aku REDDIE. ' + text;
    }

    function setSpeaking(on) {
        if (voiceBtn) {
            voiceBtn.classList.toggle('is-speaking', !!on);
        }
    }

    function cancelSpeech() {
        setSpeaking(false);
        if (speech()) {
            speech().cancel();
        }
    }

    function speak(text) {
        if (!speechSupported() || !isVoiceOn()) {
            return;
        }

        text = withCharacterIntro(sanitizeNarration(text));
        if (!text) {
            return;
        }

        speech().speak(text, {
            onStart: function () {
                setSpeaking(true);
            },
            onEnd: function () {
                setSpeaking(false);
            },
        });
    }

    function speakCurrent() {
        speak(currentNarration);
    }

    function hookSpeech() {
        if (voicesHooked || !speech()) {
            return;
        }
        voicesHooked = true;
        speech().bind();
        speech().onPreferenceChange(syncVoiceButton);
        speech().onSpeakingChange(setSpeaking);
        speech().onVoicesReady(function (info) {
            if (
                isVoiceOn()
                && isActive()
                && currentNarration
                && info.voice
                && info.key !== info.previousKey
                && info.isIndonesian
                && !info.previousWasId
            ) {
                speakCurrent();
            }
        });
    }

    function syncVoiceButton() {
        if (!voiceBtn) {
            return;
        }

        var supported = speechSupported();
        voiceBtn.classList.toggle('d-none', !supported);
        if (!supported) {
            return;
        }

        var on = isVoiceOn();
        var hasId = hasIndonesianVoice();
        var hint = (speech() && speech().HINT_NO_ID) || '';
        voiceBtn.innerHTML = on ? ICON_ON : ICON_MUTED;
        voiceBtn.classList.toggle('is-on', on);
        voiceBtn.classList.toggle('is-fallback', on && !hasId);
        voiceBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (on) {
            voiceBtn.title = hasId
                ? 'Matikan suara tur'
                : 'Matikan suara tur. ' + hint;
        } else {
            voiceBtn.title = hasId
                ? 'Nyalakan suara tur'
                : 'Nyalakan suara tur. ' + hint;
        }
        voiceBtn.setAttribute('aria-label', on ? 'Matikan suara tur' : 'Nyalakan suara tur');
        if (!hasId) {
            voiceBtn.setAttribute('aria-description', hint);
        } else {
            voiceBtn.removeAttribute('aria-description');
        }
    }

    function clearVoicePending() {
        try {
            sessionStorage.removeItem(VOICE_PENDING_KEY);
        } catch (e) {
            // ignore
        }
    }

    function armSpeakForNextShow() {
        speakOnNextShow = true;
        try {
            if (isVoiceOn()) {
                sessionStorage.setItem(VOICE_PENDING_KEY, '1');
            } else {
                sessionStorage.removeItem(VOICE_PENDING_KEY);
            }
        } catch (e) {
            // ignore
        }
    }

    function primeSpeech() {
        if (!speechSupported() || !isVoiceOn()) {
            return;
        }
        speech().resume();
    }

    function requestNext() {
        cancelSpeech();
        armSpeakForNextShow();
        primeSpeech();
        if (handlers.onNext) {
            handlers.onNext();
        }
    }

    function requestFinish() {
        cancelSpeech();
        speakOnNextShow = false;
        clearVoicePending();
        if (handlers.onFinish) {
            handlers.onFinish();
        } else if (handlers.onSkip) {
            handlers.onSkip();
        }
    }

    function requestPrev() {
        cancelSpeech();
        armSpeakForNextShow();
        primeSpeech();
        if (handlers.onPrev) {
            handlers.onPrev();
        }
    }

    function requestSkip() {
        cancelSpeech();
        speakOnNextShow = false;
        clearVoicePending();
        if (handlers.onSkip) {
            handlers.onSkip();
        }
    }

    function maybeSpeakAfterShow() {
        if (!speechSupported() || !isVoiceOn()) {
            speakOnNextShow = false;
            clearVoicePending();
            return;
        }

        var pending = false;
        try {
            pending = sessionStorage.getItem(VOICE_PENDING_KEY) === '1';
            sessionStorage.removeItem(VOICE_PENDING_KEY);
        } catch (e) {
            pending = false;
        }

        if (speakOnNextShow || pending) {
            speakOnNextShow = false;
            speakCurrent();
        }
    }

    function ensureDom() {
        if (overlay) {
            return;
        }

        overlay = document.createElement('div');
        overlay.id = 'agent-tour-overlay';
        overlay.className = 'agent-tour-overlay d-none';
        overlay.setAttribute('aria-hidden', 'true');

        spotlight = document.createElement('div');
        spotlight.id = 'agent-tour-spotlight';
        spotlight.className = 'agent-tour-spotlight';
        overlay.appendChild(spotlight);

        tooltip = document.createElement('div');
        tooltip.id = 'agent-tour-tooltip';
        tooltip.className = 'agent-tour-tooltip';
        tooltip.setAttribute('role', 'dialog');
        tooltip.setAttribute('aria-modal', 'true');
        tooltip.setAttribute('aria-labelledby', 'agent-tour-tooltip-title');

        var head = document.createElement('div');
        head.className = 'agent-tour-tooltip-head';

        stepEl = document.createElement('div');
        stepEl.className = 'agent-tour-tooltip-step';
        head.appendChild(stepEl);

        voiceBtn = document.createElement('button');
        voiceBtn.type = 'button';
        voiceBtn.className = 'agent-tour-voice';
        voiceBtn.addEventListener('click', function () {
            if (!speechSupported()) {
                return;
            }
            var next = !isVoiceOn();
            setVoiceOn(next);
            if (next) {
                speakOnNextShow = false;
                clearVoicePending();
                speakCurrent();
            } else {
                cancelSpeech();
            }
        });
        head.appendChild(voiceBtn);
        tooltip.appendChild(head);
        hookSpeech();
        syncVoiceButton();

        titleEl = document.createElement('h3');
        titleEl.id = 'agent-tour-tooltip-title';
        titleEl.className = 'agent-tour-tooltip-title';
        tooltip.appendChild(titleEl);

        copyEl = document.createElement('p');
        copyEl.className = 'agent-tour-tooltip-copy';
        tooltip.appendChild(copyEl);

        var actions = document.createElement('div');
        actions.className = 'agent-tour-tooltip-actions';

        skipBtn = document.createElement('button');
        skipBtn.type = 'button';
        skipBtn.className = 'agent-tour-btn agent-tour-btn-ghost';
        skipBtn.textContent = 'Lewati';
        skipBtn.addEventListener('click', function () {
            requestSkip();
        });

        var nav = document.createElement('div');
        nav.className = 'agent-tour-tooltip-nav';

        backBtn = document.createElement('button');
        backBtn.type = 'button';
        backBtn.className = 'agent-tour-btn agent-tour-btn-secondary';
        backBtn.textContent = 'Kembali';
        backBtn.addEventListener('click', function () {
            requestPrev();
        });

        nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'agent-tour-btn agent-tour-btn-primary';
        nextBtn.textContent = 'Lanjut';
        nextBtn.setAttribute('data-tour-action', 'next');
        nextBtn.addEventListener('click', function () {
            if (currentIsLast) {
                requestFinish();
            } else {
                requestNext();
            }
        });

        nav.appendChild(backBtn);
        nav.appendChild(nextBtn);
        actions.appendChild(skipBtn);
        actions.appendChild(nav);
        tooltip.appendChild(actions);
        overlay.appendChild(tooltip);
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                event.preventDefault();
            }
        });

        if (!repositionBound) {
            repositionBound = true;
            window.addEventListener('resize', position);
            window.addEventListener('scroll', position, true);
        }

        if (!keyBound) {
            keyBound = true;
            document.addEventListener('keydown', function (event) {
                if (!isActive()) {
                    return;
                }
                var tag = (event.target && event.target.tagName) || '';
                if (tag === 'INPUT' || tag === 'TEXTAREA' || (event.target && event.target.isContentEditable)) {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        requestSkip();
                    }
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    requestSkip();
                } else if (event.key === 'ArrowRight' || event.key === 'Enter') {
                    event.preventDefault();
                    if (currentIsLast) {
                        requestFinish();
                    } else {
                        requestNext();
                    }
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    if (backBtn && !backBtn.disabled) {
                        requestPrev();
                    }
                }
            });
        }
    }

    function fillTooltip(attachment, missingTarget) {
        var label = attachment.label || 'Ruang ini';
        var step = Number(attachment.step) || 0;
        var total = Number(attachment.total) || 0;
        var isLast = attachment.is_last === true || (total > 0 && step >= total);
        currentIsLast = isLast;
        var hasPrev = attachment.has_prev === true || step > 1;
        var body = String(attachment.body || attachment.blurb || '').trim();
        var voice = String(attachment.voice || '').trim();

        titleEl.textContent = label;
        stepEl.textContent = (step && total) ? (step + ' / ' + total) : '';
        stepEl.classList.toggle('d-none', !(step && total));

        if (missingTarget) {
            copyEl.textContent = body
                ? body
                : (String(attachment.kind || '') === 'page'
                    ? 'Bagian ini tidak terlihat di halaman (mungkin di luar izinmu). Kamu tetap bisa lanjut.'
                    : 'Menu ' + label + ' tidak terlihat di sidebar (mungkin di luar izinmu). Kamu tetap bisa lanjut ke ruang berikutnya.');
        } else {
            copyEl.textContent = body || ('Ini ruang ' + label + '.');
        }

        if (!voice) {
            voice = copyEl.textContent || ('Ini ruang ' + label + '.');
        }
        currentNarration = sanitizeNarration(voice);
        syncVoiceButton();

        backBtn.disabled = !hasPrev;
        backBtn.setAttribute('aria-disabled', hasPrev ? 'false' : 'true');
        nextBtn.textContent = isLast ? 'Selesai' : 'Lanjut';
        nextBtn.setAttribute('data-tour-action', isLast ? 'finish' : 'next');
        skipBtn.textContent = isLast ? 'Tutup' : 'Lewati';
        skipBtn.classList.toggle('d-none', isLast);
    }

    function position() {
        if (!overlay || overlay.classList.contains('d-none')) {
            return;
        }

        if (!targetEl || !document.body.contains(targetEl)) {
            spotlight.classList.add('is-idle');
            tooltip.classList.add('is-centered');
            tooltip.removeAttribute('data-placement');
            tooltip.style.top = '';
            tooltip.style.left = '';
            return;
        }

        spotlight.classList.remove('is-idle');
        tooltip.classList.remove('is-centered');

        var rect = targetEl.getBoundingClientRect();
        var top = Math.max(8, rect.top - PAD);
        var left = Math.max(8, rect.left - PAD);
        var width = Math.min(window.innerWidth - left - 8, rect.width + PAD * 2);
        var height = Math.min(window.innerHeight - top - 8, rect.height + PAD * 2);

        spotlight.style.top = top + 'px';
        spotlight.style.left = left + 'px';
        spotlight.style.width = Math.max(28, width) + 'px';
        spotlight.style.height = Math.max(28, height) + 'px';

        var tw = tooltip.offsetWidth || 320;
        var th = tooltip.offsetHeight || 180;
        var spaceRight = window.innerWidth - (left + width);
        var spaceLeft = left;
        var spaceBottom = window.innerHeight - (top + height);
        var place = 'right';
        var ttTop = top;
        var ttLeft = left + width + GAP;

        if (spaceRight >= tw + GAP + 12) {
            place = 'right';
            ttLeft = left + width + GAP;
            ttTop = top;
        } else if (spaceLeft >= tw + GAP + 12) {
            place = 'left';
            ttLeft = left - tw - GAP;
            ttTop = top;
        } else if (spaceBottom >= th + GAP + 12) {
            place = 'bottom';
            ttLeft = left;
            ttTop = top + height + GAP;
        } else {
            place = 'top';
            ttLeft = left;
            ttTop = Math.max(12, top - th - GAP);
        }

        ttTop = Math.max(12, Math.min(ttTop, window.innerHeight - th - 12));
        ttLeft = Math.max(12, Math.min(ttLeft, window.innerWidth - tw - 12));

        tooltip.style.top = ttTop + 'px';
        tooltip.style.left = ttLeft + 'px';
        tooltip.setAttribute('data-placement', place);
    }

    function clearTargetClass() {
        document.querySelectorAll('.agent-tour-target').forEach(function (el) {
            el.classList.remove('agent-tour-target');
        });
        targetEl = null;
    }

    function isActive() {
        return !!(overlay && !overlay.classList.contains('d-none'));
    }

    function setLoading(loading) {
        if (!overlay) {
            return;
        }
        overlay.classList.toggle('is-loading', !!loading);
        if (backBtn) {
            backBtn.disabled = loading || backBtn.getAttribute('aria-disabled') === 'true';
        }
        if (nextBtn) {
            nextBtn.disabled = !!loading;
        }
        if (skipBtn) {
            skipBtn.disabled = !!loading;
        }
    }

    function clear() {
        cancelSpeech();
        speakOnNextShow = false;
        clearVoicePending();
        currentNarration = '';
        currentIsLast = false;
        introSpoken = false;
        setLoading(false);
        clearTargetClass();
        handlers = {};
        if (overlay) {
            overlay.classList.add('d-none');
            overlay.setAttribute('aria-hidden', 'true');
        }
        var root = document.getElementById('agent-chat-root');
        if (root) {
            root.classList.remove('is-touring');
        }
    }

    function show(attachment, nextHandlers) {
        if (!attachment || attachment.operation === 'stop' || attachment.active === false) {
            clear();
            return;
        }

        ensureDom();
        handlers = nextHandlers || handlers || {};
        clearTargetClass();

        var root = document.getElementById('agent-chat-root');
        if (root) {
            root.classList.add('is-touring');
        }

        targetEl = findTourTarget(attachment);
        fillTooltip(attachment, !targetEl);

        if (targetEl) {
            var kind = String(attachment.kind || '');
            if (kind !== 'page') {
                ensureSidebarVisible();
                revealMenuTarget(targetEl);
            } else if (targetEl.scrollIntoView) {
                targetEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
            targetEl.classList.add('agent-tour-target');
        }

        overlay.classList.remove('d-none');
        overlay.setAttribute('aria-hidden', 'false');
        setLoading(false);

        window.setTimeout(position, 40);
        window.setTimeout(position, 280);

        var chatPanel = document.getElementById('agent-chat-panel');
        var chatInput = document.getElementById('agent-chat-input');
        var panelVisible = chatPanel && !chatPanel.classList.contains('d-none');
        var typingInChat = panelVisible && chatInput && document.activeElement === chatInput;
        if (!typingInChat && nextBtn) {
            nextBtn.focus();
        }

        maybeSpeakAfterShow();
    }

    window.AgentTour = {
        show: show,
        setLoading: setLoading,
        clear: clear,
        isActive: isActive,
        findTourTarget: findTourTarget,
        cancelSpeech: cancelSpeech,
    };

    hookSpeech();
})(window, document);
