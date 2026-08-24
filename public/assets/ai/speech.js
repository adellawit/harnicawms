(function (window) {
    'use strict';

    var VOICE_KEY = 'agent_tour_voice';
    var TITANIE_RATE = 1.02;
    var TITANIE_PITCH = 1.05;
    var TITANIE_GREETING = 'Halo, aku Titanie. Asisten gudang kamu, siap bantu.';
    var HINT_NO_ID = 'Browser belum punya suara Indonesia. Di macOS: System Settings → Spoken Content.';
    var MALE_HINTS = ['damir', 'agus', 'ardi', 'andika', 'budi', 'farid', 'reza', 'male', 'pria', 'laki'];
    var FEMALE_HINTS = ['damayanti', 'gadis', 'female', 'wanita', 'perempuan', 'woman', 'girl'];

    var cachedVoice = null;
    var hasIndonesianVoice = false;
    var lastVoiceLogKey = '';
    var voicesBound = false;
    var speakTimer = null;
    var preferenceListeners = [];
    var voicesListeners = [];
    var speakingListeners = [];

    function speechSupported() {
        return typeof window.speechSynthesis !== 'undefined'
            && typeof window.SpeechSynthesisUtterance !== 'undefined';
    }

    function isVoiceOn() {
        try {
            return localStorage.getItem(VOICE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function notify(listeners, payload) {
        listeners.forEach(function (fn) {
            try {
                fn(payload);
            } catch (e) {
                // listener must not break TTS
            }
        });
    }

    function setVoiceOn(on) {
        try {
            localStorage.setItem(VOICE_KEY, on ? '1' : '0');
        } catch (e) {
            // ignore quota / private mode
        }
        if (!on) {
            cancelSpeech();
        }
        notify(preferenceListeners);
    }

    function onPreferenceChange(fn) {
        if (typeof fn === 'function') {
            preferenceListeners.push(fn);
        }
    }

    function onVoicesReady(fn) {
        if (typeof fn === 'function') {
            voicesListeners.push(fn);
        }
    }

    function onSpeakingChange(fn) {
        if (typeof fn === 'function') {
            speakingListeners.push(fn);
        }
    }

    function setSpeaking(on) {
        notify(speakingListeners, !!on);
    }

    function normalizeVoiceLang(lang) {
        return String(lang || '').toLowerCase().replace(/_/g, '-').trim();
    }

    function voiceNameBlob(voice) {
        return String(voice && voice.name ? voice.name : '').toLowerCase();
    }

    function hasHint(name, hints) {
        var i;
        for (i = 0; i < hints.length; i++) {
            if (name.indexOf(hints[i]) !== -1) {
                return true;
            }
        }
        return false;
    }

    function isIndonesianVoice(voice) {
        if (!voice) {
            return false;
        }

        var lang = normalizeVoiceLang(voice.lang);
        var name = voiceNameBlob(voice);

        if (lang === 'id' || lang.indexOf('id-') === 0) {
            return true;
        }
        if (lang === 'in' || lang.indexOf('in-id') === 0) {
            return true;
        }
        if (name.indexOf('bahasa') !== -1) {
            return true;
        }
        if (name.indexOf('indonesia') !== -1 || name.indexOf('indonesian') !== -1) {
            return true;
        }
        if (hasHint(name, FEMALE_HINTS) || hasHint(name, MALE_HINTS)) {
            return true;
        }

        return false;
    }

    function isFemaleVoice(voice) {
        return hasHint(voiceNameBlob(voice), FEMALE_HINTS);
    }

    function isMaleVoice(voice) {
        var name = voiceNameBlob(voice);
        if (isFemaleVoice(voice)) {
            return false;
        }
        if (hasHint(name, MALE_HINTS)) {
            return true;
        }
        if (/(neural2|wavenet|standard|studio)[- ]?[bc]\b/.test(name)) {
            return true;
        }
        return false;
    }

    function scoreIndonesianVoice(voice) {
        var lang = normalizeVoiceLang(voice.lang);
        var name = voiceNameBlob(voice);
        var score = 0;

        if (lang === 'id-id' || lang === 'id') {
            score += 100;
        } else if (lang.indexOf('id-') === 0) {
            score += 90;
        } else if (lang === 'in' || lang.indexOf('in-id') === 0) {
            score += 70;
        }

        if (name.indexOf('bahasa') !== -1 || name.indexOf('indonesia') !== -1 || name.indexOf('indonesian') !== -1) {
            score += 50;
        }

        if (isFemaleVoice(voice)) {
            score -= 250;
        } else if (isMaleVoice(voice)) {
            score += 80;
        }

        if (name.indexOf('google') !== -1 && !isFemaleVoice(voice)) {
            score += 20;
        }
        if (name.indexOf('online') !== -1 || name.indexOf('neural') !== -1) {
            score += 8;
        }
        if (voice.localService) {
            score += 4;
        }
        if (voice.default) {
            score += 1;
        }

        return score;
    }

    function pickFallbackVoice(voices) {
        var i;
        for (i = 0; i < voices.length; i++) {
            if (voices[i] && voices[i].default) {
                return voices[i];
            }
        }
        return voices[0] || null;
    }

    function voiceKey(voice) {
        if (!voice) {
            return 'none';
        }
        return String(voice.name || '') + '|' + String(voice.lang || '');
    }

    function logVoiceChoice(voice, indonesian) {
        var key = voiceKey(voice) + '|' + (indonesian ? 'id' : 'fb');
        if (key === lastVoiceLogKey) {
            return;
        }
        lastVoiceLogKey = key;

        if (typeof console === 'undefined') {
            return;
        }

        if (indonesian && voice) {
            if (typeof console.info === 'function') {
                console.info('[Titanie TTS] suara Indonesia (laki-laki jika ada):', voice.name, '(' + voice.lang + ')');
            }
            return;
        }

        if (typeof console.warn === 'function') {
            if (voice) {
                console.warn('[Titanie TTS] tidak ada suara Indonesia. Fallback:', voice.name, '(' + (voice.lang || '?') + '). Teks tetap bahasa Indonesia. macOS: System Settings → Spoken Content.');
            } else {
                console.warn('[Titanie TTS] browser tidak punya suara TTS.');
            }
        }
    }

    function pickVoice(force) {
        if (!speechSupported()) {
            cachedVoice = null;
            hasIndonesianVoice = false;
            return null;
        }

        if (!force && cachedVoice) {
            return cachedVoice;
        }

        var voices = window.speechSynthesis.getVoices() || [];
        var best = null;
        var bestScore = -Infinity;
        var i;
        var score;

        for (i = 0; i < voices.length; i++) {
            if (!isIndonesianVoice(voices[i])) {
                continue;
            }
            score = scoreIndonesianVoice(voices[i]);
            if (best === null || score > bestScore) {
                bestScore = score;
                best = voices[i];
            }
        }

        hasIndonesianVoice = !!best;
        cachedVoice = best || pickFallbackVoice(voices);
        logVoiceChoice(cachedVoice, hasIndonesianVoice);
        notify(preferenceListeners);

        return cachedVoice;
    }

    function onVoicesChanged() {
        var previousKey = voiceKey(cachedVoice);
        var previousWasId = hasIndonesianVoice;
        cachedVoice = null;
        var next = pickVoice(true);
        var info = {
            voice: next,
            key: voiceKey(next),
            previousKey: previousKey,
            isIndonesian: hasIndonesianVoice,
            previousWasId: previousWasId,
        };
        notify(voicesListeners, info);
    }

    function bindVoices() {
        if (!speechSupported() || voicesBound) {
            return;
        }
        voicesBound = true;
        if (typeof window.speechSynthesis.addEventListener === 'function') {
            window.speechSynthesis.addEventListener('voiceschanged', onVoicesChanged);
        } else {
            window.speechSynthesis.onvoiceschanged = onVoicesChanged;
        }
        pickVoice(true);
        window.setTimeout(function () {
            onVoicesChanged();
        }, 350);
    }

    function sanitizeNarration(text) {
        var out = String(text || '');
        out = out.replace(/```[\s\S]*?```/g, ' ');
        out = out.replace(/`([^`]+)`/g, '$1');
        out = out.replace(/\*\*([^*]+)\*\*/g, '$1');
        out = out.replace(/__([^_]+)__/g, '$1');
        out = out.replace(/\*([^*]+)\*/g, '$1');
        out = out.replace(/^#{1,6}\s+/gm, '');
        out = out.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
        out = out.replace(/!\[[^\]]*\]\([^)]+\)/g, ' ');
        out = out.replace(/^\s*\|?[ :\-|]+\|?\s*$/gm, ' ');
        out = out.replace(/\|/g, ' ');
        out = out.replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, '');
        out = out.replace(/[\u2600-\u27BF]/g, '');
        out = out.replace(/[\uFE0E\uFE0F]/g, '');
        out = out.replace(/[\u200D]/g, '');
        out = out.replace(/[_#*~]+/g, ' ');
        out = out.replace(/\s+/g, ' ').trim();
        return out;
    }

    function cancelSpeech() {
        if (speakTimer) {
            window.clearTimeout(speakTimer);
            speakTimer = null;
        }
        setSpeaking(false);
        if (speechSupported()) {
            try {
                window.speechSynthesis.cancel();
            } catch (e) {
                // ignore
            }
        }
    }

    function resumeSpeech() {
        if (!speechSupported()) {
            return;
        }
        try {
            window.speechSynthesis.resume();
        } catch (e) {
            // ignore
        }
    }

    function speak(text, options) {
        options = options || {};
        if (!speechSupported() || (!options.force && !isVoiceOn())) {
            return;
        }

        text = sanitizeNarration(text);
        if (!text) {
            return;
        }

        cancelSpeech();
        speakTimer = window.setTimeout(function () {
            speakTimer = null;
            if ((!options.force && !isVoiceOn()) || !speechSupported()) {
                return;
            }

            var utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = TITANIE_RATE;
            utterance.pitch = TITANIE_PITCH;
            var voice = pickVoice(true);
            if (voice) {
                utterance.voice = voice;
            }
            utterance.onstart = function () {
                setSpeaking(true);
                if (typeof options.onStart === 'function') {
                    options.onStart();
                }
            };
            utterance.onend = function () {
                setSpeaking(false);
                if (typeof options.onEnd === 'function') {
                    options.onEnd();
                }
            };
            utterance.onerror = function () {
                setSpeaking(false);
                if (typeof options.onEnd === 'function') {
                    options.onEnd();
                }
            };

            try {
                window.speechSynthesis.speak(utterance);
            } catch (e) {
                setSpeaking(false);
            }
        }, 60);
    }

    function speakGreeting(options) {
        speak(TITANIE_GREETING, options);
    }

    window.AgentSpeech = {
        VOICE_KEY: VOICE_KEY,
        HINT_NO_ID: HINT_NO_ID,
        GREETING: TITANIE_GREETING,
        RATE: TITANIE_RATE,
        PITCH: TITANIE_PITCH,
        supported: speechSupported,
        isOn: isVoiceOn,
        setOn: setVoiceOn,
        pickVoice: pickVoice,
        hasIndonesian: function () {
            return hasIndonesianVoice;
        },
        isIndonesianVoice: isIndonesianVoice,
        sanitize: sanitizeNarration,
        speak: speak,
        speakGreeting: speakGreeting,
        cancel: cancelSpeech,
        resume: resumeSpeech,
        bind: bindVoices,
        onPreferenceChange: onPreferenceChange,
        onVoicesReady: onVoicesReady,
        onSpeakingChange: onSpeakingChange,
    };

    bindVoices();
    window.addEventListener('pagehide', cancelSpeech);
})(window);
