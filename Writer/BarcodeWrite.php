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

namespace BaksDev\Barcode\Writer;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Генерирует из текста штрихкод:
 *
 */
final class BarcodeWrite
{
    private string $format;

    private string $text;

    private string $type;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/upload/')] private readonly string $upload,
    ) {
        /** По умолчанию генерируемый QRCode */
        $this->type = (BarcodeType::QRCode)->value;

        /** По умолчанию генерируемый форма SVG */
        $this->format = (BarcodeFormat::SVG)->value;

    }

    public function text(string|int $text): self
    {
        $this->text = (string) $text;
        return $this;
    }

    public function type(BarcodeType $type): self
    {
        $this->type = $type->value;
        return $this;
    }

    public function format(BarcodeFormat $format): self
    {
        $this->format = $format->value;
        return $this;
    }

    /** Указать относительный директории upload путь  */
    public function generate(string $path): string|false
    {
        if(empty($this->text))
        {
            throw new InvalidArgumentException('Текст штрих-кода не может быть пустым');
        }

        $process = new Process([
            '/usr/lib/zxing-cpp/example/ZXingWriter',
            $this->type,
            $this->text,
            $this->upload.DIRECTORY_SEPARATOR.$path.DIRECTORY_SEPARATOR.strtolower($this->type).'.'.$this->format
        ]);

        try
        {
            $process->run();
            return $process->getOutput();
        }
        catch(ProcessFailedException $exception)
        {

        }

        return false;
    }
}
