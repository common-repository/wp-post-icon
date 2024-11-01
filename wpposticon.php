<?php
/*
Plugin Name: WP Post Icon
Plugin URI: http://www.linewbie.com/wordpress-plugins/
Description: Enables blogs authors to upload and select topic icons or images and have it automatically show up in their blog post.
Version: 1.0
Author: linewbie
Author URI: http://www.linewbie.com/wordpress-plugins/
*/

/*
Copyright (C) 2007 Linewbie.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

# WP-Post-Icon paths, With trailing slash.
define('WPIDIR', dirname(__FILE__) . '/');
define('WPIURL', get_option('siteurl'));
define('IMGROOT', WPIDIR . 'img/');
define('WPICSS', WPIURL . '/wp-content/plugins/wp-post-icon/css/');
define('WPIIMG', WPIURL . '/wp-content/plugins/wp-post-icon/img/');

class WPPostIcon {
	var $version = '1.0';
	
	# is full-uninstall?
	var $full_uninstall = false;
	
	# __construct()
	function WPPostIcon() {
		global $wpdb;
		
		# table names init
		$this -> db = array(
			'posts_pictures' => $wpdb -> prefix . 'wpi_posts_pictures'
		);
		
		# is installed?
		$this -> installed = get_option('wpi_version') == $this -> version;
		
		# actions
		add_action('activate_wp-post-icon/wpposticon.php', array(&$this, 'install'));		// install
		add_action('deactivate_wp-post-icon/wpposticon.php', array(&$this, 'uninstall'));	// uninstall
		add_action('wp_head', array(&$this, 'style'));										// load style
		add_action('dbx_post_sidebar', array(&$this, 'dropdown'));							// dropdown
		add_action('publish_post', array(&$this, 'save'));									// publish
		add_action('save_post', array(&$this, 'save'));										// save
		add_action('delete_post', array(&$this, 'delete'));									// delete
		add_action('admin_menu', array(&$this, 'adminMenu'));								// adminMenu
		
		# filters
		add_filter('the_content', array(&$this, 'the_content'));							// content
	}
	
	# install plugin
	function install() {
		global $wpdb;
		
		if (file_exists(ABSPATH . '/wp-admin/upgrade-functions.php'))
      		require_once(ABSPATH . '/wp-admin/upgrade-functions.php');
    	else
      		require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
			
		if (!$this -> installed) {
			# wp_wpi_posts_pictures
			dbDelta("CREATE TABLE `{$this -> db['posts_pictures']}` (
						`ID` BIGINT UNSIGNED NOT NULL DEFAULT 0,
						`picture` VARCHAR(255) NOT NULL,
						PRIMARY KEY(`ID`)
					)");
			# options
			add_option('wpi_version', $this -> version);
			add_option('wpi_position', 'right');
			$this -> installed = true;
		}
	}
	
	# uninstall plugin
	function uninstall() {
		global $wpdb;
		
		if ($this -> full_uninstall) {
			# delete tables
			foreach ($this -> db as $table) {
				$wpdb -> query("DROP TABLE `{$table}`");
			}
			# delete options
			delete_option('wpi_version');
			delete_option('wpi_position');
		}
	}
	
	# called before content is shown
	function the_content($content) {
		global $wpdb,$post;
		$postID = $post -> ID;
		if ($result = $wpdb -> get_results("SELECT * FROM `{$this -> db['posts_pictures']}` WHERE `ID`={$postID}")) {
			$position = strtolower(get_option('wpi_position'));
			$content = '<img class="wpi_img_' . $position . '" src="' . WPIIMG . $result[0] -> picture . '" title="' . $result[0] -> picture . '" />' . $content;
		}
		return $content;
	}
	
	# load style when page is loaded
	function style() {
		echo "
		<style>
		<!--
			.wpi_img_left,.wpi_img_right {
				margin-bottom:15px;
				background:#eee;
				padding:2px;
				border:1px solid #d0d0d0;
			}
			.wpi_img_left {
				margin-right:15px;
				float:left;
			}
			.wpi_img_right {
				margin-left:15px;
				float:right;
			}
			*+html .wpi_img_left {
				margin-top:20px;
			}
			*+html .wpi_img_right {
				margin-top:20px;
			}
		-->
		</style>
		";
	}
	
	# show dropdown for selectting a picture
	function dropdown() {
		echo '
		<fieldset id="wpi_dropdown_pictures" class="dbx-box">
			<h3 class="dbx-handle">Select Picture</h3>
			<div class="dbx-content">
				<p>
					<select name="select_picture">
						<option value="">Select Picture ... </option>
		';
		if ($dir = opendir(IMGROOT)) {
			while(false !== ($file = readdir($dir))) {
				if ($file != '.' && $file != '..') {
					$extension = strtolower(substr($file, strrpos($file, '.')+1));
					if ($extension === 'jpg' || $extension === 'jpeg' || $extension === 'gif' || $extension === 'png') {
						echo '<option value="' . $file . '">' . $file . '</option>' . "\n";
					}
				}
			}
			closedir($dir);
		}
		echo '
					</select>
				</p>
			</div>
		</fieldset>
		';
	}
	
	# called when post is published or saved
	function save($postID) {
		global $wpdb;
		if (isset($_POST['select_picture']) && !empty($_POST['select_picture'])) {
			$is_exists = $wpdb -> get_var("SELECT COUNT(*) FROM `{$this -> db['posts_pictures']}` WHERE `ID`={$postID}");
			if ($is_exists) {
				$wpdb -> query("UPDATE `{$this -> db['posts_pictures']}` SET `picture`='{$_POST['select_picture']}' WHERE `ID`={$postID}");
			} else {
				$wpdb -> query("INSERT INTO `{$this -> db['posts_pictures']}` VALUES({$postID},'{$_POST['select_picture']}')");
			}
		}
	}
	
	# called when post is deleted
	function delete($postID) {
		global $wpdb;
		$wpdb -> query("DELETE FROM `{$this -> db['posts_pictures']}` WHERE `ID`={$postID}");
	}
	
	# adds the WP-Post-Icon item to menu
	function adminMenu() {
		add_options_page('WP-Post-Icon', 'WP-Post-Icon', 1, 'WP-Post-Icon', array(&$this, 'admin'));
	}
	
	function admin() {
		if (@$_GET['wpi'] == 'save') {
			$position = strtolower($_GET['position']);
			if ($position === 'left' || $position === 'right') {
				update_option('wpi_position', $position);
				echo '<div id="message" class="updated fade"><p>WP-Post-Icon Position Configuration <strong>Saved</strong>.</p></div>';
			} else {
				echo '<div id="message" class="updated fade"><p>Error! Unkown Position.</p></div>';
			}
		}
		if (@$_POST['wpi'] == 'upload') {
			$file_size_max = 1000000;
			if ($_FILES['image']['size'] > $file_size_max) {
				echo '<div id="message" class="updated fade"><p>Error! Picture is too large, the max size is 1M.</p></div>';
			} else {
				$filename = $_FILES['image']['name'];
				$extension = strtolower(substr($filename, strrpos($filename, '.')+1));
				if ($extension === 'jpg' || $extension === 'jpeg' || $extension === 'gif' || $extension === 'png') {
					if (file_exists(IMGROOT . $_FILES['image']['name'])) {
						echo '<div id="message" class="updated fade"><p>Error! Picture is exist.</p></div>';
					} else {
						@move_uploaded_file($_FILES['image']['tmp_name'], IMGROOT . $_FILES['image']['name']);
						echo '<div id="message" class="updated fade"><p>Upload Picture <strong>Success</strong>.</p></div>';
					}
				} else {
					echo '<div id="message" class="updated fade"><p>Error! Picture must be jpg(jpeg), gif or png.</p></div>';
				}
			}
		}
		echo '
		<div class="wrap">
			<h2>WP-Post-Icon Admin</h2>
			<form name="wpi_admin" method="get" action="">
				<table>
					<tbody>
						<tr>
		';
		if (strtolower(get_option("wpi_position")) === 'right') {
			echo '
			<td><input name="position" type="radio" value="left" /></td>
			<td>Left-Top</td>
			<td><input name="position" type="radio" value="right" checked="checked" /></td>
			<td>Right-Top</td>
			';
		} else {
			echo '
			<td><input name="position" type="radio" value="left" checked="checked" /></td>
			<td>Left-Top</td>
			<td><input name="position" type="radio" value="right" /></td>
			<td>Right-Top</td>
			';
		}
		echo '
						</tr>
					</tbody>
				</table>
				<br />
				<p class="submit">
					<input type="hidden" name="page" value="' . $_GET['page'] . '" />
					<input type="hidden" name="wpi" value="save" />
					<input type="submit" value="Save Configuration >>" />
				</p>
			</form>
			<br />
			<form name="wpi_upload" method="post" enctype="multipart/form-data" action="">
				<table>
					<tbody>
						<tr>
							<th scope="row"><label for="upload">Picture</label></th>
							<td><input id="upload" type="file" name="image" style="width:600px;" /></td>
						</tr>
					</tbody>
				</table>
				<br />
				<p class="submit">
					<input type="hidden" name="page" value="' . $_GET['page'] . '" />
					<input type="hidden" name="wpi" value="upload" />
					<input type="submit" value="Upload >>" />
				</p>
			</form>
		</div>
		';
	}
}

$wpposticon = & new WPPostIcon();