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
use Composer\Script\Event as ScriptEvent;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const TARGET_PACKAGE = 'statikbe/craft-cookie-banner';
    private const MIGRATION_FILENAME = 'm260430_094400_rename_cookie_banner_handle.php';

    private bool $migrationInstalled = false;

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
            ScriptEvents::POST_INSTALL_CMD => 'maybeMigrate',
            ScriptEvents::POST_UPDATE_CMD => 'maybeMigrate',
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

        $this->migrationInstalled = true;
    }

    public function maybeMigrate(ScriptEvent $event): void
    {
        if (!$this->migrationInstalled) {
            return;
        }

        try {
            $cwd = getcwd();
            $env = $this->readCraftEnvironment($cwd . '/.env');

            if ($env !== 'dev') {
                $event->getIO()->write(sprintf(
                    '<info>cookie-banner installer: skipping migrate/all (CRAFT_ENVIRONMENT=%s, only runs on dev)</info>',
                    $env ?? 'unset'
                ));
                return;
            }

            $craftBin = $cwd . '/craft';
            if (!is_file($craftBin)) {
                $event->getIO()->writeError(sprintf(
                    '<warning>cookie-banner installer: %s not found, skipping migrate/all</warning>',
                    $craftBin
                ));
                return;
            }

            [$cmd, $runner] = $this->buildMigrateCommand($cwd, $craftBin);

            $event->getIO()->write(sprintf(
                '<info>cookie-banner installer: running %s migrate/all (CRAFT_ENVIRONMENT=dev)</info>',
                $runner
            ));

            passthru($cmd, $exitCode);

            if ($exitCode !== 0) {
                $event->getIO()->writeError(sprintf(
                    '<warning>cookie-banner installer: %s migrate/all exited with code %d — run migrations manually if needed</warning>',
                    $runner,
                    $exitCode
                ));
            }
        } catch (\Throwable $e) {
            $event->getIO()->writeError(sprintf(
                '<warning>cookie-banner installer: migrate/all skipped due to error: %s</warning>',
                $e->getMessage()
            ));
        }
    }

    /**
     * @return array{0: string, 1: string} [command string, runner label]
     */
    private function buildMigrateCommand(string $cwd, string $craftBin): array
    {
        $insideDdev = getenv('IS_DDEV_PROJECT') === 'true';
        $hasDdevConfig = is_file($cwd . '/.ddev/config.yaml');

        if (!$insideDdev && $hasDdevConfig && $this->commandExists('ddev')) {
            return [
                'ddev craft migrate/all --interactive=0',
                'ddev craft',
            ];
        }

        $cmd = sprintf(
            '%s %s migrate/all --interactive=0',
            escapeshellcmd(PHP_BINARY),
            escapeshellarg($craftBin)
        );

        return [$cmd, 'php craft'];
    }

    private function commandExists(string $bin): bool
    {
        $output = [];
        $exitCode = 1;
        @exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($bin)), $output, $exitCode);
        return $exitCode === 0;
    }

    private function readCraftEnvironment(string $envFile): ?string
    {
        if (!is_readable($envFile)) {
            return null;
        }

        $contents = @file_get_contents($envFile);
        if ($contents === false) {
            return null;
        }

        if (preg_match('/^\s*CRAFT_ENVIRONMENT\s*=\s*"?([^"\r\n]+?)"?\s*$/m', $contents, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
