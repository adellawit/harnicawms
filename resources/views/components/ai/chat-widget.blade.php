@if(config('agent.enabled') && config('agent.widget_enabled') && auth()->check() && \App\Support\Ai\ChatAccess::canShowWidget(auth()->user()))
        @php($assistantName = config('agent.assistant_name', 'TITANIE'))
        @php($mascotUrl = asset('assets/img/titanie/mascot-head.png').'?v='.filemtime(public_path('assets/img/titanie/mascot-head.png')))
        @php($mascotFabUrl = asset('assets/img/titanie/mascot-pose.png').'?v='.filemtime(public_path('assets/img/titanie/mascot-pose.png')))
        @php($mascotPoseUrl = asset('assets/img/titanie/mascot-pose.png').'?v='.filemtime(public_path('assets/img/titanie/mascot-pose.png')))
        <link rel="stylesheet" href="{{ asset('assets/ai/chat.css') }}?v={{ filemtime(public_path('assets/ai/chat.css')) }}">

        <div id="agent-chat-root"
            data-chat-url="{{ route('agent.chat') }}"
            data-confirm-url="{{ route('agent.actions.confirm') }}"
            data-tour-stop-url="{{ route('agent.tour.stop') }}"
            data-new-url="{{ route('agent.conversations.new') }}"
            data-list-url="{{ route('agent.conversations') }}"
            data-messages-url="{{ route('agent.conversations.messages', ['conversationId' => '__ID__']) }}"
            data-mascot-url="{{ $mascotUrl }}"
            data-branch="{{ auth()->user()->businessUnit?->name ?? 'Cabang aktif' }}">

            <button type="button" class="agent-chat-fab" id="agent-chat-fab" aria-label="Buka asisten {{ $assistantName }}" aria-expanded="false" aria-controls="agent-chat-panel">
                <span class="agent-chat-fab-glow" aria-hidden="true"></span>
                <span class="agent-chat-fab-unread" aria-hidden="true"></span>
                <img src="{{ $mascotFabUrl }}" alt="" class="agent-chat-fab-mascot">
            </button>

            <div class="agent-chat-panel d-none" id="agent-chat-panel" role="region" aria-labelledby="agent-chat-title" aria-hidden="true">
                <div class="agent-chat-header">
                    <div class="agent-chat-brand">
                        <span class="agent-chat-brand-well" aria-hidden="true">
                            <img src="{{ $mascotUrl }}" alt="" class="agent-chat-brand-mascot">
                        </span>
                        <div class="agent-chat-brand-copy">
                            <div class="agent-chat-kicker">Asisten gudang</div>
                            <div class="agent-chat-title" id="agent-chat-title">{{ $assistantName }}</div>
                            <div class="agent-chat-subtitle" id="agent-chat-branch">
                                <span class="agent-chat-status" aria-hidden="true"></span>
                                <span class="agent-chat-status-label">Online</span>
                                <span class="agent-chat-branch-sep" aria-hidden="true">·</span>
                                {{ auth()->user()->businessUnit?->name ?? 'Cabang aktif' }}
                            </div>
                        </div>
                    </div>
                    <div class="agent-chat-header-actions">
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary" id="agent-chat-voice" title="Nyalakan suara balasan" aria-pressed="false" aria-label="Nyalakan suara balasan">
                            <i class="ti ti-volume-off"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary" id="agent-chat-history-btn" title="Riwayat chat" aria-pressed="false" aria-expanded="false" aria-controls="agent-chat-history" aria-label="Buka riwayat chat">
                            <i class="ti ti-history"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary" id="agent-chat-new" title="Chat baru" aria-label="Mulai chat baru">
                            <i class="ti ti-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary" id="agent-chat-fullscreen" title="Layar penuh" aria-pressed="false" aria-label="Layar penuh">
                            <i class="ti ti-arrows-maximize"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary" id="agent-chat-close" title="Tutup" aria-label="Tutup chat">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                </div>

                <div class="agent-chat-body">
                    <div class="agent-chat-messages" id="agent-chat-messages" aria-live="polite">
                        <div class="agent-chat-welcome" id="agent-chat-welcome">
                            <img src="{{ $mascotPoseUrl }}" alt="" class="agent-chat-welcome-mascot">
                            <p class="agent-chat-welcome-kicker">Siap bantu di gudang</p>
                            <h2 class="agent-chat-welcome-title">Halo, aku {{ $assistantName }}</h2>
                            <p class="agent-chat-welcome-body">Tanyakan alur bisnis, cek stok atau produk, atau minta bantuan membuat penjualan tunai — langsung dari sini.</p>
                        </div>
                    </div>

                    <div class="agent-chat-suggestions" id="agent-chat-suggestions">
                        <p class="agent-chat-suggestions-label">Mulai dari sini</p>
                        <div class="agent-chat-suggestions-list" role="list">
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Apa sih ini?"><i class="ti ti-sparkles" aria-hidden="true"></i>Apa sih ini?</button>
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Turin fiturnya dong"><i class="ti ti-map-2" aria-hidden="true"></i>Turin fiturnya dong</button>
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Buka halaman kategori"><i class="ti ti-folder" aria-hidden="true"></i>Buka halaman kategori</button>
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Gimana alur order agen?"><i class="ti ti-route" aria-hidden="true"></i>Gimana alur order agen?</button>
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Tampilkan stok"><i class="ti ti-packages" aria-hidden="true"></i>Tampilkan stok</button>
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Tambahkan divisi Management"><i class="ti ti-users" aria-hidden="true"></i>Tambahkan divisi Management</button>
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Daftar produk"><i class="ti ti-box" aria-hidden="true"></i>Daftar produk</button>
                            <button type="button" class="agent-chat-suggestion" role="listitem" data-prompt="Jual 2 kopi arabica tunai"><i class="ti ti-cash" aria-hidden="true"></i>Jual 2 kopi arabica tunai</button>
                        </div>
                    </div>

                    <div class="agent-chat-typing d-none" id="agent-chat-typing" role="status" aria-live="polite">
                        <span class="agent-chat-typing-figure" aria-hidden="true">
                            <img src="{{ $mascotUrl }}" alt="" class="agent-chat-typing-mascot">
                        </span>
                        <span class="agent-chat-typing-copy">
                            <span class="agent-chat-typing-label">{{ $assistantName }} sedang berpikir</span>
                            <span class="agent-chat-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        </span>
                    </div>

                    <form class="agent-chat-form" id="agent-chat-form">
                        <label class="visually-hidden" for="agent-chat-input">Pesan untuk {{ $assistantName }}</label>
                        <textarea
                            id="agent-chat-input"
                            class="form-control agent-chat-input"
                            rows="2"
                            maxlength="{{ (int) config('agent.max_message_length', 2000) }}"
                            placeholder="Tanya Titanie: stok, alur, atau jual tunai…"
                            required
                        ></textarea>
                        <button type="submit" class="btn btn-primary agent-chat-send" id="agent-chat-send" aria-label="Kirim pesan" title="Kirim">
                            <i class="ti ti-send" aria-hidden="true"></i>
                        </button>
                    </form>

                    <div class="agent-chat-history d-none" id="agent-chat-history" hidden>
                        <div class="agent-chat-history-head">
                            <p class="agent-chat-history-title">Riwayat chat</p>
                            <button type="button" class="agent-chat-history-close" id="agent-chat-history-close" title="Tutup riwayat" aria-label="Tutup riwayat chat">
                                <i class="ti ti-x" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="agent-chat-history-list" id="agent-chat-history-list" role="list"></div>
                    </div>
                </div>
            </div>
        </div>

        <script src="{{ asset('assets/ai/speech.js') }}?v={{ filemtime(public_path('assets/ai/speech.js')) }}"></script>
        <script src="{{ asset('assets/ai/tour.js') }}?v={{ filemtime(public_path('assets/ai/tour.js')) }}"></script>
        <script src="{{ asset('assets/ai/chat.js') }}?v={{ filemtime(public_path('assets/ai/chat.js')) }}"></script>
        <script>
            document.body.classList.add('has-agent-chat');
            if (document.querySelector('.floating-footer')) {
                document.body.classList.add('has-agent-chat-with-footer');
            }
        </script>
@endif
