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

namespace BaksDev\Barcode\Reader;

use ErrorException;
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
     * Принимает абсолютный либо относительно директории
     * <project_dir>/public/upload/barcode/tmp
     * проекта путь к файлу
     */
    public function decode(string $imgSource): self
    {
        $this->error = false;

        /**
         * Если файла не существует - пробуем применить BLOB как png
         */

        $isDelete = false;

        if(file_exists($imgSource) === false)
        {
            $isDelete = true;

            /** Файл временный файл */
            $path = implode(DIRECTORY_SEPARATOR, [
                $this->upload,
                'public',
                'upload',
                'barcode',
                'tmp',
                uniqid('', false).'.png',
            ]);

            $this->filesystem->dumpFile($path, $imgSource);

            $imgSource = $path;
        }


        /** Проверяем что файл существует по указанному абсолютному пути */
        $isExist = $this->filesystem->exists($imgSource);

        if($isExist === true)
        {
            $path = $imgSource;
        }

        /** Если по абсолютному пути не находит - пробуем найти по относительному */
        if($isExist === false)
        {
            $path = implode(DIRECTORY_SEPARATOR, [
                $this->upload,
                'public',
                'upload',
                'barcode',
                'tmp',
                $imgSource,
            ]);

            /** Если передан относительный директории проекта путь файла */
            $isExist = $this->filesystem->exists($path);
        }

        if($isExist === true)
        {
            /** Получаем информацию о файле */
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $fileType = finfo_file($info, $path);
            finfo_close($info);

            $path = match ($fileType)
            {
                'image/svg+xml', 'application/pdf' => $this->convertToPng($path),
                'image/png' => $path,
                'image/jpeg' => $path,
                default => false
            };
        }

        /** Если файла не существует - возвращаем ошибку */
        else
        {
            $this->error = true;
            return $this;
        }


        if($path === false)
        {
            throw new ErrorException(sprintf('Неизвестный тип %s', $fileType));
        }

        if($path)
        {
            $process = new Process([
                __DIR__.DIRECTORY_SEPARATOR.'Decode',
                $path,
                '-single',
            ]);

            $process->run();

            if(!empty($process->getErrorOutput()))
            {
                $this->logger->critical(sprintf('Barcode: %s', $process->getErrorOutput()));
                $this->error = true;
            }

            $this->decodeResult($process->getOutput());
        }

        /** Удаляем временный файл */
        if(true === $isDelete)
        {
            $this->filesystem->remove($path);
        }

        return $this;
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
            return $this->decode['Text'];
        }

        return 'Ошибка при сканировании';
    }

    public function isFormat(string $format)
    {
        return strtolower($this->decode['Format']) === $format;
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

        $imagick = new Imagick();
        $imagick->setResolution(500, 500);
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


}
