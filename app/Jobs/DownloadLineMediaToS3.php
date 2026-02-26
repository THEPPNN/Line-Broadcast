<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;
use App\Jobs\UploadLineMediaToS3;

class DownloadLineMediaToS3 implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public string $messageId,
        public string $type,
        public ?string $groupId,
    ) {}

    public function handle(): void
    {
        $url = "https://api-data.line.me/v2/bot/message/{$this->messageId}/content";

        $response = Http::withToken(config('services.line.token'))
            ->withOptions(['stream' => true])
            ->timeout(10)
            ->get($url);

        if (!$response->successful()) {
            return;
        }

        $ext = match ($this->type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'm4a',
            default => 'bin',
        };

        $path = "line_media/{$this->groupId}/{$this->messageId}.{$ext}";

        Storage::disk('s3')->put(
            $path,
            $response->toPsrResponse()->getBody(),
            [
                'visibility' => 'public',
                'CacheControl' => 'max-age=31536000'
            ]
        );

        LineMessage::where('message_id', $this->messageId)
            ->update(['file_url' => $path]);
    }
}