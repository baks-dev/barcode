<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Barcode\Writer\Tests;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group barcode
 */
#[When(env: 'test')]
class BarcodeWriteTest extends KernelTestCase
{
    private const string TEXT = '9ff0ff18-f3bc-7ebc-aa9c-378ff10d1e60';

    private static BarcodeWrite $BarcodeWrite;

    public static function setUpBeforeClass(): void
    {
        self::$BarcodeWrite = self::getContainer()->get(BarcodeWrite::class);
    }


    public function testAztecSVG(): void
    {
        $path = ['barcode', 'test'];

        foreach(BarcodeType::cases() as $type)
        {
            $text = match ($type->value)
            {
                'Aztec' => self::TEXT, // произвольно
                'Codabar' => 'A12345B', // должен начинаться и заканчиваться с символа начала/конца, например: `A123456B`
                'Code39' => self::TEXT, // можно использовать пробелы, но максимальная длина — 43 символа
                'Code93' => self::TEXT, // допускает символы: A-Z, 0-9 и некоторые специальные символы
                'Code128' => self::TEXT, // поддерживает все ASCII символы
                'DataMatrix' => self::TEXT, // произвольно до до 3116 символов
                'EAN-8' => '1234567', // должен состоять из 7 цифр
                'EAN-13' => '123456789012', // должен состоять из 12 цифр
                'ITF' => '023456789012', // вторая цифра может быть нулем или одним из других заданных значений
                'PDF417' => self::TEXT, // можно использовать произвольные строки
                'QRCode' => self::TEXT, // можно закодировать URL, текст или другую информацию
                'UPC-A' => '012345678905', // должен состоять из 12 цифр, включает контрольную цифру
                'UPC-E' => '0123456' // должен состоять из 6 значащих цифр плюс 2 нуля, чтобы достичь 8 знаков
            };


            foreach(BarcodeFormat::cases() as $format)
            {
                /** @see BarcodeWriteDTO */
                $BarcodeWrite = self::$BarcodeWrite;

                $result = $BarcodeWrite
                    ->text($text)
                    ->format($format)
                    ->type($type)
                    ->generate(implode(DIRECTORY_SEPARATOR, $path));

                if($result === false)
                {
                    echo sprintf('Ошибка генерации кода %s %s', $type, $format).PHP_EOL;
                }

                self::assertTrue($result);
            }
        }
    }
}
