<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Invitee;
use App\Support\WhatsAppPhone;

/**
 * Shared contact detection is event-scoped and never merges invitee identities,
 * QR codes, RSVP responses, or invitation delivery records.
 */
class SharedInviteePhoneService
{
    public function normalize(?string $phone): ?string
    {
        return WhatsAppPhone::normalize($phone);
    }

    /** @return array<string, array<int, array{id: int, name: string}>> */
    public function groups(Event $event): array
    {
        // Cache within one HTTP request only, not between requests. This means
        // newly imported or edited contacts are visible on the next request.
        $key = 'elive_shared_phone_groups_'.$event->getKey();
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $groups = [];
        Invitee::query()->where('event_id', $event->getKey())
            ->whereNotNull('phone')->select(['id', 'name', 'phone'])
            ->chunkById(500, function ($invitees) use (&$groups): void {
                foreach ($invitees as $invitee) {
                    $phone = $this->normalize($invitee->phone);
                    if ($phone !== null) {
                        $groups[$phone][] = [
                            'id' => (int) $invitee->id,
                            'name' => (string) $invitee->name,
                        ];
                    }
                }
            });

        $groups = array_filter($groups, static fn (array $members): bool => count($members) > 1);
        request()->attributes->set($key, $groups);

        return $groups;
    }

    /**
     * Filter event-scoped shared-contact groups for review and export.
     * Search supports names and telephone formatting with or without punctuation.
     *
     * @param array<string, array<int, array{id: int, name: string}>> $groups
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    public function filterGroups(array $groups, string $search = '', int $minimumInvitees = 2, string $sort = 'count_desc'): array
    {
        $search = mb_strtolower(trim($search));
        $digits = preg_replace('/\\D+/u', '', $search) ?? '';
        $minimumInvitees = max(2, $minimumInvitees);

        $filtered = array_filter($groups, static function (array $members, string $phone) use ($search, $digits, $minimumInvitees): bool {
            if (count($members) < $minimumInvitees) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $phoneDigits = preg_replace('/\\D+/u', '', $phone) ?? '';
            if (str_contains(mb_strtolower($phone), $search) || ($digits !== '' && str_contains($phoneDigits, $digits))) {
                return true;
            }

            foreach ($members as $member) {
                if (str_contains(mb_strtolower((string) $member['name']), $search)) {
                    return true;
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);

        uksort($filtered, static function (string $a, string $b) use ($filtered, $sort): int {
            return match ($sort) {
                'phone_asc' => strcmp($a, $b),
                'phone_desc' => strcmp($b, $a),
                'count_asc' => count($filtered[$a]) <=> count($filtered[$b]) ?: strcmp($a, $b),
                default => count($filtered[$b]) <=> count($filtered[$a]) ?: strcmp($a, $b),
            };
        });

        return $filtered;
    }

    /** @return array<int, array{id: int, name: string}> */
    public function others(Invitee $invitee): array
    {
        $phone = $this->normalize($invitee->phone);
        if ($phone === null || ! $invitee->event_id) {
            return [];
        }

        $event = $invitee->event ?: Event::find($invitee->event_id);
        if (! $event) {
            return [];
        }

        return array_values(array_filter(
            $this->groups($event)[$phone] ?? [],
            static fn (array $member): bool => $member['id'] !== (int) $invitee->id,
        ));
    }

    /** @return array{shared_numbers: int, shared_invitees: int, examples: string} */
    public function summary(Event $event): array
    {
        $groups = $this->groups($event);

        return [
            'shared_numbers' => count($groups),
            'shared_invitees' => array_sum(array_map('count', $groups)),
            'examples' => collect($groups)->take(3)
                ->map(fn (array $members): string => implode(', ', array_column($members, 'name')))
                ->implode(' | '),
        ];
    }
}
