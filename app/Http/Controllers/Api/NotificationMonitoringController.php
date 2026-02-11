<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notifications\GetRecentNotificationsAction;
use App\Actions\Notifications\GetSummaryNotificationsAction;
use App\Dto\GetRecentNotificationsDto;
use App\Dto\GetSummaryNotificationsDto;
use App\Http\Requests\GetRecentNotificationsRequest;
use App\Http\Requests\GetSummaryNotificationsRequest;
use App\Http\Resources\NotificationCollection;
use App\Http\Resources\SummaryResource;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\BaseController;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Response;

class NotificationMonitoringController extends BaseController
{
    public function recent(
        GetRecentNotificationsRequest $request,
        GetRecentNotificationsAction $action
    ): JsonResponse {
        $dto = GetRecentNotificationsDto::fromRequest($request);
        $paginator = $action->execute($dto);

        return $this->successResponse(
            payload: NotificationResource::collection($paginator),
            responseCode: Response::HTTP_OK
        );
    }

    public function summary(
        GetSummaryNotificationsRequest $request,
        GetSummaryNotificationsAction $action
    ): JsonResponse {
        $dto = GetSummaryNotificationsDto::fromRequest($request);
        $result = $action->execute($dto);

        return $this->successResponse(
            payload: new SummaryResource($result),
            responseCode: Response::HTTP_OK
        );
    }
}
