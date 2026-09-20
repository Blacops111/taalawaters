<?php

namespace Tests\Feature;

use App\Mail\DeliveryConfirmationCodeMail;
use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AfricasTalkingSmsService;
use App\Services\DeliveryConfirmationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryConfirmationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_africas_talking_sms_service_sends_to_sandbox_with_normalized_kenyan_number(): void
    {
        config([
            'services.africastalking.environment' => 'sandbox',
            'services.africastalking.username' => 'sandbox',
            'services.africastalking.api_key' => 'test-key',
            'services.africastalking.sender_id' => 'TAALA',
        ]);

        Http::fake([
            'https://api.sandbox.africastalking.com/version1/messaging' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [[
                        'statusCode' => 101,
                        'number' => '+254712345678',
                        'status' => 'Success',
                    ]],
                ],
            ], 201),
        ]);

        app(AfricasTalkingSmsService::class)->send(
            '0712 345 678',
            'Test delivery code',
        );

        Http::assertSent(function (Request $request) {
            return $request->url()
                === 'https://api.sandbox.africastalking.com/version1/messaging'
                && $request->hasHeader('apiKey', 'test-key')
                && $request['username'] === 'sandbox'
                && $request['to'] === '+254712345678'
                && $request['message'] === 'Test delivery code'
                && $request['from'] === 'TAALA';
        });
    }

    public function test_confirmation_sender_sends_same_code_to_sms_and_email_and_records_both_channels(): void
    {
        config([
            'services.africastalking.environment' => 'sandbox',
            'services.africastalking.username' => 'sandbox',
            'services.africastalking.api_key' => 'test-key',
            'services.africastalking.sender_id' => null,
        ]);

        Http::fake([
            'https://api.sandbox.africastalking.com/version1/messaging' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [[
                        'statusCode' => 102,
                        'number' => '+254712345678',
                        'status' => 'Queued',
                    ]],
                ],
            ], 201),
        ]);

        Mail::fake();

        $deliveryNote = $this->dispatchedDeliveryNote();
        $code = '482731';

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make($code),
            'confirmation_code_generated_at' => now(),
            'confirmation_code_expires_at' => now()->addHour(),
        ])->save();

        app(DeliveryConfirmationSender::class)->send(
            $deliveryNote,
            $code,
        );

        Mail::assertSent(
            DeliveryConfirmationCodeMail::class,
            fn (DeliveryConfirmationCodeMail $mail) =>
                $mail->hasTo('receiver@example.com')
                && $mail->code === $code
                && $mail->deliveryReference === $deliveryNote->reference
        );

        Http::assertSentCount(1);

        $deliveryNote->refresh();

        $this->assertNotNull($deliveryNote->confirmation_code_sms_sent_at);
        $this->assertNotNull($deliveryNote->confirmation_code_email_sent_at);
        $this->assertNotNull($deliveryNote->confirmation_code_last_sent_at);
    }

    public function test_email_only_recipient_skips_sms_channel(): void
    {
        Mail::fake();
        Http::fake();

        $deliveryNote = $this->dispatchedDeliveryNote();
        $code = '593104';

        $deliveryNote->update([
            'recipient_phone' => null,
        ]);

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make($code),
            'confirmation_code_generated_at' => now(),
            'confirmation_code_expires_at' => now()->addHour(),
        ])->save();

        app(DeliveryConfirmationSender::class)->send(
            $deliveryNote,
            $code,
        );

        Http::assertNothingSent();
        Mail::assertSent(
            DeliveryConfirmationCodeMail::class,
            fn (DeliveryConfirmationCodeMail $mail) =>
                $mail->hasTo('receiver@example.com')
        );

        $deliveryNote->refresh();

        $this->assertNull($deliveryNote->confirmation_code_sms_sent_at);
        $this->assertNotNull($deliveryNote->confirmation_code_email_sent_at);
    }

    public function test_stale_confirmation_code_is_not_sent(): void
    {
        Mail::fake();
        Http::fake();

        $deliveryNote = $this->dispatchedDeliveryNote();

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make('111222'),
            'confirmation_code_generated_at' => now(),
            'confirmation_code_expires_at' => now()->addHour(),
        ])->save();

        app(DeliveryConfirmationSender::class)->send(
            $deliveryNote,
            '999888',
        );

        Http::assertNothingSent();
        Mail::assertNothingSent();

        $deliveryNote->refresh();

        $this->assertNull($deliveryNote->confirmation_code_last_sent_at);
    }

    private function dispatchedDeliveryNote(): DeliveryNote
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $sale = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-NOTIFY-'.uniqid(),
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => now(),
            'total_amount' => 1000,
            'created_by' => $admin->id,
        ]);

        return DeliveryNote::create([
            'reference' => 'DN-NOTIFY-'.uniqid(),
            'sales_order_id' => $sale->id,
            'status' => DeliveryNote::STATUS_DISPATCHED,
            'recipient_name' => 'Delivery Receiver',
            'recipient_phone' => '+254712345678',
            'recipient_email' => 'receiver@example.com',
            'dispatched_at' => now(),
            'created_by' => $admin->id,
        ]);
    }
}
