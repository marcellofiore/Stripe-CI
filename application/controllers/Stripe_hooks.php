<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * TEST Stripe SDK Hooks
 * Power by Marcello Fiore
 * 24/01/2019
 * required: Library => Stripe_lib power by Marcello Fiore
 * required: configuration: Config => stripe.php with Private Key And Public Key
 * required: hook Private Key
 */

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
        if(!$payload) {
            echo "no PayLoad";
            exit;
        }
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        if(!$sig_header) {
            echo "no Signature";
            exit;
        }
        //$event = $this->stripe_lib->verifyingSignaturesWebHook($payload, $sig_header);
        //$this->sendEmailTEST($payload, "Oggetto Stripe 2"); // IMPORTANTE PER LA SICUREZZA
        // il payload non è criptato in questo caso... chiedere anche a Jan come mai se lui ha delle configurazioni...
        $event = json_decode($payload, true); // da eliminare nel caso event è encriptato

        $version_api = (string)$event['api_version']; //"api_version":"2015-10-16"
        if($version_api != "2018-11-08") {
            exit();
        }
        
        //$this->sendEmail($event, "WebHook Stripe Generale da controllare");

        $event_json = null;
        if($event) { // $event => se nel caso questo sia encryptato
            // SWITCH IN BASE al tipo di notifica
            http_response_code(200); // PHP 5.4 or greater
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
                    //$trialEnd = $event['data']['object']['trial_end']; // "trial_end": 1548937381,
                    
                    //$plan_interval = $event['data']['object']['plan']['interval']; // "interval": "month",
                    //$interval_count = $event['data']['object']['plan']['interval_count']; // interval_count : 1

                    //$idPlan = $event['data']['object']['plan']['nickname']; // "nickname": "Stripe USD PLAN" // => PER POTER SAPERE QUALE PRODOTTO SI STA UTILIZZANDO
                    /*
                    "id": "plan_EOx7vC7R2l8YiJ",
                    "object": "plan",
                    "active": true,
                    "aggregate_usage": null,
                    "amount": 999,
                    "billing_scheme": "per_unit",
                    "created": 1548340164,
                    "currency": "usd",
                    "interval": "month",
                    "interval_count": 1,
                    "livemode": false,
                    "metadata": [],
                    "nickname": "Stripe USD PLAN",
                    "product": "prod_EOx7MK1cpiWJpU",
                    "tiers": null,
                    "tiers_mode": null,
                    "transform_usage": null,
                    "trial_period_days": null,
                    "usage_type": "licensed"
                    */
                    // SETTA I DATI NEL DATABASE
                    $is_inDB = $this->checkSubscriptioninDb($subID);
                    if($is_inDB == true) { // Subscripotion Presente IN DB OK! Aggiungi il pagamento modificando la subscription già presente
                        $data_sub = array(
                            'notification_type' => 'customer.subscription.deleted',
                            'subscr_status' => $status,
                            'last_update' => date("Y-m-d H:i:s"),
                            'current_period_start' => $this->convertDate( trim($event['data']['object']['period_start']) ), 
                            'current_period_end' => $this->convertDate( trim($event['data']['object']['period_end']) )
                        );
                        //update data Subscription on DB
                        $this->model->edit('stripe_subscriptions', $data_sub, 'subscr_id', $subID);

                        // Update User Subscription
                        $data_user = array(
                            'transation_id_subscr' => "",
                            'account_type' => "basic"
                        );
                        $this->model->edit('users', $data_user, 'transation_id_subscr', $subID);
                    }

                    // INVIA MAIL CON I DATI
                    //$this->sendEmail($event, "WebHook Stripe - Sub DELETED");
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
                    //$startSubscr = $event['data']['object']['start'];
                    $statusSub = $event['data']['object']['status']; // active => Subscription states => trialing, active, past_due, canceled, unpaid             
                    // TRIAL STATUS
                    $trialStart = $event['data']['object']['trial_start'] ?? null;
                    $trialEnd = $event['data']['object']['trial_end'] ?? null;
                    $trial = null;
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
                    // AGGIORNA DATI SABSCRIPTION
                    // SETTA I DATI NEL DATABASE
                    $is_inDB = $this->checkSubscriptioninDb($subID);
                    if(($is_inDB == true) && ($subID != "") && ($subID != " ")) { // Subscripotion Presente IN DB OK! Aggiungi il pagamento modificando la subscription già presente
                        $data_sub = array(
                            'notification_type' => 'customer.subscription.updated',
                            'subscr_status' => $statusSub,
                            'last_update' => date("Y-m-d H:i:s"),
                            'isTrial' => $trial
                        );
                        //update data Subscription on DB
                        $this->model->edit('stripe_subscriptions', $data_sub, 'subscr_id', $subID);
                    }

                    // INVIA MAIL CON DATI
                    //$this->sendEmail($event, "WebHook Stripe - Sub UPDATED");
                    break;

                case 'invoice.payment_succeeded':
                    // pagamento Invoice Eseguito correttamente
                    $subID = trim($event['data']['object']['subscription']);
                    $statusSub = trim($event['data']['object']['status']); // status Subscription =>  paid
                    $statusPaid = trim($event['data']['object']['paid']); // true / false
                    $total = trim($event['data']['object']['total']); // "total" pagato
                    $currency = trim($event['data']['object']['currency']); // currency code
                    //$paymentID = $event['data']['object']['id'];
                    $billingReason = trim($event['data']['object']['billing_reason']); // "subscription_create"
                    $dateEvent = trim($event['data']['object']['date']);
                    $invoiceNumber = trim($event['data']['object']['number']); // "661000C-0002"
                    $subNickName = trim($event['data']['object']['lines']['data'][0]['plan']['nickname']) ?? NULL; // GOLD PLAN ETC....

                    // SETTA I DATI NEL DATABASE
                    $is_inDB = $this->checkSubscriptioninDb($subID);
                    if(($is_inDB == true) && ($subID != "") && ($subID != " ")) { // Subscripotion Presente IN DB OK! Aggiungi il pagamento modificando la subscription già presente
                        $data_sub = array(
                            'notification_type' => 'invoice.payment_succeeded',
                            'subscr_status' => $statusSub,
                            'status_paid' => $statusPaid,
                            'total_paid' => $total,
                            'currency_code' => $currency,
                            'invoice_number' => $invoiceNumber,
                            'last_update' => date("Y-m-d H:i:s"),
                            'current_period_start' => $this->convertDate( trim($event['data']['object']['period_start']) ), 
                            'current_period_end' => $this->convertDate( trim($event['data']['object']['period_end']) )
                        );
                        //update data Subscription on DB
                        $this->model->edit('stripe_subscriptions', $data_sub, 'subscr_id', $subID);

                        // update Coins user Alla subscription
                        if(($statusPaid == true) && ($subID != "") && ($subID != " ")) {
                            $num_credit = 1; // default credits quando l'utente è in freetrial
                            if($total != 0) {
                                // check num crediti da inserire all'utente quando il free trial è completato
                                $num_credit = $this->stripe_lib->returnCreditFromPlan($subNickName);
                            }
                            $data_user = array(
                                'credits' => $num_credit,
                                'date_shop' => (string)date('d/m/Y') //$this->createDateShop( trim($event['data']['object']['period_start']) )
                            );
                            $this->model->edit('users', $data_user, 'transation_id_subscr', $subID);
                            // se il totale pagato è diverso da 0 quindi non è il primo invoice che genera Stripe
                            if($total != 0) {
                                // per generare le invoice bisogna cambiare un po le cose in questo caso perchè non ho tutte queste informazioni dell'utente (firstname - lastname - email - telefono - country)
                                // $this->createInvoiceAbo($data_sub);

                                $invoice_pdf_link = $event['data']['object']['invoice_pdf']; // read url PDF invoice stripe
                                // invia per email l'invoice Stripe a Jasmin
                                //$this->sendEmailInvoice($invoice_pdf_link);
                            }
                            
                        } 
                    }
                    // SEND DATA PER EMAIL To develepoer
                    //$this->sendEmail($event, "WebHook Stripe - PAYMENT INVOICE SUCCESS", $subID);
                    break;

                case 'invoice.payment_failed':
                    // pagamento Invoice fallito
                    $subID = $event['data']['object']['subscription']; // sub_iddsjka
                    $statusSub = $event['data']['object']['status']; // draft
                    $statusPaid = $event['data']['object']['paid']; // true / false
                    $total = trim($event['data']['object']['total']); // "total" pagato
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

                    $is_inDB = $this->checkSubscriptioninDb($subID);
                    if(($is_inDB == true) && ($subID != "") && ($subID != " ")) {
                        $data_sub = array(
                            'notification_type' => 'invoice.payment_failed',
                            'subscr_status' => $statusSub,
                            'status_paid' => $statusPaid,
                            'total_paid' => $total,
                            'last_update' => date("Y-m-d H:i:s"),
                            'current_period_start' => $this->convertDate( trim($event['data']['object']['period_start']) ), 
                            'current_period_end' => $this->convertDate( trim($event['data']['object']['period_end']) )
                        );
                        //update data Subscription on DB
                        $this->model->edit('stripe_subscriptions', $data_sub, 'subscr_id', $subID);
            // Disattiva per sicurezza la Subscription????
            // IN CASO RICHIAMARE LA LIBRERIA Stripe ed effettuare la disattivazione della subscription immediatamente
                    }

                    //$this->sendEmail($event, "WebHook Stripe - PAYMENT INVOICE FAILED");
                    break;

                case 'charge.succeeded':
                    // caricamento denaro con successo => charged completed
                    //"balance_transaction": "txn_1Dw8r4C6L2NX2yba4KqHQi4v",
                    //"status": "succeeded",
                    // $this->sendEmail($event, "WebHook Stripe - CHARGE SUCCESS");
                    break;

                default:
                // CASO NON CONTROLLATO
                    $this->sendEmail($event, "WebHook Stripe - NON CONTROLLATO!");
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
        ');
        $this->email->message($html);
        $this->email->send(); // SEND EMAIL
    }

    private function sendEmailTEST($event, $oggetto) {
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
        <div>'.$event.'</div>
        ');
        $this->email->message($html);
        $this->email->send(); // SEND EMAIL
    }

    /* HELPER CHECK SUBSCRIPTION IN DB */
    private function checkSubscriptioninDb($id) {
        $n = $this->model->count_token('stripe_subscriptions', 'subscr_id', $id);
        if($n > 0) {
            return true;
        } else {
            return false;
        }
    }
    /* HELPER CONVERT DATE */
    private function convertDate($date) {
		// CONVERTI FORMATO DATA
        //$data_ricevuta = strtotime($date); // convert string to timestamp
        $data_convertita = date("Y-m-d H:i:s", (int)$date); // convert timestamp to Server Format
        //$data_ricevuta = new DateTime(strtotime($date));
		//$data_convertita = $data_ricevuta->format('Y-m-d H:i:s');
		return $data_convertita;
    }
    private function createDateShop($date) {
        //$data_ricevuta = strtotime($data); // convert string to timestamp
        $data_convertita = date("d/m/Y", $date); // convert timestamp to Server Format 31/08/2018
        return $data_convertita;
    }

    /*** HELPER FUNCTION FOR INVOICE ***/
    //(NON IN USO AL MOMENTO)
    private function createInvoiceAbo($notifica) {
        // read invoice ID from DB
        $id = $this->model->read_1('invoice_abo_id', 'id_inv', 1);
        if($id) {
            $up_invoice = array(
                'num' => (int)($id->num + 5)
            );
            //update number invoice in DB
            $this->model->edit('invoice_abo_id', $up_invoice, 'id_inv', 1); // aggiorna con il prossimo invoice da usare
            $inv_id = "SUB-".$id->num;

            $this->sendEmailInvoice($notifica, $inv_id); // invia mail con invoice
        }
    }
    // (USO PER INVIARE IL LINK PDF DELL'INVOICE GENERATO DA STRIPE)
    private function sendEmailInvoice($link_pdf_invoice) {
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
        $this->email->from('support@tuunes.co', 'Tuunes Support');
        $this->email->to('payments@tuunes.co'); // da cambiare con la vera mail => payments@tuunes.co
        $this->email->subject('Invoice for Stripe Abo');
        $html = ('
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Tuunes</title>

            <!-- Google Fonts -->
            <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700" rel="stylesheet">
            <!-- FontAwesome -->
            <link rel="stylesheet" href="'.site_url().'/resource/css/font-awesome.min.css">
            <style>
                body {
                    font-family: "Open Sans", sans-serif;
                    color: #3F4B56;
                    text-align: center;
                    line-height: normal;
                    font-weight: 400;
                    font-size: 18px;
                }
                .img-resposnsive {
                    display: block;
                    position: relative;
                    width: 180px;
                    height: auto;
                    margin-left: auto;
                    margin-right: auto;
                    margin-top: 30px;
                }
                div.primo {
                    display: block;
                    position: relative;
                    margin: 30px 0px;
                }
                h3 {
                    display: block;
                    position: relative;
                    color: #4EC3B1;
                    font-size: 35px;
                    margin: 0px;
                    font-weight: 700;
                }
                h4 {
                    display: block;
                    position: relative;
                    font-size: 20px;
                    margin: 0px;
                    font-weight: 400;
                }
                a.btn {
                    display: inline-block;
                    position: relative;
                    text-decoration: none;
                    color: white;
                    background-color: #4EC3B1;
                    padding: 12px 35px;
                    border-radius: 3px;
                    -webkit-border-radius: 3px;
                    -moz-border-radius: 3px;
                    font-weight: 700;
                    font-size: 18px;
                }
                div.d-pay {
                    display: block;
                    position: relative;
                    margin: 40px 0px;
                }
                div.d-pay p {
                    display: block;
                    margin: 0px;
                    line-height: normal;
                }
                div.c-pay {
                    display: block;
                    position: relative;
                    margin: 35px 0px;
                }
                div.c-pay p {
                    display: block;
                    position: relative;
                    margin: 0px;
                    line-height: normal;
                }
                div.con-pay {
                    display: block;
                    position: relative;
                    margin: 50px 0px;
                }
                div.con-pay p {
                    display: block;
                    position: relative;
                    margin: 0px;
                }
                div.footer {
                    display: block;
                    position: relative;
                    width: 90%;
                    margin: 0px auto;
                    border-top: 1px solid #EAEEF1;
                }
                div.footer p {
                    display: block;
                    position: relative;
                    font-size: 16px;
                }
                a {
                    color: #4EC3B1;
                    text-decoration: none;
                }
                p.price {
                    font-size: 25px;
                }
            </style>
        </head>

        <body>
            <a href="'.$link_pdf_invoice.'">INVOICE STRIPE PDF</a>
        </body>
        </html>
        ');

        $this->email->message($html);
        $this->email->send(); //INVIA MAIL AD ADMIN PAYMENTS

    }

}