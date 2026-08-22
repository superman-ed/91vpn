<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index()
    {
        return view('user.tickets.index', [
            'tickets' => auth()->user()->tickets()->latest('updated_at')->get(),
        ]);
    }

    public function create()
    {
        return view('user.tickets.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $ticket = DB::transaction(function () use ($data) {
            $ticket = Ticket::create([
                'user_id' => auth()->id(),
                'subject' => $data['subject'],
                'status' => 'open',
                'last_reply_at' => now(),
            ]);
            $ticket->replies()->create([
                'user_id' => auth()->id(),
                'is_admin' => false,
                'content' => $data['content'],
            ]);
            return $ticket;
        });

        return redirect("/user/ticket/{$ticket->id}")->with('status', '工单已提交');
    }

    public function show(Ticket $ticket)
    {
        abort_unless($ticket->user_id === auth()->id(), 403);

        return view('user.tickets.show', [
            'ticket' => $ticket->load('replies.user'),
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->user_id === auth()->id(), 403);
        $data = $request->validate(['content' => ['required', 'string']]);

        $ticket->replies()->create(['user_id' => auth()->id(), 'is_admin' => false, 'content' => $data['content']]);
        $ticket->update(['status' => 'open', 'last_reply_at' => now()]);

        return back()->with('status', '已回复');
    }
}
