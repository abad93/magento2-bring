<?php
namespace Markant\Bring\Model\Api;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Copyright (C) Markant Norge AS - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @author petterk
 * @date 5/23/16 3:05 PM
 */
class BookingClient
{


    const BRING_CUSTOMERS_API = 'https://api.bring.com/booking/api/customers.json';

    const BRING_BOOKING_API = 'https://api.bring.com/booking/api/booking';

    private $clientId;

    private $apiKey;

    private $clientUrl;

    private $_customers = array();


    public function __construct($clientUrl, $clienetId, $apiKey)
    {
        $this->clientId = $clienetId;
        $this->apiKey = $apiKey;
        $this->clientUrl = $clientUrl;

        if (!$this->clientId) {
            throw new \Exception("Mybring login ID must not be empty.");
        }
        if (!$this->apiKey) {
            throw new \Exception("Mybring login API KEY must not be empty.");
        }
        if (!$this->clientUrl) {
            throw new \Exception("Bring Client URL must not be empty.");
        }
    }


    private function request ($method, $endpoint, array $options = []) {
        $client = new Client();

        $options = array_merge($options, [
            'headers' => [
                'X-Bring-Client-URL' => $this->clientUrl,
                'Accept'     => 'application/json'
            ]
        ]);
        $options['headers']['X-MyBring-API-Uid'] = $this->clientId;;
        $options['headers']['X-MyBring-API-Key'] = $this->apiKey;

        return $client->request($method, $endpoint, $options);
    }

    public function getCustomers () {
        if ($this->_customers) return $this->_customers;

        $request = $this->request('get', self::BRING_CUSTOMERS_API);
        if ($request->getStatusCode() == 200) {
            $json = json_decode($request->getBody(), true);
            $this->_customers = $json['customers'];
            return $json['customers'];
        }
    }
    public function customersToOptionArray () {
        $option = [];
        foreach ($this->getCustomers() as $customer) {
            $option[] = ['value' => $customer['customerNumber'], 'label' => $customer['name']];
        }
        return $option;
    }

}