<?php
/**
 * Plugin Name: JetEngine Forms - attach media to post
 * Plugin URI:
 * Description: Allow to attach media to parent post on insert post. Also supports JetFormBuilder.
 * Version:     1.1.0
 * Author:      Crocoblock
 * Author URI:  https://crocoblock.com/
 * Text Domain: jet-forms-attach-media-to-post
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
use Jet_Form_Builder\Actions\Action_Handler;

if ( ! defined( 'WPINC' ) ) {
	die();
}

add_action( 'jet-engine/forms/booking/notification/insert_post', 'jet_forms_attach_media_to_post', 20, 2 );

add_action( 'jet-form-builder/action/after-post-insert', 'jet_forms_attach_media_to_post_jfb', 20, 2 );

function _jet_forms_attach_images( $request_source, $media_keys, $inserted_post_id ) {
	$get_attachment_image_data_array = function( $img_data = null, $include = 'all' ) {
		$result = false;

		if ( empty( $img_data ) ) {
			return $result;
		}

		if ( is_numeric( $img_data ) ) {

			switch ( $include ) {
				case 'id':
					$result = array(
						'id' => $img_data,
					);
					break;

				case 'url':
					$result = array(
						'url' => wp_get_attachment_url( $img_data ),
					);
					break;

				default:
					$result = array(
						'id'  => $img_data,
						'url' => wp_get_attachment_url( $img_data ),
					);
			}

		} elseif ( filter_var( $img_data, FILTER_VALIDATE_URL ) ) {

			switch ( $include ) {
				case 'id':
					$result = array(
						'id' => attachment_url_to_postid( $img_data ),
					);
					break;

				case 'url':
					$result = array(
						'url' => $img_data,
					);
					break;

				default:
					$result = array(
						'id'  => attachment_url_to_postid( $img_data ),
						'url' => $img_data,
					);
			}

		} elseif ( is_array( $img_data ) && isset( $img_data['id'] ) && isset( $img_data['url'] ) ) {

			switch ( $include ) {
				case 'id':
					$result = array(
						'id' => $img_data['id'],
					);
					break;

				case 'url':
					$result = array(
						'url' => $img_data['url'],
					);
					break;

				default:
					$result = $img_data;
			}

		}

		return $result;
	};

	foreach ( $media_keys as $media_key ) {

		if ( empty( $request_source[ $media_key ] ) ) {
			continue;
		}

		$attachment_data = $request_source[ $media_key ];

		if ( ! is_array( $attachment_data ) ) {
			$attachment_data = explode( ',', $request_source[ $media_key ] );
		}

		$attachment_data = isset( $attachment_data['id'] ) ? array( $attachment_data ) : $attachment_data;

		$attachment_ids = array_map( function ( $item ) use ( $get_attachment_image_data_array ) {
			return call_user_func( $get_attachment_image_data_array, $item, 'id' );
		}, $attachment_data );
		
		$attachment_ids = wp_list_pluck( $attachment_ids, 'id' );

		foreach ( $attachment_ids as $attachment_id ) {
			wp_update_post( array(
				'ID'          => $attachment_id,
				'post_parent' => $inserted_post_id,
			) );
		}
	}
};

/**
 * @param $action
 * @param Action_Handler $handler
 */
function jet_forms_attach_media_to_post_jfb( $action, $handler ) {

	$inserted_id = $handler->get_inserted_post_id();

	if ( empty( $inserted_id ) ) {
		return;
	}
	$field_settings = wp_list_pluck( jet_form_builder()->form_handler->request_handler->_fields, 'attrs' );
	$media_keys = array();

	foreach ( $field_settings as $field_setting ) {
		if ( isset( $field_setting['insert_attachment'] )
		     && isset( $field_setting['name'] )
		     && filter_var( $field_setting['insert_attachment'], FILTER_VALIDATE_BOOLEAN )
		) {
			$media_keys[] = $field_setting['name'];
		}
	}

	if ( empty( $media_keys ) ) {
		return;
	}

	call_user_func( '_jet_forms_attach_images', $handler->request_data, $media_keys, $inserted_id );
}

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

	call_user_func( '_jet_forms_attach_images', $manager->data, $media_keys, $manager->data['inserted_post_id'] );
}
