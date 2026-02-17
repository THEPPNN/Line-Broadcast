<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AnnouncementService;

class AnnouncementController extends Controller
{
    public function store(Request $request, AnnouncementService $service)
    {
        $data = $request->validate([
            'title' => 'nullable|string',
            'message' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'types' => 'required|in:1,2',
            'send_at' => 'required|date',
            'send_at_time' => 'required|date_format:H:i'
        ]);
        $data['send_at'] = $data['send_at'].' '.$data['send_at_time'];
        $response = $service->create($data);

        return $response;
    }
    public function cancel($id, AnnouncementService $service)
    {
        $response = $service->cancel($id);
        return $response;
    }
    public function send($id, AnnouncementService $service)
    {
        $response = $service->send($id);
        return $response;
    }
}