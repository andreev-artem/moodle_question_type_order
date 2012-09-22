<?php

/**
 * Question type class for the order question type.
 *
 * @package    qtype
 * @subpackage order
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');


/**
 * The order question type class.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_order extends question_type {

    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);
        $question->options = $DB->get_record('question_order', array('question' => $question->id));
        $question->options->subquestions = $DB->get_records('question_order_sub',
                array('question' => $question->id), 'id ASC');
        return true;
    }

    public function save_question_options($question) {
        global $DB;
        $context = $question->context;
        $result = new stdClass();

        $oldsubquestions = $DB->get_records('question_order_sub',
                array('question' => $question->id), 'id ASC');

        // $subquestions will be an array with subquestion ids
        $subquestions = array();

        // Insert all the new question+answer pairs
        $ordercount = 1;
        foreach ($question->subquestions as $key => $questiontext) {
            if ($questiontext['text'] == '') {
                continue;
            }

            // Update an existing subquestion if possible.
            $subquestion = array_shift($oldsubquestions);
            if (!$subquestion) {
                $subquestion = new stdClass();
                // Determine a unique random code
                $subquestion->code = rand(1, 999999999);
                while ($DB->record_exists('question_order_sub',
                        array('code' => $subquestion->code, 'question' => $question->id))) {
                    $subquestion->code = rand(1, 999999999);
                }
                $subquestion->question = $question->id;
                $subquestion->questiontext = '';
                $subquestion->answertext = '';
                $subquestion->id = $DB->insert_record('question_order_sub', $subquestion);
            }

            $subquestion->questiontext = $this->import_or_save_files($questiontext,
                    $context, 'qtype_order', 'subquestion', $subquestion->id);
            $subquestion->questiontextformat = $questiontext['format'];
            $subquestion->answertext = $ordercount;
            $ordercount++;

            $DB->update_record('question_order_sub', $subquestion);

            $subquestions[] = $subquestion->id;
        }

        // Delete old subquestions records
        $fs = get_file_storage();
        foreach ($oldsubquestions as $oldsub) {
            $fs->delete_area_files($context->id, 'qtype_order', 'subquestion', $oldsub->id);
            $DB->delete_records('question_order_sub', array('id' => $oldsub->id));
        }

        // Save the question options.
        $options = $DB->get_record('question_order', array('question' => $question->id));
        if (!$options) {
            $options = new stdClass();
            $options->question = $question->id;
            $options->correctfeedback = '';
            $options->partiallycorrectfeedback = '';
            $options->incorrectfeedback = '';
            $options->id = $DB->insert_record('question_order', $options);
        }

        $options->subquestions = implode(',', $subquestions);
        $options->horizontal = $question->horizontal;
        $options = $this->save_combined_feedback_helper($options, $question, $context, true);
        $DB->update_record('question_order', $options);

        $this->save_hints($question, true);

        if (!empty($result->notice)) {
            return $result;
        }

        if (count($subquestions) < 3) {
            $result->notice = get_string('notenoughanswers', 'question', 3);
            return $result;
        }

        return true;
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->shufflestems = true;
        $question->horizontal = $questiondata->options->horizontal;
        $this->initialise_combined_feedback($question, $questiondata, true);

        $question->stems = array();
        $question->choices = array();
        $question->right = array();

        foreach ($questiondata->options->subquestions as $matchsub) {
            $ans = $matchsub->answertext;
            $key = array_search($matchsub->answertext, $question->choices);
            if ($key === false) {
                $key = $matchsub->id;
                $question->choices[$key] = $matchsub->answertext;
            }

            if ($matchsub->questiontext !== '') {
                $question->stems[$matchsub->id] = $matchsub->questiontext;
                $question->stemformat[$matchsub->id] = $matchsub->questiontextformat;
                $question->right[$matchsub->id] = $key;
            }
        }
    }

    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    public function delete_question($questionid, $contextid) {
        global $DB;
        $DB->delete_records('question_order', array('question' => $questionid));
        $DB->delete_records('question_order_sub', array('question' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    public function get_random_guess_score($questiondata) {
        $q = $this->make_question($questiondata);
        return 1 / count($q->choices);
    }

    public function get_possible_responses($questiondata) {
        $subqs = array();

        $q = $this->make_question($questiondata);

        foreach ($q->stems as $stemid => $stem) {

            $responses = array();
            foreach ($q->choices as $choiceid => $choice) {
                $responses[$choiceid] = new question_possible_response(
                        $q->html_to_text($stem, $q->stemformat[$stemid]) . ': ' . $choice,
                        ($choiceid == $q->right[$stemid]) / count($q->stems));
            }
            $responses[null] = question_possible_response::no_response();

            $subqs[$stemid] = $responses;
        }

        return $subqs;
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        global $DB;
        $fs = get_file_storage();

        parent::move_files($questionid, $oldcontextid, $newcontextid);

        $subquestionids = $DB->get_records_menu('question_order_sub',
                array('question' => $questionid), 'id', 'id,1');
        foreach ($subquestionids as $subquestionid => $notused) {
            $fs->move_area_files_to_new_context($oldcontextid,
                    $newcontextid, 'qtype_order', 'subquestion', $subquestionid);
        }
    }

    protected function delete_files($questionid, $contextid) {
        global $DB;
        $fs = get_file_storage();

        parent::delete_files($questionid, $contextid);

        $subquestionids = $DB->get_records_menu('question_order_sub',
                array('question' => $questionid), 'id', 'id,1');
        foreach ($subquestionids as $subquestionid => $notused) {
            $fs->delete_area_files($contextid, 'qtype_order', 'subquestion', $subquestionid);
        }

        $fs->delete_area_files($contextid, 'qtype_order',
                'correctfeedback', $questionid);
        $fs->delete_area_files($contextid, 'qtype_order',
                'partiallycorrectfeedback', $questionid);
        $fs->delete_area_files($contextid, 'qtype_order',
                'incorrectfeedback', $questionid);
    }
	
/// IMPORT EXPORT FUNCTIONS ////////////////////////////
 
    /**
     ** Provide export functionality for xml format
     ** @param question object the question object
     ** @param format object the format object so that helper methods can be used 
     ** @param extra mixed any additional format specific data that may be passed by the format (see format code for info)
     ** @return string the data to append to the output buffer or false if error
     **/
    public function export_to_xml($question, qformat_xml $format, $extra=null) {
        $expout = '';
        $fs = get_file_storage();
        $contextid = $question->contextid;
        $expout .= "    <horizontal>" . $question->options->horizontal .
                        "</horizontal>\n";
		$expout .= $format->write_combined_feedback($question->options,
                                                    $question->id,
                                                    $question->contextid);
        foreach($question->options->subquestions as $subquestion) {
            $files = $fs->get_area_files($contextid, 'qtype_order', 'subquestion', $subquestion->id);
            $textformat = $format->get_format($subquestion->questiontextformat);
            $expout .= "    <subquestion format=\"$textformat\">\n";
            $expout .= $format->writetext( $subquestion->questiontext, 3);
            $expout .= $format->write_files($files);
            $expout .= "      <answer>\n";
			$expout .= $format->writetext( $subquestion->answertext, 4);
			$expout .= "      </answer>\n";
            $expout .= "    </subquestion>\n";
        }

        return $expout;
    }

   /**
    ** Provide import functionality for xml format
    ** @param data mixed the segment of data containing the question
    ** @param question object question object processed (so far) by standard import code
    ** @param format object the format object so that helper methods can be used (in particular error() )
    ** @param extra mixed any additional format specific data that may be passed by the format (see format code for info)
    ** @return object question object suitable for save_options() call or false if cannot handle
    **/
    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
       // check question is for us
       $qtype = $data['@']['type'];
       if ($qtype=='order') {
           $question = $format->import_headers( $data );

            // header parts particular to matching
            $question->qtype = $qtype;
            $question->shuffleanswers = 1;
            $question->horizontal = $format->getpath( $data, array( '#','horizontal',0,'#' ), 1 );

            // get subquestions
            $subquestions = $data['#']['subquestion'];
            $question->subquestions = array();
            $question->subanswers = array();

            // run through subquestions
            foreach ($subquestions as $subquestion) {
                $qo = array();
                $qo['text'] = $format->getpath($subquestion, array('#', 'text', 0, '#'), '', true);
                $qo['format'] = $format->trans_format(
                        $format->getpath($subquestion, array('@', 'format'), 'html'));
                $qo['files'] = array();

                $files = $format->getpath($subquestion, array('#', 'file'), array());
                foreach ($files as $file) {
                    $record = new stdclass();
                    $record->content = $file['#'];
                    $record->encoding = $file['@']['encoding'];
                    $record->name = $file['@']['name'];
                    $qo['files'][] = $record;
                }
                $question->subquestions[] = $qo;
				$ans = $format->getpath($subquestion, array('#', 'answer', 0), array());
                $question->subanswers[] = $ans;
            }
			$format->import_combined_feedback($question, $data, true);
			$format->import_hints($question, $data, true);
            return $question;
       }
       else {
           return false;
       }
    } 
}
