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

    private string $path;

    private string $filename;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $upload,
        #[Target('barcodeLogger')] private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,

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

    /**
     * Указать относительный директории upload путь
     */
    public function generate(string $path, string|bool $filename = false): bool
    {
        if(empty($this->text))
        {
            throw new InvalidArgumentException('Текст штрих-кода не может быть пустым');
        }


        $isExistsDir = $this->filesystem->exists($path);


        if($isExistsDir === false)
        {
            /** Если директории не найдено - проверяем относительный директории upload путь */

            $this->path = implode(DIRECTORY_SEPARATOR, [
                $this->upload,
                'public',
                'upload',
                $path,
                ''
            ]);

            /** Если отсутствует директория - создаем */
            $isExistsDir = $this->filesystem->exists($this->path);

            if($isExistsDir === false)
            {
                $this->filesystem->mkdir($this->path);
            }
        }
        else
        {
            $this->path = $path;
        }

        $this->filename = $filename ? $filename.'.'.$this->format : strtolower($this->type).'.'.$this->format;

        $isExistsFile = $this->filesystem->exists($this->path.$this->filename);

        if($isExistsFile)
        {
            /** Удаляем файл для генерации нового */
            $this->filesystem->remove($this->path.$this->filename);
        }

        $process = new Process([
            __DIR__.DIRECTORY_SEPARATOR.'Generate',

            '-size', //      Размер сгенерированного изображения
            '25',
            //'-eclevel', //   Error correction level, [0-8]
            //'-binary', //    Интерпретировать <Text> как имя файла, содержащее двоичные данные
            '-noqz', //      Печата штрих -кода с тихой зоной
            //'-hrt', //       Распечатайте читаемый текст человека под штрих -кодом (если поддерживается)



            $this->type,
            $this->text,
            $this->path.$this->filename
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

    public function render(): string
    {
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
