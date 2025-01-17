<?php
/*---------------------------------------------------------------------------
* @Module Name: Feedback
* @Description: Feedback for LiveStreet
* @Version: 2.0
* @Author: Chiffa
* @LiveStreet version: 1.X
* @File Name: PluginFeedback.class.php
* @License: CC BY-NC, http://creativecommons.org/licenses/by-nc/3.0/
*----------------------------------------------------------------------------
*/

if (!class_exists('Plugin')) {
	die('Hacking attemp!');
}

class PluginFeedback extends Plugin {

	/**
	 * Активация плагина
	 */
	public function Activate() {
		if (!$this->isTableExists('prefix_feedback_setting')) {
			$this->ExportSQL(dirname(__FILE__).'/sql/install.sql');
		}
		/**
		 * Устанавливаем по умолчанию мыло админа из конфига
		 */
		$aSet = array();
		$aSet['mail'][] = Config::Get('sys.mail.from_email');
		$this->PluginFeedback_Feedback_SetSettings($aSet);
		return true;
	}

	/**
	 * Деактивация плагина
	 */
	public function Deactivate() {
		if (Config::Get('plugin.feedback.deactivate.delete')) {
			$this->ExportSQL(dirname(__FILE__).'/sql/deinstall.sql');
		}
		return true;
	}

	/**
	 * Инициализация плагина
	 */
	public function Init() {
		/**
		 * Загружаем конфиг
		 */
		Config::Set('plugin.feedback',func_array_merge_assoc(Config::Get('plugin.feedback'), $this->PluginFeedback_Feedback_GetSettings()));
		/**
		 * Подключаем CSS
		 */
		$this->Viewer_AppendStyle(Plugin::GetTemplatePath(__CLASS__).'css/feedback.css');
		/**
		 * Подключаем JS
		 */
		$this->Viewer_AppendScript(Plugin::GetTemplatePath(__CLASS__).'js/feedback.js');
		/**
		 * Подключаем кнопку
		 */
		if (Config::Get('plugin.feedback.system.button')) {
			$this->Viewer_AddBlock('toolbar','toolbar_feedback.tpl',array('plugin'=>__CLASS__),-111);
		}
	}

}
?>