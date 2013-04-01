<?php
/**
Plugin Name: Traffic Manager
Plugin Tag: traffic, stats, google, analytics, sitemaps, sitemaps.xml, bing, yahoo
Description: <p>You will be able to manage the Internet traffic on your website and to enhance it.</p><p>You may: </p><ul><li>see statistics on users browsing your website; </li><li>see statistics on web crawler;</li><li>inform Google, Bing, etc. when your site is updated;</li><li>configure Google Analytics;</li><li>add sitemap.xml information on your website;</li></ul><p>This plugin is under GPL licence</p>
Version: 1.1.2


Framework: SL_Framework
Author: SedLex
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/extend/plugins/traffic-manager/
License: GPL3
*/

//Including the framework in order to make the plugin work

require_once('core.php') ; 


/** ====================================================================================================================================================
* This class has to be extended from the pluginSedLex class which is defined in the framework
*/
class traffic_manager extends pluginSedLex {

	/** ====================================================================================================================================================
	* Plugin initialization
	* 
	* @return void
	*/
	static $instance = false;

	protected function _init() {
		global $wpdb ; 
		
		// Name of the plugin (Please modify)
		$this->pluginName = 'Traffic Manager' ; 
		$this->maxTime = 600 ; 
		
		// The structure of the SQL table if needed (for instance, 'id_post mediumint(9) NOT NULL, short_url TEXT DEFAULT '', UNIQUE KEY id_post (id_post)') 
		$this->tableSQL = "id mediumint(9) NOT NULL AUTO_INCREMENT, count mediumint(9) NOT NULL, uniq_visit mediumint(9) NOT NULL, viewed BOOL, type VARCHAR(15), ip VARCHAR(100), browserName VARCHAR(30), browserVersion VARCHAR(100), platformName VARCHAR(30), platformVersion VARCHAR(30),  browserUserAgent VARCHAR(250), referer VARCHAR(500), page VARCHAR(100), time DATETIME, singleCookie VARCHAR(100), refreshNumber mediumint(9) NOT NULL, UNIQUE KEY id (id)" ; 

		// The name of the SQL table (Do no modify except if you know what you do)
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 

		//Configuration of callbacks, shortcode, ... (Please modify)
		// For instance, see 
		//	- add_shortcode (http://codex.wordpress.org/Function_Reference/add_shortcode)
		//	- add_action 
		//		- http://codex.wordpress.org/Function_Reference/add_action
		//		- http://codex.wordpress.org/Plugin_API/Action_Reference
		//	- add_filter 
		//		- http://codex.wordpress.org/Function_Reference/add_filter
		//		- http://codex.wordpress.org/Plugin_API/Filter_Reference
		// Be aware that the second argument should be of the form of array($this,"the_function")
		// For instance add_action( "the_content",  array($this,"modify_content")) : this function will call the function 'modify_content' when the content of a post is displayed
		
		add_action( 'wp_ajax_UserWebStat', array( $this, 'UserWebStat'));
		add_action( 'wp_ajax_nopriv_UserWebStat', array( $this, 'UserWebStat'));
		
		add_action("save_post", array( $this, "create_sitemap_upon_save"));
		
		// Important variables initialisation (Do not modify)
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		// activation and deactivation functions (Do not modify)
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array('traffic_manager','uninstall_removedata'));
	}
	
	/** ====================================================================================================================================================
	* In order to uninstall the plugin, few things are to be done ... 
	* (do not modify this function)
	* 
	* @return void
	*/
	
	public function uninstall_removedata () {
		global $wpdb ;
		// DELETE OPTIONS
		delete_option('traffic_manager'.'_options') ;
		if (is_multisite()) {
			delete_site_option('traffic_manager'.'_options') ;
		}
		
		// DELETE SQL
		if (function_exists('is_multisite') && is_multisite()){
			$old_blog = $wpdb->blogid;
			$old_prefix = $wpdb->prefix ; 
			// Get all blog ids
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM ".$wpdb->blogs));
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				$wpdb->query("DROP TABLE ".str_replace($old_prefix, $wpdb->prefix, $wpdb->prefix . "pluginSL_" . 'traffic_manager')) ; 
			}
			switch_to_blog($old_blog);
		} else {
			$wpdb->query("DROP TABLE ".$wpdb->prefix . "pluginSL_" . 'traffic_manager' ) ; 
		}
	}

	/**====================================================================================================================================================
	* Function called when the plugin is activated
	* For instance, you can do stuff regarding the update of the format of the database if needed
	* If you do not need this function, you may delete it.
	*
	* @return void
	*/
	
	public function _update() {
		
	}
	
	/** ====================================================================================================================================================
	* Init javascript for the admin side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('my_plugin_script', plugins_url('/script.js', __FILE__));</code>
	*
	* @return void
	*/
	
	function _admin_js_load() {	
		wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');
		return ; 
	}
	
	/**====================================================================================================================================================
	* Function called to return a number of notification of this plugin
	* This number will be displayed in the admin menu
	*
	* @return int the number of notifications available
	*/
	 
	public function _notify() {
		return 0 ; 
	}
	
	/**====================================================================================================================================================
	* Function to instantiate the class and make it a singleton
	* This function is not supposed to be modified or called (the only call is declared at the end of this file)
	*
	* @return void
	*/
	
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/** ====================================================================================================================================================
	* Define the default option values of the plugin
	* This function is called when the $this->get_param function do not find any value fo the given option
	* Please note that the default return value will define the type of input form: if the default return value is a: 
	* 	- string, the input form will be an input text
	*	- integer, the input form will be an input text accepting only integer
	*	- string beggining with a '*', the input form will be a textarea
	* 	- boolean, the input form will be a checkbox 
	* 
	* @param string $option the name of the option
	* @return variant of the option
	*/
	public function get_default_option($option) {
		switch ($option) {
			case 'localwebstat' 		: return false 		; break ; 
			case 'local_track_user'		: return true ; break ; 
			case 'local_detail'		: return false ; break ; 
			case 'local_detail_nb'		: return array(array("100", "nb100"), array("*50", "nb50"), array("20", "nb20"), array("10", "nb10") ) ; break ; 
			case 'local_show_visits'		: return false ; break ; 
			case 'local_period'		: return array(array(__('3 Year', $this->pluginID), "a3"), array(__('1 Year', $this->pluginID), "a1"), array(__('6 Month', $this->pluginID), "m6"), array("*".__('1 Month', $this->pluginID), "m1"), array(__('2 Week', $this->pluginID), "w2"), array(__('1 Week', $this->pluginID), "w1")) ; break ; 
			case 'local_show_type'		: return false ; break ; 
			case 'local_color'		: return "*['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']" ; break ; 
			case 'local_cron_concat'		: return "" ; break ; 
			
			case 'googlewebstat' 		: return false 		; break ; 
			case 'googlewebstat_user' 		: return "" 		; break ; 
			case 'googlewebstat_acc_id' 		: return "" 		; break ; 
			case 'googlewebstat_list' 		: return array("")		; break ; 
			case 'googlewebstat_auth' 		: return false 		; break ; 
			case 'googlewebstat_auth_token' 		: return "" 		; break ; 
			case 'google_api_key'		: return "" ; break ; 
			case 'google_show_visits'		: return false ; break ; 
			case 'google_show_type'		: return false ; break ; 
			case 'google_track_user'		: return true ; break ; 
			case 'google_color'		: return "*['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']" ; break ; 
			case 'google_period'		: return array(array(__('3 Year', $this->pluginID), "a3"), array(__('1 Year', $this->pluginID), "a1"), array(__('6 Month', $this->pluginID), "m6"), array("*".__('1 Month', $this->pluginID), "m1"), array(__('2 Week', $this->pluginID), "w2"), array(__('1 Week', $this->pluginID), "w1")) ; break ; 

			case 'sitemaps'		: return false ; break ; 
			case 'sitemaps_date'		: return "" ; break ; 
			case 'sitemaps_nb'		: return 0 ; break ; 
			case 'sitemaps_notify_google'		: return false ; break ; 
			case 'sitemaps_notify_google_date'		: return "" ; break ; 
			case 'sitemaps_notify_bing'		: return false ; break ; 
			case 'sitemaps_notify_bing_date'		: return "" ; break ; 
			case 'sitemaps_notify_ask'		: return false ; break ; 
			case 'sitemaps_notify_ask_date'		: return "" ; break ; 

		}
		return null ;
	}

	/** ====================================================================================================================================================
	* Init css for the frontend side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _front_css_load() {	
		global $blog_id ; 
		
		// Add sitemap declaration
		
		if ($this->get_param('sitemaps')) {
			if (is_multisite()) {
				if (is_file(ABSPATH . "sitemap".$blog_id.".xml.gz"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap'.$blog_id.'.xml.gz" />'."\n" ; 
				if (is_file(ABSPATH . "sitemap".$blog_id.".xml"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap'.$blog_id.'.xml" />'."\n" ; 
			} else {
				if (is_file(ABSPATH . "sitemap.xml.gz"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap.xml.gz" />'."\n" ; 
				if (is_file(ABSPATH . "sitemap.xml"))
					echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="'.get_site_url()."/".'sitemap.xml" />'."\n" ; 
			}
		}
	}	
	
	/** ====================================================================================================================================================
	* Init javascript for the public side
	* If you want to load a script, please type :
	* 	<code>wp_enqueue_script( 'jsapi', 'https://www.google.com/jsapi');</code> or 
	*	<code>wp_enqueue_script('my_plugin_script', plugins_url('/script.js', __FILE__));</code>
	*	<code>$this->add_inline_js($js_text);</code>
	*	<code>$this->add_js($js_url_file);</code>
	*
	* @return void
	*/
	
	function _public_js_load() {	
		
		// On charge le jquery si ce n'est deja fait
		wp_enqueue_script("jquery") ; 
		
		if ($this->get_param('localwebstat')) {
			$current_user = wp_get_current_user();			
			if ( (($this->get_param('local_track_user')) && (0 != $current_user->ID))  || (0 == $current_user->ID) ) {
				ob_start() ; 
					?>
					var isActiveSL;

					//window.onfocus = function () { 
					//  isActiveSL = true; 
					//}; 
					
					//window.onblur = function () { 
					//  isActiveSL = false; 
					//}; 
					
					function UserWebStat_sC(name,value,days) {
						if (days) {
							var date = new Date();
							date.setTime(date.getTime()+(days*24*60*60*1000));
							var expires = "; expires="+date.toGMTString();
						}
						else var expires = "";
						document.cookie = name+"="+value+expires+"; path=/";
					}
			
					function UserWebStat_gC(name) {
						var nameEQ = name + "=";
						var ca = document.cookie.split(';');
						for(var i=0; i < ca.length;i++) {
							var c = ca[i];
							while (c.charAt(0)==' ') c = c.substring(1,c.length);
							if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
						}
						return null;
					}
			
					function UserWebStat() {
						//if (!isActiveSL) {
						//	window.setTimeout("UserWebStat()", 1000);
						//	return ; 
						//}
						if (UserWebStat_gC('sC')!=null) {
							var sC = UserWebStat_gC('sC') ; 
						} else {
							var sC = "" ; 
						}
						if (UserWebStat_gC('rN')!=null) {
							var rN = UserWebStat_gC('rN') ; 
						} else {
							var rN = 0 ; 
						}
						
						var arguments = {
							action: 'UserWebStat', 
							browserName : navigator.appName, 
							browserVersion : navigator.appVersion, 
							platform : navigator.platform, 
							browserUserAgent: navigator.userAgent,
							cookieEnabled: navigator.cookieEnabled,
							singleCookie: sC,
							refreshNumber: rN,
							referer : document.referrer,
							page: window.location.pathname
						} 
						
						
						var ajaxurl2 = "<?php echo admin_url()."admin-ajax.php"?>" ; 
						jQuery.post(ajaxurl2, arguments, function(response) {
							//We put the return values in cookie and we relaunch
							if (response+""=="0") {
								UserWebStat_sC('rN', 0) ; 
							} else {
								var val = (response+"").split(",") ; 
								if (val.length==2) {
									UserWebStat_sC('sC', val[0], 365) ; 
									UserWebStat_sC('rN', val[1]) ; 
									var t=setTimeout("UserWebStat()",10000);
								}
							}
						});    
					}
					
					// We launch the callback when jQuery is loaded or at least when the page is loaded
					if (typeof(jQuery) == 'function') {
						UserWebStat() ; 			
					} else { 
						if (window.attachEvent) {window.attachEvent('onload', UserWebStat);}
						else if (window.addEventListener) {window.addEventListener('load', UserWebStat, false);}
						else {document.addEventListener('load', UserWebStat, false);} 
					}
					
					<?php 
				
				$java = ob_get_clean() ; 
				$this->add_inline_js($java) ; 
			}
		}
		if ($this->get_param('googlewebstat')) {
			if ($this->get_param('googlewebstat_user') != "") {
				$current_user = wp_get_current_user();
				if ( (($this->get_param('google_track_user')) && (0 != $current_user->ID)) || (0 == $current_user->ID) ) {
					ob_start() ; 
					?>
						var _gaq = _gaq || [];
						_gaq.push(['_setAccount', '<?php echo $this->get_param('googlewebstat_user') ; ?>']);
						_gaq.push(['_trackPageview']);
						_gaq.push(['_trackPageLoadTime']);
	
						(function() {
							var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
							ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
							var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
						})();
									
					<?php 
					$java = ob_get_clean() ; 
					$this->add_inline_js($java) ;
				}
			}
		}
	}

	/** ====================================================================================================================================================
	* The admin configuration page
	* This function will be called when you select the plugin in the admin backend 
	*
	* @return void
	*/
	
	public function configuration_page() {
	
		global $wpdb;
		global $_GET ; 
		global $_POST ; 
		global $blog_id ; 
		

		// On concatene si besoin pour economiser de la place dans la bdd
		if ($this->get_param('local_cron_concat')=="") {
			$this->set_param('local_cron_concat', strtotime(date_i18n("Y-m-d 0:0:1")." +1 day")) ; 
		}
				
		// Si la page est avec un GET token, on refresh en postant 
		if (isset($_GET['token']))  {
			?>
			<form action='<?php echo get_admin_url()."admin.php?page=traffic-manager/traffic-manager.php" ;  ?>' method='post' name='frm'>
			<input type='hidden' name='token' value='<?php echo $_GET['token'] ; ?>'>
			</form>
			<script language="JavaScript">
				document.frm.submit();
			</script>
			<?php
			return ; 
		}
		
		// We save the parameters !
		ob_start() ; 
			if ((isset($_POST['token'])) && ($this->get_param('googlewebstat_auth_token') == "") ){
				$this->set_param('googlewebstat', true) ; 
				$this->set_param('googlewebstat_auth', true) ; 
				$token = $this->get_session_token($_POST['token']) ; 
				if ($token != false) {
					$this->set_param('googlewebstat_auth_token', $token) ; 
					echo "<div class='updated fade'><p>".__('The authentication with Google Analytics is successful',  $this->pluginID)."</p></div>" ; 
				} else {
					echo "<div class='error fade'><p>".__('The authentication with Google Analytics have failed... please retry!',  $this->pluginID)."</p></div>" ; 
				}
			}
			
			if (isset($_POST['untoken'])) {
				$this->set_param('googlewebstat_auth', false) ; 
				if ($this->get_param('googlewebstat_auth_token')!="") {
					$this->revoke_session_token($this->get_param('googlewebstat_auth_token')) ; 
					$this->set_param('googlewebstat_auth_token', "") ; 
					$this->set_param('googlewebstat_acc_id', "") ; 
					echo "<div class='updated fade'><p>".__("The Google Analytics's authorization has been revoked as requested",  $this->pluginID)."</p></div>" ; 
				}
			}
			
			$params = new parametersSedLex($this, "tab-parameters") ; 
			$params->add_title(__("Local Web Statistics", $this->pluginID)) ; 
			$params->add_param('localwebstat', __('Do you want to manage the web statistics locally?', $this->pluginID),"", "", array('local_track_user', 'local_detail', 'local_detail_nb', 'local_show_visits', 'local_show_type', 'local_color', 'local_period')) ; 
			$params->add_comment(__("If so, stats will be stored in the local SQL database. Be sure that you have free space into your database", $this->pluginID)) ; 
			if ($this->get_param('localwebstat')) {
				$params->add_comment(sprintf(__("The next compression of the database will occur on %s", $this->pluginID), date_i18n("Y-m-d H:i:s", $this->get_param('local_cron_concat')))) ; 
			}
			
			$params->add_param('local_detail', __('Do you want to display the last viewed pages?', $this->pluginID),"", "", array('local_detail_nb')) ; 
			$params->add_comment(__("If so, a list of the last viewed pages will be displayed including IP of the user, the url of the viewed page, the time, the referer, the browser name, the OS name, etc.", $this->pluginID)) ; 
			$params->add_param('local_detail_nb', __('How many pages should be displayed?', $this->pluginID)) ; 
			$params->add_param('local_show_visits', __('Show statistics on number of visits and viewed pages?', $this->pluginID)) ; 
			$params->add_param('local_show_type', __('Show statistics on the OS and browser types of your visitors?', $this->pluginID)) ; 
			$params->add_param('local_color', __('What are the colors for the charts?', $this->pluginID)) ; 
			$params->add_comment(sprintf(__("The default colors are %s.", $this->pluginID), "<code>['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']</code>")) ; 
			$params->add_param('local_period', __('What are the period for which charts should be provided?', $this->pluginID)) ; 
			$params->add_param('local_track_user', __('Do you want to track the logged user?', $this->pluginID)) ; 
			
			
			
			$params->add_title(__("Google Analytics Web Statistics", $this->pluginID)) ; 
			$params->add_param('googlewebstat', __('Do you want to manage the web statistics with Google Analytics?', $this->pluginID), "", "", array('googlewebstat_user', 'googlewebstat_list', 'google_show_visits', 'google_show_type', 'google_show_time', 'google_color', 'google_period', 'google_track_user', 'google_api_key')) ; 
			$params->add_comment(sprintf(__("For additional information, please visit the %s website. Moreover you could see all your authorized accesses on this %spage%s.", $this->pluginID), "<a href='http://www.google.com/analytics/'>Google Analytics</a>", "<a href='https://accounts.google.com/b/0/IssuedAuthSubTokens'>", "</a>")) ; 
			
			if (!$this->get_param('googlewebstat_auth')) {
				$params->add_param('googlewebstat_user', __('Google Analytics ID:', $this->pluginID)) ; 
				$params->add_comment(sprintf(__("The Google Analytics ID is should be similar to %s.", $this->pluginID), "<code>UA-XXXXX-X</code>")) ; 
				$params->add_comment(sprintf(__("You may also %sauthenticate%s with your Google account and thus ease the process.", $this->pluginID), "<a href='https://www.google.com/accounts/AuthSubRequest?next=".urlencode(get_admin_url()."admin.php?page=traffic-manager/traffic-manager.php")."&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fanalytics.readonly&secure=0&session=1&hd=default'>", "</a>")) ; 
			} else {
				$params->add_comment(sprintf(__("For now, this page is authenticated with your Google Analytics accounts. %sUnapprove the authentication%s", $this->pluginID), '<input name="untoken" class="button validButton" value="', '" type="submit">')) ; 
				
				$account = $this->get_analytics_accounts() ; 

				$list = array() ; 
				$list[] = __("Select...", $this->pluginID) ; 
				
				// On enregistre googlewebstat_user
				if (isset($_POST['submitOptions'])) {
					$id = "" ; 
					$name = "" ; 
					$acc_id = "" ; 
					$selected = $_POST['googlewebstat_list'] ; 
					
					foreach ($account as $a) {
						if ($selected == Utils::create_identifier($a['title'])) {
							$acc_id = $a['tableId'] ; 
							$id = $a['webPropertyId'] ; 
							$name = $a['title'] ; 
						}
					}
					$this->set_param('googlewebstat_user', $id ) ; 
					$this->set_param('googlewebstat_acc_id', $acc_id) ; 
				}
				
				// On affiche 
				$id = "" ; 
				$name = "" ; 
				$acc_id = "" ; 
				if (!isset($account['error'])) {
					foreach ($account as $a) {
						if ($a['webPropertyId']==$this->get_param('googlewebstat_user')) {
							$list[] = "*".$a['title'] ; 
							$id = $a['webPropertyId'] ; 
							$acc_id = $a['tableId'] ; 
							$name = $a['title'] ; 
						} else {
							$list[] = $a['title'] ; 
						}
					}
				} else {
					echo "<div class='error fade'><p>".$account['error']."</p></div>" ; 
				}
				
				if (($this->get_param('googlewebstat_acc_id')=="")&&($acc_id!="")) {
					$this->set_param('googlewebstat_acc_id', $acc_id) ; 
				}
				
				$this->set_param('googlewebstat_list', $list) ; 
				$params->add_param('googlewebstat_list', __('Choose your website in this list', $this->pluginID)) ; 
				if ($name != "") {
					$params->add_comment(sprintf(__("Please note that the Google Analytics ID for %s is %s.", $this->pluginID), "<code>".$name."</code>", "<code>".$id."</code>")) ; 
				} else {
					$params->add_comment(__("No Google Analytics ID configured for now.", $this->pluginID)) ; 			
				}
				$params->add_param('google_api_key', __('What is the API key?', $this->pluginID)) ; 
				$params->add_comment(__("This API key is useful to avoid any quota limit. If you do not set this key, only very few requests may be allowed by Google", $this->pluginID)) ; 
				$params->add_comment(sprintf(__("To get this API key, please visit %s, create a projet, allow Google Analytics, and then go to API console to get a %s", $this->pluginID), "<a href='https://code.google.com/apis/console'>https://code.google.com/apis/console</a>", "'<i>Key for browser apps</i>'")) ; 
				
				$params->add_param('google_show_visits', __('Show statistics on number of visits and viewed pages?', $this->pluginID)) ; 
				$params->add_param('google_show_type', __('Show statistics on the OS and browser types of your visitors?', $this->pluginID)) ; 
				$params->add_param('google_color', __('What are the colors for the charts?', $this->pluginID)) ; 
				$params->add_comment(sprintf(__("The default colors are %s.", $this->pluginID), "<code>['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']</code>")) ; 
				$params->add_param('google_period', __('What are the period for which charts should be provided?', $this->pluginID)) ; 
			}
			$params->add_param('google_track_user', __('Do you want to track the logged user?', $this->pluginID)) ; 

			
			$params->add_title(__("Sitemaps Configuration", $this->pluginID)) ; 
			$params->add_param('sitemaps', __('Do you want to compute a Sitemaps file?', $this->pluginID), "", "", array('sitemaps_nb', 'sitemaps_notify_google', 'sitemaps_notify_bing', 'sitemaps_notify_ask')) ; 
			$params->add_comment(__("A Sitemap is an XML file that lists the URLs for your website. It informs search engines about URLs on your website that are available for crawling.", $this->pluginID)) ; 
			if ($this->get_param('sitemaps_date')=="") {
				$params->add_comment(__("No sitemap has been generated yet.", $this->pluginID)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured on the next sitemap generation : %s.", $this->pluginID), $this->get_param('sitemaps_date'))) ; 
			} else {
				$params->add_comment(sprintf(__("The last sitemap has been generated on %s.", $this->pluginID), $this->get_param('sitemaps_date'))) ; 
				$filename = "sitemap" ; 
				if (is_multisite()) {
					$filename = $filename.$blog_id;
				} 
				$params->add_comment(sprintf(__("You may see your sitemap at %s.", $this->pluginID), "<a href='".get_site_url()."/".$filename.".xml'>".get_site_url()."/".$filename.".xml</a>")) ; 
			}
			$params->add_param('sitemaps_nb', __('How many posts and pages will be included in this file?', $this->pluginID)) ; 
			$params->add_comment(__("If you have too many posts, set this number to 1000 for instance in order to avoid any memory issue. If you set this number to 0, all posts will be included.", $this->pluginID)) ; 
			// Google
			$name_crawler = "Google" ; 
			$name_crawler_s = strtolower($name_crawler) ; 
			$params->add_param('sitemaps_notify_'.$name_crawler_s, sprintf(__('Do you want to notify %s when the sitemap is updated?', $this->pluginID), $name_crawler)) ; 
			if ($this->get_param('sitemaps_notify_'.$name_crawler_s.'_date')=="") {
				$params->add_comment(sprintf(__("%s has never been notified yet. Waiting for the next update.", $this->pluginID), $name_crawler)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured when notifying %s for the last time.", $this->pluginID), $name_crawler)) ; 
			} else {
				$params->add_comment(sprintf(__("%s has be notified for the last time on %s.", $this->pluginID), $name_crawler, $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))) ; 
			}
			// Ask
			$name_crawler = "Ask" ; 
			$name_crawler_s = strtolower($name_crawler) ; 
			$params->add_param('sitemaps_notify_'.$name_crawler_s, sprintf(__('Do you want to notify %s when the sitemap is updated?', $this->pluginID), $name_crawler)) ; 
			if ($this->get_param('sitemaps_notify_'.$name_crawler_s.'_date')=="") {
				$params->add_comment(sprintf(__("%s has never been notified yet. Waiting for the next update.", $this->pluginID), $name_crawler)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured when notifying %s for the last time.", $this->pluginID), $name_crawler)) ; 
			} else {
				$params->add_comment(sprintf(__("%s has be notified for the last time on %s.", $this->pluginID), $name_crawler, $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))) ; 
			}
			// Bing
			$name_crawler = "Bing" ; 
			$name_crawler_s = strtolower($name_crawler) ; 
			$params->add_param('sitemaps_notify_'.$name_crawler_s, sprintf(__('Do you want to notify %s when the sitemap is updated?', $this->pluginID), "Bing & Yahoo")) ; 
			if ($this->get_param('sitemaps_notify_'.$name_crawler_s.'_date')=="") {
				$params->add_comment(sprintf(__("%s has never been notified yet. Waiting for the next update.", $this->pluginID), $name_crawler)) ; 
			} else if (@date_i18n("Ymd", $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))===FALSE) {
				$params->add_comment(sprintf(__("An error occured when notifying %s for the last time.", $this->pluginID), $name_crawler)) ; 
			} else {
				$params->add_comment(sprintf(__("%s has be notified for the last time on %s.", $this->pluginID), $name_crawler, $this->get_param('sitemaps_notify_'.$name_crawler_s.'_date'))) ; 
			}

			$params->flush() ; 
		$parameters = ob_get_clean() ; 
		
		if ($this->get_param('sitemaps')) {
			$resu = $this->generateSitemaps("sitemap") ; 
			// If an error occurred
			if (isset($resu['error'])) {
				echo "<div class='error fade'><p>".$resu['error']."</p></div>" ; 				
			} 
		}
	
		?>
		

		<div class="wrap">
			<div id="icon-themes" class="icon32"><br></div>
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		<div style="padding:20px;">			
			<?php
			//===============================================================================================
			// After this comment, you may modify whatever you want
		
			?>
			<p><? echo __("You may see here the statistics of your website (locally or with Google Analytics) and improve the future traffic by informing web crawlers of your contents (sitemaps and notifications).", $this->pluginID) ;?></p>
			<?php
			
			// We check rights
			$this->check_folder_rights( array(array(WP_CONTENT_DIR."/sedlex/test/", "rwx")) ) ;
			
			$tabs = new adminTabs() ; 
			
			ob_start() ; 
			
				if ($this->get_param('googlewebstat')) {
				
					// Get the Google Data
					//----------------------------------
				
					$period = $this->get_param('google_period') ; 
					$date2 = date_i18n("Y-m-d") ; 
					$date1 = date_i18n("Y-m-d", strtotime($date2 . " - 1 month")) ;
					$pas = "ga:date" ; 
					$ptd=sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1)), date_i18n(get_option('date_format') , strtotime($date2))) ; 
					foreach ($period as $p) {
						if (strpos($p[0], "*")!==false) {
							switch ($p[1]) {
								case 'a3' : $date1 = date_i18n("Y-m-d", strtotime(date_i18n("Y-m-01", strtotime($date2 . " -3 years")))) ; $pas = "ga:year,ga:month" ; break ; 
								// -1day car commence le dimanche
								case 'a1' : $date1 = date_i18n("Y-m-d", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -1 year")) ." -1 day")) ;  $pas = "ga:year,ga:week" ; break ; 
								// -1day car commence le dimanche
								case 'm6' : $date1 = date_i18n("Y-m-d", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -6 months")) ." -1 day"));  $pas = "ga:year,ga:week" ;  break ; 
								case 'm1' : $date1 = date_i18n("Y-m-d", strtotime($date2 . " -1 month")) ;  $pas = "ga:date" ;  break ; 
								case 'w2' : $date1 = date_i18n("Y-m-d", strtotime($date2 . " -2 weeks")) ;  $pas = "ga:date" ;  break ; 
								case 'w1' : $date1 = date_i18n("Y-m-d", strtotime($date2 . " -1 week")) ;  $pas = "ga:date" ;  break ; 
							}
						}
					}
					$data = $this->get_analytics_data("start-date=$date1&end-date=$date2", $pas) ; 
					$ptd=sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1)), date_i18n(get_option('date_format') , strtotime($date2))) ; 
				}
				
				if ($this->get_param('localwebstat')) {
				
					// Get the Local Data
					//----------------------------------
				
					$period_local = $this->get_param('local_period') ; 
					$date2_local = date_i18n("Ymd") ; 
					$date1_local = date_i18n("Ymd", strtotime($date2_local . " - 1 month")) ;
					$pas_local = "day" ; 
					$ptd_local = sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1_local)), date_i18n(get_option('date_format') , strtotime($date2_local))) ; 
					foreach ($period_local as $p) {
						if (strpos($p[0], "*")!==false) {
							switch ($p[1]) {
								case 'a3' : $date1_local = date_i18n("Ymd", strtotime(date_i18n("Y-m-01", strtotime($date2_local . " - 3 years")))) ; $pas_local = "month" ;  break ; 
								case 'a1' : $date1_local = date_i18n("Ymd", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -1 year"))))  ;  $pas_local = "week" ;  break ; 
								case 'm6' : $date1_local = date_i18n("Ymd", strtotime(date_i18n("o-\\WW", strtotime($date2 . " -6 months"))));  $pas_local = "week" ;  break ; 
								case 'm1' : $date1_local = date_i18n("Ymd", strtotime($date2_local . " - 1 month")) ;  $pas_local = "day" ;  break ; 
								case 'w2' : $date1_local = date_i18n("Ymd", strtotime($date2_local . " - 2 weeks")) ;  $pas_local = "day" ;  break ; 
								case 'w1' : $date1_local = date_i18n("Ymd", strtotime($date2_local . " - 1 week")) ;  $pas_local = "day" ;  break ; 
							}
						}
					}
					
					$data_local = $this->get_local_data($date1_local, date_i18n("Ymd", strtotime($date2_local . " + 1 day")), $pas_local) ; 
					$ptd_local =sprintf(__("from %s to %s", $this->pluginID), date_i18n(get_option('date_format') , strtotime($date1_local )), date_i18n(get_option('date_format') , strtotime($date2_local ))) ; 
				}
				
				if ( $this->get_param('googlewebstat') || $this->get_param('localwebstat') ) {
								
					// Creating the graph for the Google
					// Analytics visits count
					//----------------------------------
				
					ob_start() ; 
						$google_show = false ; 

						if ( ($this->get_param('googlewebstat')) && ($this->get_param('google_show_visits')) && (isset($data['visits'])) ) {
							$google_show = true ; 
							$rows = "" ; 
							
							$first = true ; 
							$nb = 0 ; 
							$last_persons = "0" ; 
							$last_visits = "0" ; 
							foreach ($data['visits'] as $k => $d) {
								if ($pas=="ga:date") {
									if (!$first) $rows .= "," ; 
									$date = date_i18n(get_option('date_format') , strtotime($k)) ; 
									$visit = $d["ga:visits"] ; 
									$pageViews = $d["ga:pageviews"] ; 
									$rows .= "['".$date."', ".$visit .", ".$pageViews."]" ; 
									$first = false ; 
									$nb++ ; 
								}
								if ($pas=="ga:year,ga:week") {
									// On boucle sur les annees
									foreach ($d as $w => $a) {
										if (!$first) $rows .= "," ; 
										$date = sprintf(__('Week %s (%s)', $this->pluginID), $w, $k) ; 
										$visit = $a["ga:visits"] ; 
										$pageViews = $a["ga:pageviews"] ; 
										$rows .= "['".$date."', ".$visit .", ".$pageViews."]" ; 
										$first = false ; 
										$nb++ ; 
									}
								}
								if ($pas=="ga:year,ga:month") {
									// On boucle sur les annees
									foreach ($d as $m => $a) {
										if (!$first) $rows .= "," ; 
										$date = sprintf(__('%s %s', $this->pluginID), date_i18n("F",mktime(date_i18n("H"),date_i18n("i"),date_i18n("s") ,$m, date_i18n("j"))), $k) ; 
										$visit = $a["ga:visits"] ; 
										$pageViews = $a["ga:pageviews"] ; 
										$rows .= "['".$date."', ".$visit .", ".$pageViews."]" ; 
										$first = false ; 
										$nb++ ; 
									}
								}
								$beforelast_persons = $last_persons ; 	
								$beforelast_visits = $last_visits ; 
								$last_persons = $visit ; 
								$last_visits = $pageViews ; 
							}
							$width = "900" ; 
							$height = "400" ; 
							?>
							<h3><?php echo __('Google Analytics Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Google Analytics Data, here is the number of visits and the number of page views.', $this->pluginID)?></p>
							<p><?php 
								if ($pas=="ga:date") {
									echo sprintf(__('Today, %s people have visited your site and %s pages have been viewed (%s and %s for yesterday).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas=="ga:year,ga:week") {
									echo sprintf(__('This week, %s people have visited your site and %s pages have been viewed (%s and %s for last week).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas=="ga:year,ga:month") {
									echo sprintf(__('This month, %s people have visited your site and %s pages have been viewed (%s and %s for last month).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								}?>
							</p>
							<?php
							if ($pas=="ga:year,ga:week") {
								?>
								<p><?php echo __('NOTA: Google considers that the week starts on sundays which is not ISO 8601 compliant (normally starts on mondays). So be careful when comparing weeks with your calendar: week numbers may be sligthly different.', $this->pluginID)?></p>
								<?php
							}
							
							$colors = $this->get_param('google_color') ; 
							if ($this->get_param('google_color')=="") {
								$colors = "['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']" ; 
							}
							
							?>
							<div id="google_visits_count" style="margin: 0px auto; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
							<script  type="text/javascript">
								google.setOnLoadCallback(CountVisits);
								google.load('visualization', '1', {'packages':['corechart']});
								
								function CountVisits() {
									var data = new google.visualization.DataTable();
									data.addColumn('string', '<?php echo __('Month', $this->pluginID)?>');
									data.addColumn('number', '<?php echo __('Number of Visitors', $this->pluginID)?>');
									data.addColumn('number', '<?php echo __('Number of Page Views', $this->pluginID)?>');
									data.addRows([<?php echo $rows ; ?>]);
									var options = {
										width: <?php echo $width ; ?>, 
										height: <?php echo $height ; ?>,
										colors:<?php echo $colors ?>,
										title: '<?php echo sprintf(__("Visitors and Page Views (%s)", $this->pluginID), $ptd) ?>',
										hAxis: {title: '<?php echo __('Time Line', $this->pluginID)?>'}
									};

									var chart = new google.visualization.ColumnChart(document.getElementById('google_visits_count'));
									chart.draw(data, options);
								}
							</script>
							<?php
						}	
						
						
						if ( ($this->get_param('localwebstat')) && ($this->get_param('local_show_visits')) && (isset($data_local['visits'])) ) {
							$google_show = true ; 
							$rows = "" ; 
							$first = true ; 
							$nb = 0 ; 
							
							$last_persons = "0" ; 
							$last_visits = "0" ; 
							foreach ($data_local['visits'] as $k => $d) {
								$k = trim($k) ; 
								if ($pas_local=="day") {
									if (!$first) $rows .= "," ; 
									$date = date_i18n(get_option('date_format') , strtotime($k)) ; 
									$visit = $d["visits"] ; 
									$pageViews = $d["pageviews"] ; 
									$rows .= "['".$date."', ".$visit .", ".$pageViews."]" ; 
									$first = false ; 
									$nb++ ; 
								}
								if ($pas_local=="week") {
									// On boucle sur les annees
									foreach ($d as $w => $a) {
										if (!$first) $rows .= "," ; 
										$date = sprintf(__('Week %s (%s)', $this->pluginID), $w, $k) ; 
										$visit = $a["visits"] ; 
										$pageViews = $a["pageviews"] ; 
										$rows .= "['".$date."', ".$visit .", ".$pageViews."]" ; 
										$first = false ; 
										$nb++ ; 
									}
								}
								if ($pas_local=="month") {
									// On boucle sur les annees
									foreach ($d as $m => $a) {
										if (!$first) $rows .= "," ; 
										$date = sprintf(__('%s %s', $this->pluginID), date_i18n("F",mktime(date_i18n("H"),date_i18n("i"),date_i18n("s") ,$m, date_i18n("j"))), $k) ; 
										$visit = $a["visits"] ; 
										$pageViews = $a["pageviews"] ; 
										$rows .= "['".$date."', ".$visit .", ".$pageViews."]" ; 
										$first = false ; 
										$nb++ ; 
									}
								}
								$beforelast_persons = $last_persons ; 	
								$beforelast_visits = $last_visits ; 
								$last_persons = $visit ; 
								$last_visits = $pageViews ; 
							}
							$width = "900" ; 
							$height = "400" ; 
							
							$colors = $this->get_param('local_color') ; 
							if ($this->get_param('local_color')=="") {
								$colors = "['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']" ; 
							}
							
							?>
							<h3><?php echo __('Local Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Local Data, here is the number of visits and the number of page views.', $this->pluginID)?></p>
							<p><?php 
								if ($pas_local=="day") {
									echo sprintf(__('Today, %s people have visited your site and %s pages have been viewed (%s and %s for yesterday).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas_local=="week") {
									echo sprintf(__('This week, %s people have visited your site and %s pages have been viewed (%s and %s for last week).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								} else if ($pas_local=="month") {
									echo sprintf(__('This month, %s people have visited your site and %s pages have been viewed (%s and %s for last month).', $this->pluginID), "<span style='font-size:120%;font-weight:bold;'>".$last_persons."</span>","<span style='font-size:120%;font-weight:bold;'>".$last_visits."</span>","<span style='font-weight:bold;'>".$beforelast_persons."</span>", "<span style='font-weight:bold;'>".$beforelast_visits."</span>") ; 
								}?>
							</p>							
							<div id="local_visits_count" style="margin: 0px auto; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
							<script  type="text/javascript">
								google.setOnLoadCallback(CountVisits_local);
								function CountVisits_local() {
									var data = new google.visualization.DataTable();
									data.addColumn('string', '<?php echo __('Month', $this->pluginID)?>');
									data.addColumn('number', '<?php echo __('Number of Visitors', $this->pluginID)?>');
									data.addColumn('number', '<?php echo __('Number of Page Views', $this->pluginID)?>');
									data.addRows([<?php echo $rows ; ?>]);
									var options = {
									  	width: <?php echo $width ; ?>, 
									 	height: <?php echo $height ; ?>,
									  	colors:<?php echo $colors?>,
									  	title: '<?php echo sprintf(__("Visitors and Page Views (%s)", $this->pluginID), $ptd_local) ?>',
									  	hAxis: {title: '<?php echo __('Time Line', $this->pluginID)?>'}
									};

									var chart = new google.visualization.ColumnChart(document.getElementById('local_visits_count'));
									chart.draw(data, options);
								}
							</script>
							<?php
						}
						

						
					$content_graph = ob_get_clean() ; 
					if (strlen($content_graph)>0) {
						$box = new boxAdmin (__("Visits Count", $this->pluginID), $content_graph) ; 
						echo $box->flush() ; 
					}
					
					// Creating the graph for the Google
					// Analytics type distribution
					//----------------------------------
					
					ob_start() ; 
						if ( ($this->get_param('google_show_type')) && (isset($data['browser'])) && (isset($data['os'])) ) {
							$google_show = true ; 
							$width = 450 ; 
							$height = 300 ; 
							
							$colors = $this->get_param('google_color') ; 
							if ($this->get_param('google_color')=="") {
								$colors = "['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']" ; 
							}
							
							?>
							<h3><?php echo __('Google Analytics Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Google Analytics Data, here is the distribution of browser types and OS.', $this->pluginID)?></p>
							<div style="margin: 0px auto; width:<?php echo $width*2; ?>px; height:<?php echo $height; ?>px;">
								<div id="google_visitors_browser" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script type="text/javascript">
								google.setOnLoadCallback(CountTypes);
								function CountTypes() {
										var data = new google.visualization.DataTable();
										data.addColumn('string', '<?php echo __('Browser name', $this->pluginID)?>');
										data.addColumn('number', '<?php echo __('Number of visitors', $this->pluginID)?>');
										data.addRows([
											<?php
											$first = true ; 
											foreach ($data['browser'] as $k => $d) {
												if (!$first) echo "," ; 
												echo "['".$k."', ".$d["ga:visits"]."]" ; 
												$first = false ; 
											}
											?>
										]);
										var options = {
											title: '<?php echo sprintf(__("Browser distribution (%s)", $this->pluginID), $ptd) ?>',
											colors:<?php echo $colors ?>,
											width: <?php echo $width ; ?>, 
									 		height: <?php echo $height ; ?>
										};
										var chart = new google.visualization.PieChart(document.getElementById('google_visitors_browser'));
										chart.draw(data, options);
									}
								</script>
								<div id="google_visitors_os" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script  type="text/javascript">
								google.setOnLoadCallback(CountTypesOS);
								function CountTypesOS() {
										var data = new google.visualization.DataTable();
										data.addColumn('string', '<?php echo __('OS name', $this->pluginID)?>');
										data.addColumn('number', '<?php echo __('Number of visitors', $this->pluginID)?>');
										data.addRows([
											<?php
											$first = true ; 
											foreach ($data['os'] as $k => $d) {
												if (!$first) echo "," ; 
												echo "['".$k."', ".$d["ga:visits"]."]" ; 
												$first = false ; 
											}
											?>
										]);
										var options = {
											title: '<?php echo sprintf(__("Operating System distribution (%s)", $this->pluginID), $ptd) ?>',
											colors:<?php echo $colors?>,
											width: <?php echo $width ; ?>, 
									 		height: <?php echo $height ; ?>	
										};
										var chart = new google.visualization.PieChart(document.getElementById('google_visitors_os'));
										chart.draw(data, options);
									}
								</script>
							</div>
							<?php
						}
						
						if ( ($this->get_param('local_show_type')) && (isset($data_local['browser'])) && (isset($data_local['os'])) ) {
							$google_show = true ; 
							$width = 450 ; 
							$height = 300 ; 
							
							$colors = $this->get_param('local_color') ; 
							if ($this->get_param('local_color')=="") {
								$colors = "['#0A3472', '#2EBBFD', '#57AEBE', '#537E78', '#49584B', '#72705A', '#807374', '#5E5556', '#55475E', '#2F2C47']" ; 
							}
							
							?>
							<h3><?php echo __('Local Data', $this->pluginID)?></h3>
							<p><?php echo __('According to Local Data, here is the distribution of browser types and OS.', $this->pluginID)?></p>
							<div style="margin: 0px auto; width:<?php echo $width*2; ?>px; height:<?php echo $height; ?>px;">
								<div id="local_visitors_browser" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script type="text/javascript">
								google.setOnLoadCallback(CountTypes_local);
								function CountTypes_local() {
										var data = new google.visualization.DataTable();
										data.addColumn('string', '<?php echo __('Browser name', $this->pluginID)?>');
										data.addColumn('number', '<?php echo __('Number of visitors', $this->pluginID)?>');
										data.addRows([
											<?php
											$first = true ; 
											foreach ($data_local['browser'] as $k => $d) {
												if (!$first) echo "," ; 
												echo "['".$k."', ".$d["visits"]."]" ; 
												$first = false ; 
											}
											?>
										]);
										var options = {
											title: '<?php echo sprintf(__("Browser distribution (%s)", $this->pluginID), $ptd_local) ?>',
											colors:<?php echo $colors ; ?>,
											width: <?php echo $width ; ?>, 
									 		height: <?php echo $height ; ?>
										};
										var chart = new google.visualization.PieChart(document.getElementById('local_visitors_browser'));
										chart.draw(data, options);
									}
								</script>
								<div id="local_visitors_os" style="float: left; margin: 0; width:<?php echo $width; ?>px; height:<?php echo $height; ?>px;"></div>
								<script  type="text/javascript">
								google.setOnLoadCallback(CountTypesOS_local);
								function CountTypesOS_local() {
										var data = new google.visualization.DataTable();
										data.addColumn('string', '<?php echo __('OS name', $this->pluginID)?>');
										data.addColumn('number', '<?php echo __('Number of visitors', $this->pluginID)?>');
										data.addRows([
											<?php
											$first = true ; 
											foreach ($data_local['os'] as $k => $d) {
												if (!$first) echo "," ; 
												echo "['".$k."', ".$d["visits"]."]" ; 
												$first = false ; 
											}
											?>
										]);
										var options = {
											title: '<?php echo sprintf(__("Operating System distribution (%s)", $this->pluginID), $ptd_local) ?>',
											colors:<?php echo $colors ;?>,
											width: <?php echo $width ; ?>, 
									 		height: <?php echo $height ; ?>	
										};
										var chart = new google.visualization.PieChart(document.getElementById('local_visitors_os'));
										chart.draw(data, options);
									}
								</script>
							</div>
							<?php
						}
						
					$content_graph = ob_get_clean() ; 
					if (strlen($content_graph)>0) {
						$box = new boxAdmin (__("Browser and OS Statistics", $this->pluginID), $content_graph) ; 
						echo $box->flush() ; 
					}
					
					// Display Information on last viewed pages
					//----------------------------------

					ob_start() ; 
						if ($this->get_param('local_detail')) {
							$sql_select = "SELECT * FROM ".$this->table_name." WHERE " ; 
							$sql_select .= "type='single' " ; 
							$sql_select .= "ORDER BY time DESC " ; 
							$nombre = "50" ;

							foreach ($this->get_param('local_detail_nb') as $n) {
								if (strpos($n[0], "*")!==false) {
									switch ($n[1]) {
										case 'nb100' : $nombre="100" ; break ; 
										case 'nb50' : $nombre="50" ; break ; 
										case 'nb20' : $nombre="20" ; break ; 
										case 'nb10' : $nombre="10" ; break ; 
									}
								}
							}							
							$sql_select .= "LIMIT ".$nombre." ; " ; 
							$results = $wpdb->get_results($sql_select) ; 

							$nb_result = 0 ; 
							$table = new adminTable() ;
							$table->title(array(__("Time", $this->pluginID),__("IP Address", $this->pluginID), __("Viewed Page", $this->pluginID), __("Browser", $this->pluginID), __("OS", $this->pluginID) , __("Referer", $this->pluginID), __("Time spent", $this->pluginID) )) ;
							?>
							
							<p><?php echo sprintf(__('Please note that the current local time of the server is %s. If it is not correct, please set the Wordpress installation correctly.', $this->pluginID), "<strong>".date_i18n('Y-m-d H:i:s')."</strong>")?></p>
							<p><?php echo __('If the entry is displayed for the first time, the time field will be in bold characters.', $this->pluginID)?></p>
							<p><?php echo __('If the user accesses your website for the first time today, the IP field will be in blod characters.', $this->pluginID)?></p>
							<?php
							foreach ( $results as $l ) {
								$time = $l->time ; 
								if (!$l->viewed) {
									$time = "<b>".$l->time."</b>" ; 
								}
								$cel1 =  new adminCell($time) ;
								$ip = $l->ip ; 
								$ips = explode (",", $ip) ; 
								$content_ip = "" ; 
								for ($i=0; $i<count($ips) ; $i++) {
									if ($i!=count($ips)-1) {
										$content_ip .= "<small>".$ips[$i]."</small> via " ; 
									} else {
										if ($l->uniq_visit>0) {
											$content_ip .= "<b>".$ips[$i]."</b>" ; 
										} else {
											$content_ip .= $ips[$i] ; 
										}
									}
								}
								
								$cel2 =  new adminCell($content_ip);
								$idpage =  url_to_postid( $l->page ) ; 
								// Si on a trouve
								if ($idpage!=0) {
									$page = "<a href='".get_permalink($idpage)."'>".get_the_title($idpage)."</a>" ; 
								} else {
									$page = "<a href='".$l->page."'>".$l->page."</a>" ; 
								}
								$cel3 =  new adminCell($page);
								$cel4 =  new adminCell($l->browserName." ".$l->browserVersion);
								//$cel5 = new adminCell($l->platformName." ".$l->platformVersion." [".$l->browserUserAgent."]") ;
								$cel5 = new adminCell($l->platformName." ".$l->platformVersion) ;
								$source = "" ; 
								$referer = $l->referer ; 
								$type_referer = "link" ;
								if (preg_match("/^http:\/\/www\.google(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "google" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[2]))) ; 
								} else if ( (preg_match("/^http:\/\/www\.google(.*)imgres(.*)imgurl=([^&|^#]+)/i", $referer, $matches)) ) {
									$source = "google_image" ; 
									$type_referer = "img" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if ( (preg_match("/^http:\/\/www\.google(.*)[?|&|#]q=([&#]+)/i", $referer, $matches)) || (preg_match("/^http:\/\/www\.google(.*)[?|&|#]q=$/i", $referer, $matches)) ) {
									$source = "google" ; 
									$type_referer = "stripped_words" ;
									$referer = __('Unknown keywords.', $this->pluginID) ; 
								} else if (preg_match("/^http:\/\/www\.bing(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "bing" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[2]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.yahoo\.(.*)[?|&|#]p=([^&|^#]+)/i", $referer, $matches)) {
									$source = "yahoo" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("/^http:\/\/(.*)\.aol\.(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "aol" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[3]))) ; 
								} else if (preg_match("#^http://wordpress\.org/extend/plugins/([^/]*)/#i", $referer, $matches)) {
									$source = "wordpress" ; 
									$type_referer = "words" ;
									$referer = sprintf(__('Plugin: %s', $this->pluginID),strip_tags($matches[1])) ; 
								} else if (preg_match("#^http://(.*)/wp-admin/admin\.php\?page=([^/]*)/#i", $referer, $matches)) {
									$source = "wordpress_local" ; 
									$type_referer = "words" ;
									$referer = sprintf(__('Plugin: %s, website: %s', $this->pluginID),strip_tags($matches[2]), "<a href='http://".strip_tags($matches[1])."'>http://".strip_tags($matches[1])."</a>") ; 
								} else if (preg_match("/^http:\/\/search\.babylon(.*)[?|&|#]q=([^&|^#]+)/i", $referer, $matches)) {
									$source = "babylon" ; 
									$type_referer = "words" ;
									$referer = str_replace("\'", "'", strip_tags(urldecode($matches[2]))) ; 
								} else if ( (preg_match("/^http:\/\/search\.free(.*)/i", $referer, $matches)) ) {
									$source = "free" ; 
									$type_referer = "stripped_words" ;
									$referer = __('Unknown keywords.', $this->pluginID) ; 
								}
								$content_referer = "" ; 
								if ($type_referer == "words") {
									$content_referer = "<a href='".strip_tags($l->referer)."'><img style='border:0' src='".WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/".$source.".png"."' alt='".$source."'></a> ".ucfirst(strtolower($referer)) ;
								} else if ($type_referer == "stripped_words") {
									$content_referer = "<img src='".WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/".$source."_masqued.png"."' alt='".ucfirst(strtolower($source))."'> <span style='color:#BBBBBB'>".$referer."</span>" ;
								} else if ($type_referer == "img") {
									$content_referer = "<img src='".WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/".$source.".png"."' alt=''> <img src='$referer' alt='' width='100px'/>" ;
								} else if ($type_referer == "link") {
									$length = 50 ; 
									$refererDisplay = substr($referer, 0, $length);
									if (strlen($referer) > $length) {
										$refererDisplay .= '...';
									}
									$content_referer = "<a href='".$referer."'>".urldecode($refererDisplay)."</a>" ;
								}
								$cel6 = new adminCell($content_referer) ;
								$minutes = intval(($l->refreshNumber*10 / 60)); 
								$hms = str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";
								$seconds = intval(($l->refreshNumber*10) % 60); 
								$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
								
								if ($l->refreshNumber>=($this->maxTime/10)) {
									$minutes = intval(($this->maxTime / 60)); 
									$hms = str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";
									$seconds = intval($this->maxTime % 60); 
									$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
									$hms = ">".$hms ; 
								}
								$cel7 = new adminCell($hms) ;
								$table->add_line(array($cel1, $cel2, $cel3, $cel4, $cel5, $cel6, $cel7), $l->id) ;
								$nb_result ++ ; 
							}
							
							// On met a jour la table pour dire qu'on a tout vu
							$sql_update = "UPDATE ".$this->table_name." SET viewed=TRUE " ; 
							$wpdb->query($sql_update) ; 
							
							if ($nb_result==0) {
								$cel1 = new adminCell(__("No data for now... Please wait until someone visits your website", $this->pluginID)) ;
								$cel2 = new adminCell("") ;
								$cel3 = new adminCell("") ;
								$cel4 = new adminCell("") ;
								$cel5 = new adminCell("") ;
								$cel6 = new adminCell("") ;
								$cel7 = new adminCell("") ;
								$table->add_line(array($cel1, $cel2, $cel3, $cel4, $cel5, $cel6, $cel7), '1') ;
							}
							echo $table->flush() ; 
						}
					$content_graph = ob_get_clean() ; 
					if (strlen($content_graph)>0) {
						$box = new boxAdmin (sprintf(__("Details on the Last %s Viewed Pages", $this->pluginID),$nombre), $content_graph) ; 
						echo $box->flush() ; 
					}					
					
				} else {
					echo "<p>".__('You have configured no graph... Please go on the parameters tab to configure them.', $this->pluginID)."</p>" ; 
				}
				
			$tabs->add_tab(__('Web Statistics',  $this->pluginID), ob_get_clean()) ; 	
				
			$tabs->add_tab(__('Parameters',  $this->pluginID), $parameters , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new translationSL($this->pluginID, $plugin) ; 
				$trans->enable_translation() ; 
			$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new feedbackSL($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				// A liste of plugin slug to be excluded
				$exlude = array('wp-pirate-search') ; 
				// Replace sedLex by your own author name
				$trans = new otherPlugins("sedLex", $exlude) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			
			// Before this comment, you may modify whatever you want
			//===============================================================================================
			?>
			<?php echo $this->signature ; ?>
		</div>
		<?php
	}
	
	
	
	/** ====================================================================================================================================================
	* Test if IP is private
	*
	* @return string adress
	*/

	function ip_is_private($ip) {
		if ($ip=="unknown") {
			return true ; 
		}
			
		if (function_exists('filter_var')){
			return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
		}
		return false ; 
	}
	/** ====================================================================================================================================================
	* Get the Remote address (support of proxy)
	*
	* @return string adress
	*/

	function getRemoteAddress() {
		$hostname = $_SERVER['REMOTE_ADDR'];
		
		// GET the header of the HTTP request
		
		$headers = array();
		$rx_http = '/\AHTTP_/';
		foreach($_SERVER as $key => $val) {
			if( preg_match($rx_http, $key) ) {
				$headers_key = preg_replace($rx_http, '', $key);
				$rx_matches = array();
				// do some nasty string manipulations to restore the original letter case
				// this should work in most cases
				$rx_matches = explode('_', $headers_key);
				if (count($rx_matches) > 0 and strlen($headers_key) > 2 ) {
					foreach($rx_matches as $ak_key => $ak_val) {
						$rx_matches[$ak_key] = ucfirst($ak_val);
					}
					$headers_key = implode('-', $rx_matches);
				}	
				$headers[$headers_key] = $val;
			}
		}
		
		// Get the list of proxy
		
		$list_proxy = array() ; 
		$list_proxy[] = $hostname ; 
		
		foreach($headers as $k => $v) {
			if(strcasecmp($k, "x-forwarded-for"))
				continue;
			$list_proxy_temp = explode(",", $v);
			foreach ($list_proxy_temp as $h) {
				if ($this->ip_is_private($h)) {
					continue ; 
				}
				$list_proxy[] = $h ;
			}
			break;
		}
		
		
		
		// We remove duplicate and reverse it )
		$list_proxy = implode(",", array_unique($list_proxy)) ; 

		return $list_proxy ;
	}
	/** ====================================================================================================================================================
	* Callback updating the SQL table with browser info
	*
	* @return void
	*/
	
	function UserWebStat() {
		global $wpdb ; 
		$wpdb->show_errors(); 
		
		// Retrieve Information and Parameters
		$browserUserAgent = mysql_real_escape_string($_POST['browserUserAgent']) ; 
		$cookieEnabled = mysql_real_escape_string($_POST['cookieEnabled']) ; 
		$referer = mysql_real_escape_string($_POST['referer']) ; 
		$page = mysql_real_escape_string($_POST['page']) ; 
		$singleCookie = mysql_real_escape_string($_POST['singleCookie']) ; 
		$refreshNumber = mysql_real_escape_string($_POST['refreshNumber']) ; 
		
		// DETECTION DE L'OS
		$brow = new browsersOsDetection($browserUserAgent) ; 
		$browserName = 	$brow->getBrowserName() ; 
		$browserVersion = 	$brow->getBrowserVersion() ; 
		$platformName = 	$brow->getPlatformName() ; 
		$platformVersion = 	$brow->getPlatformVersion() ; 
		
		if ($singleCookie=="") {
			$singleCookie = md5(microtime()) ; 
		} else {
			// On regarde s'il existe deja une page avec les memes informations il y a moins d'une minute, 
			// si c'est le cas, on met a jour l'entree 
			$sql_select = "SELECT id, refreshNumber FROM ".$this->table_name." WHERE " ; 
			$sql_select .= "type='single' AND " ;   
			$sql_select .= "page='".$page."' AND " ;   
			$sql_select .= "singleCookie='".$singleCookie."' AND " ; 
			$sql_select .= "time > DATE_SUB('".date_i18n('Y-m-d H:i:s')."',INTERVAL 1 MINUTE) LIMIT 1" ; 
			$result = $wpdb->get_row($sql_select) ; 
			if ($result) {
				// Si depasse le max time  on arrete le rafraichissement car c'est probablement une page 'ouverte sans personne devant'.
				if (floor($result->refreshNumber*10) > $this->maxTime) {
					echo "0" ; 
					die() ; 
				}
				// On met a jour les informations de la base
				$sql_update = "UPDATE ".$this->table_name." SET " ; 
				$sql_update .= "time='".date_i18n('Y-m-d H:i:s')."', " ; 
				$sql_update .= "refreshNumber='".$refreshNumber."' WHERE " ; 
				$sql_update .= "id='".$result->id."' " ; 
				$wpdb->query($sql_update) ; 
			} else {
				// We check if this user has already seen a page today
				$sql_select = "SELECT id FROM ".$this->table_name." WHERE " ; 
				$sql_select .= "type='single' AND " ;   
				$sql_select .= "singleCookie='".$singleCookie."' AND " ; 
				$sql_select .= "time BETWEEN '".date_i18n('Y-m-d 0:0:0')."' AND '".date_i18n('Y-m-d H:i:s')."' " ; 
				$result = $wpdb->get_row($sql_select) ; 
				if ($result) {
					$unique = "0" ; 
				} else {
					$unique = "1" ; 
				}
				
				// Insert new entry for this visitor
				$refreshNumber = 0 ; 
				$sql_insert = "INSERT INTO ".$this->table_name." SET " ; 
				$sql_insert .= "type='single', " ; 
				$sql_insert .= "count=1, " ; 
				$sql_insert .= "uniq_visit=".$unique.", " ; 
				$sql_insert .= "viewed=FALSE, " ; 
				$sql_insert .= "ip='".$this->getRemoteAddress()."', " ; 
				$sql_insert .= "browserName='".$browserName."', " ; 
				$sql_insert .= "browserVersion='".$browserVersion."', " ;  
				$sql_insert .= "platformName='".$platformName."', " ;  
				$sql_insert .= "platformVersion='".$platformVersion."', " ;  
				$sql_insert .= "browserUserAgent='".$browserUserAgent."', " ;  
				$sql_insert .= "referer='".$referer."', " ;  
				$sql_insert .= "page='".$page."', " ;   
				$sql_insert .= "time='".date_i18n('Y-m-d H:i:s')."', " ; 
				$sql_insert .= "singleCookie='".$singleCookie."', " ; 
				$sql_insert .= "refreshNumber='".$refreshNumber."'" ; 
				$wpdb->query($sql_insert) ; 
			}
		}
		
		// On concatene si besoin pour economiser de la place dans la bdd
		if ($this->get_param('local_cron_concat')=="") {
			$this->set_param('local_cron_concat', strtotime(date_i18n("Y-m-d 0:0:1")." +1 day")) ; 
		}
		
		if (strtotime(date_i18n("Y-m-d H:i:s"))>$this->get_param('local_cron_concat')) {
			// On me en place le prochain cron
			$this->set_param('local_cron_concat', strtotime(date_i18n("Ymd 0:0:1")." +1 day")) ; 
			
			$offset = 2 ; // Deux jours et 7 jours avant ...
			$max = -1 ; 
			// On trouve le plus vieux single existant
			$sql_old = "SELECT time FROM ".$this->table_name." WHERE " ; 
			$sql_old .= "type='single' " ; 
			$sql_old .= "ORDER BY time ASC LIMIT 1" ; 
			$oldest = $wpdb->get_row($sql_old) ; 
			if ($oldest) {
				$max = floor((strtotime(date_i18n("Y-m-d 0:0:0")." -".($offset)." day")-strtotime($oldest->time))/86400) ; 
			}
			
			for ($i=0 ; $i<=$max ; $i++) {
				$offset ++ ; 
				$date1 = date_i18n("Y-m-d  0:0:0", strtotime(date_i18n("Y-m-d")." -".($offset)." day"));
				$date2 = date_i18n("Y-m-d  23:59:59", strtotime(date_i18n("Y-m-d")." -".($offset)." day"));

				// On concatene les donnees single en donnees day_browser et day_os
				$sql_browser = "SELECT SUM(count) as nb, SUM(uniq_visit) as nb2, browserName FROM ".$this->table_name." WHERE " ; 
				$sql_browser .= "time between '".$date1."' and '".$date2."' AND " ; 
				$sql_browser .= "type='single' " ; 
				$sql_browser .= "GROUP BY browserName" ; 
				$res = $wpdb->get_results($sql_browser) ; 
				foreach ($res as $r) {
					$sql_insert = "INSERT INTO ".$this->table_name." SET " ; 
					$sql_insert .= "type='day_browser', " ; 
					$sql_insert .= "count=".$r->nb.", " ; 
					$sql_insert .= "uniq_visit=".$r->nb2.", " ; 
					$sql_insert .= "viewed=TRUE, " ; 
					$sql_insert .= "ip='', " ; 
					$sql_insert .= "browserName='".$r->browserName."', " ; 
					$sql_insert .= "browserVersion='', " ;  
					$sql_insert .= "platformName='', " ;  
					$sql_insert .= "platformVersion='', " ;  
					$sql_insert .= "browserUserAgent='', " ;  
					$sql_insert .= "referer='', " ;  
					$sql_insert .= "page='', " ;   
					$sql_insert .= "time='".$date1."', " ; 
					$sql_insert .= "singleCookie='', " ; 
					$sql_insert .= "refreshNumber=0" ; 
					$wpdb->query($sql_insert) ; 
				}
				
				$sql_os = "SELECT SUM(count) as nb, SUM(uniq_visit) as nb2, platformName FROM ".$this->table_name." WHERE " ; 
				$sql_os .= "time between '".$date1."' and '".$date2."' AND " ; 
				$sql_os .= "type='single' " ; 
				$sql_os .= "GROUP BY platformName" ; 
				$res = $wpdb->get_results($sql_os) ; 
				foreach ($res as $r) {
					$sql_insert = "INSERT INTO ".$this->table_name." SET " ; 
					$sql_insert .= "type='day_platform', " ; 
					$sql_insert .= "count=".$r->nb.", " ; 
					$sql_insert .= "uniq_visit=".$r->nb2.", " ; 
					$sql_insert .= "viewed=TRUE, " ; 
					$sql_insert .= "ip='', " ; 
					$sql_insert .= "browserName='', " ; 
					$sql_insert .= "browserVersion='', " ;  
					$sql_insert .= "platformName='".$r->platformName."', " ;  
					$sql_insert .= "platformVersion='', " ;  
					$sql_insert .= "browserUserAgent='', " ;  
					$sql_insert .= "referer='', " ;  
					$sql_insert .= "page='', " ;   
					$sql_insert .= "time='".$date1."', " ; 
					$sql_insert .= "singleCookie='', " ; 
					$sql_insert .= "refreshNumber=0" ; 
					$wpdb->query($sql_insert) ; 
				}
				
				$sql_delete = "DELETE FROM ".$this->table_name." WHERE " ; 
				$sql_delete .= "time between '".$date1."' and '".$date2."' AND " ; 
				$sql_delete .= "type='single' " ; 
				$wpdb->query($sql_delete) ; 
			}
		}
		
		
		// On ne force le rafraichissement que s'il y a des cookies (car sinon doublons)
		if ($cookieEnabled==true)
			echo $singleCookie.",".($refreshNumber+1) ; 

		die() ; 
	}
	
	
	
	/** ====================================================================================================================================================
	* Convert the on-time token into a session token
	*
	* @return string the token (or false in case of error)
	*/

	function get_session_token($ott) {
		$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
		$args['headers']['Authorization'] = 'AuthSub token="' . $ott . '"';
		$response = wp_remote_post("https://www.google.com/accounts/AuthSubSessionToken", $args);
		
		// Check for WordPress error
		if ( is_wp_error($response) ) {
			return false;
		}
		// Get the response code
		if ($response['response']['code']!="200") {
			return false ; 
		}
	
		// Get the authentication token and  update it
		$token = substr(strstr($response['body'], "Token="), 6);	
		return $token ; 
	}
	
	/** ====================================================================================================================================================
	* Revoke the session token 
	*
	* @return void
	*/

	function revoke_session_token($ost) {
		$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
		$args['headers']['Authorization'] = 'AuthSub token="' . $ost . '"';
		$response = wp_remote_get("https://www.google.com/accounts/AuthSubRevokeToken", $args);
	}
	
	/** ====================================================================================================================================================
	* Get the correct Google error message
	*
	* @return void
	*/

	function get_google_error($resp) {
		$orig_resp = $resp ; 
		$resp = str_replace("<","&lt;",$resp) ; 
		$resp = str_replace(">","&gt;",$resp) ; 
		$resp = str_replace("\n","<br/>",$resp) ; 
		$resp = str_replace(" ","&nbsp;",$resp) ; 
		
		if (preg_match("/userRateLimitExceededUnreg/", $orig_resp)) {
			return array('error' => __('Google states that the User Rate Limit has been exceeded!',  $this->pluginID)."<br/>".__('In order to avoid User Rate Limit, you should get an API key.',  $this->pluginID)."<br/>".sprintf(__("To get this API key, please visit %s, create a projet, allow Google Analytics, and then go to API console to get a %s", $this->pluginID), "<a href='https://code.google.com/apis/console'>https://code.google.com/apis/console</a>", "'<i>Key for browser apps</i>'")."<br/><br/><img src='".WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."img/api_access_key.jpg'><br/><br/>".__('Once a key generated, please configure it in the configuration tab.',  $this->pluginID)."<br/><br/><b>".__('If you have just saved the API key in the parameters, please reload the page and it shoud work.',  $this->pluginID)."</b>");
		}

		return array('error' => __('The authorization seems to have expired or has been revoked. Please revoke the authorization (see below) and re-authentify!',  $this->pluginID)."<br/>".$resp);
	}
	
		
	
	/** ====================================================================================================================================================
	* Checks if the WordPress API is a valid method for selecting an account
	*
	* @return array list of accounts if available, if there is an error the array contain an 'error' field
	*/

	function get_analytics_accounts(){
		$accounts = array();
		
		if (!$this->get_param('googlewebstat')) 
			return array();
		
		$api_key = "" ; 
		if ($this->get_param('google_api_key')!="") {
			$api_key = "?key=".$this->get_param('google_api_key') ; 
		}
		
		if ( $this->get_param('googlewebstat_auth_token') != "" ) {
			$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
			$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
			$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/management/accounts/~all/webproperties/~all/profiles".$api_key, $args);
			// Check for WordPress error
			if ( is_wp_error($response) ) {
				return array('error' => __('An unknown error occured during the API request',  $this->pluginID));
			}
			// Get the response code
			if ($response['response']['code']!="200") {
				return $this->get_google_error($response['body']) ; 
			}
			
			$doc = new DOMDocument();
			$doc->loadXML($response['body']);
			
			$entries = $doc->getElementsByTagName('entry');
			$i = 0;
			$profiles= array();
			foreach($entries as $entry) {
			    $profiles[$i] = array();
			    $properties = $entry->getElementsByTagName('property');
			    foreach($properties as $property) {
				if (strcmp($property->getAttribute('name'), 'ga:accountId') == 0)
					$profiles[$i]["accountId"] = $property->getAttribute('value');
		 
				if (strcmp($property->getAttribute('name'), 'ga:profileName') == 0)
					$profiles[$i]["title"] = $property->getAttribute('value');
				
				if (strcmp($property->getAttribute('name'), 'ga:accountName') == 0)
					$profiles[$i]["accountName"] = $property->getAttribute('value');
		 
				if (strcmp($property->getAttribute('name'), 'dxp:tableId') == 0)
					$profiles[$i]["tableId"] = $property->getAttribute('value');
					
				if (strcmp($property->getAttribute('name'), 'ga:profileId') == 0)
					$profiles[$i]["profileId"] = $property->getAttribute('value');
					
				if (strcmp($property->getAttribute('name'), 'ga:webPropertyId') == 0)
					$profiles[$i]["webPropertyId"] = $property->getAttribute('value');
			    }
				 
			    $i++;
			}
			
			return $profiles;
		} else {
			return array('error' => __('No token provided!',  $this->pluginID));
		}
	}
	
	/** ====================================================================================================================================================
	* Get the analytics data
	* 
	* @param string $date should be similar to "start-date=2011-12-21&end-date=2011-12-22"
	* @return 
	*/

	function get_analytics_data($date, $pas){
		$accounts = array();
		
		$api_key = "" ; 
		if ($this->get_param('google_api_key')!="") {
			$api_key = "&key=".$this->get_param('google_api_key') ; 
		}
		
		if ( $this->get_param('googlewebstat_auth_token') != "" ) {
			if ($this->get_param('googlewebstat_acc_id') != "") {
				$result = array() ; 
				$errorGoogle = "" ; 
				if ($this->get_param('google_show_visits')) {
				
					// GET THE VISIT COUNT
					//==========================
					$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
					$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
					$dimensions = $pas ; 
					$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/data?ids=".$this->get_param('googlewebstat_acc_id')."&".$date."&metrics=ga:visits,ga:pageviews&dimensions=".$dimensions.$api_key, $args);

					$result['visits'] = $this->parseGoogleResponse($response) ; 
					
					if (isset($result['visits']['error'])) {
						$errorGoogle .=  "<p>".$result['visits']['error']."</p>" ; 
						$result['visits'] = array() ; 
					}
				}

				if ($this->get_param('google_show_type')) {
					// GET THE TYPE OF THE BROWSER
					//========================================
					$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
					$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
					$dimensions = "ga:browser" ; 
					$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/data?ids=".$this->get_param('googlewebstat_acc_id')."&".$date."&metrics=ga:visits&dimensions=".$dimensions.$api_key, $args);
					
					$result['browser'] = $this->parseGoogleResponse($response) ; 
					if (isset($result['browser']['error'])) {
						if (strpos($errorGoogle ,$result['browser']['error'] )===FALSE) {
							$errorGoogle .= "<p>".$result['browser']['error']."</p>" ; 
						}
						$result['browser'] = array() ; 
					}
					
					// GET THE TYPE OF THE OS
					//========================================
					$args['headers'] = array('Content-Type' => 'application/x-www-form-urlencoded');
					$args['headers']['Authorization'] = 'AuthSub token="' . $this->get_param('googlewebstat_auth_token')  . '"';
					$dimensions = "ga:operatingSystem" ; 
					$response = wp_remote_get("https://www.googleapis.com/analytics/v2.4/data?ids=".$this->get_param('googlewebstat_acc_id')."&".$date."&metrics=ga:visits&dimensions=".$dimensions.$api_key, $args);
					
					$result['os'] = $this->parseGoogleResponse($response) ; 
					if (isset($result['os']['error'])) {
						if (strpos($errorGoogle , $result['os']['error'])===FALSE) {
							$errorGoogle .= "<p>".$result['os']['error']."</p>" ; 
						}
						$result['os'] = array() ; 
					}
				}
				
				if ($errorGoogle!="") {
					echo "<div class='error fade'>$errorGoogle</div>" ; 
				}
				
				return $result ; 
			} else {
				echo "<div class='error fade'><p>".__('No website selected for Google statistics (see the parameter tab)!',  $this->pluginID)."</p></div>" ; 
				return array('error' => __('No website selected!',  $this->pluginID));
			}
		} else {
			return array('error' => __('No token provided!',  $this->pluginID));
		}
	
	
	}
	
	/** ====================================================================================================================================================
	* Parse the Google Analytics response
	* 
	* @return 
	*/

	function parseGoogleResponse($response) {
		// Check for WordPress error
		if ( is_wp_error($response) ) {
			return array('error' => __('An unknown error occured during the API request',  $this->pluginID));
		}
		// Get the response code
		if ($response['response']['code']!="200") {
			return $this->get_google_error($response['body']) ; 
		}
		
		$aResult = array();

		$oDoc = new DOMDocument();
		$oDoc->loadXML($response['body']);
		$oEntries = $oDoc->getElementsByTagName('entry');
		foreach($oEntries as $oEntry){

			$cell_array = &$aResult ; 
			foreach($oEntry->getElementsByTagName('dimension') as $dimension) {
			    $cell_array = &$cell_array[$dimension->getAttribute('value')] ; 
			}
			
			$oMetrics = $oEntry->getElementsByTagName('metric');
			foreach($oMetrics as $oMetric){
				$cell_array[$oMetric->getAttribute('name')] = $oMetric->getAttribute('value') ; 
			}
		}
		return $aResult ; 
	}
	
	/** ====================================================================================================================================================
	* Get the local data
	* 
	* @param string $date1 should be similar to "20111221"
	* @param string $date2 should be similar to "20111230"
	* @return 
	*/

	function get_local_data($date1, $date2, $pas){
		global $wpdb ; 
		$result = array() ; 
		
		if ($this->get_param('local_show_visits')) {
			// GET THE VISIT COUNT
			//==========================
			if ($pas == 'day') {
				$sql_day = "SELECT DATE(time) AS day, SUM(count) as pageviews, SUM(uniq_visit) as visits FROM ".$this->table_name." WHERE " ; 
				$sql_day .= "(type='single' OR type='day_browser') AND " ; 
				$sql_day .= "time between '".$date1."' and '".$date2."' GROUP BY YEAR(time), MONTH(time), DAY(time)" ; 
				$res = $wpdb->get_results($sql_day) ; 
				$result['visits'] = array() ; 
				// On complete avec des fausses donnees si les jours ne sont pas presents
				for ($i=0 ; $i < floor((strtotime($date2)-strtotime($date1))/86400) ; $i++) {
					$result['visits'][" ".date_i18n("Ymd", strtotime($date1." +".$i." day"))." "] = array("pageviews" => 0, "visits" => 0) ; 
				}
				
				foreach ($res as $r) {
					$result['visits'][" ".date_i18n("Ymd", strtotime($r->day))." "] = array("pageviews" => $r->pageviews, "visits" => $r->visits) ; 
				}
			}
			if ($pas == 'week') {
				$sql_week = "SELECT YEARWEEK(time, 3) AS week, " ; 
				$sql_week .= 		"SUM(count) as pageviews, " ; 
				$sql_week .= 		"SUM(uniq_visit) as visits " ; 
				$sql_week .= 		"FROM ".$this->table_name." WHERE " ; 
				$sql_week .= "(type='single' OR type='day_browser') AND " ; 
				$sql_week .= "time between '".$date1."' and '".$date2."' GROUP BY YEARWEEK(time, 3)" ; 
				$res = $wpdb->get_results($sql_week) ;
				
				$result['visits'] = array() ; 
				
				// On complete avec des fausses donnees si les semaines ne sont pas presents
				$startTime = strtotime($date1);
				$endTime = strtotime($date2);
				$lastWeek = "" ; 
				while ($startTime < $endTime) {  
    				$result['visits'][" ".date_i18n('Y', $startTime)." "][" ".date_i18n('W', $startTime)." "] = array("pageviews" => 0, "visits" => 0) ; 
    				$lastWeek = date_i18n('YW', $startTime) ; 
    				$startTime += strtotime('+1 week', 0);
				}
				// Pour eviter de louper la derniere
				if (date_i18n('YW', $endTime)!=$lastWeek) {
    				$result['visits'][" ".date_i18n('Y', $endTime)." "][" ".date_i18n('W', $endTime)." "] = array("pageviews" => 0, "visits" => 0) ; 				
				}
				
				foreach ($res as $r) {
					$year = substr($r->week, 0,4) ; 
					$week = substr($r->week, 4) ; 
					$result['visits'][" ".$year." "][" ".$week." "] = array("pageviews" => $r->pageviews, "visits" => $r->visits) ; 
				}
			}
			
			if ($pas == 'month') {
				$sql_month = "SELECT CONCAT(YEAR(time), MONTH(time)) AS month, " ; 
				$sql_month .= 		"SUM(count) as pageviews, " ; 
				$sql_month .= 		"SUM(uniq_visit) as visits " ; 
				$sql_month .= 		"FROM ".$this->table_name." WHERE " ; 
				$sql_month .= "(type='single' OR type='day_browser') AND " ; 
				$sql_month .= "time between '".$date1."' and '".$date2."' GROUP BY YEAR(time), MONTH(time)" ; 
				$res = $wpdb->get_results($sql_month) ;
				
				$result['visits'] = array() ; 
				
				// On complete avec des fausses donnees si les mois ne sont pas presents
				$startTime = strtotime($date1);
				$endTime = strtotime($date2);
				$lastWeek = "" ; 
				while ($startTime < $endTime) {  
    				$result['visits'][" ".date_i18n('Y', $startTime)." "][" ".date_i18n('m', $startTime)." "] = array("pageviews" => 0, "visits" => 0) ; 
    				$lastMonth = date_i18n('Ym', $startTime) ; 
    				$startTime += strtotime('+1 month', 0);
				}
				// Pour eviter de louper la derniere
				if (date_i18n('Ym', $endTime)!=$lastMonth) {
    				$result['visits'][" ".date_i18n('Y', $endTime)." "][" ".date_i18n('m', $endTime)." "] = array("pageviews" => 0, "visits" => 0) ; 				
				}
				
				foreach ($res as $r) {
					$year = substr($r->month, 0,4) ; 
					$month = substr($r->month, 4) ; 
					$month = str_pad($month, 2, '0', STR_PAD_LEFT) ; 
					$result['visits'][" ".$year." "][" ".$month." "] = array("pageviews" => $r->pageviews, "visits" => $r->visits) ; 
				}
			}			
		}

		if ($this->get_param('local_show_type')) {
			// GET THE TYPE OF THE BROWSER
			//========================================
			$sql_browser = "SELECT SUM(count) as nb, browserName FROM ".$this->table_name." WHERE " ; 
			$sql_browser .= "time between '".$date1."' and '".$date2."' AND " ; 
			$sql_browser .= "(type='single' OR type='day_browser') AND " ; 
			$sql_browser .= "uniq_visit > 0 " ; 
			$sql_browser .= "GROUP BY browserName" ; 
			$res = $wpdb->get_results($sql_browser) ; 
			$result['browser'] = array() ; 
			foreach ($res as $r) {
				$result['browser'][" ".$r->browserName." "] = array("visits" => $r->nb) ; 
			}
			
			// GET THE TYPE OF THE OS
			//========================================
			
			$sql_os = "SELECT SUM(count) as nb, platformName FROM ".$this->table_name." WHERE " ; 
			$sql_os .= "time between '".$date1."' and '".$date2."' AND " ; 
			$sql_os .= "(type='single' OR type='day_platform') AND " ; 
			$sql_os .= "uniq_visit > 0 " ; 
			$sql_os .= "GROUP BY platformName" ; 
			$res = $wpdb->get_results($sql_os) ; 
			$result['os'] = array() ; 
			foreach ($res as $r) {
				$result['os'][" ".$r->platformName." "] = array("visits" => $r->nb) ; 
			}
		}

		return $result ; 
	}
	
	/** ====================================================================================================================================================
	* Callback to Compute a sitemaps upon save of posts
	* 
	* @return 
	*/

	function create_sitemap_upon_save () {
		$this->generateSitemaps("sitemap", true) ; 
	}			

	
	/** ====================================================================================================================================================
	* Compute a sitemaps
	* 
	* @param string $filename the filename of the sitemap (probably sitemap.xml)
	* @return 
	*/

	function generateSitemaps($filename, $force=false) {
		global $blog_id;
		
		if (is_multisite()) {
			$filename = $filename.$blog_id;
		} 
	
		if (is_file(ABSPATH . $filename.".xml") && !$force) {
			return array('info'=>'sitemaps already exists') ; 
		}
	
		$nb = $this->get_param('sitemaps_nb') ; 
		if ($nb==0)
			$nb=-1 ; 
		
	 	$postsForSitemap = get_posts(array(
			'numberposts' => $nb,
			'orderby' => 'modified',
			'post_type'  => array('post','page'),
			'order'    => 'DESC'
  		));

		$sitemap = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
		
		$sitemap .= "\t".'<url>'."\n" ; 
  		if (!is_multisite()) {
			$sitemap .= "\t\t".'<loc>'. get_home_url() .'</loc>'."\n" ; 
		} else {
			$sitemap .= "\t\t".'<loc>'. get_home_url($blog_id) .'</loc>'."\n" ; 
		}
  		$sitemap .= "\t\t".'<lastmod>'. date_i18n("Y-m-d") .'</lastmod>'."\n" ; 
  		$sitemap .= "\t\t".'<changefreq>daily</changefreq>'."\n" ; 
  		$sitemap .= "\t\t".'<priority>1.0</priority>'."\n" ; 
		$sitemap .= "\t".'</url>'."\n";
		
		foreach($postsForSitemap as $post) {
			$postdate = explode(" ", $post->post_modified);
			$sitemap .= "\t".'<url>'."\n" ; 
  			$sitemap .= "\t\t".'<loc>'. get_permalink($post->ID) .'</loc>'."\n" ; 
  			$sitemap .= "\t\t".'<lastmod>'. $postdate[0] .'</lastmod>'."\n" ; 
  			$sitemap .= "\t\t".'<changefreq>monthly</changefreq>'."\n" ; 
  			$sitemap .= "\t\t".'<priority>0.5</priority>'."\n" ; 
			$sitemap .= "\t".'</url>'."\n";
  		}

  		$sitemap .= '</urlset>'."\n";

  		if (@file_put_contents(ABSPATH . $filename.".xml", $sitemap)===FALSE) {
			return array('error'=>sprintf(__('The file %s cannot be created. Please make sure that the file rights allow writing in the following folder: %s', $this->pluginID), "<code>".$filename.".xml"."</code>", "<code>".ABSPATH."</code>")) ; 
			$this->set_param("sitemaps_date", sprintf(__('Problem with folder rights on %s',  $this->pluginID), "<code>".ABSPATH."</code>"));
		} else {
			if (function_exists('gzencode')) {
				$gz = gzencode($sitemap, 9);
				@file_put_contents(ABSPATH . $filename.".xml.gz", $gz) ; 
			}
			$this->set_param("sitemaps_date",  date_i18n("Y-m-d H:i:s"));
			$this->notifyCrawlers(get_site_url()."/".$filename.'.xml') ; 
			return array('info'=>'sitemaps saved') ; 
		}
		
		
	}
	
	/** ====================================================================================================================================================
	* Notify sitemaps to crawler
	* 
	* @param string $filename the filename of the sitemap (probably sitemap.xml)
	* @return 
	*/

	function notifyCrawlers($url) {
		//Ping Google
		if($this->get_param("sitemaps_notify_google")) {
			$sPingUrl="http://www.google.com/webmasters/sitemaps/ping?sitemap=" . urlencode($url);
			$response = wp_remote_get($sPingUrl) ;
			if ( is_wp_error($response) || ($response['response']['code']!="200") )  {
				$this->set_param("sitemaps_notify_google_date", __('An unknown error occured during the API request',  $this->pluginID));
			} else {
				$this->set_param("sitemaps_notify_google_date", date_i18n("Y-m-d H:i:s"));
			}
		}
		//Ping Ask
		if($this->get_param("sitemaps_notify_ask")) {
			$sPingUrl="http://submissions.ask.com/ping?sitemap=" . urlencode($url);
			$response = wp_remote_get($sPingUrl) ;
			if ( is_wp_error($response) || ($response['response']['code']!="200") )  {
				$this->set_param("sitemaps_notify_ask_date", __('An unknown error occured during the API request',  $this->pluginID));
			} else {
				$this->set_param("sitemaps_notify_ask_date", date_i18n("Y-m-d H:i:s"));
			}
		}
		//Ping Bing
		if($this->get_param("sitemaps_notify_bing")) {
			$sPingUrl="http://www.bing.com/webmaster/ping.aspx?siteMap=" . urlencode($url);
			$response = wp_remote_get($sPingUrl) ;
			if ( is_wp_error($response) || ($response['response']['code']!="200") )  {
				$this->set_param("sitemaps_notify_bing_date", __('An unknown error occured during the API request',  $this->pluginID));
			} else {
				$this->set_param("sitemaps_notify_bing_date", date_i18n("Y-m-d H:i:s"));
			}
		}
	}
		
}

$traffic_manager = traffic_manager::getInstance();

?>