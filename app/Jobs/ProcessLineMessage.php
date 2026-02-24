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

    public $tries = 5;
    public $backoff = 10;

    public function __construct(public array $event) {}

    public function handle()
    {
        $msg = $this->event['message'] ?? null;
        if (!$msg) return;

        $messageId = $msg['id'] ?? null;
        $type = $msg['type'] ?? null;

        if (!$messageId || !$type) return;

        // กัน duplicate
        if (LineMessage::where('message_id', $messageId)->exists()) {
            return;
        }

        Log::info('PROCESS LINE MESSAGE', [
            'messageId' => $messageId,
            'type' => $type,
        ]);

        $filePath = null;

        /*
        |--------------------------------------------------------------------------
        | MEDIA DOWNLOAD
        |--------------------------------------------------------------------------
        */

        if (in_array($type, ['image', 'video', 'audio', 'file'])) {

            $ext = match ($type) {
                'image' => 'jpg',
                'video' => 'mp4',
                'audio' => 'm4a',
                default => 'bin'
            };

            $filePath = "line_media/{$messageId}.{$ext}";

            try {

                $token = config('services.line.token');

                if (!$token) {
                    throw new \Exception('LINE token is missing');
                }

                $response = Http::withToken($token)
                    ->timeout(30)
                    ->get("https://api-data.line.me/v2/bot/message/{$messageId}/content");

                if (!$response->successful()) {
                    throw new \Exception("LINE fetch failed: " . $response->status());
                }

                $content = $response->body();

                if (empty($content)) {
                    throw new \Exception("LINE returned empty body");
                }

                Storage::disk('s3')->put(
                    $filePath,
                    $content,
                    ['visibility' => 'public']
                );

                Log::info('S3 upload success', [
                    'path' => $filePath
                ]);

            } catch (\Throwable $e) {

                Log::error('MEDIA PROCESS FAILED', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                ]);

                // ❗ สำคัญ: ไม่ throw
                $filePath = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | USER PROFILE (optional)
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | SAVE DATABASE (Always Save)
        |--------------------------------------------------------------------------
        */

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

        Log::info('DB saved', [
            'messageId' => $messageId
        ]);
    }

    public function failed(\Throwable $exception)
    {
        Log::error('ProcessLineMessage job failed totally', [
            'error' => $exception->getMessage()
        ]);
    }
}