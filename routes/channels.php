<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('uploads', function () {
    return true; // everyone can listen
});
