<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\PaymentConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendPurchaseConfirmation implements ShouldQueue
{
    // ShouldQueue превращает listener из синхронного обработчика в queued listener - письмо отправится не во время webhook-запроса, а после выполнения worker'ом job в queue без торможения запроса вебхука
    use InteractsWithQueue;
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        Mail::to($order->user->email)->send(new PaymentConfirmation($order));
    }
}
