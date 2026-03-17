<?php

namespace App\Livewire\Portal;

use App\Events\Portal\NewChatMessage;
use App\Models\Portal\ChatConversation;
use App\Models\Portal\ChatMessage;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ManageChatConversations extends Component
{
    use Toast, WithPagination;

    public $search = '';
    public $statusFilter = 'all';

    // Chat modal
    public $chatModal = false;
    public $activeConversation = null;
    public $messages = [];
    public $replyBody = '';

    public function getListeners()
    {
        if ($this->activeConversation) {
            return [
                "echo:chat.conversation.{$this->activeConversation->id},.new.message" => 'onNewMessage',
            ];
        }

        return [];
    }

    public function onNewMessage($event)
    {
        $this->loadMessages();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function openChat($conversationId)
    {
        $this->activeConversation = ChatConversation::with('patient')->find($conversationId);
        $this->loadMessages();

        // Mark patient messages as read
        ChatMessage::where('conversation_id', $conversationId)
            ->where('sender_type', 'patient')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->chatModal = true;
    }

    public function loadMessages()
    {
        if ($this->activeConversation) {
            $this->messages = $this->activeConversation->messages()
                ->orderBy('created_at', 'asc')
                ->get()
                ->toArray();
        }
    }

    public function sendReply()
    {
        if (empty(trim($this->replyBody)) || !$this->activeConversation) {
            return;
        }

        if ($this->activeConversation->status !== 'open') {
            $this->error('This conversation has been closed.');
            return;
        }

        $staffName = auth()->user()->name ?? 'Staff';

        $message = ChatMessage::create([
            'conversation_id' => $this->activeConversation->id,
            'sender_type' => 'staff',
            'sender_id' => auth()->id(),
            'sender_name' => $staffName,
            'body' => trim($this->replyBody),
        ]);

        $this->activeConversation->update(['last_message_at' => now()]);

        broadcast(new NewChatMessage($message));

        $this->replyBody = '';
        $this->loadMessages();
        $this->success('Reply sent.');
    }

    public function closeConversation($conversationId)
    {
        $conversation = ChatConversation::find($conversationId);
        if ($conversation) {
            $conversation->update(['status' => 'closed']);
            if ($this->activeConversation && $this->activeConversation->id === $conversationId) {
                $this->activeConversation->status = 'closed';
            }
            $this->success('Conversation closed.');
        }
    }

    public function reopenConversation($conversationId)
    {
        $conversation = ChatConversation::find($conversationId);
        if ($conversation) {
            $conversation->update(['status' => 'open']);
            if ($this->activeConversation && $this->activeConversation->id === $conversationId) {
                $this->activeConversation->status = 'open';
            }
            $this->success('Conversation reopened.');
        }
    }

    public function render()
    {
        $conversations = ChatConversation::with(['patient', 'latestMessage'])
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('subject', 'LIKE', "%{$this->search}%")
                        ->orWhereHas('patient', function ($pq) {
                            $pq->where('patlast', 'LIKE', "%{$this->search}%")
                                ->orWhere('patfirst', 'LIKE', "%{$this->search}%");
                        });
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(15);

        $openCount = ChatConversation::where('status', 'open')->count();
        $unreadCount = ChatMessage::where('sender_type', 'patient')
            ->whereNull('read_at')
            ->count();

        return view('livewire.portal.manage-chat-conversations', [
            'conversations' => $conversations,
            'openCount' => $openCount,
            'unreadCount' => $unreadCount,
        ]);
    }
}
