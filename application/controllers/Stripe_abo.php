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

class Stripe_abo extends CI_Controller {

    protected $data_user;

    function __construct(){
        parent::__construct();
        // JWT Token Library
        $this->load->library('jwt_lib');
        // Verifica token Login
        if( isset($_COOKIE['log']) ) { //verifica che esiste il cookie //!isset($_COOKIE['log']) => se non esiste
            if( $this->jwt_lib->validate($_COOKIE['log']) != true ) { // valida il token
                //echo "Token settato nei cookie";
                //$verifica = $this->jwt_lib->validate($_COOKIE['log']); //valida il token ed aggiornalo nei cookie //
                redirect('login');
            } else {
                $this->data_user = $this->jwt_lib->decode($_COOKIE['log']); // salva data User
            }
        } else {
            redirect('login');
        }

        // Load URL Helper
        $this->load->helper('url');
        // Load Stripe Library
		$this->load->library('stripe_lib');
    }

    public function index() {
        show_404();
    }

    public function connectStripeAbo() {
        $data = $this->input->post(); // get Data Post from FORM
        // check User Logged se si è loggati
        if($this->isUserLogged() == false) {
            redirect('login');
            exit;
        }
        if(!isset($data['stripeToken'])) {
            echo "<p style='color:red'>Stripe PAYMENT TOKEN NOT SET!</p>";
            //redirect('login'); // rediretta alla pagina account per sicurezza
            exit;
        }

        // creare uno switch dei vari piani
        $nameCustomer = $data['customerName'] ;
        $emailUser = $data['customerMail'];
        // bug in caso no email from Facebook
        if(($emailUser == "undefined") || ($emailUser == "") || ($emailUser == " ")) {
            $emailUser = NULL;
        }
        // IN CASO non ci sia un nome al customer allora setta come customer l'id Tuunes Account
        if(($nameCustomer == NULL) || ($nameCustomer == "") || ($nameCustomer == " ")) {
            $nameCustomer = "TuunesAccount: ".$this->data_user->data->userId;
        }

        $idPlan = json_decode(GOLD_PLAN);

        switch($_GET['prod']) {
            case '1':
                $piano = json_decode(GOLD_PLAN);
                break;
            case '2':
                $piano = json_decode(MEDIUM_PLAN);
                break;
            default:
                redirect('start-your-freetrial/step-3');
                break;
        }

        // read data from USER e controlla come è la situazione dell'abbonamento
        $user_data = $this->model->read_1('users', 'cod_user', $this->data_user->data->userId);

        // Check Free Trial Availabe?
        $freetrialAvailable = $this->userFreeTrialAvailable($user_data);
        //print_r($freetrialAvailable);
        if($freetrialAvailable == 0) {
            // echo "FREETRIAL NON DISPONIBILE";
            //$trialEnd = $this->AddTime('+3 days'); // OR +3 minutes => Add 7 Days For Trial (al momento Ho impostato 1 ora per poter testare cosa succede quando il trial finisce)
            // create subscription senza free trial
            $trialEnd = NULL; // NO TRIAL

            // CONFIG SUBSCRIPTION
            $subCongif = array(
                'product_name' => $idPlan->name_product,
                'tokenStripe' => $data['stripeToken'],
                'trialEnd' => $trialEnd,
                'nameCustomer' => $nameCustomer,
                'emailCustomer' => $emailUser,
                'nickPlan' => $idPlan->id, // Constant PLAN ID => SELEZIONA IL PIANO ABBONAMENTO DA CREARE
                'plan_interval' => $idPlan->interval, // day, week, month, and year
                'plan_interval_count' => $idPlan->interval_count, // 1 Month for example
                'amount_cent' => $idPlan->price, // => 2.99 in centesimi
                'currency' => 'usd' // currency iso code
            );
            // CREATE SUBSCRIPTION PIANO GOLD
            $result = $this->CreateSubscription($subCongif); //$data['stripeToken'], $trialEnd, $nameCustomer, $emailUser);
            //echo json_encode($result);
            if($result->id) {
                // add Data Subscription IN DB
                $this->addDataSubscriptionInDB($result);
            }
            // vai nella sezione subscription
            redirect('account/subscription/');
            //redirect('account/subscription/?loading=true');
            exit;
        } 
        if ($freetrialAvailable == 1) {
            //echo "ABONAMENTO FREE TRIAL DISPONIBILE";
            
            // CREATE SUBSCRIPTION PIANO with free trial
            $trialEnd = $this->AddTime($idPlan->trial); // Set trial Time in base alla costante impostata

            $subCongif = array(
                'product_name' => $idPlan->name_product,
                'tokenStripe' => $data['stripeToken'],
                'trialEnd' => $trialEnd,
                'nameCustomer' => $nameCustomer,
                'emailCustomer' => $emailUser,
                'nickPlan' => $idPlan->id, // Constant PLAN ID
                'plan_interval' => $idPlan->interval, // day, week, month, and year
                'plan_interval_count' => $idPlan->interval_count, // 1 Month for example
                'amount_cent' => $idPlan->price, // => 2.99 in centesimi
                'currency' => 'usd' // currency iso code
            );
            $result = $this->CreateSubscription($subCongif); //$data['stripeToken'], $trialEnd, $nameCustomer, $emailUser
            //echo json_encode($result);
            if($result->id) {
                // add Data Subscription IN DB
                $this->addDataSubscriptionInDB($result);
            }
            // vai nella sezione subscription
            redirect('account/subscription/');
            //redirect('account/subscription/?loading=true');
            exit;
        }
        if($freetrialAvailable == 2) {
            //echo "ABBONAMENTO GIA ATTIVO";
            redirect('account/subscription');
            exit;
        }


    }

