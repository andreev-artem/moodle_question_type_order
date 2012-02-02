<?php

/**
 * Matching question definition class.
 *
 * @package    qtype
 * @subpackage order
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot . '/question/type/match/question.php');

/**
 * Represents an order question.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_order_question extends qtype_match_question {

    public function start_attempt(question_attempt_step $step, $variant) {
        parent::start_attempt($step, $variant);

        $choiceorder = array_keys($this->choices);
        $step->set_qt_var('_choiceorder', implode(',', $choiceorder));
        $this->set_choiceorder($choiceorder);
    }
    
    public function get_num_parts_right(array $response) {
        $fieldname = $this->get_dontknow_field_name();
        if (array_key_exists($fieldname, $response) and $response[$fieldname])
            return array(0, count($this->stemorder));

        return parent::get_num_parts_right($response);
    }

    public function get_expected_data() {
        $vars = parent::get_expected_data();
        $vars[$this->get_dontknow_field_name()] = PARAM_ALPHA;
        
        return $vars;
    }
    
    public function get_field_name($key) {
        return $this->field($key);
    }
    
    public function get_dontknow_field_name() {
        return 'dontknow'.$this->id;
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'qtype_order' && $filearea == 'subquestion') {
            $subqid = reset($args); // itemid is sub question id
            return array_key_exists($subqid, $this->stems);

        } else if ($component == 'question' && in_array($filearea,
                array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'))) {
            return $this->check_combined_feedback_file_access($qa, $options, $filearea);

        } else if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);

        } else {
            return parent::check_file_access($qa, $options, $component, $filearea,
                    $args, $forcedownload);
        }
    }
}
