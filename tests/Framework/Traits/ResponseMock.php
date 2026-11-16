<?php
/**
 * ResponseMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use Mockery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Exception\RequestException;
use stdClass;
use RuntimeException;

/**
 * ResponseMock.
 */
trait ResponseMock {
	/**
	 * Mock ResponseInterface.
	 *
	 * @param int $status_code
	 * @param string $reason_phrase
	 * @param stdClass $body_content
	 * @return ResponseInterface
	 */
	protected function mock_response( int $status_code, string $reason_phrase, stdClass $body_content ): ResponseInterface {
		// Mock StreamInterface.
		$stream = Mockery::mock( StreamInterface::class );
		$stream->shouldReceive( 'getContents' )->once()->andReturn( json_encode( $body_content ) );

		// Mock ResponseInterface.
		$response = Mockery::mock( ResponseInterface::class );
		$response->shouldReceive( 'getStatusCode' )->once()->andReturn( $status_code );
		$response->shouldReceive( 'getReasonPhrase' )->once()->andReturn( $reason_phrase );
		$response->shouldReceive( 'getBody' )->once()->andReturn( $stream );

		return $response;
	}

	/**
	 * Mock RequestException.
	 *
	 * @param int $status_code
	 * @param string $reason_phrase
	 * @param stdClass $body_content
	 * @return RequestException
	 */
	protected function mock_request_exception( int $status_code, string $reason_phrase, stdClass $body_content ): RequestException {
		// Mock StreamInterface.
		$stream = Mockery::mock( StreamInterface::class );
		$stream->shouldReceive( 'getContents' )->once()->andReturn( json_encode( $body_content ) );

		// Mock ResponseInterface.
		$response = Mockery::mock( ResponseInterface::class );
		$response->shouldReceive( 'getStatusCode' )->twice()->andReturn( $status_code );
		$response->shouldReceive( 'getReasonPhrase' )->once()->andReturn( $reason_phrase );
		$response->shouldReceive( 'getBody' )->once()->andReturn( $stream );

		$request = Mockery::mock( RequestInterface::class );

		return new RequestException(
			'Request failed',
			$request,
			$response
		);
	}

	/**
	 * Mock RequestException.
	 *
	 * @param int $status_code
	 * @param string $reason_phrase
	 * @return ResponseInterface
	 */
	protected function mock_runtime_exception( int $status_code, string $reason_phrase ): ResponseInterface {
		// Mock StreamInterface.
		$stream = Mockery::mock( StreamInterface::class );
		$stream->shouldReceive( 'getContents' )->twice()->andThrow( new RuntimeException( 'Failed to read stream' ) );

		// Mock ResponseInterface.
		$response = Mockery::mock( ResponseInterface::class );
		$response->shouldReceive( 'getStatusCode' )->twice()->andReturn( $status_code );
		$response->shouldReceive( 'getReasonPhrase' )->twice()->andReturn( $reason_phrase );
		$response->shouldReceive( 'getBody' )->twice()->andReturn( $stream );

		return $response;
	}
}
