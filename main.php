#!/usr/bin/env php
<?php
/**
 * Part of php-code-sniffer-tool project.
 *
 * @copyright Copyright (C) 2015 LeoOnTheEarth
 * @license MIT
 */

createInstallDir();
installComposer();
main();

/**
 * Main function
 *
 * @return void
 */
function main()
{
    $argv = $_SERVER['argv'];
    $command = 'list';
    $commands = ['list', 'show', 'install', 'help'];

    if (isset($argv[1])) {
        $command = $argv[1];

        if (!in_array($command, $commands)) {
            help('list');
        }
    }

    switch ($command) {
        case 'show':
            show();

            break;

        case 'install':
            if (!isset($argv[2])) {
                help('install');
            }

            install($argv[2]);

            break;

        case 'update':
            if (!isset($argv[2])) {
                help('update');
            }

            update($argv[2]);

            break;

        case 'help':
            if (!isset($argv[2])) {
                help('help');
            }

            help($argv[2]);

            break;

        default:
            help('list');
    }
}

/**
 * Show a list with available code sniffers
 *
 * @return void
 */
function show()
{
    $list = api('index.json');

    echo implode(PHP_EOL, $list) . PHP_EOL;
}

/**
 * Install code sniffer with a given code sniffer name (ex: symfony/Symfony2)
 *
 * @param string $snifferName Code sniffer name (ex: symfony/Symfony2)
 *
 * @return void
 */
function install($snifferName)
{
    $installDir = getInstallDir();
    $composer = readComposerFile();
    $list = api('index.json');

    if (!in_array($snifferName, $list)) {
        error(sprintf('The sniffer name "%s" is not exists!', $snifferName));
    }

    // Get sniffer config file
    $package = api($snifferName . '.json');

    $doInstall = false;
    $repoIndex = -1;
    $paths = array();

    foreach ($composer['repositories'] as $index => $repo) {
        if ($snifferName === $repo['package']['name']) {
            $repoIndex = $index;

            if ($repo['package']['version'] !== $package['version']) {
                $doInstall = true;
                $composer['repositories'][$index]['package'] = $package;
            }
        }

        list($folderName) = explode('/', $repo['package']['name']);
        $paths[] = $installDir . '/vendor/' . $folderName;
    }

    if (-1 === $repoIndex) {
        $doInstall = true;
        $composer['repositories'][] = array(
            'type' => 'package',
            'package' => $package,
        );

        list($folderName) = explode('/', $package['name']);
        $paths[] = $installDir . '/vendor/' . $folderName;
    }

    $composer['require'][$snifferName] = $package['version'];

    writeComposerFile($composer);

    if ($doInstall) {
        composerInstall();
    }

    $paths = array_unique($paths);
    $paths = array_map('realpath', $paths);
    $config = array('installed_paths' => implode(',', $paths));
    $output = sprintf('<?php $phpCodeSnifferConfig=%s; ?>', var_export($config, true));

    // Write CodeSniffer.conf
    file_put_contents($installDir . '/vendor/squizlabs/php_codesniffer/CodeSniffer.conf', $output);

    echo PHP_EOL . 'Install complete' . PHP_EOL;

    printf('phpcs is locate at "%s"' . PHP_EOL, realpath($installDir . '/vendor/bin/phpcs'));
}

/**
 * Update code sniffer with a given code sniffer name (ex: symfony/Symfony2)
 *
 * @param string $snifferName Code sniffer name (ex: symfony/Symfony2)
 *
 * @return void
 */
function update($snifferName)
{
    install($snifferName);
}

/**
 * Read composer file
 *
 * @return array
 */
function readComposerFile()
{
    $composerFilename = getComposerFilename();
    $composer = array();

    if (file_exists($composerFilename)) {
        $composer = @json_decode(file_get_contents($composerFilename), true);
    }

    foreach (array('repositories', 'require') as $key) {
        if (!array_key_exists($key, $composer)) {
            $composer[$key] = array();
        }
    }

    if (!array_key_exists('squizlabs/php_codesniffer', $composer['require'])) {
        $composer['require']['squizlabs/php_codesniffer'] = '*';
    }

    return $composer;
}

