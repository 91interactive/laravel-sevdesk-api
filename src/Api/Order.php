<?php
/*
 * Order.php
 * @author Ruben Müller <mueller@91interactive.com>
 * @copyright 2023 Ruben Müller
 */

namespace Exlo89\LaravelSevdeskApi\Api;

use Exception;
use Exlo89\LaravelSevdeskApi\Constants\Country;
use Exlo89\LaravelSevdeskApi\Models\SevOrder;
use Illuminate\Support\Collection;
use Exlo89\LaravelSevdeskApi\Api\Utils\ApiClient;
use Exlo89\LaravelSevdeskApi\Api\Utils\Routes;

/**
 * Sevdesk Order Api
 *
 * @see https://api.sevdesk.de/#tag/Order
 */
class Order extends ApiClient
{
	/**
	 * Order status
	 */
	const DRAFT = 100;
	const DELIVERED = 200;
	const REJECTED_OR_CANCELLED = 300;
	const ACCEPTED = 500;
	const PARTIALLY_CALCULATED = 750;
	const CALCULATED = 1000;

	/**
	 * Order type
	 */
	const ESTIMATE_OR_PROPOSAL = "AN";
	const ORDER_CONFIRMATION = "AB";
	const DELIVERY_NOTE = "LI";


	const DEFAULT_LIMIT = 9999;

	// =========================== all ====================================

	/**
	 * Return all orders.
	 *
	 * @return mixed
	 */
	public function all(int $depth = 0, int $limit = self::DEFAULT_LIMIT)
	{
		return Collection::make($this->_get(Routes::ORDER, ['depth' => $depth, 'limit' => $limit, 'embed' => 'category,unity', 'countAll' => 'true']));
	}

	/**
	 * Return all draft orders.
	 *
	 * @return mixed
	 */
	public function allDraft()
	{
		return Collection::make($this->_get(Routes::ORDER, ['status' => self::DRAFT]));
	}

	/**
	 * Return all open orders.
	 *
	 * @return mixed
	 */
	public function allOpen()
	{
		return Collection::make($this->_get(Routes::ORDER, ['status' => self::DELIVERED]));
	}

	/**
	 * Return all accepted orders.
	 *
	 * @return mixed
	 */
	public function allAccepted()
	{
		return Collection::make($this->_get(Routes::ORDER, ['status' => self::ACCEPTED]));
	}

	/**
	 * Return all orders filtered by contact id.
	 *
	 * @return mixed
	 */
	public function allByContact($contactId)
	{
		return Collection::make($this->_get(Routes::ORDER, [
			'contact' => [
				'id' => $contactId,
				'objectName' => 'Contact'
			],
		]));
	}

	/**
	 * Return all orders filtered by a date equal or lower.
	 *
	 * @return mixed
	 */
	public function allBefore(int $timestamp)
	{
		return Collection::make($this->_get(Routes::ORDER, ['endDate' => $timestamp]));
	}

	/**
	 * Return all orders filtered by a date equal or higher.
	 *
	 * @return mixed
	 */
	public function allAfter(int $timestamp)
	{
		return Collection::make($this->_get(Routes::ORDER, ['startDate' => $timestamp]));
	}

	/**
	 * Return a single order.
	 *
	 * @param $orderId
	 * @return mixed
	 */
	public function get($orderId): SevOrder
	{
		return SevOrder::make($this->_get(Routes::ORDER . '/' . $orderId)['objects'][0]);
	}

	// =========================== create ====================================

	/**
	 * Create order.
	 *
	 * @param $contactId
	 * @param $items
	 * @param array $parameters
	 * @return mixed
	 * @throws Exception
	 */
	public function create($contactId, $items, array $parameters = [])
	{
		return SevOrder::make($this->_post(Routes::CREATE_ORDER, $this->getParameters($contactId, $items, $parameters))['objects']['order']);
	}

