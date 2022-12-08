<?php
// Merchant Key 90f20b45
define('OM_PROTOCOL', 'https');
define('OM_HOST', 'api.orange.com');
define('OM_CONTEXT_ACCESSTOKEN', 'oauth/v2/token');
define('OM_CONTEXT_REQUESTWP', 'orange-money-webpay/cm/v1/webpayment');
define('OM_URL_AT', OM_PROTOCOL . '://' . OM_HOST . '/' . OM_CONTEXT_ACCESSTOKEN);
define('OM_URL_WP', OM_PROTOCOL . '://' . OM_HOST . '/' . OM_CONTEXT_REQUESTWP);

class Ynote_Orangemoney_IndexController extends Mage_Core_Controller_Front_Action
{

    protected $_responseUcollect = null;
    protected $_invoice = null;
    protected $_invoiceFlag = false;
    protected $_order = null;
    protected $_post = null;
    protected $_refNumber = null;
    public $access_token_response = "";

    /**
     * When has error in treatment
     */
    public function failureAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Get checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

 public function redirectAction()
    {
        $helper = Mage::helper('orangemoney');

        $request_headers = array();
        $request_headers[] = 'Authorization: Basic '.Mage::getStoreConfig('payment/orangemoney/access_token');

        $this->_postAK = array(
            "grant_type" => "client_credentials"
        );

        $currency="OUV";
        if(Mage::getStoreConfig('payment/orangemoney/develop_mode')==0){
            $currency="XAF";
        }

         Mage::log('Currency----'.$currency,null,'orangemoney.log');

	Mage::log('Access Token----'.Mage::getStoreConfig('payment/orangemoney/access_token'),null,'orangemoney.log');

	Mage::log('Merchant Key----'.Mage::getStoreConfig('payment/orangemoney/merchant_id'),null,'orangemoney.log');

        $price = $helper->_getAmount();
        $this->_post= array(
          "merchant_key" => Mage::getStoreConfig('payment/orangemoney/merchant_id'),
          "currency" => $currency,
          "order_id" => $helper->_getOrderId(),
          "amount" => $helper->_getAmount(),
          "return_url" => $helper->_getNormalReturnUrl(),
          "cancel_url" => $helper->_getCancelReturnUrl(),
          "notif_url" => $helper->_getIpnReturnUrl(),
          "lang" => "fr",
          "reference" => "VisionConfort",
        );

        /*** Construction de la première requête de gestion des Access Token */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, OM_URL_AT);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  "grant_type=client_credentials");
         curl_setopt($ch, CURLOPT_HTTPHEADER,  $request_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $response = explode(',',$response);
        $access_token = explode(':',$response[1]);
        $returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->access_token_response=trim(str_replace("\"","",$access_token[1]));

        Mage::log('----------Access Token------------',null,'orangemoney.log');
        Mage::log('ReturnCode: '.$returnCode,null,'orangemoney.log');
        Mage::log('Response: '.$response,null,'orangemoney.log');
        Mage::log('Access Token: '.$this->access_token_response,null,'orangemoney.log');

        $chpay = curl_init();

        $data_string = json_encode($this->_post);
        curl_setopt($chpay, CURLOPT_URL, OM_URL_WP);
        curl_setopt($chpay, CURLOPT_POST, true);
        curl_setopt($chpay, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($chpay, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($chpay, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($chpay, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->access_token_response,
            'Accept: application/json'));
        curl_setopt($chpay, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($chpay);
        $response_decode=json_decode($response);
        if(isset($response_decode->code)){
            $returnCode = $response_decode->code;
        }else{
            $returnCode = $response_decode->status;
        }

        /******* Création d'une commande tampon pour montrer le resultat
                 Orange Money commande qui reste en attente*******/
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($helper->_getOrderId());
        
        //Check if there are no errors ie httpresponse == 200 -OK
        if ($returnCode == 201) {
            //This line declares the Link to pay for this transaction
            $paylink = $response_decode->payment_url;
            $notif_token = $response_decode->notif_token;
            $notification = Mage::getModel('orangemoney/notification')
            ->setData(
                array(
                    'id_order'=>$helper->_getOrderId(),
                    'id_notification'=> $notif_token,
                    'price_order'=>$price,
                    'id_accesstoken'=>$this->access_token_response
                )
            );
            $notification->save();
            Mage::log('Payment URL: '.$paylink,null,'orangemoney.log');
            $order->addStatusHistoryComment(
                $this->__('Paiement envoyé a la plateforme OrangeMoney.
                    Access Token: '.$this->access_token_response.'
                    Notif Token: '.$notif_token.'
                    Payment URL: '.$paylink))
                ->save();
            echo '<script type="text/javascript">';
            echo 'document.location.href="'.$paylink.'";';
            echo '</script>';
            echo 'Redirection vers la <a href="'.$paylink.'">plateforme de paiement OrangeMoney /</a>';
            //return $paylink;
        } else {
            //Get return Error Code, If there was an error during call
            //
            switch($returnCode){
                default:
                    $order->addStatusHistoryComment(
                    $this->__('Erreur sur plateform Orange Money. Access Token:'.$this->access_token_response.' // Error Code : '.$returnCode))
                    ->save();
                //Declare the Request Error
                    Mage::log('Payment Error : '.$returnCode,null,'orangemoney.log');
                    Mage::log('Payment Message : '.$response_decode->message,null,'orangemoney.log');
                    $result = 'HTTP ERROR -> ' . $returnCode.'<br/>';
                    $result.= 'Message : '.$response_decode->message;

                    break;
            }
            echo $result;

        }
    }

    public function ipnAction()
    {

        $helper = Mage::helper('orangemoney');
        $params = $this->getRequest()->getParams();

        $request = file_get_contents('php://input');
        $request = json_decode($request);
        

        $status = $request->status;
        $notif_token = $request->notif_token;
        $txnid = $request->txnid;
        $refNo = null;
        $messages = array();
        
        Mage::log('----------IPN------------',null,'orangemoney-ipn.log');
        Mage::log('Status: '.$status,null,'orangemoney-ipn.log');
        Mage::log('Notif Token: '.$notif_token,null,'orangemoney-ipn.log');
        Mage::log('TXnid: '.$txnid,null,'orangemoney-ipn.log');
        Mage::log('Request: ',null,'orangemoney-ipn.log');
        Mage::log($params,null,'orangemoney-ipn.log');



        switch ($status) {
            case 'SUCCESS':
                // On fait une seconde vérification asynchrone pour être sur que quelqu'un a pas simplement renvoyé la bonne URL avec son numéro de commande...

                $notification = Mage::getModel('orangemoney/notification')
                        ->getCollection()
                        ->addFieldToFilter("id_notification",$notif_token);
                foreach($notification as $item){
                    $order = Mage::getModel('sales/order');
                    $this->_refNumber = $item->getIdOrder();
                    $order->loadByIncrementId($this->_refNumber);
                    $this->_order=$order;
                    Mage::log('Notification Token - IPN : '.$notif_token,null,'orangemoney-ipn.log');

                    //$order->addStatusHistoryComment($this->__('Paiement valide par la plateforme OM.<br/> Reference Transaction:'.$notif_token))->save();
                    $order->addStatusHistoryComment($this->__('Paiement valide par la plateforme OM.<br/>NumTransaction : '.$txnid.'<br/>Reference Transaction:'.$notif_token))->save();
		    $this->getCheckoutSession()->getQuote()->setIsActive(false)->save();
                    // Set redirect URL
                    $response['redirect_url'] = 'checkout/onepage/success';

                    // Update payment
                    $this->_processOrderPayment($notif_token);

                    // Create invoice
                    if ($this->_invoiceFlag) {
                        $invoiceId = $this->_processInvoice();
                        $messages[] = $helper->__('Invoice #%s created', $invoiceId);
                    }

                    // Add messages to order history
                    foreach ($messages as $message) {
                        $this->_order->addStatusHistoryComment($message);
                    }

                    // Save order
                    $this->_order->save();

                    // Send order confirmation email
                    if (!$this->_order->getEmailSent() && $this->_order->getCanSendNewEmailFlag()) {
                        try {
                            if (method_exists($this->_order, 'queueNewOrderEmail')) {
                            $this->_order->queueNewOrderEmail();
                            } else {
                            $this->_order->sendNewOrderEmail();
                            }
                        } catch (Exception $e) {
                          Mage::logException($e);
                        }
                        }
                        // Send invoice email
                        if ($this->_invoiceFlag) {
                            try {
                                $this->_invoice->sendEmail();
                            } catch (Exception $e) {
                                Mage::logException($e);
                            }
                        }

                }

            break;
        default:
            // Log error
            $errorMessage = $this->__('Paiement non valide par OM :  %s.<br />Reference Transaction : %s', $status, $notif_token);
            // Add error on order message, cancel order and reorder
            if ($order->getId()) {
                if ($order->canCancel()) {
                    try {
                        $order->registerCancellation($errorMessage)->save();
                    } catch (Mage_Core_Exception $e) {
                        Mage::logException($e);
                    } catch (Exception $e) {
                        Mage::logException($e);
                        $errorMessage .= '<br/><br/>';
                        $errorMessage .= $this->__('The order has not been cancelled.'). ' : ' . $e->getMessage();
                        $order->addStatusHistoryComment($errorMessage)->save();
                    }
                } else {
                    $errorMessage .= '<br/><br/>';
                    $errorMessage .= $this->__('The order was already cancelled.');
                    $order->addStatusHistoryComment($errorMessage)->save();
                }

                // Refill cart
                Mage::helper('orangemoney')->reorder($refNo);

            }

            // Set redirect URL
        

        }
        $this->_redirect($response['redirect_url'], array('_secure' => true));
    }

    /**
     * Update order payment
     */
    protected function _processOrderPayment($transactionId)
    {
        try {
            // Set transaction
            $payment = $this->_order->getPayment();
            $payment->setTransactionId($transactionId);
            $payment->save();
            // Add authorization transaction
            $this->_invoiceFlag = true;
            $this->_order->save();
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::app()->getResponse()
                    ->setHeader('HTTP/1.1', '503 Service Unavailable')
                    ->sendResponse();
            exit;
        }
    }

    /**
     * Create invoice
     *
     * @return string
     */
    protected function _processInvoice()
    {
        try {
            $this->_invoice = $this->_order->prepareInvoice();
            $this->_invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $this->_invoice->register();

            $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($this->_invoice)->addObject($this->_invoice->getOrder())
                    ->save();
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::app()->getResponse()
                    ->setHeader('HTTP/1.1', '503 Service Unavailable')
                    ->sendResponse();
            exit;
        }

        return $this->_invoice->getIncrementId();
    }


}


