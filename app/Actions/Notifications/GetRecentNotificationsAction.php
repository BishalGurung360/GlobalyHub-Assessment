<?php

namespace App\Actions\Notifications;

use App\Dto\GetRecentNotificationsDto;
use App\Repositories\Contracts\NotificationRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetRecentNotificationsAction
{
    public function __construct(
        protected NotificationRepositoryContract $notificationRepository
    ) {
    }

    /**
     * Execute the action to get recent notifications.
     *
     * @return LengthAwarePaginator
     */
    public function execute(GetRecentNotificationsDto $dto): LengthAwarePaginator
    {
        $filters = array_filter([
            'user_id' => $dto->userId,
            'channel' => $dto->channel,
            'status' => $dto->status,
            'tenant_id' => $dto->tenantId,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->notificationRepository->getRecent($dto->limit, $dto->page, $filters);
    }
}
