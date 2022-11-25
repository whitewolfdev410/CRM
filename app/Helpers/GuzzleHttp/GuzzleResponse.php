<?php

namespace App\Helpers\GuzzleHttp;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle HTTP response object
 */
class GuzzleResponse extends Response
{
    private $effectiveUri;

    /**
     * Create from PSR response instance
     * @param  ResponseInterface $response
     * @return GuzzleResponse
     */
    public static function fromPsrResponse(ResponseInterface $response)
    {
        return new static(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody(),
            $response->getProtocolVersion(),
            $response->getReasonPhrase()
        );
    }

    /**
     * Get parsed JSON body
     * @param  array  $config
     * @return mixed
     */
    public function json(array $config = [])
    {
        return json_decode(
            (string) $this->getBody(),
            isset($config['object']) ? !$config['object'] : true,
            512,
            isset($config['big_int_strings']) ? JSON_BIGINT_AS_STRING : 0
        );
    }

    /**
     * Get parsed XML body
     * @param  array  $config
     * @return mixed
     */
    public function xml(array $config = [])
    {
        $disableEntities = libxml_disable_entity_loader(true);
        $internalErrors = libxml_use_internal_errors(true);

        try {
            // Allow XML to be retrieved even if there is no response body
            $xml = new \SimpleXMLElement(
                (string) $this->getBody() ?: '<root />',
                isset($config['libxml_options']) ? $config['libxml_options'] : LIBXML_NONET,
                false,
                isset($config['ns']) ? $config['ns'] : '',
                isset($config['ns_is_prefix']) ? $config['ns_is_prefix'] : false
            );
            libxml_disable_entity_loader($disableEntities);
            libxml_use_internal_errors($internalErrors);
        } catch (\Exception $e) {
            libxml_disable_entity_loader($disableEntities);
            libxml_use_internal_errors($internalErrors);

            throw $e;
        }

        return $xml;
    }

    /**
     * Get effective URI
     * @return string
     */
    public function getEffectiveUri()
    {
        return $this->effectiveUri;
    }

    /**
     * Set effective URI
     * @param string $uri
     * @return void
     */
    public function setEffectiveUri($uri)
    {
        $this->effectiveUri = $uri;
    }

    /**
     * Cast to string
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getBody();
    }
}