	/**
	 * Validate and return config values.
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getConfigs(): array
	{
		$values = [];
		$values['taxRate'] = config('sevdesk-api.tax_rate');
		if (empty($values['taxRate'])) {
			throw new Exception('Configuration parameter not found: tax_rate');
		}

		$values['taxText'] = config('sevdesk-api.tax_text');
		if (empty($values['taxText'])) {
			throw new Exception('Configuration parameter not found: tax_text');
		}

		$values['taxType'] = config('sevdesk-api.tax_type');
		if (empty($values['taxType'])) {
			throw new Exception('Configuration parameter not found: tax_type');
		}

		// $values['invoiceType'] = config('sevdesk-api.invoice_type');
		// if (empty($values['invoiceType'])) {
		//     throw new Exception('Configuration parameter not found: invoice_type');
		// }

		$values['currency'] = config('sevdesk-api.currency');
		if (empty($values['currency'])) {
			throw new Exception('Configuration parameter not found: currency');
		}

		$values['sevUserId'] = config('sevdesk-api.sev_user_id');
		if (empty($values['sevUserId'])) {
			throw new Exception('Configuration parameter not found: sev_user_id');
		}
		return $values;
	}

	/**
	 * Format items
	 *
	 * @param $items
	 * @param $configs
	 * @return array
	 * @throws Exception
	 */
	private function getOrderItems($items, $configs): array
	{
		$orderItems = [];
		if (empty($items)) {
			throw new Exception('No order items found');
		}
		foreach ($items as $item) {
			if (array_key_exists('name', $item) && array_key_exists('price', $item)) {
				$orderItems[] = [
					'objectName' => 'OrderPos',
					'mapAll' => 'true',
					'quantity' => $item['quantity'] ?? 1,
					'part' => ['id' => $item['part_id'], 'objectName' => "Part"],
					'price' => $item['price'],
					'name' => $item['name'],
					'text' => $item['text'] ?? '',
					'taxRate' => $configs['taxRate'],
					'unity' => [
					    'id' => 1,
					    'objectName' => 'Unity',
					]

				];
			}
		}
		return $orderItems;
	}

	/**
	 * @param $contactId
	 * @param $items
	 * @param $parameters
	 * @return array
	 * @throws Exception
	 */
	private function getParameters($contactId, $items, $parameters): array
	{
		// validate config values
		$configs = $this->getConfigs();
		// fetch and format next order number
		$nextSequence = $this->getNextSequence(ApiClient::ORDER);
		$requiredParameters = [
			'order' => [
				'objectName' => 'Order',
				'contact' => [
					'id' => $contactId,
					'objectName' => 'Contact'
				],
				'header' => 'Angebot NR. ' . $nextSequence, //TODO (Martin): find better solution to generate header
				'orderNumber' => $nextSequence,
				'orderDate' => date('Y-m-d H:i:s'),
				'discount' => 0,
				'addressCountry' => [
					'id' => $parameters['country'] ?? Country::GERMANY,
					'objectName' => 'StaticCountry'
				],
				'status' => $parameters['status'] ?? self::DRAFT,
				'contactPerson' => [
					'id' => $configs['sevUserId'],
					'objectName' => 'SevUser'
				],
				'taxRate' => $configs['taxRate'],
				'taxText' => $configs['taxText'],
				'taxType' => $configs['taxType'],
				'orderType' => $parameters['orderType'],
				'currency' => $configs['currency'],
				'mapAll' => 'true',
				'version' => 0 // rm: Version of the order. Can be used if you have multiple drafts for the same order. Should start with 0
			],
			'orderPosSave' => $this->getOrderItems($items, $configs)
		];
		return array_replace_recursive($requiredParameters, $parameters);
	}

	// =======================================================================

	/**
	 * Returns pdf file of the giving order id.
	 *
	 * @return void
	 */
	public function download($orderId, $preview = false)
	{
		$response = $this->_get(Routes::ORDER . '/' . $orderId . '/getPdf', ["preventSendBy" => $preview])['objects'];
		$file = $response['filename'];
		file_put_contents($file, base64_decode($response['content']));

		if (file_exists($file)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="' . basename($file) . '"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($file));
			readfile($file);
			exit();
		}
	}

	/**
	 * Returns raw pdf object of the giving order id.
	 *
	 * @return void
	 */
	public function getRawPdfData($orderId, $preview = true)
	{
		$response = $this->_get(Routes::ORDER . '/' . $orderId . '/getPdf', ["preventSendBy" => $preview])['objects'];
		return $response;
	}

	/**
	 * Send order per email.
	 *
	 * @return void
	 */
	public function sendPerMail($orderId, $email, $subject, $text)
	{
		return $this->_post(Routes::ORDER . '/' . $orderId . '/sendViaEmail', [
			'toEmail' => $email,
			'subject' => $subject,
			'text' => $text,
		]);
	}
}
