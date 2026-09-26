<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\StripeService;
use App\Services\TicketService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class TicketController extends Controller
{
    public function purchase(Request $request, Event $event, TicketService $tickets, StripeService $stripe)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email'],
            'tickets' => ['required', 'array'], 'tickets.*' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);
        try {
            $order = $tickets->createOrder($event, array_filter($data['tickets']), $data);
            if ($order->total_cents === 0) {
                $tickets->fulfill($order);
                return redirect()->route('tickets.success', ['order' => $order->reference]);
            }
            $session = $stripe->ticketCheckout($order);
            $order->update(['stripe_checkout_session_id' => $session->id]);
            return redirect()->away($session->url);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['tickets' => $exception->getMessage()])->withInput();
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['tickets' => 'Checkout could not be started.'])->withInput();
        }
    }

    public function success(Request $request, StripeService $stripe, TicketService $tickets)
    {
        $order = null;
        if ($request->filled('session_id')) {
            try {
                $session = $stripe->client()->checkout->sessions->retrieve($request->string('session_id')->toString());
                if (($session->payment_status ?? '') === 'paid' && ($session->metadata->purpose ?? '') === 'ticket_order') {
                    $order = Order::find($session->metadata->order_id);
                    if ($order) {
                        $tickets->fulfill($order, $session->payment_intent ?? null);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        } elseif ($request->filled('order')) {
            $order = Order::where('reference', $request->query('order'))->first();
        }
        return view('tickets.success', ['order' => $order?->load('tickets.ticketType', 'event')]);
    }

    public function qr(Request $request, Ticket $ticket)
    {
        abort_unless($request->user() && ($ticket->user_id === $request->user()->id || $request->user()->hasAnyRole(['super_admin', 'operations', 'check_in'])), 403);
        $token = hash_hmac('sha256', $ticket->uuid, config('app.key'));
        $checkInUrl = route('tickets.check-in', ['ticket' => $ticket->uuid, 'token' => $token]);
        $result = (new SvgWriter())->write(new QrCode($checkInUrl));
        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType(), 'Cache-Control' => 'private, no-store']);
    }

    public function checkIn(Request $request, string $ticket, TicketService $tickets)
    {
        $record = Ticket::where('uuid', $ticket)->first();
        $token = $request->string('token')->toString();
        if (!$record || !hash_equals(hash_hmac('sha256', $record->uuid, config('app.key')), $token)) {
            return response()->json(['result' => 'INVALID'], 404);
        }
        $result = $tickets->checkIn($token, $request->user()->id);
        return response()->json(['result' => $result['result'], 'ticket' => $record->uuid]);
    }
}
