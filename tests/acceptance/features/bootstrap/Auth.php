<?php
/**
 * @author Christoph Wurst <christoph@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;

require __DIR__ . '/../../../../lib/composer/autoload.php';

/**
 * Authentication functions
 */
trait Auth {
	/**
	 * @var Client
	 */
	private $client = '';

	/**
	 * @var string
	 */
	private $clientToken;

	/**
	 * @var string
	 */
	private $appToken;

	/**
	 * @var boolean
	 */
	private $tokenAuthHasBeenSet = false;

	/**
	 * @BeforeScenario
	 *
	 * @return void
	 */
	public function setUpScenario() {
		$this->client = new Client();
		$this->responseXml = '';
	}

	/**
	 * @When a user requests :url with :method and no authentication
	 * @Given a user has requested :url with :method and no authentication
	 *
	 * @param string $url
	 * @param string $method
	 *
	 * @return void
	 */
	public function userRequestsURLWith($url, $method) {
		$this->sendRequest($url, $method);
	}

	/**
	 * @param string $url
	 * @param string $method
	 * @param string|null $authHeader
	 * @param bool $useCookies
	 *
	 * @return void
	 */
	public function sendRequest(
		$url, $method, $authHeader = null, $useCookies = false
	) {
		$fullUrl = $this->getBaseUrl() . $url;
		$options = [];
		try {
			$headers = [
				'OCS_APIREQUEST' => 'true',
				'requesttoken' => $this->requestToken
			];
			if ($authHeader) {
				$headers['Authorization'] = $authHeader;
			}
			if ($useCookies) {
				$options = [
					'cookies' => $this->cookieJar,
				];
			}
			if ($authHeader) {
				$headers['Authorization'] = $authHeader;
			}
			$headers['OCS-APIREQUEST'] = 'true';
			if (isset($this->requestToken)) {
				$headers['requesttoken'] = $this->requestToken;
			}
			$request = new Request($method, $fullUrl, $headers);
			$this->response = $this->client->send($request, $options);
		} catch (BadResponseException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * Use the private API to generate an app password
	 *
	 * @param string $name
	 *
	 * @return void
	 */
	public function userGeneratesNewAppPasswordNamed($name) {
		$options = [];
		$options['cookies'] = $this->cookieJar;
		$options['form_params'] = ['name' => $name];
		$headers = [];
		$headers['Content-Type'] = 'application/x-www-form-urlencoded';
		$headers['OCS-APIREQUEST'] = 'true';
		$headers['requesttoken'] = $this->requestToken;
		$headers['X-Requested-With'] = 'XMLHttpRequest';
		$request = new Request(
			'POST',
			$this->getBaseUrl() . '/index.php/settings/personal/authtokens',
			$headers
		);
		$this->response = $this->client->send($request, $options);
		$this->appToken
			= \json_decode($this->response->getBody()->getContents())->token;
	}

	/**
	 * @Given the user has generated a new app password named :name
	 *
	 * @param string $name
	 *
	 * @return void
	 */
	public function aNewAppPasswordHasBeenGenerated($name) {
		$this->userGeneratesNewAppPasswordNamed($name);
		$this->theHTTPStatusCodeShouldBe(200);
	}

	/**
	 * @When user :user generates a new client token using the token API
	 * @Given a new client token for :user has been generated
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function aNewClientTokenHasBeenGenerated($user) {
		$client = new Client();
		$resp = $client->post(
			$this->getBaseUrl() . '/token/generate', [
			'json' => [
					'user' => $user,
					'password' => $this->getPasswordForUser($user),
			]
			]
		);
		$this->clientToken
			= \json_decode($resp->getBody()->getContents())->token;
	}

	/**
	 * @When user :user requests :url with :method using basic auth
	 * @Given user :user has requested :url with :method using basic auth
	 *
	 * @param string $user
	 * @param string $url
	 * @param string $method
	 *
	 * @return void
	 */
	public function userRequestsURLWithUsingBasicAuth($user, $url, $method) {
		$authString = $user . ':' . $this->getPasswordForUser($user);
		$this->sendRequest(
			$url, $method, 'basic ' . \base64_encode($authString)
		);
	}

	/**
	 * @When user :user requests :url with :method using basic token auth
	 * @Given user :user has requested :url with :method using basic token auth
	 *
	 * @param string $user
	 * @param string $url
	 * @param string $method
	 *
	 * @return void
	 */
	public function userRequestsURLWithUsingBasicTokenAuth($user, $url, $method) {
		$this->sendRequest(
			$url,
			$method,
			'basic ' . \base64_encode($user . ':' . $this->clientToken)
		);
	}

	/**
	 * @When the user requests :url with :method using the generated client token
	 * @Given the user has requested :url with :method using the generated client token
	 *
	 * @param string $url
	 * @param string $method
	 *
	 * @return void
	 */
	public function userRequestsURLWithUsingAClientToken($url, $method) {
		$this->sendRequest($url, $method, 'token ' . $this->clientToken);
	}

	/**
	 * @When the user requests :url with :method using the generated app password
	 * @Given the user has requested :url with :method using the generated app password
	 *
	 * @param string $url
	 * @param string $method
	 *
	 * @return void
	 */
	public function userRequestsURLWithUsingAppPassword($url, $method) {
		$this->sendRequest($url, $method, 'token ' . $this->appToken);
	}

	/**
	 * @When the user requests :url with :method using the browser session
	 * @Given the user has requested :url with :method using the browser session
	 *
	 * @param string $url
	 * @param string $method
	 *
	 * @return void
	 */
	public function userRequestsURLWithBrowserSession($url, $method) {
		$this->sendRequest($url, $method, null, true);
	}

	/**
	 * @Given a new browser session for :user has been started
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function aNewBrowserSessionForHasBeenStarted($user) {
		$loginUrl = $this->getBaseUrl() . '/index.php/login';
		// Request a new session and extract CSRF token
		$client = new Client();
		$response = $client->get(
			$loginUrl, [
			'cookies' => $this->cookieJar,
			]
		);
		$this->extractRequestTokenFromResponse($response);

		// Login and extract new token
		$client = new Client();
		$response = $client->post(
			$loginUrl, [
				'form_params' => [
					'user' => $user,
					'password' => $this->getPasswordForUser($user),
					'requesttoken' => $this->requestToken,
				],
				'cookies' => $this->cookieJar,
			]
		);
		$this->extractRequestTokenFromResponse($response);
	}

	/**
	 * @When /^the administrator (enforces|does not enforce)\s?token auth$/
	 * @Given /^token auth has (not|)\s?been enforced$/
	 *
	 * @param string $hasOrNot
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function tokenAuthHasBeenEnforced($hasOrNot) {
		$enforce = (($hasOrNot !== "not") && ($hasOrNot !== "does not enforce"));
		if ($enforce) {
			$value = 'true';
		} else {
			$value = 'false';
		}
		$this->runOcc(
			[
				'config:system:set',
				'token_auth_enforced',
				'--type',
				'boolean',
				'--value',
				$value
			]
		);

		// Remember that we set this value, so it can be removed after the scenario
		$this->tokenAuthHasBeenSet = true;
	}

	/**
	 * delete token_auth_enforced if it was set in the scenario
	 *
	 * @AfterScenario
	 *
	 * @return void
	 */
	public function deleteTokenAuthEnforcedAfterScenario() {
		if ($this->tokenAuthHasBeenSet) {
			$this->runOcc(
				[
					'config:system:delete',
					'token_auth_enforced'
				]
			);
			$this->tokenAuthHasBeenSet = false;
		}
	}
}
