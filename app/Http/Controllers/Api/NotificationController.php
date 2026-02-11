<?php

namespace App\Http\Controllers\Api;

use App\Contracts\NotificationServiceContract;
use App\Dto\CreateNotificationDto;
use App\Http\Controllers\BaseController;
use App\Http\Requests\StoreNotificationRequest;
use Illuminate\Http\JsonResponse;

class NotificationController extends BaseController
{
    /**
     * Handle the incoming request to create and queue a notification.
     *
     * Validates the request, builds the DTO, delegates to the service,
     * and returns a 202 Accepted JSON response.
     */
    public function store(StoreNotificationRequest $request, NotificationServiceContract $service): JsonResponse
    {
        $dto = CreateNotificationDto::fromRequest($request);
        $response = $service->createAndQueue($dto);

        return response()->json($response->toArray(), 202);
    }
}

