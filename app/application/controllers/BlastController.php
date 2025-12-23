<?php
defined('BASEPATH') or exit('No direct script access allowed');

class BlastController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!is_login()) {
            redirect(base_url('login'));
        }
        date_default_timezone_set('Asia/Jakarta');
    }

    public function blast()
    {
        if ($this->input->post()) {
            try {
                // Input validation
                $device = trim($this->input->post('device'));
                $pesan = trim($this->input->post('pesan'));
                $message_type = $this->input->post('type');
                $user_id = $this->session->userdata('id_login');

                // Validate required fields
                if (empty($device) || empty($pesan) || empty($message_type)) {
                    $this->session->set_flashdata('error', 'Device, message, and message type are required.');
                    redirect(base_url('blast'));
                }

                // Validate message type
                if (!in_array($message_type, ['1', '2', '3', '4'])) {
                    $this->session->set_flashdata('error', 'Invalid message type.');
                    redirect(base_url('blast'));
                }

                // Check device ownership
                $device_check = $this->db->get_where('device', [
                    'nomor' => $device,
                    'pemilik' => $user_id
                ]);

                if ($device_check->num_rows() == 0) {
                    $this->session->set_flashdata('error', 'Device not found or access denied.');
                    redirect(base_url('blast'));
                }

                // Handle media for type 2 (Text & Media)
                $media = null;
                if ($message_type == '2') {
                    $media_source = $this->input->post('media_source') ?: 'local';
                    if ($media_source == 'external') {
                        $media = trim($this->input->post('media_external'));
                        if (!empty($media) && !filter_var($media, FILTER_VALIDATE_URL)) {
                            $this->session->set_flashdata('error', 'Invalid media URL format.');
                            redirect(base_url('blast'));
                        }
                    } else {
                        $media = trim($this->input->post('media'));
                        if (!empty($media)) {
                            // Validate local media file exists
                            $media_path = FCPATH . 'storage/' . basename($media);
                            if (!file_exists($media_path)) {
                                $this->session->set_flashdata('error', 'Media file not found.');
                                redirect(base_url('blast'));
                            }
                        }
                    }
                }

                // Get target numbers
                $target = [];
                if ($this->input->post('all_number')) {
                    // Get all user contacts
                    $all_contacts = $this->db->get_where('nomor', ['make_by' => $user_id]);
                    if ($all_contacts->num_rows() == 0) {
                        $this->session->set_flashdata('error', 'No contacts found. Please add contacts first.');
                        redirect(base_url('blast'));
                    }
                    foreach ($all_contacts->result() as $contact) {
                        $target[] = $contact->nomor;
                    }
                } else {
                    $target = $this->input->post('listnumber');
                    if (empty($target) || !is_array($target)) {
                        $this->session->set_flashdata('error', 'Please select at least one recipient.');
                        redirect(base_url('blast'));
                    }
                }

                $target = array_unique($target);
                $queued_count = 0;

                // Process each target recipient
                foreach ($target as $recipient) {
                    try {
                        // Check if recipient is a label or direct number
                        $label_check = $this->db->get_where('nomor', [
                            'label' => $recipient,
                            'make_by' => $user_id
                        ]);

                        if ($label_check->num_rows() > 0) {
                            // Process all contacts with this label
                            $label_contacts = $label_check->result();
                            foreach ($label_contacts as $contact) {
                                $this->queue_blast_message($device, $contact->nomor, $contact->nama, $message_type, $pesan, $media, $user_id);
                                $queued_count++;
                            }
                        } else {
                            // Process direct number
                            $contact_check = $this->db->get_where('nomor', [
                                'nomor' => $recipient,
                                'make_by' => $user_id
                            ]);

                            if ($contact_check->num_rows() == 0) {
                                log_message('warning', 'BlastController::blast - Contact not found: ' . $recipient);
                                continue; // Skip invalid contacts
                            }

                            $contact = $contact_check->row();
                            $this->queue_blast_message($device, $recipient, $contact->nama, $message_type, $pesan, $media, $user_id);
                            $queued_count++;
                        }
                    } catch (Exception $e) {
                        log_message('error', 'BlastController::blast - Error processing recipient ' . $recipient . ': ' . $e->getMessage());
                        continue; // Continue with other recipients
                    }
                }

                // Success response
                if ($queued_count > 0) {
                    $this->session->set_flashdata('success', $queued_count . ' message(s) queued successfully. Status can be seen in the table below.');
                } else {
                    $this->session->set_flashdata('warning', 'No messages were queued. Please check your recipients.');
                }

            } catch (Exception $e) {
                log_message('error', 'BlastController::blast - Error processing blast: ' . $e->getMessage());
                $this->session->set_flashdata('error', 'An error occurred while processing your blast message.');
            }

            redirect(base_url('blast'));
        } else {
            $id_login = $this->session->userdata('id_login');
            $data = [
                'title' => 'Blast',
                'device' =>  $this->db->get_where('device', ['pemilik' => $id_login]),
                'nomor' => $this->db->get_where('nomor', ['make_by' => $id_login]),
                'blast' => $this->db->query("SELECT * FROM blast WHERE make_by='$id_login' ORDER BY id DESC"),
                'label' => $this->db->query('SELECT * FROM nomor WHERE label!="" GROUP BY label ORDER BY id DESC')
            ];
            view('blast', $data);
        }
    }

    public function blast_del()
    {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $this->db->query("DELETE FROM blast WHERE id IN(" . implode(",", $id) . ")");
            $this->session->set_flashdata('success', 'Successfully Delete the blast in the checklist.');
            redirect(base_url('blast'));
        } else {
            $this->session->set_flashdata('error', 'checklist that you want to delete.');
            redirect(base_url('blast'));
        }
    }
}
