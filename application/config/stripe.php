<?php
defined('BASEPATH') OR exit('No direct script access allowed');

  // Private Key Stipe (SANDBOX) => check on Stripe Dashboard
  $config['secret_key'] = ''; // EXAMPLE => sk_test_
  $config['publishable_key'] = ''; // EXAMPLE => pk_test_
  $config['endpoint_secret'] = ''; // for WebHook Decrypting... EXAMPLE => whsec_