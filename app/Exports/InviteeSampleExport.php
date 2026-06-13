<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InviteeSampleExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name',
            'phone',
            'card_type',
            'allowed_guests',
            'category',
            'table_number',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Guest 1',
                '255700000001',
                'Single',
                1,
                'Category 1',
                'A1',
            ],
            [
                'Guest 2',
                '255700000002',
                'Double',
                2,
                'Category 2',
                'A2',
            ],
            [
                'Guest 3',
                '255700000003',
                'Family',
                4,
                'Category 3',
                'A3',
            ],
            [
                'Guest 4',
                '255700000004',
                'VIP',
                1,
                'Category 4',
                'V1',
            ],
            [
                'Guest 5',
                '255700000005',
                'Committee',
                1,
                'Category 5',
                'C1',
            ],
        ];
    }
}