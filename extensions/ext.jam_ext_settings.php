<?php
/**
* Author: Mick Buckley.
* Copyright (c) Jam Digital Ltd. 2009 Some Rights Reserved.
* Creative Commons Attribution-Share Alike New Zealand.
*
* Visit http://creativecommons.org/licenses/by-sa/3.0/nz/ for details.
*
*/

if (!defined('EXT')) exit('Invalid file request');

if (!defined('JAM_EXT_SETTINGS_VERSION'))
{
        define("JAM_EXT_SETTINGS_VERSION", "0.1");
}


class Jam_ext_settings {

        //var $settings = array(); // UNUSED!
        var $name = 'Jam Ext Settings';
        var $version = JAM_EXT_SETTINGS_VERSION;
        var $description = 'Offers an API and storage for extension settings.  Works with Multiple Site Manager.';
        var $settings_exist = 'y';
	var $docs_url = 'http://jamdigital.co.nz/';

	function Jam_ext_settings($extension_name = NULL, $settings = NULL)
	{
                $this->__construct($extension_name, $settings);
        }

        function __construct($extension_name = NULL, $settings = NULL)
        {
                global $SESS;
                if (isset($SESS->cache['Jam_ext_settings']) === FALSE)
                {
                        $SESS->cache['Jam_ext_settings'] = array();
                }

		if ($extension_name && $settings)
		{
			$this->set_default_settings($extension_name, $settings);
		}
        }

	function set_default_settings($extension_name, $default_settings)
	{
		$SESS->cache['Jam_ext_settings'][$extension_name]['default'] = $default_settings;
	}

	function get_settings($extension_name, $site_id, $force_refresh = FALSE)
	{
		global $DB, $REGX, $SESS;
		$settings = NULL;
                if(isset($SESS->cache['Jam_ext_settings'][$extension_name][$site_id]['settings']) === FALSE
			 OR $force_refresh === TRUE)
                {
                        // check the db for extension settings
                        $sql = "SELECT settings FROM exp_jam_ext_settings "
				. " WHERE extension_name = '" . $DB->escape_str($extension_name) . "'"
				. " AND site_id = " . intval($site_id)
				;
                        $query = $DB->query($sql);

                        // if there is a row and the row has settings
                        if ($query->num_rows > 0 && $query->row['settings'] != '')
                        {
                                // save them to the cache
                                $SESS->cache['Jam_ext_settings'][$extension_name][$site_id]['settings'] = $REGX->array_stripslashes(unserialize($query->row['settings']));
                        }
                }

		if (empty($SESS->cache['Jam_ext_settings'][$extension_name][$site_id]['settings']) !== TRUE)
		{
			$settings = $SESS->cache['Jam_ext_settings'][$extension_name][$site_id]['settings'];
		}
		else
		{
			$settings = $this->_default_settings($extension_name);
		}
		return $settings;
	}

	function get_default_settings()
	{
		//
		// Returns the default settings for THIS extension.
		//
		$default_settings = array(
                        'setting_one' => '123'
                );
                return $default_settings;
	}

	function _default_settings($extension_name)
	{
		//
		// Internal method used to read default settings for a given
		// extension from
		// the cache or from the exp_extensions table (i.e. the default settings)
		//
		global $DB, $REGX;
		$result = array();
		if (empty($SESS->cache['Jam_ext_settings'][$extension_name]['default']) !== TRUE)
		{
			$result = $SESS->cache['Jam_ext_settings'][$extension_name]['default'];
		}
		else
		{
			$sql = "SELECT settings "
				. " FROM exp_extensions "
				. " WHERE enabled = 'y' "
				. " AND class = '" . $DB->escape_str($extension_name) . "' LIMIT 1"
				;
			$query = $DB->query($sql);

			if ($query->num_rows > 0 && $query->row['settings'] != '')
                        {
                                $result = $REGX->array_stripslashes(unserialize($query->row['settings']));
				$SESS->cache['Jam_ext_settings'][$extension_name]['default'] = $result;
			}
		}
		return $result;
	}

