# BaksDev Barcode

[![Version](https://img.shields.io/badge/version-7.1.4-blue)](https://github.com/baks-dev/barcode/releases)
![php 8.3+](https://img.shields.io/badge/php-min%208.3-red.svg)

Модуль генерации и считывания штрихкодов.

Поддерживаемый типы:

* Aztec
* Codabar
* Code39
* Code93
* Code128
* DataMatrix
* EAN-8
* EAN-13
* ITF
* PDF417
* QRCode
* UPC-A
* UPC-E

## Установка

``` bash
$ composer require baks-dev/barcode
```

Устанавливаем расширения для работы изображениями

``` bash
sudo apt-get install php-imagick librsvg2-dev librsvg2-bin libcairo2-dev
```

Делаем испольняемыми файлы

``` bash
chmod +x .....PATH_TO_PROJECT..../vendor/baks-dev/barcode/Writer/Generate
chmod +x .....PATH_TO_PROJECT..../vendor/baks-dev/barcode/Reader/Decode
```

Тесты

``` bash
$ php bin/phpunit --group=barcode
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

