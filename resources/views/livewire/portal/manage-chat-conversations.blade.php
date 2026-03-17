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

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Patient</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Subject</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Last Message</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Status</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Updated</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($conversations as $conv)
                        @php
                            $unread = $conv->messages()->where('sender_type', 'patient')->whereNull('read_at')->count();
                        @endphp
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100 {{ $unread > 0 ? 'bg-purple-50' : '' }}">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900 text-sm">{{ $conv->patient?->fullname ?? '-' }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $conv->patient?->hpercode ?? 'N/A' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-sm text-gray-900 font-medium">{{ $conv->subject ?? 'General Inquiry' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-sm text-gray-600 truncate max-w-[250px]">
                                    @if($conv->latestMessage)
                                        <span class="text-xs font-semibold {{ $conv->latestMessage->sender_type === 'staff' ? 'text-blue-600' : 'text-purple-600' }}">
                                            {{ $conv->latestMessage->sender_type === 'staff' ? 'Staff' : 'Patient' }}:
                                        </span>
                                        {{ $conv->latestMessage->body }}
                                    @else
                                        <span class="text-gray-400">No messages</span>
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
                            <td class="py-3 px-4 text-xs text-gray-500">{{ $conv->last_message_at?->format('M d, Y h:i A') ?? $conv->created_at?->format('M d, Y h:i A') }}</td>
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
                                    <x-mary-icon name="o-chat-bubble-left-right" class="w-16 h-16 text-gray-300 mb-4" />
                                    <span class="text-xl font-bold text-gray-400">No conversations found</span>
                                    <span class="text-sm text-gray-400 mt-2">Patient chat conversations from the Salun-at app will appear here</span>
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

    {{-- Chat Modal --}}
    <x-mary-modal wire:model="chatModal" title="" class="backdrop-blur" box-class="max-w-3xl">
        @if($activeConversation)
            <div class="space-y-4">
                {{-- Conversation header --}}
                <div class="flex items-center justify-between pb-3 border-b">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $activeConversation->subject ?? 'General Inquiry' }}</h3>
                        <p class="text-sm text-gray-500">
                            {{ $activeConversation->patient?->fullname ?? 'Unknown Patient' }}
                            <span class="font-mono text-xs">({{ $activeConversation->patient?->hpercode ?? 'N/A' }})</span>
                        </p>
                    </div>
                    <div>
                        @if($activeConversation->status === 'open')
                            <span class="badge badge-success">Open</span>
                        @else
                            <span class="badge badge-ghost">Closed</span>
                        @endif
                    </div>
                </div>

                {{-- Messages --}}
                <div class="h-96 overflow-y-auto space-y-3 p-3 bg-gray-50 rounded-lg" id="chat-messages-container">
                    @forelse($messages as $msg)
                        <div class="flex {{ $msg['sender_type'] === 'staff' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[75%] rounded-2xl px-4 py-2 {{ $msg['sender_type'] === 'staff' ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-900' }}">
                                <div class="text-xs font-semibold mb-1 {{ $msg['sender_type'] === 'staff' ? 'text-blue-100' : 'text-purple-600' }}">
                                    {{ $msg['sender_name'] ?? ($msg['sender_type'] === 'staff' ? 'Staff' : 'Patient') }}
                                </div>
                                <div class="text-sm whitespace-pre-wrap break-words">{{ $msg['body'] }}</div>
                                <div class="text-xs mt-1 {{ $msg['sender_type'] === 'staff' ? 'text-blue-200' : 'text-gray-400' }}">
                                    {{ \Carbon\Carbon::parse($msg['created_at'])->format('M d, h:i A') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-400 py-8">No messages in this conversation.</div>
                    @endforelse
                </div>

                {{-- Reply box --}}
                @if($activeConversation->status === 'open')
                    <div class="flex gap-2 pt-2">
                        <x-mary-textarea wire:model="replyBody" placeholder="Type your reply..." rows="2" class="flex-1" />
                        <x-mary-button label="Send" icon="o-paper-airplane" wire:click="sendReply"
                            class="btn-primary self-end" spinner="sendReply"
                            :disabled="empty(trim($replyBody))" />
                    </div>
                @else
                    <div class="text-center text-gray-400 text-sm py-2 bg-gray-100 rounded-lg">
                        This conversation is closed.
                        <button wire:click="reopenConversation({{ $activeConversation->id }})" class="text-blue-600 underline ml-1">Reopen</button>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            @if($activeConversation && $activeConversation->status === 'open')
                <x-mary-button label="Close Conversation" wire:click="closeConversation({{ $activeConversation->id }})" class="btn-warning btn-sm" />
            @endif
            <x-mary-button label="Close" wire:click="$set('chatModal', false)" />
        </x-slot:actions>
    </x-mary-modal>
</div>
