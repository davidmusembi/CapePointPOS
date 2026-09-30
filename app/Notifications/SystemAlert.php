<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Generic in-app alert stored in the notifications table and shown in the navbar bell.
 */
class SystemAlert extends Notification
{
    /**
     * @param  string  $key  de-duplication key, e.g. "low_stock:12"
     * @param  string  $type  low_stock | out_of_stock
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $title,
        public string $message,
        public ?string $url = null,
        public ?string $context = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'context' => $this->context,
        ];
    }
}
