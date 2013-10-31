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
 * This is a class used to search for images from openclipart
 *
 * @package    repository_openclipart
 * @copyright  2011 Benjamin Ellis
 * @author     Benjamin Ellis benjamin.c.ellis@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('OPENCLIPART_URL', 'http://openclipart.org/media/feed/rss/');
define('DEFAULT_IMAGE_HEIGHT', 320);    // Default image height in pixels.
define('DEFAULT_THUMBNAIL_HEIGHT', 90); // Default icon height in pixels.
define('DEFAULT_MAX_FILES', 50);        // Default files in results.

class repository_openclipart extends repository {
    /**
     * Constructor
     *
     * @param int $repositoryid
     * @param stdClass $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SITEID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);

        $conf = get_config('openclipart');
        if (!isset($conf->imageheight)) {     // Empty object??
            $this->_setconfig();
        } else {
            // Checks for for silly inputs in the configuration.
            if ($conf->imageheight < 10 || $conf->imageheight > 4068) {
                $conf->imageheight = DEFAULT_IMAGE_HEIGHT;
                set_config('imageheight', $conf->imageheight, 'repository_openclipart');
            }

            if ($conf->maxfiles < 10 || $conf->maxfiles > 500) {
                $conf->maxfiles = DEFAULT_MAX_FILES;
                set_config('maxfiles', $conf->maxfiles, 'repository_openclipart');
            }

            // Now set it up.
            $this->imageheight = (int) $conf->imageheight;
            $this->maxfiles = (int) $conf->maxfiles;
        }
    }

    /**
     * ensure the configuartion is set to defaults
     */
    private function _setconfig() {
        $this->imageheight = DEFAULT_IMAGE_HEIGHT;
        $this->maxfiles = DEFAULT_MAX_FILES;
        set_config('imageheight', $this->imageheight, 'openclipart');
        set_config('maxfiles', $this->maxfiles, 'openclipart');
    }

    /**
     * Get file listing - uses Open Clipart's recent list as root 'folder'
     *
     * @param string $path
     * @param string $page
     */
    public function get_listing($path = '', $page = '') {
        $recent = OPENCLIPART_URL;
        return($this->_getfilelist($recent, get_string('recent', 'repository_openclipart')));
    }

    /**
     * Search Open clipart
     *
     * @param string $text
     */
    public function search($text, $page = 0) {
        $text = urlencode($text);
        $recent = OPENCLIPART_URL . $text;
        return($this->_getfilelist($recent, $text));
    }

    /**
     * Option names of openclipart plugin
     * @return array
     */
    public static function get_type_option_names() {
        return array('imageheight', 'maxfiles', 'pluginname');
    }

    /**
     * config form
     * @param object $mform - form object
     */
    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform);   // Name for the repo.

        $conf = get_config('openclipart');

        // Image height.
        $mform->addElement('text', 'imageheight', get_string('imageheight', 'repository_openclipart'));
        $mform->setDefault('imageheight', $conf->imageheight);
        $mform->setType('imageheight', PARAM_INT);
        $mform->addElement('static', 'stat1', '', get_string('imageheight_help', 'repository_openclipart', DEFAULT_IMAGE_HEIGHT));

        // File numbers.
        $mform->addElement('text', 'maxfiles', get_string('maxfiles', 'repository_openclipart'));
        $mform->setDefault('maxfiles', $conf->maxfiles);
        $mform->setType('maxfiles', PARAM_INT);
        $mform->addElement('static', 'stat2', '', get_string('maxfiles_help', 'repository_openclipart', DEFAULT_MAX_FILES));
    }

    /**
     * Supports file linking and copying
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL | FILE_EXTERNAL;  // Does not appear to work if I return just FILE_EXTERNAL.
        // From moodle 2.3, we support file reference.
        // See moodle docs for more information. e.g return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE;.
    }

    /**
     * internal function to get listing from openclipart.com
     *
     * @param string $request the url to call
     * @param string $vpath - the 'pretend' path to show in the interface
     * @return int
     */
    private function _getfilelist($request, $vpath) {
        global $CFG;
        require_once($CFG->libdir . '/simplepie/moodle_simplepie.php');

        $list = array();
        $list['list'] = array();
        $list['manage'] = false;            // The management interface url.
        $list['dynload'] = false;           // Dynamically loading.

        // The current path of this list.
        $list['path'] = array(
            array('name' => 'root', 'path' => ''),
            array('name' => $vpath, 'path' => ''),
        );
        $list['nologin'] = true;            // Set to true, the login link will be removed.
        $list['nosearch'] = false;          // Set to true, the search button will be removed.

        $feed = new moodle_simplepie($request);         // RSS Feed.

        if (!$feed->error()) {
            $feeditems = $feed->get_items(0, $this->maxfiles);
            foreach ($feeditems as $item) {
                // Do some fancy stuff with the filename.
                $thumbnail = $item->get_enclosure(0)->get_thumbnail();
                $filename = basename($thumbnail);
                $pattern = '|\/' . DEFAULT_THUMBNAIL_HEIGHT . 'px|';
                $source = preg_replace($pattern, '/' . $this->imageheight . 'px', $thumbnail);

                $clipartfile = array(
                    'title' => $filename,       // This is the filename not really the title.
                    'author' => $item->get_author(0)->get_name(),
                    'date' => $item->get_date('j F Y | g:i a'),
                    'thumbnail' => $thumbnail,
                    'thumbnail_width' => DEFAULT_THUMBNAIL_HEIGHT,
                    'source' => $source,
                    'url' => $item->get_permalink(),
                );
                // Stick it onto the file list.
                $list['list'][] = $clipartfile;
            }
        } else {
            debugging('Feed Error: ' . $feed->error(), DEBUG_DEVELOPER);
        }
        return $list;
    }
}

/* ?>  */
