<?php
defined('BASEPATH') or exit('No direct script access allowed');

class DeviceManagement
{
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->library('session');
    }

    public function addDevice($nomor, $webhook)
    {
        try {
            $user_id = $this->CI->session->userdata('id_login');

            // Check device limit
            $users = $this->CI->db->get_where('account', ['id' => $user_id])->row();
            if (!$users) {
                throw new Exception('User account not found.');
            }

            if ($this->CI->db->get_where('device', ['pemilik' => $user_id])->num_rows() >= $users->limit_device) {
                throw new Exception('You have exceeded the device limit.');
            }

            // Validate device number
            if (empty($nomor) || !is_numeric($nomor)) {
                throw new Exception('Invalid device number.');
            }

            // Check if device already exists
            if ($this->CI->db->get_where('device', ['nomor' => $nomor])->num_rows() > 0) {
                throw new Exception('Number already registered.');
            }

            // Insert new device
            $this->CI->db->insert('device', [
                'pemilik' => $user_id,
                'nomor' => $nomor,
                'link_webhook' => $webhook,
                'chunk' => 100
            ]);

            return ['status' => 'success', 'message' => 'The device has been successfully added.'];

        } catch (Exception $e) {
            log_message('error', 'DeviceManagement::addDevice - Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getDashboardData($user_id)
    {
        return [
            'device' => $this->CI->db->get_where('device', ['pemilik' => $user_id]),
            'contacts' => $this->CI->db->get_where('nomor', ['make_by' => $user_id])->num_rows(),
            'pending' => $this->CI->db->get_where('pesan', ['status' => 'MENUNGGU JADWAL', 'make_by' => $user_id])->num_rows(),
            'gagal' => $this->CI->db->get_where('pesan', ['status' => 'GAGAL', 'make_by' => $user_id])->num_rows(),
            'terkirim' => $this->CI->db->get_where('pesan', ['status' => 'TERKIRIM', 'make_by' => $user_id])->num_rows(),
            'blast_pending' => $this->CI->db->get_where('blast', ['status' => 'pending', 'make_by' => $user_id])->num_rows(),
            'blast_gagal' => $this->CI->db->get_where('blast', ['status' => 'gagal', 'make_by' => $user_id])->num_rows(),
            'blast_terkirim' => $this->CI->db->get_where('blast', ['status' => 'terkirim', 'make_by' => $user_id])->num_rows()
        ];
    }
}
