<?php
/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
* @author    PrestaShop SA <contact@prestashop.com>
* @copyright 2007-2015 PrestaShop SA
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit('');

require_once (dirname(__FILE__).'/lib/LabsMobile/ApiClient.php');

class LabsMobile extends Module
{

	/**
	 * A logger instance for labsmobile module.
	 * writes in file located in ./logs/labsmobile.log
	 *
	 * @var unknown
	 */
	private $logger;

	/**
	 * Are we in develoment mode?
	 * In develoment mode the log is active.
	 *
	 * @var boolean
	 */
	private $development_mode = false;

	/**
	 * Should we log to file?
	 *
	 * @var boolean
	 */
	private $log_enabled = false;

	/**
	 *
	 * @var LabsMobileApiClient
	 */
	private $api_client;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->name = 'labsmobile';

		$this->tab = 'emailing';

		$this->page = basename(__FILE__, '.php');

		$this->displayName = $this->l('LabsMobile SMS');

		$this->version = '1.0.0';

		$this->author = 'LabsMobile Dev Team';

		$this->need_instance = 0;

		$this->ps_versions_compliancy = array(
			'min' => '1.6',
			'max' => _PS_VERSION_
		);

		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('LabsMobile SMS');

		$this->description = $this->l(
			'With LabsMobile SMS module for Prestashop you will be able to integrate all the LabsMobile features with no coding. This module requires to  have an account with labsmobile.com and have available credit.');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall? You will not be able to send sms notifications.');

		$this->langid = !empty($this->context->language->id) ? $this->context->language->id : '';
		$this->lang_cookie = $this->context->cookie;

		if (!Configuration::get('LABSMOBILE_PASSWORD'))
			$this->warning = $this->l('Missing LabsMobile Account Password');

		if (!Configuration::get('LABSMOBILE_USERNAME'))
			$this->warning = $this->l('Missing LabsMobile Account Username');

			// Checking Extension
		if (!extension_loaded('curl') || !ini_get('allow_url_fopen'))
		{
			if (!extension_loaded('curl') && !ini_get('allow_url_fopen'))
				$this->warning = $this->l('You must enable cURL extension and allow_url_fopen option on your server if you want to use this module.');
			else
				if (!extension_loaded('curl'))
					$this->warning = $this->l('You must enable cURL extension on your server if you want to use this module.');
				else
					if (!ini_get('allow_url_fopen'))
						$this->warning = $this->l('You must enable allow_url_fopen option on your server if you want to use this module.');
		}

		$this->initLogger();

