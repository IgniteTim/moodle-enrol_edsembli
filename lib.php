<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * EdSembli enrolment plugin implementation.
 *
 * @package    enrol_edsembli
 * @author     Tim Martinez <tim.martinez@ignitecentre.ca>
 * @copyright  2021 Tim Martinez <tim.martinez@ignitecentre.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class enrol_edsembli_plugin extends enrol_plugin {

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        if (!has_capability('enrol/edsembli:manage', $context)) {
            return false;
        }
        if (!enrol_is_enabled('edsembli')) {
            return true;
        }

        return false;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/edsembli:manage', $context);
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     *
     * @return bool - false. We only allow these modifications through the plugin.
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        return false;
    }

    /**
     * Forces synchronisation of all enrolments with EdSembli.
     *
     * @param progress_trace $trace
     * @param int|null $onecourse limit sync to one course->id, null if all courses
     * @return void
     */
    public function sync_enrolments(progress_trace $trace, $onecourse = null) {
        global $DB;
        $ws = new \auth_edsembli\webservice();

        if ($onecourse) {
            if (!$course = get_course($onecourse)) {
                $trace->output("Requested course $onecourse does not exist, no sync performed.");
                $trace->finished();
                return;
            }
        }

        $enroll = enrol_get_plugin('edsembli');

        $studentrole = get_config('enrol_edsembli', 'student_role');
        
        $dbman = $DB->get_manager();

        /// Define table user to be created
        $table = new xmldb_table('tmp_extcourses');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        $trace->output('Creating temporary table.');
        $dbman->create_temp_table($table);

        //First deal with the teachers.
        $classes = $ws->GetTeacherClasses(get_config('enrol_edsembli', 'schoolnum'));

        foreach ($classes as $class) {
            $classcode = $class->Course_Code->__toString() . $class->Class_Section->__toString();
            if ($onecourse > 0 && $classcode != $course->idnumber) {
                //We're only processing one class and this ain't it.
                continue;
            }

            //See if we have this class.
            if (!$course = $DB->get_record('course', array('idnumber' => $classcode))) {
                //This section isn't linked in Moodle we can skip
                $trace->output("Section $classcode is not linked in Moodle. Skipping.");
                continue;
            }

            //find the primary teacher.
            if (!$user = $DB->get_record('user', array('idnumber' => 'T' . $class->Teacher_Code->__toString()))) {
                //The primary teacher does not exist in Moodle. Log it and bail in the course.
                $params = new \stdClass();
                $params->sisuserid = 'T' . $class->Teacher_Code->__toString();
                $params->courseid = $course->id;
                $params->role = get_config('enrol_edsembli', 'teacher_role');
                $course->exception = 'The primary teacher with ID ' . $class->Teacher_Code->__toString() . ' could not be found in Moodle.';
                \report_edsembli\enrol::enroll(0, EDSEMBLI_USER_NOTFOUND, time(), $params);
                $trace->output('The primary teacher with ID ' . $class->Teacher_Code->__toString() . ' could not be found in Moodle.');
                continue;
            } else {
                $rec = array();
                $rec['courseid'] = $course->id;
                $rec['userid'] = $user->id;
                $rec['roleid'] = get_config('enrol_edsembli', 'teacher_role');
                $DB->insert_record('tmp_extcourses', $rec);
            }

            //Find the alternate teacher (if there is one.
            $altteacher = $class->Alternate_Teacher_Code->__toString();
            if (strlen($altteacher) > 0) {
                if (!$user = $DB->get_record('user', array('idnumber' => 'T' . $altteacher))) {
                    //The alternate teacher does not exist in Moodle. Log it and bail in the course.
                    $params = new \stdClass();
                    $params->sisuserid = 'T' . $altteacher;
                    $params->courseid = $course->id;
                    $params->role = get_config('enrol_edsembli', 'additional_teacher_role');
                    $params->exception = 'The alternate teacher with ID ' . $altteacher . ' could not be found in Moodle.';
                    \report_edsembli\enrol::enroll(0, EDSEMBLI_USER_NOTFOUND, time(), $params);
                    $trace->output('The alternate teacher with ID ' . $altteacher . ' could not be found in Moodle.');
                    continue;
                } else {
                    $rec = array();
                    $rec['courseid'] = $course->id;
                    $rec['userid'] = $user->id;
                    $rec['roleid'] = get_config('enrol_edsembli', 'additional_teacher_role');
                    $DB->insert_record('tmp_extcourses', $rec);
                }
            }
        }

        //Now deal with the students.
        $classes = $ws->GetStudentClasses(get_config('enrol_edsembli', 'schoolnum'));

        foreach ($classes as $class) {
            $classcode = $class->Course_Code->__toString() . $class->Class_Code->__toString();
            if ($onecourse > 0 && $classcode != $course->idnumber) {
                //We're only processing one class and this ain't it.
                continue;
            }

            //See if we have this class.
            if (!$course = $DB->get_record('course', array('idnumber' => $classcode))) {
                //This section isn't linked in Moodle we can skip
                $trace->output("Section $classcode is not linked in Moodle. Skipping.");
                continue;
            }

            $studentcode = 'S' . $class->Student_Code->__toString();
            //Does this student exist?
            if (!$user = $DB->get_record('user', array('idnumber' => $studentcode))) {
                //The student does not exist in Moodle. Log it.
                $params = new \stdClass();
                $params->sisuserid = $studentcode;
                $params->courseid = $course->id;
                $params->role = $studentrole;
                $course->exception = 'The student with EdSembli ID ' . $studentcode . ' could not be found in Moodle.';
                \report_edsembli\enrol::enroll(0, EDSEMBLI_USER_NOTFOUND, time(), $params);
                $trace->output('The student with EdSembli ID ' . $studentcode . ' could not be found in Moodle.');
                continue;
            } else {
                $rec = array();
                $rec['courseid'] = $course->id;
                $rec['userid'] = $user->id;
                $rec['roleid'] = $studentrole;
                $DB->insert_record('tmp_extcourses', $rec);
            }
        }

        //Now that we have everything from EdSembli, sync the courses.
        $courses = $DB->get_records_sql("SELECT * FROM {course} where idnumber <> ''");

        foreach ($courses as $course) {
            //Get the instance in this course
            $instance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'edsembli'));
            //If this course does not have the instance create it
            if (empty($instance)) {
                // Only add an enrol instance to the course if non-existent.
                $enrolid = $enroll->add_instance($course);
                $instance = $DB->get_record('enrol', array('id' => $enrolid));
            }

            //Add new users and update existing ones.
            $sql = 'SELECT ec.id,ue.status,ec.userid,ec.roleid FROM {tmp_extcourses} ec LEFT JOIN {user_enrolments} ue ON ec.userid = ue.userid AND ec.courseid = ? AND ue.enrolid = ?';
            $users = $DB->get_records_sql($sql, array($course->id, $instance->id));
            foreach ($users as $ue) {
                if ($ue->status === null) {
                    //This is a new user. Enrol them
                    $this->enrol_user($instance, $ue->userid, $ue->roleid);
                    $params = array();
                    $params['courseid'] = $course->id;
                    $params['role'] = $eu->roleid;
                    \report_edsembli\enrol::enroll($ue->userid, EDSEMBLI_SUCCESS, time(), $params);
                    $trace->output('Enroled user with id ' . $ue->userid . ' into course with id ' . $course->id);
                
                } else {
                    //Is the role assignment correct?
                    
                }
            }
        }
    }

}
