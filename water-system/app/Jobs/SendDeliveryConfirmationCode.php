<?php

namespace App\Jobs;

use App\Models\DeliveryNote;
use App\Services\DeliveryConfirmationSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeliveryConfirmationCode implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(
        public int $deliveryNoteId,
        public string $code,
    ) {
        $this->afterCommit = true;
    }

    public function handle(DeliveryConfirmationSender $sender): void
    {
        $deliveryNote = DeliveryNote::find($this->deliveryNoteId);

        if (! $deliveryNote) {
            return;
        }

        $sender->send($deliveryNote, $this->code);
    }
}
