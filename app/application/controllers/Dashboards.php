<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboards extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            redirect(base_url('login'));
        }
        date_default_timezone_set('Asia/Jakarta');
        $this->load->library('DeviceManagement');
    }

    public function index()
    {
        try {
            if ($this->input->post()) {
                $nomor = _POST('nomor');
                $webhook = _POST('webhook');

                $result = $this->devicemanagement->addDevice($nomor, $webhook);

                if ($result['status'] === 'success') {
                    $this->session->set_flashdata('success', $result['message']);
                } else {
                    $this->session->set_flashdata('error', $result['message']);
                }
                redirect(base_url('home'));
            } else {
                // Clear any old flashdata messages
                $this->session->set_flashdata('error', '');
                $this->session->set_flashdata('success', '');

                $user_id = $this->session->userdata('id_login');
                $data = array_merge(['title' => 'Home'], $this->devicemanagement->getDashboardData($user_id));
                view("home", $data);
            }
        } catch (Exception $e) {
            log_message('error', 'Dashboards::index - Error: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'An unexpected error occurred. Please try again.');
            redirect(base_url('home'));
        }
    }
}
