<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Omnipay\Omnipay;

require_once(APPPATH . 'third_party/omnipay/vendor/autoload.php');

class Paymongo {

    private $_CI;

    public function __construct() {
        $this->_CI = &get_instance();
        $this->_CI->load->model('setting_model');
    }

    public function payment() {
        $gateway = Omnipay::create('Paymongo_Card');

        $setting = $this->_CI->setting_model->getSetting();
        if (empty($setting->paymongo_public_key) || empty($setting->paymongo_secret_key)) {
            return false;
        }

        $gateway->setKeys($setting->paymongo_public_key, $setting->paymongo_secret_key);
        $token = $gateway->authorize([
            'number' => '4123 4501 3100 0508',
            'expiryMonth' => '1',
            'expiryYear' => '22',
            'cvv' => '123',
        ]);
        print_r($token);
    }

}
