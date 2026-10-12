<?php

namespace App\Console\Commands;

use App\Enums\AccessRequestDetailStatus;
use App\Models\AccessRequestDetail;
use Illuminate\Console\Command;

class ExpireAccessRequestDetailsCommand extends Command
{
    protected $signature = 'access-requests:expire';

    protected $description = 'Expire approved access-request details whose end date has passed';

    public function handle(): int
    {
        $details = AccessRequestDetail::query()
            ->where('status', AccessRequestDetailStatus::Approved->value)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        $expiredCount = 0;

        foreach ($details as $detail) {
            $detail->forceFill([
                'status' => AccessRequestDetailStatus::Expired->value,
            ])->save();

            $expiredCount++;
        }

        $this->info("Expired {$expiredCount} access-request detail(s). ");

        return self::SUCCESS;
    }
}
