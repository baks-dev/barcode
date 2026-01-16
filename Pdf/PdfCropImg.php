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

namespace BaksDev\Barcode\Pdf;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class PdfCropImg
{
    private string $path;

    private string $filename;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('barcodeLogger')] private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
    ) {}

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function filename(string $filename): self
    {
        $this->filename = trim($filename);

        return $this;
    }

    public function crop(string $save): bool
    {
        if(false === $this->filesystem->exists($this->path))
        {
            $this->logger->warning('File not found', ['file' => $this->path]);
            return false;
        }

        $filepath = sprintf('%s'.DIRECTORY_SEPARATOR.'%s', $this->path, $this->filename);

        /** Создаем директорию для изображений */
        $save = $this->path.DIRECTORY_SEPARATOR.$save;
        $this->filesystem->mkdir($save);


        //  pdfimgcrop
        // --input=/mnt/1TB/RUST/pdfimgcrop/src/assets/file.pdf
        // --output=/mnt/1TB/RUST/pdfimgcrop/src/assets
        // --format=png

        $command[] = __DIR__.DIRECTORY_SEPARATOR.'PdfCropImg';

        $command[] = sprintf('--input=%s', trim($filepath));
        $command[] = sprintf('--output=%s', trim($save));
        $command[] = '--format=png';

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
}