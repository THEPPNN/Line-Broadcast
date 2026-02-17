<?php

namespace App\Jobs;

use App\Models\Announcement;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAnnouncementJob implements ShouldQueue
{
    public $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function handle()
    {
        // TODO ยิง API LINE

        // update status
        $this->announcement->update([
            'status'=>'sent'
        ]);
    }
}