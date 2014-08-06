<?php
	namespace Eliya;
	
	abstract class Controller
	{
		const		START_CLASS_NAME			=	'\\Controller_';

		public		static $uri					=	null;
		protected	static $_init				=	false;
		protected	static $_page_name	=	'home';

		protected	$_header_view_path	=	'index';
		protected	$_footer_view_path		=	'index';
		protected	$_breadcrumb			=	[];

		public		$request	=	null;
		public		$response	=	null;

		final public function __construct(Request &$request)
		{
			$this->request		=	$request;
			$this->response		=	$request->response();

			if( ! $this->request->isFromAjax())
				Tpl::set('default_app_page', static::$_page_name);
		}

		public function getHeaderViewPath()
		{
			return $this->_header_view_path;
		}

		public function getFooterViewPath()
		{
			return $this->_footer_view_path;
		}

		public function addBreadcrumbLevel(array $data)
		{
			foreach($data as $label => $link)
				$this->_breadcrumb[]	=	['label' => $label, 'link' => $link];
			return $this;
		}

		public function getBreadcrumb()
		{
			return $this->_breadcrumb;
		}

		public function setBreadcrumb(array $breadcrumb)
		{
			$this->_breadcrumb	=	[];
			return $this->addBreadcrumbLevel($breadcrumb);
		}

		public function getBreadcrumbTpl($appendLinks = true, $separator = ' > ')
		{
			$links	=	[];
			$total	=	count($this->_breadcrumb);
			$index	=	0;
			$currentPath	=	null;
			$base_url		=	Config('main')->ROUTING['BASE_URL'];

			foreach($this->_breadcrumb as $level)
			{
				if(substr($level['link'], 0, 1) === '/')
					$link	=	$base_url.substr($level['link'], 1);
				else
					$link	=	$level['link'];

				if($appendLinks)
					$currentPath	.=	$link;
				else
					$currentPath	=	$link;

				if(++$index < $total)
					$links[]	=	'<a href="'.$currentPath.'">' . $level['label'] . '</a>';
				else
					$links[]	=	'<span>' . $level['label'] . '</span>';
			}

			return join($separator, $links);
		}

		public function getPath()
		{
			$controller_name	=	get_called_class();
			$segments			=	explode('_', $controller_name);

			//Remove first element (should be 'Controller')
			array_shift($segments);

			return $this->request->getBaseURL().join('/', $segments);
		}

		public function __init(){}
		public function __before(){}
		public function __after(){}
	}
?>