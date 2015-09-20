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
define(__NAMESPACE__.'\PLUGIN_SETTINGS_FOUNDATION_ALERT',PLUGIN_SETTINGS.'_foundationalert') ;
define(__NAMESPACE__.'\PLUGIN_SETTINGS_ACTIVE_PROVIDERS',PLUGIN_NAMESPACE.'_activeproviders') ;
define(__NAMESPACE__.'\PROVIDER_SETTINGS',PLUGIN_NAMESPACE.'_provider') ;
define(__NAMESPACE__.'\PROVIDER_ACTIVE','active') ;
define(__NAMESPACE__.'\PROVIDER_PUBLIC_KEY','publickey') ;
define(__NAMESPACE__.'\PROVIDER_PRIVATE_KEY','privatekey') ;
define(__NAMESPACE__.'\PROVIDER_EXTRA','extra') ;

// Url params
define(__NAMESPACE__.'\URL_PARAM_REDIRECT_TO','redirect_to') ;
define(__NAMESPACE__.'\URL_PARAM_PROVIDER',PLUGIN_NAMESPACE.'_provider') ;

// Others
define(__NAMESPACE__.'\PHP_EXTENSION','.php') ;
define(__NAMESPACE__.'\HYBRIDAUTH_DIR','hybridauthdev/') ;

function list_providers()
{
	$providers=array() ;
	if($directory=opendir(plugin_dir_path(__FILE__).HYBRIDAUTH_DIR.'Hybrid/Providers'))
	{
		while(($file=readdir($directory))!==false)
			if(strpos($file,PHP_EXTENSION)==strlen($file)-strlen(PHP_EXTENSION))
				array_push($providers,substr($file,0,strlen(PHP_EXTENSION)*-1)) ;
		closedir($directory) ;
		sort($providers,SORT_STRING) ;
	}
	return $providers ;
}

