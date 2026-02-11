<?php

namespace App\Actions\Notifications;

use App\Dto\GetSummaryNotificationsDto;
use App\Repositories\Contracts\NotificationRepositoryContract;

class GetSummaryNotificationsAction
{
    public function __construct(
        protected NotificationRepositoryContract $notificationRepository
    ) {
    }

    /**
     * Execute the action to get summary statistics.
     *
     * @return array{counts_by_status: array<string, int>, total: int, by_channel?: array<string, array<string, int>>}
     */
    public function execute(GetSummaryNotificationsDto $dto): array
    {
        return $this->notificationRepository->getSummary($dto->since, $dto->byChannel);
    }
}
