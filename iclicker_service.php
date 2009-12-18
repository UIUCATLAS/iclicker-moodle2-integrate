<?php
/**
 * Copyright (c) 2009 i>clicker (R) <http://www.iclicker.com/dnn/>
 *
 * This file is part of i>clicker Moodle integrate.
 *
 * i>clicker Moodle integrate is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * i>clicker Moodle integrate is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with i>clicker Moodle integrate.  If not, see <http://www.gnu.org/licenses/>.
 */
/* $Id$ */

require_once (dirname(__FILE__).'/../../config.php');
global $CFG,$USER,$COURSE;
// link in external libraries
require_once ($CFG->libdir.'/gradelib.php');
require_once ($CFG->libdir.'/dmllib.php');
require_once ($CFG->libdir.'/accesslib.php');

/**
 * Defines an exception which can occur when validating clicker ids
 * Valid types are:
 * empty - the clickerId is null or empty string
 * length - the clickerId length is not 8 chars (too long), shorter clickerIds are padded out to 8
 * chars - the clickerId contains invalid characters
 * checksum - the clickerId did not validate using the checksum method
 * sample - the clickerId matches the sample one and cannot be used
 */
class ClickerIdInvalidException extends Exception {
    const F_EMPTY = 'EMPTY';
    const F_LENGTH = 'LENGTH';
    const F_CHARS = 'CHARS';
    const F_CHECKSUM = 'CHECKSUM';
    const F_SAMPLE = 'SAMPLE';
    public $type = "UNKNOWN";
    public $clicker_id = NULL;
    /**
     * @param string $message the error message
     * @param string $type [optional] Valid types are:
     * empty - the clickerId is null or empty string
     * length - the clickerId length is not 8 chars (too long), shorter clickerIds are padded out to 8
     * chars - the clickerId contains invalid characters
     * checksum - the clickerId did not validate using the checksum method
     * sample - the clickerId matches the sample one and cannot be used
     * @param string $clicker_id [optional] the clicker id
     */
    function __construct($message, $type = NULL, $clicker_id = NULL) {
        parent::__construct($message);
        $this->type = $type;
        $this->clicker_id = $clicker_id;
    }
    public function errorMessage() {
        $errorMsg = 'Error on line '.$this->getLine().' in '.$this->getFile().': '.$this->getMessage().' : type='.$this->type.' : clicker_id='.$this->clicker_id;
        return $errorMsg;
    }
}

class ClickerRegisteredException extends Exception {
    public $owner_id;
    public $clicker_id;
    public $registered_owner_id;
    function __construct($message, $owner_id, $clicker_id, $registered_owner_id) {
        parent::__construct($message);
        $this->owner_id = $owner_id;
        $this->clicker_id = $clicker_id;
        $this->registered_owner_id = $registered_owner_id;
    }
    public function errorMessage() {
        $errorMsg = 'Error on line '.$this->getLine().' in '.$this->getFile().': '.$this->getMessage().' : cannot register to '.$this->owner_id.', clicker already registered to owner='.$this->registered_owner_id.' : clicker_id='.$this->clicker_id;
        return $errorMsg;
    }
}

/**
 * This marks an exception as being related to an authn or authz failure
 */
class SecurityException extends Exception {}

/**
 * This holds all the service logic for the iclicker integrate plugin
 */
class iclicker_service {

    // CONSTANTS
    const BLOCK_NAME = 'block_iclicker';
    const BLOCK_PATH = '/blocks/iclicker';
    const REG_TABLENAME = 'iclicker_registration';
    const REG_ORDER = 'timemodified desc';
    const DEFAULT_SYNC_HOUR = 3;
    const DEFAULT_SERVER_URL = "http://moodle.org/"; // "http://epicurus.learningmate.com/";
    const NATIONAL_WS_URL = "https://webservices.iclicker.com/iclicker_gbsync_registrations/service.asmx";
    /*
     * iclicker_gbsync_reg / #8d7608e1e7f4@
     * 'Basic ' + base64(username + ":" + password)
     */
    const NATIONAL_WS_BASIC_AUTH_HEADER = "Basic aWNsaWNrZXJfZ2JzeW5jX3JlZzojOGQ3NjA4ZTFlN2Y0QA==";

