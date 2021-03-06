PHP Code Sniffer Tool
=====================

A management tool for php code sniffers

## Installation

Download [phpcs-tool](https://github.com/LeoOnTheEarth/php-code-sniffer-tool/releases/download/0.0.7/phpcs-tool.phar)

## List available code sniffers

```bash
$ php phpcs-tool.phar show
```

Output example:

```
symfony/Symfony2
joomla/Joomla
```

## Install a code sniffer

```bash
$ php phpcs-tool.phar install symfony/Symfony2
```

Show available code sniffer with phpcs command

```bash
$ ~/.php-code-sniffer-tool/bin/phpcs -i
```

## Update a code sniffer

```bash
$ php phpcs-tool.phar update symfony/Symfony2
```

## TODO

- Add `remove` command to remove a sniffer
- Add `self-update` command to update `phpcs-tool.phar` file
- Add CodeSniffer 3.x support
