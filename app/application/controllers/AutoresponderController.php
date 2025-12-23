<?php
defined('BASEPATH') or exit('No direct script access allowed');

class AutoresponderController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            redirect(base_url('login'));
        }
        date_default_timezone_set('Asia/Jakarta');
    }

    public function autoresponder()
    {
        if ($this->input->post()) {
            if ($this->input->post('type') == '1') {
                $datainsert = [
                    'type' => 'Text',
                    'keyword' => _POST('keyword'),
                    'response' => _POST('message'),
                    'nomor' => _POST('device'),
                    'make_by' => $this->session->userdata('id_login')
                ];
                $this->db->insert('autoreply', $datainsert);
            } else if ($this->input->post('type') == '2') {
                $datainsert = [
                    'type' => 'Text & Media',
                    'keyword' => _POST('keyword'),
                    'response' => _POST('message'),
                    'nomor' => _POST('device'),
                    'media' => _POST('media'),
                    'make_by' => $this->session->userdata('id_login')
                ];
                $this->db->insert('autoreply', $datainsert);
            } else if ($this->input->post('type') == '3') {
                $datainsert = [
                    'type' => 'Quick Reply Button',
                    'keyword' => _POST('keyword'),
                    'response' => _POST('message'),
                    'footer' => _POST('footer'),
                    'btn1' => _POST('btn1'),
                    'btnid1' => _POST('btn1'),
                    'btn2' => _POST('btn2'),
                    'btnid2' => _POST('btn2'),
                    'btn3' => _POST('btn3'),
                    'btnid3' => _POST('btn3'),
                    'nomor' => _POST('device'),
                    'make_by' => $this->session->userdata('id_login')
                ];
                $this->db->insert('autoreply', $datainsert);
            } else if ($this->input->post('type') == '4') {
                $datainsert = [
                    'type' => 'Url & Call Button',
                    'keyword' => _POST('keyword'),
                    'response' => _POST('message'),
                    'footer' => _POST('footer'),
                    'btn1' => _POST('btnurl'),
                    'btnid1' => _POST('btnurl_val'),
                    'btn2' => _POST('btncall'),
                    'btnid2' => _POST('btncall_val'),
                    'nomor' => _POST('device'),
                    'make_by' => $this->session->userdata('id_login')
                ];
                $this->db->insert('autoreply', $datainsert);
            }
            $this->session->set_flashdata('success', 'Successfully added Auto Reply.');
            redirect(base_url('autoresponder'));
        } else {
            $data = [
                'title' => 'Auto Reply',
                'respon' => $this->db->get_where('autoreply', ['make_by' => $this->session->userdata('id_login')]),
                'device' =>  $this->db->get_where('device', ['pemilik' => $this->session->userdata('id_login')])
            ];
            view('autoresponder', $data);
        }
    }

    public function autoresponder_del($id = null)
    {
        if (!$id) {
            redirect(base_url('autoresponder'));
        }

        $id = htmlspecialchars(str_replace("'", "", $id));
        $this->db->delete('autoreply', ['id' => $id]);
        $this->session->set_flashdata('success', 'Autoreply deleted successfully.');
        redirect(base_url('autoresponder'));
    }

    public function autoresponder_view($id = null)
    {
        if (!$id) {
            redirect(base_url('autoresponder'));
        }

        $id = htmlspecialchars(str_replace("'", "", $id));
        $data['title'] = 'Auto Reply View';
        $data['row'] = $this->db->get_where('autoreply', ['id' => $id])->row();
        view('autoresponder_view', $data);
    }
}