    // CLASS VARIABLES

    // CONFIG
    var $server_id = "UNKNOWN_SERVER_ID";
    var $server_URL = self::DEFAULT_SERVER_URL;
    var $domain_URL = self::DEFAULT_SERVER_URL;
    var $use_national_webservices = false;
    var $webservices_URL = self::NATIONAL_WS_URL;
    var $webservices_use_basic_auth = true;
    var $webservices_basic_auth_header = self::NATIONAL_WS_BASIC_AUTH_HEADER;
    var $disable_sync_with_national = false;
    var $webservices_national_sync_hour = self::DEFAULT_SYNC_HOUR;
    var $notify_emails_string = NULL;
    var $notify_emails = array();

    // STATIC METHODS

    /**
     * @return the path for this block
     */
    static function block_path($added=NULL) {
        global $CFG;
        if (isset($added)) {
            $added = '/'.$added;
        } else {
            $added = '';
        }
        return $CFG->dirroot.self::BLOCK_PATH.$added;
    }

    /**
     * @return the url for this block
     */
    static function block_url($added=NULL) {
        global $CFG;
        if (isset($added)) {
            $added = '/'.$added;
        } else {
            $added = '';
        }
        return $CFG->wwwroot.self::BLOCK_PATH.$added;
    }

    /**
     * i18n message handling
     *
     * @param string $key i18 msg key
     * @param object $vars [optional] optional replacement variables
     * @return the translated string
     */
    static function msg($key, $vars=NULL) {
        return get_string($key, self::BLOCK_NAME, $vars);
    }

    // USERS

    /**
     * 
     * @param string $username
     * @param string $password
     * @return true if the authentication is successful
     */
    static function authenticate_user($username, $password) {
        global $USER;
        // @todo make this do a real authn check
        if (! isset($USER->id)) {
            $USER->id = 1; // FIXME
            $USER->username = $username;
            $USER->secret = $password;
            if (! isset($USER->id)) {
                throw new SecurityException('Could not authenticate username ('.$username.')');
            }
        }
        return true;
    }

    /**
     * Ensure user is logged in and return the current user id
     * @return the current user id
     * @throws SecurityException if there is no current user
     * @static
     */
    static function require_user() {
        global $USER;
        if (! isset($USER->id)) {
            throw new SecurityException('User must be logged in');
        }
        return $USER->id;
    }

    /**
     * @return the current user id OR null/false if no user
     */
    static function get_current_user_id() {
        $current_user = null;
        try {
            $current_user = iclicker_service::require_user();
        } catch (SecurityException $e) {
            $current_user = false;
        }
        return $current_user;
    }

    /**
     * Get user records for a set of user ids
     * @param array $user_ids and array of user ids
     * @return a map of user_id -> user data
     */
    static function get_users($user_ids) {
        // @todo make this do something
        $results = array();
        foreach ($user_ids as $user_id) {
            $results[$user_id] = array('id'=>$user_id);
        }
        return $results;
    }

    /**
     * @param int $user_id [optional] the user id
     * @return true if this user is an admin OR false if not
     * @static
     */
    static function is_admin($user_id = NULL) {
        if (! isset($user_id)) {
            try {
                $user_id = self::require_user();
            } catch (SecurityException $e) {
                return false;
            }
        }
        $result = is_siteadmin($user_id);
        return $result;
    }

