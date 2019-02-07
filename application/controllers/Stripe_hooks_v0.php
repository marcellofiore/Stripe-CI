<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Stripe_hooks extends CI_Controller {

    function __construct(){
        parent::__construct();

        $this->load->helper('url'); // load helper URL
		$this->load->library('stripe_lib'); // Load Stripe Library
    }

    public function index() {
        //show_404();
        // Retrieve the request's body and parse it as JSON:
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = $this->stripe_lib->verifyingSignaturesWebHook($payload, $sig_header);
        $event_json = null;
        if($event) {
            // setta risposta header
            http_response_code(200); // PHP 5.4 or greater
            // SWITCH IN BASE al tipo di notifica
            switch ($event["type"]) {
                case 'customer.subscription.created':
                // subscription created
                    $subID = $event['data']['object']['id'];
                    $subStart = $event['data']['object']['start'];
                    $status = $event['data']['object']['status']; // trialing // active

                    $trialStart = $event['data']['object']['trial_start']; // verficare se esista
                    $trialEnd = $event['data']['object']['trial_end']; // verificare se esista


                    //$this->sendEmail($event, "WebHook Stripe - Sub CREATED", $subID);
                    break;

                case 'customer.subscription.deleted':
                // subscription cancel
                    $subID = $event['data']['object']['id'];
                    $status = $event['data']['object']['status']; //"canceled"
                    $trialEnd = $event['data']['object']['"trial_end"']; // "trial_end": 1548937381,
                    
                    $this->sendEmail($event, "WebHook Stripe - Sub DELETED");

                    break;

                case 'customer.subscription.trial_will_end':
                // Il TRIAL STA PER SCADERE! se si vuole avvisare il cliente
                    $subID = $event['data']['object']['id'];
                    $billingCycle = $event['data']['object']['billing_cycle_anchor']; // Timestump
                    $statusSub = $event['data']['object']['status']; //"trialing" // active
                    $trial = false;
                    /*
                    "start": 1548335344,
                    "status": "trialing",
                    "tax_percent": null,
                    "trial_end": 1548594476,
                    "trial_start": 1548335276
                    */

                    // $this->sendEmail($event, "WebHook Stripe - Sub TRIAL END");
                    break;

                case 'customer.subscription.updated':
                // subscription Updated => viene chiamato anche prima del pagamento dell INVOICE PROSSIMA (dopo un ora circa viene addebitata la fattura)
                    $subID = $event['data']['object']['id']; // subscription ID
                    $startSubscr = $event['data']['object']['start'];
                    $statusSub = $event['data']['object']['status']; // active => Subscription states => trialing, active, past_due, canceled, unpaid             
                    // TRIAL STATUS
                    $trialStart = $event['data']['object']['trial_start'] ?? null;
                    $trialEnd = $event['data']['object']['trial_end'] ?? null;
                    if($trialStart && $trialEnd) {
                        // check Trial Status Subscription
                        if(time() > $trialEnd) {
                            $trial = false;
                        } else {
                            $trial = true;
                        }
                    }
                    
                    /*
                    "start": 1548340165, // original start
                    "status": "active",
                    "tax_percent": null,
                    "trial_end": 1548340344,
                    "trial_start": 1548340165
                    */

                    $this->sendEmail($event, "WebHook Stripe - Sub UPDATED");
                    break;

                case 'invoice.payment_succeeded':
                    // pagamento Invoice Eseguito correttamente
                    $subID = $event['data']['object']['subscription'];
                    $statusSub = $event['data']['object']['status']; // paid
                    $statusPaid = $event['data']['object']['paid']; // true / false
                    $total = $event['data']['object']['total']; // "total" pagato
                    $currency = ['data']['object']['currency'];
                    $paymentID = $event['data']['object']['id'];
                    $billingReason = ['data']['object']['billing_reason']; // "subscription_create"
                    $dateEvent = ['data']['object']['date'];
                    $invoiceNumber = ['data']['object']['number']; // "661000C-0002"
                    /*
                    "next_payment_attempt": null,
                    "number": "DB392CC-0001",
                    "paid": true,
                    "period_end": 1548332581,
                    "period_start": 1548332581,
                    "receipt_number": null,
                    "starting_balance": 0,
                    "statement_descriptor": null,
                    "status": "paid",
                    */

                    $this->sendEmail($event, "WebHook Stripe - PAYMENT INVOICE SUCCESS", $subID);
                    break;

                case 'invoice.payment_failed':
                    // pagamento Invoice fallito
                    $subID = $event['data']['object']['subscription']; // sub_iddsjka
                    $statusSub = $event['data']['object']['status']; // draft
                    $statusPaid = $event['data']['object']['paid']; // true / false
                    /*
                    "paid": false,
                    "period_end": 1548336899,
                    "period_start": 1548336899,
                    "receipt_number": null,
                    "starting_balance": 0,
                    "statement_descriptor": null,
                    "subscription": null,
                    "tax": 0,
                    "tax_percent": null,
                    "total": 0,
                    */

                    $this->sendEmail($event, "WebHook Stripe - PAYMENT INVOICE FAILED");
                    break;

                case 'charge.succeeded':
                    // caricamento denaro con successo => charged completed
                    //"balance_transaction": "txn_1Dw8r4C6L2NX2yba4KqHQi4v",
                    //"status": "succeeded",
                    // $this->sendEmail($event, "WebHook Stripe - CHARGE SUCCESS");
                    break;

                default:
                // CASO NON CONTROLLATO
                    // $this->sendEmail($event, "WebHook Stripe - NON CONTROLLATO");
                    break;
            }
        }
        
        
    }


    // HELPER => EMAIL SEND to Dev
    private function sendEmail($event, $oggetto, $subID = NULL) {
        // TEST EMAIL RICEZIONE DATI PAGAMENTO PAYPAL
        $this->load->library('email');
        //CONFIG EMAIL
        $config['protocol'] = 'mail';
        $config['smtp_host'] = 'smtp.1und1.de';
        $config['smtp_port'] = '465';
        $config['mailtype'] = 'html';
        $config['mailpath'] = '/usr/sbin/sendmail';
        $config['charset'] = 'utf-8';
        $config['wordwrap'] = TRUE;
        $this->email->initialize($config);

        // SEND EMAIL WITH POST DATA
        $this->email->from('abosupport@tuunes.co', 'WebHoock STRIPE Tuunes.co');
        $this->email->to('dev@whitepointprojects.com');
        //$oggetto = 'Subscription Paypal';
        $this->email->subject($oggetto);
        $html = ('
        <div>'.$subID.'</div>
        <br><br>
        <div style:"font-size: 16px">
        '.json_encode($event).'
        </div>
        <p>SENZA CODIFICA JSON</p>
        <div>'.var_export($event, true).'</div>
        ');
        $this->email->message($html);
        $this->email->send(); // SEND EMAIL
    }

}