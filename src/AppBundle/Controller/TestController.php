<?php

namespace AppBundle\Controller;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\VarDumper;

use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Transaction;

class TestController extends Controller
{

    /** @var  EntityManager */
    private $em;

    public function setContainer(ContainerInterface $container=NULL)
    {
        parent::setContainer($container);
        $this->em = $this->getDoctrine()->getManager();
    }


    public function indexAction()
    {
        return $this->render('test/index.html.twig', [
            'message' => 'Hello, Message'
        ]);
    }

    public function payAction()
    {
        $route = $this->generateUrl('pay_success');

        $returnUrl = 'http://' . $this->getParameter('app.baseUrl') . $this->generateUrl('pay_success');

        $paypal = $this->get('app.paypal');
        $items = [
            [
                'name' => 'Ground Coffee 40 oz',
                'price' => 10.5,
                'qty' => 2
            ],
            [
                'name' => 'Granola bars',
                'price' => 15.5,
                'qty' => 2
            ],
        ];

        try {
            $data = $paypal->pay(['items' => $items, 'returnUrl' => $returnUrl]);
            $approvalUrl = $data['url'];

            $payment = new \AppBundle\Entity\Payment();
            $payment->setStatus(\AppBundle\Entity\Payment::STATUS_NEW)->setName('Test')->setPrice($data['total'])
                ->setPaymentId($data['paymentId']);
            $this->em->persist($payment);
            $this->em->flush();


        } catch (\Exception $ex) {
            VarDumper::dump($ex);
            die("ERROR");
        }


        header("Location: " . $approvalUrl);
        exit;
    }

    public function paySuccessAction(Request $request)
    {
        $paymentId = $request->query->get('paymentId');
        $payerID = $request->query->get('PayerID');

//            $paymentId = $_GET['paymentId'];
            $paypal = $this->get('app.paypal');
            $payment = Payment::get($paymentId, $paypal->getApiContext());
            $tablePayment = $this->em->getRepository(\AppBundle\Entity\Payment::class)->findOneBy(['paymentId' => $paymentId]);

            $execution = new PaymentExecution();
            $execution->setPayerId($payerID);

            $total = $tablePayment->getPrice();
//            $total = 10.5;


            $transaction = new Transaction();
            $amount = new Amount();
//            $details = new Details();
            $amount->setCurrency('USD');
            $amount->setTotal($total);
            $transaction->setAmount($amount);

            $execution->addTransaction($transaction);




            $result = $payment->execute($execution, $paypal->getApiContext());


//            $var = ($result->getTransactions()[0])->getRelatedResources[0];
//
//            dump($var); die;

            $transactionId = $result->getTransactions()[0]->getRelatedResources()[0]->getSale()->getId();
            $tablePayment->setTransaction($transactionId);
            $this->em->flush();

            return new JsonResponse('success');
    }

    public function refundAction()
    {
        $id = 7;
        $payment = $this->em->getRepository(\AppBundle\Entity\Payment::class)->find($id);
        $paypal = $this->get('app.paypal');
        $paypal->refund($payment->getTransaction(), $payment->getPrice());


        return new JsonResponse('success');

    }
}