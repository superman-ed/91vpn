<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index()
    {
        return view('admin.tickets.index', [
            'tickets' => Ticket::with('user')->latest('updated_at')->paginate(30),
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
