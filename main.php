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
    $commands = ['list', 'show', 'install', 'update', 'help'];

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
 * @param string $snifferName  Code sniffer name (ex: symfony/Symfony2)
 * @param bool   $forceInstall Decide whether to force install / update composer
 *
 * @return void
 */
function install($snifferName, $forceInstall = false)
{
    $list = api('index.json');

    if (!in_array($snifferName, $list)) {
        error(sprintf('The sniffer name "%s" is not exists!', $snifferName));
    }

    // Get sniffer config file
    $package = api($snifferName . '.json');

    $installDir = getInstallDir()  . '/';
    $vendorDir = $installDir . 'vendor/';
    $composer = readComposerFile();
    $installedSniffers = getInstalledSniffers();
    $doInstall = $forceInstall;
    $paths = array();

    foreach ($composer['repositories'] as $index => $repo) {
        if ($snifferName === $repo['package']['name']) {
            if ($repo['package']['version'] !== $package['version']) {
                $doInstall = true;
                $composer['repositories'][$index]['package'] = $package;
            }
        }

        if (array_key_exists($repo['package']['name'], $installedSniffers)) {
            list($folderName) = explode('/', $repo['package']['name']);
            $paths[] = $vendorDir . $folderName;
        }
    }

    if (!array_key_exists($snifferName, $installedSniffers)) {
        $doInstall = true;

        $composer['repositories'][] = array(
            'type' => 'package',
            'package' => $package,
        );

        list($folderName) = explode('/', $package['name']);
        $paths[] = $vendorDir . $folderName;
    }

    $composer['require'][$snifferName] = $package['version'];

    writeComposerFile($composer);

    if ($doInstall) {
        composerInstall();
        updateInstalledSniffers($snifferName, $package['phpcs-branch']);
    }

    $paths = array_unique($paths);
    $paths = array_map('realpath', $paths);
    $config = array('installed_paths' => implode(',', $paths));
    $output = sprintf('<?php $phpCodeSnifferConfig=%s; ?>', var_export($config, true));

    // Write CodeSniffer.conf
    foreach (supportPHPCodeSnifferVersions() as $branch => $version) {
        file_put_contents($vendorDir . 'squizlabs/phpcs-' . $branch . '/CodeSniffer.conf', $output);
    }

    echo PHP_EOL . 'Install complete' . PHP_EOL;

    $phpcsSourceFilename = __DIR__ . '/bin/phpcs';
    $phpcsTargetFilename = $installDir . '/bin/phpcs';

    writeConsoleFile($phpcsSourceFilename, $phpcsTargetFilename);
    writeConsoleFile($phpcsSourceFilename . '.bat', $phpcsTargetFilename . '.bat');

    printf('phpcs is locate at "%s"' . PHP_EOL, realpath($phpcsTargetFilename));
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
    install($snifferName, true);
}

/**
 * Update installed sniffers
 *
 * @param string $snifferName The installed sniffer name
 * @param string $version     The version of PHP Code Sniffer
 *
 * @return void
 */
function updateInstalledSniffers($snifferName, $version)
{
    $supportPHPCodeSnifferBranches = array_keys(supportPHPCodeSnifferVersions());

    if (!in_array($version, $supportPHPCodeSnifferBranches)) {
        error(
            sprintf(
                'The sniffer package phpcs-branch "%s" is not valid! (currently support %s only)',
                $version,
                implode(', ', $supportPHPCodeSnifferBranches)
            )
        );
    }

    $sniffers = getInstalledSniffers();

    if (!array_key_exists($snifferName, $sniffers)) {
        $sniffers[$snifferName] = array();
    }

    $sniffers[$snifferName]['phpcs-branch'] = $version;

    file_put_contents(getInstalledSnifferFilename(), json_encode($sniffers));
}

/**
 * Get installed sniffers
 *
 * @return  array
 */
function getInstalledSniffers()
{
    $installedSnifferFilename = getInstalledSnifferFilename();

    if (file_exists($installedSnifferFilename)) {
        $return = @json_decode(file_get_contents($installedSnifferFilename), true);

        if (is_array($return)) {
            return $return;
        }
    }

    return array();
}

/**
 * Get installed sniffer filename
 *
 * @return string
 */
function getInstalledSnifferFilename()
{
    return getInstallDir() . '/installed.json';
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

        if (!is_array($composer)) {
            $composer = array();
        }
    }

    setPHPCSComposerSchema($composer);

    return $composer;
}

/**
 * Set phpcs composer schema
 *
 * @param array $composer Composer schema
 *
 * @return void
 */
function setPHPCSComposerSchema(&$composer)
{
    $schema = getPHPCSComposerSchema();

    foreach (array('repositories', 'require') as $key) {
        if (!array_key_exists($key, $composer)) {
            $composer[$key] = array();
        }
    }

    foreach ($schema['repositories'] as $repoName => $package) {
        $repoIndex = -1;

        foreach ($composer['repositories'] as $index => $repo) {
            if ($repoName === $repo['package']['name']) {
                $repoIndex = $index;
            }
        }

        if (-1 === $repoIndex) {
            $composer['repositories'][] = $package;
        } else {
            $composer['repositories'][$repoIndex] = $package;
        }

        $composer['require'][$repoName] = $schema['require'][$repoName];
    }

    unset($composer['require']['squizlabs/php_codesniffer']);
}

/**
 * Get phpcs composer schema
 *
 * @return array
 */
function getPHPCSComposerSchema()
{
    $versions = supportPHPCodeSnifferVersions();

    $schema = array(
        'repositories' => array(),
        'require' => array(),
    );

    foreach ($versions as $branch => $version) {
        $schema['repositories']['squizlabs/phpcs-' . $branch] = array(
            "type" => "package",
            "package" => array(
                "name" => "squizlabs/phpcs-" . $branch,
                "version" => $version,
                "dist" => array(
                    "url" => 'https://github.com/squizlabs/PHP_CodeSniffer/archive/' . $version . '.zip',
                    "type" => "zip",
                ),
            ),
        );

        $schema['require']['squizlabs/phpcs-' . $branch] = $version;
    }

    return $schema;
}

/**
 * Support PHP Code Sniffer versions
 *
 * @return array
 */
function supportPHPCodeSnifferVersions()
{
    return array(
        '1.x' => '1.5.6',
        '2.x' => '2.3.0',
    );
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
 * Write console files
 *
 * @param array $source Source filename
 * @param array $target Target filename
 *
 * @return void
 */
function writeConsoleFile($source, $target)
{
    file_put_contents($target, file_get_contents($source));

    chmod($target, 0755);
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

    $return = @json_decode($result, true);

    if (is_array($return)) {
        return $return;
    }

    return array();
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

    $binDir = $dir . '/bin';

    if (!is_dir($binDir)) {
        printf('Create bin directory: "%s"' . PHP_EOL, $binDir);

        mkdir($binDir, 0777);
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
