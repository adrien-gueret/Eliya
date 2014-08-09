<?php
	namespace Eliya;

	var_dump('included');

	require_once('Controller.class.php');
	
	abstract class Controller_Ajax extends Controller
	{
		CONST JSONP_CALLBACK_PARAM_NAME	=	'callback';
		CONST XML_OBJECT_TAG_NAME		=	'object';

		protected $_jsonp_callback	=	null;
		protected $_allow_jsonp		=	false;

		public function __init($type = Mime::JSON)
		{
			$this->response->isRaw(true)->type($type);
		}

		public function success($data, $status = 200)
		{
			$this->response->status($status)->set($this->_transformData($data));
		}

		public function error($data, $status = 500)
		{
			$this->response->error($this->_transformData($data), $status);
		}

		protected function _handleNewXMLTag(\SimpleXMLElement &$tag, array $data)
		{
			foreach($data as $key => $value)
			{
				if(is_array($value))
				{
					$childTag	=	$tag->addChild(is_numeric($key) ? $this::XML_OBJECT_TAG_NAME : $key);
					$this->_handleNewXMLTag($childTag, $value);
				}
				else
					$tag->addChild($key, $value);

			}
		}

		protected function _transformData($data)
		{
			if($this->response->type() === Mime::XML)
			{
				$xml	=	new \SimpleXMLElement('<response/>');

				//Transform $data into a true array (we don't want Objects!)
				$data	=	json_decode(json_encode($data), true);

				$this->_handleNewXMLTag($xml, $data);

				return $xml->asXML();
			}
			else
			{
				if($this->_allow_jsonp && !empty($this->_jsonp_callback))
				{
					$this->response->type(Mime::JS);
					return ';'.$this->_jsonp_callback.'('.json_encode($data).');';
				}

				return $this->response->type() === Mime::JSON ? json_encode($data) : $data;
			}
		}
	}
?>