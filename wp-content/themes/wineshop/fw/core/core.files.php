<?php
/**
 * ThemeREX Framework: file system manipulations, styles and scripts usage, etc.
 *
 * @package	themerex
 * @since	themerex 1.0
 */

// Disable direct call
if ( ! defined( 'ABSPATH' ) ) { exit; }


/* File system utils
------------------------------------------------------------------------------------- */

// Return list folders inside specified folder in the child theme dir (if exists) or main theme dir
if (!function_exists('themerex_get_list_folders')) {
    function themerex_get_list_folders($folder, $only_names=true) {
        $dir = themerex_get_folder_dir($folder);
        $url = themerex_get_folder_url($folder);
        $list = array();
        global $wp_filesystem;
        if (isset($wp_filesystem) && is_object($wp_filesystem)) {
            $dir = str_replace(ABSPATH, $wp_filesystem->abspath(), $dir);
            if ($wp_filesystem->is_dir($dir)) {
                $files = $wp_filesystem->dirlist($dir);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if ($file['type'] != 'd') continue;
                        $key = $file['name'];
                        $list[$key] = $only_names ? themerex_strtoproper($key) : $url . '/' . $file['name'];
                    }
                }
            }
        } else {
            if (themerex_get_theme_option('debug_mode')=='yes')
                throw new Exception(sprintf(esc_html__('WP Filesystem is not initialized! Get list folders from folder "%s" failed', 'wineshop'), $dir));
        }
        return $list;
    }
}

// Replace site url to current site url
if (!function_exists('themerex_replace_site_url')) {
    function themerex_replace_site_url($str, $old_url) {
        static $site_url = '', $site_len = 0;
        if (is_array($str) && count($str) > 0) {
            foreach ($str as $k=>$v) {
                $str[$k] = themerex_replace_site_url($v, $old_url);
            }
        } else if (is_string($str)) {
            if (empty($site_url)) {
                $site_url = get_site_url();
                $site_len = themerex_strlen($site_url);
                if (themerex_substr($site_url, -1)=='/') {
                    $site_len--;
                    $site_url = themerex_substr($site_url, 0, $site_len);
                }
            }
            if (themerex_substr($old_url, -1)=='/') $old_url = themerex_substr($old_url, 0, themerex_strlen($old_url)-1);
            $break = '\'" ';
            $pos = 0;
            while (($pos = themerex_strpos($str, $old_url, $pos))!==false) {
                $str = themerex_unserialize($str);
                if (is_array($str) && count($str) > 0) {
                    foreach ($str as $k=>$v) {
                        $str[$k] = themerex_replace_site_url($v, $old_url);
                    }
                    $str = serialize($str);
                    break;
                } else {
                    $pos0 = $pos;
                    $chg = true;
                    while ($pos0 >= 0) {
                        if (themerex_strpos($break, themerex_substr($str, $pos0, 1))!==false) {
                            $chg = false;
                            break;
                        }
                        if (themerex_substr($str, $pos0, 5)=='http:' || themerex_substr($str, $pos0, 6)=='https:')
                            break;
                        $pos0--;
                    }
                    if ($chg && $pos0>=0) {
                        $str = ($pos0 > 0 ? themerex_substr($str, 0, $pos0) : '') . ($site_url) . themerex_substr($str, $pos+themerex_strlen($old_url));
                        $pos = $pos0 + $site_len;
                    } else
                        $pos++;
                }
            }
        }
        return $str;
    }
}

