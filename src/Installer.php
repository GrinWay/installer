<?php

namespace GrinWay\Installer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;

class Installer implements PluginInterface, EventSubscriberInterface
{
	private Composer $composer;
	private IOInterface $io;
	
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
			ScriptEvents::POST_INSTALL_CMD => ['beforeComposerInstall'],
            /*
			ScriptEvents::POST_INSTALL_CMD => ['install'],
            ScriptEvents::POST_UPDATE_CMD => ['install'],
			*/
        ];
    }
	
	public function beforeComposerInstall(): void
	{
		$packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
		\var_dump(__METHOD__);
	}
	
	public function install(): void
	{
		\var_dump(__METHOD__);
	}
}