		// instance the LabsMobile Api Client
		$this->api_client = new LabsmobileApiClient();
		$this->api_client->setCredentials(Configuration::get('LABSMOBILE_USERNAME'), Configuration::get('LABSMOBILE_PASSWORD'));
	}

	/**
	 * Install the Plugin registering to the payment and order hooks
	 *
	 * @return boolean
	 */
	public function install()
	{
		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL);

		$this->logMessage('Installing LabsMobile Module');

		$success = (parent::install() && $this->hookInstall());

		if ($success)
		{
			$suggested_order_template = '';
			$suggested_order_template .= 'New order %order_reference%'."\n";
			$suggested_order_template .= 'from  %civility% %first_name% %last_name%,'."\n";
			$suggested_order_template .= 'placed on  %order_date%'."\n";
			$suggested_order_template .= 'for amount %order_price%'."\n";
			$suggested_order_template .= 'has been placed.'."\n";

            Configuration::updateValue('LABSMOBILE_ORDER_TEMPLATE', $suggested_order_template);

			$suggested_shipment_template = '';
			$suggested_shipment_template .= 'Dear %civility% %first_name% %last_name%,'."\n";
			$suggested_shipment_template .= 'your order  %order_reference%'."\n";
			$suggested_shipment_template .= 'placed on  %order_date%'."\n";
			$suggested_shipment_template .= 'for amount %order_price%'."\n";
			$suggested_shipment_template .= 'has been shipped.'."\n";

			$this->logMessage('Successfully installed LabsMobile Module');

            $languages = Language::getLanguages(false);
            $values = array();
            foreach ($languages as $lang) {
                $values['LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'][$lang['id_lang']] = $suggested_shipment_template;
            }
            Configuration::updateValue('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE', $values['LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE']);
		}
		else
			$this->logMessage('Error Installing LabsMobile Module');

		return $success;
	}

	/**
	 * Delete custom configuration keys.
	 *
	 * @return boolean
	 */
	private function removeConfigKeys()
	{
		Configuration::deleteByName('LABSMOBILE_USERNAME');
        Configuration::deleteByName('LABSMOBILE_PASSWORD');
        Configuration::deleteByName('LABSMOBILE_DEFAULT_ALPHASENDER');
        Configuration::deleteByName('LABSMOBILE_ORDER_TEMPLATE');
        Configuration::deleteByName('LABSMOBILE_ORDER_RECIPIENT');
        Configuration::deleteByName('LABSMOBILE_ORDER_NOTIFICATION_ACTIVE');
        Configuration::deleteByName('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE');
        Configuration::deleteByName('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE');

        return true;
	}

	/**
	 * Uninstall of hooks
	 *
	 * @return boolean
	 */
	private function hookUninstall()
	{
		return ($this->unregisterHook('orderConfirmation') && $this->unregisterHook('updateOrderStatus'));
	}

	/**
	 * Installation of hooks
	 *
	 * @return boolean
	 */
	private function hookInstall()
	{
		return ($this->registerHook('orderConfirmation') && $this->registerHook('updateOrderStatus'));
	}

	/**
	 *
	 * @return boolean
	 */
	public function uninstall()
	{
		$this->logMessage('Uninstalling LabsMobile Module');

        $success1 = parent::uninstall();
        $success2 = $this->removeConfigKeys();
        $success3 = $this->hookUninstall();

        $success = $success1 && $success2 && $success3;

		if ($success)
			$this->logMessage('LabsMobile Module Uninstalled Successfully');

		$this->dumpConfig();

		return $success;
	}

	/**
	 * Returns true if the user has opted in for shipping notification.
	 *
	 * @return boolean
	 */
	private function shouldNotifyUponShipment()
	{
		return Configuration::get('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE') == 1 &&
			Configuration::get('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE') != '';
	}

	/**
	 * Returns true if the user has opted in for new Order notification.
	 *
	 * @return boolean
	 */
	private function shouldNotifyUponNewOrder()
	{
		return Configuration::get('LABSMOBILE_ORDER_NOTIFICATION_ACTIVE') == 1 && Configuration::get('LABSMOBILE_ORDER_TEMPLATE') != '';
	}

	/**
	 * Should we use the specified Alphanumeric Sender instead of a mobile number?
	 *
	 * @return boolean
	 */
	private function shouldUseAlphasender()
	{
		return Configuration::get('LABSMOBILE_ALPHASENDER_ACTIVE') == 1 && Configuration::get('LABSMOBILE_DEFAULT_ALPHASENDER') != '';
	}

	/**
	 * Hook the event of shipping an order.
	 *
	 * @param unknown $params
	 * @return boolean
	 */
	public function hookUpdateOrderStatus($params)
	{
		$this->logMessage('Enter hookUpdateOrderStatus');

		if (!$this->checkModuleStatus())
		{
			$this->logMessage('LabsMobile module not enabled');
			return false;
		}

		$id_order_state = Tools::getValue('id_order_state');

		// if the order is not being shipped. Exit.
		if ($id_order_state != 4)
		{
			$this->logMessage("Order state do not match state 4. state is $id_order_state");
			return false;
		}

		// If the user didn't opted for notifications. Exit.
		if (!$this->shouldNotifyUponShipment())
		{
			$this->logMessage('User did not opted in for shipment notification');
			return false;
		}

		$this->logMessage('Valid hookUpdateOrderStatus');

		$params = $this->getParamsFromOrder();

		if (!$params)
		{
			$this->logMessage('Unable to load order data');
			return false;
		}

		return $this->sendMessageForOrder($params, 'LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE');
	}

	/**
	 *
	 * @return null Ambigous null, mixed>
	 */
	private function getParamsFromOrder()
	{
		$order = new Order(Tools::getValue('id_order'));
		$address = new Address((int)$order->id_address_delivery);

		$params = $this->populateOrderVariables($order, $address);

		$customer_mobile = $this->buildCustomerMobileNumber($address);

		if (!$customer_mobile)
		{
			$this->logMessage('Unable to retrive customers mobile number');
			return null;
		}

		$params['customer_mobile'] = $customer_mobile;

		return $params;
	}

	/**
	 *
	 * @param array $params
	 * @param string $template_id
	 */
	public function sendMessageForOrder($params, $template_id)
	{
		$this->logMessage(print_r($params, 1));

		$template = Configuration::get($template_id);

		$data = array();
		$data['text'] = $this->buildMessageBody($params, $template);
		$data['from'] = Configuration::get('LABSMOBILE_DEFAULT_ALPHASENDER');
		$data['to'] = $params['customer_mobile'];

		return $this->sendSmsApi($data);
	}

	/**
	 *
	 * @param Order $order
	 * @param Address $address
	 * @return array
	 */
	private function populateOrderVariables($order, $address)
	{
		$params = array();

		$customer_civility_result = Db::getInstance()->ExecuteS(
			'SELECT id_gender,firstname,lastname FROM '._DB_PREFIX_.'customer WHERE `id_customer` = '.(int)$order->id_customer);
		$firstname = (isset($address->firstname)) ? $address->firstname : '';
		$lastname = (isset($address->lastname)) ? $address->lastname : '';

		// Try to gess the civilty about the user.

		$civility_value = '';
		if (Tools::strtolower($firstname) === Tools::strtolower($customer_civility_result[0]['firstname']) &&
			Tools::strtolower($lastname) === Tools::strtolower($customer_civility_result[0]['lastname']))
			$civility_value = (isset($customer_civility_result['0']['id_gender'])) ? $customer_civility_result['0']['id_gender'] : '';

			// Guess the civilty for given user. Defaults to no civilty.

		switch ($civility_value)
		{
			case 1:
				$civility = 'Mr.';
				break;
			case 2:
				$civility = 'Ms.';
				break;
			case 3:
				$civility = 'Miss.';
				break;
			default:
				$civility = '';
				break;
		}

		// get order date.
		// try to format the date according to language context

		$order_date = (isset($order->date_upd)) ? $order->date_upd : 0;

		// if ($this->context->language->id == 1) {
		// $order_date = date('m/d/Y', strtotime($order_date));
		// } else {
		// $order_date = date('d/m/Y', strtotime($order_date));
		// }

		// the order amount and currency.
		$order_price = (isset($order->total_paid)) ? round($order->total_paid,2) : 0;
		$order_price = $this->context->currency->iso_code.' '.$order_price;

		if (_PS_VERSION_ < '1.5.0.0')
			$order_reference = (isset($order->id)) ? $order->id : '';
		else
			$order_reference = (isset($order->reference)) ? $order->reference : '';

			// Prepare variables for message template replacement.
			// We assume the user have specified a template for the message.

		$params['civility'] = $civility;
		$params['first_name'] = $firstname;
		$params['last_name'] = $lastname;
		$params['order_price'] = $order_price;
		$params['order_date'] = $order_date;
		$params['order_reference'] = $order_reference;

		return $params;
	}

	/**
	 * When a user places an order, the tracking code integrates in the order confirmation page.
	 *
	 * @param unknown $params
	 * @return boolean
	 */
	public function hookOrderConfirmation($params)
	{
		if (!$this->checkModuleStatus())
		{
			$this->logMessage('LabsMobile module not enabled');
			return false;
		}

		// If the user didn't opted for New Order notifications. Exit.
		if (!$this->shouldNotifyUponNewOrder())
		{
			$this->logMessage('Used did not opted in for New Order notification');
			return false;
		}

		$params_aux = $this->getParamsFromOrder();
		if (!$params_aux) {

		} else {
            $params = $params_aux;
        }

		$this->logMessage('hookOrderConfirmation');
		$this->logMessage(print_r($params, 1));

		$template = Configuration::get('LABSMOBILE_ORDER_TEMPLATE');

		$data = array();
		$data['text'] = $this->buildMessageBody($params, $template);
		$data['from'] = Configuration::get('LABSMOBILE_DEFAULT_ALPHASENDER');
		$data['to'] = Configuration::get('LABSMOBILE_ORDER_RECIPIENT');

		// Do Send Message
		return $this->sendSmsApi($data);
	}

	/**
	 * The user should have specified a country and mobile number.
	 *
	 * @param string $mobile_number
	 * @param Address $address
	 *
	 * @return string null mobile number or null
	 */
	private function buildCustomerMobileNumber($address)
	{
		// If for some reason the mobile number not specified in customer address. Exit.
		if (!isset($address->phone_mobile) || empty($address->phone_mobile))
		{
			$this->logMessage('Invalid customer mobile');
			return null;
		}

		$mobile_number = $address->phone_mobile;

		// Fetch the international prefix.
		// if not specified. Exit.

		$call_prefix_query = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
			'
				SELECT `call_prefix`
				FROM `'._DB_PREFIX_.'country`
				WHERE `id_country` = '.(int)$address->id_country);

		if (!isset($call_prefix_query['call_prefix']) || empty($call_prefix_query['call_prefix']))
		{
			$this->logMessage('Invalid customer country');
			return null;
		}

		$prefix = $call_prefix_query['call_prefix'];

		$this->logMessage("buildCustomerMobileNumber: $mobile_number / $prefix ");

		$mobile_number = trim($mobile_number);

		// replace double zero with plus
		if ($this->startsWith($mobile_number, '00'))
		{
			$mobile_number = str_replace('00', '', $mobile_number);
			return $mobile_number;
		}

		if ($this->startsWith($mobile_number, '+'))
		{
			$mobile_number = str_replace('+', '', $mobile_number);
			return $mobile_number;
		}

		return $prefix.$mobile_number;
	}

	/**
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @return boolean
	 */
	private function startsWith($haystack, $needle)
	{
		return $needle === '' || strrpos($haystack, $needle, -Tools::strlen($haystack)) !== false;
	}

	/**
	 * Return the user's credit.
	 *
	 * @return number
	 */
	public function getCredit()
	{
		return $this->api_client->getGatewayCredit();
	}

	/**
	 * Build an sms message merging a specified template, and given params array.
	 *
	 * @param array $params
	 * @param string $template
	 * @return string
	 */
	private function buildMessageBody($params, $template)
	{
		// TODO: we should perparse and notify the user if the message excedes a single message.
		if (isset($params['civility']))
			$template = str_replace('%civility%', $params['civility'], $template);

		if (isset($params['first_name']))
			$template = str_replace('%first_name%', $params['first_name'], $template);

		if (isset($params['last_name']))
			$template = str_replace('%last_name%', $params['last_name'], $template);

		if (isset($params['order_price']))
			$template = str_replace('%order_price%', $params['order_price'], $template);

		if (isset($params['order_date']))
			$template = str_replace('%order_date%', $params['order_date'], $template);

		if (isset($params['order_reference']))
			$template = str_replace('%order_reference%', $params['order_reference'], $template);

		return $template;
	}

	/**
	 * Send out a SMS using labsmobile API Client
	 *
	 * @param array $data
	 */
	private function sendSmsApi(array $data)
	{
		$this->logMessage('*********************** sendSmsApi ***********************');
		$this->logMessage(print_r($data, 1));

		$recipients = $data['to'];
		$text = $data['text'];
        $sender = $data['from'];

		$result = $this->api_client->sendSMS($recipients, $text, $sender);

		$this->logMessage($result);

		return $result;
	}

	/**
	 * Configure end render the admin's module form.
	 *
	 * @return string
	 */
	public function displayForm()
	{
		$data = array();
		$data['token'] = Tools::encrypt(Configuration::get('PS_SHOP_NAME'));
		$this->context->smarty->assign($data);

		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$fields_form = array();
		array_push($fields_form, array());

		// Configuration Form
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Settings'),
				'image' => '/modules/labsmobile/logointro.png'
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('LabsMobile Account Username'),
					'desc' => $this->l('The username to access LabsMobile services.'),
					'name' => 'LABSMOBILE_USERNAME',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('LabsMobile Account Password'),
					'desc' => $this->l('The password to access LabsMobile services'),
					'name' => 'LABSMOBILE_PASSWORD',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('Sender'),
					'desc' => $this->l('Alphanumeric or numeric Sender. Up to 11 characters without spaces or special characters (a-zA-Z0-9).'),
					'name' => 'LABSMOBILE_DEFAULT_ALPHASENDER',
					'size' => 11,
					'required' => false
				),
				array(
					'type' => 'checkbox',
					'label' => $this->l('New Order notification enabled?'),
					'desc' => $this->l('Check this option in order to receive a notification when a New Order is placed.'),
					'name' => 'LABSMOBILE_ORDER_NOTIFICATION',
					'required' => false,
					'values' => array(
						'query' => array(
							array(
								'id' => 'ACTIVE',
								'name' => $this->l('Enabled'),
								'val' => '1'
							)
						),
						'id' => 'id',
						'name' => 'name'
					)
				),
				array(
					'type' => 'text',
					'label' => $this->l('Order Recipient'),
					'desc' => $this->l('Recipient receiving SMS Order Notifications'),
					'name' => 'LABSMOBILE_ORDER_RECIPIENT',
					'size' => 20,
					'required' => false
				),
				array(
					'type' => 'textarea',
					'label' => $this->l('Order message template'),
					'desc' => $this->l('Type the message template for orders. You can use the variables %civility% %first_name% %last_name% %order_price% %order_date% %order_reference% that will be replaced in the message.'),
					'name' => 'LABSMOBILE_ORDER_TEMPLATE',
					'cols' => 40,
					'rows' => 5,
				),
				array(
					'type' => 'checkbox',
					'label' => $this->l('Shipment Status notification enabled?'),
					'desc' => $this->l('Check this option in order to send automatically a message to your customer when an order is shipped. The message will be sent if customer mobile phone and country are specified.'),
					'name' => 'LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION',
					'required' => false,
					'values' => array(
						'query' => array(
							array(
								'id' => 'ACTIVE',
								'name' => $this->l('Enabled'),
								'val' => '1'
							)
						),
						'id' => 'id',
						'name' => 'name'
					)
				),
				array(
					'type' => 'textarea',
					'label' => $this->l('Shipment Status template'),
					'desc' => $this->l('Type the message a customer receive when the order status transitions to SHIPPED. You can use the variables %civility% %first_name% %last_name% %order_price% %order_date% %order_reference% that will be replaced in the message.'),
					'name' => 'LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE',
					'cols' => 40,
					'rows' => 5,
                    'lang' => true,
				),
				array(
					'type' => 'free',
					'label' => $this->l('Check the Credit'),
					'desc' => $this->display(__FILE__, 'views/templates/admin/scripts.tpl'),
					'name' => 'FREE_TEXT',
					'required' => false
				)
			),
			'submit' => array(
				'title' => $this->l('Save')
			)
		);

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true; // false -> remove toolbar
		$helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' => array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.'&token='.
				Tools::getAdminTokenLite('AdminModules')
			),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);

		// Load current value
		$helper->fields_value['LABSMOBILE_USERNAME'] = Configuration::get('LABSMOBILE_USERNAME');
		$helper->fields_value['LABSMOBILE_PASSWORD'] = Configuration::get('LABSMOBILE_PASSWORD');
		$helper->fields_value['LABSMOBILE_DEFAULT_ALPHASENDER'] = Configuration::get('LABSMOBILE_DEFAULT_ALPHASENDER');
		$helper->fields_value['LABSMOBILE_ORDER_NOTIFICATION_ACTIVE'] = ((string)Configuration::get('LABSMOBILE_ORDER_NOTIFICATION_ACTIVE') == '1');
		$helper->fields_value['LABSMOBILE_ORDER_RECIPIENT'] = Configuration::get('LABSMOBILE_ORDER_RECIPIENT');
        $helper->fields_value['LABSMOBILE_ORDER_TEMPLATE'] = Configuration::get('LABSMOBILE_ORDER_TEMPLATE');
		$helper->fields_value['LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE'] = ((string)Configuration::get('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE') ==
			'1');
		$helper->fields_value['FREE_TEXT'] = Configuration::get('FREE_TEXT');

        $languages = Language::getLanguages(false);
        foreach ($languages as $lang)
        {
            $helper->fields_value['LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'][$lang['id_lang']] = Configuration::get('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE', $lang['id_lang']);
        }

		$theform = '';

		$this->context->smarty->assign($data);

        $helper->tpl_vars = array(
            'uri' => $this->getPathUri(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

		$theform .= $this->display(__FILE__, 'views/templates/admin/intro.tpl');
		$theform .= $helper->generateForm($fields_form);

		return $theform;
	}


	/**
	 * When submitted the config form!
	 *
	 * @return string
	 */
	public function getContent()
	{
		$output = null;
        $languages = Language::getLanguages(false);

		if (Tools::isSubmit('submit'.$this->name))
		{
			$labsmobile_username = (string)Tools::getValue('LABSMOBILE_USERNAME');
			if (!$labsmobile_username || empty($labsmobile_username) || !Validate::isEmail($labsmobile_username))
				$output .= $this->displayError($this->l('Invalid username'));
			else
			{
				Configuration::updateValue('LABSMOBILE_USERNAME', $labsmobile_username);
				$output .= $this->displayConfirmation($this->l('Username updated'));
			}

			// Password field

			$labsmobile_password = (string)Tools::getValue('LABSMOBILE_PASSWORD');
			if (!$labsmobile_password || empty($labsmobile_password) || !Validate::isGenericName($labsmobile_password))
				$output .= $this->displayError($this->l('Invalid password'));
			else
			{
				Configuration::updateValue('LABSMOBILE_PASSWORD', $labsmobile_password);
				$output .= $this->displayConfirmation($this->l('Password updated'));
			}


			// Alphanumeric sender. we validate just if the user opted in.
            $labsmobile_alpha_sender = (string)Tools::getValue('LABSMOBILE_DEFAULT_ALPHASENDER');
            $labsmobile_alpha_sender = trim($labsmobile_alpha_sender);

            if (!$labsmobile_alpha_sender || empty($labsmobile_alpha_sender) || !$this->isValidAlphasender($labsmobile_alpha_sender))
                $output .= $this->displayError($this->l('Invalid Alpha Sender'));

            else
            {
                Configuration::updateValue('LABSMOBILE_DEFAULT_ALPHASENDER', $labsmobile_alpha_sender);
                $output .= $this->displayConfirmation($this->l('Alpha Sender updated'));
            }


			// New Order Notification active

			$labsmobile_neworder_active = Tools::getValue('LABSMOBILE_ORDER_NOTIFICATION_ACTIVE');
			Configuration::updateValue('LABSMOBILE_ORDER_NOTIFICATION_ACTIVE', $labsmobile_neworder_active);

			$this->logMessage('New order notification active');
			$this->logMessage($labsmobile_neworder_active);

			if ($labsmobile_neworder_active)
			{
				// New Order notification Template
                $labsmobile_order_template = (string)Tools::getValue('LABSMOBILE_ORDER_TEMPLATE');
                if (!$labsmobile_order_template || empty($labsmobile_order_template))
                    Configuration::updateValue('LABSMOBILE_ORDER_TEMPLATE', Configuration::get('LABSMOBILE_ORDER_TEMPLATE'));
                else
                {
                    Configuration::updateValue('LABSMOBILE_ORDER_TEMPLATE', $labsmobile_order_template);
                }
                $output .= $this->displayConfirmation($this->l('Order Template updated'));

				// New Order Recipient

				$labsmobile_order_recipient = (string)Tools::getValue('LABSMOBILE_ORDER_RECIPIENT');
				$labsmobile_order_recipient = $this->normalizeNumber($labsmobile_order_recipient);

				if (!$labsmobile_order_recipient || empty($labsmobile_order_recipient) || !Validate::isGenericName($labsmobile_order_recipient) ||
					!$this->isValidMobileNumber($labsmobile_order_recipient))
					$output .= $this->displayError($this->l('Invalid Order Recipient'));
				else
				{
					Configuration::updateValue('LABSMOBILE_ORDER_RECIPIENT', $labsmobile_order_recipient);
					$output .= $this->displayConfirmation($this->l('Order Recipient Updated'));
				}
			}

			// Shipment active
			// Update the checkbox

			$labsmobile_shipment_active = Tools::getValue('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE');
			Configuration::updateValue('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE', $labsmobile_shipment_active);

			$this->logMessage('shipment active');
			$this->logMessage($labsmobile_shipment_active);

			// Shipment Template
			if ($labsmobile_shipment_active)
			{
                $values = array();
                foreach ($languages as $lang)
                {
                    $labsmobile_shipment_template = (string)Tools::getValue('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE_'.$lang['id_lang']);
                    if (!$labsmobile_shipment_template || empty($labsmobile_shipment_template))
                        $values['LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'][$lang['id_lang']] = Configuration::get('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE');
                    else
                    {
                        $values['LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'][$lang['id_lang']] = $labsmobile_shipment_template;
                    }
                }
                Configuration::updateValue('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE', $values['LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE']);
                $output .= $this->displayConfirmation($this->l('Shipment Template updated'));
			}

			$this->logMessage('Updated config Values');

			$this->dumpConfig();
		}

		return $output.$this->displayForm();
	}

	/**
	 */
	private function dumpConfig()
	{
		if (!$this->development_mode)
			return;

			// general
		$this->logMessage('LABSMOBILE_PASSWORD: '.Tools::getValue('LABSMOBILE_PASSWORD'));
		$this->logMessage('LABSMOBILE_USERNAME: '.Tools::getValue('LABSMOBILE_USERNAME'));

		// Sender number or alphanumeric sender
		$this->logMessage('LABSMOBILE_DEFAULT_ALPHASENDER: '.Tools::getValue('LABSMOBILE_DEFAULT_ALPHASENDER'));

		// feature new order
		$this->logMessage('LABSMOBILE_ORDER_NOTIFICATION_ACTIVE: '.Tools::getValue('LABSMOBILE_ORDER_NOTIFICATION_ACTIVE'));
		$this->logMessage('LABSMOBILE_ORDER_RECIPIENT: '.Tools::getValue('LABSMOBILE_ORDER_RECIPIENT'));
		$this->logMessage('LABSMOBILE_ORDER_TEMPLATE: '.Tools::getValue('LABSMOBILE_ORDER_TEMPLATE'));

		// feature shipment
		$this->logMessage('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE: '.Tools::getValue('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_TEMPLATE'));
		$this->logMessage('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE: '.Tools::getValue('LABSMOBILE_SHIPMENTSTATUS_NOTIFICATION_ACTIVE'));
	}

	/**
	 * We do not implement the validation now.
	 * Is too complex.
	 * Will be done in next release.
	 *
	 * @param string $alpha_sender
	 * @return boolean
	 */
	private function isValidAlphasender($alpha_sender)
	{
		return (trim($alpha_sender) !== '');
	}

	/**
	 * Normalize a mobile number string
	 *
	 * @param unknown $mobile_number
	 * @return boolean number
	 */
	private function normalizeNumber($mobile_number)
	{
		$mobile_number = str_replace('+', '', $mobile_number);
		$mobile_number = preg_replace('/\s+/', '', $mobile_number);
		return $mobile_number;
	}

	/**
	 * Method is used to check the current status of the module whether its active or not.
	 */
	private function checkModuleStatus()
	{
		return Module::isEnabled('labsmobile');
	}

    /**
     *
     * @param unknown $mobile_number
     * @return boolean number
     */
    private function isValidMobileNumber($mobile_number)
    {
        return preg_match('/^[0-9]{8,12}$/', $mobile_number);
    }

	/**
	 * Add a message to the Log.
	 *
	 * @param unknown $message
	 */
	private function logMessage($message)
	{
		if (!$this->log_enabled)
			return;

		$this->logger->logDebug($message);
	}

	/**
	 * Initialize the logger
	 */
	private function initLogger()
	{
		if (!$this->log_enabled)
			return;

		$this->logger = new FileLogger(0);
		$this->logger->setFilename(_PS_ROOT_DIR_.'/log/labsmobile.log');
	}
}