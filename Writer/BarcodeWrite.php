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
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Генерирует из текста штрихкод:
 *
 */
final class BarcodeWrite
{
    private const COMMAND = '';

    private string $format;

    private string $text;

    private string $type;
    private LoggerInterface $logger;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $upload,
        private readonly Filesystem $filesystem,
        LoggerInterface $barcodeLogger
    ) {
        /** По умолчанию генерируемый QRCode */
        $this->type = (BarcodeType::QRCode)->value;

        /** По умолчанию генерируемый форма SVG */
        $this->format = (BarcodeFormat::SVG)->value;

        $this->logger = $barcodeLogger;
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
    public function generate(string $path): bool
    {
        if(empty($this->text))
        {
            throw new InvalidArgumentException('Текст штрих-кода не может быть пустым');
        }

        $upload = implode(DIRECTORY_SEPARATOR, [
            $this->upload,
            'public',
            'upload',
            $path,
            ''
        ]);

        /** Если отсутствует директория - создаем */
        $isExistsDir = $this->filesystem->exists($upload);

        if($isExistsDir === false)
        {
            $this->filesystem->mkdir($upload);
        }

        $filename = strtolower($this->type).'.'.$this->format;

        $isExistsFile = $this->filesystem->exists($upload.$filename);

        if($isExistsFile)
        {
            /** Удаляем файл для генерации нового */
            $this->filesystem->remove($upload.$filename);
        }

        $process = new Process([
            __DIR__.DIRECTORY_SEPARATOR.'Generate',
            $this->type,
            $this->text,
            $upload.$filename
        ]);

        try
        {
            $process->mustRun();
            return true;
        }
        catch(ProcessFailedException $exception)
        {
            $this->logger->critical($exception->getMessage());
        }

        return false;
    }
}
