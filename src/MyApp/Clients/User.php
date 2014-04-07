<?php

namespace MyApp\Clients;

use MyApp\DAO\PropertiesDAO;
use MyApp\DAO\UserBlacklistDAO;
use MyApp\DAO\UserDAO;
use MyApp\Log;
use MyApp\Response\Response;
use Ratchet\ConnectionInterface;
use React\EventLoop\Timer\TimerInterface;

class User implements ConnectionInterface
{
	/**
	 * @var \Ratchet\ConnectionInterface
	 */
	private $connection;

	/**
	 * @var TimerInterface
	 */
	private $timer;
	private $asyncDetach = true;
	private $lastMsgId = 0;

	/**
	 * @var UserDAO
	 */
	private $userDAO;

	public function __construct(ConnectionInterface $client)
	{
		$this->connection = $client;
		ChatsCollection::get()->fetchRoom(1);
	}

	public function send($data)
	{
		$this->connection->send(json_encode($data));
		return $this;
	}

	public function close()
	{
		$this->connection->close();
	}

	public function update(Response $response)
	{
		$this->connection->send($response->toString());
	}

	public function getUserDAO()
	{
		return $this->userDAO;
	}

	public function setUserDAO(UserDAO $user)
	{
		$this->userDAO = $user;
	}

	public function getConnectionId()
	{
		return $this->connection->resourceId;
	}

	public function &getWSRequest()
	{
		return $this->connection->WebSocket->request;
	}

	public function getConnection()
	{
		return $this->connection;
	}

	public function setConnection(ConnectionInterface $conn)
	{
		$this->connection = $conn;
	}

	/**
	 * @param string $chatId
	 * @return $this
	 */
	public function setChatId($chatId)
	{
		ChatsCollection::get()->fetchRoom($chatId);
		$this->userDAO->setChatId($chatId);
		return $this;
	}

	/**
	 * @return int
	 */
	public function getChatId()
	{
		return $this->userDAO->getChatId();
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->userDAO->getId();
	}

	/**
	 * @return string
	 */
	public function getEmail()
	{
		return $this->userDAO->getEmail();
	}

	/**
	 * @return PropertiesDAO
	 */
	public function getProperties()
	{
		return $this->userDAO->getPropeties();
	}

	/**
	 * @return UserBlacklistDAO
	 */
	public function getBlacklist()
	{
		return $this->userDAO->getBlacklist();
	}

	/**
	 * @param \React\EventLoop\Timer\TimerInterface $timer
	 */
	public function setTimer(TimerInterface $timer)
	{
		$this->timer = $timer;
	}

	/**
	 * @return \React\EventLoop\Timer\TimerInterface
	 */
	public function getTimer()
	{
		return $this->timer;
	}

	/**
	 * @param $async
	 * @return $this
	 */
	public function setAsyncDetach($async)
	{
		$this->asyncDetach = $async;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isAsyncDetach()
	{
		return $this->asyncDetach;
	}

	/**
	 * @param int $lastMsgId
	 */
	public function setLastMsgId($lastMsgId)
	{
		$this->lastMsgId = $lastMsgId;
	}

	/**
	 * @return int
	 */
	public function getLastMsgId()
	{
		return $this->lastMsgId;
	}

	public function isInPrivateChat()
	{
		return $this->getChatId() != 1;
	}

	public function save()
	{
		try {
			$properties = $this->getProperties();
			$properties->save();

			$blacklist = $this->getBlacklist();
			$blacklist->save();

			$this->userDAO->save();
		} catch (\PDOException $e) {
			 Log::get()->fetch()->warn("PDO Exception: ".print_r($e, 1), [__CLASS__]);
		}
	}
}