/**
 * Write new content into composer file
 *
 * @param array $composer New composer content
 *
 * @return int The number of bytes that were written to the file, or false on failure.
 */
function writeComposerFile(array $composer)
{
    return file_put_contents(getComposerFilename(), json_encode($composer));
}

/**
 * Get composer filename
 *
 * @return string The composer filename
 */
function getComposerFilename()
{
    return getInstallDir() . '/composer.json';
}

/**
 * Install packages with composer
 *
 * @return void
 */
function composerInstall()
{
    $installDir = getInstallDir();
    $composerLockFilename = $installDir . '/composer.lock';
    $command = (!file_exists($composerLockFilename) ? 'install' : 'update');
    $command = sprintf('php %s/composer.phar --working-dir=%s %s', $installDir, $installDir, $command);

    echo PHP_EOL . 'Execute command: ' . $command . PHP_EOL;

    passthru($command);
}

/**
 * API
 *
 * @param string $filename
 *
 * @return array API results
 */
function api($filename)
{
    $url = 'http://leoontheearth.github.io/php-code-sniffer-tool/cs/' . $filename;

    $result = file_get_contents($url);

    return @json_decode($result, true);
}

/**
 * Install composer
 *
 * @return void
 */
function installComposer()
{
    $filename = getInstallDir() . '/composer.phar';
    $install = false;

    if (!file_exists($filename)) {
        $install = true;
    } else {
        $age = ($_SERVER['REQUEST_TIME'] - filemtime($filename)) / 86400;

        // If the composer.phar file is over 30 days, update it.
        if ($age > 30) {
            $install = true;
        }
    }

    if (true === $install) {
        echo 'Download composer.phar...' . PHP_EOL;

        $phar = file_get_contents('http://getcomposer.org/composer.phar');

        file_put_contents($filename, $phar);
    }
}

/**
 * Get help massages for a command
 *
 * @param string $command
 *
 * @return string Help messages
 */
function help($command)
{
    $message = <<<MSG
=======================================
         PHP Code Sniffer Tool

A management tool for PHP Code Sniffers
=======================================

Usage:
 phpcs-tool <command-name> [arguments]

Available commands:
 help      Displays help for a command
 install   Install code sniffer with a given code sniffer name
 update    Update code sniffer with a given code sniffer name
 list      List commands
 show      Show a list with available code sniffers
MSG;

    switch ($command) {
        case 'show':
            $message = <<<MSG
Usage:
 phpcs-tool show

Help:
 Show a list with available code sniffers
MSG;
        break;

        case 'install':
            $message = <<<MSG
Usage:
 phpcs-tool install <code-sniffer-name>

Help:
 Install code sniffer with a given code sniffer name (ex: symfony/Symfony2)
MSG;
        break;

        case 'update':
            $message = <<<MSG
Usage:
 phpcs-tool update <code-sniffer-name>

Help:
 Update code sniffer with a given code sniffer name (ex: symfony/Symfony2)
MSG;
        break;

        case 'help':
            $message = <<<MSG
Usage:
 phpcs-tool help <command-name>

Help:
 Displays help for a command
MSG;
        break;
    }

    echo $message . PHP_EOL;

    exit;
}

/**
 * Create install directory
 *
 * If the install directory is not exists, create one.
 *
 * @return void
 */
function createInstallDir()
{
    $dir = getInstallDir();

    if (!is_dir($dir)) {
        printf('Create install directory: "%s"' . PHP_EOL, $dir);

        mkdir($dir, 0777, true);
    }
}

/**
 * Get install directory
 *
 * @return string Return install directory
 */
function getInstallDir()
{
    $folderName = '.php-code-sniffer-tool';
    $os = php_uname();

    if (preg_match('#^Windows#i', $os)) {
        // Windows
        $configPath = getenv('HOMEDRIVE') . getenv('HOMEPATH') . '\\' . $folderName;
    } else {
        // Linux or Mac OSX
        $configPath = getenv('HOME') . '/' . $folderName;
    }

    return $configPath;
}

/**
 * Show error messages
 *
 * @param string $message
 *
 * @return void
 */
function error($message)
{
    echo $message . PHP_EOL;

    exit;
}