    const CLICKERID_SAMPLE = '11A4C277';
    /**
     * Cleans up and validates a given clicker_id
     * @param clicker_id a remote clicker ID
     * @return the cleaned up and valid clicker ID
     * @throws ClickerIdInvalidException if the id is invalid for some reason,
     * the exception will indicate the type of validation failure
     * @static
     */
    static function validate_clicker_id($clicker_id) {
        if (! $clicker_id) {
            throw new ClickerIdInvalidException("empty or NULL clicker_id", ClickerIdInvalidException::F_EMPTY, $clicker_id);
        }
        if (strlen($clicker_id) > 8) {
            throw new ClickerIdInvalidException("clicker_id is an invalid length", ClickerIdInvalidException::F_LENGTH, $clicker_id);
        }
        $clicker_id = strtoupper(trim($clicker_id));
        if (! preg_match('/^[0-9A-F]+$/', $clicker_id) ) {
            throw new ClickerIdInvalidException("clicker_id can only contains A-F and 0-9", ClickerIdInvalidException::F_CHARS, $clicker_id);
        }
        while (strlen($clicker_id) < 8) {
            $clicker_id = "0".$clicker_id;
        }
        if (self::CLICKERID_SAMPLE == $clicker_id) {
            throw new ClickerIdInvalidException("clicker_id cannot match the sample ID", ClickerIdInvalidException::F_SAMPLE, $clicker_id);
        }
        $idArray = array();
        $idArray[0] = substr($clicker_id, 0, 2);
        $idArray[1] = substr($clicker_id, 2, 2);
        $idArray[2] = substr($clicker_id, 4, 2);
        $idArray[3] = substr($clicker_id, 6, 2);
        $checksum = 0;
        foreach ($idArray as $piece) {
            $hex = hexdec($piece);
            $checksum = $checksum ^ $hex;
        }
        if ($checksum != 0) {
            throw new ClickerIdInvalidException("clicker_id checksum ("+$checksum+") validation failed", ClickerIdInvalidException::F_CHECKSUM, $clicker_id);
        }
        return $clicker_id;
    }

    // CLICKER REGISTRATIONS DATA

    /**
     * @param int $id the registration ID
     * @return the registration object OR false if none found
     * @static
     */
    static function get_registration_by_id($reg_id) {
        if (! isset($reg_id)) {
            throw new InvalidArgumentException("reg_id must be set");
        }
        $result = get_record(self::REG_TABLENAME,'id',$reg_id);
        return $result;
    }

    /**
     * @param string $clicker_id the clicker id
     * @param int $user_id [optional] the user who registered the clicker (id)
     * @return the registration object OR false if none found
     * @static
     */
    static function get_registration_by_clicker_id($clicker_id, $user_id = NULL) {
        if (!$clicker_id) {
            throw new InvalidArgumentException("clicker_id must be set");
        }
        $current_user_id = self::require_user();
        if (!isset($user_id)) {
            $user_id = $current_user_id;
        }
        try {
            $clicker_id = self::validate_clicker_id($clicker_id);
        } catch (ClickerIdInvalidException $e) {
            return false;
        }
        $result = get_record(self::REG_TABLENAME, 'clicker_id', $clicker_id, 'owner_id', $user_id);
        if ($result) {
            if (!self::can_read_registration($result, $current_user_id)) {
                throw new SecurityException("User ($current_user_id) not allowed to access registration ($result->id)");
            }
        }
        return $result;
    }

    static function can_read_registration($clicker_registration, $user_id) {
        if (! isset($clicker_registration)) {
            throw new InvalidArgumentException("clicker_registration must be set");
        }
        if (! isset($user_id)) {
            throw new InvalidArgumentException("user_id must be set");
        }
        $result = false;
        if ($clicker_registration->owner_id == $user_id) {
            $result = true;
        }
        // @todo make this do a real check
        $result = true;
        return $result;
    }

