<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Reports extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            redirect(base_url('login'));
        }
        date_default_timezone_set('Asia/Jakarta');
    }

    public function single()
    {
        $data['title'] = 'Report Single Message';
        $iduser = $this->session->userdata('id_login');
        $data['request'] = $this->db->query("SELECT reports.*, device.pemilik, device.nomor FROM device INNER JOIN reports ON device.nomor = reports.device WHERE pemilik=$iduser and type='single' ORDER BY id DESC");
        view('report_single', $data);
    }

    public function single_del()
    {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $this->db->query("DELETE FROM reports WHERE id IN(" . implode(",", $id) . ")");
            $this->session->set_flashdata('success', 'Successfully delete.');
            redirect(base_url('report/single'));
        } else {
            $this->session->set_flashdata('error', 'checklist that you want to delete.');
            redirect(base_url('report/single'));
        }
    }

    public function received()
    {
        $data['title'] = 'Report Received Message';
        $iduser = $this->session->userdata('id_login');
        $data['request'] = $this->db->query("SELECT reports.*, device.pemilik, device.nomor FROM device INNER JOIN reports ON device.nomor = reports.device WHERE pemilik=$iduser and type='received' ORDER BY id DESC");
        view('report_received', $data);
    }

    public function received_del()
    {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $this->db->query("DELETE FROM reports WHERE id IN(" . implode(",", $id) . ")");
            $this->session->set_flashdata('success', 'Successfully delete.');
            redirect(base_url('report/received'));
        } else {
            $this->session->set_flashdata('error', 'checklist that you want to delete.');
            redirect(base_url('report/received'));
        }
    }

    public function api()
    {
        $data['title'] = 'Report Api Message';
        $iduser = $this->session->userdata('id_login');
        $data['request'] = $this->db->query("SELECT reports.*, device.pemilik, device.nomor FROM device INNER JOIN reports ON device.nomor = reports.device WHERE pemilik=$iduser and type='api' ORDER BY id DESC");
        view('report_api', $data);
    }

    public function api_del()
    {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $this->db->query("DELETE FROM reports WHERE id IN(" . implode(",", $id) . ")");
            $this->session->set_flashdata('success', 'Successfully delete.');
            redirect(base_url('report/api'));
        } else {
            $this->session->set_flashdata('error', 'checklist that you want to delete.');
            redirect(base_url('report/api'));
        }
    }

    public function chat_history($receiver = null)
    {
        if (!$receiver) {
            // Show list of conversations
            $iduser = $this->session->userdata('id_login');
            $device = $this->db->query("SELECT nomor FROM device WHERE pemilik=$iduser LIMIT 1")->row()->nomor;

            // Get unique receivers from reports
            $conversations = $this->db->query("SELECT DISTINCT receiver as contact, COUNT(*) as message_count, MAX(created_at) as last_message FROM reports WHERE device='$device' AND type IN ('single', 'received') GROUP BY receiver ORDER BY last_message DESC");

            $data['title'] = 'Chat History';
            $data['conversations'] = $conversations;
            view('chat_history_list', $data);
            return;
        }

        $iduser = $this->session->userdata('id_login');
        $device = $this->db->query("SELECT nomor FROM device WHERE pemilik=$iduser LIMIT 1")->row()->nomor;

        // Get sent messages
        $sent = $this->db->query("SELECT 'sent' as direction, message, created_at as timestamp FROM reports WHERE receiver='$receiver' AND device='$device' AND type IN ('single', 'received') ORDER BY created_at ASC");

        // Get received messages
        $received = $this->db->query("SELECT 'received' as direction, pesan as message, tanggal as timestamp FROM receive_chat WHERE nomor='$receiver' AND nomor_saya='$device' ORDER BY tanggal ASC");

        // Combine and sort
        $messages = array();
        foreach ($sent->result() as $msg) {
            $messages[] = $msg;
        }
        foreach ($received->result() as $msg) {
            $messages[] = $msg;
        }

        // Sort by timestamp
        usort($messages, function($a, $b) {
            return strtotime($a->timestamp) - strtotime($b->timestamp);
        });

        $data['title'] = 'Chat History - ' . $receiver;
        $data['receiver'] = $receiver;
        $data['messages'] = $messages;
        view('chat_history', $data);
    }
}
