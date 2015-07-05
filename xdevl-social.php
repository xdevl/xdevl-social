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

// Form setting
define(__NAMESPACE__.'\PLUGIN_SETTINGS',PLUGIN_NAMESPACE) ;
define(__NAMESPACE__.'\PLUGIN_SETTINGS_ACTIVE_PROVIDERS',PLUGIN_NAMESPACE.'_activeproviders') ;
define(__NAMESPACE__.'\PROVIDER_SETTINGS',PLUGIN_NAMESPACE.'_provider') ;
define(__NAMESPACE__.'\PROVIDER_ACTIVE','active') ;
define(__NAMESPACE__.'\PROVIDER_PUBLIC_KEY','publickey') ;
define(__NAMESPACE__.'\PROVIDER_PRIVATE_KEY','privatekey') ;
define(__NAMESPACE__.'\PROVIDER_EXTRA','extra') ;

// Others
define(__NAMESPACE__.'\PHP_EXTENSION','.php') ;
define(__NAMESPACE__.'\HYBRIDAUTH_DIR',plugin_dir_path(__FILE__).'hybridauth/hybridauth/') ;

function list_providers()
{
	$providers=array() ;
	if($directory=opendir(HYBRIDAUTH_DIR.'Hybrid/Providers'))
	{
		while(($file=readdir($directory))!==false)
			if(strpos($file,PHP_EXTENSION)==strlen($file)-strlen(PHP_EXTENSION))
				array_push($providers,substr($file,0,strlen(PHP_EXTENSION)*-1)) ;
		closedir($directory) ;
		sort($providers,SORT_STRING) ;
	}
	return $providers ;
}

function providers_panel()
{
	$providersPanel='' ;
	$activeProviders=get_option(PLUGIN_SETTINGS_ACTIVE_PROVIDERS) ;
	if(is_array($activeProviders))
		foreach($activeProviders as $provider)
			$providersPanel.='<a href="'.wp_login_url().'?provider='.$provider.'"><img src="'.plugins_url('img/'.$provider.'.png',__FILE__).'" />' ;
	return $providersPanel ;
}

class ProviderSettings
{
	function __construct($provider)
	{
		$this->provider=$provider ;
		$this->options=get_option($this->getSettingsGroup()) ;
	}
	
	function getProvider()
	{
		return $this->provider ;
	}
	
	function getSettingsGroup()
	{
		return PROVIDER_SETTINGS.'_'.$this->provider ;
	}
	
	function getSettingName($setting)
	{
		return $this->getSettingsGroup().'['.$setting.']' ;
	}
	
	function getSetting($setting,$default)
	{
		return is_array($this->options) && array_key_exists($setting,$this->options)
				&& !empty($this->options[$setting])?$this->options[$setting]:$default ;
	}
	
	function isActive()
	{
		$activeProviders=get_option(PLUGIN_SETTINGS_ACTIVE_PROVIDERS) ;
		return is_array($activeProviders) && in_array($this->getProvider(),$activeProviders) ;
	}
	
	function inputCallback($setting)
	{
		echo '<input name="'.$this->getSettingName($setting).'" type="text" size="64" value="'.htmlspecialchars($this->getSetting($setting,'')).'" />' ;
	}
	
	function checkboxCallback()
	{
		echo '<fieldset><label><input name="'.PLUGIN_SETTINGS_ACTIVE_PROVIDERS.'[]" type="checkbox" value="'.$this->getProvider().'" '.
				($this->isActive()==true?'checked':'').' /> Active</label></fieldset>' ;
	}
	
	function getConfig()
	{
		$config=json_decode($this->getSetting(PROVIDER_EXTRA,'{}'),true) ;
		$config=$config?$config:array() ;
		$config['enabled']=$this->isActive() ;
		$config['keys']=array('id'=>$this->getSetting(PROVIDER_PUBLIC_KEY,''),'secret'=> $this->getSetting(PROVIDER_PRIVATE_KEY,'')) ;
		return $config ;
	}
}

function admin_init()
{
	foreach(list_providers() as $provider)
	{
		$providerSettings=new ProviderSettings($provider) ;
		
		add_settings_section($providerSettings->getSettingsGroup(),$providerSettings->getProvider(),null,PLUGIN_SETTINGS) ;
		
		add_settings_field(PLUGIN_SETTINGS_ACTIVE_PROVIDERS.'[]','Status:',array($providerSettings,'checkboxCallback'),PLUGIN_SETTINGS,$providerSettings->getSettingsGroup()) ;
		add_settings_field($providerSettings->getSettingName(PROVIDER_PUBLIC_KEY),'Public key:',array($providerSettings,'inputCallback'),PLUGIN_SETTINGS,$providerSettings->getSettingsGroup(),PROVIDER_PUBLIC_KEY) ;
		add_settings_field($providerSettings->getSettingName(PROVIDER_PRIVATE_KEY),'Private key:',array($providerSettings,'inputCallback'),PLUGIN_SETTINGS,$providerSettings->getSettingsGroup(),PROVIDER_PRIVATE_KEY) ;
		add_settings_field($providerSettings->getSettingName(PROVIDER_EXTRA),'Extra:',array($providerSettings,'inputCallback'),PLUGIN_SETTINGS,$providerSettings->getSettingsGroup(),PROVIDER_EXTRA) ;
		
		register_setting(PLUGIN_SETTINGS,$providerSettings->getSettingsGroup(),__NAMESPACE__.'\sanitize_callback') ;
		register_setting(PLUGIN_SETTINGS,PLUGIN_SETTINGS_ACTIVE_PROVIDERS) ;
	}
}

