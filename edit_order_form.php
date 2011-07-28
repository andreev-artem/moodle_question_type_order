<?php
/**
 * Defines the editing form for the order question type.
 *
 * @copyright &copy; 2007 Jamie Pratt
 * @author Jamie Pratt me@jamiep.org
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionbank
 * @subpackage questiontypes
 */

/**
 * match editing form definition.
 */
class question_edit_order_form extends question_edit_form {

    function get_per_answer_fields(&$mform, $label, $gradeoptions, &$repeatedoptions, &$answersoption) {
        $repeated = array();
        $repeated[] =& $mform->createElement('header', 'answerhdr', $label);
        $repeated[] =& $mform->createElement('editor', 'subquestions', get_string('question', 'quiz'), null, $this->editoroptions);
        $repeated[] =& $mform->createElement('hidden', 'subanswers', '0', null);
        $repeatedoptions['subquestions']['type'] = PARAM_RAW;
        $repeatedoptions['subanswers']['type'] = PARAM_TEXT;
        $answersoption = 'subquestions';
        return $repeated;
    }

    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    function definition_inner(&$mform) {
        $mform->addElement('advcheckbox', 'horizontal', get_string('horizontal', 'qtype_order'), null, null, array(0,1));
        $mform->setDefault('horizontal', 0);

        $mform->addElement('static', 'answersinstruct', get_string('choices', 'quiz'), get_string('filloutthreeitems', 'qtype_order'));
        $mform->closeHeaderBefore('answersinstruct');

        $this->add_per_answer_fields($mform, get_string('questionno', 'quiz', '{no}'), 0);
    }

    function data_preprocessing($question) {
        if (isset($question->options)) {
            $subquestions = $question->options->subquestions;
            if (count($subquestions)) {
                $key = 0;
                foreach ($subquestions as $subquestion){
                    $default_values['subanswers['.$key.']'] = $subquestion->answertext;

                    $draftid = file_get_submitted_draft_itemid('subquestions['.$key.']');
                    $default_values['subquestions['.$key.']'] = array();
                    $default_values['subquestions['.$key.']']['format'] = $subquestion->questiontextformat;
                    $default_values['subquestions['.$key.']']['text'] = file_prepare_draft_area(
                        $draftid, // draftid
                        $this->context->id, // context
                        'qtype_order', // component
                        'subquestion', // filarea
                        !empty($subquestion->id)?(int)$subquestion->id:null, // itemid
                        $this->fileoptions, // options
                        $subquestion->questiontext // text
                    );
                    $default_values['subquestions['.$key.']']['itemid'] = $draftid;

                    $key++;
                }
            }
            $default_values['horizontal'] =  $question->options->horizontal;
            $question = (object)((array)$question + $default_values);
        }
        return $question;
    }

    function qtype() {
        return 'order';
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $answers = $data['subanswers'];
        $questions = $data['subquestions'];
        $questioncount = 0;
        $answercount = 0;
        foreach ($questions as $key => $question){
            $trimmedquestion = trim($question['text']);
            $trimmedanswer = trim($answers[$key]);
            if ($trimmedquestion != ''){
                $questioncount++;
            }
            if ($trimmedanswer != '' || $trimmedquestion != ''){
                $answercount++;
            }
            if ($trimmedquestion != '' && $trimmedanswer == ''){
                $errors['subanswers['.$key.']'] = get_string('nomatchinganswerforq', 'qtype_match', $trimmedquestion);
            }
        }
        $numberqanda = new stdClass;
        $numberqanda->q = 3;
        if ($questioncount < 1){
            $errors['subquestions[0]'] = get_string('notenoughqsandas', 'qtype_match', $numberqanda);
        }
        if ($questioncount < 2){
            $errors['subquestions[1]'] = get_string('notenoughqsandas', 'qtype_match', $numberqanda);
        }
        if ($questioncount < 3){
            $errors['subquestions[2]'] = get_string('notenoughqsandas', 'qtype_match', $numberqanda);
        }
        return $errors;
    }
}
