<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AAL_Hooks {
	
	public function __construct() {
		// Load abstract class.
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/abstract-class-aal-hook-base.php' );
		
		// TODO: Maybe I will use with glob() function for this.
		// Load all our hooks.
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-users.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-attachments.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-menus.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-options.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-plugins.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-posts.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-taxonomies.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-themes.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-widgets.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-core.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-export.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-comments.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-emails.php' );
		include( plugin_dir_path( ACTIVITY_LOG__FILE__ ) . '/hooks/class-aal-hook-file-editor.php' );

		new AAL_Hook_Users();
		new AAL_Hook_Attachments();
		new AAL_Hook_Menus();
		new AAL_Hook_Options();
		new AAL_Hook_Plugins();
		new AAL_Hook_Posts();
		new AAL_Hook_Taxonomies();
		new AAL_Hook_Themes();
		new AAL_Hook_Widgets();
		new AAL_Hook_Core();
		new AAL_Hook_Export();
		new AAL_Hook_Comments();
		new AAL_Hook_Emails();
		new AAL_Hook_File_Editor();
	}
}
