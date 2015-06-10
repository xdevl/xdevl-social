<?php
/**
 * Plugin Name: XdevL social
 * Plugin URI: http://www.xdevl.com/blog
 * Description: Allows users to log in using social platforms
 * Version: 1.0
 * Date: 09 June 2015
 * Author: XdevL
 * Author URI: http://www.xdevl.com/blog
 * License: GPL2
 * 
 * @copyright Copyright (c) 2015, XdevL
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace xdevl\social
{

defined('ABSPATH') or die('No script kiddies please!') ;

define(__NAMESPACE__.'\PLUGIN_NAMESPACE','xdevl_social') ;

function wp_enqueue_scripts()
{
	wp_register_style(PLUGIN_NAMESPACE.'_style',plugins_url('style.css',__FILE__)) ;
	wp_enqueue_style(PLUGIN_NAMESPACE.'_style') ;
}

function login_form()
{
?>
<p class="<?php echo PLUGIN_NAMESPACE ?>">
	<label>
		Or, login using<br />
		<span class="social-button google"></span>
		<span class="social-button facebook"></span>
	</label>
</p>
<?php
}

function comment_form_default_fields($fields)
{
	$fields['author']='' ;
	$fields['email']='' ;
	$fields['url']='' ;
	return $fields ;
}

function show_password_fields($value, $profile)
{
	return !isset($profile) ;
}

add_action('login_enqueue_scripts',__NAMESPACE__.'\wp_enqueue_scripts') ;
add_action('login_form',__NAMESPACE__.'\login_form') ;
add_action('comment_form_default_fields',__NAMESPACE__.'\comment_form_default_fields') ;
add_filter('show_password_fields',__NAMESPACE__.'\show_password_fields',10,2) ;

} //end xdevl\social
?>
