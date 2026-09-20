<?php

namespace App\Services;

use App\Mail\DeliveryConfirmationCodeMail;
use App\Models\DeliveryNote;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class DeliveryConfirmationSender
{
    public function __construct(
        private readonly AfricasTalkingSmsService $sms,
    ) {
    }

    public function send(DeliveryNote $deliveryNote, string $code): void
    {
        $deliveryNote->refresh();

        if ($deliveryNote->status !== DeliveryNote::STATUS_DISPATCHED) {
            return;
        }

        if (
            blank($deliveryNote->confirmation_code_hash)
            || ! Hash::check($code, $deliveryNote->confirmation_code_hash)
        ) {
            return;
        }

        if (
            $deliveryNote->confirmation_code_expires_at === null
            || $deliveryNote->confirmation_code_expires_at->isPast()
        ) {
            return;
        }

        $errors = [];
        $sentAny = false;

        if (
            filled($deliveryNote->recipient_phone)
            && $deliveryNote->confirmation_code_sms_sent_at === null
        ) {
            try {
                $this->sms->send(
                    $deliveryNote->recipient_phone,
                    $this->smsMessage($deliveryNote, $code),
                );

                $deliveryNote->forceFill([
                    'confirmation_code_sms_sent_at' => now(),
                    'confirmation_code_last_sent_at' => now(),
                ])->save();

                $sentAny = true;
            } catch (Throwable $exception) {
                $errors[] = $exception;
            }
        }

        if (
            filled($deliveryNote->recipient_email)
            && $deliveryNote->confirmation_code_email_sent_at === null
        ) {
            try {
                Mail::to($deliveryNote->recipient_email)->send(
                    new DeliveryConfirmationCodeMail(
                        deliveryReference: $deliveryNote->reference,
                        recipientName: $deliveryNote->recipient_name,
                        code: $code,
                        expiresAt: $deliveryNote
                            ->confirmation_code_expires_at
                            ->format('Y-m-d H:i'),
                    )
                );

                $deliveryNote->forceFill([
                    'confirmation_code_email_sent_at' => now(),
                    'confirmation_code_last_sent_at' => now(),
                ])->save();

                $sentAny = true;
            } catch (Throwable $exception) {
                $errors[] = $exception;
            }
        }

        if ($errors !== []) {
            throw new RuntimeException(
                'One or more delivery confirmation channels failed.',
                previous: $errors[0],
            );
        }

        if (! $sentAny) {
            return;
        }
    }

    private function smsMessage(
        DeliveryNote $deliveryNote,
        string $code,
    ): string {
        return 'Taala Crystal delivery '
            .$deliveryNote->reference
            .' confirmation code: '
            .$code
            .'. Give this code to the driver only after receiving your delivery. '
            .'Expires '
            .$deliveryNote->confirmation_code_expires_at->format('H:i')
            .'.';
    }
}
