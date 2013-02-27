<?php

/**
 * Order question renderer class.
 *
 * @package    qtype
 * @subpackage order
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Generates the output for order questions.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_order_renderer extends qtype_with_combined_feedback_renderer {

    protected function can_use_drag_and_drop() {
        global $USER;

        $ie = check_browser_version('MSIE', 6.0);
        $ff = check_browser_version('Gecko', 20051106);
        $op = check_browser_version('Opera', 9.0);
        $sa = check_browser_version('Safari', 412);
        $ch = check_browser_version('Chrome', 6);

        if ((!$ie && !$ff && !$op && !$sa && !$ch) or !empty($USER->screenreader)) {
            return false;
        }

        return true;
    }

    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $o = html_writer::tag('div', $question->format_questiontext($qa),
                array('class' => 'qtext'));

        $o .= html_writer::start_tag('div', array('id' => 'ablock_'.$question->id, 'class' => 'ablock'));
        $o .= $this->construct_ablock_select($qa, $options);
        $o .= html_writer::end_tag('div');

        if ($this->can_use_drag_and_drop()) $o .= html_writer::tag('div', '', array('class' => 'clearer'));

        if ($qa->get_state() == question_state::$invalid) {
            $o .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($response),
                    array('class' => 'validationerror'));
        }

        if ($this->can_use_drag_and_drop()) {
            $initparams = new stdClass();
            $initparams->qid = $question->id;
            $initparams->stemscount = count($question->get_stem_order());
            $initparams->ablockcontent = $this->construct_ablock_dragable($qa, $options);
            $initparams->readonly = $options->readonly;

            global $PAGE;
            $PAGE->requires->js_init_call('M.order.Init',
                                          array($initparams),
                                          FALSE,
                                          array('name' => 'order',
                                                'fullpath' => '/question/type/order/order.js',
                                                'requires' => array('yui2-yahoo', 'yui2-event', 'yui2-dom', 'yui2-dragdrop', 'yui2-animation')));

        }
        
        return $o;
    }
    
    private function construct_ablock_select(question_attempt $qa,
            question_display_options $options) {
        
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $stemorder = $question->get_stem_order();
        $choices = $this->format_choices($question);

        $o = html_writer::start_tag('table', array('class' => 'answer'));
        $o .= html_writer::start_tag('tbody');

        $parity = 0;
        foreach ($stemorder as $key => $stemid) {

            $o .= html_writer::start_tag('tr', array('class' => 'r' . $parity));

            $o .= html_writer::tag('td', $question->format_text(
                    $question->stems[$stemid], $question->stemformat[$stemid],
                    $qa, 'qtype_order', 'subquestion', $stemid),
                    array('class' => 'text'));

            $classes = 'control';
            $feedback = $this->get_feedback_class_image($qa, $options, $key);
            if ($feedback->class) $classes .= ' '.$feedback->class;

            $selected = $this->get_selected($question, $response, $key);
            
            $o .= html_writer::tag('td',
                    html_writer::select($choices, $qa->get_qt_field_name($question->get_field_name($key)), $selected,
                            array('0' => 'choose'), array('disabled' => $options->readonly)) .
                    ' ' . $feedback->image, array('class' => $classes));

            $o .= html_writer::end_tag('tr');
            $parity = 1 - $parity;
        }
        $o .= html_writer::end_tag('tbody');
        $o .= html_writer::end_tag('table');

        return $o;
    }

    private function construct_ablock_dragable(question_attempt $qa,
        question_display_options $options) {
        
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $o = '';
        $stemorder = $question->get_stem_order();
        $selectedstemorder = $this->get_selected_stemorder($qa);
        foreach ($selectedstemorder as $key => $stemid) {
            $stemorderkey = array_search($stemid, $stemorder);
            $attributes = array(
                    'id' => 'li_'.$question->id.'_'.$stemorderkey,
                    'name' => $qa->get_qt_field_name($question->get_field_name($stemorderkey)));
            
            $feedback = $this->get_feedback_class_image($qa, $options, $stemorderkey);
            
            if ($feedback->class) $attributes['class'] = $feedback->class;
            
            $stemcontent = $question->format_text(
                    $question->stems[$stemid], $question->stemformat[$stemid],
                    $qa, 'qtype_order', 'subquestion', $stemid);
            
            $o .= html_writer::tag('li', $stemcontent.' '.$feedback->image, $attributes);
        }
        $classes = 'draglist';
        if ($options->readonly) $classes .= ' readonly';
        if ($question->horizontal) $classes .= ' inline';
        $fieldname = $question->get_dontknow_field_name();
        if (array_key_exists($fieldname, $response) and $response[$fieldname]) $classes .= ' deactivateddraglist';
        $o .= html_writer::tag('div', '', array('class' => 'clearer'));
        $o = html_writer::tag('ul', $o, array('id' => 'ul_'.$question->id, 'class' => $classes));
        
        $attributes = array(
                'id'        => 'ch_'.$question->id,
                'name'      => $qa->get_qt_field_name($fieldname),
                'type'      => 'checkbox',
                'onClick'   => "M.order.OnClickDontKnow($question->id)");
        if (array_key_exists($fieldname, $response) and $response[$fieldname]) $attributes['checked'] = 'on';
        $o .= html_writer::empty_tag('input', $attributes);
        $o .= ' '.get_string('defaultresponse', 'qtype_order');

        foreach ($selectedstemorder as $key => $stemid) {
            $stemorderkey = array_search($stemid, $stemorder);
            $attributes = array(
                    'type'  => 'hidden',
                    'id'    => $qa->get_qt_field_name($question->get_field_name($stemorderkey)),
                    'name'  => $qa->get_qt_field_name($question->get_field_name($stemorderkey)),
                    'value' => $key + 1);
            $o .= html_writer::empty_tag('input', $attributes);
        }
        
        return $o;
    }
    
    private function get_selected($question, $response, $key) {
        if (array_key_exists($question->get_field_name($key), $response)) {
            return $response[$question->get_field_name($key)];
        } else {
            return 0;
        }
    }
    
    private function get_feedback_class_image(question_attempt $qa,
        question_display_options $options, $key) {
        
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();
        $stemorder = $question->get_stem_order();
        $stemid = $stemorder[$key];

        $ret = new stdClass();
        
        $ret->class = null;
        $ret->image = '';

        $selected = $this->get_selected($question, $response, $key);

        $fraction = (int) ($selected && $selected == $question->get_right_choice_for($stemid));

        if ($options->correctness && $selected) {
            $ret->class = $this->feedback_class($fraction);
            $ret->image = $this->feedback_image($fraction);
        }
        
        return $ret;
    }
    
    private function get_selected_stemorder($qa) {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $selectedstemorder = array();
        foreach ($question->get_stem_order() as $key => $stemid) {
            $choicenum = $this->get_selected($question, $response, $key);
            
            if ($choicenum == 0) return $question->get_stem_order();
            $selectedstemorder[$choicenum - 1] = $stemid;
        }
        ksort($selectedstemorder);
        
        return $selectedstemorder;
    }

    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    public function format_choices($question) {
        $choices = array();
        foreach ($question->get_choice_order() as $key => $choiceid) {
            $choices[$key] = htmlspecialchars($question->choices[$choiceid]);
        }
        return $choices;
    }

    public function correct_response(question_attempt $qa) {
        if ($qa->get_state()->is_correct()) return '';

        $question = $qa->get_question();
        $choices = $question->get_choice_order();
        
        if (count($choices)) {
            $table = new html_table();
            $table->attributes['class'] = 'generaltable correctanswertable';
            foreach ($choices as $key => $subqid) {
                $table->data[][] = $question->format_text($question->stems[$subqid],
                        $question->stemformat[$subqid], $qa,
                        'qtype_order', 'subquestion', $subqid);
            }

            return get_string('correctansweris', 'qtype_match', html_writer::table($table));
        }
        
        return '';
    }
}
