<?php
namespace MyApp\Controllers;

use MyApp\Chain\ChainContainer;
use MyApp\Clients\ChatsCollection;
use MyApp\Clients\PendingDuals;
use MyApp\Clients\User;
use MyApp\Clients\UserCollection;
use MyApp\Response\MessageResponse;
use MyApp\Utils\Lang;

class MainChatController extends ControllerBase
{
	public function handleRequest(ChainContainer $chain)
	{
		$user = $chain->getFrom();
		$oldChatId = $user->getChatId();
		$duals = PendingDuals::get();

		if ($duals->deleteByUserId($user->getId())) {
			$this->informOnPendingExit($user);
			$userList = $duals->getUsersByTim($user->getProperties()->getTim());
			$this->sendRenewPositions($userList);
		}

		if (!$user->isInPrivateChat()) {
			return;
		}

		$user->setChatId(1);

		$this->handleOthersOnOldChat($user, $oldChatId);

		$this->informYouselfOnExit($user);
		$this->refreshGuestListOnNewChat($user);

		ChatsCollection::get()->clean($user);
		$user->save();
	}

	protected function getFields()
	{
		return [];
	}

	private function handleOthersOnOldChat(User $user, $oldChatId)
	{
		$clients = UserCollection::get();
		$partners = $clients->getUsersByChatId($oldChatId);

		$response = (new MessageResponse())
			->setTime(null)
			->setMsg(Lang::get()->getPhrase('UserLeftPrivate', $user->getProperties()->getName()))
			->setDualChat('exit')
			->setGuests($partners)
			->setChatId($oldChatId);

		$clients
			->setResponse($response)
			->notify();

		foreach ($partners as $pUser) {
			$pUser->setChatId(1);
			$pUser->save();
		}

		$this->refreshGuestListOnNewChat($user);
	}

	private function refreshGuestListOnNewChat(User $user)
	{
		$clients = UserCollection::get();

		$response = (new MessageResponse())
			->setTime(null)
			->setGuests($clients->getUsersByChatId(1))
			->setChatId($user->getChatId());

		$clients
			->setResponse($response)
			->notify(false);
	}

	private function informOnPendingExit(User $user)
	{
		$response = (new MessageResponse())
			->setChatId($user->getChatId())
			->setTime(null)
			->setDualChat('exit')
			->setMsg(Lang::get()->getPhrase('ExitDualQueue'));

		(new UserCollection())
			->attach($user)
			->setResponse($response)
			->notify(false);
	}

	private function informYouselfOnExit(User $user)
	{
		$response = (new MessageResponse())
			->setChatId($user->getChatId())
			->setTime(null)
			->setDualChat('exit')
			->setMsg((Lang::get()->getPhrase('ReturnedToMainChat')));

		(new UserCollection())
			->attach($user)
			->setResponse($response)
			->notify(false);
	}


	private function sendRenewPositions(array $userIds)
	{
		if (empty($userIds)) {
			return;
		}

		$notification = new UserCollection();
		$users = UserCollection::get();

		foreach ($userIds as $userId) {
			$user = $users->getClientById($userId);
			$response = (new MessageResponse())
				->setGuests(UserCollection::get()->getUsersByChatId($user->getChatId()))
				->setChatId($user->getChatId());

			$notification
				->attach($user)
				->setResponse($response);
		}

		$notification->notify(false);
	}
}