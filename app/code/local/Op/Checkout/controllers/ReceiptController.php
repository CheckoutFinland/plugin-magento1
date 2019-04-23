<?php
class Op_Checkout_ReceiptController extends Mage_Core_Controller_Front_Action
{
    protected $checkoutPayment;
    protected $orderNo;
    protected $status;
    protected $signature;

    public function _construct()
    {
        $this->checkoutPayment = Mage::getModel('opcheckout/Payment');
    }

    public function indexAction()
    {
        Mage::log("recept index", null, 'op_checkout.log', true);
        $this->orderNo = $this->getRequest()->getParam('checkout-reference');
        $this->status = $this->getRequest()->getParam('checkout-status');
        $this->signature = $this->getRequest()->getParam('signature');
        $params = $this->getRequest()->getParams();

        $rcn = "recepit_processing_".$this->orderNo;
        $processingOrderCache = Mage::app()->getCache()->load($rcn);

        if($processingOrderCache) {
            sleep(1);
            Mage::app()->getCache()->remove($rcn);
        } else {
            Mage::app()->getCache()->save('processing', $rcn);
        }
        try {
            $validate = $this->checkoutPayment->verifyPayment($this->signature, $this->status, $params);
        } catch (Exception $exception)
        {
            Mage::log($exception->getMessage(), null, 'op_checkout.log');
            $validate = false;
        }

        //var_dump($validate); exit;
        if($validate)
        {
            $this->successAction($params);
        } else {
            $this->failureAction();
        }
    }

    public function successAction($params)
    {
        $invoice = false;
        try {
            $invoice = $this->checkoutPayment->validatePayment($params);
        } catch (Exception $exception) {
            Mage::log($exception->getMessage(), null, 'op_checkout.log');
        }


        Mage::log(get_class($invoice), null, 'op_checkout.log', true);


        if ($invoice instanceof Mage_Sales_Model_Order_Invoice || $invoice == "recovery") {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/success'))->sendResponse();
        } else {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/failure'))->sendResponse();
        }
        //exit;*/
    }

    public function failureAction()
    {
        Mage::getSingleton('checkout/session')->addError(Mage::helper('opcheckout')->__('Payment failed.'));

        $order = Mage::getModel("sales/order")->loadByIncrementId($this->orderNo);
        if($order->getId() && !in_array($order->getStatus(), ['closed', 'canceled'], true))
        {
            Mage::getModel('opcheckout/opcheckout')->cancelOrderAndActivateQuote($order);
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'))->sendResponse();
        } else {
            Mage::app()->getResponse()->setRedirect(Mage::getUrl(''))->sendResponse();
        }
        exit;

    }

    public function confirmAction()
    {
        echo 'confirm';
        exit;


    }

}
