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
 * openclipart class
 *
 * @package    repository_openclipart
 * @copyright  2011 Benjamin Ellis
 * @author     Benjamin Ellis benjamin.c.ellis@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('OPENCLIPART_THUMBS_PER_PAGE', 24);
define('OPENCLIPART_FILE_NS', 6);
define('OPENCLIPART_IMAGE_SIDE_LENGTH', 1024);

class openclipart {
    /**
     * Constructor
     *
     * @param string $url the url to get the clipart
     */
    public function __construct($url = '') {
        if (empty($url)) {
            $this->api = 'http://commons.openclipart.org/w/api.php';
        } else {
            $this->api = $url;
        }
        $this->_param['format'] = 'php';
        $this->_param['redirects'] = true;
        $this->_conn = new curl(array('cache' => true, 'debug' => false));
    }

    /**
     * Generate thumbnail URL from image URL.
     *
     * @param string $image_url - then clipart's url
     * @param int $orig_width - the width
     * @param int $orig_height - the height
     * @param int $thumb_width  - the required width of the thumbnail
     * @return string
     */
    public function get_thumb_url($image_url, $orig_width, $orig_height, $thumb_width = 75) {
        global $OUTPUT;

        if ($orig_width <= $thumb_width AND $orig_height <= $thumb_width) {
            return $image_url;
        } else {
            $thumb_url = '';
            $commons_main_dir = 'http://upload.openclipart.org/wikipedia/commons/';
            if ($image_url) {
                $short_path = str_replace($commons_main_dir, '', $image_url);
                $extension = pathinfo($short_path, PATHINFO_EXTENSION);
                if (strcmp($extension, 'gif') == 0) {  // No thumb for gifs.
                    return $OUTPUT->pix_url(file_extension_icon('xx.jpg', 32));
                }
                $dir_parts = explode('/', $short_path);
                $file_name = end($dir_parts);
                if ($orig_height > $orig_width) {
                    $thumb_width = round($thumb_width * $orig_width / $orig_height);
                }
                $thumb_url = $commons_main_dir . 'thumb/' . implode('/', $dir_parts) . '/' . $thumb_width . 'px-' . $file_name;
                if (strcmp($extension, 'svg') == 0) {  // Png thumb for svg-s.
                    $thumb_url .= '.png';
                }
            }
            return $thumb_url;
        }
    }

    /**
     * Search for images and return photos array.
     *
     * @param string $keyword - the saerch term/s
     * @param int $page - the page to display - not really used in this version of the plugin
     * @return array
     */
    public function search_images($keyword, $page = 0) {
        $files_array = array();
        $this->_param['action'] = 'query';
        $this->_param['generator'] = 'search';
        $this->_param['gsrsearch'] = $keyword;
        $this->_param['gsrnamespace'] = OPENCLIPART_FILE_NS;
        $this->_param['gsrlimit'] = OPENCLIPART_THUMBS_PER_PAGE;
        $this->_param['gsroffset'] = $page * OPENCLIPART_THUMBS_PER_PAGE;
        $this->_param['prop'] = 'imageinfo';
        $this->_param['iiprop'] = 'url|dimensions|mime';
        $this->_param['iiurlwidth'] = OPENCLIPART_IMAGE_SIDE_LENGTH;
        $this->_param['iiurlheight'] = OPENCLIPART_IMAGE_SIDE_LENGTH;
        // Didn't work with POST.
        $content = $this->_conn->get($this->api, $this->_param);
        $result = unserialize($content);
        if (!empty($result['query']['pages'])) {
            foreach ($result['query']['pages'] as $page) {
                $title = $page['title'];
                $file_type = $page['imageinfo'][0]['mime'];
                $image_types = array('image/jpeg', 'image/png', 'image/gif', 'image/svg+xml');
                if (in_array($file_type, $image_types)) {  // Is image.
                    $thumbnail = $this->get_thumb_url($page['imageinfo'][0]['url'],
                        $page['imageinfo'][0]['width'], $page['imageinfo'][0]['height']);
                    $source = $page['imageinfo'][0]['thumburl'];        // Upload scaled down image.
                    $extension = pathinfo($title, PATHINFO_EXTENSION);
                    if (strcmp($extension, 'svg') == 0) {               // Upload png version of svg-s.
                        $title .= '.png';
                    }
                } else {                                   // Other file types.
                    $thumbnail = '';
                    $source = $page['imageinfo'][0]['url'];
                }
                $files_array[] = array(
                    'title' => substr($title, 5),   // Chop off 'File:'.
                    'thumbnail' => $thumbnail,
                    'thumbnail_width' => 120,
                    'thumbnail_height' => 120,
                    // Plugin-dependent unique path to the file (id, url, path, etc.).
                    'source' => $source,
                    // The accessible url of the file.
                    'url' => $page['imageinfo'][0]['descriptionurl']
                );
            }
        }
        return $files_array;
    }
}

/* ?> */
