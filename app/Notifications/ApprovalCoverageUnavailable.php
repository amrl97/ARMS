<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApprovalCoverageUnavailable extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{request_id: int, detail_id: int, module_name: string, step: string, available: int, required: int}>  $issues
     */
    public function __construct(
        private readonly array $issues,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Action required: access request approval coverage')
            ->greeting('Approval coverage needs attention')
            ->line('Some pending access-request lines no longer have enough eligible approvers after a user was deactivated.')
            ->line('Review the current module approver rules or active Admin assignments and restore the required approval coverage.');

        foreach ($this->issues as $issue) {
            $mail->line(sprintf(
                'Request #%d, line #%d — %s (%s step): %d of %d required approver(s) available.',
                $issue['request_id'],
                $issue['detail_id'],
                $issue['module_name'],
                $issue['step'],
                $issue['available'],
                $issue['required'],
            ));
        }

        return $mail->line('The affected request lines remain pending. Approval assignments will be resolved again after the configuration is corrected.');
    }
}
