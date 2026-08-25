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
        $tickets = Ticket::with('user')
            ->when(in_array($status, ['open', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->latest('updated_at')->paginate(30)->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'status' => $status,
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

        return back()->with('status', '已回复');
    }

    public function close(Ticket $ticket)
    {
        $ticket->update(['status' => 'closed']);

        return back()->with('status', '工单已关闭');
    }
}
