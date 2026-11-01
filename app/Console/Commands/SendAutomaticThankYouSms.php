<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Services\MessageTemplateRenderer;
use App\Services\ReminderSmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendAutomaticThankYouSms extends Command
{
    protected $signature = 'sms:send-automatic-thank-you';

    protected $description = 'Send opt-in thank-you SMS once to eligible guests after an event is completed.';

    public function handle(ReminderSmsService $sms, MessageTemplateRenderer $renderer): int
    {
        Event::query()
            ->where('thank_you_mode', 'automatic')
            ->where('status', Event::STATUS_COMPLETED)
            ->whereDate('event_date', '<=', today())
            ->chunkById(50, function ($events) use ($sms, $renderer): void {
                foreach ($events as $event) {
                    $template = MessageTemplate::query()
                        ->where('channel', 'sms')
                        ->where('type', 'thank_you')
                        ->where('status', MessageTemplate::STATUS_ACTIVE)
                        ->where(function ($query) use ($event): void {
                            $query->where('event_id', $event->id)->orWhereNull('event_id');
                        })
                        ->orderByRaw('CASE WHEN event_id = ? THEN 0 ELSE 1 END', [$event->id])
                        ->first();

                    if (! $template || blank($template->content)) {
                        $this->warn("Event {$event->id}: no active SMS thank-you template; nothing sent.");
                        continue;
                    }

                    $scope = $event->auto_thank_you_scope ?: 'checked_in';
                    $invitees = Invitee::query()
                        ->where('event_id', $event->id)
                        ->where('card_status', Invitee::CARD_STATUS_ACTIVE)
                        ->whereNotNull('phone')
                        ->where('phone', '!= '');

                    if ($scope === 'checked_in') {
                        $invitees->where('checked_in_count', '>', 0);
                    } elseif ($scope === 'attending') {
                        $invitees->where('rsvp_status', Invitee::RSVP_ATTENDING);
                    } else {
                        $this->warn("Event {$event->id}: unsupported auto thank-you scope.");
                        continue;
                    }

                    $invitees->chunkById(100, function ($batch) use ($event, $template, $sms, $renderer): void {
                        foreach ($batch as $invitee) {
                            $claimed = DB::table('automatic_thank_you_sends')->insertOrIgnore([
                                'event_id' => $event->id,
                                'invitee_id' => $invitee->id,
                                'channel' => 'sms',
                                'status' => 'attempted',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            if (! $claimed) {
                                continue;
                            }

                            try {
                                $body = $renderer->render((string) $template->content, $invitee);
                                $ok = $sms->sendCustomSms($invitee, $body);

                                DB::table('automatic_thank_you_sends')
                                    ->where('event_id', $event->id)
                                    ->where('invitee_id', $invitee->id)
                                    ->where('channel', 'sms')
                                    ->update([
                                        'status' => $ok ? 'sent' : 'failed',
                                        'error' => $ok ? null : 'SMS provider did not confirm successful submission.',
                                        'updated_at' => now(),
                                    ]);
                            } catch (Throwable $exception) {
                                report($exception);
                                DB::table('automatic_thank_you_sends')
                                    ->where('event_id', $event->id)
                                    ->where('invitee_id', $invitee->id)
                                    ->where('channel', 'sms')
                                    ->update([
                                        'status' => 'failed',
                                        'error' => mb_substr($exception->getMessage(), 0, 2000),
                                        'updated_at' => now(),
                                    ]);
                            }
                        }
                    });
                }
            });

        return self::SUCCESS;
    }
}
