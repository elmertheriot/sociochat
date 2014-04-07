<?php

namespace MyApp;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use MyApp\TSingleton;

class Log
{
	use TSingleton;

	protected $logger;

	public function __construct()
	{
		$this->logger = new Logger('Chat');
		$type = ChatConfig::get()->getConfig()->logger ?: STDOUT;
		$this->logger->pushHandler(new StreamHandler($type));
	}

	public function fetch()
	{
		return $this->logger;
	}
} 