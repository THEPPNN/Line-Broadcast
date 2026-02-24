<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;
use App\Models\LineUnsend;

class ProcessLineUnsend implements ShouldQueue
{
    use Queueable;

    public $tries = 5;
    public $timeout = 20;

    public function __construct(public array $event) {}

    public function handle(): void
    {
        $mid = $this->event['unsend']['messageId'] ?? null;

        if (!$mid) {
            return;
        }

        $message = LineMessage::where('message_id', $mid)->first();

        // 🔁 ถ้า message ยังไม่เข้า DB → retry
        if (!$message) {
            $this->release(5);
            return;
        }

        // ✅ กัน unsend ซ้ำ
        if ($message->is_unsent) {
            return;
        }

        try {
            // ✅ บันทึก log unsend แบบ idempotent
            LineUnsend::updateOrCreate(
                ['message_id' => $mid],
                [
                    'user_id'   => $this->event['source']['userId'] ?? null,
                    'group_id'  => $this->event['source']['groupId'] ?? null,
                    'unsent_at' => now(),
                ]
            );

            // ✅ update message
            $message->update([
                'is_unsent' => true,
                'unsent_at' => now()
            ]);

        } catch (\Throwable $e) {
            Log::error('Unsend DB Error: '.$e->getMessage());
            throw $e; // ให้ queue retry
        }

        // -------------------- PUSH แจ้งเตือน --------------------

        $userName = $message->user_name ?? 'Unknown';

        $messageText =
            "📢 ข้อความถูกยกเลิก\n\n" .
            "ผู้ส่ง: @{$userName}\n" .
            "เวลา: {$message->created_at}\n" .
            "ประเภท: " . match ($message->type) {
                'image' => 'รูปภาพ',
                'video' => 'วิดีโอ',
                'audio' => 'เสียง',
                default => 'ข้อความ'
            } . "\n\n" .
            ($message->text ? "ข้อความ:\n{$message->text}" : '');

        $pushMessages = [[
            'type' => 'text',
            'text' => $messageText
        ]];

        // ถ้าเป็นรูป → ส่งแนบไปด้วย
        if ($message->type === "image" && $message->file_url) {
            $url = config('filesystems.disks.s3.url') . '/' . $message->file_url;

            $pushMessages[] = [
                'type' => 'image',
                'originalContentUrl' => $url,
                'previewImageUrl' => $url
            ];
        }

        try {
            $response = Http::withToken(config('services.line.token'))
                ->timeout(10)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $message->group_id,
                    'messages' => $pushMessages
                ]);

            if (!$response->successful()) {
                Log::error('LINE Push Error', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('LINE Push Exception: '.$e->getMessage());
            throw $e; // retry ได้
        }
    }
}