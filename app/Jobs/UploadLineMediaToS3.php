<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;
use Illuminate\Support\Facades\Http;

class UploadLineMediaToS3 implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $messageId,
        public string $type
    ) {}

    public function handle()
    {
        $token = config('services.line.token');

        $response = Http::withToken($token)
            ->timeout(20)
            ->get("https://api-data.line.me/v2/bot/message/{$this->messageId}/content");

        Log::info('LINE RESPONSE', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'messageId' => $this->messageId
        ]);

        if (!$response->successful()) {
            Log::error('LINE DOWNLOAD FAILED', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return;
        }

        $ext = match ($this->type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'm4a',
            default => 'bin'
        };

        $s3Path = "line_media/{$this->messageId}.{$ext}";

        $result = Storage::disk('s3')->put(
            $s3Path,
            $response->body(),
            ['visibility' => 'public']
        );

        if (!$result) {
            Log::error('S3 UPLOAD FAILED', [
                'messageId' => $this->messageId
            ]);
            return;
        }

        LineMessage::where('message_id', $this->messageId)
            ->update([
                'file_url' => $s3Path
            ]);

        Log::info('UPLOAD SUCCESS', [
            'messageId' => $this->messageId
        ]);
    }
}