    // Optional => CANCELL SUBSCRIPTION
    /*
	public function cancel() {
		$idSubscription = "sub_EOxZruQVPixABU"; // SUBSCRIPTION ID DA CANCELLARE
		$result = $this->stripe_lib->cancelSubscriptionWithID($idSubscription);
		print_r($result);
    }
    */

    // HELPER => Aggiungi al completamento della subscription i dati NEL DB
    private function addDataSubscriptionInDB($result) {
        // add subscription in DB
        $data_sub = array(
            'subscr_id' => trim($result->id),
            'subscr_status' => trim($result->status),
            'subscr_created' => $this->convertDate(trim($result->created)),
            'isTrial' => $this->isInTrial(trim($result->trial_end)),
            'trial_end' => $this->convertDate(trim($result->trial_end)),
            'trial_start' => $this->convertDate(trim($result->trial_start)),
            'last_update' => date("Y-m-d H:i:s"),
            'user_id' => trim($this->data_user->data->userId), // set user ID per questa subscription
            'current_period_end' => $this->convertDate(trim($result->current_period_end)),
            'current_period_start' => $this->convertDate(trim($result->current_period_start)),
            'nickname_subscr' => trim($result->plan->nickname)
        );
        $this->model->c_object('stripe_subscriptions', $data_sub); // inserisci in tabella subscription la transazione
        // Verifica lo stato dell'abonamento
        if(($result->status == "trialing") || ($result->status == "active")) {
            // INSERISCI Nel profilo utente sia i crediti che l'abonamento in base al piano attivato

            // Get NumCredits da settare
            $num_credit_to_set = $this->stripe_lib->returnCreditFromPlan($data_sub['nickname_subscr']);

            $data_user = array(
                'transation_id_subscr' => trim($result->id),
                'account_type' => "basic_stripe",
                //'credits' => $num_credit_to_set,
                'date_shop' => date("d/m/Y")
            );
            $this->model->edit('users', $data_user, 'cod_user', $this->data_user->data->userId); // inserisci la transazione nella tabella utente
        }
    }