// Return list files in folder
if (!function_exists('themerex_get_list_files')) {
    function themerex_get_list_files($folder, $ext='', $only_names=false, $return_url=true) {
        $dir = themerex_get_folder_dir($folder);
        $url = themerex_get_folder_url($folder);
        $list = array();
        global $wp_filesystem;
        if (isset($wp_filesystem) && is_object($wp_filesystem)) {
            $dir = str_replace(ABSPATH, $wp_filesystem->abspath(), $dir);
            if ($wp_filesystem->is_dir($dir)) {
                $files = $wp_filesystem->dirlist($dir);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if ($file['type'] != 'f' || (!empty($ext) && themerex_get_file_ext($file['name'])!=$ext)) continue;
                        $key = themerex_substr($file['name'], 0, themerex_strrpos($file['name'], '.'));
                        if (themerex_substr($key, -4)=='.min') $key = themerex_substr($key, 0, themerex_strrpos($key, '.'));
                        $list[$key] = $only_names
                            ? themerex_strtoproper(str_replace('_', ' ', $key))
                            : ($return_url ? $url : $dir) . '/' . $file['name'];
                    }
                }
            }
        } else {
            if (themerex_get_theme_option('debug_mode')=='yes')
                throw new Exception(sprintf(esc_html__('WP Filesystem is not initialized! Get list files from folder "%s" failed', 'wineshop'), $dir));
        }
        return $list;
    }
}

// Return list files in subfolders
if (!function_exists('themerex_collect_files')) {
    function themerex_collect_files($dir, $ext=array()) {
        global $wp_filesystem;
        if (!is_array($ext)) $ext = array($ext);
        if (themerex_substr($dir, -1)=='/') $dir = themerex_substr($dir, 0, themerex_strlen($dir)-1);
        $list = array();
        if (isset($wp_filesystem) && is_object($wp_filesystem)) {
            $dir = str_replace(ABSPATH, $wp_filesystem->abspath(), $dir);
            if ($wp_filesystem->is_dir($dir)) {
                $files = $wp_filesystem->dirlist($dir);
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $pi = pathinfo($dir . '/' . $file['name']);
                        if (substr($file['name'], 0, 1) == '.')
                            continue;
                        if (is_dir($dir . '/' . $file['name']))
                            $list = array_merge($list, themerex_collect_files($dir . '/' . $file['name'], $ext));
                        else if (empty($ext) || in_array($pi['extension'], $ext))
                            $list[] = $dir . '/' . $file['name'];
                    }
                }
            }
        }
        return $list;
    }
}

// Return path to directory with uploaded images
if (!function_exists('themerex_get_uploads_dir_from_url')) {	
	function themerex_get_uploads_dir_from_url($url) {
		$upload_info = wp_upload_dir();
		$upload_dir = $upload_info['basedir'];
		$upload_url = $upload_info['baseurl'];
		
		$http_prefix = "http://";
		$https_prefix = "https://";
		
		if (!strncmp($url, $https_prefix, themerex_strlen($https_prefix)))
			$upload_url = str_replace($http_prefix, $https_prefix, $upload_url);
		else if (!strncmp($url, $http_prefix, themerex_strlen($http_prefix)))
			$upload_url = str_replace($https_prefix, $http_prefix, $upload_url);		
	
		// Check if $img_url is local.
		if ( false === themerex_strpos( $url, $upload_url ) ) return false;
	
		// Define path of image.
		$rel_path = str_replace( $upload_url, '', $url );
		$img_path = ($upload_dir) . ($rel_path);
		
		return $img_path;
	}
}

// Replace uploads url to current site uploads url
if (!function_exists('themerex_replace_uploads_url')) {	
	function themerex_replace_uploads_url($str, $uploads_folder='uploads') {
        static $uploads_url = '', $uploads_len = 0;
        if (is_array($str) && count($str) > 0) {
            foreach ($str as $k=>$v) {
                $str[$k] = themerex_replace_uploads_url($v, $uploads_folder);
            }
        } else if (is_string($str)) {
            if (empty($uploads_url)) {
                $uploads_info = wp_upload_dir();
                $uploads_url = $uploads_info['baseurl'];
                $uploads_len = themerex_strlen($uploads_url);
            }
            $break = '\'" ';
            $pos = 0;
            while (($pos = themerex_strpos($str, "/{$uploads_folder}/", $pos))!==false) {
                $pos0 = $pos;
                $chg = true;
                while ($pos0) {
                    if (themerex_strpos($break, themerex_substr($str, $pos0, 1))!==false) {
                        $chg = false;
                        break;
                    }
                    if (themerex_substr($str, $pos0, 5)=='http:' || themerex_substr($str, $pos0, 6)=='https:')
                        break;
                    $pos0--;
                }
                if ($chg) {
                    $str = ($pos0 > 0 ? themerex_substr($str, 0, $pos0) : '') . ($uploads_url) . themerex_substr($str, $pos+themerex_strlen($uploads_folder)+1);
                    $pos = $pos0 + $uploads_len;
                } else
                    $pos++;
            }
        }
        return $str;
	}
}