    static function can_write_registration($clicker_registration, $user_id) {
        if (! isset($clicker_registration)) {
            throw new InvalidArgumentException("clicker_registration must be set");
        }
        if (! isset($user_id)) {
            throw new InvalidArgumentException("user_id must be set");
        }
        $result = false;
        if ($clicker_registration->owner_id == $user_id) {
            $result = true;
        }
        // @todo make this do a real check
        $result = true;
        return $result;
    }

    /**
     * @param int $user_id [optional] the user id OR current user id
     * @param boolean $activated if null or not set then return all,
     * if true then return active only, if false then return inactive only
     * @return the list of registrations for this user or empty array if none
     */
    static function get_registrations_by_user($user_id=NULL, $activated=NULL) {
        $current_user_id = self::require_user();
        if (! isset($user_id)) {
            $user_id = $current_user_id;
        }
        $sql = "owner_id = '".addslashes($user_id)."'";
        if (isset($activated)) {
            $sql .= ' and activated = '. ($activated ? 1 : 0);
        }
        $results = get_records_select(self::REG_TABLENAME, $sql, self::REG_ORDER);
        if (! $results) {
            $results = array();
        }
        return $results;
    }

    /**
     * ADMIN ONLY
     * This is a method to get all the clickers for the clicker admin view
     * @param int $start [optional] start value for paging
     * @param int $max [optional] max value for paging
     * @param string $order [optional] the order by string
     * @param string $search [optional] search string for clickers
     * @return array of clicker registrations
     */
    static function get_all_registrations($start=0, $max=0, $order='clicker_id', $search='') {
        if (! self::is_admin()) {
            throw new SecurityException("Only admins can use this function");
        }
        if ($max <= 0) {
            $max = 10;
        }
        $query = '';
        if ($search) {
            // build a search query
            $query = 'clicker_id '.sql_ilike().' '.addslashes($search).'%';
        }
        $results = get_records_select(self::REG_TABLENAME, $query, $order, '*', $start, $max);
        if (! $results) {
            $results = array();
        } else {
            // @todo insert user display names
        }
        return $results;
    }

    /**
     * @return the count of the total number of registered clickers
     */
    static function count_all_registrations() {
        return count_records(self::REG_TABLENAME);
    }

