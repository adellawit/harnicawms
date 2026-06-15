(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var root = document.getElementById('agent-chat-root');
        if (!root) return;

        var chatUrl = root.dataset.chatUrl;
        var newUrl = root.dataset.newUrl;
        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

        var fab = document.getElementById('agent-chat-fab');
        var panel = document.getElementById('agent-chat-panel');
        var closeBtn = document.getElementById('agent-chat-close');
        var newBtn = document.getElementById('agent-chat-new');
        var form = document.getElementById('agent-chat-form');
        var input = document.getElementById('agent-chat-input');
        var messagesEl = document.getElementById('agent-chat-messages');
        var typingEl = document.getElementById('agent-chat-typing');
        var sendBtn = document.getElementById('agent-chat-send');

        var conversationId = localStorage.getItem('agent_chat_conversation_id') || null;
        var panelOpen = localStorage.getItem('agent_chat_open') === '1';

        function setPanelOpen(open) {
            panelOpen = open;
            localStorage.setItem('agent_chat_open', open ? '1' : '0');
            panel.classList.toggle('d-none', !open);
        }

        if (panelOpen) {
            setPanelOpen(true);
        }

        fab.addEventListener('click', function () {
            setPanelOpen(!panelOpen);
            if (panelOpen) {
                input.focus();
            }
        });

        closeBtn.addEventListener('click', function () {
            setPanelOpen(false);
        });

        newBtn.addEventListener('click', function () {
            conversationId = null;
            localStorage.removeItem('agent_chat_conversation_id');
            messagesEl.innerHTML = '';
            appendAssistantMessage('Chat baru. Silakan tanyakan stok, produk, customer, atau penjualan.');
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            sendMessage();
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function appendUserMessage(text) {
            var wrap = document.createElement('div');
            wrap.className = 'agent-chat-message agent-chat-message-user';
            wrap.innerHTML = '<div class="agent-chat-bubble"></div>';
            wrap.querySelector('.agent-chat-bubble').textContent = text;
            messagesEl.appendChild(wrap);
            scrollToBottom();
        }

        function appendAssistantMessage(text, attachments) {
            var wrap = document.createElement('div');
            wrap.className = 'agent-chat-message agent-chat-message-assistant';
            var bubble = document.createElement('div');
            bubble.className = 'agent-chat-bubble';
            bubble.textContent = text;
            wrap.appendChild(bubble);

            if (attachments && attachments.length) {
                attachments.forEach(function (attachment) {
                    if (attachment.type === 'table') {
                        bubble.appendChild(renderTable(attachment));
                    }
                });
            }

            messagesEl.appendChild(wrap);
            scrollToBottom();
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

        function scrollToBottom() {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function setLoading(loading) {
            typingEl.classList.toggle('d-none', !loading);
            sendBtn.disabled = loading;
            input.disabled = loading;
        }

        function sendMessage() {
            var text = (input.value || '').trim();
            if (!text) return;

            appendUserMessage(text);
            input.value = '';
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
                        conversationId = data.conversation_id;
                        localStorage.setItem('agent_chat_conversation_id', conversationId);
                    }

                    if (!result.ok || !data.success) {
                        appendAssistantMessage(data.message || 'Gagal memproses pesan.');
                        return;
                    }

                    var reply = data.reply || {};
                    appendAssistantMessage(reply.content || 'Selesai.', reply.attachments || []);
                })
                .catch(function () {
                    appendAssistantMessage('Terjadi kesalahan jaringan. Coba lagi.');
                })
                .finally(function () {
                    setLoading(false);
                    input.focus();
                });
        }
    });
})();
