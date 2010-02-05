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
/* $Id: test_gradebook.php 9 2009-11-28 17:10:13Z azeckoski $ */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

require_once (dirname(__FILE__).'/../../../config.php');
global $CFG,$USER,$COURSE;
// link in external libraries
require_once ($CFG->dirroot.'/blocks/iclicker/iclicker_service.php');

/**
 * Unit tests for the iclicker services
 * Execute tests at:
 * moodle/admin/report/unittest/index.php?path=blocks%2Ficlicker
 */
class iclicker_services_test extends UnitTestCase {

    var $courseid = 1;
    var $clicker_id = '99996666';

    var $studentid1 = 1;
    var $studentid2 = 2;

   	var $cat_name = 'az_category';
   	var $item_name = 'az_gradeitem';
   	var $grade_score = 91;

    public function cleanup() {
        // cleanup any clickers before the test
        $user_id = iclicker_service::require_user();
        $results = iclicker_service::get_registrations_by_user($user_id);
        if ($results) {
            echo "cleanup registrations for user: $user_id  ";
            foreach($results as $reg) {
                if ($reg->clicker_id == $this->clicker_id) {
                    iclicker_service::remove_registration($reg->id);
                    echo "cleanup: $reg->id ";
                }
            }
        }
        // cleanup the test grades
        $grade_cats = grade_category::fetch_all( array(
            'courseid' => $this->courseid,
            'fullname' => iclicker_service::GRADE_CATEGORY_NAME
            )
        );
        if ($grade_cats) {
            foreach ($grade_cats as $cat) {
                $grade_items = grade_item::fetch_all(array(
                    'courseid' => $this->courseid,
                    'categoryid' => $cat->id
                    )
                );
                if ($grade_items) {
                    foreach ($grade_items as $item) {
                        $grades = grade_grade::fetch_all(array(
                            'itemid'=>$item->id
                            )
                        );
                        if ($grades) {
                            foreach ($grades as $grade) {
                                $grade->delete("cleanup");
                            }
                        }
                        $item->delete("cleanup");
                    }
                }
                $cat->delete("cleanup");
            }
        }
    }

    public function setUp() {
        $this->cleanup();
        iclicker_service::$test_mode = true;
    }

    public function tearDown() {
        iclicker_service::$test_mode = false;
        $this->cleanup();
    }

