<?php

/**
 * Upgrade library code for the match question type.
 *
 * @package    qtype
 * @subpackage match
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Class for converting attempt data for order questions when upgrading
 * attempts to the new question engine.
 *
 * This class is used by the code in question/engine/upgrade/upgradelib.php.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_order_qe2_attempt_updater extends question_qtype_attempt_updater {
    protected $stems;
    protected $choices;
    protected $right;
    protected $stemorder;
    protected $choiceorder;
    protected $code2subid;
    protected $flippedchoiceorder;

    /**
     * This value gets stored in the question_attempts.questionsummary column.
     * @return string
     */
    public function question_summary() {
        $this->stems = array();
        $this->choices = array();
        $this->right = array();
        $this->code2subid = array();
        foreach ($this->question->options->subquestions as $matchsub) {
            $key = $matchsub->id;
            $this->choices[$key] = $matchsub->answertext;

            if ($matchsub->questiontext !== '') {
                $this->stems[$key] = $this->to_text($matchsub->questiontext);
                $this->right[$key] = $key;
            }

            $this->code2subid[$matchsub->code] = $matchsub->id;
        }
        $summary = $this->to_text($this->question->questiontext) . ' {' .
            implode('; ', $this->stems) . '} -> {' . implode('; ', $this->choices) . '}';
        return $summary;
    }

    /**
     * This value gets stored in the question_attempts.rightanswer column.
     * @return string
     */
    public function right_answer() {
        $answer = array();
        foreach ($this->stems as $key => $stem) {
            $answer[$stem] = $this->choices[$this->right[$key]];
        }
        return $this->make_summary($answer);
    }

    /**
     * @param $answer
     * @return array (subquestionid => choice position, ..., subquestionid => choice position) (subquestionid a.k.a stemid)
     */
    protected function explode_answer($answer) {
        if (!$answer) {
            return array();
        }
        $bits = explode(',', $answer);
        $selections = array();
        foreach ($bits as $bit) {
            $data = explode('-', $bit);

           // ignore the "no" or "yes" piece
            if (count($data) == 2) {
                list($stem, $choice) = $data;
                if (isset($this->code2subid[$stem])) {
                    $selections[$this->code2subid[$stem]] = $choice;
                }
            }
        }
        return $selections;
    }

    protected function make_summary($pairs) {
        $bits = array();
        foreach ($pairs as $stem => $answer) {
            $bits[] = $stem . ' -> ' . $answer;
        }
        return implode('; ', $bits);
    }

    protected function lookup_choice($choice) {
        foreach ($this->question->options->subquestions as $matchsub) {
            if ($matchsub->code == $choice) {
                if (array_key_exists($matchsub->id, $this->choices)) {
                    return $matchsub->id;
                } else {
                    return array_search($matchsub->answertext, $this->choices);
                }
            }
        }
        return null;
    }

    /**
     * This value gets stored in the question_attempts.responsesummary column.
     * @return string
     */
    public function response_summary($state) {
        $choices = $this->explode_answer($state->answer);
        if (empty($choices)) {
            return null;
        }

        $pairs = array();
        foreach ($choices as $stemid => $choicekey) {
            if ($choicekey && array_key_exists($stemid, $this->stems)) {
                $pairs[$this->stems[$stemid]] = $choicekey;
            }
        }

        if ($pairs) {
            return $this->make_summary($pairs);
        } else {
            return '';
        }
    }

    public function was_answered($state) {
        $choices = $this->explode_answer($state->answer);
        foreach ($choices as $choice) {
            if ($choice) {
                return true;
            }
        }
        return false;
    }

    public function get_stemorder($answer) {
        if (null === $this->stemorder && $answer) {
            $bits = explode(',', $answer);
            foreach ($bits as $bit) {
                //todo: fix this, not always two pieces here
                $data = explode('-', $bit);
                if (count($data) == 2) {
                    $stem = $data[0];
                    $this->stemorder[] = $this->code2subid[$stem];
                }
            }
        }
        return $this->stemorder;
    }

    public function set_first_step_data_elements($state, &$data) {
        $this->choiceorder = array_keys($this->choices);
        $answertexts = array_flip($this->choices);
        ksort($answertexts);
        $choiceorder = array_values($answertexts);  // gets the subquestion ids in the order defined by the answertext field
        $data['_stemorder'] = implode(',', $this->get_stemorder($state->answer));
        $data['_choiceorder'] = implode(',', $choiceorder);
    }

    public function supply_missing_first_step_data(&$data) {
        throw new coding_exception('qtype_order_updater::supply_missing_first_step_data ' .
                'not tested');
        $data['_stemorder'] = array_keys($this->stems);
        $data['_choiceorder'] = array_keys($this->choices);
    }

    public function set_data_elements_for_step($state, &$data) {
        $choices = $this->explode_answer($state->answer);
        $stemorder = $this->get_stemorder($state->answer);
        foreach ($stemorder as $i => $key) {
            if (empty($choices[$key])) {
                $data['sub' . $i] = 0;
                continue;
            }
            if (array_key_exists($key, $choices)) {
                $data['sub' . $i] = $choices[$key];
            } else {
                $data['sub' . $i] = 0;
            }
        }
    }
}
