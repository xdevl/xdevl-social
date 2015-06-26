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
		<a href="<?php echo wp_login_url(); ?>?provider=Google"><span class="social-button google"></span></a>
		<a href="<?php echo wp_login_url(); ?>?provider=Facebook"><span class="social-button facebook"></span></a>
	</label>
</p>
<?php
}

function comment_form_default_fields($fields)
{
	unset($fields['author']) ;
	unset($fields['email']) ;
	unset($fields['url']) ;
	
	return $fields ;
}

function comment_form_defaults($defaults)
{
	$defaults['must_log_in']='<p class="must-log-in '.PLUGIN_NAMESPACE.'">'.
			'To comment, <a href="'.wp_login_url(apply_filters('the_permalink',get_permalink( ))).'">log in</a> or authenticate using one of the following providers:<br />'.
			'<a href="'.wp_login_url().'?provider=Google"><span class="social-button google"></span></a>'.
			'<a href="'.wp_login_url().'?provider=Facebook"><span class="social-button facebook"></span></a>' ;
	return $defaults ;
}

function show_password_fields($value, $profile)
{
	return !isset($profile) ;
}

function get_provider_id_meta_key($provider)
{
	return PLUGIN_NAMESPACE.'_'.$provider.'_id' ;
}

function get_provider_profile_meta_key($provider)
{
	return PLUGIN_NAMESPACE.'_'.$provider.'_profile' ;
}

abstract class MatchType
{
	const NONE=0 ;
	const PROVIDER=1 ;
	const EMAIL=2 ;
}

/**
 * Returns an array of (WP_User, MatchType) 
 **/ 
function user_lookup($provider, $userProfile)
{
	global $wpdb ;
	$result=$wpdb->get_results($wpdb->prepare("SELECT a.ID, b.meta_key FROM $wpdb->users AS a INNER JOIN $wpdb->usermeta AS b ON a.ID=b.user_id"
		.' WHERE (a.user_email=%s AND b.meta_key=\'first_name\') OR (b.meta_key=%s AND b.meta_value=%s)'
		.' ORDER BY b.meta_key',
		array($userProfile->email,get_provider_id_meta_key($provider),$userProfile->identifier))) ;

	$count=count($result) ;
	// The user has never logged in before
	if($count==0)
		return array(false,MatchType::NONE) ;
	// There are two different existing accounts for the same user
	// merge them
	else if($count==2 && $result[0]->ID!=$result[1]->ID)
		wp_delete_user($result[0]->ID,$result[1]->ID) ;
	
	// The user has never logged in before using this provider
	// but his email address is in the system
	// OR
	// The user has already logged in with this provider
	// but his email address has changed
	return array(get_user_by('id',$result[0]->ID),($count==1 && $result[0]->meta_key=='first_name')?MatchType::EMAIL:MatchType::PROVIDER) ;
}

/**
 * Creates a new user
 **/
function create_user($provider, $userProfile)
{
	$result=wp_insert_user(array(
		'user_login' => $provider.'_'.$userProfile->identifier,
		'user_pass' => wp_hash_password(wp_generate_password()),
		'display_name' => $userProfile->displayName,
		'user_email' => $userProfile->email,
		'user_url' => empty($userProfile->webSiteUrl)?$userProfile->profileURL:$userProfile->webSiteUrl,
		'first_name' => $userProfile->firstName, // meta data
		'last_name' => $userProfile->lastName, // meta data
		'nickname' => $userProfile->firstName, // meta data
		'description' => $userProfile->description // meta data
	)) ;
	
	if(!is_wp_error($result))
	{
		if(add_user_meta($userId,get_provider_id_meta_key($provider),$userProfile->identifier)
				&& add_user_meta($userId,get_provider_profile_meta_key($provider),json_encode($userProfile)))
			return get_user_by('id',$result) ;
		else return new \WP_Error('login_failed', __( '<strong>ERROR</strong>: Unable to save user meta data')) ;
	}
	else return $result ;
}

function authenticate($user, $username, $password)
{
	// TODO: use query_var instead
	if(isset($_GET['provider'])) 
	{
		$provider=$_GET['provider'] ;
		$hybridAuthDir=plugin_dir_path(__FILE__).'hybridauth/hybridauth' ;
		try {
			require_once($hybridAuthDir.'/Hybrid/Auth.php') ;
			$hybridauth=new \Hybrid_Auth($hybridAuthDir.'/config.php') ;
			$adapter=$hybridauth->authenticate($_GET['provider']) ;
			$userProfile=$adapter->getUserProfile() ;
			
			$lookup=user_lookup($provider,$userProfile) ;
			if($lookup[0]==false)
				return create_user($provider,$userProfile) ;
			// TODO: update user info if it has changed
			else return $lookup[0] ;
			
		} catch(Exception $e) {
			return new \WP_Error('login_failed', __( '<strong>ERROR</strong>: Login failed, please try again')) ;
		}
	}	
}

add_action('wp_enqueue_scripts',__NAMESPACE__.'\wp_enqueue_scripts') ;
add_action('login_enqueue_scripts',__NAMESPACE__.'\wp_enqueue_scripts') ;
add_action('login_form',__NAMESPACE__.'\login_form') ;
add_action('comment_form_default_fields',__NAMESPACE__.'\comment_form_default_fields') ;
add_action('comment_form_defaults',__NAMESPACE__.'\comment_form_defaults') ;
add_filter('show_password_fields',__NAMESPACE__.'\show_password_fields',10,2) ;
add_filter('authenticate',__NAMESPACE__.'\authenticate',10,3) ;

} //end xdevl\social
?>