function admin_menu()
{
	add_options_page('XdevL social setup','XdevL social','manage_options',PLUGIN_SETTINGS, __NAMESPACE__.'\options_page') ;
}

function sanitize_callback($providerSettings)
{
	if(array_key_exists(PROVIDER_EXTRA,$providerSettings) && !empty($providerSettings[PROVIDER_EXTRA]))
		if(!json_decode($providerSettings[PROVIDER_EXTRA],true))
			add_settings_error(PROVIDER_EXTRA,PROVIDER_EXTRA,json_Last_error_msg()) ;
			
	return $providerSettings ;
}

function options_page()
{
?>
<div>
	<h2>XdevL social setup</h2>
	<form method="post" action="options.php">
		<?php
			settings_fields(PLUGIN_SETTINGS) ;
			do_settings_sections(PLUGIN_SETTINGS) ;
			submit_button() ; ?>
	</form>
</div>

<?php
}

function get_HybridAuth_config()
{
	$providers=array() ;
	foreach(list_providers() as $provider)
		$providers[$provider]=(new ProviderSettings($provider))->getConfig() ;
	
	return array('base_url'=>plugins_url('hybridauth/hybridauth/',__FILE__),'providers'=>$providers) ;
}

function wp_enqueue_scripts()
{
	wp_register_style(PLUGIN_NAMESPACE.'_style',plugins_url('style.css',__FILE__)) ;
	wp_enqueue_style(PLUGIN_NAMESPACE.'_style') ;
}

// TODO: make use of redirect_to
function login_form()
{
	return '<p class="'.PLUGIN_NAMESPACE.'"><label>Or, authenticate using<br />'.providers_panel().'</label></p>' ;
}

function echo_login_form()
{
	echo login_form() ;
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
			'<a href="'.wp_login_url(get_permalink()).'&provider=Google"><span class="social-button google"></span></a>'.
			'<a href="'.wp_login_url(get_permalink()).'&provider=Facebook"><span class="social-button facebook"></span></a>'.
			'<a href="'.wp_login_url(get_permalink()).'&provider=GitHub"><span class="social-button github"></span></a>' ;
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

// TODO: this function is unsafe, we should only authenticate a user based on his email address if it has been verified first
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
		if(add_user_meta($result,get_provider_id_meta_key($provider),$userProfile->identifier)
				&& add_user_meta($result,get_provider_profile_meta_key($provider),json_encode($userProfile)))
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
		try {
			require_once(HYBRIDAUTH_DIR.'Hybrid/Auth.php') ;
			$hybridauth=new \Hybrid_Auth(get_HybridAuth_config()) ;
			$adapter=$hybridauth->authenticate($_GET['provider']) ;
			$userProfile=$adapter->getUserProfile() ;
			
			$users=get_users(array('meta_key'=>get_provider_id_meta_key($provider),'meta_value'=>$userProfile->identifier)) ;
			if(count($users)!=1)
				return create_user($provider,$userProfile) ;
			else return $users[0] ;
			
			/*$lookup=user_lookup($provider,$userProfile) ;
			if($lookup[0]==false)
				return create_user($provider,$userProfile) ;
			// TODO: update user info if it has changed
			else return $lookup[0] ;*/
			
		} catch(\Exception $e) {
			return new \WP_Error('login_failed', __( '<strong>ERROR</strong>: '.$e->getMessage())) ;
		}
	}	
}

add_action('wp_enqueue_scripts',__NAMESPACE__.'\wp_enqueue_scripts') ;
add_action('login_enqueue_scripts',__NAMESPACE__.'\wp_enqueue_scripts') ;
add_action('login_form',__NAMESPACE__.'\echo_login_form') ;
add_filter('login_form_middle',__NAMESPACE__.'\login_form') ;
add_action('comment_form_default_fields',__NAMESPACE__.'\comment_form_default_fields') ;
add_action('comment_form_defaults',__NAMESPACE__.'\comment_form_defaults') ;
add_filter('show_password_fields',__NAMESPACE__.'\show_password_fields',10,2) ;
add_filter('authenticate',__NAMESPACE__.'\authenticate',10,3) ;

if(is_admin())
{
	add_action('admin_menu',__NAMESPACE__.'\admin_menu') ;
	add_action('admin_init',__NAMESPACE__.'\admin_init') ;
}

} //end xdevl\social
?>
