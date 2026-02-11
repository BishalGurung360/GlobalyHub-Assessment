<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Dto\CreateNotificationDto;
use App\Http\Controllers\BaseController;
use App\Http\Resources\NotificationResource;
use App\Contracts\NotificationServiceContract;
use App\Http\Requests\StoreNotificationRequest;

class NotificationController extends BaseController
{
    public function notify(StoreNotificationRequest $request, NotificationServiceContract $service): JsonResponse
    {
        $dto = CreateNotificationDto::fromRequest($request);
        $notification = $service->createAndQueue($dto);

        // I'm returning a success response with the response code 202 Accepted
        // This is because the notification is queued and will be processed in the background
        // Meaning that the request was successful but the work is still in progress
        return $this->successResponse(
            payload: NotificationResource::make($notification),
            responseCode: Response::HTTP_ACCEPTED
        );
    }
}

