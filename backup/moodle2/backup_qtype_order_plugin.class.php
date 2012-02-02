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
 * Provides the information to backup order questions
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
        $orderoptions = new backup_nested_element('orderoptions', array('id'), array(
            'subquestions', 'horizontal','correctfeedback', 'correctfeedbackformat',
            'partiallycorrectfeedback', 'partiallycorrectfeedbackformat',
            'incorrectfeedback', 'incorrectfeedbackformat', 'shownumcorrect'));

        $orders = new backup_nested_element('orders');

        $order = new backup_nested_element('order', array('id'), array(
            'code', 'questiontext', 'questiontextformat', 'answertext'));

        // Now the own qtype tree
        $pluginwrapper->add_child($orderoptions);
        $pluginwrapper->add_child($orders);
        $orders->add_child($order);

        // set source to populate the data
        $orderoptions->set_source_table('question_order', array('question' => backup::VAR_PARENTID));
        $order->set_source_table('question_order_sub', array('question' => backup::VAR_PARENTID));

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
            'correctfeedback' => 'question_order',
            'partiallycorrectfeedback' => 'question_order',
            'incorrectfeedback' => 'question_order',
            'subquestion'   => 'question_order_sub',
            'subanswer'     => 'question_order_sub');
    }
}
