<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use App\Models\LineMessage;
use Illuminate\Support\Facades\Storage;

class DownloadLineMedia implements ShouldQueue
{
    public function __construct(
        public string $messageId,
        public string $type,
        public array $source,
        public ?string $groupId
    ) {}

    public function handle()
    {
        $token = config('services.line.token');

        $response = Http::timeout(15)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])
            ->get("https://api-data.line.me/v2/bot/message/{$this->messageId}/content");

        if (!$response->successful()) {
            return;
        }

        $path = "temp/{$this->messageId}.{$this->type}";
        Storage::disk('local')->put($path, $response->body());

        // update status
        LineMessage::where('message_id', $this->messageId)
            ->update(['file_url' => $path]);

        // dispatch upload ต่อ
        UploadLineMediaToS3::dispatch($this->messageId)
            ->onQueue('media');
    }
}