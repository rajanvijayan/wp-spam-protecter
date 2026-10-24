<?php
/**
 * Contact Form 7, WPForms, Gravity Forms, Fluent Forms and Elementor Pro forms.
 *
 * @package ShieldCraft
 */

namespace ShieldCraft\Integrations;

use ShieldCraft\Engine\Fields;

/**
 * Each plugin gets the hidden fields inside its forms and a check through its own spam or
 * validation hook, so a refused message shows the plugin's usual error. Every integration is
 * registered only when its plugin is active.
 */
final class Form_Plugins {

	/**
	 * Hooks.
	 */
	public static function register() {
		if ( defined( 'WPCF7_VERSION' ) && Form_Data::enabled( 'cf7' ) ) {
			add_filter( 'wpcf7_form_elements', array( self::class, 'cf7_fields' ) );
			add_filter( 'wpcf7_spam', array( self::class, 'cf7_check' ), 20, 2 );
		}
		if ( function_exists( 'wpforms' ) && Form_Data::enabled( 'wpforms' ) ) {
			add_action( 'wpforms_display_submit_before', array( self::class, 'wpforms_fields' ) );
			add_action( 'wpforms_process_before', array( self::class, 'wpforms_check' ), 20, 2 );
		}
		if ( class_exists( 'GFForms' ) && Form_Data::enabled( 'gravityforms' ) ) {
			add_filter( 'gform_submit_button', array( self::class, 'gravity_fields' ), 20, 2 );
			add_filter( 'gform_entry_is_spam', array( self::class, 'gravity_check' ), 20, 3 );
		}
		if ( defined( 'FLUENTFORM' ) && Form_Data::enabled( 'fluentforms' ) ) {
			add_action( 'fluentform/form_element_start', array( self::class, 'fluent_fields' ) );
			add_filter( 'fluentform/validation_errors', array( self::class, 'fluent_check' ), 20, 3 );
		}
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) && Form_Data::enabled( 'elementor' ) ) {
			add_filter( 'elementor/widget/render_content', array( self::class, 'elementor_fields' ), 20, 2 );
			add_action( 'elementor_pro/forms/validation', array( self::class, 'elementor_check' ), 20, 2 );
		}
	}

	/**
	 * Form plugins ShieldCraft supports, with whether each is active.
	 *
	 * @return array[] key => { label, active }
	 */
	public static function supported() {
		return array(
			'cf7'          => array(
				'label'  => 'Contact Form 7',
				'active' => defined( 'WPCF7_VERSION' ),
			),
			'wpforms'      => array(
				'label'  => 'WPForms',
				'active' => function_exists( 'wpforms' ),
			),
			'gravityforms' => array(
				'label'  => 'Gravity Forms',
				'active' => class_exists( 'GFForms' ),
			),
			'fluentforms'  => array(
				'label'  => 'Fluent Forms',
				'active' => defined( 'FLUENTFORM' ),
			),
			'elementor'    => array(
				'label'  => 'Elementor Pro Forms',
				'active' => defined( 'ELEMENTOR_PRO_VERSION' ),
			),
		);
	}

	/**
	 * Contact Form 7: fields at the end of the form.
	 *
	 * @param string $html Form elements.
	 * @return string
	 */
	public static function cf7_fields( $html ) {
		return $html . Fields::render( 'cf7' );
	}

	/**
	 * Contact Form 7: spam check (the reason shows in Flamingo and the spam log).
	 *
	 * @param bool   $spam       Spam so far.
	 * @param object $submission WPCF7_Submission.
	 * @return bool
	 */
	public static function cf7_check( $spam, $submission ) {
		if ( $spam || ! is_object( $submission ) || ! method_exists( $submission, 'get_posted_data' ) ) {
			return $spam;
		}
		$form    = method_exists( $submission, 'get_contact_form' ) && $submission->get_contact_form() ? $submission->get_contact_form()->id() : '';
		$message = Form_Data::check( 'cf7', $form, (array) $submission->get_posted_data() );
		if ( '' === $message ) {
			return $spam;
		}
		if ( method_exists( $submission, 'add_spam_log' ) ) {
			$submission->add_spam_log(
				array(
					'agent'  => 'shieldcraft',
					'reason' => __( 'ShieldCraft marked this message as spam.', 'wp-spam-protecter' ),
				)
			);
		}
		return true;
	}

	/**
	 * WPForms: fields before the submit button.
	 *
	 * @param array $form_data Form data.
	 */
	public static function wpforms_fields( $form_data ) {
		unset( $form_data );
		echo Fields::render( 'wpforms' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * WPForms: refuse spam with a form error.
	 *
	 * @param array $entry     Submitted entry.
	 * @param array $form_data Form data.
	 */
	public static function wpforms_check( $entry, $form_data ) {
		$form_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
		$values  = array();
		$fields  = isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ? $form_data['fields'] : array();
		foreach ( isset( $entry['fields'] ) && is_array( $entry['fields'] ) ? $entry['fields'] : array() as $id => $value ) {
			$label            = isset( $fields[ $id ]['label'] ) ? $fields[ $id ]['label'] : 'field_' . $id;
			$values[ $label ] = $value;
		}
		$message = Form_Data::check( 'wpforms', $form_id, $values );
		if ( '' === $message || ! function_exists( 'wpforms' ) ) {
			return;
		}
		$process = method_exists( wpforms(), 'obj' ) ? wpforms()->obj( 'process' ) : ( isset( wpforms()->process ) ? wpforms()->process : null );
		if ( $process ) {
			$process->errors[ $form_id ]['header'] = $message;
		}
	}

	/**
	 * Gravity Forms: fields next to the submit button.
	 *
	 * @param string $button Button markup.
	 * @param array  $form   Form.
	 * @return string
	 */
	public static function gravity_fields( $button, $form ) {
		unset( $form );
		return $button . Fields::render( 'gravityforms' );
	}

	/**
	 * Gravity Forms: mark the entry as spam (it is kept in the form's Spam list).
	 *
	 * @param bool  $is_spam Spam so far.
	 * @param array $form    Form.
	 * @param array $entry   Entry.
	 * @return bool
	 */
	public static function gravity_check( $is_spam, $form, $entry ) {
		if ( $is_spam ) {
			return $is_spam;
		}
		$values = array();
		foreach ( isset( $form['fields'] ) ? (array) $form['fields'] : array() as $field ) {
			$id    = is_object( $field ) ? $field->id : ( isset( $field['id'] ) ? $field['id'] : '' );
			$label = is_object( $field ) ? $field->label : ( isset( $field['label'] ) ? $field['label'] : '' );
			foreach ( (array) $entry as $key => $value ) {
				// Entry keys are the field ID, or "ID.N" for the parts of a name or address.
				if ( (string) $key === (string) $id || 0 === strpos( (string) $key, $id . '.' ) ) {
					$values[ $label . ' ' . $key ] = $value;
				}
			}
		}
		$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;
		$message = Form_Data::check( 'gravityforms', $form_id, $values );
		if ( '' === $message ) {
			return $is_spam;
		}
		if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'set_spam_filter' ) ) {
			\GFCommon::set_spam_filter( $form_id, 'ShieldCraft', __( 'Marked as spam by ShieldCraft.', 'wp-spam-protecter' ) );
		}
		return true;
	}

	/**
	 * Fluent Forms: fields at the start of the form.
	 *
	 * @param object $form Form.
	 */
	public static function fluent_fields( $form ) {
		unset( $form );
		echo Fields::render( 'fluentforms' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Fields::render().
	}

	/**
	 * Fluent Forms: refuse spam with a validation error. Fluent Forms sends the form serialised
	 * in one field, so the hidden fields are read from its parsed form data.
	 *
	 * @param array  $errors    Errors by field.
	 * @param array  $form_data Submitted data.
	 * @param object $form      Form.
	 * @return array
	 */
	public static function fluent_check( $errors, $form_data, $form ) {
		if ( ! empty( $errors ) ) {
			return $errors;
		}
		$form_data = (array) $form_data;
		$source    = $form_data;
		// Fluent Forms passes only its own fields here; the hidden ones are in the raw "data" field.
		if ( ! isset( $source[ Fields::TOKEN ] ) && isset( $_POST['data'] ) && is_string( $_POST['data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Fluent Forms verifies its own nonce.
			parse_str( wp_unslash( $_POST['data'] ), $source ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- values are sanitized in Fields::from_request().
		}
		$message = Form_Data::check( 'fluentforms', is_object( $form ) && isset( $form->id ) ? $form->id : '', $form_data, $source );
		if ( '' !== $message ) {
			$errors['shieldcraft'] = array( $message );
		}
		return $errors;
	}

	/**
	 * Elementor Pro: fields at the end of each form widget.
	 *
	 * @param string $content Widget markup.
	 * @param object $widget  Widget.
	 * @return string
	 */
	public static function elementor_fields( $content, $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'form' !== $widget->get_name() || false === strpos( $content, '</form>' ) ) {
			return $content;
		}
		$pos = strrpos( $content, '</form>' );
		return substr( $content, 0, $pos ) . Fields::render( 'elementor' ) . substr( $content, $pos );
	}

	/**
	 * Elementor Pro: refuse spam with a form error.
	 *
	 * @param object $record       Form record.
	 * @param object $ajax_handler Ajax handler.
	 */
	public static function elementor_check( $record, $ajax_handler ) {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return;
		}
		$values = array();
		foreach ( (array) $record->get( 'fields' ) as $id => $field ) {
			$label            = ! empty( $field['title'] ) ? $field['title'] : $id;
			$values[ $label ] = isset( $field['value'] ) ? $field['value'] : '';
		}
		$settings = (array) $record->get( 'form_settings' );
		$message  = Form_Data::check( 'elementor', isset( $settings['id'] ) ? $settings['id'] : '', $values );
		if ( '' !== $message && is_object( $ajax_handler ) && method_exists( $ajax_handler, 'add_error_message' ) ) {
			$ajax_handler->add_error_message( $message );
			if ( method_exists( $ajax_handler, 'add_error' ) ) {
				$ajax_handler->add_error( 'shieldcraft', $message );
			}
		}
	}
}