// Autoload templates, widgets, etc.
// Scan subfolders and require file with same name in each folder
if (!function_exists('themerex_autoload_folder')) {
	function themerex_autoload_folder($folder, $from_subfolders=true, $from_skin=true) {
		static $skin_dir = '';
		if ($folder[0]=='/') $folder = themerex_substr($file, 1);
		if ($from_skin && empty($skin_dir) && function_exists('themerex_get_custom_option')) {
			$skin_dir = themerex_esc(themerex_get_custom_option('theme_skin'));
			if ($skin_dir) $skin_dir  = 'skins/'.($skin_dir);
		} else
			$skin_dir = '-no-skins-';
		$theme_dir = get_template_directory();
		$child_dir = get_stylesheet_directory();
		$dirs = array(
			($child_dir).'/'.($skin_dir).'/'.($folder),
			($child_dir).'/'.($folder),
			($child_dir).('/' . THEMEREX_FW_DIR).($folder),
			($theme_dir).'/'.($skin_dir).'/'.($folder),
			($theme_dir).'/'.($folder),
			($theme_dir).('/' . THEMEREX_FW_DIR).($folder)
		);
		$loaded = array();
		foreach($dirs as $dir) {
			if ( is_dir($dir) ) {
                if ( is_dir($dir) ) {
                    $files = glob(sprintf("%s/*", $dir));
                    if ( is_array($files) ) {
                        foreach ($files as $file) {
                            if (substr($file, 0, 1) == '.' || in_array($file, $loaded)){
                                continue;
                            }
                            if ( is_dir( ($file) ) ) {
                                $file_name = basename($file);
                                if ($from_subfolders && file_exists( $file . '/' . ($file_name) . '.php' ) ) {
                                    $loaded[] = $file . '/' . ($file_name) . '.php';
                                    require_once( $file . '/' . ($file_name) . '.php' );
                                }
                            } else {
                                $loaded[] = $file;
                                require_once( $file );
                            }
                        }
                    }
                }
			}
		}
	}
}

// Get domain part from URL
if (!function_exists('themerex_get_domain_from_url')) {
    function themerex_get_domain_from_url($url) {
        if (($pos=strpos($url, '://'))!==false) $url = substr($url, $pos+3);
        if (($pos=strpos($url, '/'))!==false) $url = substr($url, 0, $pos);
        return $url;
    }
}

// Return file extension from full name/path
if (!function_exists('themerex_get_file_ext')) {
    function themerex_get_file_ext($file) {
        $parts = pathinfo($file);
        return $parts['extension'];
    }
}


/* File system utils
------------------------------------------------------------------------------------- */