	function save_settings()
	{
		global $DB, $REGX, $PREFS;
		$extension_name = $REGX->xss_clean($_POST['name']);
		$site_id = $PREFS->ini('site_id');

		$default_settings = $this->_default_settings($extension_name);

		//
		// For multiselect options the $_POST object holds unwanted
		// elements which need to be removed.
		//
		foreach ($default_settings as $setting => $default)
		{
			if (isset($_POST[$setting]) && is_array($_POST[$setting]))
			{
				// this was a multi-select setting
				foreach ($_POST[$setting] as $key => $value)
				{
					unset($_POST[$setting . '_' . $key]);
				}
			}
		}

                $_POST = $REGX->xss_clean(array_merge($default_settings, $_POST));
		unset($_POST['name']);
		$settings = $_POST;

		$sql = "SELECT settings FROM exp_jam_ext_settings "
			. " WHERE site_id = " . intval($site_id)
			. " AND extension_name = '" . $DB->escape_str($extension_name) . "'"
			;
		$query = $DB->query($sql);

		if ($query->num_rows > 0)
		{
			$sql = "UPDATE exp_jam_ext_settings "
				. " SET settings = '" . addslashes(serialize($settings)) . "'"
				. " WHERE site_id = " . intval($site_id)
				. " AND extension_name = '" . $DB->escape_str($extension_name) . "'"
				;
			
		}
		else
		{
			$sql = "INSERT INTO exp_jam_ext_settings ( "
				. " `extension_name` "
				. " , `site_id` "
				. " , `settings` "
				. " ) values ( "
				. " '" . $DB->escape_str($extension_name) . "'"
				. " ," . intval($site_id)
				. " , '" . addslashes(serialize($settings)) . "'"
				. " ) "
				;
		}
                $query = $DB->query($sql);

	}

	function merge_default_settings($extension_name)
        {
		global $DB, $REGX;
		//
		// Make sure that stored settings contain all the setting
		// fields known in the default settings for this extension.
		// Used to update stored settings when new settings are added to
		// defualts, e.g. when upgrading an extension.
		//
		$default_settings = $this->_default_settings($extension_name);

		$sql = "SELECT setting_id, settings "
			. " FROM exp_jam_ext_settings "
			. " WHERE extension_name = '" . $DB->escape_str($extension_name) . "'"
			;
		$query = $DB->query($sql);

		$merged_settings = array();
		if ($query->num_rows > 0) {
			foreach ($query->result as $row) {
				$settings = $REGX->array_stripslashes(unserialize($query->row['settings']));
			}
//echo "<p>setting merged_settings" . $row['setting_id'] . "</p>\n";
			$merged_settings[$row['setting_id']] = array_merge($settings, $default_settings);
		}

		foreach ($merged_settings as $setting_id => $settings) {
//echo "<p>updating merged_settings" . $setting_id . "</p>\n";
			$sql = "UPDATE exp_jam_ext_settings "
				. " SET settings = '" . addslashes(serialize($settings)) . "'"
				. " WHERE setting_id = " . intval($setting_id)
				;
//echo $sql;
			$DB->query($sql);
		}
	}

