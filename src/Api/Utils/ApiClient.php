<?php
/*
 * ApiClient.php
 * @author Martin Appelmann <hello@martin-appelmann.de>
 * @copyright 2021 Martin Appelmann
 */

namespace Exlo89\LaravelSevdeskApi\Api\Utils;

use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exlo89\LaravelSevdeskApi\Api\Invoice;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Client;
use Exception;

class ApiClient
{
	private $client;

	private function getClient(): Client
	{
		if (!$this->client) {
			$this->client = new Client([
				'base_uri' => $this->baseUrl(),
			]);
		}
		return $this->client;
	}

	private function getToken(): string
	{
		return config('sevdesk-api.api_token');
	}

	private function baseUrl()
	{
		return 'https://my.sevdesk.de';
	}

	public function execute($httpMethod, $url, array $parameters = [])
	{
		try {
			$parameters['token'] = $this->getToken();
			if ($httpMethod === 'get') {
				$response = $this->getClient()->{$httpMethod}('api/v1/' . $url, ['query' => $parameters]);
			} else {
				$parametersToken = ['token' => $this->getToken()];
				$response = $this->getClient()->{$httpMethod}('api/v1/' . $url, ['form_params' => $parameters, 'query' => $parametersToken]);
			}
			$responseBody = json_decode((string)$response->getBody(), true);
			return $responseBody;
			// return $responseBody['objects'];
		} catch (BadResponseException $exception) {
			$response = json_decode((string)$exception->getResponse()->getBody(), true);
			if ($response) {
				if (array_key_exists('error', $response)) {
					if ($response['error']['code'] == 151) throw new ModelNotFoundException($response['error']['message']);
					if ($response['error']['message'] != null) throw new Exception($response['error']['message']);
				}
				if (array_key_exists('status', $response)) {
					if ($response['status'] == 401) throw new UnauthorizedException($response['message']);
				}
			}
			throw new \Exception('Something went wrong.');
		}
	}

	// ========================= base methods ======================================

	public function _get($url = null, $parameters = [])
	{
		return $this->execute('get', $url, $parameters);
	}

	public function _post($url = null, array $parameters = [])
	{
		return $this->execute('post', $url, $parameters);
	}

	public function _put($url = null, array $parameters = [])
	{
		return $this->execute('put', $url, $parameters);
	}

	public function _patch($url = null, array $parameters = [])
	{
		return $this->execute('patch', $url, $parameters);
	}

	public function _delete($url = null, array $parameters = [])
	{
		return $this->execute('delete', $url, $parameters);
	}

	// =========================== helper ===========================================

	const INVOICE = 'Invoice';
	const CREDIT_NOTE = 'CreditNote';
	const ORDER = 'Order';

	public function getNextSequence(string $objectType, string $type)
	{
		$sequence = $this->_get(Routes::SEQUENCE, ['objectType' => $objectType, 'type' => $type])['objects'];
		return str_replace('%NUMBER', $sequence['nextSequence'], $sequence['format']);
	}

	public function getNextOrderNumber(string $type, bool $useNextNumber)
	{
		$sequence = $this->_get(Routes::ORDER . '/Factory/getNextOrderNumber', ['orderType' => $type, 'useNextNumber' => $useNextNumber])['objects'];
		return $sequence;
	}
}
