<?php
require_once __DIR__ . '/../../lib/TransactionRequestBean.php';

function is_not_17(){
	return version_compare(_PS_VERSION_, '1.7', '<');
}

class PaynimorequestModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	private $template_data =array();
	public $display_column_left = false;

	public function postProcess()
    {
        //After confirming order
        if (Tools::getValue('confirm')) {

            if (Tools::usingSecureMode()) {
                $domain = Tools::getShopDomainSsl(true);
            } else {
                $domain = Tools::getShopDomain(true);
            }


            /* Getting merchant details */
            $merchantDetails = $this->module->getConfigFormValues();

            /* Setting payment details */
            $merchantTxnId = rand(1, 1000000);
            $curDate = date("d-m-Y");

            /* Getting cart details */
            $cart = Context::getContext()->cart;
            $amount = number_format((float)$cart->getOrderTotal(true, 3), 2, '.', '');
            $address = new Address((int)($cart->id_address_delivery));

            $customerId = (int)$this->context->cookie->id_customer;
            $customer = new Customer((int)$customerId);
                  
            $total = $this->context->cart->getOrderTotal(true, Cart::BOTH);

            $result = Db::getInstance()->getRow('
                        SELECT `id_guest`
                        FROM `'._DB_PREFIX_.'guest`
                        WHERE `id_customer` = '.$customerId);

            $isGuest = '0';
            if (!$this->context->cookie->isLogged()){
                $isGuest = '1';
            }
        
            //Make payment request using TransactionRequestBean
            $transactionRequestBean = new TransactionRequestBean();
            $transactionRequestBean->__set('merchantCode', $merchantDetails['PAYNIMO_MERCHANT_CODE']);
            $transactionRequestBean->__set('requestType', $merchantDetails['REQUEST_TYPE']);
            $transactionRequestBean->__set('merchantTxnRefNumber', $merchantTxnId);
            $transactionRequestBean->__set('hashalgo', $merchantDetails['PAYNIMO_HASH_ALGO']);

            if ($merchantDetails['PAYNIMO_LIVE_MODE']=='Live') {
            	$url = 'https://www.tpsl-india.in/PaymentGateway/TransactionDetailsNew.wsdl';
                $shoppingCartStr = $merchantDetails['PAYNIMO_SCODE'] . '_' . $amount . '_0.0';
            } else {
            	$amount = '1.00';
                $url = 'https://www.tekprocess.co.in/PaymentGateway/TransactionDetailsNew.wsdl';
                $shoppingCartStr = $merchantDetails['PAYNIMO_SCODE'] . '_' . $amount . '_0.0';
                $bankCode = '470';

                $transactionRequestBean->__set('bankCode', $bankCode);                
            }

            $transactionRequestBean->__set('amount', $amount);
            $transactionRequestBean->__set('webServiceLocator', $url);
            $transactionRequestBean->__set('shoppingCartDetails', $shoppingCartStr);

            if ($customer) {
                $customerName = Tools::safeOutput($customer->firstname) . ' ' . Tools::safeOutput($customer->lastname);
                $transactionRequestBean->__set('customerName', $customerName);
            }

            $returnUrl = $domain . __PS_BASE_URI__ .
                "index.php?fc=module&module=paynimo&controller=response&currentOrderId=" . $orderId . "&isGuest=" . $isGuest .
                "&guestEmail=" . $customer->email .  "&cartId=" . (int)$this->context->cart->id;

            $transactionRequestBean->__set('returnURL', $returnUrl);
            $transactionRequestBean->__set('txnDate', $curDate);
            $transactionRequestBean->__set('key', $merchantDetails['PAYNIMO_KEY']);
            $transactionRequestBean->__set('iv', $merchantDetails['PAYNIMO_IV']);        
            $transactionRequestBean->__set('custId', $customerId);
            $transactionRequestBean->__set('ITC', 'email:' . $customer->email);
            $transactionRequestBean->__set('email', $customer->email);
            $transactionRequestBean->__set('mobileNumber', $address->phone);
            $response = $transactionRequestBean->getTransactionToken();
            header('Location: ' . $response);
        }
    }


	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{ 
		
		$temp_data = array(
			'total' => $this->context->cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->module->getPathUri(), //keep for retro compat
			'checkout_label' => Configuration::get('paynimo_checkout_label'),
			'this_path_paynimo' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
		);
		$this->template_data = array_merge($this->template_data, $temp_data);
		$this->context->smarty->assign($this->template_data);
		$this->display_column_left = false;
		$this->display_column_right = false;
		if(is_not_17()){
			$this->setTemplate('validation_old.tpl');
            //$this->setTemplate('module:Paynimo/views/templates/front/validation_new.tpl');
		}
		else{
			$this->setTemplate('module:Paynimo/views/templates/front/validation_new.tpl');
		}
		parent::initContent();
	}
}
