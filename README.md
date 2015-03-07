PHP Code Sniffer Tool
=====================

A management tool for php code sniffers

## Installation

Download [phpcs-tool](https://github.com/LeoOnTheEarth/php-code-sniffer-tool/releases/download/0.0.6/phpcs-tool.phar)

## List available code sniffers

```bash
$ php phpcs-tool.phar show
```

Output example:

```
symfony/Symfony2
smstw/SMSTWJoomla
joomla/Joomla
```

## Install a code sniffer

```bash
$ php phpcs-tool.phar install symfony/Symfony2
```

Show available code sniffer with phpcs command

```bash
$ ~/.php-code-sniffer-tool/vendor/bin/phpcs -i
```
