<?php
namespace SocioChat\Controllers;

use SocioChat\Chain\ChainContainer;
use SocioChat\Clients\PendingDuals;
use SocioChat\Clients\User;
use SocioChat\Clients\UserCollection;
use SocioChat\DAO\PropertiesDAO;
use SocioChat\Enum\SexEnum;
use SocioChat\Enum\TimEnum;
use SocioChat\Forms\Form;
use SocioChat\Forms\Rules;
use SocioChat\Forms\WrongRuleNameException;
use SocioChat\OnOpenFilters\ResponseFilter;
use SocioChat\Response\MessageResponse;
use SocioChat\Response\UserPropetiesResponse;
use SocioChat\Utils\CharTranslator;
use SocioChat\Utils\Lang;

class PropertiesController extends ControllerBase
{
	private $actionsMap = [
		'info' => 'processInfo',
		'submit' => 'processSubmit'
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
		return ['action'];
	}

	protected function processSubmit(ChainContainer $chain)
	{
		$request = $chain->getRequest();
		$user = $chain->getFrom();
		$lang = $user->getLang();

		try {
			$form = (new Form())
				->import($request)
				->addRule(PropertiesDAO::NAME, Rules::namePattern(), $lang->getPhrase('InvalidNameFormat'))
				->addRule(PropertiesDAO::TIM, Rules::timPattern(), $lang->getPhrase('InvalidTIMFormat'))
				->addRule(PropertiesDAO::SEX, Rules::sexPattern(), $lang->getPhrase('InvalidSexFormat'));
				//->addRule(PropertiesDAO::NOTIFICATIONS, Rules::notNull(), 'Не заполнены настройки уведомлений');
		} catch (WrongRuleNameException $e) {
			$this->errorResponse($user, ['property' => $lang->getPhrase('InvalidProperty')]);
			return;
		}

		if (!$form->validate()) {
			$this->errorResponse($user, $form->getErrors());
			return;
		}

		$userName = $request[PropertiesDAO::NAME] = strip_tags(trim($request[PropertiesDAO::NAME]));

		if (!$this->checkIfAlreadyRegisteredName(CharTranslator::toEnglish($userName), $user)) {
			return;
		}

		if (!$this->checkIfAlreadyRegisteredName(CharTranslator::toRussian($userName), $user)) {
			return;
		}

		$oldName = $user->getProperties()->getName();


		$this->importProperties($user, $request);

		if ($user->isInPrivateChat() || PendingDuals::get()->getUserPosition($user)) {
			$this->forbiddenChangeInDualization($user);
			$this->propertiesResponse($user);
			return;
		}

		$this->guestsUpdateResponse($user, $oldName);
		$this->propertiesResponse($user);

		(new ResponseFilter())->notifyOnPendingDuals($user);
	}

	private function checkIfAlreadyRegisteredName($userName, User $user)
	{
		$duplUser = PropertiesDAO::create()->getByUserName($userName);

		if ($duplUser->getId() && $duplUser->getUserId() != $user->getId()) {
			$this->errorResponse($user, [PropertiesDAO::NAME => $user->getLang()->getPhrase('NameAlreadyRegistered', $userName)]);
			$this->propertiesResponse($user);
			return;
		}

		return true;
	}

	private function propertiesResponse(User $user)
	{
		$response = (new UserPropetiesResponse())
			->setUserProps($user)
			->setChatId($user->getChatId());

		(new UserCollection())
			->attach($user)
			->setResponse($response)
			->notify();
	}

	private function guestsUpdateResponse(User $user, $oldName)
	{
		$response = (new MessageResponse())
			->setGuests(UserCollection::get()->getUsersByChatId($user->getChatId()))
			->setChatId($user->getChatId())
			->setTime(null);

		$props = $user->getProperties();

		if ($props->getName() != $oldName) {
			$response->setMsg($user->getLang()->getPhrase('UserChangedName', $oldName, $props->getName()));
		}

		UserCollection::get()
			->setResponse($response)
			->notify();
	}

	private function forbiddenChangeInDualization(User $user)
	{
		$response = (new UserPropetiesResponse())
			->setUserProps($user)
			->setMsg($user->getLang()->getPhrase('ProfileChangeForbiddenInDualization'))
			->setChatId($user->getChatId());

		(new UserCollection())
			->attach($user)
			->setResponse($response)
			->notify();
	}

	private function importProperties(User $user, $request)
	{
		$name = $request[PropertiesDAO::NAME];
		$tim = TimEnum::create($request[PropertiesDAO::TIM]);
		$guestName = $user->getLang()->getPhrase('Guest');

		if (mb_strpos($name, $guestName) !== false) {
			$newname = str_replace($guestName, $tim->getShortName(), $name);
			$duplUser = PropertiesDAO::create()->getByUserName($newname);

			if (!($duplUser->getId() && $duplUser->getUserId() != $user->getId())) {
				$name = $newname;
			}
		}

		$properties = $user->getProperties();

		$properties
			->setUserId($user->getId())
			->setName($name)
			->setTim(TimEnum::create($request[PropertiesDAO::TIM]))
			->setSex(SexEnum::create($request[PropertiesDAO::SEX]));
			//->setNotifications($request[PropertiesDAO::NOTIFICATIONS]);

		$properties->save();
	}
}