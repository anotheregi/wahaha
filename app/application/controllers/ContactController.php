<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ContactController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            redirect(base_url('login'));
        }
        date_default_timezone_set('Asia/Jakarta');
    }

    public function contacts()
    {
        if ($this->input->post()) {
            try {
                $nama = trim($this->input->post('nama'));
                $nomor = trim($this->input->post('nomor'));
                $label = trim($this->input->post('label'));

                // Input validation
                if (empty($nama) || empty($nomor)) {
                    $this->session->set_flashdata('error', 'Name and phone number are required.');
                    redirect(base_url('contacts'));
                }

                if (!preg_match('/^[0-9+\-\s()]+$/', $nomor)) {
                    $this->session->set_flashdata('error', 'Invalid phone number format.');
                    redirect(base_url('contacts'));
                }

                // Check if number already exists
                $existing = $this->db->get_where('nomor', ['nomor' => $nomor]);
                if ($existing->num_rows() > 0) {
                    $this->session->set_flashdata('error', 'Number already exists.');
                    redirect(base_url('contacts'));
                }

                // Insert new contact
                $data = [
                    'nama' => $nama,
                    'nomor' => $nomor,
                    'label' => $label,
                    'make_by' => $this->session->userdata('id_login')
                ];

                if ($this->db->insert('nomor', $data)) {
                    $this->session->set_flashdata('success', 'Successfully added Number.');
                } else {
                    $this->session->set_flashdata('error', 'Failed to add contact. Please try again.');
                }

            } catch (Exception $e) {
                log_message('error', 'ContactController::contacts - Error: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'An error occurred while adding the contact.');
            }

            redirect(base_url('contacts'));
        } else {
            try {
                $user_id = $this->session->userdata('id_login');

                $data = [
                    'title' => 'Contacts',
                    'nomor' => $this->db->get_where('nomor', ['make_by' => $user_id]),
                    'device' => $this->db->get_where('device', ['pemilik' => $user_id]),
                    'label' => $this->db->query('SELECT * FROM nomor WHERE label!="" GROUP BY label ORDER BY id DESC')
                ];

                view('contacts', $data);
            } catch (Exception $e) {
                log_message('error', 'ContactController::contacts - Error loading data: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'Failed to load contacts data.');
                redirect(base_url('home'));
            }
        }
    }

    public function get_contacts()
    {
        if ($this->input->post()) {
            try {
                $device = trim($this->input->post('device'));
                $by = $this->session->userdata('id_login');

                // Input validation
                if (empty($device)) {
                    $this->session->set_flashdata('error', 'Device is required.');
                    redirect(base_url('contacts'));
                }

                $all_contacts = $this->db->get_where('all_contacts', ['sender' => $device]);
                if ($all_contacts->num_rows() == 0) {
                    $this->session->set_flashdata('error', 'No contacts found for this device.');
                    redirect(base_url('contacts'));
                }

                $imported_count = 0;
                foreach ($all_contacts->result() as $c) {
                    // Validate contact data
                    if (empty($c->name) || empty($c->number)) {
                        continue; // Skip invalid contacts
                    }

                    $existing = $this->db->get_where('nomor', ['nomor' => $c->number, 'make_by' => $by]);
                    if ($existing->num_rows() == 0) {
                        $data = [
                            'nama' => $c->name,
                            'nomor' => $c->number,
                            'label' => '',
                            'make_by' => $by
                        ];

                        if ($this->db->insert('nomor', $data)) {
                            $imported_count++;
                        }
                    }
                }

                if ($imported_count > 0) {
                    $this->session->set_flashdata('success', "Successfully imported {$imported_count} contacts.");
                } else {
                    $this->session->set_flashdata('info', 'No new contacts to import.');
                }

            } catch (Exception $e) {
                log_message('error', 'ContactController::get_contacts - Error: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'Failed to retrieve contacts. Please try again.');
            }

            redirect(base_url('contacts'));
        }
    }

    public function contacts_del()
    {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $this->db->query("DELETE FROM nomor WHERE id IN(" . implode(",", $id) . ")");
            $this->session->set_flashdata('success', 'Successfully Delete the number in the checklist.');
            redirect(base_url('contacts'));
        } else {
            $this->session->set_flashdata('error', 'checklist that you want to delete.');
            redirect(base_url('contacts'));
        }
    }
}
