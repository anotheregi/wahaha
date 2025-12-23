<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('Asia/Jakarta');
        error_reporting(0);
        $this->load->library('form_validation');
        $this->load->driver('cache', array('adapter' => 'file'));
        $this->load->library('session');
    }

    public function send_message()
    {
        header('Content-Type: application/json');

        try {
            // Rate limiting check
            $ip = $this->input->ip_address();
            $cache_key = 'rate_limit_' . $ip;
            $requests = $this->cache->get($cache_key);
            if ($requests === FALSE) {
                $requests = 0;
            }
            if ($requests >= 100) { // 100 requests per 15 minutes
                $ret['status'] = false;
                $ret['msg'] = "Rate limit exceeded. Try again later.";
                echo json_encode($ret, true);
                exit;
            }
            $this->cache->save($cache_key, $requests + 1, 900); // 15 minutes

            // Parse input data
            if ($this->input->post()) {
                $data = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $ret['status'] = false;
                    $ret['msg'] = "Invalid JSON data";
                    echo json_encode($ret, true);
                    exit;
                }
                $this->form_validation->set_data($data);
            } else {
                $data = [
                    'sender' => $this->input->get('sender'),
                    'number' => $this->input->get('receiver'),
                    'message' => $this->input->get('message'),
                    'api_key' => $this->input->get('apikey')
                ];
                $this->form_validation->set_data($data);
            }

            // Validation rules
            $this->form_validation->set_rules('sender', 'Sender', 'required|trim|xss_clean|numeric');
            $this->form_validation->set_rules('number', 'Number', 'required|trim|xss_clean|numeric');
            $this->form_validation->set_rules('message', 'Message', 'required|trim|xss_clean|max_length[1000]');
            $this->form_validation->set_rules('api_key', 'API Key', 'required|trim|xss_clean|alpha_numeric');

            if ($this->form_validation->run() == FALSE) {
                $ret['status'] = false;
                $ret['msg'] = validation_errors();
                echo json_encode($ret, true);
                exit;
            }

            $sender = $this->form_validation->set_value('sender');
            $nomor = $this->form_validation->set_value('number');
            $pesan = $this->form_validation->set_value('message');
            $key = $this->form_validation->set_value('api_key');

            // Validate API key
            $cek = $this->db->get_where('account', ['api_key' => $key]);
            if ($cek->num_rows() == 0) {
                $ret['status'] = false;
                $ret['msg'] = "API Key is wrong/not found!";
                echo json_encode($ret, true);
                exit;
            }
            $id_users = $cek->row()->id;

            // Validate device ownership
            $cek2 = $this->db->get_where('device', ['nomor' => $sender, 'pemilik' => $id_users]);
            if ($cek2->num_rows() == 0) {
                $ret['status'] = false;
                $ret['msg'] = "Device not found or access denied!";
                echo json_encode($ret, true);
                exit;
            }

            // Send message
            $res = sendMSG($nomor, $pesan, $sender);
            if ($res['status'] == "true") {
                // Log successful send
                $datainsert = [
                    'device' => $sender,
                    'receiver' => $nomor,
                    'message' => $pesan,
                    'type' => 'api',
                    'status' => 'Sent',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('reports', $datainsert);

                $ret['status'] = true;
                $ret['msg'] = "Message sent successfully";
                echo json_encode($ret, true);
                exit;
            } else {
                // Log failed send
                $datainsert = [
                    'device' => $sender,
                    'receiver' => $nomor,
                    'message' => $pesan,
                    'type' => 'api',
                    'status' => 'Failed',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('reports', $datainsert);

                $ret['status'] = false;
                $ret['msg'] = 'Device not connected or message failed to send';
                echo json_encode($ret, true);
                exit;
            }

        } catch (Exception $e) {
            log_message('error', 'Api::send_message - Error: ' . $e->getMessage());
            $ret['status'] = false;
            $ret['msg'] = 'Internal server error';
            echo json_encode($ret, true);
            exit;
        }
    }

    public function send_media()
    {
        header('Content-Type: application/json');

        try {
            // Rate limiting check
            $ip = $this->input->ip_address();
            $cache_key = 'rate_limit_' . $ip;
            $requests = $this->cache->get($cache_key);
            if ($requests === FALSE) {
                $requests = 0;
            }
            if ($requests >= 100) { // 100 requests per 15 minutes
                $ret['status'] = false;
                $ret['msg'] = "Rate limit exceeded. Try again later.";
                echo json_encode($ret, true);
                exit;
            }
            $this->cache->save($cache_key, $requests + 1, 900); // 15 minutes

            // Parse input data
            if ($this->input->post()) {
                $data = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $ret['status'] = false;
                    $ret['msg'] = "Invalid JSON data";
                    echo json_encode($ret, true);
                    exit;
                }
                $this->form_validation->set_data($data);
            } else {
                $data = [
                    'sender' => $this->input->get('sender'),
                    'number' => $this->input->get('receiver'),
                    'message' => $this->input->get('message'),
                    'api_key' => $this->input->get('apikey'),
                    'url' => $this->input->get('url')
                ];
                $this->form_validation->set_data($data);
            }

            // Validation rules
            $this->form_validation->set_rules('sender', 'Sender', 'required|trim|xss_clean|numeric');
            $this->form_validation->set_rules('number', 'Number', 'required|trim|xss_clean|numeric');
            $this->form_validation->set_rules('message', 'Message', 'trim|xss_clean|max_length[1000]');
            $this->form_validation->set_rules('api_key', 'API Key', 'required|trim|xss_clean|alpha_numeric');
            $this->form_validation->set_rules('url', 'URL', 'required|trim|xss_clean|valid_url');

            if ($this->form_validation->run() == FALSE) {
                $ret['status'] = false;
                $ret['msg'] = validation_errors();
                echo json_encode($ret, true);
                exit;
            }

            $sender = $this->form_validation->set_value('sender');
            $nomor = $this->form_validation->set_value('number');
            $caption = $this->form_validation->set_value('message');
            $key = $this->form_validation->set_value('api_key');
            $url = $this->form_validation->set_value('url');

            // Parse media URL
            $a = explode('/', $url);
            if (empty($a)) {
                $ret['status'] = false;
                $ret['msg'] = "Invalid media URL format";
                echo json_encode($ret, true);
                exit;
            }
            $filename = $a[count($a) - 1];
            $a2 = explode('.', $filename);
            if (count($a2) < 2) {
                $ret['status'] = false;
                $ret['msg'] = "Invalid media file extension";
                echo json_encode($ret, true);
                exit;
            }
            $namefile = $a2[count($a2) - 2];
            $ext = $a2[count($a2) - 1];

            // Validate supported file types
            if (!in_array(strtolower($ext), ['jpg', 'pdf', 'png'])) {
                $ret['status'] = false;
                $ret['msg'] = "Only support jpg, pdf, and png files";
                echo json_encode($ret, true);
                exit;
            }

            // Validate API key
            $cek = $this->db->get_where('account', ['api_key' => $key]);
            if ($cek->num_rows() == 0) {
                $ret['status'] = false;
                $ret['msg'] = "API Key is wrong/not found!";
                echo json_encode($ret, true);
                exit;
            }
            $id_users = $cek->row()->id;

            // Validate device ownership
            $cek2 = $this->db->get_where('device', ['nomor' => $sender, 'pemilik' => $id_users]);
            if ($cek2->num_rows() == 0) {
                $ret['status'] = false;
                $ret['msg'] = "Device not found or access denied!";
                echo json_encode($ret, true);
                exit;
            }

            // Send media message
            $res = sendMedia($nomor, $caption, $sender, $ext, $namefile, $url);
            if ($res['status'] == "true") {
                // Log successful send
                $datainsert = [
                    'device' => $sender,
                    'receiver' => $nomor,
                    'message' => $caption,
                    'media' => $url,
                    'type' => 'api',
                    'status' => 'Sent',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('reports', $datainsert);

                $ret['status'] = true;
                $ret['msg'] = "Media sent successfully";
                echo json_encode($ret, true);
                exit;
            } else {
                // Log failed send
                $datainsert = [
                    'device' => $sender,
                    'receiver' => $nomor,
                    'message' => $caption,
                    'media' => $url,
                    'type' => 'api',
                    'status' => 'Failed',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('reports', $datainsert);

                $ret['status'] = false;
                $ret['msg'] = 'Device not connected or media failed to send';
                echo json_encode($ret, true);
                exit;
            }

        } catch (Exception $e) {
            log_message('error', 'Api::send_media - Error: ' . $e->getMessage());
            $ret['status'] = false;
            $ret['msg'] = 'Internal server error';
            echo json_encode($ret, true);
            exit;
        }
    }

    public function send_button()
    {
        header('Content-Type: application/json');

        try {
            // Rate limiting check
            $ip = $this->input->ip_address();
            $cache_key = 'rate_limit_' . $ip;
            $requests = $this->cache->get($cache_key);
            if ($requests === FALSE) {
                $requests = 0;
            }
            if ($requests >= 100) { // 100 requests per 15 minutes
                $ret['status'] = false;
                $ret['msg'] = "Rate limit exceeded. Try again later.";
                echo json_encode($ret, true);
                exit;
            }
            $this->cache->save($cache_key, $requests + 1, 900); // 15 minutes

            // Parse input data
            if ($this->input->post()) {
                $data = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $ret['status'] = false;
                    $ret['msg'] = "Invalid JSON data";
                    echo json_encode($ret, true);
                    exit;
                }
                $this->form_validation->set_data($data);
            } else {
                $data = [
                    'sender' => $this->input->get('sender'),
                    'number' => $this->input->get('receiver'),
                    'message' => $this->input->get('message'),
                    'footer' => $this->input->get('footer'),
                    'button1' => $this->input->get('btn1'),
                    'button2' => $this->input->get('btn2'),
                    'api_key' => $this->input->get('apikey')
                ];
                $this->form_validation->set_data($data);
            }

            // Validation rules
            $this->form_validation->set_rules('sender', 'Sender', 'required|trim|xss_clean|numeric');
            $this->form_validation->set_rules('number', 'Number', 'required|trim|xss_clean|numeric');
            $this->form_validation->set_rules('message', 'Message', 'required|trim|xss_clean|max_length[1000]');
            $this->form_validation->set_rules('footer', 'Footer', 'trim|xss_clean|max_length[100]');
            $this->form_validation->set_rules('button1', 'Button 1', 'required|trim|xss_clean|max_length[50]');
            $this->form_validation->set_rules('button2', 'Button 2', 'required|trim|xss_clean|max_length[50]');
            $this->form_validation->set_rules('api_key', 'API Key', 'required|trim|xss_clean|alpha_numeric');

            if ($this->form_validation->run() == FALSE) {
                $ret['status'] = false;
                $ret['msg'] = validation_errors();
                echo json_encode($ret, true);
                exit;
            }

            $sender = $this->form_validation->set_value('sender');
            $nomor = $this->form_validation->set_value('number');
            $pesan = $this->form_validation->set_value('message');
            $footer = $this->form_validation->set_value('footer');
            $button1 = $this->form_validation->set_value('button1');
            $button2 = $this->form_validation->set_value('button2');
            $key = $this->form_validation->set_value('api_key');

            // Validate API key
            $cek = $this->db->get_where('account', ['api_key' => $key]);
            if ($cek->num_rows() == 0) {
                $ret['status'] = false;
                $ret['msg'] = "API Key is wrong/not found!";
                echo json_encode($ret, true);
                exit;
            }
            $id_users = $cek->row()->id;

            // Validate device ownership
            $cek2 = $this->db->get_where('device', ['nomor' => $sender, 'pemilik' => $id_users]);
            if ($cek2->num_rows() == 0) {
                $ret['status'] = false;
                $ret['msg'] = "Device not found or access denied!";
                echo json_encode($ret, true);
                exit;
            }

            // Send button message
            $res = sendBTN($nomor, $sender, $pesan, $footer, $button1, $button2);
            if ($res['status'] == "true") {
                // Log successful send
                $datainsert = [
                    'device' => $sender,
                    'receiver' => $nomor,
                    'message' => $pesan,
                    'footer' => $footer,
                    'btn1' => $button1,
                    'btn2' => $button2,
                    'btnid1' => $button1,
                    'btnid2' => $button2,
                    'type' => 'api',
                    'status' => 'Sent',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('reports', $datainsert);

                $ret['status'] = true;
                $ret['msg'] = "Button message sent successfully";
                echo json_encode($ret, true);
                exit;
            } else {
                // Log failed send
                $datainsert = [
                    'device' => $sender,
                    'receiver' => $nomor,
                    'message' => $pesan,
                    'footer' => $footer,
                    'btn1' => $button1,
                    'btn2' => $button2,
                    'btnid1' => $button1,
                    'btnid2' => $button2,
                    'type' => 'api',
                    'status' => 'Failed',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('reports', $datainsert);

                $ret['status'] = false;
                $ret['msg'] = 'Device not connected or button message failed to send';
                echo json_encode($ret, true);
                exit;
            }

        } catch (Exception $e) {
            log_message('error', 'Api::send_button - Error: ' . $e->getMessage());
            $ret['status'] = false;
            $ret['msg'] = 'Internal server error';
            echo json_encode($ret, true);
            exit;
        }
    }

    public function callback()
    {
        header('content-type: application/json');

        try {
            // Rate limiting check
            $ip = $this->input->ip_address();
            $cache_key = 'rate_limit_' . $ip;
            $requests = $this->cache->get($cache_key);
            if ($requests === FALSE) {
                $requests = 0;
            }
            if ($requests >= 100) { // 100 requests per 15 minutes
                echo json_encode(['status' => false, 'msg' => 'Rate limit exceeded. Try again later.']);
                exit;
            }
            $this->cache->save($cache_key, $requests + 1, 900); // 15 minutes

            // Parse input data
            $data = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['status' => false, 'msg' => 'Invalid JSON data']);
                exit;
            }

            if (!isset($data['id']) || !isset($data['data']) || !is_array($data['data'])) {
                echo json_encode(['status' => false, 'msg' => 'Invalid data structure']);
                exit;
            }

            $sender = preg_replace("/\D/", "", $data['id']);
            if (empty($sender) || !is_numeric($sender)) {
                echo json_encode(['status' => false, 'msg' => 'Invalid sender ID']);
                exit;
            }

            $processed = 0;
            foreach ($data['data'] as $d) {
                if (!isset($d['id']) || !isset($d['name'])) {
                    continue; // Skip invalid entries
                }
                $number = str_replace("@s.whatsapp.net", "", $d['id']);
                $nama = filter_var($d['name'], FILTER_SANITIZE_STRING);

                // Validate number format
                if (!is_numeric($number) || strlen($number) < 10) {
                    continue; // Skip invalid numbers
                }

                // Check if contact already exists
                $cek = $this->db->get_where('all_contacts', ['sender' => $sender, 'number' => $number]);
                if ($cek->num_rows() == 0) {
                    $this->db->insert('all_contacts', [
                        'sender' => $sender,
                        'number' => $number,
                        'nama' => $nama,
                        'type' => 'Personal'
                    ]);
                    $processed++;
                }
            }

            echo json_encode(['status' => true, 'processed' => $processed]);

        } catch (Exception $e) {
            log_message('error', 'Api::callback - Error: ' . $e->getMessage());
            echo json_encode(['status' => false, 'msg' => 'Internal server error']);
            exit;
        }
    }
}
