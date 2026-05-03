<?php
/**
 * Validation layer for custom abilities.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Validation;

defined( 'ABSPATH' ) || exit;

/**
 * Validates custom ability data before persistence.
 *
 * Provides comprehensive validation for all custom ability fields including
 * slug format, label requirements, schema validity, and callback resolution.
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Ability_Validator {

	/**
	 * Valid ability statuses.
	 *
	 * @var array<string>
	 */
	private static array $valid_statuses = array( 'active', 'draft', 'archived' );

	/**
	 * Validates a complete custom ability object.
	 *
	 * @param array<string, mixed> $data Ability data to validate.
	 * @return array<string, mixed> {
	 *     Validation result.
	 *
	 *     @type bool   $valid  True if all validations passed.
	 *     @type array  $errors Array of error messages (empty if valid).
	 * }
	 */
	public static function validate_ability( array $data ): array {
		$errors = array();

		// Validate slug (required).
		if ( ! isset( $data['ability_slug'] ) ) {
			$errors[] = 'Ability slug is required.';
		} else {
			$slug_error = self::validate_slug( (string) $data['ability_slug'] );
			if ( true !== $slug_error ) {
				$errors[] = $slug_error;
			}
		}

		// Validate label (required).
		if ( ! isset( $data['label'] ) ) {
			$errors[] = 'Label is required.';
		} else {
			$label_error = self::validate_label( (string) $data['label'] );
			if ( true !== $label_error ) {
				$errors[] = $label_error;
			}
		}

		// Validate input_schema (optional but if provided, must be valid).
		if ( isset( $data['input_schema'] ) && null !== $data['input_schema'] ) {
			$input_error = self::validate_input_schema( $data['input_schema'] );
			if ( true !== $input_error ) {
				$errors[] = $input_error;
			}
		}

		// Validate output_schema (optional but if provided, must be valid).
		if ( isset( $data['output_schema'] ) && null !== $data['output_schema'] ) {
			$output_error = self::validate_output_schema( $data['output_schema'] );
			if ( true !== $output_error ) {
				$errors[] = $output_error;
			}
		}

		// Validate callbacks (optional but if provided, must be resolvable).
		$execute_callback    = $data['execute_callback'] ?? null;
		$permission_callback = $data['permission_callback'] ?? null;
		$callbacks_error     = self::validate_callbacks( $execute_callback, $permission_callback );
		if ( true !== $callbacks_error ) {
			$errors[] = $callbacks_error;
		}

		// Validate status (optional but if provided, must be valid).
		if ( isset( $data['status'] ) && null !== $data['status'] ) {
			$status_error = self::validate_status( (string) $data['status'] );
			if ( true !== $status_error ) {
				$errors[] = $status_error;
			}
		}

		// Validate category (optional).
		if ( isset( $data['category'] ) && null !== $data['category'] && '' !== $data['category'] ) {
			$category_error = self::validate_category( (string) $data['category'] );
			if ( true !== $category_error ) {
				$errors[] = $category_error;
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validates ability slug format.
	 *
	 * Valid format: alphanumeric, dashes, and forward slashes.
	 * Example: 'my-site/custom-processor'
	 *
	 * @param string $slug Slug to validate.
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_slug( string $slug ): bool|string {
		if ( empty( $slug ) ) {
			return 'Ability slug cannot be empty.';
		}

		// Allow alphanumeric, dashes, and forward slashes.
		if ( ! preg_match( '/^[a-z0-9\-\/]+$/i', $slug ) ) {
			return 'Ability slug must contain only alphanumeric characters, dashes, and forward slashes.';
		}

		// Minimum length check.
		if ( strlen( $slug ) < 3 ) {
			return 'Ability slug must be at least 3 characters long.';
		}

		// Maximum length check (database column limit).
		if ( strlen( $slug ) > 255 ) {
			return 'Ability slug must not exceed 255 characters.';
		}

		return true;
	}

	/**
	 * Validates ability label.
	 *
	 * Label must be a non-empty string.
	 *
	 * @param string $label Label to validate.
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_label( string $label ): bool|string {
		if ( empty( trim( $label ) ) ) {
			return 'Label cannot be empty.';
		}

		if ( strlen( $label ) > 255 ) {
			return 'Label must not exceed 255 characters.';
		}

		return true;
	}

	/**
	 * Validates input schema format.
	 *
	 * Must be valid JSON Schema (string that decodes to object/array).
	 *
	 * @param mixed $schema Schema to validate (can be string or array).
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_input_schema( mixed $schema ): bool|string {
		return self::validate_json_schema( $schema, 'Input schema' );
	}

	/**
	 * Validates output schema format.
	 *
	 * Must be valid JSON Schema (string that decodes to object/array).
	 *
	 * @param mixed $schema Schema to validate (can be string or array).
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_output_schema( mixed $schema ): bool|string {
		return self::validate_json_schema( $schema, 'Output schema' );
	}

	/**
	 * Validates a JSON schema structure.
	 *
	 * Checks that the schema is a valid JSON string or an array/object that can be JSON encoded.
	 *
	 * @param mixed  $schema Schema to validate.
	 * @param string $name   Schema name for error messages.
	 * @return true|string True on success, error message on failure.
	 */
	private static function validate_json_schema( mixed $schema, string $name ): bool|string {
		// If it's a string, try to decode it.
		if ( is_string( $schema ) ) {
			$decoded = json_decode( $schema, true );
			if ( null === $decoded && 'null' !== strtolower( $schema ) ) {
				return "{$name} must be valid JSON.";
			}

			// Decoded successfully, check if it's an array or object.
			if ( ! is_array( $decoded ) && ! is_object( $decoded ) ) {
				return "{$name} must be a JSON object or array.";
			}

			return true;
		}

		// If it's an array, it's valid.
		if ( is_array( $schema ) ) {
			return true;
		}

		// If it's an object, it's valid.
		if ( is_object( $schema ) ) {
			return true;
		}

		// Otherwise, it's not valid.
		return "{$name} must be a JSON string, array, or object.";
	}

	/**
	 * Validates callback functions.
	 *
	 * Each callback, if provided, must be a valid function name or static method.
	 * Static methods should be in the format 'ClassName::method_name'.
	 *
	 * @param mixed $execute_callback    Execute callback to validate (optional).
	 * @param mixed $permission_callback Permission callback to validate (optional).
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_callbacks( mixed $execute_callback = null, mixed $permission_callback = null ): bool|string {
		if ( null !== $execute_callback && '' !== $execute_callback ) {
			$execute_callback_str = (string) $execute_callback;
			if ( ! self::is_resolvable_callback( $execute_callback_str ) ) {
				return "Execute callback '{$execute_callback_str}' is not resolvable. Use a valid function name or 'ClassName::method_name'.";
			}
		}

		if ( null !== $permission_callback && '' !== $permission_callback ) {
			$permission_callback_str = (string) $permission_callback;
			if ( ! self::is_resolvable_callback( $permission_callback_str ) ) {
				return "Permission callback '{$permission_callback_str}' is not resolvable. Use a valid function name or 'ClassName::method_name'.";
			}
		}

		return true;
	}

	/**
	 * Validates status value.
	 *
	 * Status must be one of: active, draft, archived.
	 *
	 * @param string $status Status to validate.
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_status( string $status ): bool|string {
		if ( empty( trim( $status ) ) ) {
			return 'Status cannot be empty.';
		}

		if ( ! in_array( $status, self::$valid_statuses, true ) ) {
			$valid_list = implode( ', ', self::$valid_statuses );
			return "Status must be one of: {$valid_list}.";
		}

		return true;
	}

	/**
	 * Validates category value.
	 *
	 * Category must be a valid string if provided.
	 *
	 * @param string $category Category to validate.
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_category( string $category ): bool|string {
		if ( strlen( $category ) > 255 ) {
			return 'Category must not exceed 255 characters.';
		}

		return true;
	}

	/**
	 * Checks if a callback string is resolvable.
	 *
	 * Supports:
	 * - Function names: 'my_function'
	 * - Static methods: 'ClassName::method_name'
	 *
	 * @param string $callback Callback string to check.
	 * @return bool True if resolvable, false otherwise.
	 */
	public static function is_resolvable_callback( string $callback ): bool {
		if ( empty( $callback ) ) {
			return false;
		}

		// Check if it's a static method call (ClassName::method).
		if ( strpos( $callback, '::' ) !== false ) {
			$parts = explode( '::', $callback, 2 );

			if ( 2 !== count( $parts ) ) {
				return false;
			}

			$class_name  = $parts[0];
			$method_name = $parts[1];

			// Both class name and method name must be non-empty.
			if ( empty( $class_name ) || empty( $method_name ) ) {
				return false;
			}

			// Check if class exists and method exists.
			if ( ! class_exists( $class_name ) ) {
				return false;
			}

			if ( ! method_exists( $class_name, $method_name ) ) {
				return false;
			}

			return true;
		}

		// Otherwise, check if it's a function.
		return function_exists( $callback );
	}
}
