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
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * restore plugin class that provides the necessary information
 * needed to restore one order qtype plugin
 */
class restore_qtype_order_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // Add own qtype stuff
        $elename = 'orderoptions';
        $elepath = $this->get_pathfor('/orderoptions'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'order';
        $elepath = $this->get_pathfor('/orders/order'); // we used get_recommended_name() so this works
        $paths[] = new restore_path_element($elename, $elepath);


        return $paths; // And we return the interesting paths
    }

    /**
     * Process the qtype/orderoptions element
     */
    public function process_orderoptions($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its question_order too
        if ($questioncreated) {
            // Adjust some columns
            $data->question = $newquestionid;
            // Keep question_order->subquestions unmodified
            // after_execute_question() will perform the remapping once all subquestions
            // have been created
			
			//Added by justin hunt 20120131, previously errors occured here cos no default value for these fields in DB
			//yet since these members are new in 2.x, the 1.9 backups didn't contain them
			if(!isset($data->correctfeedback)){ $data->correctfeedback =" ";}
			if(!isset($data->partiallycorrectfeedback)){ $data->partiallycorrectfeedback =" ";}
			if(!isset($data->incorrectfeedback)){ $data->incorrectfeedback =" ";}
			
            // Insert record
            $newitemid = $DB->insert_record('question_order', $data);
            // Create mapping
            $this->set_mapping('question_order', $oldid, $newitemid);
        } else {
            // Nothing to remap if the question already existed
        }
    }

    /**
     * Process the qtype/orders/order element
     */
    public function process_order($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its question_order_sub too
        if ($questioncreated) {
            // Adjust some columns
            $data->question = $newquestionid;
            // Insert record
            $newitemid = $DB->insert_record('question_order_sub', $data);
            // Create mapping (there are files and states based on this)
            $this->set_mapping('question_order_sub', $oldid, $newitemid);

        // order questions require mapping of question_order_sub, because
        // they are used by question_states->answer
        } else {
            // Look for ordering subquestion (by question, questiontext and answertext)
            $sub = $DB->get_record_select('question_order_sub', 'question = ? AND ' .
                    $DB->sql_compare_text('questiontext') . ' = ' .
                    $DB->sql_compare_text('?').
                    $DB->sql_compare_text('AND answertext') . ' = ' .
                    $DB->sql_compare_text('?'),
                    array($newquestionid, $data->questiontext, $data->answertext),
                    'id', IGNORE_MULTIPLE);
            // Found, let's create the mapping
            if ($sub) {
                $this->set_mapping('question_order_sub', $oldid, $sub->id);
            // Something went really wrong, cannot map subquestion for one order question
            } else {
              //  throw restore_step_exception('error_question_order_sub_missing_in_db', $data);
				print_r($data);
            }
        }
    }

    /**
     * This method is executed once the whole restore_structure_step,
     * more exactly ({@link restore_create_categories_and_questions})
     * has ended processing the whole xml structure. Its name is:
     * "after_execute_" + connectionpoint ("question")
     *
     * For order qtype we use it to restore the subquestions column,
     * containing one list of question_order_sub ids
     */
    public function after_execute_question() {
        global $DB;
        // Now that all the question_order_subs have been restored, let's process
        // the created question_order subquestions (list of question_order_sub ids)
        $rs = $DB->get_recordset_sql("SELECT qm.id, qm.subquestions
                                        FROM {question_order} qm
                                        JOIN {backup_ids_temp} bi ON bi.newitemid = qm.question
                                       WHERE bi.backupid = ?
                                         AND bi.itemname = 'question_created'", array($this->get_restoreid()));
        foreach ($rs as $rec) {
            $subquestionsarr = explode(',', $rec->subquestions);
            foreach ($subquestionsarr as $key => $subquestion) {
                $subquestionsarr[$key] = $this->get_mappingid('question_order_sub', $subquestion);
            }
            $subquestions = implode(',', $subquestionsarr);
            $DB->set_field('question_order', 'subquestions', $subquestions, array('id' => $rec->id));
        }
        $rs->close();
    }

    /**
     * Given one question_states record, return the answer
     * recoded pointing to all the restored stuff for order questions
     *
     * answer is one comma separated list of hypen separated pairs
     * containing question_order_sub->id and question_order_sub->code
     */
    public function recode_state_answer($state) {
        $answer = $state->answer;
        $resultarr = array();

        $responses = explode(',', $answer);
        $defaultresponse = array_pop($responses);

        foreach ($responses as $pair) {
            $pairarr = explode('-', $pair);
            $id = $pairarr[0];
            $code = $pairarr[1];
            $newid = $this->get_mappingid('question_order_sub', $id);
            $resultarr[] = implode('-', array($newid, $code));
        }

        $resultarr[] = $defaultresponse;
        return implode(',', $resultarr);
    }

    /**
     * Return the contents of this qtype to be processed by the links decoder
     */
    static public function define_decode_contents() {

        $contents = array();

        $contents[] = new restore_decode_content('question_order_sub', array('questiontext'), 'question_order_sub');

        return $contents;
    }
}
