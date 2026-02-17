<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\AnnouncementLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendLineMessageJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 30;

    protected $gid;
    protected $message;
    protected $type;
    protected $image;
    protected $announcementId;

    public function __construct($gid, $announcementId, $message, $type, $image = null)
    {
        $this->gid = $gid;
        $this->announcementId = $announcementId;
        $this->message = $message;
        $this->type = $type;
        $this->image = $image;
    }

    public function handle()
    {
        try {

            // ----------------
            // build message
            // ----------------
            if ($this->type == 2 && $this->image) {

                $url = url('storage/' . $this->image);

                $messages = [[
                    'type' => 'image',
                    'originalContentUrl' => $url,
                    'previewImageUrl' => $url
                ]];
            } else {

                $messages = [[
                    'type' => 'text',
                    'text' => $this->message
                ]];
            }

            // ----------------
            // send request
            // ----------------
            $response = Http::withToken(config('services.line.token'))
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $this->gid,
                    'messages' => $messages
                ]);

            // ----------------
            // update log result
            // ----------------
            AnnouncementLog::where([
                'announcement_id' => $this->announcementId,
                'group_id' => $this->gid
            ])->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'response' => (string)$response->body(),
                'sent_at' => now()
            ]);

            // ----------------
            // update announcement status
            // ----------------
            $this->updateAnnouncementStatus();
        } catch (\Throwable $e) {

            Log::error('LINE JOB ERROR', [
                'gid' => $this->gid,
                'error' => $e->getMessage()
            ]);

            AnnouncementLog::where([
                'announcement_id' => $this->announcementId,
                'group_id' => $this->gid
            ])->update([
                'status' => 'failed',
                'response' => $e->getMessage(),
                'sent_at' => now()
            ]);

            $this->updateAnnouncementStatus();
        }
    }

    private function updateAnnouncementStatus()
    {
        $total = AnnouncementLog::where('announcement_id', $this->announcementId)->count();

        $done = AnnouncementLog::where('announcement_id', $this->announcementId)
            ->whereIn('status', ['success', 'failed'])
            ->count();

        if ($total === $done) {

            $hasFail = AnnouncementLog::where('announcement_id', $this->announcementId)
                ->where('status', 'failed')
                ->exists();

            Announcement::where('id', $this->announcementId)
                ->update([
                    'status' => $hasFail ? 'failed' : 'sent'
                ]);
        }
    }
}
