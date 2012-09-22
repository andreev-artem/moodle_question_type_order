<?php

/**
 * Serve question type files
 *
 * @since 2.0
 * @package questionbank
 * @subpackage questiontypes
 * @author Dongsheng Cai <dongsheng@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function qtype_order_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_order', $filearea, $args, $forcedownload, $options);
}
