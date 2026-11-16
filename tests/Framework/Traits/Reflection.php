<?php
/**
 * Reflection.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Reflection.
 */
trait Reflection {
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
	 * Initialize reflection for an object.
	 *
	 * @param object $test_class
	 * @return void
	 */
	private function initialize_reflection( object $test_class ): void {
		$this->reflection      = new ReflectionClass( $test_class );
		$this->reflected_class = $test_class;
	}

	/**
	 * Get private property and make it accessible.
	 *
	 * @param string $property_name
	 * @return ReflectionProperty
	 */
	protected function get_private_property( string $property_name ): ReflectionProperty {
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
	protected function get_private_property_value( string $property_name ): mixed {
		$property = $this->get_private_property( $property_name );

		return $property->getValue( $this->reflected_class );
	}

	/**
	 * Get private method and make it accessible.
	 *
	 * @param string $method_name Name of the private method.
	 * @return ReflectionMethod The accessible method.
	 */
	protected function get_private_method( string $method_name ): ReflectionMethod {
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
	protected function get_private_method_value( string $method_name, mixed ...$args ): mixed {
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
	protected function set_private_property_value( string $property_name, mixed $value ): void {
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
	protected function set_private_method_value( string $method_name, mixed ...$args ): void {
		$method = $this->get_private_method( $method_name );
		$method->invoke( $this->reflected_class, ...$args );
	}
}
