<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Barcode\Reader;

use Imagick;
use ImagickPixel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class BarcodeRead
{
    private bool $error = false;

    private array $decode = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('barcodeLogger')] private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
    ) {}

    /**
     * Принимает абсолютный путь к файлу
     *
     * @example <project_dir>/public/upload/barcode/tmp
     *
     * @var $decode - декодировать строку base64
     */
    public function decode(string $imgSource, bool $decode = false): self
    {
        $this->error = false;

        $isDelete = false; // флаг для удаления файла после сканирования

        /**
         * Если файла не существует - пробуем сохранить BLOB в файл формата PNG
         */

        if(false === file_exists($imgSource))
        {
            if(true === $decode)
            {
                $imgSource = base64_decode($imgSource);
            }

            $isDelete = true;

            /** Сохраняем BLOB во временный файл */
            $tmpPath = implode(DIRECTORY_SEPARATOR, [
                $this->upload,
                'public',
                'upload',
                'barcode',
                'tmp',
                uniqid('', false).'.png',
            ]);

            $this->filesystem->dumpFile($tmpPath, $imgSource);

            $imgSource = $tmpPath;

        }

        /** Проверяем что файл существует по указанному абсолютному пути */

        if(false === $this->filesystem->exists($imgSource))
        {
            $this->logger->critical(sprintf('Файл для сканирования не найден: %s', $imgSource));

            $this->error = true;
            return $this;
        }

        /** Получаем информацию о файле */
        $info = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($info, $imgSource);
        finfo_close($info);

        $path = match ($fileType)
        {
            'image/svg+xml', 'application/pdf' => $this->convertToPng($imgSource), // конвертируем SVG и PDF в PNG
            'image/png', 'image/jpeg', 'image/jpg' => $imgSource, // PNG и JPEG
            default => false
        };

        if($path === false)
        {
            $this->error = true;
            return $this;
        }

        /** Сканируем файл */

        $process = new Process([
            __DIR__.DIRECTORY_SEPARATOR.'Decode',
            $path,
            '-single',
        ]);

        $process->run();

        if(false === empty($process->getErrorOutput()))
        {
            $this->logger->critical(
                sprintf('barcode: Ошибка при сканировании файла: %s', $path),
                [self::class.':'.__LINE__, $process->getErrorOutput()],
            );

            $this->error = true;
        }

        $this->decodeResult($process->getOutput());

        /** Удаляем файл после сканирования */
        if(true === $isDelete)
        {
            $this->filesystem->remove($path);
        }

        return $this;
    }

    /**
     * Метод конвертируем PNG
     */
    private function convertToPng(string $path): string|false
    {
        // Проверяем, что Imagick установлен
        if(!extension_loaded('imagick'))
        {
            $this->logger->critical('Imagick extension is not loaded');
            $this->error = true;

            return false;
        }

        $convert = $path.'.png';

        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, 3600);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, (1024 * 1024 * 256));

        $imagick = new Imagick();
        $imagick->setResolution(400, 400);
        $imagick->readImage($path);
        $imagick->borderImage('white', 5, 5);

        // Установите цвет фона
        $imagick->setImageBackgroundColor(new ImagickPixel('white'));

        // Получите слои и объедините их
        $layeredImages = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        // Установите формат изображения
        $layeredImages->setImageFormat('png');

        // Сохраните результат
        $layeredImages->writeImage($convert);

        // Освобождение ресурсов
        $layeredImages->clear();
        $imagick->clear();

        return $convert;
    }

    private function decodeResult(string $result): void
    {
        if($this->error)
        {
            return;
        }

        $result = trim($result);

        if($result === 'No barcode found')
        {
            $this->error = true;
            return;
        }

        $lines = explode(PHP_EOL, trim($result));


        // Обрабатываем каждую строку
        foreach($lines as $line)
        {
            // Разделяем строку по первому двоеточию
            [$key, $value] = explode(':', $line, 2);

            if(empty($key) || empty($value))
            {
                continue;
            }

            $decode[trim($key)] = trim($value);
        }

        // Удаляем первый и последний символ из значения 'Text'
        if(isset($decode['Text']))
        {
            $decode['Text'] = substr($decode['Text'], 1, -1);
            $this->decode = $decode;
            $this->error = false;

            return;
        }


        $this->logger->critical('Barcode: Невозможно распознать файл');
        $this->error = true;
    }

    /**
     * Error
     */
    public function isError(): bool
    {
        return $this->error;
    }

    public function getText()
    {
        if($this->error === false)
        {
            return $this->decode['Text'] ?? 'Ошибка при сканировании';
        }

        return 'Ошибка при сканировании';
    }

    public function isFormat(string $format)
    {
        return strtolower($this->decode['Format']) === $format;
    }


}
