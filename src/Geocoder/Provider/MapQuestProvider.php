<?php

/**
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider;

use Geocoder\Exception\InvalidCredentialsException;
use Geocoder\HttpAdapter\HttpAdapterInterface;
use Geocoder\Exception\NoResultException;
use Geocoder\Exception\UnsupportedException;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class MapQuestProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * @var string
     */
    const GEOCODE_ENDPOINT_URL = 'http://open.mapquestapi.com/geocoding/v1/address?location=%s&outFormat=json&maxResults=%d&key=%s&thumbMaps=false';

    /**
     * @var string
     */
    const REVERSE_ENDPOINT_URL = 'http://open.mapquestapi.com/geocoding/v1/reverse?key=%s&lat=%F&lng=%F';

    /**
     * @var string
     */
    private $apiKey = null;

    /**
     * @param HttpAdapterInterface $adapter An HTTP adapter.
     * @param string               $apiKey  An API key.
     * @param string               $locale  A locale (optional).
     */
    public function __construct(HttpAdapterInterface $adapter, $apiKey, $locale = null)
    {
        parent::__construct($adapter, $locale);

        $this->apiKey = $apiKey;
    }

    /**
     * {@inheritDoc}
     */
    public function getGeocodedData($address)
    {
        // This API doesn't handle IPs
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedException('The MapQuestProvider does not support IP addresses.');
        }

        if (null === $this->apiKey) {
            throw new InvalidCredentialsException('No API Key provided.');
        }

        $query = sprintf(self::GEOCODE_ENDPOINT_URL, urlencode($address), $this->getMaxResults(), $this->apiKey);

        return $this->executeQuery($query);
    }

    /**
     * {@inheritDoc}
     */
    public function getReversedData(array $coordinates)
    {
        if (null === $this->apiKey) {
            throw new InvalidCredentialsException('No API Key provided.');
        }

        $query = sprintf(self::REVERSE_ENDPOINT_URL, $this->apiKey, $coordinates[0], $coordinates[1]);

        return $this->executeQuery($query);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'map_quest';
    }

    /**
     * @param string $query
     *
     * @return array
     */
    protected function executeQuery($query)
    {
        $content = $this->getAdapter()->getContent($query);

        if (null === $content) {
            throw new NoResultException(sprintf('Could not execute query: %s', $query));
        }

        $json = json_decode($content, true);

        if (!isset($json['results']) || empty($json['results'])) {
            throw new NoResultException(sprintf('Could not find results for given query: %s', $query));
        }

        $locations = $json['results'][0]['locations'];

        if (empty($locations)) {
            throw new NoResultException(sprintf('Could not find results for given query: %s', $query));
        }

        $results = array();

        foreach ($locations as $location) {
            $results[] = array_merge($this->getDefaults(), array(
                'latitude'      => $location['latLng']['lat'],
                'longitude'     => $location['latLng']['lng'],
                'streetName'    => $location['street'] ?: null,
                'city'          => $location['adminArea5'] ?: null,
                'zipcode'       => $location['postalCode'] ?: null,
                'county'        => $location['adminArea4'] ?: null,
                'region'        => $location['adminArea3'] ?: null,
                'country'       => $location['adminArea1'] ?: null,
            ));
        }

        return $results;
    }
}
