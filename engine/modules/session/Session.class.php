<?php
/*-------------------------------------------------------
*
*   LiveStreet Engine Social Networking
*   Copyright © 2008 Mzhelskiy Maxim
*
*--------------------------------------------------------
*
*   Official site: www.livestreet.ru
*   Contact e-mail: rus.engine@gmail.com
*
*   GNU General Public License, version 2:
*   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*
---------------------------------------------------------
*/

/**
 * Модуль для работы с сессиями
 * Выступает в качестве врапера для стандартного механизма сессий
 *
 * @package engine.modules
 * @since 1.0
 */
class ModuleSession extends Module
{
    /**
     * Список user-agent'ов для флеш плеера
     * Используется для передачи ID сессии при обращениии к сайту через flash, например, загрузка файлов через flash
     *
     * @var array
     */
    protected $aFlashUserAgent = array(
        'Shockwave Flash'
    );
    
    /**
     * Инициализация модуля
     *
     */
    public function Init()
    {
        /**
         * Стартуем сессию
         */
        $this->Start();
    }
    
    /**
     * Старт сессии
     *
     */
    protected function Start()
    {
        session_name(Config::Get('sys.session.name'));
        if (PHP_VERSION_ID < 70300) {
            session_set_cookie_params(
                Config::Get('sys.session.timeout'),
                Config::Get('sys.session.path') . '; sameSite=' . Config::Get('sys.cookie.sameSite'),
                Config::Get('sys.session.host'),
                Config::Get('sys.session.secure'),
                Config::Get('sys.session.httponly')
            );
        } else {
            session_set_cookie_params(
                [
                    'lifetime' => Config::Get('sys.session.timeout'),
                    'path' => Config::Get('sys.cookie.path'),
                    'domain' => Config::Get('sys.cookie.host'),
                    'sameSite' => Config::Get('sys.cookie.sameSite'),
                    'secure' => Config::Get('sys.session.secure'),
                    'httponly' => Config::Get('sys.session.httponly'),
                ]
            );
        }
        if (!session_id()) {
            /**
             * Попытка подменить идентификатор имени сессии через куку
             */
            if (isset($_COOKIE[Config::Get('sys.session.name')])) {
                if (!is_string($_COOKIE[Config::Get('sys.session.name')])) {
                    die("Hacking attempt! Please check cookie PHP session name.");
                }
                if (!preg_match('#^[a-z0-9,-]{1,40}$#i', $_COOKIE[Config::Get('sys.session.name')])) {
                    die("Hacking attempt! Please check cookie PHP session id.");
                }
            }
            /**
             * Попытка подменить идентификатор имени сессии в реквесте
             */
            $aRequest = array_merge($_GET, $_POST); // Исключаем попадаение $_COOKIE в реквест
            if (@ini_get('session.use_only_cookies') === "0" and isset($aRequest[Config::Get('sys.session.name')]) and !is_string($aRequest[Config::Get('sys.session.name')])) {
                die("Hacking attempt! Please check cookie PHP session name.");
            }
            /**
             * Даем возможность флешу задавать id сессии
             */
            $sUserAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
            if ($sUserAgent and (in_array($sUserAgent, $this->aFlashUserAgent) or strpos($sUserAgent,
                        "Adobe Flash Player") === 0) and is_string(getRequest('SSID')) and preg_match("/^[\w\d]{5,40}$/",
                    getRequest('SSID'))
            ) {
                session_id(getRequest('SSID'));
            } else {
                if (session_status() == PHP_SESSION_ACTIVE) {
                    session_regenerate_id();
                }
            }
            
            @session_start();
        }
    }
    
    /**
     * Получает идентификатор текущей сессии
     *
     */
    public function GetId()
    {
        return session_id();
    }
    
    /**
     * Гинерирует уникальный идентификатор
     *
     * @return string
     */
    protected function GenerateId()
    {
        return md5(func_generator() . time());
    }
    
    /**
     * Получает значение из сессии
     *
     * @param string $sName Имя параметра
     * @return mixed|null
     */
    public function Get($sName)
    {
        return isset($_SESSION[$sName]) ? $_SESSION[$sName] : null;
    }
    
    /**
     * Записывает значение в сессию
     *
     * @param string $sName Имя параметра
     * @param mixed $data Данные
     */
    public function Set($sName, $data)
    {
        $_SESSION[$sName] = $data;
    }
    
    /**
     * Удаляет значение из сессии
     *
     * @param string $sName Имя параметра
     */
    public function Drop($sName)
    {
        unset($_SESSION[$sName]);
    }
    
    /**
     * Получает разом все данные сессии
     *
     * @return array
     */
    public function GetData()
    {
        return $_SESSION;
    }
    
    /**
     * Завершает сессию, дропая все данные
     *
     */
    public function DropSession()
    {
        unset($_SESSION);
        session_destroy();
    }
    
    /**
     * Устанавливает куку
     *
     * @param string $sName
     * @param string $sValue
     * @param int|null $iTime
     * @param bool $bSecure
     * @param bool $bHttpOnly
     */
    public function SetCookie($sName, $sValue, $iTime = null, $bSecure = false, $bHttpOnly = false)
    {
        $_COOKIE[$sName] = $sValue;
        if (PHP_VERSION_ID < 70300) {
            setcookie($sName, $sValue, $iTime, Config::Get('sys.cookie.path') . '; sameSite=' . Config::Get('sys.cookie.sameSite'), Config::Get('sys.cookie.host'), $bSecure, $bHttpOnly);
        } else {
            setcookie($sName, $sValue, [
                'expires' => $iTime,
                'path' => Config::Get('sys.cookie.path'),
                'domain' => Config::Get('sys.cookie.host'),
                'sameSite' => Config::Get('sys.cookie.sameSite'),
                'secure' => $bSecure,
                'httponly' => $bHttpOnly,
            ]);
        }
    }
    
    /**
     * Читает куку
     *
     * @param string $sName
     * @param mixed $mDefault
     * @return string|mixed
     */
    public function GetCookie($sName, $mDefault = null)
    {
        if (isset($_COOKIE[$sName])) {
            return $_COOKIE[$sName];
        }
        return $mDefault;
    }
    
    /**
     * Удаляет куку
     *
     * @param $sName
     */
    public function DropCookie($sName)
    {
        setcookie($sName, null, -1, Config::Get('sys.cookie.path'), Config::Get('sys.cookie.host'));
        unset($_COOKIE[$sName]);
    }
}
?>