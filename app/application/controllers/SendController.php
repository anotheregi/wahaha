<?php
defined('BASEPATH') or exit('No direct script access allowed');

class SendController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            redirect(base_url('login'));
        }
        date_default_timezone_set('Asia/Jakarta');
    }

    public function send()
    {
        if ($this->input->post()) {
            $submitby = $this->input->post('submitby');
            if ($submitby == 'pesan-text') {
                $device = _POST('device');
                $nomor = _POST('nomor');
                $pesan = _POST('pesan');
                $res = sendMSG($nomor, $pesan, $device);
                if ($res['status'] == true) {
                    $datainsert = [
                        'device' => $device,
                        'receiver' => $nomor,
                        'message' => $pesan,
                        'type' => 'single',
                        'status' => 'Sent',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $this->db->insert('reports', $datainsert);
                    $this->session->set_flashdata('success', 'Text message sent.');
                    redirect(base_url('send'));
                } else {
                    $datainsert = [
                        'device' => $device,
                        'receiver' => $nomor,
                        'message' => $pesan,
                        'type' => 'single',
                        'status' => 'Failed',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $this->db->insert('reports', $datainsert);
                    $this->session->set_flashdata('error', 'failed to send message.');
                    redirect(base_url('send'));
                }
            } else if ($submitby == 'pesan-media') {
                $device = $this->input->post('device');
                $nomor = $this->input->post('nomor');
                $pesan = $this->input->post('pesan');
                $media = $this->input->post('media');
                $a = explode('/', $media);
                $filename = $a[count($a) - 1];
                $a2 = explode('.', $filename);
                $namefile = $a2[count($a2) - 2];
                $filetype = $a2[count($a2) - 1];
                $getstorage = $this->db->get_where('storage', ['namafile' => $filename])->row();
                $res = sendMedia($nomor, $pesan, $device, $filetype, explode('.', $getstorage->nama_original)[0], $media);
                if ($res['status'] == true) {
                    $datainsert = [
                        'device' => $device,
                        'receiver' => $nomor,
                        'message' => $pesan,
                        'media' => $media,
                        'type' => 'single',
                        'status' => 'Sent',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $this->db->insert('reports', $datainsert);
                    $this->session->set_flashdata('success', 'Media message sent.');
                    redirect(base_url('send'));
                } else {
                    $datainsert = [
                        'device' => $device,
                        'receiver' => $nomor,
                        'message' => $pesan,
                        'media' => $media,
                        'type' => 'single',
                        'status' => 'Failed',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $this->db->insert('reports', $datainsert);
                    $this->session->set_flashdata('error', 'failed to send message.');
                    redirect(base_url('send'));
                }
            } else if ($submitby == 'pesan-button') {
                $device = $this->input->post('device');
                $nomor = $this->input->post('nomor');
                $pesan = $this->input->post('pesan');
                $footer = $this->input->post('footer');
                $btn1 = $this->input->post('btn1');
                $btn2 = $this->input->post('btn2');
                $res = sendBTN($nomor, $device, $pesan, $footer, $btn1, $btn2);
                if ($res['status'] == true) {
                    $datainsert = [
                        'device' => $device,
                        'receiver' => $nomor,
                        'message' => $pesan,
                        'footer' => $footer,
                        'btn1' => $btn1,
                        'btn2' => $btn2,
                        'btnid1' => $btn1,
                        'btnid2' => $btn2,
                        'type' => 'single',
                        'status' => 'Sent',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $this->db->insert('reports', $datainsert);
                    $this->session->set_flashdata('success', 'Text message sent.');
                    redirect(base_url('send'));
                } else {
                    $datainsert = [
                        'device' => $device,
                        'receiver' => $nomor,
                        'message' => $pesan,
                        'footer' => $footer,
                        'btn1' => $btn1,
                        'btn2' => $btn2,
                        'btnid1' => $btn1,
                        'btnid2' => $btn2,
                        'type' => 'single',
                        'status' => 'Failed',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    $this->db->insert('reports', $datainsert);
                    $this->session->set_flashdata('error', 'failed to send message');
                    redirect(base_url('send'));
                }
            }
        } else {
            $data = [
                'title' => 'Single Send',
                'device' =>  $this->db->get_where('device', ['pemilik' => $this->session->userdata('id_login')])
            ];
            view('send', $data);
        }
    }
}
