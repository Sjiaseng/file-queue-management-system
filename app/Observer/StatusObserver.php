<?php
namespace App\Observer;

use App\Models\Upload;
use App\Events\UploadStatusChanged;

class StatusObserver
{
    public function updated(Upload $upload)
    {
        if ($upload->wasChanged('status')) {
            broadcast(new UploadStatusChanged($upload));
        }
    }
}
