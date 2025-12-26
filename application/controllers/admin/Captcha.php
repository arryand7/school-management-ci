<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Captcha extends Admin_Controller
{
    public $custom_fields_list = array();
    public function __construct()
    {
        parent::__construct();
        $this->load->model('captcha_model');
        $this->load->model('setting_model');
        $this->load->library('captchalib');
    }

    public function index()
    {
        $userdata                    = $this->customlib->getUserData();
        if($userdata["role_id"] != 7){
            access_denied();
        }
        
        $this->session->set_userdata('top_menu', 'System Settings');
        $this->session->set_userdata('sub_menu', 'System Settings/captcha');
        $data['inserted_fields'] = $this->captcha_model->getSetting();
        $data['setting']         = $this->setting_model->getSetting();
        $this->load->view('layout/header');
        $this->load->view('admin/captcha/index', $data);
        $this->load->view('layout/footer');
    }

    public function changeStatus()
    {
        $data = array(
            'name'   => $this->input->post('name'),
            'status' => $this->input->post('status'),
        );
        $this->captcha_model->update_status($data);

    }

    public function update_keys()
    {
        $userdata = $this->customlib->getUserData();
        if ($userdata["role_id"] != 7) {
            access_denied();
        }

        $data = array(
            'id'                            => $this->input->post('id'),
            'google_maps_api_key'           => $this->input->post('google_maps_api_key'),
            'firebase_service_account_json' => $this->input->post('firebase_service_account_json'),
            'yandex_translate_api_key'      => $this->input->post('yandex_translate_api_key'),
            'paymongo_public_key'           => $this->input->post('paymongo_public_key'),
            'paymongo_secret_key'           => $this->input->post('paymongo_secret_key'),
        );

        $this->setting_model->add($data);
        $this->session->set_flashdata('success_msg', $this->lang->line('success_message'));
        redirect('admin/captcha');
    }
}