// Init WP Filesystem
if (!function_exists('themerex_init_filesystem')) {
    add_action( 'after_setup_theme', 'themerex_init_filesystem', 0);
    function themerex_init_filesystem() {
        if( !function_exists('WP_Filesystem') ) {
            require_once( ABSPATH .'/wp-admin/includes/file.php' );
        }
        if (is_admin()) {
            $url = admin_url();
            $creds = false;
            // First attempt to get credentials.
            if ( function_exists('request_filesystem_credentials') && false === ( $creds = request_filesystem_credentials( $url, '', false, false, array() ) ) ) {
                // If we comes here - we don't have credentials
                // so the request for them is displaying no need for further processing
                return false;
            }
	
            // Now we got some credentials - try to use them.
            if ( !WP_Filesystem( $creds ) ) {
                // Incorrect connection data - ask for credentials again, now with error message.
                if ( function_exists('request_filesystem_credentials') ) request_filesystem_credentials( $url, '', true, false );
                return false;
            }
			
            return true; // Filesystem object successfully initiated.
        } else {
            WP_Filesystem();
        }
        return true;
    }
}
// Put data into specified file
if (!function_exists('themerex_fpc')) {
    function themerex_fpc($file, $data, $flag=0) {
        global $wp_filesystem;
        if (!empty($file)) {
            if (isset($wp_filesystem) && is_object($wp_filesystem)) {
                $file = str_replace(ABSPATH, $wp_filesystem->abspath(), $file);
                // Attention! WP_Filesystem can't append the content to the file!
                // That's why we have to read the contents of the file into a string,
                // add new content to this string and re-write it to the file if parameter $flag == FILE_APPEND!
                return $wp_filesystem->put_contents($file, ($flag==FILE_APPEND && $wp_filesystem->exists($file) ? $wp_filesystem->get_contents($file) : '') . $data, false);
            } else {
                if (themerex_param_is_on(themerex_get_theme_option('debug_mode')))
                    throw new Exception(sprintf(esc_html__('WP Filesystem is not initialized! Put contents to the file "%s" failed', 'wineshop'), $file));
            }
        }
        return false;
    }
}

// Get text from specified file
if (!function_exists('themerex_fgc')) {
    function themerex_fgc($file) {
        global $wp_filesystem;
        if (!empty($file)) {
            if (isset($wp_filesystem) && is_object($wp_filesystem)) {
                $file = str_replace(ABSPATH, $wp_filesystem->abspath(), $file);
                return $wp_filesystem->get_contents($file);
            } else {
                if (themerex_param_is_on(themerex_get_theme_option('debug_mode')))
                    throw new Exception(sprintf(esc_html__('WP Filesystem is not initialized! Get contents from the file "%s" failed', 'wineshop'), $file));
            }
        }
        return '';
    }
}

// Get array with rows from specified file
if (!function_exists('themerex_fga')) {
    function themerex_fga($file) {
        global $wp_filesystem;
        if (!empty($file)) {
            if (isset($wp_filesystem) && is_object($wp_filesystem)) {
                $file = str_replace(ABSPATH, $wp_filesystem->abspath(), $file);
                return $wp_filesystem->get_contents_array($file);
            } else {
                if (themerex_param_is_on(themerex_get_theme_option('debug_mode')))
                    throw new Exception(sprintf(esc_html__('WP Filesystem is not initialized! Get rows from the file "%s" failed', 'wineshop'), $file));
            }
        }
        return array();
    }
}

// Remove unsafe characters from file/folder path
if (!function_exists('themerex_esc')) {	
	function themerex_esc($file) {
        return sanitize_file_name($file);
	}
}


/* Check if file/folder present in the child theme and return path (url) to it. 
   Else - path (url) to file in the main theme dir
------------------------------------------------------------------------------------- */

// Detect file location with next algorithm:
// 1) check in the skin folder in the child theme folder (optional, if $from_skin==true)
// 2) check in the child theme folder
// 3) check in the framework folder in the child theme folder
// 4) check in the skin folder in the main theme folder (optional, if $from_skin==true)
// 5) check in the main theme folder
// 6) check in the framework folder in the main theme folder
if (!function_exists('themerex_get_file_dir')) {
    function themerex_get_file_dir($file, $return_url=false) {

        if ($file[0] == '/') $file = themerex_substr($file, 1);

        // Use new WordPress functions (if present)
        if ( function_exists('get_theme_file_path') ) {
            $dir = get_theme_file_path($file);

            if (file_exists($dir) ) {
                $dir = ($return_url ? get_theme_file_uri($file) : $dir);
            } else {
                $file = THEMEREX_FW_DIR . '/' . $file;
                $dir = get_theme_file_path($file);
                $dir = ($return_url ? get_theme_file_uri($file) : $dir);
            }

            // Otherwise (on WordPress older then 4.7.0)
        } else {
            $theme_dir = get_template_directory();
            $theme_url = get_template_directory_uri();
            $child_dir = get_stylesheet_directory();
            $child_url = get_stylesheet_directory_uri();
            $dir = '';
            if (file_exists(($child_dir) . '/' . ($file)))
                $dir = ($return_url ? $child_url : $child_dir) . '/' . ($file);
            else if (file_exists(($child_dir) . '/' . (THEMEREX_FW_DIR) . '/' . ($file)))
                $dir = ($return_url ? $child_url : $child_dir) . '/' . (THEMEREX_FW_DIR) . '/' . ($file);
            else if (file_exists(($theme_dir) . '/' . ($file)))
                $dir = ($return_url ? $theme_url : $theme_dir) . '/' . ($file);
            else if (file_exists(($theme_dir) . '/' . (THEMEREX_FW_DIR) . '/' . ($file)))
                $dir = ($return_url ? $theme_url : $theme_dir) . '/' . (THEMEREX_FW_DIR) . '/' . ($file);
        }

        return $dir;
    }
}

