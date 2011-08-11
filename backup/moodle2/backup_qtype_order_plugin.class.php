<?php

/**
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Provides the information to backup order questions
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_order_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill
        $plugin = $this->get_plugin_element(null, '../../qtype', 'order');

        // Create one standard named plugin element (the visible container)
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // connect the visible container ASAP
        $plugin->add_child($pluginwrapper);

        // Now create the qtype own structures
        $matchoptions = new backup_nested_element('matchoptions', array('id'), array(
            'subquestions', 'horizontal', 'gradingmethod', 'correctfeedback', 'correctfeedbackformat',
            'partiallycorrectfeedback', 'partiallycorrectfeedbackformat',
            'incorrectfeedback', 'incorrectfeedbackformat', 'shownumcorrect'));

        $matches = new backup_nested_element('matches');

        $match = new backup_nested_element('match', array('id'), array(
            'code', 'questiontext', 'questiontextformat', 'answertext'));

        // Now the own qtype tree
        $pluginwrapper->add_child($matchoptions);
        $pluginwrapper->add_child($matches);
        $matches->add_child($match);

        // set source to populate the data
        $matchoptions->set_source_table('question_order',
                array('question' => backup::VAR_PARENTID));
        $match->set_source_sql('
                SELECT *
                FROM {question_order_sub}
                WHERE question = :question
                ORDER BY id',
                array('question' => backup::VAR_PARENTID));

        // don't need to annotate ids nor files

        return $plugin;
    }

    /**
     * Returns one array with filearea => mappingname elements for the qtype
     *
     * Used by {@link get_components_and_fileareas} to know about all the qtype
     * files to be processed both in backup and restore.
     */
    public static function get_qtype_fileareas() {
        return array(
            'correctfeedback' => 'question_ddmatch',
            'partiallycorrectfeedback' => 'question_ddmatch',
            'incorrectfeedback' => 'question_ddmatch',
            'subquestion' => 'question_order_sub');
    }
}