    /**
     * FUNCTION CREATE SUBSCRIPOTIONS
     */
    // HELPER CREATE A SUBSCRIPTION
    private function CreateSubscription($subCongif) { //$stripeToken, $date_free_trial, $nameCustomer, $emailUser = NULL
        // CREATE PRODUCT
        $configProduct = array(
            'product_name' => $subCongif['product_name'],
            'product_type' => 'service'
        );
        $result = $this->stripe_lib->createSubscriptionProduct($configProduct);
        $prod_id = $result->id;
        // CREATE PLAN
        $configPlan = array(
            'product_id' => $prod_id,
            'plan_nickname' => $subCongif['nickPlan'], // IDPLAN ABO
            'plan_interval' => $subCongif['plan_interval'], // day, week, month, and year
            'plan_interval_count' => $subCongif['plan_interval_count'], // 1 Month for example
            'amount_cent' => $subCongif['amount_cent'], // => 2.99 in centesimi
            'currency' => $subCongif['currency'] // currency iso code
        );
        $result = $this->stripe_lib->createPlanWithProductID($configPlan);
        $idPlan = $result->id;
        // CREATE CUSTOMER
        $emailUser = $subCongif['emailCustomer'] ?? NULL; // opzionale
        $nameCustomer = $subCongif['nameCustomer'];
        $configCustomer = array(
            'customer_description' => 'Customer '.$nameCustomer ?? $emailUser,
            'email_customer' => $emailUser,
            'source_payment_token' => $subCongif['tokenStripe']
        );
        $result = $this->stripe_lib->createCustomerWithPaymentSource($configCustomer); // associa al piano il pagamento che viene selezionato dall'utente
        $idCustomer = $result->id;
        // CREATE SUBSCRIPTION
        //$trialEnd = $this->AddTime('+3 days'); // OR +3 minutes => Add 7 Days For Trial (al momento Ho impostato 1 ora per poter testare cosa succede quando il trial finisce)
        //$trialEnd = null; // niente freetrial
        $configSubscription = array(
            'customer_id' => $idCustomer,
            'id_plan' => $idPlan,
            'timestump_trial_end' => $subCongif['trialEnd'] ?? NULL
        );

        $result = $this->stripe_lib->createSubscriptionWithPlanAndCustomer($configSubscription); // crea Piano Con trial

        return $result;
    }

    /**
     * HELPER FUNCTION FOR STRIPE ABO CONTROLLER
    **/

    /* HELPER CHECK IF LOGGED */
    private function isUserLogged() {
        //verifica se si è già loggati in base al token
        if( isset($_COOKIE['log']) ) { //verifica che esiste il cookie //!isset($_COOKIE['log']) => se non esiste
            if( $this->jwt_lib->validate($_COOKIE['log']) != true ) { // valida il token
                // non valido
                return false;
            } else {
                return true;
            }
        } else {
            // non valido
            return false;
        }
    }
    // HELPER CHECK USER FREE TRIAL YES / NO
    private function userFreeTrialAvailable($user_data) {
        // return  2 => abonamento attivo
                // 0 => Abonamento freetrial non disponibile
                // 1 => Abbonamento free trial disponibile!

        // UTENTE HA GIA UN ABBONAMENTO ATTIVO
        if($user_data->transation_id_subscr != "" && $user_data->date_shop != "") {
            return 2;
        }
        // UTENTE HA GIA AVUTO UN ABBONAMENTO, ma in questo momento non ne ha uno attivo
        elseif($user_data->transation_id_subscr == "" && $user_data->date_shop != "") {
            return 0;
        }
        // UTENTE NON HA MAI AVUTO UN ABBONAMENTO
        elseif($user_data->transation_id_subscr == "" && $user_data->date_shop == "") {
            // Carica View per Step2
            return 1;
        } elseif($user_data->transation_id_subscr != "" && $user_data->date_shop == "") {
            // CASO ERRORE
           return 0;
        } else {
            // ALTRO CASO NON CONTROLLATO O ERRORE
            return 0;
        }
    }

    // HELPER IS Abo IN TRIAL?
    private function isInTrial($dateTrialEnd) {
        if(time() > $dateTrialEnd) {
            return false;
        } else {
            return true;
        }
    }

	// Helper => To timeStump + 7DAYS
	private function AddTime($time) {
		$newdate = strtotime($time, time());
		//$newdate = strtotime('+3 minutes', time());
		return $newdate;
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

}