function providers_panel($url)
{
	// TODO: keep all query args
	$params=empty($_REQUEST[URL_PARAM_REDIRECT_TO])?array():array(URL_PARAM_REDIRECT_TO=>$_REQUEST[URL_PARAM_REDIRECT_TO]) ;
	$providersPanel='<div class="social-panel">' ;
	$activeProviders=get_option(PLUGIN_SETTINGS_ACTIVE_PROVIDERS) ;
	if(is_array($activeProviders))
		foreach($activeProviders as $provider)
		{
			$params[URL_PARAM_PROVIDER]=$provider ;
			$providersPanel.='<a href="'.esc_url(add_query_arg($params,$url)).'"><img src="'.plugins_url('img/'.$provider.'.png',__FILE__).'" /></a>' ;
		}
	return $providersPanel.'</div>' ;
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
	add_settings_section(PLUGIN_SETTINGS,'Plugin settings',null,PLUGIN_SETTINGS) ;
	add_settings_field(PLUGIN_SETTINGS_FOUNDATION_ALERT,'Use foundation alert styles:', __NAMESPACE__.'\foundation_styles_callback',PLUGIN_SETTINGS,PLUGIN_SETTINGS,PLUGIN_SETTINGS_FOUNDATION_ALERT) ;
	register_setting(PLUGIN_SETTINGS,PLUGIN_SETTINGS_FOUNDATION_ALERT) ;
	
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

function foundation_styles_callback($option)
{
	$value=get_option($option) ;
	echo "<input id=\"$option\" name=\"$option\" type=\"checkbox\" ".($value?'checked':'').' />' ;
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
	
	return array('base_url'=>plugins_url(HYBRIDAUTH_DIR,__FILE__),'providers'=>$providers) ;
}

function wp_enqueue_scripts()
{
	wp_register_style(PLUGIN_NAMESPACE.'_style',plugins_url('style.css',__FILE__)) ;
	wp_enqueue_style(PLUGIN_NAMESPACE.'_style') ;
	
	wp_register_script('google','https://apis.google.com/js/platform.js',array(),null,true) ;
}

function login_form()
{
	return '<div class="'.PLUGIN_NAMESPACE.'"><label>Or, authenticate using<br />'.providers_panel(wp_login_url()).'</label></div>' ;
}

function wp_logout()
{
	require_once(plugin_dir_path(__FILE__).HYBRIDAUTH_DIR.'Hybrid/Auth.php') ;
	$hybridauth=new \Hybrid_Auth(get_HybridAuth_config()) ;
	$hybridauth->logoutAllProviders() ;
}

function echo_login_form()
{
	echo login_form() ;
}

function comment_form_default_fields($fields)
{
	$commenter=wp_get_current_commenter() ;
	$req=get_option('require_name_email') ;
	$aria_req=$req?"aria-required='true'":'' ;
	$fields['author']='<p class="comment-form-author"><input id="author" name="author" type="text" value="'
			.esc_attr($commenter['comment_author']).'" placeholder="'.__('Name','domainreference').'" size="30"'.$aria_req.' /></p>' ;
			
	$fields['email']='<p class="comment-form-email"><input id="email" name="email" type="text" value="'
			.esc_attr($commenter['comment_author_email']).'" placeholder="'.__('Email','domainreference').'" size="30"'.$aria_req.' /></p>' ;
			
	$fields['url']='<p class="comment-form-url"><input id="url" name="url" type="text" value="'
			.esc_attr($commenter['comment_author_url']).'" placeholder="'.__('Website','domainreference').'" size="30" /></p>' ;
	
	/*unset($fields['author']) ;
	unset($fields['email']) ;
	unset($fields['url']) ;*/
	
	return $fields ;
}

function comment_form_defaults($defaults)
{
	global $login_error ;
	
	$error=empty($login_error)?'':'<div class="'.(get_option(PLUGIN_SETTINGS_FOUNDATION_ALERT)?'alert-box alert':'xdevl_alert-box xdevl_alert').'">'.$login_error.'</div> ' ;
	
	//$defaults['must_log_in']='<div class="must-log-in '.PLUGIN_NAMESPACE.'">'.$error.
			//'To comment, <a href="'.wp_login_url(apply_filters('the_permalink',get_permalink( ))).
			//'">log in</a> or authenticate using one of the following providers:'.providers_panel(get_permalink().'#respond').'</div>' ;
			
	$defaults['must_log_in']='<div class="'.PLUGIN_NAMESPACE.'">'.$error.
			// TODO: the # doesn't when redirected back after authentication, use jquery instead to scroll the page.
			'To post a comment, authenticate using one of the following providers:'.providers_panel($_SERVER['REQUEST_URI']).'</div>' ;
			
	$defaults['comment_notes_before']='<div class="'.PLUGIN_NAMESPACE.'">'.$error.'Authenticate using one of the following providers:'.
			providers_panel($_SERVER['REQUEST_URI']).'</div><p>Or enter the following information:</p>' ;

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

function get_provider_from_id_meta_key($id_meta_key)
{
	return substr($id_meta_key,strlen(PLUGIN_NAMESPACE.'_'),-strlen('_id')) ;
}

function get_provider_profile_meta_key($provider)
{
	return PLUGIN_NAMESPACE.'_'.$provider.'_profile' ;
}

abstract class MatchType
{
	const NONE=0 ; // User has never logged in
	const PROVIDER=1 ; // User has already logged in before with the provider
	const DUPLICATE=2 ; // User has already logged in before with the provider but his address email is already used
	const EMAIL=3 ; // User has never logged in with the provider but an user with the same email already exists
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
	if($count==0)
		return array(0,MatchType::NONE) ;
	else if($count==1)
		return array($result[0]->ID,$result[0]->meta_key=='first_name'?MatchType::EMAIL:MatchType::PROVIDER) ;
	else if($count==2 && $result[0]->ID!=$result[1]->ID)
		return array($result[1]->ID,MatchType::DUPLICATE) ;
	else return array($result[1]->ID,MatchType::PROVIDER) ;
}

/**
 * List all the providers a user has previously authenticated with
 * */
function user_providers($userId)
{
	global $wpdb ;
	$result=$wpdb->get_col($wpdb->prepare("SELECT meta_key FROM $wpdb->usermeta".
			' WHERE meta_key LIKE %s AND user_id=%d'.
			' ORDER BY meta_key',
			array(get_provider_id_meta_key('%'),$userId))) ;
			
	return $result ;
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
	// TODO: be sure the following gets called only once per page load
	if(isset($_GET[URL_PARAM_PROVIDER])) 
	{
		$provider=$_GET[URL_PARAM_PROVIDER] ;
		try {
			require_once(plugin_dir_path(__FILE__).HYBRIDAUTH_DIR.'Hybrid/Auth.php') ;
			$hybridauth=new \Hybrid_Auth(get_HybridAuth_config()) ;
			$adapter=$hybridauth->authenticate($_GET[URL_PARAM_PROVIDER]) ;
			$userProfile=$adapter->getUserProfile() ;
			
			$lookup=user_lookup($provider,$userProfile) ;
			if($lookup[1]==MatchType::NONE)
				return create_user($provider,$userProfile) ;
			else if($lookup[1]==MatchType::EMAIL)
			{
				$providers=user_providers($lookup[0]) ;
				if(count($providers)>0)
					throw new \Exception('Your Email address is already associated with a '.get_provider_from_id_meta_key($providers[0]).' account') ;
				else throw new \Exception('Your Email address is already used by another user') ;
			}
			else return get_user_by('id',$lookup[0]) ;
			
		} catch(\Exception $e) {
			return new \WP_Error('login_failed', __( '<strong>Login failed</strong>: '.$e->getMessage())) ;
		}
	}	
}

function wp_loaded()
{
	global $login_error ;
	if(!is_user_logged_in())
	{
		$user=wp_signon() ;
		if(!is_wp_error($user))
			wp_set_current_user($user->ID) ;
		else $login_error=$user->get_error_message() ;
	}
}

function wp_footer()
{
?>
	<div id="fb-root"></div>
	<script>
	(function(d, s, id) {
		var js, fjs = d.getElementsByTagName(s)[0];
		if (d.getElementById(id)) return;
		js = d.createElement(s); js.id = id;
		js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.4";
		fjs.parentNode.insertBefore(js, fjs);
	}(document, 'script', 'facebook-jssdk'));
	</script>
	
	<script>
	!function(d,s,id){
		var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';
		if(!d.getElementById(id)) {
			js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);
		}
	} (document, 'script', 'twitter-wjs');
	</script>
	
	<?php if(isset($_GET[URL_PARAM_PROVIDER])): ?>
	<script>
		jQuery('html,body').scrollTop(jQuery("#respond").offset().top) ;
	</script>
	<?php endif; ?>
<?php
}

function shortcode()
{
	wp_enqueue_script('google') ;
	ob_start() ;
?>
	<div class="xdevl_social">
		<div class="social-panel">
			<span>
				<div class="fb-like" data-href="https://developers.facebook.com/docs/plugins/"
					data-layout="button_count" data-action="like" data-show-faces="false" data-share="true"></div>
			</span>
			<span>
				<div class="g-plusone" data-annotation="none"></div>
				<div class="g-plus" data-action="share" data-annotation="bubble" data-height="24"></div>
			</span>
			<span>
				<a href="https://twitter.com/share" class="twitter-share-button">Tweet</a>
			</span>
		</div>
	</div>
	
<?php 
	return ob_get_clean() ;
}

add_action('wp_enqueue_scripts',__NAMESPACE__.'\wp_enqueue_scripts') ;
add_action('login_enqueue_scripts',__NAMESPACE__.'\wp_enqueue_scripts') ;
add_action('login_form',__NAMESPACE__.'\echo_login_form') ;
add_action('wp_logout',__NAMESPACE__.'\wp_logout') ;
add_filter('login_form_middle',__NAMESPACE__.'\login_form') ;
add_action('comment_form_default_fields',__NAMESPACE__.'\comment_form_default_fields') ;
add_action('comment_form_defaults',__NAMESPACE__.'\comment_form_defaults') ;
add_filter('show_password_fields',__NAMESPACE__.'\show_password_fields',10,2) ;
add_filter('authenticate',__NAMESPACE__.'\authenticate',10,3) ;
add_filter('wp_loaded',__NAMESPACE__.'\wp_loaded') ;


if(is_admin())
{
	add_action('admin_menu',__NAMESPACE__.'\admin_menu') ;
	add_action('admin_init',__NAMESPACE__.'\admin_init') ;

}
else
{
	add_action('wp_footer',__NAMESPACE__.'\wp_footer') ;
	add_shortcode(PLUGIN_NAMESPACE,__NAMESPACE__.'\shortcode') ;
}

} //end xdevl\social
?>
