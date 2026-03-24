<?php

namespace App\Jobs;

use App\Services\OrderConfirmationFlowService;
use App\Services\MetaWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppButtonReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $from,
        public ?string $buttonId,
        public ?string $buttonTitle,
        public ?string $contextId,
        public ?string $phoneNumberId
    ) {}

    public function handle(): void
    {
        Log::info('ProcessWhatsAppButtonReplyJob: Starting', [
            'from' => $this->from,
            'button_id' => $this->buttonId,
            'button_title' => $this->buttonTitle,
        ]);

        try {
            $whatsappService = new MetaWhatsAppService($this->phoneNumberId);
            if (!$whatsappService->isConfigured()) {
                Log::error('ProcessWhatsAppButtonReplyJob: WhatsApp not configured', [
                    'phone_number_id' => $this->phoneNumberId,
                ]);
                return;
            }

            $flowService = new OrderConfirmationFlowService($whatsappService);
            $handled = $flowService->handleButtonReply(
                $this->from,
                $this->buttonId,
                $this->contextId,
                $this->phoneNumberId,
                $this->buttonTitle
            );

            Log::info('ProcessWhatsAppButtonReplyJob: Completed', ['handled' => $handled]);
        } catch (\Throwable $e) {
            Log::error('ProcessWhatsAppButtonReplyJob: Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
