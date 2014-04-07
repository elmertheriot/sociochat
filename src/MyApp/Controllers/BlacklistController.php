<?php
namespace MyApp\Controllers;

use MyApp\Chain\ChainContainer;
use MyApp\Clients\User;
use MyApp\Clients\UserCollection;
use MyApp\DAO\PropertiesDAO;
use MyApp\Response\MessageResponse;
use MyApp\Utils\Lang;

class BlacklistController extends ControllerBase
{
	private $actionsMap = [
		'ban' => 'processAdd',
		'unban' => 'processRemove',
	];

	public function handleRequest(ChainContainer $chain)
	{
		$action = $chain->getRequest()['action'];
			if (!isset($this->actionsMap[$action])) {
			$this->errorResponse($chain->getFrom());
			return;
		}

		$this->{$this->actionsMap[$action]}($chain);
	}

	protected function getFields()
	{
		return ['action', 'user_id'];
	}

	protected function processAdd(ChainContainer $chain)
	{
		$request = $chain->getRequest();
		$user = $chain->getFrom();

		if (!$banUser = UserCollection::get()->getClientById($request[PropertiesDAO::USER_ID])) {
			$this->errorResponse($user, ['user_id' => Lang::get()->getPhrase('ThatUserNotFound')]);
			return;
		}

		if ($user->getBlacklist()->banUserId($banUser->getId())) {
			$user->save();
			$this->banResponse($user, $banUser);
		}
	}

	protected function processRemove(ChainContainer $chain)
	{
		$request = $chain->getRequest();
		$user = $chain->getFrom();

		if (!$unbanUser = UserCollection::get()->getClientById($request['user_id'])) {
			$this->errorResponse($user, ['user_id' => Lang::get()->getPhrase('ThatUserNotFound')]);
			return;
		}

		$user->getBlacklist()->unbanUserId($unbanUser->getId());
		$user->save();

		$this->unbanResponse($user, $unbanUser);
	}

	private function banResponse(User $user, User $banUser)
	{
		$response = (new MessageResponse())
			->setMsg(Lang::get()->getPhrase('UserBannedSuccessfully', $banUser->getProperties()->getName()))
			->setTime(null)
			->setChatId($user->getChatId())
			->setGuests(UserCollection::get()->getUsersByChatId($user->getChatId()));

		(new UserCollection())
			->attach($user)
			->setResponse($response)
			->notify(false);

		$response = (new MessageResponse())
			->setMsg(Lang::get()->getPhrase('UserBannedYou', $user->getProperties()->getName()))
			->setChatId($banUser->getChatId())
			->setTime(null);

		(new UserCollection())
			->attach($banUser)
			->setResponse($response)
			->notify(false);
	}

	private function unbanResponse(User $user, User $banUser)
	{
		$response = (new MessageResponse())
			->setMsg(Lang::get()->getPhrase('UserIsUnbanned', $banUser->getProperties()->getName()))
			->setTime(null)
			->setChatId($user->getChatId())
			->setGuests(UserCollection::get()->getUsersByChatId($user->getChatId()));

		(new UserCollection())
			->attach($user)
			->setResponse($response)
			->notify(false);

		$response = (new MessageResponse())
			->setMsg(Lang::get()->getPhrase('UserUnbannedYou', $user->getProperties()->getName()))
			->setChatId($banUser->getChatId())
			->setTime(null);

		(new UserCollection())
			->attach($banUser)
			->setResponse($response)
			->notify(false);
	}
}