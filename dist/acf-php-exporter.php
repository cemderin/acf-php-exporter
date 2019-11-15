<?php
	
	/**
	 * Plugin name: ACF PHP Exporter
	 * Plugin URI: https://github.com/hypress
	 * Description: PHP exporter for ACF field groups
	 * Version: 1.1.0
	 * Author: Cem Derin (wysiwyg software design GmbH)
	 * Author URI: https://github.com/wysiwyg-software-design/acf-php-exporter
	 */

/**
 * By default, the field groups will be exported to the current theme directory `acf-php/field-groups.php`. This can be
 * changed by define the constant `ACF_PHP_EXPORTER_EXPORT_PATH`. This must be an absolute path.
 */
if(!defined('ACF_PHP_EXPORTER_EXPORT_PATH')) {
    define(
        'ACF_PHP_EXPORTER_EXPORT_PATH',

        get_stylesheet_directory().DIRECTORY_SEPARATOR.
        'acf-php'. DIRECTORY_SEPARATOR.
        'field-groups.php'
    );
}

/**
 * Class ACF_PHP_Exporter
 */
class ACF_PHP_Exporter {
    /**
     * ACF_PHP_Exporter constructor.
     */
    public function __construct() {
        $this->_registerHooks();
    }

    /**
     * Registers all needed hooks
     */
    protected function _registerHooks() {
        add_action('acf/update_field_group', array($this, 'fieldGroupUpdated'));
        add_action('acf/init', array($this, 'registerPluginHandling'));
    }

    /**
     * Called, when a field group has been updated
     */
    public function fieldGroupUpdated() {
        $fieldGroupKeys = array();
        $acfFieldGroups = acf_get_field_groups();
        foreach ($acfFieldGroups as $acfFieldGroup) {
            $fieldGroupKeys[] = $acfFieldGroup['key'];
        }

        $json = array();

        foreach($fieldGroupKeys as $fieldGroupKey) {
            $fieldGroup = acf_get_field_group($fieldGroupKey);
            if (empty($fieldGroup)) continue;

            $fieldGroup['fields'] = acf_get_fields($fieldGroup);
            $fieldGroup['fields'] = acf_prepare_fields_for_export($fieldGroup['fields']);
            $json[] = $fieldGroup;
        }

        $args = array('field_groups' => $json);
        $fieldGroupKeys = acf_extract_var($args, 'field_groups');

        $exportOutput = "<?php" . "\r\n";
        $exportOutput .= 	'/**'.PHP_EOL.
            ' * This file has been generated by the ACF PHP Exporter plugin @ '. date('Y.m.d, H:i:s').PHP_EOL.
            ' * For more information check https://github.com/wysiwyg-software-design/acf-php-exporter'.PHP_EOL.
            ' **/'.PHP_EOL.
            PHP_EOL;
        $exportOutput .= "if( function_exists('acf_add_local_field_group') ):";
        $exportOutput .= "\r\n" . "\r\n";

        foreach($fieldGroupKeys as $fieldGroupKey) {
            $code = var_export($fieldGroupKey, true);
            $code = str_replace("  ", "\t", $code);
            $replace = array(
                '/([\t\r\n]+?)array/' => 'array',
                '/[0-9]+ => array/' => 'array'
            );

            if ($domain = acf_get_setting('export_textdomain')) {
                $replace["/'title' => (.+),/"] = "'title' => __($1, '$domain'),";
                $replace["/'label' => (.+),/"] = "'label' => __($1, '$domain'),";
                $replace["/'instructions' => (.+),/"] = "'instructions' => __($1, '$domain'),";
            }

            $code = preg_replace(array_keys($replace), array_values($replace), $code);
            $exportOutput .= "acf_add_local_field_group({$code});";
            $exportOutput .= "\r\n" . "\r\n";
        }

        $exportOutput .= "endif;";

        $dirName = dirname(ACF_PHP_EXPORTER_EXPORT_PATH);
        if(!file_exists($dirName)) mkdir($dirName);
        file_put_contents(ACF_PHP_EXPORTER_EXPORT_PATH, $exportOutput);
    }

    /**
     * Hides the backend menu for ACF and loads the php fields
     */
    public function registerPluginHandling() {
        if(defined('WP_DEBUG') && WP_DEBUG) {

        } else {
            add_filter('acf/settings/show_admin', '__return_false');
            if(file_exists(ACF_PHP_EXPORTER_EXPORT_PATH)) include ACF_PHP_EXPORTER_EXPORT_PATH;
        }
    }
}

/**
 * Load and run the plugin
 */
$plugin = new ACF_PHP_Exporter();
