(function () {
    'use strict';

    var cfg = window.agentTrainingProgressConfig || {};
    var timers = [];
    var pendingMaterialId = null;
    var durationModal = null;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function formatMmSs(totalSeconds) {
        var sec = Math.max(0, parseInt(totalSeconds, 10) || 0);
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(body || {}),
            credentials: 'same-origin',
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('Request failed');
            }
            return res.json();
        });
    }

    function saveProgress(materialId, elapsedSeconds) {
        var base = cfg.progressUrl || '';
        return postJson(base + '/' + materialId + '/progress', {
            elapsed_seconds: elapsedSeconds,
        });
    }

    function completeMaterial(materialId) {
        var base = cfg.progressUrl || '';
        return postJson(base + '/' + materialId + '/complete', {});
    }

    function markMaterialDone(article) {
        article.setAttribute('data-completed', '1');
        var btn = article.querySelector('.agent-training-mark-done');
        if (btn) {
            btn.remove();
        }
        if (!article.querySelector('.agent-training-done-badge')) {
            var badge = document.createElement('span');
            badge.className = 'badge bg-label-success agent-training-done-badge';
            badge.innerHTML = '<i class="ti ti-check me-1"></i>Selesai';
            var meta = article.querySelector('.agent-training-material-meta');
            if (meta) {
                meta.appendChild(badge);
            }
        }
    }

    function stopTimer(state) {
        if (state.intervalId) {
            clearInterval(state.intervalId);
            state.intervalId = null;
        }
    }

    function updateTimerDisplay(state) {
        if (!state.timerEl) {
            return;
        }
        var remaining = Math.max(0, state.duration - state.elapsed);
        state.timerEl.textContent = formatMmSs(remaining);
    }

    function showDurationEndedModal(state) {
        if (state.completed || state.promptShown) {
            return;
        }
        state.promptShown = true;
        pendingMaterialId = state.materialId;
        if (durationModal) {
            durationModal.show();
        }
    }

    function initMaterial(article) {
        var materialId = article.getAttribute('data-material-id');
        var completed = article.getAttribute('data-completed') === '1';
        var duration = parseInt(article.getAttribute('data-duration') || '0', 10);
        var elapsed = parseInt(article.getAttribute('data-elapsed') || '0', 10);

        var state = {
            article: article,
            materialId: materialId,
            duration: duration,
            elapsed: elapsed,
            completed: completed,
            intervalId: null,
            lastSavedAt: 0,
            promptShown: false,
            paused: false,
            timerEl: article.querySelector('.agent-training-timer'),
        };

        var markBtn = article.querySelector('.agent-training-mark-done');
        if (markBtn) {
            markBtn.addEventListener('click', function () {
                completeMaterial(materialId).then(function () {
                    state.completed = true;
                    stopTimer(state);
                    markMaterialDone(article);
                }).catch(function () {});
            });
        }

        if (!duration || completed) {
            if (state.timerEl && duration) {
                updateTimerDisplay(state);
            }
            return state;
        }

        updateTimerDisplay(state);

        state.intervalId = setInterval(function () {
            if (state.completed || state.paused) {
                return;
            }

            state.elapsed += 1;
            updateTimerDisplay(state);

            var now = Date.now();
            var saveEveryMs = (cfg.saveIntervalSec || 15) * 1000;
            if (now - state.lastSavedAt >= saveEveryMs) {
                state.lastSavedAt = now;
                saveProgress(materialId, state.elapsed).catch(function () {});
            }

            if (state.elapsed >= state.duration) {
                stopTimer(state);
                updateTimerDisplay(state);
                showDurationEndedModal(state);
            }
        }, 1000);

        return state;
    }

    function flushAllProgress() {
        timers.forEach(function (state) {
            if (!state.completed && state.duration > 0) {
                saveProgress(state.materialId, state.elapsed).catch(function () {});
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('agentTrainingDurationModal');
        if (modalEl && window.bootstrap) {
            durationModal = new bootstrap.Modal(modalEl);
            modalEl.querySelector('[data-action="continue"]').addEventListener('click', function () {
                pendingMaterialId = null;
                durationModal.hide();
            });
            modalEl.querySelector('[data-action="complete"]').addEventListener('click', function () {
                if (!pendingMaterialId) {
                    durationModal.hide();
                    return;
                }
                var mid = pendingMaterialId;
                pendingMaterialId = null;
                completeMaterial(mid).then(function () {
                    timers.forEach(function (state) {
                        if (state.materialId === mid) {
                            state.completed = true;
                            stopTimer(state);
                            markMaterialDone(state.article);
                        }
                    });
                    durationModal.hide();
                }).catch(function () {
                    durationModal.hide();
                });
            });
        }

        document.querySelectorAll('[data-agent-training-material]').forEach(function (article) {
            timers.push(initMaterial(article));
        });

        document.addEventListener('visibilitychange', function () {
            var hidden = document.visibilityState === 'hidden';
            timers.forEach(function (state) {
                state.paused = hidden;
            });
            if (hidden) {
                flushAllProgress();
            }
        });

        window.addEventListener('beforeunload', function () {
            timers.forEach(function (state) {
                if (!state.completed && state.duration > 0) {
                    var base = cfg.progressUrl || '';
                    var url = base + '/' + state.materialId + '/progress';
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify({ elapsed_seconds: state.elapsed }),
                        keepalive: true,
                        credentials: 'same-origin',
                    }).catch(function () {});
                }
            });
        });
    });
})();
