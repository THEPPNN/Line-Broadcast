<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;

class ProcessLineMessage implements ShouldQueue
{
    use Queueable;

    public $tries = 5;          // retry 5 ครั้ง
    public $backoff = 10;       // เว้น 10 วิ ถ้าล้ม

    public function __construct(public array $event) {}

    public function handle()
    {
        
        $msg = $this->event['message'] ?? null;
        if (!$msg) return;

        $messageId = $msg['id'];
        $type = $msg['type'];

        // ✅ กัน duplicate จาก LINE retry
        if (LineMessage::where('message_id', $messageId)->exists()) {
            return;
        }
        
        Log::info('MESSAGE TYPE DEBUG', [
            'type' => $msg['type'] ?? null,
            'event' => $this->event
        ]);

        $filePath = null;

        // =========================
        // MEDIA DOWNLOAD (STREAM)
        // =========================

        if (in_array($type, ['image', 'video', 'audio', 'file'])) {
            Log::info('MEDIA TYPE DETECTED', [
                'type' => $type,
                'messageId' => $messageId
            ]);
            $ext = match ($type) {
                'image' => 'jpg',
                'video' => 'mp4',
                'audio' => 'm4a',
                default => 'bin'
            };

            $filePath = "line_media/{$messageId}.{$ext}";

            try {
                $response = Http::withToken(config('services.line.token'))
                    ->timeout(20)
                    ->get("https://api-data.line.me/v2/bot/message/{$messageId}/content");

                if (!$response->successful()) {
                    Log::error('LINE media fetch failed', [
                        'status' => $response->status(),
                        'messageId' => $messageId
                    ]);
                    return;
                }

                $content = $response->body();

                if (empty($content)) {
                    Log::error('LINE media empty body', [
                        'messageId' => $messageId
                    ]);
                    return;
                }

                $result = Storage::disk('s3')->put(
                    $filePath,
                    $content,
                    ['visibility' => 'public']
                );

                Log::info('S3 UPLOAD RESULT', [
                    'result' => $result,
                    'path' => $filePath
                ]);
            } catch (\Throwable $e) {
                Log::error('Media upload error: ' . $e->getMessage());
                throw $e; // ให้ retry
            }
        }

        // =========================
        // USER PROFILE (optional)
        // =========================
        $userId = $this->event['source']['userId'] ?? null;
        $groupId = $this->event['source']['groupId'] ?? null;
        $displayName = null;

        if ($userId && $groupId) {
            try {
                $profile = Http::withToken(config('services.line.token'))
                    ->timeout(10)
                    ->get("https://api.line.me/v2/bot/group/{$groupId}/member/{$userId}");

                if ($profile->successful()) {
                    $displayName = $profile->json()['displayName'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::warning('Profile fetch failed: ' . $e->getMessage());
            }
        }

        // =========================
        // SAVE DATABASE
        // =========================
        LineMessage::create([
            'message_id' => $messageId,
            'type'       => $type,
            'user_id'    => $userId,
            'group_id'   => $groupId,
            'room_id'    => $this->event['source']['roomId'] ?? null,
            'text'       => $msg['text'] ?? null,
            'file_url'   => $filePath,
            'file_type'  => $type,
            'unsent_at'  => null,
            'user_name'  => $displayName,
        ]);
    }

    public function failed(\Throwable $exception)
    {
        Log::error('ProcessLineMessage failed: ' . $exception->getMessage());
    }
}