if (!function_exists('themerex_get_file_url')) {	
	function themerex_get_file_url($file) {
		return themerex_get_file_dir($file, true);
	}
}

// Detect folder location with same algorithm as file (see above)
if (!function_exists('themerex_get_folder_dir')) {	
	function themerex_get_folder_dir($folder, $return_url=false, $from_skin=false) {
		static $skin_dir = '';
		if ($folder[0]=='/') $folder = themerex_substr($folder, 1);
		if ($from_skin && empty($skin_dir) && function_exists('themerex_get_custom_option')) {
			$skin_dir = themerex_esc(themerex_get_custom_option('theme_skin'));
			if ($skin_dir) $skin_dir  = 'skins/'.($skin_dir);
		}
		$theme_dir = get_template_directory();
		$theme_url = get_template_directory_uri();
		$child_dir = get_stylesheet_directory();
		$child_url = get_stylesheet_directory_uri();
		$dir = '';
		if (!empty($skin_dir) && file_exists(($child_dir).'/'.($skin_dir).'/'.($folder)))
			$dir = ($return_url ? $child_url : $child_dir).'/'.($skin_dir).'/'.($folder);
		else if (is_dir(($child_dir).'/'.($folder)))
			$dir = ($return_url ? $child_url : $child_dir).'/'.($folder);
		else if (is_dir(($child_dir).('/' . THEMEREX_FW_DIR).($folder)))
			$dir = ($return_url ? $child_url : $child_dir).('/' . THEMEREX_FW_DIR).($folder);
		else if (!empty($skin_dir) && file_exists(($theme_dir).'/'.($skin_dir).'/'.($folder)))
			$dir = ($return_url ? $theme_url : $theme_dir).'/'.($skin_dir).'/'.($folder);
		else if (file_exists(($theme_dir).'/'.($folder)))
			$dir = ($return_url ? $theme_url : $theme_dir).'/'.($folder);
		else if (file_exists(($theme_dir).('/' . THEMEREX_FW_DIR).($folder)))
			$dir = ($return_url ? $theme_url : $theme_dir).('/' . THEMEREX_FW_DIR).($folder);
		return $dir;
	}
}

if (!function_exists('themerex_get_folder_url')) {	
	function themerex_get_folder_url($folder) {
		return themerex_get_folder_dir($folder, true);
	}
}

// Detect skin version of the social icon (if exists), else return it from template images directory
if (!function_exists('themerex_get_socials_dir')) {	
	function themerex_get_socials_dir($soc, $return_url=false) {
		return themerex_get_file_dir('images/socials/' . themerex_esc($soc) . (themerex_strpos($soc, '.')===false ? '.png' : ''), $return_url, true);
	}
}

if (!function_exists('themerex_get_socials_url')) {	
	function themerex_get_socials_url($soc) {
		return themerex_get_socials_dir($soc, true);
	}
}

// Detect theme version of the template (if exists), else return it from fw templates directory
if (!function_exists('themerex_get_template_dir')) {	
	function themerex_get_template_dir($tpl) {
		return themerex_get_file_dir('templates/' . themerex_esc($tpl) . (themerex_strpos($tpl, '.php')===false ? '.php' : ''));
	}
}
?>