        function activate_extension()
        {
                global $DB;

		$settings = $this->get_default_settings();

                // Delete old hooks
                $DB->query("DELETE FROM exp_extensions
                              WHERE class = 'Jam_ext_settings'");

                // Add new extensions
                $hook_tmpl = array(
                        'extension_id' => '',
                        'class'        => 'Jam_ext_settings',
                        'method'       => '',
                        'hook'         => '',
                        'settings'     => addslashes(serialize($settings)),
                        'priority'     => 10,
                        'version'      => JAM_EXT_SETTINGS_VERSION,
                        'enabled'      => 'y'
                );
		$DB->query($DB->insert_string('exp_extensions', $hook_tmpl));

                if ( ! $DB->table_exists('exp_jam_ext_settings'))
                {
                        $DB->query("CREATE TABLE IF NOT EXISTS exp_jam_ext_settings (
                                      `setting_id`     int(11) unsigned NOT NULL auto_increment,
                                      `extension_name` char(50) NOT NULL,
                                      `site_id`        int(11) NOT NULL,
                                      `settings`       text NOT NULL default '',
                                      PRIMARY KEY (`setting_id`)
                                    )");
                }
	}

	function update_extension($current='')
	{
		global $DB;

		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}

		$DB->query("UPDATE exp_extensions 
			SET version = '".$DB->escape_str($this->version)."' 
			WHERE class = 'Jam_ext_settings'");
	}

        function settings_form($ignored)
        {
                global $DSP, $LANG, $IN, $PREFS;

		$settings = $this->get_settings('Jam_ext_settings', $PREFS->ini('site_id'));

		$this->jam_settings_form(
			'Jam_ext_settings', 
			$LANG->line('Jam_ext_settings'), 
			$settings);
        }


	function jam_settings_form($extension_name, $extension_label, $settings, $options = null)
	{
		//
		// Given an associative array of settings
		// displays a settings editor form.
		// Fourth parameter is an array of Jam_ext_form_options objects.
		// If provided the options in the fourth parameter control
		// the method (text, select or multiselect) used to display
		// corresponding settings.
		//
                global $DSP, $LANG, $IN, $PREFS;
                $DSP->crumbline = TRUE;

                $DSP->title  = $LANG->line('extension_settings');
                $DSP->crumb  = $DSP->anchor(BASE.AMP.'C=admin'.AMP.'area=utilities', $LANG->line('utilities')).
                $DSP->crumb_item($DSP->anchor(BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=extensions_manager', $LANG->line('extensions_manager')));
                $DSP->crumb .= $DSP->crumb_item($extension_label);

                $DSP->right_crumb($LANG->line('disable_extension'), BASE.AMP.'C=admin'.AMP.'M=utilities'.AMP.'P=toggle_extension_confirm'.AMP.'which=disable'.AMP.'name='.$IN->GBL('name'));

                $DSP->body = $DSP->form_open(
                        array(
                                'action' => 'C=admin'.AMP.'M=utilities'.AMP.'P=save_extension_settings',
                                'name'   => 'settings_example',
                                'id'     => 'settings_example'
                            ),
                        array('name' => $extension_name)
                );


                $DSP->body .=   $DSP->table('tableBorder', '0', '', '100%');
                $DSP->body .=   $DSP->tr();
                $DSP->body .=   $DSP->td('tableHeadingAlt', '', '2');
                $DSP->body .=   $extension_label;
                $DSP->body .=   $DSP->td_c();
                $DSP->body .=   $DSP->tr_c();

                if (is_array($settings) && count($settings) > 0)
                {
			$i = 0;
                        foreach ($settings as $key => $value) {
				$cellStyle = ($i++ % 2) ? 'tableCellOne' : 'tableCellTwo';
                                $label = $LANG->line($key);
				if (!$label) {
					$label = $key;
				}

				$DSP->body .=   $DSP->tr();
				$DSP->body .=   $DSP->td($cellStyle, '45%');
				$DSP->body .=   $DSP->qdiv('defaultBold', $label);
				$DSP->body .=   $DSP->td_c();

				$DSP->body .=   $DSP->td($cellStyle);

				$option = $this->_find_option($key, $options);
				switch ($option->type) {
					case 'select':
						break;
					case 'multiselect':
						foreach ($option->options as $option_key => $option_value)
						{
							// this throws an error when settings haven't been set :(
							if (isset($settings[$option->name]) 
								&& is_array($settings[$option->name])
								&& in_array($option_key, $settings[$option->name]))
							{
								$checked = 'y';
							}
							else
							{
								$checked = '';
							}
							$DSP->body .= $DSP->input_checkbox(
								$option->name . '[]'
								, $option_key
								, $checked
							);

							$DSP->body .= $option_value;
							$DSP->body .= "<br />";
						}
						break;
					case 'text': /* Fall Through */
					default:
						$DSP->body .=   $DSP->input_text($key, $value);
						break;
				}

				$DSP->body .=   $DSP->td_c();
				$DSP->body .=   $DSP->tr_c();
			}
		}

                $DSP->body .=   $DSP->table_c();
                $DSP->body .=   $DSP->qdiv('itemWrapperTop', $DSP->input_submit());
                $DSP->body .=   $DSP->form_c();
        }

	function _find_option($key, $options_array)
	{
		if (is_array($options_array))
		{
			foreach ($options_array as $option)
			{
				if ($key == $option->name)
				{
					return $option;
				}
			}
		}
		return new Jam_ext_form_options($key, 'text', NULL);
	}

}

//
// Class to hold settings form options.
// Supports menu or multiselect
//
class Jam_ext_form_options {
	var $name;
	var $type;  // either 'menu' or 'multiselect'
	var $options; // array of options.  Keys are option values, values are option labels.

        function Jam_ext_form_options($name, $type, $options)
        {
                $this->__construct($name, $type, $options);
        }

        function __construct($name, $type, $options)
        {
		$this->name = $name;
		$this->type = $type;
		$this->options = $options;
	}

}
