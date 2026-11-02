<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('message_templates')
            ->whereNull('event_id')
            ->where('channel', 'whatsapp')
            ->where('type', 'invitation')
            ->where('whatsapp_template_name', 'elive_social_invitation_sw')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('message_templates')->insert([
            'event_id' => null,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'name' => 'eLive Social Invitation Swahili',
            'content' => 'Habari #NAME#, unaalikwa kwenye #EVENT_NAME#. Aina ya kadi: #CARD_TYPE#. Mahali: #VENUE#. Muda: #EVENT_TIME#.',
            'whatsapp_template_name' => 'elive_social_invitation_sw',
            'whatsapp_language_code' => 'sw',
            'whatsapp_buttons' => json_encode([
                'Nitahudhuria' => 'rsvp_attending',
                'Sitahudhuria' => 'rsvp_not_attending',
                'RAMANI' => '#LOCATION_LINK#',
            ]),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep invitation configuration and existing event references intact on rollback.
    }
};
