<?php

namespace Tests\Unit;

use App\Services\SmsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SmsPhoneNormalizationTest extends TestCase
{
    #[DataProvider('validPhones')]
    public function test_it_normalizes_tanzanian_and_international_numbers(string $input, string $expected): void
    {
        self::assertSame($expected, (new SmsService())->formatPhone($input));
    }

    public static function validPhones(): array
    {
        return [
            'tz local leading zero' => ['0712345678', '255712345678'],
            'tz local without zero' => ['712345678', '255712345678'],
            'tz country code' => ['+255712345678', '255712345678'],
            'tz international dialing prefix' => ['00255712345678', '255712345678'],
            'tz optional trunk zero' => ['+2550712345678', '255712345678'],
            'kenya country code' => ['+254714294113', '254714294113'],
            'kenya digits only' => ['254701648285', '254701648285'],
            'zambia country code' => ['+260975029316', '260975029316'],
            'international separators' => ['+254 714-294-113', '254714294113'],
        ];
    }

    #[DataProvider('invalidPhones')]
    public function test_it_rejects_invalid_phone_numbers(?string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SmsService())->formatPhone($input);
    }

    public static function invalidPhones(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'local number missing digit' => ['071234567'],
            'local number starts with zero invalid' => ['0812345678'],
            'invalid letters' => ['+254abc714294113'],
            'extension' => ['+254714294113 ext 2'],
            'too long' => ['+25471429411312345678'],
        ];
    }
}
