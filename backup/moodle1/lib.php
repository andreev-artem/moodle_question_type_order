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
 * @package    qtype
 * @subpackage order
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Order question type conversion handler
 */
class moodle1_qtype_order_handler extends moodle1_qtype_handler {

    /**
     * @return array
     */
    public function get_question_subpaths() {
        return array(
            'ORDERS/ORDER'
        );
    }

    /**
     * Appends the order specific information to the question
     */
    public function process_question(array $data, array $raw) {
        global $CFG;

        // populate the list of orders first to get their ids
        // note that the field is re-populated on restore anyway but let us
        // do our best to produce valid backup files
        $orderids = array();
        if (isset($data['orders']['order'])) {
            foreach ($data['orders']['order'] as $order) {
                $orderids[] = $order['id'];
            }
        }

        // convert order options
        $orderoptions = array();
        $orderoptions['id'] = $this->converter->get_nextid();
        $orderoptions['subquestions'] = implode(',', $orderids);
		$orderoptions['horizontal'] = $data['horizontal'];
        $this->write_xml('orderoptions', $orderoptions, array('/orderoptions/id'));

        // convert orders
        $this->xmlwriter->begin_tag('orders');
        if (isset($data['orders']['order'])) {
            foreach ($data['orders']['order'] as $order) {
                // replay the upgrade step 2009072100
                $order['questiontextformat'] = 0;
                if ($CFG->texteditors !== 'textarea' and $data['oldquestiontextformat'] == FORMAT_MOODLE) {
                    $order['questiontext'] = text_to_html($order['questiontext'], false, false, true);
                    $order['questiontextformat'] = FORMAT_HTML;
					$order['answertext'] = text_to_html($order['answertext'], false, false, true);
                    $order['answertextformat'] = FORMAT_HTML;
                } else {
                    $order['questiontextformat'] = $data['oldquestiontextformat'];
					$order['answertextformat'] = $data['oldquestiontextformat'];
                }

                $this->write_xml('order', $order, array('/order/id'));
            }
        }
        $this->xmlwriter->end_tag('orders');
    }
}