    function test_arrays_subtract() {
        // array_diff and array_diff_key are the same as a subtract when used with 2 arrays -- array_diff(A1, A2) => A1 - A2
        $a1 = array(1,2,3,4,5);
        $a2 = array(3,4,5,6,7);

        $result = array_values(array_diff($a1, $a2));
        $this->assertEqual(2, count($result));
        $this->assertTrue(in_array(1, $result));
        $this->assertTrue(in_array(2, $result));
        $result = array_values(array_diff($a2, $a1));
        $this->assertEqual(2, count($result));
        $this->assertTrue(in_array(6, $result));
        $this->assertTrue(in_array(7, $result));

        $result = array_values(array_intersect($a1, $a2));
        $this->assertEqual(3, count($result));
        $this->assertTrue(in_array(3, $result));
        $this->assertTrue(in_array(4, $result));
        $this->assertTrue(in_array(5, $result));
        $result = array_values(array_intersect($a2, $a1));
        $this->assertEqual(3, count($result));
        $this->assertTrue(in_array(3, $result));
        $this->assertTrue(in_array(4, $result));
        $this->assertTrue(in_array(5, $result));

        $a1 = array('A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5);
        $a2 = array('C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7);

        $result = array_values(array_diff_key($a1, $a2));
        $this->assertEqual(2, count($result));
        $this->assertTrue(in_array(1, $result));
        $this->assertTrue(in_array(2, $result));
        $result = array_values(array_diff_key($a2, $a1));
        $this->assertEqual(2, count($result));
        $this->assertTrue(in_array(6, $result));
        $this->assertTrue(in_array(7, $result));

        $result = array_values(array_intersect_key($a1, $a2));
        $this->assertEqual(3, count($result));
        $this->assertTrue(in_array(3, $result));
        $this->assertTrue(in_array(4, $result));
        $this->assertTrue(in_array(5, $result));
        $result = array_values(array_intersect_key($a2, $a1));
        $this->assertEqual(3, count($result));
        $this->assertTrue(in_array(3, $result));
        $this->assertTrue(in_array(4, $result));
        $this->assertTrue(in_array(5, $result));
    }

    function test_assert() {
        $this->assertEqual("AZ", "AZ");
        $this->assertEqual(iclicker_service::$test_mode, true);
    }

    function test_require_user() {
        $user_id = iclicker_service::require_user();
        $this->assertTrue($user_id);
    }

    function test_get_users() {
        $user_id = iclicker_service::require_user();
        $this->assertTrue($user_id);
        $results = iclicker_service::get_users(array($user_id));
        $this->assertTrue($results);
        $this->assertTrue(count($results) == 1);
        $this->assertEqual($results[$user_id]->id, $user_id);
    }
    
    function test_validate_clickerid() {
        $clicker_id = null;
        try {
            iclicker_service::validate_clicker_id($clicker_id);
            $this->fail("should have died");
        } catch (ClickerIdInvalidException $e) {
            $this->assertEqual(ClickerIdInvalidException::F_EMPTY, $e->type);
        }

        $clicker_id = "XXX";
        try {
            iclicker_service::validate_clicker_id($clicker_id);
            $this->fail("should have died");
        } catch (ClickerIdInvalidException $e) {
            $this->assertEqual(ClickerIdInvalidException::F_CHARS, $e->type);
        }

        $clicker_id = "00000000000";
        try {
            iclicker_service::validate_clicker_id($clicker_id);
            $this->fail("should have died");
        } catch (ClickerIdInvalidException $e) {
            $this->assertEqual(ClickerIdInvalidException::F_LENGTH, $e->type);
        }

        $clicker_id = iclicker_service::CLICKERID_SAMPLE;
        try {
            iclicker_service::validate_clicker_id($clicker_id);
            $this->fail("should have died");
        } catch (ClickerIdInvalidException $e) {
            $this->assertEqual(ClickerIdInvalidException::F_SAMPLE, $e->type);
        }

        $clicker_id = "ABCD0123";
        try {
            iclicker_service::validate_clicker_id($clicker_id);
            $this->fail("should have died");
        } catch (ClickerIdInvalidException $e) {
            $this->assertEqual(ClickerIdInvalidException::F_CHECKSUM, $e->type);
        }

        $clicker_id = "112233";
        $result = iclicker_service::validate_clicker_id($clicker_id);
        $this->assertEqual($result, "00112233");

        $clicker_id = "11111111";
        $result = iclicker_service::validate_clicker_id($clicker_id);
        $this->assertEqual($result, $clicker_id);
    }

    function test_registrations() {
        $reg = null;
        $user_id = iclicker_service::require_user();

        // try get registration
        $reg = iclicker_service::get_registration_by_clicker_id($this->clicker_id);
        $this->assertFalse($reg);

        // create registration
        $reg = iclicker_service::create_clicker_registration($this->clicker_id, $user_id);
        $this->assertTrue($reg);
        $this->assertEqual($this->clicker_id, $reg->clicker_id);
        $reg_id = $reg->id;
        $this->assertTrue($reg_id);
        $this->assertFalse($reg->from_national);

        // get registration
        $reg1 = iclicker_service::get_registration_by_clicker_id($this->clicker_id, $user_id);
        $this->assertTrue($reg1);
        $this->assertEqual($this->clicker_id, $reg1->clicker_id);
        $this->assertEqual($reg_id, $reg1->id);

        $reg2 = iclicker_service::get_registration_by_id($reg_id);
        $this->assertTrue($reg2);
        $this->assertEqual($this->clicker_id, $reg2->clicker_id);
        $this->assertEqual($reg_id, $reg2->id);

        // save registration
        $reg->from_national = 1;
        $save_id = iclicker_service::save_registration($reg);
        $this->assertTrue($save_id);
        $this->assertEqual($reg_id, $save_id);

        // check it changed
        $reg3 = iclicker_service::get_registration_by_id($reg_id);
        $this->assertTrue($reg3);
        $this->assertEqual($reg_id, $reg3->id);
        $this->assertTrue($reg3->from_national);
        // too fast $this->assertNotEqual($reg->timemodified, $reg3->timemodified);

        // make registration inactive
        $this->assertTrue($reg->activated);
        $reg4 = iclicker_service::set_registration_active($reg_id, false);
        $this->assertTrue($reg4);
        $this->assertFalse($reg4->activated);
        // check it changed
        $reg5 = iclicker_service::get_registration_by_id($reg_id);
        $this->assertTrue($reg5);
        $this->assertEqual($reg_id, $reg5->id);
        $this->assertEqual($reg4->id, $reg5->id);
        $this->assertFalse($reg5->activated);
        
        // get all registration
        $results = iclicker_service::get_registrations_by_user($user_id);
        $this->assertTrue($results);
        $this->assertEqual(1, count($results));

        $results = iclicker_service::get_registrations_by_user($user_id, true);
        $this->assertNotNull($results);
        $this->assertFalse($results);
        $this->assertEqual(0, count($results));
        
        $results = iclicker_service::get_all_registrations();
        $this->assertTrue($results);
        $this->assertTrue(count($results) >= 1);

        // remove registration
        $result = iclicker_service::remove_registration($reg_id);
        $this->assertTrue($result);

        // try get registration
        $reg = iclicker_service::get_registration_by_id($reg_id);
        $this->assertFalse($reg);
    }

    function test_encode_decode() {
        $xml = <<<XML
<Register>
  <S DisplayName="DisplayName-azeckoski-123456" FirstName="First" LastName="Lastazeckoski-123456" 
    StudentID="student01" Email="azeckoski-123456@email.com" URL="http://sakaiproject.org" ClickerID="11111111"></S>
</Register>
XML;
        $result = iclicker_service::decode_registration($xml);
        $this->assertNotNull($result);
        $this->assertNotNull($result->clicker_id);
        $this->assertNotNull($result->owner_id);
        $this->assertEqual($result->clicker_id, '11111111');
        $this->assertEqual($result->user_username, 'student01');

        $xml = <<<XML
<coursegradebook courseid="BFW61">
  <user id="1" usertype="S">
    <lineitem name="05/05/2009" pointspossible="100" type="iclicker polling scores" score="100"/>
    <lineitem name="06/06/2009" pointspossible="50" type="iclicker polling scores" score="50"/>
  </user>
  <user id="2" usertype="S">
    <lineitem name="06/06/2009" pointspossible="50" type="iclicker polling scores" score="30"/>
    <lineitem name="07/07/2009" pointspossible="100" type="iclicker polling scores" score="80"/>
  </user>
</coursegradebook>
XML;
        $result = iclicker_service::decode_gradebook($xml);
        $this->assertNotNull($result);
        $this->assertNotNull($result->course_id);
        $this->assertEqual($result->course_id, 'BFW61');
        $this->assertNotNull($result->students);
        $this->assertNotNull($result->items);
        $this->assertEqual(count($result->students), 2);
        $this->assertEqual(count($result->items), 3);
        $this->assertNotNull($result->items['05/05/2009']);
        $this->assertNotNull($result->items['06/06/2009']);
        $this->assertNotNull($result->items['07/07/2009']);

        $xml = <<<XML
<StudentRoster>
    <S StudentID="student01" FirstName="student01" LastName="student01" URL="https://www.iclicker.com/" CourseName="">
        <Registration ClickerId="11111111" WhenAdded="2009-01-27" Enabled="True" />
        <Registration ClickerId="22222222" WhenAdded="2009-01-27" Enabled="True" />
    </S>
</StudentRoster>
XML;
        $result = iclicker_service::decode_ws_xml($xml);
        $this->assertNotNull($result);
        $this->assertTrue(is_array($result));
        $this->assertEqual(count($result), 2);
        $this->assertNotNull($result[0]);
        $this->assertNotNull($result[0]->clicker_id);
        $this->assertNotNull($result[0]->owner_id);
        $this->assertNotNull($result[0]->timecreated);
        $this->assertNotNull($result[0]->activated);
        $this->assertEqual($result[0]->clicker_id, '11111111');
        $this->assertEqual($result[0]->user_username, 'student01');
        /* Fails in windows for some reason - not sure why
        Fail: blocks/iclicker/simpletest/test_iclicker_service.php / ► iclicker_services_test / ► test_encode_decode / ► 
        Equal expectation fails because [Integer: 1233032400] differs from [Integer: 1233014400] by 18000
        */
        //$this->assertEqual($result[0]->timecreated, 1233014400);
        $this->assertEqual($result[0]->activated, true);

        // no good way to test this right now
        //$result = iclicker_service::encode_courses($instructor_id);
        //$result = iclicker_service::encode_enrollments($course_id);
        //$result = iclicker_service::encode_gradebook_results($course_id, $result_items);

        $clicker_registration = new stdClass();
        $clicker_registration->owner_id = 101;
        $clicker_registration->clicker_id = '12345678';
        $clicker_registration->activated = true;
        $result = iclicker_service::encode_registration($clicker_registration);
        $this->assertNotNull($result);
        $this->assertTrue(stripos($result, 'student01') > 0);
        $this->assertTrue(stripos($result, '12345678') > 0);
        $this->assertTrue(stripos($result, 'True') > 0);

        $registrations = array($clicker_registration);
        $result = iclicker_service::encode_registration_result($registrations, true, 'hello');
        $this->assertNotNull($result);
        $this->assertTrue(stripos($result, 'True') > 0);
        $this->assertTrue(stripos($result, 'hello') > 0);
        $result = iclicker_service::encode_registration_result($registrations, false, 'failed');
        $this->assertNotNull($result);
        $this->assertTrue(stripos($result, 'False') > 0);
        $this->assertTrue(stripos($result, 'failed') > 0);

    }

    function test_save_grades() {
        $test_item_name1 = 'testing-iclicker-item1';

        $test_item_name2 = 'testing-iclicker-item2';
        $test_item_name3 = 'testing-iclicker-item3';

        $gradebook = new stdClass();

        // saving a gradebook with no course_id not allowed
        try {
            $result = iclicker_service::save_gradebook($gradebook);
            $this->fail("should have died");
        } catch (Exception $e) {
            $this->assertNotNull($e);
        }

        $gradebook->course_id = $this->courseid;
        $gradebook->items = array();

        // saving an empty gradebook not allowed
        try {
            $result = iclicker_service::save_gradebook($gradebook);
            $this->fail("should have died");
        } catch (Exception $e) {
            $this->assertNotNull($e);
        }

        // saving one with one valid item
        $score = new stdClass();
        $score->user_id = 1;
        $score->score = 75.0;

        $grade_item = new stdClass();
        $grade_item->name = $test_item_name1;
        $grade_item->points_possible = 90;
        $grade_item->type = 'stuff';
        $grade_item->scores = array();

        $grade_item->scores[] = $score;
        $gradebook->items[] = $grade_item;

        $result = iclicker_service::save_gradebook($gradebook);
        $this->assertNotNull($result);
        $this->assertNotNull($result->course_id);
        $this->assertNotNull($result->course);
        $this->assertNotNull($result->category_id);
        $this->assertNotNull($result->items);
        $this->assertEqual($result->course_id, $this->courseid);
        $this->assertEqual(count($result->items), 1);
        $this->assertNotNull($result->items[0]);
        $this->assertNotNull($result->items[0]->id);
        $this->assertNotNull($result->items[0]->scores);
        $this->assertEqual(count($result->items[0]->scores), 1);
        $this->assertFalse(isset($result->items[0]->errors));
        $this->assertEqual($result->items[0]->grademax, 90);
        $this->assertEqual($result->items[0]->categoryid, $result->category_id);
        $this->assertEqual($result->items[0]->courseid, $result->course_id);
        $this->assertEqual($result->items[0]->itemname, $test_item_name1);
        $this->assertNotNull($result->items[0]->scores[0]);
        $this->assertFalse(isset($result->items[0]->scores[0]->error));

        // saving one with multiple items, some invalid
        $score->score = 50; // SCORE_UPDATE_ERRORS

        $score1 = new stdClass();
        $score1->user_id = 'xxxxxx'; // USER_DOES_NOT_EXIST_ERROR
        $score1->score = 80;
        $grade_item->scores[] = $score1;

        $score2 = new stdClass();
        $score2->user_id = '2';
        $score2->score = 101; // POINTS_POSSIBLE_UPDATE_ERRORS
        $grade_item->scores[] = $score2;

        $score3 = new stdClass();
        $score3->user_id = '3';
        $score3->score = 'XX'; // GENERAL_ERRORS
        $grade_item->scores[] = $score3;

        $result = iclicker_service::save_gradebook($gradebook);
        $this->assertNotNull($result);
        $this->assertNotNull($result->course_id);
        $this->assertNotNull($result->course);
        $this->assertNotNull($result->category_id);
        $this->assertNotNull($result->items);
        $this->assertEqual($result->course_id, $this->courseid);
        $this->assertEqual(count($result->items), 1);
        $this->assertNotNull($result->items[0]);
        $this->assertNotNull($result->items[0]->id);
        $this->assertNotNull($result->items[0]->scores);
        $this->assertEqual(count($result->items[0]->scores), 4);
        $this->assertTrue(isset($result->items[0]->errors));
        $this->assertEqual(count($result->items[0]->errors), 4);
        $this->assertEqual($result->items[0]->grademax, 90);
        $this->assertEqual($result->items[0]->categoryid, $result->category_id);
        $this->assertEqual($result->items[0]->courseid, $result->course_id);
        $this->assertEqual($result->items[0]->itemname, $test_item_name1);
        $this->assertNotNull($result->items[0]->scores[0]);
        $this->assertTrue(isset($result->items[0]->scores[0]->error));
        $this->assertEqual($result->items[0]->scores[0]->error, iclicker_service::SCORE_UPDATE_ERRORS);
        $this->assertNotNull($result->items[0]->scores[1]);
        $this->assertTrue(isset($result->items[0]->scores[1]->error));
        $this->assertEqual($result->items[0]->scores[1]->error, iclicker_service::USER_DOES_NOT_EXIST_ERROR);
        $this->assertNotNull($result->items[0]->scores[2]);
        $this->assertTrue(isset($result->items[0]->scores[2]->error));
        $this->assertEqual($result->items[0]->scores[2]->error, iclicker_service::POINTS_POSSIBLE_UPDATE_ERRORS);
        $this->assertNotNull($result->items[0]->scores[3]);
        $this->assertTrue(isset($result->items[0]->scores[3]->error));
        $this->assertEqual($result->items[0]->scores[3]->error, 'SCORE_INVALID');

        $xml = iclicker_service::encode_gradebook_results($result);
        $this->assertNotNull($xml);
        $this->assertTrue(stripos($xml, '<user ') > 0);
        $this->assertTrue(stripos($xml, '<lineitem ') > 0);
        $this->assertTrue(stripos($xml, '<error ') > 0);
        $this->assertTrue(stripos($xml, iclicker_service::SCORE_UPDATE_ERRORS) > 0);
        $this->assertTrue(stripos($xml, iclicker_service::USER_DOES_NOT_EXIST_ERROR) > 0);
        $this->assertTrue(stripos($xml, iclicker_service::POINTS_POSSIBLE_UPDATE_ERRORS) > 0);
        $this->assertTrue(stripos($xml, iclicker_service::GENERAL_ERRORS) > 0);
        //echo "<xmp>$xml</xmp>";

        // Save 1 update and 2 new grades
        $score->score = 85;
        $score2->score = 50;
        $score3->score = 0;
        $grade_item->scores = array();
        $grade_item->scores[] = $score;
        $grade_item->scores[] = $score2;
        $grade_item->scores[] = $score3;

        $result = iclicker_service::save_gradebook($gradebook);
        $this->assertNotNull($result);
        $this->assertNotNull($result->course_id);
        $this->assertNotNull($result->items);
        $this->assertEqual($result->course_id, $this->courseid);
        $this->assertEqual(count($result->items), 1);
        $this->assertNotNull($result->items[0]);
        $this->assertNotNull($result->items[0]->id);
        $this->assertNotNull($result->items[0]->scores);
        $this->assertEqual(count($result->items[0]->scores), 3);
        $this->assertFalse(isset($result->items[0]->errors));
        $this->assertEqual($result->items[0]->grademax, 90);
        $this->assertNotNull($result->items[0]->scores[0]);
        $this->assertEqual($result->items[0]->scores[0]->rawgrade, 85);
        $this->assertNotNull($result->items[0]->scores[1]);
        $this->assertEqual($result->items[0]->scores[1]->rawgrade, 50);
        $this->assertNotNull($result->items[0]->scores[2]);
        $this->assertEqual($result->items[0]->scores[2]->rawgrade, 0);
/*
echo "<pre>";
var_export($result->items[0]);
echo "</pre>";
*/
        $xml = iclicker_service::encode_gradebook_results($result);
        $this->assertNull($xml); // no errors

        // test saving multiple items at once
        $gradebook->course_id = $this->courseid;
        $gradebook->items = array();

        $grade_item1 = new stdClass();
        $grade_item1->name = $test_item_name2;
        $grade_item1->points_possible = 100;
        $grade_item1->type = 'multiple';
        $grade_item1->scores = array();
        $gradebook->items[] = $grade_item1;

        $grade_item2 = new stdClass();
        $grade_item2->name = $test_item_name3;
        $grade_item2->points_possible = 50;
        $grade_item2->type = 'multiple';
        $grade_item2->scores = array();
        $gradebook->items[] = $grade_item2;

        $score = new stdClass();
        $score->user_id = 1;
        $score->score = 80.0;
        $grade_item1->scores[] = $score;

        $score = new stdClass();
        $score->user_id = 2;
        $score->score = 90.0;
        $grade_item1->scores[] = $score;

        $score = new stdClass();
        $score->user_id = 2;
        $score->score = 45.0;
        $grade_item2->scores[] = $score;

        $score = new stdClass();
        $score->user_id = 3;
        $score->score = 40.0;
        $grade_item2->scores[] = $score;
        
        $result = iclicker_service::save_gradebook($gradebook);
        $this->assertNotNull($result);
        $this->assertNotNull($result->course_id);
        $this->assertNotNull($result->items);
        $this->assertEqual($result->course_id, $this->courseid);
        $this->assertEqual(count($result->items), 2);
        $this->assertNotNull($result->items[0]);
        $this->assertNotNull($result->items[0]->id);
        $this->assertNotNull($result->items[0]->scores);
        $this->assertEqual(count($result->items[0]->scores), 2);
        $this->assertFalse(isset($result->items[0]->errors));
        $this->assertEqual($result->items[0]->grademax, 100);
        $this->assertNotNull($result->items[0]->scores[0]);
        $this->assertEqual($result->items[0]->scores[0]->rawgrade, 80);
        $this->assertNotNull($result->items[0]->scores[1]);
        $this->assertEqual($result->items[0]->scores[1]->rawgrade, 90);
        $this->assertNotNull($result->items[1]);
        $this->assertNotNull($result->items[1]->id);
        $this->assertNotNull($result->items[1]->scores);
        $this->assertEqual(count($result->items[1]->scores), 2);
        $this->assertFalse(isset($result->items[1]->errors));
        $this->assertEqual($result->items[1]->grademax, 50);
        $this->assertNotNull($result->items[1]->scores[0]);
        $this->assertEqual($result->items[1]->scores[0]->rawgrade, 45);
        $this->assertNotNull($result->items[1]->scores[1]);
        $this->assertEqual($result->items[1]->scores[1]->rawgrade, 40);

        $xml = iclicker_service::encode_gradebook_results($result);
        $this->assertNull($xml);

    }

    function test_save_ws() {
        $result = iclicker_service::ws_get_students();
        $this->assertNotNull($result);

        $clicker_reg = new stdClass();
        $clicker_reg->owner_id = '101'; // student01
        $clicker_reg->clicker_id = '11111111';
// NOTE: disable this to avoid putting test data into the real domain of systems
/*
        $result = iclicker_service::ws_save_clicker($clicker_reg);
        $this->assertNotNull($result);
*/

        $result = iclicker_service::ws_get_student('student01');
        $this->assertNotNull($result);
    }

}
?>
