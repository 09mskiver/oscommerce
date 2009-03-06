<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2007 osCommerce

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v2 (1991)
  as published by the Free Software Foundation.
*/

/**
 * The osC_Session class manages the session data and custom storage handlers
 */

  class osC_Session {

/**
 * Holds the session cookie parameters (lifetime, path, domain, secure, httponly)
 *
 * @var array
 * @access protected
 */

    protected $_cookie_parameters = array();

/**
 * Defines if the session has been started or not
 *
 * @var boolean
 * @access protected
 */

    protected $_is_started = false;

/**
 * Holds the name of the session
 *
 * @var string
 * @access protected
 */

    protected $_name = 'osCsid';

/**
 * Holds the session id
 *
 * @var string
 * @access protected
 */

    protected $_id = null;

/**
 * Holds the file system save path for file based session storage
 *
 * @var string
 * @access protected
 */

    protected $_save_path = DIR_FS_WORK;

/**
 * Constructor, loads custom session handle module if defined
 *
 * @param string $name The name of the session
 * @access public
 */

    public function __construct($name = null) {
      $this->setName($name);
      $this->setCookieParameters();

      if ( SERVICE_SESSION_EXPIRATION_TIME > 0 ) {
        ini_set('session.gc_maxlifetime', SERVICE_SESSION_EXPIRATION_TIME * 60);
      }
    }

/**
 * Destructor, closes the session
 *
 * @access public
 */

    public function __destruct() {
      $this->close();
    }

/**
 * Loads the session storage handler
 *
 * @param string $name The name of the session
 * @access public
 */

    public static function load($name = null) {
      $class_name = 'osC_Session';

      if ( !osc_empty(basename(STORE_SESSIONS)) && file_exists(dirname(__FILE__) . '/session/' . basename(STORE_SESSIONS) . '.php') ) {
        include(dirname(__FILE__) . '/session/' . basename(STORE_SESSIONS) . '.php');

        $class_name = 'osC_Session_' . basename(STORE_SESSIONS);
      }

      return new $class_name($name);
    }

/**
 * Verify an existing session ID and create or resume the session if the existing session ID is valid
 *
 * @access public
 * @return boolean
 */

    public function start() {
      $sane_session_id = true;

      if ( isset($_GET[$this->_name]) && (empty($_GET[$this->_name]) || (ctype_alnum($_GET[$this->_name]) === false)) ) {
        $sane_session_id = false;
      } elseif ( isset($_POST[$this->_name]) && (empty($_POST[$this->_name]) || (ctype_alnum($_POST[$this->_name]) === false)) ) {
        $sane_session_id = false;
      } elseif ( isset($_COOKIE[$this->_name]) && (empty($_COOKIE[$this->_name]) || (ctype_alnum($_COOKIE[$this->_name]) === false)) ) {
        $sane_session_id = false;
      }

      if ( $sane_session_id === false ) {
        if ( isset($_COOKIE[$this->_name]) ) {
          setcookie($this->_name, '', time()-42000, $this->getCookieParameters('path'), $this->getCookieParameters('domain'));
        }

        osc_redirect(osc_href_link(FILENAME_DEFAULT, null, 'NONSSL', false));
      } elseif ( session_start() ) {
        $this->_is_started = true;
        $this->_id = session_id();

        return true;
      }

      return false;
    }

/**
 * Checks if the session has been started or not
 *
 * @access public
 * @return boolean
 */

    public function hasStarted() {
      return $this->_is_started;
    }

/**
 * Closes the session and writes the session data to the storage handler
 *
 * @access public
 */

    public function close() {
      if ( $this->_is_started === true ) {
        $this->_is_started = false;

        return session_write_close();
      }
    }

/**
 * Deletes an existing session
 *
 * @access public
 */

    public function destroy() {
      if ( $this->_is_started === true ) {
        if ( isset($_COOKIE[$this->_name]) ) {
          setcookie($this->_name, '', time()-42000, $this->getCookieParameters('path'), $this->getCookieParameters('domain'));
        }

        $this->delete();

        return session_destroy();
      }
    }

/**
 * Deletes an existing session from the storage handler
 *
 * @param string $id The ID of the session
 * @access public
 */

    public function delete($id = null) {
      if ( empty($id) ) {
        $id = $this->_id;
      }

      if ( file_exists($this->_save_path . '/' . $id) ) {
        @unlink($this->_save_path . '/' . $id);
      }
    }

/**
 * Delete an existing session and move the session data to a new session with a new session ID
 *
 * @access public
 */

    public function recreate() {
      if ( $this->_is_started === true ) {
        return session_regenerate_id(true);
      }
    }

/**
 * Return the session file based storage location
 *
 * @access public
 * @return string
 */

    public function getSavePath() {
      return $this->_save_path;
    }

/**
 * Return the session ID
 *
 * @access public
 * @return string
 */

    public function getID() {
      return $this->_id;
    }

/**
 * Return the name of the session
 *
 * @access public
 * @return string
 */

    public function getName() {
      return $this->_name;
    }

/**
 * Sets the name of the session
 *
 * @param string $name The name of the session
 * @access public
 */

    public function setName($name) {
      if ( empty($name) ) {
        $name = 'osCsid';
      }

      session_name($name);

      $this->_name = session_name();
    }

/**
 * Sets the storage location for the file based storage handler
 *
 * @param string $path The file path to store the session data in
 * @access public
 */

    public function setSavePath($path) {
      if ( substr($path, -1) == '/' ) {
        $path = substr($path, 0, -1);
      }

      session_save_path($path);

      $this->_save_path = session_save_path();
    }

/**
 * Sets the cookie parameters for the session (lifetime, path, domain, secure, httponly)
 *
 * @param integer $lifetime The amount of minutes to keep a cookie active for
 * @param string $path The web path of the online store to limit cookies to
 * @param string $domain The domain of the online store to limit cookies to
 * @param boolean $secure Only access cookies over a secure HTTPS connection
 * @param boolean $httponly Only access cookies over a HTTP protocol (disallows javascript access to cookies)
 * @access public
 */

    public function setCookieParameters($lifetime = null, $path = null, $domain = null, $secure = false, $httponly = false) {
      global $request_type;

      if ( !is_numeric($lifetime) ) {
        $lifetime = SERVICE_SESSION_EXPIRATION_TIME * 60;
      }

      if ( empty($path) ) {
        $path = (($request_type == 'NONSSL') ? HTTP_COOKIE_PATH : HTTPS_COOKIE_PATH);
      }

      if ( empty($domain) ) {
        $domain = (($request_type == 'NONSSL') ? HTTP_COOKIE_DOMAIN : HTTPS_COOKIE_DOMAIN);
      }

      return session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
    }

/**
 * Returns the cookie parameters for the session (lifetime, path, domain, secure, httponly)
 *
 * @param string $key If specified, return only the value of this cookie parameter setting
 * @access public
 */

    public function getCookieParameters($key = null) {
      if ( empty($this->_cookie_parameters) ) {
        $this->_cookie_parameters = session_get_cookie_params();
      }

      if ( !empty($key) ) {
        return $this->_cookie_parameters[$key];
      }

      return $this->_cookie_parameters;
    }
  }
?>
