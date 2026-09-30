<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, int $id) {
    return (int) $user->id === $id;
});

Broadcast::channel('list.{id}', function ($user, int $id) {
    return $user->hasAccessToList($id);
});

Broadcast::channel('job-monitoring', function ($user) {
    try {
        return $user->hasPermissionTo('view job monitoring dashboard');
    } catch (Throwable) {
        return false;
    }
});
