<?php

namespace Spatie\UptimeMonitor\Notifications\Notifications;

use Carbon\Carbon;
use Spatie\UptimeMonitor\Notifications\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Spatie\UptimeMonitor\Models\Enums\UptimeStatus;
use Illuminate\Notifications\Messages\SlackAttachment;
use Spatie\UptimeMonitor\Notifications\BaseNotification;
use Spatie\UptimeMonitor\Events\UptimeCheckFailed as MonitorFailedEvent;

class UptimeCheckFailed extends BaseNotification
{
    /** @var \Spatie\UptimeMonitor\Events\UptimeCheckFailed */
    public $event;

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
    	$notifiable->set_email( $this->getEmail() );

        $mailMessage = (new MailMessage)
            ->error()
	        ->to( $this->getEmail() )
            ->subject($this->getMessageText())
            ->line($this->getMessageText())
            ->line($this->getLocationDescription());

        foreach ($this->getMonitorProperties() as $name => $value) {
            $mailMessage->line($name.': '.$value);
        }

	    $mailMessage->view('emails_uptime_down');
        return $mailMessage;
    }

    public function toSlack($notifiable)
    {
        return (new SlackMessage)
            ->error()
            ->attachment(function (SlackAttachment $attachment) {
                $attachment
                    ->title($this->getMessageText())
                    ->content($this->getMonitor()->uptime_check_failure_reason)
                    ->fallback($this->getMessageText())
                    ->footer($this->getLocationDescription())
                    ->timestamp(Carbon::now());
            });
    }

    public function getMonitorProperties($extraProperties = []): array
    {
        $since = "Since {$this->event->downtimePeriod->startDateTime->format('H:i')}";
        $date = $this->event->monitor->formattedLastUpdatedStatusChangeDate();

        $extraProperties = [
            $since => $date,
            'Failure reason' => $this->event->monitor->uptime_check_failure_reason,
        ];

        return parent::getMonitorProperties($extraProperties);
    }

    public function isStillRelevant(): bool
    {
        return $this->event->monitor->uptime_status == UptimeStatus::DOWN;
    }

    public function setEvent(MonitorFailedEvent $event)
    {
        $this->event = $event;

        return $this;
    }

	protected function getEmail(): string
	{
		return $this->event->monitor->email;
	}

    protected function getMessageText(): string
    {
        return "{$this->event->monitor->url} seems down";
    }
}
