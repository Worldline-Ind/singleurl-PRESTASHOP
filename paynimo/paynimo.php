<?php
if (!defined('_PS_VERSION_'))
	exit;

class Paynimo extends PaymentModule
{
	private $error_messages;
	public function __construct()
	{
		$this->name = 'paynimo';
		$this->tab = 'payments_gateways';
		$this->version = '1.0';
		$this->author = 'Techprocess';
		$this->need_instance = 0;
		$this->controllers = array('request');
		$this->is_eu_compatible = 1;
		$this->error_messages;
		$this->bootstrap = true;
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';

		parent::__construct();

		$this->displayName = $this->l('Paynimo');
		$this->description = $this->l('Paynimo Online Payment Method');

		/* For 1.4.3 and less compatibility */
		$updateConfig = array('PS_OS_CHEQUE', 'PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_CANCELED', 'PS_OS_REFUND', 'PS_OS_ERROR', 'PS_OS_OUTOFSTOCK', 'PS_OS_BANKWIRE', 'PS_OS_PAYPAL', 'PS_OS_WS_PAYMENT');
		if (!Configuration::get('PS_OS_PAYMENT'))
			foreach ($updateConfig as $u)
				if (!Configuration::get($u) && defined('_' . $u . '_'))
					Configuration::updateValue($u, constant('_' . $u . '_'));
	}

	public function install()
	{
		parent::install();
		$this->registerHook('payment');
		$this->registerHook('displayPaymentEU');
		$this->registerHook('paymentReturn');
		Configuration::updateValue('Paynimo_checkout_label', 'Pay Using Paynimo');
		return true;
	}


	public function uninstall()
	{
		parent::uninstall();
		Configuration::deleteByName('paynimo_client_id');
		Configuration::deleteByName('paynimo_client_secret');
		Configuration::deleteByName('instmaojo_testmode');
		Configuration::deleteByName('Paynimo_checkout_label');
		return true;
	}
	public function hookPayment()
	{
		if (!$this->active)
			return;

		$this->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_paynimo' => $this->_path,
			'checkout_label' => $this->l((Configuration::get('Paynimo_checkout_label')) ? Configuration::get('Paynimo_checkout_label') : "Pay using Paynimo"),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
		));

		return $this->display(__FILE__, 'payment.tpl');
	}

	public function hookDisplayPaymentEU()
	{
		if (!$this->active)
			return;

		return array(
			'cta_text' => $this->l((Configuration::get('Paynimo_checkout_label')) ? Configuration::get('Paynimo_checkout_label') : "Pay using Paynimo"),
			'logo' => Media::getMediaPath(dirname(__FILE__) . '/paynimo.png'),
			'action' => $this->context->link->getModuleLink($this->name, 'request', array('confirm' => true), true)
		);
	}


	public function hookPaymentReturn()
	{
		if (!$this->active)
			return;
		return;
	}





	# Show Configuration form in admin panel.
	public function getContent()
	{
		if (((bool)Tools::isSubmit('submitPaynimoModule')) == true) {
			$this->postProcess();
		}

		$this->context->smarty->assign('module_dir', $this->_path);

		return $this->renderForm();
	}

	protected function postProcess()
	{
		$form_values = $this->getConfigFormValues();
		foreach (array_keys($form_values) as $key) {
			Configuration::updateValue($key, Tools::getValue($key));
		}
	}

	protected function renderForm()
	{
		$helper = new HelperForm();

		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$helper->module = $this;
		$helper->default_form_language = $this->context->language->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitPaynimoModule';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
			. '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id,
		);

		return $helper->generateForm(array($this->getConfigForm()));
	}


	protected function getConfigForm()
	{
		return array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs',
				),
				'input' => array(
					array(
						'type' => 'select',
						'label' => $this->l('Web Servie Locator URL'),
						'name' => 'PAYNIMO_LIVE_MODE',
						'options' => array(
							'query' => $options = array(
								array(
									'id_option' => 'Live',
									'name' => 'Live',
								),
								array(
									'id_option' => 'Demo',
									'name' => 'Demo',
								),
							),
							'id' => 'id_option',
							'name' => 'name',
						),
					),

					array(
						'type' => 'select',
						'label' => $this->l('Hashing Algorithm'),
						'name' => 'PAYNIMO_HASH_ALGO',
						'options' => array(
							'query' => $options = array(
								array(
									'id_option' => 'SHA3-512',
									'name' => 'SHA3-512',
								),
								array(
									'id_option' => 'SHA3-256',
									'name' => 'SHA3-256',
								),
							),
							'id' => 'id_option',
							'name' => 'name',
						),
					),

					array(
						'type' => 'select',
						'label' => $this->l('Request Type:'),
						'name' => 'REQUEST_TYPE',
						'required' => true,
						'options' => array(
							'query' => $options = array(
								array(
									'id_option' => 'T',
									'name' => 'T',
								),
							),
							'id' => 'id_option',
							'name' => 'name',
						),
					),

					array(
						'col' => 3,
						'type' => 'text',
						'name' => 'PAYNIMO_MERCHANT_CODE',
						'label' => $this->l('Merchant Code'),
					),
					array(
						'col' => 3,
						'type' => 'text',
						'name' => 'PAYNIMO_KEY',
						'label' => $this->l('KEY'),
					),
					array(
						'col' => 3,
						'type' => 'text',
						'name' => 'PAYNIMO_IV',
						'label' => $this->l('IV'),
					),
					array(
						'col' => 3,
						'type' => 'text',
						'name' => 'PAYNIMO_SCODE',
						'label' => $this->l('Merchant Scheme Code'),
					),

				),
				'submit' => array(
					'title' => $this->l('Save'),
				),
			),
		);
	}

	/**
	 * Set values for the inputs.
	 */
	public function getConfigFormValues()
	{
		return array(
			'PAYNIMO_LIVE_MODE' => Configuration::get('PAYNIMO_LIVE_MODE', null),
			'PAYNIMO_HASH_ALGO' => Configuration::get('PAYNIMO_HASH_ALGO', null),
			'REQUEST_TYPE' => Configuration::get('REQUEST_TYPE', null),
			'PAYNIMO_MERCHANT_CODE' => Configuration::get('PAYNIMO_MERCHANT_CODE', null),
			'PAYNIMO_KEY' => Configuration::get('PAYNIMO_KEY', null),
			'PAYNIMO_IV' => Configuration::get('PAYNIMO_IV', null),
			'PAYNIMO_SCODE' => Configuration::get('PAYNIMO_SCODE', null),
		);
	}
}
