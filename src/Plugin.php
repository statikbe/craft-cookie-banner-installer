<?php

namespace statikbe\cookiebanner\installer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const TARGET_PACKAGE = 'statikbe/craft-cookie-banner';
    private const MIGRATION_FILENAME = 'm260430_094400_rename_cookie_banner_handle.php';

    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackage',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackage',
        ];
    }

    public function onPackage(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if ($operation instanceof InstallOperation) {
            $package = $operation->getPackage();
        } elseif ($operation instanceof UpdateOperation) {
            $package = $operation->getTargetPackage();
        } else {
            return;
        }

        if ($package->getName() !== self::TARGET_PACKAGE) {
            return;
        }

        $source = dirname(__DIR__) . '/migrations/RenameCookieBannerHandle.php';
        $destDir = getcwd() . '/migrations';
        $dest = $destDir . '/' . self::MIGRATION_FILENAME;

        if (!is_file($source)) {
            $event->getIO()->writeError(sprintf(
                '<warning>cookie-banner installer: source migration not found at %s</warning>',
                $source
            ));
            return;
        }

        if (file_exists($dest) && hash_file('sha256', $dest) === hash_file('sha256', $source)) {
            return;
        }

        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $event->getIO()->writeError(sprintf(
                '<warning>cookie-banner installer: could not create %s</warning>',
                $destDir
            ));
            return;
        }

        if (!@copy($source, $dest)) {
            $event->getIO()->writeError(sprintf(
                '<warning>cookie-banner installer: failed to copy migration to %s</warning>',
                $dest
            ));
            return;
        }

        $event->getIO()->write(sprintf(
            '<info>cookie-banner installer: installed handle-rename migration at %s</info>',
            $dest
        ));
    }
}
