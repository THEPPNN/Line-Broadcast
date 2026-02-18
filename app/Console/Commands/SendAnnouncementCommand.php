<?php
// sendAnnouncementCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Announcement;
use App\Services\AnnouncementService;

class SendAnnouncementCommand extends Command
{
    protected $signature = 'announcement:send';
    protected $description = 'Send scheduled announcements';

    public function handle()
    {
        $list = Announcement::where('status','pending')
            ->where('send_at','<=',now())
            ->get();
        if ($list->isEmpty()) {
            $this->info('No announcement to send');
            return;
        }

        $service = new AnnouncementService();

        foreach ($list as $item) {
            $service->send($item->id);
            $this->info("Sent ID: ".$item->id);
        }
    }
}