    /**
     * ADMIN ONLY
     * Removes the registration from the database
     *
     * @param int $reg_id id of the clicker registration
     * @return true if removed OR false if not found or not removed
     */
    static function remove_registration($reg_id) {
        if (! self::is_admin()) {
            throw new SecurityException("Only admins can use this function");
        }
        if (isset($reg_id)) {
            if (delete_records(self::REG_TABLENAME, 'id', $reg_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create a registration
     *
     * @param string $clicker_id the clickerID (e.g. 11111111)
     * @param string $owner_id [optional] the user_id OR current user if not set
     * @param boolean $local_only [optional] create this clicker in the local system only if true, otherwise sync to national system as well
     * @return the clicker_registration object
     */
    static function create_clicker_registration($clicker_id, $owner_id = NULL, $local_only = false) {
        $clicker_id = self::validate_clicker_id($clicker_id);
        $current_user_id = self::require_user();
        $user_id = $owner_id;
        if (! isset($owner_id)) {
            $user_id = $current_user_id;
        }
        $registration = self::get_registration_by_clicker_id($clicker_id, $user_id);
        // NOTE: we probably want to check the national system here to see if this is already registered
        if ($registration) {
            throw new ClickerRegisteredException($user_id, $registration->clicker_id, $registration->owner_id);
        } else {
            $clicker_registration = new stdClass;
            $clicker_registration->clicker_id = $clicker_id;
            $clicker_registration->owner_id = $user_id;
            $reg_id = self::save_registration($clicker_registration);
            $registration = self::get_registration_by_id($reg_id);
            if ($local_only) {
                // @todo syncClickerRegistrationWithNational(registration);
            }
        }
        return $registration;
    }

    /**
     * Make a registration active or inactive
     *
     * @param int $reg_id id of the clicker registration
     * @param boolean $activated true to enable, false to disable
     * @return the clicker_registration object
     */
    static function set_registration_active($reg_id, $activated) {
        if (! isset($reg_id)) {
            throw new InvalidArgumentException("reg_id must be set");
        }
        if (! isset($activated)) {
            throw new InvalidArgumentException("active must be set");
        }
        $current_user_id = self::require_user();
        $registration = self::get_registration_by_id($reg_id);
        if (! $registration) {
            throw new InvalidArgumentException("Could not find registration with id ($reg_id)");
        }
        $registration->activated = $activated ? 1 : 0;
        self::save_registration($registration);
        return $registration;
    }

    /**
     * Saves the clicker registration data (create or update)
     * @param object $clicker_registration the registration data as an object
     * @return the id of the saved registration
     */
    static function save_registration(&$clicker_registration) {
        if (! $clicker_registration || ! isset($clicker_registration->clicker_id)) {
            throw new InvalidArgumentException("clicker_registration cannot be empty and clicker_id must be set");
        }
        $clicker_registration->clicker_id = self::validate_clicker_id($clicker_registration->clicker_id);
        $current_user_id = self::require_user();
        // set the owner to current if not set
        if (! isset($clicker_registration->owner_id)) {
            $clicker_registration->owner_id = $current_user_id;
        } else {
            // check for valid user id
            // @todo
        }
        $clicker_registration->timemodified = time();
        $reg_id = -1;
        if (! isset($clicker_registration->id)) {
            // new item to save (no perms check)
            $clicker_registration->timecreated = time();
            if (!$reg_id = insert_record(self::REG_TABLENAME, $clicker_registration, true)) {
                print_object($clicker_registration);
                error(self::msg('inserterror'));
            }
        } else {
            // updating existing item
            if (self::can_write_registration($clicker_registration, $current_user_id)) {
                if (!update_record(self::REG_TABLENAME, $clicker_registration)) {
                    print_object($clicker_registration);
                    error(self::msg('updateerror'));
                }
                $reg_id = $clicker_registration->id;
            } else {
                throw new SecurityException("Current user cannot update item ($clicker_registration->id) because they do not have permission");
            }
        }
        return $reg_id;
    }

    // COURSES METHODS

    static function get_students_for_course_with_regs($course_id) {
        // FIXME
        return array();
    }

    static function get_courses_for_instructor($course_id=NULL) {
        // FIXME
        return array();
    }

    static function get_course($course_id) {
        // FIXME
        return array();
    }

    static function get_course_grade_item($course_id, $grade_item_id) {
        // FIXME
        return array();
    }

    static function save_grade_item($grade_item) {
        // FIXME
        return array();
    }

    // NATIONAL WEBSERVICES

    static function ws_sync_clicker($clicker_registration) {
        // FIXME
        return array();
    }

    static function ws_get_students() {
        // FIXME
        return array();
    }

    static function ws_get_student($user_name) {
        // FIXME
        return array();
    }

    static function ws_save_clicker($user_name) {
        // FIXME
        return array();
    }

    // DATA ENCODING METHODS

    static function encode_registration($clicker_registration) {
        // FIXME
        return '<xml/>';
    }

    static function encode_registration_result($registrations, $status, $message) {
        // FIXME
        return '<xml/>';
    }

    static function encode_courses($instructor_id, $courses) {
        // FIXME
        return '<xml/>';
    }

    static function encode_enrollments($course_id) {
        // FIXME
        return '<xml/>';
    }

    static function encode_gradebook_result($course_id, $grade_items) {
        // FIXME
        return '<xml/>';
    }

    static function decode_registration($xml) {
        // FIXME
        return $clicker_registration;
    }

    static function decode_gradebook($xml) {
        // FIXME
        return array();
    }

    static function decode_ws_xml($xml) {
        // FIXME
        return array(); // $clicker_registration
    }

}
?>
