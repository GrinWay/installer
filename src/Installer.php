<?php

namespace GrinWay\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;
use Composer\Script\Event;

class Installer implements PluginInterface, EventSubscriberInterface
{
	public const CONFIG_KEY = 'grin_way_installer';
	
	private Composer $composer;
	private IOInterface $io;
	private readonly string $projectDir;
	
	public function __construct() {
		$this->projectDir = __DIR__.'/../../../..';
	}
	
	public function activate(Composer $composer, IOInterface $io)
    {
		$this->composer = $composer;
		$this->io = $io;
    }
	
	public function deactivate(Composer $composer, IOInterface $io)
    {
    }
	
	public function uninstall(Composer $composer, IOInterface $io)
    {
    }
	
    public static function getSubscribedEvents(): array
    {
        return [
			ScriptEvents::PRE_INSTALL_CMD => ['preInstallCmd'],
			ScriptEvents::POST_INSTALL_CMD => ['postInstallCmd'],
            ScriptEvents::POST_UPDATE_CMD => ['postUpdateCmd'],
        ];
    }
	
	public function preInstallCmd(Event $event): void
	{
	}
	
	public function postInstallCmd(Event $event): void
	{
		$this->post($event);
	}
	
	public function postUpdateCmd(Event $event): void
	{
		$this->post($event);
	}
	
	private function post(Event $event): void {
		$getDefaultExtra = $this->getDefaultExtra(...);
		
		$extraGrinWayIsEnabled = $this->extraGetter(
			$event,
			true, // When change default -> change docs default `docs/configuration_with_composer_extra.md`
			self::CONFIG_KEY,
			'enable',
		);
		// main plugin predicat
		if (true === $extraGrinWayIsEnabled) {

			$extraGrinWayIsEnabledToCopyBundlesConfigs = $this->extraGetter(
				$event,
				true, // When change default -> change docs default `docs/configuration_with_composer_extra.md`
				self::CONFIG_KEY,
				'enable_copy_bundles_configs_to_project_dir',
			);
			if (true === $extraGrinWayIsEnabledToCopyBundlesConfigs) {
				$this->copyBundlesExampleConfigsToProjectDirToMakeItWork($event);
			}
			
			//... other future config
		}
	}
	
	private function extraGetter(Event $event, mixed $default, string|int...$keys): mixed {
		$extra = $event->getComposer()->getPackage()->getExtra();
		
		$nestedExtra = $extra;
		foreach($keys as $key) {
			if (!isset($nestedExtra[$key])) {
				return $default;
			}
			// unpack
			$nestedExtra = $nestedExtra[$key];
		}
		return $nestedExtra;
	}
	
	private function getDefaultExtra(string|int...$key): mixed {
		return 'TODO';
	}
	
	private function copyNotOverwrite(string $fromPath, string $toPath, ?callable $prependOutputMessage = null, ?callable $appendOutputMessage = null): ?array {
		if (!\is_file($fromPath)) {
			return null;
		}
		// not overwrite
		if (\is_file($toPath)) {
			return null;
		}
		$appendOutputMessage ??= static fn() => '';
		$prependOutputMessage ??= static fn() => '';
		
		$output = '';
		$resultCode = 0;
		$command = [
			'cp',
			'-n', // not overwrite
			\sprintf('"%s"', $fromPath),
			\sprintf('"%s"', $toPath),
		];
		$command = \implode(' ', $command);
		\exec($command, $output, $resultCode);
		if (0 === $resultCode) {
			$output = \sprintf(
				'%s [NEW FILE]',
				\realpath($toPath),
			);
		}
		
		$output = $prependOutputMessage($resultCode, $output).$output;
		$output .= $appendOutputMessage($resultCode, $output);
		
		return [
			'output'      => $output,
			'result_code' => $resultCode,
		];
	}
	
	private function dump(?array $outputOptions): void {
		if (null === $outputOptions) {
			return;
		}
		
		$get = static function(string $key) use ($outputOptions): mixed {
			if (!isset($outputOptions[$key])) {
				return null;
			}
			return $outputOptions[$key];
		};
		$output = (string) $get('output');
		$resultCode = (int) $get('result_code');
		
		if (0 === $resultCode) {
			$resultMessage = \sprintf(
				'%s%s',
				$this->blueColorWrap($output),
				\PHP_EOL,
			);
			$this->io->write($resultMessage);
		} else {
			$resultMessage = \sprintf(
				"%s%sWith NOT successful result code: \"%s\"%s",
				$output,
				\PHP_EOL,
				(string) $resultCode,
				\PHP_EOL,
			);
			$this->io->writeError($resultMessage);
		}
	}
	
	/*
	 * @return null (When owner doesn't exist)
	 */
	private function getPackageTypeWithoutOwnerAndBundleSuffix(string $fullPackageName): ?string {
		$fullPackageName = \strtr($fullPackageName, [
			'\\'  => '/',
			'-'   => '_',
		]);
		$explodedFullPackageName = \explode('/', $fullPackageName);
		if (!isset($explodedFullPackageName[1])) {
			return null;
		}
		$packageType = $explodedFullPackageName[1];
		$packageType = \preg_replace('~_bundle$~', '', $packageType);
		
		return $packageType;
	}
	
	private function blueColorWrap(string $string): string {
		return \sprintf('%s%s%s', "\033[0;36m", $string, "\033[0m");
	}
	
	private function copyBundlesExampleConfigsToProjectDirToMakeItWork(Event $event): void {
		$processedPackages = [];
		
		$localRepository = $this->composer->getRepositoryManager()->getLocalRepository();
		$packages = $localRepository->getPackages();
		
		foreach($packages as $package) {
			$packageName = $package->getName();
			// Avoid handling duplicates
			if (isset($processedPackages[$packageName])) {
                continue;
            }
            $processedPackages[$packageName] = true;
			
			if (\str_starts_with(\strtolower($packageName), 'grinway')) {
				$packageAbsDir = $this->composer->getInstallationManager()->getInstallPath($package);
				$packageType = $this->getPackageTypeWithoutOwnerAndBundleSuffix($packageName);
				if (null === $packageType) {
					continue;
				}
				$packageTypeLowerCase = \strtolower($packageType);
				$packageAbsDir = \rtrim($packageAbsDir, '/\\');
				
				$relativePath = \sprintf('config/packages/grin_way_%s.yaml', $packageTypeLowerCase);
				$fromPath = \sprintf(
					'%s/%s',
					$packageAbsDir,
					$relativePath,
				);
				$toPath = \sprintf(
					'%s/%s',
					$this->projectDir,
					$relativePath,
				);
				$outputOptions = $this->copyNotOverwrite(
					fromPath: $fromPath,
					toPath: $toPath,
					appendOutputMessage: static fn($resultCode) => 0 === $resultCode ? \sprintf('%sFor package: "%s"', \PHP_EOL, $packageName) : '',
				);
				$this->dump($outputOptions);
			}
		}
	}
}