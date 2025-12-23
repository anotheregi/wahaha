<?php
defined('BASEPATH') or exit('No direct script access allowed');

class DeviceController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            redirect(base_url('login'));
        }
        date_default_timezone_set('Asia/Jakarta');
    }

    public function device($nomor = null)
    {
        if (!$nomor) {
            $this->session->set_flashdata('error', 'Device number is required.');
            redirect(base_url('home'));
        }

        // Sanitize and validate device number
        $nomor = trim(htmlspecialchars(str_replace("'", "", $nomor)));
        if (empty($nomor) || !preg_match('/^[0-9+\-\s()]+$/', $nomor)) {
            $this->session->set_flashdata('error', 'Invalid device number format.');
            redirect(base_url('home'));
        }

        if ($this->input->post()) {
            try {
                $webhook = trim($this->input->post('webhook'));
                $chunk = (int)$this->input->post('chunk');

                // Validate chunk size
                if ($chunk < 1 || $chunk > 1000) {
                    $this->session->set_flashdata('error', 'Chunk size must be between 1 and 1000.');
                    redirect(base_url('device/') . $nomor);
                }

                // Validate webhook URL if provided
                if (!empty($webhook) && !filter_var($webhook, FILTER_VALIDATE_URL)) {
                    $this->session->set_flashdata('error', 'Invalid webhook URL format.');
                    redirect(base_url('device/') . $nomor);
                }

                // Check if device exists and belongs to user
                $device_check = $this->db->get_where('device', [
                    'nomor' => $nomor,
                    'pemilik' => $this->session->userdata('id_login')
                ]);

                if ($device_check->num_rows() != 1) {
                    $this->session->set_flashdata('error', 'Device not found or access denied.');
                    redirect(base_url('home'));
                }

                // Update device
                $update_data = ['chunk' => $chunk];
                if (!empty($webhook)) {
                    $update_data['link_webhook'] = $webhook;
                }

                if ($this->db->update('device', $update_data, ['nomor' => $nomor])) {
                    $this->session->set_flashdata('success', 'Device updated successfully.');
                } else {
                    $this->session->set_flashdata('error', 'Failed to update device. Please try again.');
                }

            } catch (Exception $e) {
                log_message('error', 'DeviceController::device - Error updating device: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'An error occurred while updating the device.');
            }

            redirect(base_url('device/') . $nomor);
        } else {
            try {
                // Check if device exists and belongs to user
                $query = $this->db->get_where('device', [
                    'nomor' => $nomor,
                    'pemilik' => $this->session->userdata('id_login')
                ]);

                if ($query->num_rows() != 1) {
                    $this->session->set_flashdata('error', 'Device not found or access denied.');
                    redirect(base_url('home'));
                }

                $data = [
                    'title' => 'Device',
                    'row' => $query->row(),
                    'settings' => $this->db->get_where('settings', ['id' => 1])->row()
                ];

                view('device', $data);
            } catch (Exception $e) {
                log_message('error', 'DeviceController::device - Error loading device data: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'Failed to load device information.');
                redirect(base_url('home'));
            }
        }
    }

    public function device_delete($nomor = null)
    {
        if (!$nomor) {
            $this->session->set_flashdata('error', 'Device number is required.');
            redirect(base_url('home'));
        }

        try {
            // Sanitize and validate device number
            $nomor = trim(htmlspecialchars(str_replace("'", "", $nomor)));
            if (empty($nomor) || !preg_match('/^[0-9+\-\s()]+$/', $nomor)) {
                $this->session->set_flashdata('error', 'Invalid device number format.');
                redirect(base_url('home'));
            }

            // Check if device exists and belongs to user
            $device_check = $this->db->get_where('device', [
                'nomor' => $nomor,
                'pemilik' => $this->session->userdata('id_login')
            ]);

            if ($device_check->num_rows() != 1) {
                $this->session->set_flashdata('error', 'Device not found or access denied.');
                redirect(base_url('home'));
            }

            // Delete device
            if ($this->db->delete('device', ['nomor' => $nomor])) {
                $this->session->set_flashdata('success', 'Device has been deleted successfully.');
            } else {
                $this->session->set_flashdata('error', 'Failed to delete device. Please try again.');
            }

        } catch (Exception $e) {
            log_message('error', 'DeviceController::device_delete - Error deleting device: ' . $e->getMessage());
            $this->session->set_flashdata('error', 'An error occurred while deleting the device.');
        }

        redirect(base_url('home'));
    }

    public function devices()
    {
        if ($this->input->post()) {
            try {
                $nomor = trim($this->input->post('nomor'));
                $webhook = trim($this->input->post('webhook'));

                // Input validation
                if (empty($nomor)) {
                    $this->session->set_flashdata('error', 'Device number is required.');
                    redirect(base_url('devices'));
                }

                if (!preg_match('/^[0-9+\-\s()]+$/', $nomor)) {
                    $this->session->set_flashdata('error', 'Invalid device number format.');
                    redirect(base_url('devices'));
                }

                // Validate webhook URL if provided
                if (!empty($webhook) && !filter_var($webhook, FILTER_VALIDATE_URL)) {
                    $this->session->set_flashdata('error', 'Invalid webhook URL format.');
                    redirect(base_url('devices'));
                }

                // Check device limit
                $user_id = $this->session->userdata('id_login');
                $users = $this->db->get_where('account', ['id' => $user_id])->row();
                if (!$users) {
                    $this->session->set_flashdata('error', 'User account not found.');
                    redirect(base_url('devices'));
                }

                $current_devices = $this->db->get_where('device', ['pemilik' => $user_id])->num_rows();
                if ($current_devices >= $users->limit_device) {
                    $this->session->set_flashdata('error', 'You have exceeded the device limit.');
                    redirect(base_url('devices'));
                }

                // Check if device number already exists globally
                $existing_device = $this->db->get_where('device', ['nomor' => $nomor]);
                if ($existing_device->num_rows() > 0) {
                    $this->session->set_flashdata('error', 'Device number already registered.');
                    redirect(base_url('devices'));
                }

                // Insert new device
                $device_data = [
                    'pemilik' => $user_id,
                    'nomor' => $nomor,
                    'link_webhook' => $webhook,
                    'chunk' => 100
                ];

                if ($this->db->insert('device', $device_data)) {
                    $this->session->set_flashdata('success', 'The device has been successfully added.');
                } else {
                    $this->session->set_flashdata('error', 'Failed to add device. Please try again.');
                }

            } catch (Exception $e) {
                log_message('error', 'DeviceController::devices - Error adding device: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'An error occurred while adding the device.');
            }

            redirect(base_url('devices'));
        } else {
            try {
                $user_id = $this->session->userdata('id_login');

                $data = [
                    'title' => 'Devices',
                    'device' => $this->db->get_where('device', ['pemilik' => $user_id])
                ];

                view("devices", $data);
            } catch (Exception $e) {
                log_message('error', 'DeviceController::devices - Error loading devices: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'Failed to load devices list.');
                redirect(base_url('home'));
            }
        }
    }
}
