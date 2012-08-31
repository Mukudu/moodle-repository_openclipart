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
 * Version details
 *
 * @package    repository_openclipart
 * @copyright  2011 Benjamin Ellis
 * @author     Benjamin Ellis benjamin.c.ellis@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('OPENCLIPART_URL', 'http://openclipart.org/media/feed/rss/');			//defaul height in pixels
define('DEFAULT_IMAGE_HEIGHT', 320);			//default height in pixels
define('DEFAULT_THUMBNAIL_HEIGHT', 90);			//default height in pixels - default thumbnails from Open Clipart
define('DEFAULT_MAX_FILES', 50);			//default height in pixels - default thumbnails from Open Clipart

/**
 * repository_openclipart class
 * This is a class used to browse images from openclipart
 *
 * @since 2.0
 * @package    repository_openclipart
 * @copyright  2011 Benjamin Ellis
 * @author     Benjamin Ellis benjamin.c.ellis@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

        $conf = get_config('repository_openclipart');
        if (!isset($conf->imageheight)) {					//empty object??
	        $this->_setconfig();
        }else{
	        //checks for for silly inputs in the configuration
	        if ($conf->imageheight < 10 || $conf->imageheight > 4068) {
		        error_log('Resetting image height');
		        $conf->imageheight = DEFAULT_IMAGE_HEIGHT;
		        set_config('imageheight', $conf->imageheight, 'repository_openclipart');
	        }

	        if ($conf->maxfiles < 10 || $conf->maxfiles > 500) {
		        error_log('Resetting max files');
		        $conf->maxfiles = DEFAULT_MAX_FILES;
		        set_config('maxfiles', $conf->maxfiles, 'repository_openclipart');
	        }

	        //now setup
	        $this->imageheight = (int) $conf->imageheight;
	        $this->maxfiles = (int) $conf->maxfiles;
        }
    }


    /**
     * ensure the configuartion is set to defaults
     */
    private function _setconfig() {
	    error_log("Resetting config");
        $this->imageheight = DEFAULT_IMAGE_HEIGHT;
        $this->maxfiles = DEFAULT_MAX_FILES;
        set_config('imageheight', $this->imageheight, 'repository_openclipart');
        set_config('maxfiles', $this->maxfiles, 'repository_openclipart');
    }

    /**
     * Get file listing - uses Open Clipart's recent list as root 'folder'
     *
     * @param string $path
     * @param string $page
     */
    public function get_listing($path = '', $page = '') {
        $recent = OPENCLIPART_URL;
        return($this->_getfilelist($recent, get_string('recent','repository_openclipart')));
    }

    /**
     * Search Open clipart
     *
     * @param string $text
     */
    public function search($text) {
	    $text = urlencode($text);
        $recent = OPENCLIPART_URL . $text;
        return($this->_getfilelist($recent,$text));
    }

//     /**
//      * this function must be static
//      *
//      * @return array
//      */
//     public static function get_instance_option_names() {
//         //return array('account');
//     }

//     /**
//      * Instance config form
//      */
//     public function instance_config_form(&$mform) {
//         //$mform->addElement('text', 'account', get_string('account', 'repository_demo'), array('value'=>'','size' => '40'));
//     }

//     /**
//      * Type option names
//      *
//      * @return array
//      */
//     public static function get_type_option_names() {
//         //return array('api_key');
//     }

//     /**
//      * will be called when installing a new plugin in admin panel
//      *
//      * @return bool
//      */
//     public static function plugin_init() {
//         $result = true;
//         // do nothing
//         return $result;
//     }

    /**
     * Option names of dropbox plugin
     * @return array
     */
    public static function get_type_option_names() {
        return array('imageheight', 'maxfiles', 'pluginname');
    }

    /**
     * config form  @TODO saving is not happening......
     */
    public function type_config_form($mform) {
        parent::type_config_form($mform);			//name for the repo

	    $conf = get_config('repository_openclipart');
	    if (!isset($conf->imageheight)) {			//then we reset config
			$this->_setconfig();					//this will set the params
		}

		//image height
        $mform->addElement('text', 'imageheight', get_string('imageheight', 'repository_openclipart'));
        $mform->setDefault('imageheight', $conf->imageheight);
        $mform->setType('imageheight', PARAM_INT);
        $mform->addElement('static', 'stat1', '', get_string('imageheight_help', 'repository_openclipart', DEFAULT_IMAGE_HEIGHT));

        //file numbers
        $mform->addElement('text', 'maxfiles', get_string('maxfiles', 'repository_openclipart'));
        $mform->setDefault('maxfiles', $conf->maxfiles);
        $mform->setType('maxfiles', PARAM_INT);
        $mform->addElement('static', 'stat2', '', get_string('maxfiles_help', 'repository_openclipart',DEFAULT_MAX_FILES));

    }


    /**
     * Supports file linking and copying
     *
     * @return int
     */
    public function supported_returntypes() {
	    return FILE_INTERNAL | FILE_EXTERNAL;		//does not appear to work if I return just FILE_EXTERNAL

        // From moodle 2.3, we support file reference
        // see moodle docs for more information
        //return FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE;

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
        require_once($CFG->libdir.'/simplepie/moodle_simplepie.php');

        $list = array();
        $list['list'] = array();

        // the management interface url
        $list['manage'] = false;
        // dynamically loading
        $list['dynload'] = false;

        // the current path of this list.
        $list['path'] = array(
            array('name'=>'root', 'path'=>''),
            array('name'=>$vpath, 'path'=>''),
        );
        // set to true, the login link will be removed
        $list['nologin'] = true;
        // set to true, the search button will be removed
        $list['nosearch'] = false;

        $feed = new moodle_simplepie($request);

            if (!$feed->error()) {
                $feeditems = $feed->get_items(0, $this->maxfiles);
                foreach($feeditems as $item){
                        //do some fancy stuff with the filename
                        $thumbnail = $item->get_enclosure(0)->get_thumbnail();
                        $filename = basename($thumbnail);
                        $pattern = '|\/' . DEFAULT_THUMBNAIL_HEIGHT . 'px|';
                    $source = preg_replace($pattern, '/'. $this->imageheight . 'px', $thumbnail);

                    $clipartfile = array(
                        'title'     => $filename,			//this is the filename not really the title
                        'author'    => $item->get_author(0)->get_name(),
                        'date'  =>  $item->get_date('j F Y | g:i a'),
                        'thumbnail'=> $thumbnail,
                        'thumbnail_width' => DEFAULT_THUMBNAIL_HEIGHT,
                        'source'=> $source,
                        'url'=> $item->get_permalink(),
                    );
                    //stick it onto the file list
                    $list['list'][] = $clipartfile;
                }
        }else{
                debugging('Feed Error: ' . $feed->error(), DEBUG_DEVELOPER);
        }

        return $list;
    }
}
/* ?>  */
