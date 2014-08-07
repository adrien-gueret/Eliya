<?php
	namespace Eliya;

	abstract class Core
	{
		protected 	static 	$_default_load_directory	=	'libraries';

		public static function init()
		{
			//Define the project root in file system
			define('PROJECT_ROOT', __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
			
			//Autoload application files
			self::_autoLoad();

			//Need to load Mime class at first
			require_once PROJECT_ROOT.DIRECTORY_SEPARATOR.'system/Mime.class.php';

			//Load system files
			self::requireDirContent(PROJECT_ROOT.DIRECTORY_SEPARATOR.'system');

			//Set some default values
			setlocale(LC_ALL, Config('main')->DEFAULT['LOCAL']);
			date_default_timezone_set(Config('main')->DEFAULT['TIMEZONE']);
		}

		public static function requireDirContent($dir)
		{
			foreach(new \DirectoryIterator($dir) as $file)
			{
				if( ! $file->isDot() && ! $file->isDir() && $file->getExtension() === 'php')
					require_once $file->getPathname();
			}
		}

		protected static function _autoLoad()
		{
			spl_autoload_register(function($className)
			{
				$startIndex	=	strpos($className, '\\');
				if($startIndex !== false)
					$className	=	substr($className, strpos($className, '\\') + 1);

				$segments		=	explode('_', $className);
				$total_segments	=	count($segments);
				$directory		=	null;

				if($total_segments === 0)
					return false;
				if($total_segments === 1)
					$directory	=	self::$_default_load_directory;
				else
				{
					$directory	=	strtolower(array_shift($segments));
					if(substr($directory, -1) === 'y')
						$directory	=	substr($directory, 0, -1).'ies';
					else
						$directory	.=	's';
				}

				$path	=	PROJECT_ROOT . 'application' . DIRECTORY_SEPARATOR . $directory;

				do
					$path	.=	DIRECTORY_SEPARATOR.current($segments);
				while(next($segments));

				$path	.=	'.php';

				if(file_exists($path))
					require_once $path;
			});
		}
	}
	
	//Short syntax to get configurations files
	function Config($name)
	{
		return	new Config($name);
	}