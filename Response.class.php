<?php
namespace Eliya;

class Response
{
	protected	$_isRaw		=	false;
	protected	$_type		=	Mime::HTML;
	protected	$_body		=	null;
	protected	$_status	=	200;
	protected	$_error		=	null;
	protected	$_headers	=	[];
	protected	$_redirectOnError = true;

	protected function _buildHeader()
	{
		$this->_headers['Content-type']	=	$this->_type;

		foreach($this->_headers as $name => $value)
			header($name . ': ' . $value);

		return $this;
	}

	public function getBody()
	{
		//Handle error
		if($this->isError())
		{
			if($this->_isRaw)
				$this->set($this->_error);
			else if($this->_redirectOnError)
			{
				$errorClassName	=	'Error_' . $this->_status;

				if(class_exists($errorClassName))
					new $errorClassName($this);
				else
					$this->set($this->_error);
			}
		}

		return $this->_body;
	}

	public function render()
	{
		$body	=	$this->getBody();

		$this->_buildHeader();
		http_response_code($this->_status);
		echo $body;
		return $this;
	}

	public function header($name, $value)
	{
		switch(strtolower($name))
		{
			case 'location':
				$this->_headers	=	[$name => $value];
				$this->_buildHeader();
				exit;

			case 'content-type':
				$this->_type		=	$value;
				break;

			default:
				$this->_headers[$name]	=	$value;
		}

		return $this;
	}

	public function redirect($location, $status = 302)
	{
		$this->status($status);
		$this->header('Location', $location);
		return $this;
	}

	public function status($code = false)
	{
		if($code === false)
			return $this->_status;

		$this->_status	=	$code;
		return $this;
	}

	public function redirectToFullErrorPage($redirect)
	{
		$this->_redirectOnError	=	$redirect;
		return $this;
	}

	public function type($newType = false)
	{
		if($newType === false)
			return $this->_type;

		$this->_type	=	$newType;
		return $this;
	}

	public function isRaw($boolean = null)
	{
		if($boolean === null)
			return $this->_isRaw;

		$this->_isRaw	=	$boolean;
		return $this;
	}

	public function error($msg = null, $status = 500)
	{
		if(empty($msg))
			return $this->_error;

		$this->status($status);
		$this->_error	=	$msg;
		return $this;
	}

	public function isError()
	{
		return $this->_status >= 400;
	}

	public function set($msg)
	{
		$this->_body	=	$msg;
		return $this;
	}

	public function prepend($msg)
	{
		$this->_body	=	$msg.$this->_body;
		return $this;
	}

	public function append($msg)
	{
		$this->_body	.=	$msg;
		return $this;
	}
}
?>