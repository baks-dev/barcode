<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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
use Symfony\Component\DependencyInjection\Attribute\Target;
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

    private string $path;

    private string $filename;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $upload,
        #[Target('barcodeLogger')] private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,

    )
    {
        /** По умолчанию генерируемый QRCode */
        $this->type = (BarcodeType::QRCode)->value;

        /** По умолчанию генерируемый форма SVG */
        $this->format = (BarcodeFormat::SVG)->value;

        /** По умолчанию генерируемый форма SVG */
        $this->path = implode(DIRECTORY_SEPARATOR, [
            $this->upload,
            'public',
            'upload',
            'barcode',
            'tmp',
            '',
        ]);
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

    /**
     * Указать относительный директории upload путь
     */
    public function generate(string|false $path = false, string|false $filename = false): bool
    {
        if(empty($this->text))
        {
            throw new InvalidArgumentException('Текст штрих-кода не может быть пустым');
        }

        if(false === $path)
        {
            $path = implode(DIRECTORY_SEPARATOR, [
                $this->upload,
                'public',
                'upload',
                'barcode',
                'tmp',
                '',
            ]);
        }

        if(false === str_starts_with($path, $this->upload))
        {
            $path = implode(DIRECTORY_SEPARATOR, [
                $this->upload,
                'public',
                'upload',
                $path,
                '',
            ]);

            $path = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);
        }


        $this->path = $path;
        $isExistsDir = $this->filesystem->exists($this->path);

        if($isExistsDir === false)
        {
            $this->filesystem->mkdir($this->path);
        }

        $this->filename = $filename ? $filename.'.'.$this->format : md5($this->text).'.'.$this->format;

        $isExistsFile = $this->filesystem->exists($this->path.$this->filename);

        if($isExistsFile)
        {
            /** Удаляем файл для генерации нового */
            $this->filesystem->remove($this->path.$this->filename);
        }


        // Generate [-size <width/height>] [-eclevel <level>] [-noqz] [-hrt] <format> <text> <output>

        $command[] = __DIR__.DIRECTORY_SEPARATOR.'Generate';

        if($this->format === 'png')
        {
            $command[] = '-size';
            $command[] = '500';
        }

        $command[] = $this->type;
        $command[] = $this->text;
        $command[] = $this->path.$this->filename;

        $process = new Process($command);

        try
        {
            $process->mustRun();
        }
        catch(ProcessFailedException $exception)
        {
            $this->logger->critical($exception->getMessage());
            return false;
        }

        return true;
    }

    public function render(): string
    {

        if(false === $this->filesystem->exists($this->path.$this->filename))
        {
            return '';
        }

        return $this->filesystem->readFile($this->path.$this->filename);
    }


    /** Метод возвращает пусть к файлу */
    public function getPath(): string
    {
        return $this->path;
    }

    public function remove(): self
    {
        $this->filesystem->remove($this->path.$this->filename);
        return $this;
    }
}
