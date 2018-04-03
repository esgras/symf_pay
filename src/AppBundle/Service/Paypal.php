<?php

namespace AppBundle\Service;

use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use Symfony\Component\Routing\RouterInterface;
use PayPal\Api\Capture;
use PayPal\Api\Refund;
use PayPal\Api\RefundRequest;

class Paypal
{
    private $apiContext;
    private $paymentMethod = 'paypal';
    private $baseUrl;


    public function __construct(string $clientId, string $clientSecret, string $baseUrl)
    {
        $this->baseUrl = 'http://' . $baseUrl;

        $this->apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $clientId,     // ClientID
                $clientSecret  // ClientSecret
            )
        );
    }

    public function getApiContext()
    {
        return $this->apiContext;
    }

    public function pay($data)
    {
        $intent = 'sale';

        $payer = new Payer();
        $payer->setPaymentMethod($this->paymentMethod);

        $currency = 'USD';
        $description = "Payment description";
        $returnUrl = $data['returnUrl'];
        $cancelUrl = $this->baseUrl . "/ExecutePayment.php?success=true";

        $total = 0;

        $items = [];
        foreach ($data['items'] as $elem) {
            $price = number_format($elem['price'], 2);
            $item = new Item();
            $item->setName($elem['name'])
                ->setCurrency($currency)
                ->setQuantity($elem['qty'])
                ->setSku("123123")
                ->setPrice($price);
            $items[] = $item;
            $total += $elem['qty'] * $price;
        }

        

        $itemList = new ItemList();
        $itemList->setItems($items);
        $amount = new Amount();
        $amount->setCurrency($currency)
            ->setTotal($total);

        $invoiceNumber = uniqid();

        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription($description)
            ->setInvoiceNumber($invoiceNumber);


        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($returnUrl)
            ->setCancelUrl($cancelUrl);



        $payment = new Payment();
        $payment->setIntent($intent)
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));



        $request = clone $payment;


        $payment->create($this->apiContext);

//            ResultPrinter::printError("Created Payment Using PayPal. Please visit the URL to Approve.", "Payment", null, $request, $ex);


        return ['url' => $payment->getApprovalLink(), 'total' => $total, 'invoiceNumber' => $invoiceNumber, 'paymentId' => $payment->getId()];


    }

    public function refund($transactionId, $total)
    {

        $amount = new \PayPal\Api\Amount();
        $amount->setCurrency("USD")
            ->setTotal($total);
        $refundRequest = new RefundRequest();
        $refundRequest->setAmount($amount);



        try {
            $capture = Capture::get($transactionId, $this->apiContext);
            $captureRefund = $capture->refundCapturedPayment($refundRequest,  $this->apiContext);

        } catch (\Exception $ex) {

        }

//        dump($refundRequest);
//        dump($captureRefund);
//        die;
//        ResultPrinter::printResult("Refund Capture", "Capture", $captureRefund->getId(), $refundRequest, $captureRefund);
    }


}