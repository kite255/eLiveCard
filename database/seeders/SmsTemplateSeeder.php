<?php

namespace Database\Seeders;

use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Default Invitation SMS',
                'sms_type' => SmsLog::TYPE_INVITATION,
                'message' => 'Dear {name}, you are invited to {event_name} on {event_date} at {venue}. Serial: {serial_number}. Guests: {guest_count}. Please confirm attendance.',
            ],
            [
                'name' => 'Default RSVP Pending Reminder',
                'sms_type' => SmsLog::TYPE_RSVP_PENDING_REMINDER,
                'message' => 'Dear {name}, reminder to confirm your attendance for {event_name}. Serial: {serial_number}. Please RSVP as soon as possible.',
            ],
            [
                'name' => 'Default One Day Before Reminder',
                'sms_type' => SmsLog::TYPE_ATTENDING_REMINDER,
                'message' => 'Dear {name}, reminder: {event_name} is tomorrow at {venue}. Serial: {serial_number}. Please come with your invitation card.',
            ],
            [
                'name' => 'Default Event Day Reminder',
                'sms_type' => SmsLog::TYPE_EVENT_DAY_REMINDER,
                'message' => 'Dear {name}, {event_name} is today at {venue}. Serial: {serial_number}. Please present your QR card at the gate.',
            ],
        ];

        foreach ($templates as $template) {
            SmsTemplate::updateOrCreate(
                [
                    'event_id' => null,
                    'sms_type' => $template['sms_type'],
                    'is_default' => true,
                ],
                [
                    'name' => $template['name'],
                    'message' => $template['message'],
                    'is_active' => true,
                ]
            );
        }
    }
}