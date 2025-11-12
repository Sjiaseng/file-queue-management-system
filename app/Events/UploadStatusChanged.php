<?php

namespace App\Events;

use App\Models\Upload;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $uploadData;

    /**
     * Create a new event instance.
     */
    public function __construct(Upload $upload)
    {
        // Only pass serializable data, not the resource
        $this->uploadData = [
            'id' => $upload->id,
            'file_name' => $upload->file_name,
            'status' => $upload->status,
            'uploaded_at' => $upload->created_at->toDateTimeString(),
            'time_ago' => $upload->created_at->diffForHumans(),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel|array
    {
        return new Channel('uploads');
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'UploadStatusChanged';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'upload' => $this->uploadData,
        ];
    }
}
