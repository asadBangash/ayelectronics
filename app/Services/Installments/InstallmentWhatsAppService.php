<?php

namespace App\Services\Installments;

use App\Models\Client;
use App\Models\InstallmentPlan;
use App\Models\InstallmentSetting;
use App\Models\InstallmentWhatsappMessage;
use App\Models\Sale;
use App\Support\PakistanValidators;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstallmentWhatsAppService
{
    public function buildReminderBody(Client $client, Sale $sale, InstallmentPlan $plan, array $context = []): string
    {
        $amountDue = $context['amount_due'] ?? $plan->monthly_installment;
        $dueDate = $context['due_date'] ?? $plan->next_due_date?->format('d M Y');

        return "Dear {$client->name},\n\n"
            ."This is a reminder regarding your installment payment for invoice {$sale->Ref}.\n\n"
            ."Installment amount: PKR {$amountDue}\n"
            ."Due date: {$dueDate}\n"
            ."Outstanding balance: PKR {$plan->outstanding_balance}\n\n"
            ."Please contact us if you have already made this payment.\n\nThank you.";
    }

    public function sendReminder(int $saleId, ?string $templateKey = 'manual_reminder'): InstallmentWhatsappMessage
    {
        $sale = Sale::with(['client', 'installmentPlan'])->findOrFail($saleId);
        $plan = $sale->installmentPlan;
        if (! $plan) {
            throw new \InvalidArgumentException('Sale has no installment plan.');
        }

        $settings = InstallmentSetting::current();
        $client = $sale->client;
        $to = PakistanValidators::normalizePhone($client->whatsapp ?: $client->phone);
        if (! $to || ! PakistanValidators::isValidMobile($to)) {
            throw new \InvalidArgumentException('Customer has no valid WhatsApp/mobile number.');
        }

        $body = $this->buildReminderBody($client, $sale, $plan);

        $log = InstallmentWhatsappMessage::create([
            'client_id' => $client->id,
            'sale_id' => $sale->id,
            'installment_plan_id' => $plan->id,
            'template_key' => $templateKey,
            'to_number' => $to,
            'body' => $body,
            'status' => 'queued',
            'sent_by' => Auth::id(),
        ]);

        if (! $settings->whatsapp_enabled || $settings->whatsapp_test_mode) {
            $log->update([
                'status' => 'test_logged',
                'sent_at' => now(),
                'provider_response' => ['mode' => 'test'],
            ]);

            return $log->fresh();
        }

        $token = $settings->getWhatsappAccessTokenDecrypted();
        $phoneId = $settings->whatsapp_phone_number_id;
        $url = rtrim((string) $settings->whatsapp_api_url, '/');
        if (! $token || ! $phoneId || ! $url) {
            $log->update(['status' => 'failed', 'provider_response' => ['error' => 'WhatsApp not configured']]);

            return $log->fresh();
        }

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$url}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => preg_replace('/\D/', '', $to),
                    'type' => 'text',
                    'text' => ['body' => $body],
                ]);

            $json = $response->json();
            $messageId = $json['messages'][0]['id'] ?? null;
            $log->update([
                'status' => $response->successful() ? 'accepted' : 'failed',
                'provider_message_id' => $messageId,
                'sent_at' => now(),
                'provider_response' => $json,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Installment WhatsApp send failed: '.$e->getMessage());
            $log->update(['status' => 'failed', 'provider_response' => ['error' => $e->getMessage()]]);
        }

        return $log->fresh();
    }
}
