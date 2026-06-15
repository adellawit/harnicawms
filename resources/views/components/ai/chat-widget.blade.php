@if(config('agent.enabled') && config('agent.widget_enabled') && auth()->check() && \App\Support\Ai\ChatAccess::canShowWidget(auth()->user()))
        <link rel="stylesheet" href="{{ asset('assets/ai/chat.css') }}">

        <div id="agent-chat-root"
            data-chat-url="{{ route('agent.chat') }}"
            data-new-url="{{ route('agent.conversations.new') }}"
            data-messages-url="{{ route('agent.conversations.messages', ['conversationId' => '__ID__']) }}"
            data-branch="{{ auth()->user()->businessUnit?->name ?? 'Cabang aktif' }}">

            <button type="button" class="agent-chat-fab" id="agent-chat-fab" aria-label="Buka WMS Assistant">
                <span class="agent-chat-fab-label">AI</span>
            </button>

            <div class="agent-chat-panel d-none" id="agent-chat-panel" aria-live="polite">
                <div class="agent-chat-header">
                    <div>
                        <div class="agent-chat-title">WMS Assistant</div>
                        <div class="agent-chat-subtitle" id="agent-chat-branch">{{ auth()->user()->businessUnit?->name ?? 'Cabang aktif' }}</div>
                    </div>
                    <div class="agent-chat-header-actions">
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary" id="agent-chat-new" title="Chat baru">
                            <i class="ti ti-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary" id="agent-chat-close" title="Tutup">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                </div>

                <div class="agent-chat-messages" id="agent-chat-messages">
                    <div class="agent-chat-message agent-chat-message-assistant">
                        <div class="agent-chat-bubble">
                            Halo! Saya asisten WMS. Tanyakan stok, produk, customer, atau penjualan hari ini.
                        </div>
                    </div>
                </div>

                <div class="agent-chat-typing d-none" id="agent-chat-typing">
                    <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>
                    Memproses...
                </div>

                <form class="agent-chat-form" id="agent-chat-form">
                    <textarea
                        id="agent-chat-input"
                        class="form-control agent-chat-input"
                        rows="2"
                        maxlength="{{ (int) config('agent.max_message_length', 2000) }}"
                        placeholder="Contoh: stok kopi arabica berapa?"
                        required
                    ></textarea>
                    <button type="submit" class="btn btn-primary agent-chat-send" id="agent-chat-send">
                        <i class="ti ti-send"></i>
                    </button>
                </form>
            </div>
        </div>

        <script src="{{ asset('assets/ai/chat.js') }}"></script>
@endif
