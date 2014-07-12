<?php
namespace SocioChat\OnOpenFilters;

use SocioChat\Chain\ChainContainer;
use SocioChat\Chain\ChainInterface;
use SocioChat\Clients\ChannelsCollection;
use SocioChat\Clients\PendingDuals;
use SocioChat\Clients\User;
use SocioChat\Clients\UserCollection;
use SocioChat\Controllers\Helpers\ChannelNotifier;
use SocioChat\DI;
use SocioChat\Message\Msg;
use SocioChat\Message\MsgRaw;
use SocioChat\Message\MsgToken;
use SocioChat\Response\MessageResponse;
use SocioChat\Response\UserPropetiesResponse;

class ResponseFilter implements ChainInterface
{
	public function handleRequest(ChainContainer $chain)
	{
		$clients = UserCollection::get();
		$user = $chain->getFrom();

		$this->sendNickname($user, $clients);
		$this->handleHistory($user);
		$this->notifyChat($user, $clients);
	}

	public function sendNickname(User $user, UserCollection $clients)
	{
		$response = (new UserPropetiesResponse())
			->setUserProps($user)
			->setChatId($user->getChatId())
			->setGuests($clients->getUsersByChatId($user->getChatId()));

		(new UserCollection())
			->attach($user)
			->setResponse($response)
			->notify(false);
	}

	/**
	 * @param User $user
	 * @param UserCollection $userCollection
	 */
	public function notifyChat(User $user, UserCollection $userCollection)
	{
		$chatId = $user->getChatId();

		DI::get()->getLogger()->info("Total user count {$userCollection->getTotalCount()}", [__CLASS__]);

		if ($user->isInPrivateChat()) {
			$dualUsers = new UserCollection();
			$dualUsers->attach($user);

			$response = (new MessageResponse())
				->setTime(null)
				->setGuests($userCollection->getUsersByChatId($chatId))
				->setChatId($chatId);

			if ($userCollection->getClientsCount($chatId) > 1) {
				$dualUsers = $userCollection;
				$response
					->setMsg(MsgToken::create('PartnerIsOnline'))
					->setDualChat('match');
			} elseif ($num = PendingDuals::get()->getUserPosition($user)) {
				$response
					->setMsg(MsgToken::create('StillInDualSearch', $num))
					->setDualChat('init');
			} else {
				$response
					->setMsg(MsgToken::create('YouAreAlone'))
					->setDualChat('match');
			}

			if ($user->getLastMsgId() > 0) {
				$response->setMsg(Msg::create(null));
			}

			$dualUsers
				->setResponse($response)
				->notify(false);
		} else {
			ChannelNotifier::welcome($user, $userCollection, $chatId);
		}
	}

	private function handleHistory(User $user)
	{
		ChannelNotifier::uploadHistory($user);

		if (file_exists(ROOT.DIRECTORY_SEPARATOR.'www'.DIRECTORY_SEPARATOR.'motd.txt') && $user->getLastMsgId() == 0) {
			$motd = file_get_contents(ROOT.DIRECTORY_SEPARATOR.'www'.DIRECTORY_SEPARATOR.'motd.txt');

			$client = (new UserCollection())
				->attach($user);
			$response = (new MessageResponse())
				->setChatId($user->getChatId())
				->setMsg(MsgRaw::create($motd));
			$client
				->setResponse($response)
				->notify(false);
		}
	}
}
