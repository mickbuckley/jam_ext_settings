<?php
/**
* Jam IFrame Tab extension
* 
* Author: Mick Buckley.
* Copyright (c) Jam Digital Ltd. 2009 Some Rights Reserved.
* Creative Commons Attribution-Share Alike New Zealand.
*
* Visit http://creativecommons.org/licenses/by-sa/3.0/nz/ for details.
*
*/

if (!defined('EXT')) exit('Invalid file request');

if (!defined('JAM_IFRAME_TAB_VERSION'))
{
	define("JAM_IFRAME_TAB_VERSION", "0.1");
}

require_once(PATH_EXT . 'ext.jam_ext_settings.php');

class Jam_iframe_tab {

	//var $settings = array(); // UNUSED!
	var $jam_ext_settings = null;
	var $name = 'Jam IFrame Tab';
	var $version = JAM_IFRAME_TAB_VERSION;
	var $description = 'An example extension to show how to use settings with jam_ext_settings.';
	var $settings_exist = 'y';
	var $docs_url = 'http://jamdigital.co.nz/';


	function Jam_iframe_tab( $settings="" )
	{
		$this->__construct($settings);
	}

	function __construct( $settings="" )
	{
		$this->jam_ext_settings = new Jam_ext_settings(
			'Jam_iframe_tab'
			, $this->get_default_settings()
			);
	}

	function publish_form_new_tabs( $publish_tabs, $weblog_id, $entry_id, $hidden )
	{
		global $EXT, $LANG, $PREFS;

		$LANG->fetch_language_file('jam_iframe_tab');

		if($EXT->last_call !== FALSE)
		{
			$publish_tabs = $EXT->last_call;
		}

		$settings = $this->get_settings();
		$pages = $PREFS->ini('site_pages');

		if (!is_array($settings['weblogs']))
		{
			$valid_weblog = FALSE;
		}
		else
		{
			$valid_weblog = in_array($weblog_id, $settings['weblogs']);
		}

		if ($pages != FALSE && $valid_weblog != FALSE) {
			// something to show and valid on this page
			$publish_tabs['jam_iframe_tab'] = $LANG->line('Jam_iframe_tab');
		}

		return $publish_tabs;

	}

	function publish_form_new_tabs_block( $weblog_id )
	{
		global $DSP, $EXT, $LANG, $PREFS;
		
		$r = ($EXT->last_call !== FALSE) ? $EXT->last_call : '';

		$settings = $this->get_settings();
		$pages = $PREFS->ini('site_pages');

		if (!is_array($settings['weblogs']))
		{
			$valid_weblog = FALSE;
		}
		else
		{
			$valid_weblog = in_array($weblog_id, $settings['weblogs']);
		}

		if ($pages == FALSE OR $valid_weblog == FALSE) {
			// nothing to show or not valid on this page
			return $r;
		}

		$LANG->fetch_language_file('jam_iframe_tab');

		$r .= "<div id='blockjam_iframe_tab' style='display:none'>";
		$r .= NL.$DSP->div('publishTabWrapper');
		$r .= NL.$DSP->div('publishBox');
		$r .= NL.$DSP->div('publishInnerPad');

		$r .= NL.$LANG->line('Jam_iframe_tab');

		$r .= $this->_new_tabs_block_content($weblog_id);

		$r .= NL.$DSP->div_c();
		$r .= NL.$DSP->div_c();
		$r .= NL.$DSP->div_c();
		$r .= NL."</div>";
		return $r;
	}

	function _new_tabs_block_content($weblog_id)
	{
		global $DB, $PREFS, $IN, $DSP, $LANG;

		$settings = $this->get_settings();
		$iframe_target = $settings['iframe_target'];

		$r = "";
		$r .= NL.'<iframe src ="' . $iframe_target . '" width="100%" height="300">';
		$r .= NL.'<p>Your browser does not support iframes.</p>';
		$r .= NL.'</iframe>';
		return $r;
	}
		
	function get_settings()
	{
		global $PREFS;
		$result = $this->jam_ext_settings->get_settings(
				'Jam_iframe_tab'
				, $PREFS->ini('site_id')
		);
		return $result;
	}


	function settings_form($ignored)
	{
		global $LANG, $DB, $PREFS, $SESS;
		$settings = $this->get_settings();

                $sql = "SELECT blog_name, weblog_id FROM exp_weblogs
                          WHERE site_id = '".$DB->escape_str($PREFS->ini('site_id'))."'
                          ORDER BY blog_name";
                $wquery = $DB->query($sql);
                $weblogs_array = array();

                foreach ($wquery->result as $weblog_row)
                {
                        $weblog_id = $weblog_row['weblog_id'];
                        $weblog_name = $weblog_row['blog_name'];
			$weblogs_array[$weblog_id] = $weblog_name;
		}

		$weblogs_optons = new Jam_ext_form_options(
			'weblogs',
			'multiselect',
			$weblogs_array
		);

		$this->jam_ext_settings->jam_settings_form(
			'Jam_iframe_tab',
			$LANG->line('Jam_iframe_tab'),
			$settings,
			array($weblogs_optons)
		);

	}

	function save_settings()
	{
		$this->jam_ext_settings->save_settings();
	}

	function get_default_settings()
	{
                //
                // Returns the default settings for this extension.
                // Defaults are common to all sites.
                //
		$default_settings = array(
			'weblogs'		=> '0'
			, 'iframe_target'	=> 'http://jamdigital.co.nz'
		);
		return $default_settings;
	}

	function activate_extension()
	{
		global $DB;

		$settings = $this->get_default_settings();

		$hooks = array(
			'publish_form_new_tabs'				=> 'publish_form_new_tabs'
			, 'publish_form_new_tabs_block'			=> 'publish_form_new_tabs_block'
		);

		foreach ($hooks as $hook => $method)
		{
			$sql[] = $DB->insert_string( 'exp_extensions', 
				array('extension_id' 		=> '',
					'class'			=> get_class($this),
					'method'		=> $method,
					'hook'			=> $hook,
					'settings'		=> addslashes(serialize($settings)),
					'priority'		=> 10,
					'version'		=> $this->version,
					'enabled'		=> "y"
				)
			);
		}

		// run all sql queries
		foreach ($sql as $query)
		{
			$DB->query($query);
		}

		return TRUE;
	}

	function update_extension()
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}

		$DB->query("UPDATE exp_extensions 
			SET version = '".$DB->escape_str($this->version)."' 
			WHERE class = 'Example_extension'");
	}

	function disable_extension()
	{
		global $DB;
		$DB->query("DELETE FROM exp_extensions WHERE class = '" . get_class($this) . "'");
	}

}
?>
