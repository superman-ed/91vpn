<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = $request->query('q');
        $tickets = Ticket::with('user')
            ->when(in_array($status, ['open', 'closed'], true), fn ($w) => $w->where('status', $status))
            ->when($q, fn ($w) => $w->where(fn ($s) => $s->where('subject', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%"))))
            ->latest('updated_at')->paginate(30)->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'status' => $status,
            'q' => $q,
            'counts' => [
                'all' => Ticket::count(),
                'open' => Ticket::where('status', 'open')->count(),
                'closed' => Ticket::where('status', 'closed')->count(),
            ],
        ]);
    }

    public function show(Ticket $ticket)
    {
        return view('admin.tickets.show', ['ticket' => $ticket->load('replies.user', 'user')]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['content' => ['required', 'string']]);
        $ticket->replies()->create(['user_id' => auth()->id(), 'is_admin' => true, 'content' => $data['content']]);
        $ticket->update(['last_reply_at' => now()]);
        audit('ticket.reply', "回复工单 #{$ticket->id}「".\Illuminate\Support\Str::limit($ticket->subject, 24)."」", $ticket);

        return back()->with('status', '已回复');
    }

    public function close(Ticket $ticket)
    {
        $ticket->update(['status' => 'closed']);
        audit('ticket.close', "关闭工单 #{$ticket->id}", $ticket);

        return back()->with('status', '工单已关闭');
    }

    public function reopen(Ticket $ticket)
    {
        $ticket->update(['status' => 'open']);
        audit('ticket.reopen', "重开工单 #{$ticket->id}", $ticket);

        return back()->with('status', '工单已重开');
    }
}
