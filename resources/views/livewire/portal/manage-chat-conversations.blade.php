<div class="flex flex-col px-5 py-5 mx-auto max-w-screen">
    <x-mary-header title="Chat Conversations" subtitle="Manage patient chat conversations from Salun-at portal" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Search subject, patient name..." wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass" clearable class="w-72" />
        </x-slot:middle>
    </x-mary-header>

    {{-- Status filter tabs --}}
    <div class="flex gap-2 mb-4">
        @foreach(['all' => 'All', 'open' => 'Open', 'closed' => 'Closed'] as $value => $label)
            <button wire:click="$set('statusFilter', '{{ $value }}')"
                class="btn btn-sm {{ $statusFilter === $value ? 'btn-primary' : 'btn-ghost' }}">
                {{ $label }}
                @if($value === 'open' && $openCount > 0)
                    <span class="badge badge-sm badge-warning ml-1">{{ $openCount }}</span>
                @endif
            </button>
        @endforeach
        @if($unreadCount > 0)
            <span class="badge badge-sm badge-error self-center ml-2">{{ $unreadCount }} unread</span>
        @endif
    </div>

    <div class="bg-base-100 rounded-2xl shadow-xl border border-base-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-base-200">
                    <tr>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Patient</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Subject</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Last Message</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Status</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Updated</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($conversations as $conv)
                        @php
                            $unread = $conv->messages()->where('sender_type', 'patient')->whereNull('read_at')->count();
                        @endphp
                        <tr class="hover:bg-base-200/70 transition-colors border-b border-base-300 {{ $unread > 0 ? 'bg-primary/5' : '' }}">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-base-content text-sm">{{ $conv->patient?->fullname ?? '-' }}</div>
                                <div class="text-xs text-base-content/60 font-mono">{{ $conv->patient?->hpercode ?? 'N/A' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-sm text-base-content font-medium">{{ $conv->subject ?? 'General Inquiry' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-sm text-base-content/70 truncate max-w-[250px]">
                                    @if($conv->latestMessage)
                                        <span class="text-xs font-semibold {{ $conv->latestMessage->sender_type === 'staff' ? 'text-info' : 'text-secondary' }}">
                                            {{ $conv->latestMessage->sender_type === 'staff' ? 'Staff' : 'Patient' }}:
                                        </span>
                                        {{ $conv->latestMessage->body }}
                                    @else
                                        <span class="text-base-content/40">No messages</span>
                                    @endif
                                </div>
                                @if($unread > 0)
                                    <span class="badge badge-xs badge-primary mt-1">{{ $unread }} new</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($conv->status === 'open')
                                    <span class="badge badge-sm badge-success">Open</span>
                                @else
                                    <span class="badge badge-sm badge-ghost">Closed</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-base-content/60">{{ $conv->last_message_at?->format('M d, Y h:i A') ?? $conv->created_at?->format('M d, Y h:i A') }}</td>
                            <td class="py-3 px-4">
                                <div class="flex justify-center gap-1">
                                    <button class="btn btn-xs btn-primary" wire:click="openChat({{ $conv->id }})" title="Open Chat">
                                        <x-mary-icon name="o-chat-bubble-left-right" class="w-3 h-3" />
                                    </button>
                                    @if($conv->status === 'open')
                                        <button class="btn btn-xs btn-warning" wire:click="closeConversation({{ $conv->id }})" title="Close Conversation">
                                            <x-mary-icon name="o-x-circle" class="w-3 h-3" />
                                        </button>
                                    @else
                                        <button class="btn btn-xs btn-success" wire:click="reopenConversation({{ $conv->id }})" title="Reopen Conversation">
                                            <x-mary-icon name="o-arrow-path" class="w-3 h-3" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <x-mary-icon name="o-chat-bubble-left-right" class="w-16 h-16 text-base-content/30 mb-4" />
                                    <span class="text-xl font-bold text-base-content/60">No conversations found</span>
                                    <span class="text-sm text-base-content/50 mt-2">Patient chat conversations from the Salun-at app will appear here</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $conversations->links() }}
        </div>
    </div>

    {{-- Chat Drawer (DaisyUI) --}}
    <div class="drawer drawer-end" x-data="{ open: @entangle('chatModal') }">
        <input type="checkbox" class="drawer-toggle" :checked="open" />
        <div class="drawer-side z-50">
            <label class="drawer-overlay" @click="open = false"></label>
            <div class="bg-base-100 w-[90vw] lg:w-[420px] h-full flex flex-col">
                @if($activeConversation)
                    {{-- Header --}}
                    <div class="px-4 py-3 border-b border-base-300 bg-base-200 flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-bold text-base-content truncate">{{ $activeConversation->subject ?? 'General Inquiry' }}</h3>
                            <p class="text-xs text-base-content/60">
                                {{ $activeConversation->patient?->fullname ?? 'Unknown Patient' }}
                                <span class="font-mono">({{ $activeConversation->patient?->hpercode ?? 'N/A' }})</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                            @if($activeConversation->status === 'open')
                                <span class="badge badge-success badge-sm">Open</span>
                            @else
                                <span class="badge badge-ghost badge-sm">Closed</span>
                            @endif
                            <button @click="open = false" class="btn btn-ghost btn-xs text-base-content">
                                <x-mary-icon name="o-x-mark" class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    {{-- Messages --}}
                    <div class="flex-1 overflow-y-auto space-y-3 p-3 bg-base-200/50" id="chat-messages-container"
                        x-data x-init="
                            let el = $el;
                            el.scrollTop = el.scrollHeight;
                            new MutationObserver(() => el.scrollTop = el.scrollHeight).observe(el, { childList: true, subtree: true });
                        ">
                        @forelse($messages as $msg)
                            <div class="flex {{ $msg['sender_type'] === 'staff' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[80%] rounded-2xl px-4 py-2 {{ $msg['sender_type'] === 'staff' ? 'bg-primary text-primary-content' : 'bg-base-100 border border-base-300 text-base-content' }}">
                                    <div class="text-xs font-semibold mb-1 {{ $msg['sender_type'] === 'staff' ? 'text-primary-content/80' : 'text-secondary' }}">
                                        {{ $msg['sender_name'] ?? ($msg['sender_type'] === 'staff' ? 'Staff' : 'Patient') }}
                                    </div>
                                    <div class="text-sm whitespace-pre-wrap break-words">{{ $msg['body'] }}</div>
                                    <div class="text-xs mt-1 {{ $msg['sender_type'] === 'staff' ? 'text-primary-content/70' : 'text-base-content/50' }}">
                                        {{ \Carbon\Carbon::parse($msg['created_at'])->format('M d, h:i A') }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-base-content/50 py-8">No messages in this conversation.</div>
                        @endforelse
                    </div>

                    {{-- Reply box / Actions --}}
                    <div class="border-t border-base-300 bg-base-100 p-3">
                        @if($activeConversation->status === 'open')
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <x-mary-textarea wire:model.live="replyBody" placeholder="Type your reply..." rows="2" />
                                </div>
                                <x-mary-button icon="o-paper-airplane" wire:click="sendReply"
                                    class="btn-primary btn-sm" spinner="sendReply" />
                            </div>
                            <div class="mt-2 text-right">
                                <x-mary-button label="Close Conversation" wire:click="closeConversation({{ $activeConversation->id }})" class="btn-warning btn-xs" />
                            </div>
                        @else
                            <div class="text-center text-base-content/50 text-sm py-2 bg-base-200 rounded-lg">
                                This conversation is closed.
                                <button wire:click="reopenConversation({{ $activeConversation->id }})" class="text-primary underline ml-1">Reopen</button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
