<?php
function create_customer_support_specialist_role(): void {
	if ( ! get_role( 'customer_support_specialist' ) ) {
		add_role( 'customer_support_specialist', __( 'Customer Support Specialist', 'dovira' ), [
			'read'                           => true,
			'edit_conversations'             => true,
			'edit_others_conversations'      => true,
			'edit_published_conversations'   => true,
			'publish_conversations'          => true,
			'delete_conversations'           => true,
			'delete_others_conversations'    => true,
			'delete_published_conversations' => true,
			'delete_private_conversations'   => true,
			'edit_private_conversations'     => true,
			'read_private_conversations'     => true,
			'edit_conversation'              => true,
			'read_conversation'              => true,
			'delete_conversation'            => true,
		] );
	}
}

add_action( 'init', 'create_customer_support_specialist_role' );


function hide_admin_menu_for_customer_support(): void {
	if ( current_user_can( 'edit_conversations' ) && ! current_user_can( 'manage_options' ) ) {
		remove_menu_page( 'index.php' );
		remove_menu_page( 'edit.php' );
		remove_menu_page( 'upload.php' );
		remove_menu_page( 'edit.php?post_type=page' );
		remove_menu_page( 'edit.php?post_type=employee' );
		remove_menu_page( 'edit.php?post_type=service' );
		remove_menu_page( 'edit-comments.php' );
		remove_menu_page( 'themes.php' );
		remove_menu_page( 'plugins.php' );
		remove_menu_page( 'users.php' );
		remove_menu_page( 'tools.php' );
		remove_menu_page( 'options-general.php' );
		remove_submenu_page( 'options-general.php', 'options-general.php' );
		remove_submenu_page( 'options-general.php', 'options-writing.php' );
		remove_submenu_page( 'options-general.php', 'options-reading.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		remove_submenu_page( 'options-general.php', 'options-media.php' );
		remove_submenu_page( 'options-general.php', 'options-permalink.php' );
		remove_submenu_page( 'options-general.php', 'privacy.php' );
	}
}

add_action( 'admin_menu', 'hide_admin_menu_for_customer_support' );

function remove_admin_bar_items_for_customer_support(): void {
	if ( current_user_can( 'edit_conversations' ) && ! current_user_can( 'manage_options' ) ) {
		global $wp_admin_bar;
		$wp_admin_bar->remove_menu( 'wp-logo' );
		$wp_admin_bar->remove_menu( 'about' );
		$wp_admin_bar->remove_menu( 'wporg' );
		$wp_admin_bar->remove_menu( 'documentation' );
		$wp_admin_bar->remove_menu( 'support-forums' );
		$wp_admin_bar->remove_menu( 'feedback' );
		$wp_admin_bar->remove_menu( 'new-content' );
		$wp_admin_bar->remove_menu( 'comments' );
		$wp_admin_bar->remove_menu( 'appearance' );
		$wp_admin_bar->remove_menu( 'themes' );
		$wp_admin_bar->remove_menu( 'widgets' );
		$wp_admin_bar->remove_menu( 'menus' );
		$wp_admin_bar->remove_menu( 'customize' );
	}
}

add_action( 'wp_before_admin_bar_render', 'remove_admin_bar_items_for_customer_support', 999 );

function redirect_customer_support_after_login( string $redirect_to, string $request, WP_User $user ): string {
	if ( isset( $user->roles ) && is_array( $user->roles ) && in_array( 'customer_support_specialist', $user->roles ) ) {
		return admin_url( 'edit.php?post_type=conversation' );
	}

	return $redirect_to;
}

add_filter( 'login_redirect', 'redirect_customer_support_after_login', 10, 3 );
