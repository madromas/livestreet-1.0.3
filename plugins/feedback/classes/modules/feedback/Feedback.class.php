<?php
/*---------------------------------------------------------------------------
* @Module Name: Feedback
* @Description: Feedback for LiveStreet
* @Version: 2.0
* @Author: Chiffa
* @LiveStreet version: 1.X
* @File Name: Feedback.class.php
* @License: CC BY-NC, http://creativecommons.org/licenses/by-nc/3.0/
*----------------------------------------------------------------------------
*/

/**
 * Модуль Feedback - обратная связь
 *
 */
class PluginFeedback_ModuleFeedback extends ModuleORM {
	const ERROR_NOT_MAILS		= 100;
	const ERROR_IN_BLACKLIST	= 201;
	const ERROR_IN_TIMELIMIT	= 202;

	const SORT_DEFAULT			= 0;
	const SORT_BY_GROUP			= 1;
	const SORT_BY_KEY			= 2;

	/**
	 * Объект текущего пользователя
	 *
	 * @var ModuleUser_EntityUser|null
	 */
	protected $oUserCurrent;

	/**
	 * Инициализация
	 *
	 */
	public function Init() {
		parent::Init();
		/**
		 * Получаем объект текущего юзера
		 */
		$this->oUserCurrent=$this->User_GetUserCurrent();
	}

	/**
	 * Получить массив настроек плагина
	 *
	 * @param	integer		$iSort
	 * @return	array
	 */
	public function GetSettings($iSort=self::SORT_DEFAULT) {
		$aRes = array();
		$aSets = $this->GetSettingItemsAll();
		foreach ($aSets as $oSet) {
			if ($iSort == self::SORT_BY_GROUP) {
				$aRes[$oSet->getGroup()][]=$oSet;
			} elseif ($iSort == self::SORT_BY_KEY) {
				$aRes[$oSet->getKey()]=$oSet;
			} else {
				$aKeys=explode('.',$oSet->getKey());
				$sEval='$aRes';
				foreach ($aKeys as $sK) {
					$sEval.='['.var_export((string)$sK,true).']';
				}
				$sEval.='=$oSet->getValue();';
				eval($sEval);
			}
		}
		return $aRes;
	}

	/**
	 * Задает массив настроек плагина
	 *
	 * @param	array		$aSets
	 * @return	boolean
	 */
	public function SetSettings($aSets) {
		if (!is_array($aSets)) {
			return false;
		}
		$aSettings = $this->GetSettings(self::SORT_BY_KEY);
		/**
		 * Сохраняем
		 */
		foreach ($aSets as $sGroup=>$aItems) {
			foreach ($aItems as $sKey=>$sValue) {
				$sNewKey = (string)$sGroup.'.'.$sKey;
				if (isset($aSettings[$sNewKey])) {
					$oSet = $aSettings[$sNewKey];
					unset($aSettings[$sNewKey]);
				} else {
					$oSet = LS::Ent('PluginFeedback_Feedback_Setting');
					$oSet->setGroup($sGroup);
					$oSet->setKey($sNewKey);
				}
				$oSet->setValue($sValue);
				$oSet->Save();
			}
		}
		/**
		 * Удаляем старые
		 */
		foreach ($aSettings as $oSet) $oSet->Delete();

		return true;
	}

	/**
	 * Отправка письма
	 *
	 * @param	object	$oMsg
	 * @return	array
	 */
	public function Send(PluginFeedback_ModuleFeedback_EntityMsg $oMsg) {
		$aRes = array();

		$aMails = Config::Get('plugin.feedback.mail');

		if (!empty($aMails)) {
			/**
			 * Проверяем IP по спискам
			 */
			$bInBlackList = false;
			$bInWhiteList = false;
			$aIpList = $this->PluginFeedback_Feedback_GetIpItemsAll();
			foreach ($aIpList as $oIp) {
				$iChekIp = ip2int($oMsg->getIp());
				$iFromIp = $oIp->getIpFrom();
				$iToIp = $oIp->getIpTo();
				if ($iChekIp >= $iFromIp && $iChekIp <= $iToIp) {
					$bInBlackList = (bool)($oIp->getGroup() == 'black');
					$bInWhiteList = (bool)($oIp->getGroup() == 'white');
				}
			}
			if ($bInBlackList) {
				return array('state'=>false,'code'=>self::ERROR_IN_BLACKLIST);
			}
			if (!$this->CanWrite() && !$bInWhiteList) {
				return array('state'=>false,'code'=>self::ERROR_IN_TIMELIMIT);
			}
			/**
			 * Собираем данные
			 */
			$sSendTitle = $oMsg->getTitle() ? $oMsg->getTitle() : $this->Lang_Get('plugin.feedback.notify_title');
			$aSendContent = array(
				'sIp' => $oMsg->getIp(),
				'sName' => $oMsg->getName(),
				'sMail' => $oMsg->getMail(),
				'sText' => $oMsg->getText()
			);
			/**
			 * Отправляем письмо
			 */
			foreach ($aMails as $sMail) {
				$this->Notify_Send(
					$sMail,
					'notify.feedback.tpl',
					$sSendTitle,
					$aSendContent,
					__CLASS__
				);
			}
			if (!$bInWhiteList) {
				$iTimeLimit = (int)Config::Get('plugin.feedback.acl.limit_time');
				fSetCookie('CfFB', md5(func_getIp()), 0, 0, 0, $iTimeLimit);
			}
			$aRes['state'] = true;
		} else {
			$aRes['state'] = false;
			$aRes['code'] = self::ERROR_NOT_MAILS;
		}
		return $aRes;
	}

	/**
	 * Проверяет возможность написать
	 *
	 * @return boolean
	 */
	public function CanWrite() {
		$sCookieIp=fGetCookie('CfFB');
		return (bool)($sCookieIp != md5(func_getIp()));
	}
}
?>