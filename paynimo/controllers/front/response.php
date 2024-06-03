<?php
require_once __DIR__ . '/../../lib/TransactionResponseBean.php';

class PaynimoresponseModuleFrontController extends ModuleFrontController
{
	 public function postProcess()
    {  
        if ($_POST) {
            $response = $_POST;
            $merchantDetails = $this->module->getConfigFormValues();
            if (is_array($response)) {
                $str = $response['msg'];
            } else if (is_string($response) && strstr($response, 'msg=')) {
                $outputStr = str_replace('msg=', '', $response);
                $outputArr = explode('&', $outputStr);
                $str = $outputArr[0];
            } else {
                $str = $response;
            }

            $transactionResponseBean = new TransactionResponseBean();

            $transactionResponseBean->setResponsePayload($str);
            $transactionResponseBean->__set('key', $merchantDetails['PAYNIMO_KEY']);
            $transactionResponseBean->__set('iv', $merchantDetails['PAYNIMO_IV']);

            $response = $transactionResponseBean->getResponsePayload();

            $responseDetails = explode('|',$response);
        	$responseData = array();
	        foreach($responseDetails as $responseDetailsData)
	        {
	            $data = explode("=",$responseDetailsData);
	            $responseData[$data[0]] = $data[1];
	        }
	        
    
            $status = $responseData['txn_status'];

            $cart = Context::getContext()->cart;
            $customer = new Customer((int)$cart->id_customer);

            $total = $this->context->cart->getOrderTotal(true, Cart::BOTH);
            $extra_vars = Array(); 
			$extra_vars['transaction_id'] = $responseData['tpsl_txn_id'];


            if (Tools::usingSecureMode()) {
                $domain = Tools::getShopDomainSsl(true);
            } else {
                $domain = Tools::getShopDomain(true);
            }

            if ($status == '300') {
                $this->addOrderHistory($_GET['currentOrderId'], (int)Configuration::get('PS_OS_PAYMENT'));
                $this->module->validateOrder($this->context->cart->id , _PS_OS_PAYMENT_, $total, Configuration::get('Paynimo_checkout_label'), NULL, $extra_vars, NULL, false, $customer->secure_key, NULL);
                
                $orderId = Order::getOrderByCartId((int)$this->context->cart->id);
                $order = new Order((int)$orderId);
                $orderReference = $order->reference;
  
                //Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-detail&id_order='.(int)$this->module->currentOrder);

                if($_GET['isGuest'] == '1'){
                    $url = $domain . __PS_BASE_URI__ .'/index.php?controller=guest-tracking&order_reference='.$orderReference.'&email='.$_GET['guestEmail'];
                }else {
                    $url = $domain . __PS_BASE_URI__ . '/index.php?controller=order-detail&id_order='.(int)$this->module->currentOrder;
                    // $url = $this->context->link->getPageLink('history', true, null, false);
                }

            } else {
                $this->addOrderHistory($_GET['currentOrderId'], (int)Configuration::get('PS_OS_ERROR'));
					  // $cart_id = $this->context->cart->id;
					   $this->module->validateOrder($this->context->cart->id , _PS_OS_ERROR_, $total, Configuration::get('Paynimo_checkout_label'), NULL, $extra_vars, NULL, false, $customer->secure_key, NULL);
					 
					  // $this->context->cart = new Cart($cart_id);
					  // $duplicated_cart = $this->context->cart->duplicate();
					  // $this->context->cart = $duplicated_cart['cart'];
					  // $this->context->cookie->id_cart = (int)$this->context->cart->id;					  
					 // Tools::redirectLink($this->context->link->getPageLink('order',true));

					  $orderId = Order::getOrderByCartId((int)$this->context->cart->id);
                	  $order = new Order((int)$orderId);
                	  $orderReference = $order->reference;

                if($_GET['isGuest'] == 1){
                    $url = $domain . __PS_BASE_URI__ .'/index.php?controller=guest-tracking&order_reference='.$orderReference.'&email='.$_GET['guestEmail'];
                }else {
                    $url = $domain . __PS_BASE_URI__ . '/index.php?controller=order-detail&id_order='.(int)$this->module->currentOrder;
                }
                
            }
            Tools::redirectLink($url);
        }
    }

    /**
     * Adds order history to order_history table
     *
     * @param $currentOrderId
     * @param $orderStateId
     */
    public function addOrderHistory($orderId, $orderStateId)
    {
        $sql = "UPDATE `" . _DB_PREFIX_ . "order_history`
                SET `id_order_state` = '" . $orderStateId . "'
                WHERE id_order = " . $orderId;

        Db::getInstance()->execute($sql);
    }
}
