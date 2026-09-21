<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContributionRecipientsSampleExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['name', 'phone'];
    }

    public function array(): array
    {
        return [
            ['Kitenken Lucas', '0754123456'],
        ];
    }
}
