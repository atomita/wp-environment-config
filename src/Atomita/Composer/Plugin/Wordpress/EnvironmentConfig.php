<?php

namespace Atomita\Composer\Plugin\Wordpress;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Script\Event as ScriptEvent;
use Composer\Util\Filesystem;

/**
 *
 */
class EnvironmentConfig implements PluginInterface, EventSubscriberInterface
{
	private $banner = '@generated by atomita/wp-environment-config';

	protected $servername_switch = <<<EOD
switch (\$_SERVER['SERVER_NAME']){
	case '':
		defined('ENVIRONMENT', 'production');
		break;
	case '':
		defined('ENVIRONMENT', 'staging');
		break;
}
EOD;

	protected $call_environment = <<<EOD
if (!defined('ABSPATH')){
	define('ABSPATH', dirname(__FILE__) . '/');
}

// environment
if (include(dirname(__FILE__) . '/wp-environment-config.php')){
    return;
}
EOD;

	protected $process_environment = <<<EOD
if (defined('ENVIRONMENT')) {
	\$env = ENVIRONMENT;
}
else {
	if (empty(\$env = \$_SERVER['EXECUTION_ENVIRONMENT'])
	and empty(\$env = \$_SERVER['REDIRECT_EXECUTION_ENVIRONMENT'])){

		if (false !== strpos(\$_SERVER['SERVER_NAME'], 'localhost')){
			\$env = 'local';
		}
		else {
			\$env = 'development';
		}

	}
	
	define('ENVIRONMENT', \$env);
}


\$environment_config_file = dirname(__FILE__) . "/{\$env}/wp-config.php";
if (file_exists(\$environment_config_file)) {
	include \$environment_config_file;
	return true;
}

return false;
EOD;

	protected $composer;
	protected $io;

	public function activate(Composer $composer, IOInterface $io)
	{
		$this->composer	 = $composer;
		$this->io		 = $io;
	}


	public static function getSubscribedEvents()
	{
		return array(
			ScriptEvents::POST_INSTALL_CMD => array(
				array('onPostInstallCommand', 0)
			),
		);
	}

	public function onPostInstallCommand(ScriptEvent $event)
	{
		$composer = $event->getComposer();
		$config   = $composer->getConfig();
		
		$filesystem	 = new Filesystem();

		if ($composer->getPackage()){
			$extra = $composer->getPackage()->getExtra();
		
			if (isset($extra['webroot-dir'])){
				$wp_dir		 = $filesystem->normalizePath($extra['webroot-dir']);
				$config_path = $filesystem->normalizePath($wp_dir . '/wp-config.php');
			
				if (!file_exists($config_path) or false === strpos(file_get_contents($config_path), $this->banner)){
					file_put_contents($config_path, <<<EOD
<?php

{$this->servername_switch}

?>
<?php

// {$this->banner}

{$this->call_environment}

// @end generated

EOD
					);
				}
			
				// generate environment.php
				file_put_contents($wp_dir . '/wp-environment-config.php', <<<EOD
<?php

// {$this->banner}

{$this->process_environment}

// @end generated

EOD
				);
			
				// copy wp-config-sample.php
				$sample_path = $filesystem->normalizePath($wp_dir . '/wp-config-sample.php');
			
				foreach (array('production', 'staging', 'development', 'local') as $env){
					if (!file_exists($filesystem->normalizePath($path = "{$wp_dir}/{$env}/wp-config-sample.php"))){
						$filesystem->ensureDirectoryExists("{$extra['webroot-dir']}/{$env}");
						copy($sample_path, $path);
					}
				}
			}
		}
	}

}
