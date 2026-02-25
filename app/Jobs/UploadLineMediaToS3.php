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
        public string $localPath,
        public string $messageId,
        public string $type
    ) {}

    public function handle()
    {
        $token = config('services.line.channel_access_token');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get("https://api-data.line.me/v2/bot/message/{$this->messageId}/content");

        if (!$response->successful()) {
            Log::error('LINE download failed', [
                'status' => $response->status()
            ]);
            return;
        }

        $content = $response->body();

        $s3Path = "line_media/{$this->messageId}.jpg";

        $result = Storage::disk('s3')->put(
            $s3Path,
            $content,
            ['visibility' => 'public']
        );

        if (!$result) {
            Log::error('S3 upload failed');
            return;
        }

        LineMessage::where('message_id', $this->messageId)
            ->update([
                'file_url' => $s3Path
            ]);

        Log::info('Upload to S3 completed', [
            'messageId' => $this->messageId
        ]);
    }
    // public function handle()
    // {
    //     if (!Storage::disk('local')->exists($this->localPath)) {
    //         Log::error('Local file missing', ['path'=>$this->localPath]);
    //         return;
    //     }

    //     $content = Storage::disk('local')->get($this->localPath);

    //     $s3Path = "line_media/" . basename($this->localPath);

    //     Storage::disk('s3')->put(
    //         $s3Path,
    //         $content,
    //         ['visibility'=>'public']
    //     );

    //     // update DB
    //     LineMessage::where('message_id', $this->messageId)
    //         ->update([
    //             'file_url' => $s3Path
    //         ]);

    //     // delete local file
    //     Storage::disk('local')->delete($this->localPath);

    //     Log::info('Upload to S3 completed', [
    //         'messageId' => $this->messageId
    //     ]);
    // }
}
