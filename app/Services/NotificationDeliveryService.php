<?php

namespace App\Services;

use App\Exceptions\NotificationChannelNotFound;
use App\Factories\NotificationChannelFactory;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Handles the actual delivery of notifications through channels.
 */
class NotificationDeliveryService
{
    /**
     * Deliver a notification through its configured channel.
     *
     * @throws \Throwable
     */
    public function deliver(Notification $notification): void
    {
        $notification->load('user');
        
        Log::info('NotificationDeliveryService: performing delivery', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
        ]);

        try {
            /**
             * We are using factory pattern to create the channel instance
             * This is to avoid the tight coupling between the NotificationDeliveryService and the channel classes
             * If we need to add a new channel, we can simply add a new class and register it in the factory
             * And the NotificationDeliveryService will automatically use the new channel
             */
            $channel = NotificationChannelFactory::make($notification->channel);
            $channel->send($notification);
        } catch (NotificationChannelNotFound $e) {
            Log::error('NotificationDeliveryService: channel not found', [
                'error' => $e->getMessage(),
                'channel' => $notification->channel,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('NotificationDeliveryService: delivery failed', [
                'error' => $e->getMessage(),
                'notification_id' => $notification->id,
            ]);
            throw $e;
        }
    }
}
