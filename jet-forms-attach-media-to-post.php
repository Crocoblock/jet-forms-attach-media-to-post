<?php
/**
 * Plugin Name: JetEngine Forms - attach media to post
 * Plugin URI:
 * Description: Allow to attach media to parent post on insert post.
 * Version:     1.0.0
 * Author:      Crocoblock
 * Author URI:  https://crocoblock.com/
 * Text Domain: jet-forms-attach-media-to-post
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die();
}

add_action( 'jet-engine/forms/booking/notification/insert_post', 'jet_forms_attach_media_to_post', 20, 2 );

function jet_forms_attach_media_to_post( $notification, $manager ) {

	if ( empty( $manager->data['inserted_post_id'] ) ) {
		return;
	}

	$form_id = absint( $manager->form );

	$form_data = get_post_meta( $form_id, '_form_data', true );
	$form_data = json_decode( wp_unslash( $form_data ), true );

	if ( empty( $form_data ) ) {
		return;
	}

	$field_settings = wp_list_pluck( $form_data, 'settings' );

	$media_keys = array();

	foreach ( $field_settings as $field_setting ) {
		if ( ! empty( $field_setting['type'] ) && 'media' === $field_setting['type']
		     && isset( $field_setting['insert_attachment'] )
		     && filter_var( $field_setting['insert_attachment'], FILTER_VALIDATE_BOOLEAN )
		) {
			$media_keys[] = $field_setting['name'];
		}
	}

	if ( empty( $media_keys ) ) {
		return;
	}

	foreach ( $media_keys as $media_key ) {

		if ( empty( $manager->data[ $media_key ] ) ) {
			continue;
		}

		$attachment_data = $manager->data[ $media_key ];

		if ( ! is_array( $attachment_data ) ) {
			$attachment_data = explode( ',', $manager->data[ $media_key ] );
		}

		$attachment_data = isset( $attachment_data['id'] ) ? array( $attachment_data ) : $attachment_data;

		$attachment_ids = array_map( function ( $item ) {
			return Jet_Engine_Tools::get_attachment_image_data_array( $item, 'id' );
		}, $attachment_data );

		$attachment_ids = wp_list_pluck( $attachment_ids, 'id' );

		foreach ( $attachment_ids as $attachment_id ) {
			wp_update_post( array(
				'ID'          => $attachment_id,
				'post_parent' => $manager->data['inserted_post_id'],
			) );
		}
	}
}
