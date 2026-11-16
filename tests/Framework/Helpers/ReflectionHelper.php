<?php
/**
 * ReflectionHelper.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Helpers;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * ReflectionHelper class.
 */
class ReflectionHelper {
	/**
	 * Reflection object.
	 *
	 * @var ReflectionClass
	 */
	private ReflectionClass $reflection;

	/**
	 * Reflected class instance.
	 *
	 * @var object
	 */
	private object $reflected_class;

	/**
	 * Constructor.
	 *
	 * @param object $class_instance
	 */
	public function __construct( object $class_instance ) {
		$this->reflected_class = $class_instance;
		$this->reflection      = new ReflectionClass( $class_instance );
	}

	/**
	 * Get private property and make it accessible.
	 *
	 * @param string $property_name
	 * @return ReflectionProperty
	 */
	public function get_private_property( string $property_name ): ReflectionProperty {
		$property = $this->reflection->getProperty( $property_name );
		$property->setAccessible( true );

		return $property;
	}

	/**
	 * Get private property value.
	 *
	 * @param string $property_name
	 * @return mixed
	 */
	public function get_private_property_value( string $property_name ): mixed {
		$property = $this->get_private_property( $property_name );

		return $property->getValue( $this->reflected_class );
	}

	/**
	 * Get private method and make it accessible.
	 *
	 * @param string $method_name Name of the private method.
	 * @return ReflectionMethod The accessible method.
	 */
	public function get_private_method( string $method_name ): ReflectionMethod {
		$method = $this->reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * Invoke a private method and return its value.
	 *
	 * @param string $method_name
	 * @param mixed ...$args
	 * @return mixed
	 */
	public function get_private_method_value( string $method_name, mixed ...$args ): mixed {
		$method = $this->get_private_method( $method_name );

		return $method->invoke( $this->reflected_class, ...$args );
	}

	/**
	 * Set private property value.
	 *
	 * @param string $property_name
	 * @param mixed $value
	 * @return void
	 */
	public function set_private_property_value( string $property_name, mixed $value ): void {
		$property = $this->get_private_property( $property_name );
		$property->setValue( $this->reflected_class, $value );
	}

	/**
	 * Set private method value.
	 *
	 * @param string $method_name
	 * @param mixed ...$args
	 * @return void
	 */
	public function set_private_method_value( string $method_name, mixed ...$args ): void {
		$method = $this->get_private_method( $method_name );
		$method->invoke( $this->reflected_class, ...$args );
	}
}
