<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Accounts extends CI_Controller {
	public function __construct()
	{
		// Load parent's constructor.
       	parent::__construct();
		$this->load->database();
		$this->load->helper('form');
        $this->load->helper('url_helper');
	    $this->load->library('session');
		$this->load->model('accounts_model');
		$this->load->model('Accounts_model');
		$this->load->model('billings_model');
		$this->load->helper('billing');
		$this->load->model('procedures_model');
		$this->load->model('Wallet_model');
		$this->load->model('Purchase_order_model');
		$this->load->model('billingmodel_model');
		$this->load->model('center_model');
		$this->load->model('stock_model');
		$this->load->helper('myhelper');
		$this->load->library("pagination");
		
	}
	
	private function _init_email() {
		$this->load->library('email');
		
		$config['protocol']     = 'smtp';
		$config['smtp_host']    = 'smtp.gmail.com'; 
		$config['smtp_port']    = 587;                     
		$config['smtp_crypto']  = 'tls'; // 👈 Port 587 के लिए यह लाइन अनिवार्य है
		$config['smtp_user']    = 'noreply@indiaivf.in'; 
		$config['smtp_pass']    = 'gnwc uadf rgqp bhse'; // App Password
		$config['mailtype']     = 'html';
		$config['charset']      = 'utf-8';
		$config['newline']      = "\r\n";
		$config['crlf']         = "\r\n";
		$config['smtp_timeout'] = '30';
		
		$this->email->initialize($config);
	}
	
		function htmltopdf(){
        	$formname = $_POST['formname'];
            $paitent_id = $_POST['iic_id'];
            $paitent_html = $_POST['html'];
            
            $dest_path = $this->config->item('upload_path');
            $destination = $dest_path.'whatsapp-pdf/';
            $NewImageName = $formname."-".$paitent_id;
            
            require_once $dest_path. '/mpdf/vendor/autoload.php';
            $filename= $dest_path."whatsapp-pdf/".$NewImageName.".pdf";
			//$filename= "https://indiaivf.website/assets/whatsapp-pdf/".$NewImageName.".pdf";
            $mpdf = new \Mpdf\Mpdf();
            $mpdf->WriteHTML($paitent_html);
            $mpdf->Output($filename,'F');
    		
    		$patient_data = get_patient_detail($paitent_id);
    		if($patient_data['whats_registers'] == 0){
    		    $register_patient = whatsappregister($patient_data['wife_phone']);
    		    if($register_patient > 0){
    		        $this->db->where('patient_id', $paitent_id);
    		        $this->db->update('hms_patients', array('whats_registers' => 1));
    		    }
    		}
    		$sendprep = whatsappfileprep($patient_data['wife_phone'], $filename);
    	    if($sendprep > 0){
    	        echo json_encode(array('status' => 1));
    	        die;
    	    }else{
    	        echo json_encode(array('status' => 0));
    	        die;
    	    }	
        }
        
    function billhtmltopdf(){
		    $receipt_number = $_POST['receipt_number'];
            $paitent_id = $_POST['iic_id'];
            $paitent_html = $_POST['html'];
			$dateTime = date("Ymd-His");
            
            $dest_path = $this->config->item('upload_path');
            $destination = $dest_path.'whatsapp-pdf/';
            $NewImageName = "Billing-" . $paitent_id . "-" . $dateTime;
            
            require_once $dest_path. '/mpdf/vendor/autoload.php';
            $filename= $dest_path."whatsapp-pdf/".$NewImageName.".pdf";
            $mpdf = new \Mpdf\Mpdf();
            $mpdf->WriteHTML($paitent_html);
            $mpdf->Output($filename,'F');
    		
    		$patient_data = get_patient_detail($paitent_id);
    		if($patient_data['whats_registers'] == 0){
    		    $register_patient = whatsappregister($patient_data['wife_phone']);
    		    if($register_patient > 0){
    		        $this->db->where('patient_id', $paitent_id);
    		        $this->db->update('hms_patients', array('whats_registers' => 1));
    		    }
    		}
    		$sendprep = whatsappfileprep($patient_data['wife_phone'], $filename, 'Billing Receipt', 'india_ivf_bill_sent');
    	    if($sendprep > 0){
    	        echo json_encode(array('status' => 1));
    	        die;
    	    }else{
    	        echo json_encode(array('status' => 0));
    	        die;
    	    }	
        }
        
    function prephtmltopdf(){
        $paitent_id = $_POST['iic_id'];
        $paitent_html = $_POST['html'];
        
        $dest_path = $this->config->item('upload_path');
        $destination = $dest_path.'whatsapp-pdf/';
        $NewImageName = "Billing-".$paitent_id;
        
        require_once $dest_path. '/mpdf/vendor/autoload.php';
        $filename= $dest_path."whatsapp-pdf/".$NewImageName.".pdf";
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($paitent_html);
        $mpdf->Output($filename,'F');
		
		$patient_data = get_patient_detail($paitent_id);
    
		if($patient_data['whats_registers'] == 0){
		    $register_patient = whatsappregister($patient_data['wife_phone']);
		    if($register_patient > 0){
		        $this->db->where('patient_id', $paitent_id);
		        $this->db->update('hms_patients', array('whats_registers' => 1));
		    }else{
		        echo json_encode(array('status' => 0, 'message' => $register_patient['message']));
	            die;
		    }
		}
		$sendprep = whatsappfileprep($patient_data['wife_phone'], $filename, 'Prescription', 'prescription_sent');
	    if($sendprep > 0){
	        echo json_encode(array('status' => 1, 'message' => "Prescription has been sent!"));
	        die;
	    }else{
	        echo json_encode(array('status' => 0, 'message' => $sendprep['message']));
	        die;
	    }	
    }

	public function accounts()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/accounts');
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function freezing()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/freezing');
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function get_patient_data(){
		$search_this = $_POST['data']['search_this'];
		$search_by = $_POST['data']['search_by'];
		if($search_this == ''){
			$response = array();
			$response = array('status' => 0, 'data'=> 'Phone number/IIC ID is required');
			echo json_encode($response);
			die;	
		}
		$consultation_result = $investigate_result = $procedure_result = $procedure_can_result = $patient_result = $medicine_result = $sales_result = $refund_amount_result = array();		
	   $data = $this->accounts_model->get_patient_data_by($search_this, $search_by);
	    //$medicine_result = $data['medicine_result'];
		//$sales_result = $data['sales_result'];
	if(!empty($data)){
		$patient_id = $data['patient_result']['patient_id'];
		$patient_data = get_patient_detail($patient_id);
		$currency = '';
		$consultation_result = $data['consultation_result'];
		$investigate_result = $data['investigate_result'];
		$procedure_result = $data['procedure_result'];
		$procedure_can_result = $data['procedure_can_result'];
		$patient_result = $data['patient_result'];
		$medicine_result = $data['medicine_result'];
		$sales_result = $data['sales_result'];
		$refund_amount_result = $data['refund_amount_result'];
		$payments = $data['payments'];	
		$response = array();
		if (!empty($patient_result))
        {
			$html = $payment_html = '';		
			if (count($consultation_result) > 0) {
    $type = $consultation_result['type'];
    foreach ($consultation_result['data'] as $key => $val) {
        
        // 1. Convert status safely if it is an array
        $status_val = $val['status'];
        if (is_array($status_val)) {
            $status_str = isset($status_val['name']) ? $status_val['name'] : (isset($status_val[0]) ? $status_val[0] : '');
        } else {
            $status_str = (string)$status_val;
        }

        // 2. Convert billing_from safely if it is an array
        $billing_from = is_array($val['billing_from']) ? ($val['billing_from']['name'] ?? '') : $val['billing_from'];

        // 3. Convert billing_at safely if it is an array
        $billing_at = is_array($val['billing_at']) ? ($val['billing_at']['id'] ?? '') : $val['billing_at'];

        // 4. Convert biller_id safely if it is an array
        $biller_id = is_array($val['biller_id']) ? ($val['biller_id']['id'] ?? '') : $val['biller_id'];

        // 5. Safely handle employee name (Fixes line 224 error)
        $employee_name = $this->get_employee_name($biller_id);
        if (is_array($employee_name)) {
            $employee_name_str = isset($employee_name['name']) ? $employee_name['name'] : (isset($employee_name[0]) ? $employee_name[0] : '');
        } else {
            $employee_name_str = (string) $employee_name;
        }

        $html .= '<tr>';
        $html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=consultation">'.$val['receipt_number'].'</a></td>';
        $html .= '<td>'.dateformat($val['on_date']).'</td>';
        $html .= '<td>'.$this->get_center_name($billing_at).'</td>';
        
        if ($billing_from == 'IndiaIVF') { 
            $html .= '<td>'.$billing_from.'</td>'; 
        } else {
            $html .= '<td>'.$this->get_center_name($billing_from).'</td>';
        } 
        
        $html .= '<td>'.$employee_name_str.'</td>';                    
        $html .= '<td>'.$currency.$val['totalpackage'].'</td>';
        $html .= '<td>'.$currency.$val['fees'].'</td>';
        $html .= '<td>'.$currency.$val['payment_done'].'</td>';
        $html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
        $html .= '<td>Consultation</td>';
        $html .= '<td>'.ucwords($status_str).'</td>';
        $html .= '</tr>';
    }
}	

if (count($investigate_result) > 0) {
    $type = $investigate_result['type'];
    foreach ($investigate_result['data'] as $key => $val) {
        
        // 1. Status ko string aur lowercase me convert karein
        $status_val = $val['status'];
        if (is_array($status_val)) {
            $status_str = isset($status_val['name']) ? $status_val['name'] : (isset($status_val[0]) ? $status_val[0] : '');
        } else {
            $status_str = (string)$status_val;
        }
        $status = strtolower($status_str);

        // 2. Safely extract billing_from, billing_at, aur biller_id
        $billing_from = is_array($val['billing_from']) ? ($val['billing_from']['name'] ?? '') : $val['billing_from'];
        $billing_at   = is_array($val['billing_at']) ? ($val['billing_at']['id'] ?? '') : $val['billing_at'];
        $biller_id    = is_array($val['biller_id']) ? ($val['biller_id']['id'] ?? '') : $val['biller_id'];

        // 3. Employee name warning fix
        $employee_name = $this->get_employee_name($biller_id);
        if (is_array($employee_name)) {
            $employee_name_str = isset($employee_name['name']) ? $employee_name['name'] : (isset($employee_name[0]) ? $employee_name[0] : '');
        } else {
            $employee_name_str = (string)$employee_name;
        }

        $html .= '<tr>';
        
        // [FIXED] Status Disapprove / Cancel hone par YA Remaining Amount <= 0 hone par "Add Payments" hide hoga
        $show_add_payment = in_array($status, ['pending', 'approved']) 
                            && !in_array($status, ['disapprove', 'disapproved', 'cancel', 'cancelled']) 
                            && (float)$val['remaining_amount'] > 0;

        $html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=investigation">'.$val['receipt_number'].'</a>' .
                 ($show_add_payment ? ' <a class="btn btn-large" target="_blank" href="'.base_url().'accounts/partial_procedure_billing/'.$val['receipt_number'].'?t=investigation">Add Payments</a>' : '') .
                 '</td>';

        $html .= '<td>'.dateformat($val['on_date']).'</td>';
        $html .= '<td>'.$this->get_center_name($billing_at).'</td>';
        
        if ($billing_from == 'IndiaIVF') { 
            $html .= '<td>'.$billing_from.'</td>'; 
        } else {
            $html .= '<td>'.$this->get_center_name($billing_from).'</td>';
        } 
        
        $html .= '<td>'.$employee_name_str.'</td>';
        $html .= '<td>'.$currency.$val['totalpackage'].'</td>';
        $html .= '<td>'.$currency.$val['fees'].'</td>';
        $html .= '<td>'.$currency.$val['payment_done'].'</td>';
        $html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
        $html .= '<td>Investigation</td>';
        $html .= '<td>'.ucwords($status_str).'</td>';
        $html .= '</tr>';
    }
}

// Step 1: Partial Payments se Billing-wise total calculation karein
$partial_payments_map = [];

if (!empty($payments) && is_array($payments) && isset($payments['data']) && is_array($payments['data'])) {
    foreach ($payments['data'] as $p_val) {
        // Sirf Approved Payments (status == 1) ko calculate karein
        $p_status = isset($p_val['status']) ? $p_val['status'] : 0;
        if ($p_status == 1) { 
            $bill_id = isset($p_val['billing_id']) ? $p_val['billing_id'] : '';
            $paid_amt = isset($p_val['payment_done']) ? (float)$p_val['payment_done'] : 0;
            
            if ($bill_id) {
                if (!isset($partial_payments_map[$bill_id])) {
                    $partial_payments_map[$bill_id] = 0;
                }
                $partial_payments_map[$bill_id] += $paid_amt;
            }
        }
    }
}

// Step 2: Main Procedure Loop me Add & Subtract apply karein
if (count($procedure_result) > 0) {
    $type = $procedure_result['type'];
    
    foreach ($procedure_result['data'] as $key => $val) {
        
        // 1. Safely extract status as a string
        $status_val = $val['status'];
        if (is_array($status_val)) {
            $status_str = isset($status_val['status']) ? $status_val['status'] : (isset($status_val[0]) ? $status_val[0] : '');
        } else {
            $status_str = (string) $status_val;
        }
        $status = strtolower($status_str);

        // 2. Safely extract series_number as a string
        $series_num = is_array($val['series_number']) ? implode(', ', $val['series_number']) : $val['series_number'];

        // 3. Safely extract billing_from as a string
        $billing_from = is_array($val['billing_from']) ? ($val['billing_from']['name'] ?? '') : $val['billing_from'];

        // 4. Safely extract biller_id and Employee Name
        $biller_id = is_array($val['biller_id']) ? ($val['biller_id']['id'] ?? '') : $val['biller_id'];
        $employee_name = $this->get_employee_name($biller_id);
        if (is_array($employee_name)) {
            $employee_name_str = isset($employee_name['name']) ? $employee_name['name'] : (isset($employee_name[0]) ? $employee_name[0] : '');
        } else {
            $employee_name_str = (string) $employee_name;
        }

        // ==========================================
        // PARTIAL PAYMENT ADJUSTMENT HERE
        // ==========================================
        $receipt_num = $val['receipt_number'];
        $extra_paid  = isset($partial_payments_map[$receipt_num]) ? (float)$partial_payments_map[$receipt_num] : 0;

        $final_payment_done   = (float)$val['payment_done'] + $extra_paid;
        $final_remaining_amt = (float)$val['remaining_amount'] - $extra_paid;

        // Prevent negative remaining balance (Edge-case safe)
        if ($final_remaining_amt < 0) {
            $final_remaining_amt = 0;
        }

        $html .= '<tr>';
        
        // [FIXED] Condition update ki gayi: Tabhi Add Payments dikhe jab $final_remaining_amt > 0 ho
        $html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a>' . 
                 (in_array($status, ['pending', 'approved']) && $final_remaining_amt > 0 ? ' <a class="btn btn-large" target="_blank" href="'.base_url().'accounts/partial_procedure_billing/'.$val['receipt_number'].'?t=procedure">Add Payments</a>' : '') . 
                 '<br/>' . $series_num . '</td>';
                 
        $html .= '<td>'.dateformat($val['on_date']).'</td>';
        $html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
        
        if ($billing_from == 'IndiaIVF') { 
            $html .= '<td>'.$billing_from.'</td>'; 
        } else {
            $html .= '<td>'.$this->get_center_name($billing_from).'</td>';
        } 
        
        $html .= '<td>'.$employee_name_str.'</td>';
        
        $html .= '<td>'.$currency.$val['totalpackage'].'</td>';
        $html .= '<td>'.$currency.$val['fees'].'</td>';
        
        // Dynamic Calculated Values
        $html .= '<td>'.$currency.number_format($final_payment_done, 2).'</td>';
        $html .= '<td>'.$currency.number_format($final_remaining_amt, 2).'</td>';
        
        $html .= '<td>Procedure</td>';
        $html .= '<td>'.ucwords($status_str).'</td>';
        
        if (!empty($val['data'])) {
            $procedure_data = is_array($val['data']) ? $val['data'] : @unserialize($val['data']);
            if (isset($procedure_data['patient_procedures']) && is_array($procedure_data['patient_procedures'])) {
                foreach ($procedure_data['patient_procedures'] as $v2_data) {
                    $sql1 = "select * from hms_procedures where code='".$v2_data['sub_procedures_code']."'";
                    $query = $this->db->query($sql1);
                    $select_result1 = $query->result(); 
                    foreach ($select_result1 as $res_val) {
                        $html .= '<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
                    }
                }
            }
        }
        $html .= '</tr>';
    }
}

			if (!empty($procedure_can_result) && is_array($procedure_can_result) && count($procedure_can_result) > 0) {
		$type = isset($procedure_can_result['type']) ? $procedure_can_result['type'] : '';
                
                if (isset($procedure_can_result['data']) && is_array($procedure_can_result['data'])) {
                    foreach ($procedure_can_result['data'] as $key => $val) {
                        
                        // 1. Safely extract billing_from, billing_at, biller_id, aur cn_invoice
                        $billing_from = is_array($val['billing_from']) ? ($val['billing_from']['name'] ?? '') : $val['billing_from'];
                        $billing_at   = is_array($val['billing_at']) ? ($val['billing_at']['id'] ?? '') : $val['billing_at'];
                        $biller_id    = is_array($val['biller_id']) ? ($val['biller_id']['id'] ?? '') : $val['biller_id'];
                        $cn_invoice   = is_array($val['cn_invoice']) ? implode(', ', $val['cn_invoice']) : $val['cn_invoice'];

                        // 2. Employee Name ko safely fetch aur format karein (FIX FOR EMPLOYEE NAME)
                        $employee_name = $this->get_employee_name($biller_id);
                        if (is_array($employee_name)) {
                            $employee_name_str = isset($employee_name['name']) ? $employee_name['name'] : (isset($employee_name[0]) ? $employee_name[0] : '');
                        } else {
                            $employee_name_str = (string) $employee_name;
                        }

                        // --- [FIX: NEGATIVE BALANCE FOR CREDIT NOTES / CANCELLATION] ---
                        $totalpackage     = -abs((float)$val['totalpackage']);
                        $fees             = -abs((float)$val['fees']);
                        $payment_done     = -abs((float)$val['payment_done']);
                        $remaining_amount = -abs((float)$val['remaining_amount']);

                        $html .= '<tr>';
                        $html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a> </td>';
                        $html .= '<td>'.dateformat($val['modified_on']).'</td>';
                        $html .= '<td>'.$this->get_center_name($billing_at).'</td>';
                        
                        if ($billing_from == 'IndiaIVF') { 
                            $html .= '<td>'.$billing_from.'</td>'; 
                        } else {
                            $html .= '<td>'.$this->get_center_name($billing_from).'</td>';
                        } 
                        
                        // Employee Name Column
                        $html .= '<td>'.$employee_name_str.'</td>';

                        // Amounts with Negative Balance Formatting
                        $html .= '<td>'. $currency . number_format($totalpackage, 2) . '</td>';
                        $html .= '<td>'. $currency . number_format($fees, 2) . '</td>';
                        $html .= '<td>'. $currency . number_format($payment_done, 2) . '</td>';
                        $html .= '<td>'. $currency . number_format($remaining_amount, 2) . '</td>';
                        $html .= '<td>Procedure</td>';
                        $html .= '<td>Credit Notes</td>';
                        
                        // 3. Safely handle procedure serialized data
                        if (!empty($val['data'])) {
                            $procedure_data = is_array($val['data']) ? $val['data'] : @unserialize($val['data']);
                            if (isset($procedure_data['patient_procedures']) && is_array($procedure_data['patient_procedures'])) {
                                foreach ($procedure_data['patient_procedures'] as $v2_data) {
                                    $sql1 = "select * from hms_procedures where code='".$this->db->escape_str($v2_data['sub_procedures_code'])."'";
                                    $query = $this->db->query($sql1);
                                    $select_result1 = $query->result(); 
                                    foreach ($select_result1 as $res_val) {
                                        // Item paid price ko bhi negative me format kar diya gaya hai
                                        $sub_price = -abs((float)$v2_data['sub_procedures_paid_price']);
                                        $html .= '<td>'.$res_val->procedure_name.','.$currency.number_format($sub_price, 2).'</td>';
                                    }
                                }
                            }
                        }
                        $html .= '</tr>';
                    }
                }
            }
			
			/*if(count($medicine_result) > 0){
				$type = $medicine_result['type'];
				foreach($medicine_result['data'] as $key => $val){
					$html .= '<tr>';
					$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=medicine">'.$val['receipt_number'].'</a></td>';
					$html .= '<td>'.dateformat($val['on_date']).'</td>';
					$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
					if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
					else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}
					$html .= '<td>'.$this->get_employee_name($val['biller_id']).'</td>';
					$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
					$html .= '<td>'.$currency.$val['fees'].'</td>';
					$html .= '<td>'.$currency.$val['payment_done'].'</td>';
					$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
					$html .= '<td>Medicine</td>';
					$html .= '<td>'.ucwords($val['status']).'</td>';
					$html .= '</tr>';
				}
			}*/

			if(count($sales_result) > 0){
				$type = $sales_result['type'];
				foreach($sales_result['data'] as $key => $val){
					$html .= '<tr>';
					$html .= '<td><a class="btn btn-large" href="'.base_url().'stocks_new/print_sale/'.$val->id.'" target="_blank">SALE-'.$val->id.'</a></td>';
					$html .= '<td>'.dateformat($val->sale_date).'</td>';
					$html .= '<td>'.$val->center_name.'</td>';
					$html .= '<td>'.$val->center_name.'</td>';
					$html .= '<td>'.$val->salesperson_name.'</td>';
					$html .= '<td>'.$currency.$val->subtotal.'</td>';
					$html .= '<td>'.$currency.$val->total_amount.'</td>';
					$html .= '<td>'.$currency.$val->total_amount.'</td>';
					$html .= '<td>'.$currency.'0.00</td>';
					$html .= '<td>Medicine</td>';
					$html .= '<td>'.ucwords($val->status).'</td>';
					$html .= '</tr>';
				}
			}

			if(count($refund_amount_result) > 0){
				$type = $refund_amount_result['type'];
				foreach($refund_amount_result['data'] as $key => $val){	
					$html .= '<tr>';
					$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=medicine">'.$val['receipt_number'].'</a></td>';
					$html .= '<td>'.dateformat($val['on_date']).'</td>';
					$html .= '<td>Consultation Fee<br/>'.$currency.$val['consultation_fee'].'</td>';
					$html .= '<td>USG Scan Charge<br/>'.$currency.$val['usg_scan_charge'].'</td>';
					$html .= '<td>Consumable Charges<br/>'.$currency.$val['consumable_charges'].'</td>';
					$html .= '<td>File Registation Charge<br/>'.$currency.$val['file_registation_charge'].'</td>';
					$html .= '<td>Refund Amount<br/>'.$currency.$val['refund_amount'].'</td>';
					$html .= '<td>Refund Amount</td>';
					if($val['status'] == 1){ $html .= '<td>Approved</td>'; }
					else{$html .= '<td>Pending</td>';}	
					$html .= '</tr>';
				}
			}

if (!empty($payments) && is_array($payments) && count($payments) > 0) {
    $type = isset($payments['type']) ? $payments['type'] : '';
    
    if (isset($payments['data']) && is_array($payments['data'])) {
        
        // SORTING: Oldest date at top, Newest date at bottom
        usort($payments['data'], function($a, $b) {
            $dateA = strtotime(isset($a['on_date']) ? $a['on_date'] : 0);
            $dateB = strtotime(isset($b['on_date']) ? $b['on_date'] : 0);
            return $dateA <=> $dateB; // Ascending Order (Old -> New)
        });

        foreach ($payments['data'] as $key => $val) {
            
            // 1. Safely extract billing_from, billing_at, and employee_number
            $billing_from    = is_array($val['billing_from']) ? ($val['billing_from']['name'] ?? '') : $val['billing_from'];
            $billing_at      = is_array($val['billing_at']) ? ($val['billing_at']['id'] ?? '') : $val['billing_at'];
            $employee_number = is_array($val['employee_number']) ? ($val['employee_number']['id'] ?? '') : $val['employee_number'];
            $series_num      = is_array($val['series_number']) ? implode(', ', $val['series_number']) : $val['series_number'];

            // 2. Safely get Employee Name without triggering Array to String conversion warning
            $employee_name = $this->get_employee_name($employee_number);
            if (is_array($employee_name)) {
                $employee_name_str = isset($employee_name['name']) ? $employee_name['name'] : (isset($employee_name[0]) ? $employee_name[0] : '');
            } else {
                $employee_name_str = (string) $employee_name;
            }

            $payment_html .= '<tr>';
            $payment_html .= '<td><a target="_blank" class="btn btn-large" href="'.base_url().'accounts/details/'.$val['billing_id'].'?t='.$val['type'].'">'.$val['billing_id'].'</a><br/> ' . $series_num . '</td>';
            $payment_html .= '<td><a target="_blank" href="'.base_url().'partial-payment-receipt/'.$val['refrence_number'].'">'.$val['refrence_number'].'</a></td>';
            $payment_html .= '<td><a target="_blank" href="'.base_url().'accounts/patient_details/'.$patient_id.'">'.$patient_id.'</a></td>';
            $payment_html .= '<td>'.(isset($patient_data['wife_name']) ? $patient_data['wife_name'] : '').'</td>';
            $payment_html .= '<td>'.$this->get_center_name($billing_at).'</td>';
            
            if ($billing_from == 'IndiaIVF') { 
                $payment_html .= '<td>'.$billing_from.'</td>'; 
            } else {
                $payment_html .= '<td>'.$this->get_center_name($billing_from).'</td>';
            } 
            
            $payment_html .= '<td>'.$employee_name_str.'</td>';
            $payment_html .= '<td>'.$currency.$val['payment_done'].'</td>';
            $payment_html .= '<td>'.$val['payment_method'].'</td>';
            $payment_html .= '<td>'.$val['transaction_id'];
            
            if (!empty($val['transaction_img'])) {
                $payment_html .= ' (<a href="'.$val['transaction_img'].'" class="hide_print" target="_blank">Transaction Image</a>)</td>';
            } else {
                $payment_html .= '</td>';
            }
            
            $payment_html .= '<td>'.$val['on_date'].'</td>';
            
            // Status Badges
            if ($val['status'] == 1) { 
                $payment_html .= '<td>Approved</td>'; 
            } else if ($val['status'] == 2) { 
                $payment_html .= '<td>Disapproved</td>'; 
            } else if ($val['status'] == 3) { 
                $payment_html .= '<td>Cancel</td>'; 
            } else {
                $payment_html .= '<td>Pending</td>';
            }  
            
            // 3. Safely unserialize procedures data
            if (!empty($val['data'])) {
                $procedure_data = is_array($val['data']) ? $val['data'] : @unserialize($val['data']);
                if (isset($procedure_data['patient_procedures']) && is_array($procedure_data['patient_procedures'])) {
                    foreach ($procedure_data['patient_procedures'] as $v2_data) {
                        $code = isset($v2_data['sub_procedure']) ? $v2_data['sub_procedure'] : '';
                        $payment_html .= '<td>' . $code . '</td>';
                    }
                }
            }                   
            
            $payment_html .= '</tr>';
        }
    }
}
			// 🚀 FETCH WALLET BALANCES FROM DATABASE
        $wallet_info = $this->db->get_where('hms_patient_wallets', array('patient_id' => $patient_id))->row_array();
        $wallet_1 = isset($wallet_info['wallet_1_balance']) ? $wallet_info['wallet_1_balance'] : 0;
        $wallet_2 = isset($wallet_info['wallet_2_balance']) ? $wallet_info['wallet_2_balance'] : 0;

        $response = array(
            'data' => $html,
            'current_balance' => patient_balance($patient_id),
            'wallet_1_balance' => $wallet_1,
            'wallet_2_balance' => $wallet_2,
            'patient_name' => $patient_result['wife_name'],
            'husband_name' => $patient_result['husband_name'],
            'patient_phone' => sting_masking($patient_result['wife_phone']),
            'payment_html' => $payment_html
        );
			//$response = array('data' => $html,'current_balance' => patient_balance($patient_id), 'patient_name'=> $patient_result['wife_name'], 'husband_name'=> $patient_result['husband_name'], 'patient_phone'=> sting_masking($patient_result['wife_phone']), 'payment_html' => $payment_html);
			echo json_encode($response);
			die;
        }else{
			$response = array('data' => 'No record found!', 'patient_name'=> '', 'patient_email'=> '', 'patient_phone'=> '', 'payment_html' => '');
			echo json_encode($response);
			die;
		}
	}else{
		$response = array('data' => 'No record found!', 'patient_name'=> '', 'patient_email'=> '', 'patient_phone'=> '', 'payment_html' => '');
		echo json_encode($response);
		die;
	}

	}
	
	public function get_patient_freezing(){
		$search_this = $_POST['data']['search_this'];
		$search_by = $_POST['data']['search_by'];
		if($search_this == ''){
			$response = array();
			$response = array('status' => 0, 'data'=> 'Phone number/IIC ID is required');
			echo json_encode($response);
			die;	
		}
		$consultation_result = $freezing_result  = $investigate_result = $procedure_result = $patient_result = array();		
		
	$data = $this->accounts_model->get_patient_data_by($search_this, $search_by);
	
	
	if(!empty($data)){
		$patient_id = $data['patient_result']['patient_id'];
		$patient_data = get_patient_detail($patient_id);
		$currency = '';
	
		$procedure_result = $data['procedure_result'];
		$patient_result = $data['patient_result'];
		$investigate_result = $data['investigate_result'];
		$payments = $data['payments'];	
		
		$response = array();
		if (!empty($patient_result))
        {
			$html = $payment_html = '';		
			
		if(count($procedure_result) > 0){
			$type = $procedure_result['type'];
			foreach($procedure_result['data'] as $key => $val){
				if(!empty($val['data'])){
					$procedure_data = unserialize($val['data']);
					foreach ($procedure_data['patient_procedures'] as $v2_data){
						$sql1 = "select * from hms_procedures where code='".$v2_data['sub_procedures_code']."'";
                        $query = $this->db->query($sql1);
                        $select_result1 = $query->result(); 
					        foreach ($select_result1 as $res_val){
                                if($v2_data['sub_procedures_code'] == "IP03" ){								
									$html .= '<tr>';
									$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a></td>';
									$html .= '<td>'.dateformat($val['on_date']).'</td>';
									$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
									if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
									else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
									$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
									$html .= '<td>'.$currency.$val['fees'].'</td>';
									$html .= '<td>'.$currency.$val['payment_done'].'</td>';
									$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
									$html .= '<td>Procedure</td>';
									
									$html .='<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
									
									$html .= '<td>'.ucwords($val['status']).'</td>';
									$html .= '</tr>';
				                }
								if($v2_data['sub_procedures_code'] == "IP14" ){								
									$html .= '<tr>';
									$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a></td>';
									$html .= '<td>'.dateformat($val['on_date']).'</td>';
									$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
									if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
									else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
									$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
									$html .= '<td>'.$currency.$val['fees'].'</td>';
									$html .= '<td>'.$currency.$val['payment_done'].'</td>';
									$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
									$html .= '<td>Procedure</td>';
									
									$html .='<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
									
									$html .= '<td>'.ucwords($val['status']).'</td>';
									$html .= '</tr>';
								}
								if($v2_data['sub_procedures_code'] == "IP17" ){								
									$html .= '<tr>';
									$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a></td>';
									$html .= '<td>'.dateformat($val['on_date']).'</td>';
									$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
									if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
									else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
									$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
									$html .= '<td>'.$currency.$val['fees'].'</td>';
									$html .= '<td>'.$currency.$val['payment_done'].'</td>';
									$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
									$html .= '<td>Procedure</td>';
									
									$html .='<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
									
									$html .= '<td>'.ucwords($val['status']).'</td>';
									$html .= '</tr>';
								}
								if($v2_data['sub_procedures_code'] == "IP18" ){								
									$html .= '<tr>';
									$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a></td>';
									$html .= '<td>'.dateformat($val['on_date']).'</td>';
									$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
									if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
									else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
									$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
									$html .= '<td>'.$currency.$val['fees'].'</td>';
									$html .= '<td>'.$currency.$val['payment_done'].'</td>';
									$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
									$html .= '<td>Procedure</td>';
									
									$html .='<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
									
									$html .= '<td>'.ucwords($val['status']).'</td>';
									$html .= '</tr>';
								}
								if($v2_data['sub_procedures_code'] == "IP12" ){								
									$html .= '<tr>';
									$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a></td>';
									$html .= '<td>'.dateformat($val['on_date']).'</td>';
									$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
									if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
									else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
									$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
									$html .= '<td>'.$currency.$val['fees'].'</td>';
									$html .= '<td>'.$currency.$val['payment_done'].'</td>';
									$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
									$html .= '<td>Procedure</td>';
									
									$html .='<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
									
									$html .= '<td>'.ucwords($val['status']).'</td>';
									$html .= '</tr>';
								}
								if($v2_data['sub_procedures_code'] == "IP96" ){								
									$html .= '<tr>';
									$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a></td>';
									$html .= '<td>'.dateformat($val['on_date']).'</td>';
									$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
									if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
									else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
									$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
									$html .= '<td>'.$currency.$val['fees'].'</td>';
									$html .= '<td>'.$currency.$val['payment_done'].'</td>';
									$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
									$html .= '<td>Procedure</td>';
									
									$html .='<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
									
									$html .= '<td>'.ucwords($val['status']).'</td>';
									$html .= '</tr>';
								}
								if($v2_data['sub_procedures_code'] == "IP65" ){								
									$html .= '<tr>';
									$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=procedure">'.$val['receipt_number'].'</a></td>';
									$html .= '<td>'.dateformat($val['on_date']).'</td>';
									$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
									if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
									else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
									$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
									$html .= '<td>'.$currency.$val['fees'].'</td>';
									$html .= '<td>'.$currency.$val['payment_done'].'</td>';
									$html .= '<td>'.$currency.$val['remaining_amount'].'</td>';
									$html .= '<td>Procedure</td>';
									
									$html .='<td>'.$res_val->procedure_name.','.$v2_data['sub_procedures_paid_price'].'</td>';
									
									$html .= '<td>'.ucwords($val['status']).'</td>';
									$html .= '</tr>';
								}
							}
					    }
						
					}
					
					
				}
				
			}
			
			if(count($investigate_result) > 0){
				$sql2 = "select * from freezing where patient_id='".$data['patient_result']['patient_id']."'";
						   $query = $this->db->query($sql2);
                            $select_result2 = $query->result(); 
							 foreach ($select_result2 as $res_val2){
								 $html .= '<tr>';	
					
								$html .= '<td>'.$res_val2['freezing_date'].'</td>';
								$html .= '<td>'.$res_val2['expiry_date'].'</td>';
				$type = $investigate_result['type'];
				foreach($investigate_result['data'] as $key => $val){
					$
								// echo "<pre>";
								// print_r($res_val2);
					            // echo "</pre>";
								 //exit();
							 
					$html .= '<td><a class="btn btn-large" href="'.base_url().'accounts/details/'.$val['receipt_number'].'?t=investigation">'.$val['receipt_number'].'</a></td>';
					$html .= '<td>'.dateformat($val['on_date']).'</td>';
					$html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
					if($val['billing_from'] == 'IndiaIVF'){ $html .= '<td>'.$val['billing_from'].'</td>'; }
					else{$html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
					$html .= '<td>'.$currency.$val['totalpackage'].'</td>';
					$html .= '<td>'.$currency.$val['fees'].'</td>';
					$html .= '<td>'.$currency.$val['payment_done'].'</td>';
					$html .= '<td>'.$res_val2['freezing_date'].'</td>';
					
				}
				$html .= '<td>Investigation</td>';
				$html .= '<td>'.ucwords($val['status']).'</td>';
				$html .= '</tr>';
				}
			}

			if(count($payments) > 0){
				$type = $payments['type'];
				foreach($payments['data'] as $key => $val){ //var_dump($val);die;
					$payment_html .= '<tr>';
					$payment_html .= '<td><a target="_blank" class="btn btn-large" href="'.base_url().'accounts/details/'.$val['billing_id'].'?t='.$val['type'].'">'.$val['billing_id'].'</a></td>';
					$payment_html .= '<td><a target="_blank" href="'.base_url().'partial-payment-receipt/'.$val['refrence_number'].'">'.$val['refrence_number'].'</a></td>';
					$payment_html .= '<td><a target="_blank" href="'.base_url().'accounts/patient_details/'.$patient_id.'">'.$patient_id.'</a></td>';
					$payment_html .= '<td>'.$patient_data['wife_name'].'</td>';
					$payment_html .= '<td>'.$this->get_center_name($val['billing_at']).'</td>';
					if($val['billing_from'] == 'IndiaIVF'){ $payment_html .= '<td>'.$val['billing_from'].'</td>'; }
					else{$payment_html .= '<td>'.$this->get_center_name($val['billing_from']).'</td>';}							
					$payment_html .= '<td>'.$currency.$val['payment_done'].'</td>';
					$payment_html .= '<td>'.$val['payment_method'].'</td>';
					$payment_html .= '<td>'.$val['transaction_id'];
					if(!empty($val['transaction_img'])){
					  $payment_html .= '(<a href="'.$val['transaction_img'].'" class="hide_print"  target="_blank">Transaction Image</a>) </td>';
					}else{
					    $payment_html .= '</td>';
					}
					
					$payment_html .= '<td>'.$val['on_date'].'</td>';
					if($val['status'] == 1){ $payment_html .= '<td>Approved</td>'; }
					else if($val['status'] == 2){ $payment_html .= '<td>Disapproved</td>'; }
					else{$payment_html .= '<td>Pending</td>';}							
					$payment_html .= '</tr>';
				}
			}
			
			$response = array('data' => $html,'current_balance' => patient_balance($patient_id), 'patient_name'=> $patient_result['wife_name'], 'husband_name'=> $patient_result['husband_name'], 'patient_phone'=> sting_masking($patient_result['wife_phone']), 'payment_html' => $payment_html);
			echo json_encode($response);
			die;
        }else{
			$response = array('data' => 'No record found!', 'patient_name'=> '', 'patient_email'=> '', 'patient_phone'=> '', 'payment_html' => '');
			echo json_encode($response);
			die;
		}
	}else{
		$response = array('data' => 'No record found!', 'patient_name'=> '', 'patient_email'=> '', 'patient_phone'=> '', 'payment_html' => '');
		echo json_encode($response);
		die;
	}

	}

	public function freezing_renewal(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$iic_id = $this->input->get('iic_id', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/freezing_renewal";
        	$config["total_rows"] = $this->accounts_model->freezing_renewal_count($start_date, $end_date, $iic_id);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['freezing_result'] = $this->accounts_model->freezing_renewal_list_patination($config["per_page"], $per_page, $start_date, $end_date, $iic_id);
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["iic_id"] = $iic_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/freezing_renewal', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	

    public function investigation_patients(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$status = $this->input->get('status', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_investigation_data($start_date, $status, $end_date, $center, $patient_id, $payment_method);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Investigation-Patients-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name, Receipt Number, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type, Date, Status, Origins,Series Number';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					
					$sql4 = "SELECT * FROM hms_centers WHERE center_number='" . $val['origins'] . "'";
					$select_result4 = run_select_query($sql4);
					
					$origin_from = $select_result4['center_name'];
					
					$billing_at = get_center_name($val['billing_at']);
					
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['receipt_number'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at, $val['billing_type'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status'], $origin_from, $val['series_number']);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/investigation_patients";
        	$config["total_rows"] = $this->accounts_model->patient_investigation_count($center, $status, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['investigate_result'] = $this->accounts_model->patient_investigation_list_patination($config["per_page"], $per_page, $center, $status, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["status"] = $status;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/investigation_patients', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}  	
	
	
	public function procedure_patients(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$status = $this->input->get('status', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_procedure_data($start_date, $end_date, $center, $patient_id, $payment_method, $biller_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Procedure-Patients-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name,Receipt Number, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type, Date, Status,Series Number';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], "'" . $val['receipt_number'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at, $val['billing_type'], $val['on_date'], $val['status'],$val['series_number']);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/procedure_patients";
        	$config["total_rows"] = $this->accounts_model->patient_procedure_count($center, $status, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->patient_procedure_list_patination($config["per_page"], $per_page, $center, $status, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
			$data["billing_at"] = $center;
			$data["status"] = $status;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/procedure_patients', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	
	public function consultation_patients(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$status = $this->input->get('status', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_consultation_patients_data($start_date,$status, $end_date, $center, $patient_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=consultation-Patients-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type, Date, Status';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at, $val['billing_type'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status']);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/consultation_patients";
        	$config["total_rows"] = $this->accounts_model->consultation_patients_count($center,$status, $start_date, $end_date, $patient_id,$doctor_id = null);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_result'] = $this->accounts_model->patient_consultation_list_patination($config["per_page"], $per_page, $center,$start_date, $end_date, $patient_id, $status);
			$data["billing_at"] = $center;
			$data["status"] = $status;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/consultation_patients', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function registration_patients(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$status = $this->input->get('status', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('patient_id', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_registration_data($center, $status, $start_date, $end_date, $patient_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Registration-Patients-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type, Date, Status,Series Number';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'],$val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at, $val['billing_type'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status'],$val['series_number']);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/registration_patients";
        	$config["total_rows"] = $this->accounts_model->patient_registration_count($center, $status, $start_date, $end_date, $patient_id);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['registration_result'] = $this->accounts_model->patient_registration_list_patination($config["per_page"], $per_page, $center, $status, $start_date, $end_date, $patient_id);
			$data["billing_at"] = $center;
			$data["status"] = $status;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/registration_patients', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function partialpayments_request(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$status = $this->input->get('status', true);
			$export_billing = $this->input->get('export-billing', true);
if (isset($export_billing)){
    $data = $this->accounts_model->export_partialpayments_data($start_date, $end_date, $center, $patient_id, $status);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Partialpayments-'.$start_date.'-'.$end_date.'.csv');
    $fp = fopen('php://output','w');
    
    // Headers
    $headers = array(
        'IIC ID', 'Patient Name', 'Payment ID', 'Total package', 
        'Discounted Package', 'Paid Amount', 'Remaining Amount', 
        'Payment Method', 'Billing From', 'Billing At', 
        'Procedure Name', 'Billing Type', 'Date', 'Series Number', 
        'Status', 'Booking Date'
    );
    
    // Add headers using fputcsv for proper formatting
    fputcsv($fp, $headers);

    foreach ($data as $key => $val) {
        $billing_from = $val['billing_from'];
        if($billing_from != "IndiaIVF"){
            $billing_from = get_center_name($billing_from);
        }
        $billing_at = get_center_name($val['billing_at']);
        
        // [FIX] "\t" prepend karne se Excel billing_id ko Text ki tarah padhega aur digits 0000 nahi honge
        $billing_id = "\t" . $val['billing_id'];

        $lead_arr = array(
            $val['patient_id'], 
            $val['wife_name'],
            $billing_id, // Updated as string text
            $val['totalpackage'], 
            $val['discounted_package'], 
            $val['payment_done'], 
            $val['remaining_amount'], 
            $val['payment_method'], 
            $billing_from, 
            $billing_at,
            $val['procedure_name'],
            $val['billing_type'], 
            date('Y-m-d H:i:s', strtotime($val['date'])), 
            $val['series_number'], 
            $val['status'],
            $val['booking_date']
        );
        
        fputcsv($fp, $lead_arr);
    }
    fclose($fp);
    exit();
}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/partialpayments_request";
        	$config["total_rows"] = $this->accounts_model->patient_partialpayments_count($center, $start_date, $end_date, $patient_id, $status, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['partialpayments_result'] = $this->accounts_model->patient_partialpayments_list_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $status, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["status"] = $status;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/partialpayments_request', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}


	   public function partialpayments_report(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$billing_id = $this->input->get('billing_id', true);
			$payment_method = $this->input->get('payment_method', true);
			$status = $this->input->get('status', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_partialpayments_report_data($start_date, $end_date, $center, $patient_id, $status);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Partialpaymentsreport-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'Lead ID, Lead Source, IIC ID, Patient Name, Package ID, Receipt Number, Paid Amount, Payment Method, Billing At, Billing Type, Date, Payment Type, Center Name,Booking Date, Status,Origin';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$sql2 = "SELECT * FROM hms_patient_procedure WHERE receipt_number='" . $val['billing_id'] . "'";
					$select_result = run_select_query($sql2);
					
					$booking_date = $select_result['on_date'];
					
					$billing_at = get_center_name($val['billing_at']);
					
					$sql = "SELECT * FROM hms_appointments WHERE paitent_id='" . $val['patient_id'] . "'";
					$appoint_result = run_select_query($sql);

					$sql3 = "SELECT * FROM hms_appointments WHERE wife_phone='" . $appoint_result['wife_phone'] . "' and paitent_type='new_patient'";
					$select_result3 = run_select_query($sql3);

					$lead_id = $select_result3['crm_id'];
					$lead_source = $select_result3['lead_source'];
					
					$sql4 = "SELECT * FROM hms_centers WHERE center_number='" . $select_result3['appoitment_for'] . "'";
					$select_result4 = run_select_query($sql4);
					
					$lead_arr = array($lead_id, $lead_source, $val['patient_id'], $val['wife_name'], $val['billing_id'],$val['refrence_number'], $val['payment_done'], $val['payment_method'], $billing_at, $val['type'], date('Y-m-d H:i:s', strtotime($val['date'])),$val['billing_type'],$val['biller'],$booking_date,$val['status'],$select_result4['center_name']);
					
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/partialpayments_report";
        	$config["total_rows"] = $this->accounts_model->patient_partialpayments_count($center, $start_date, $end_date, $patient_id, $status, $payment_method);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['partialpayments_result'] = $this->accounts_model->patient_partialpayments_list_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $status,$payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["status"] = $status;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/partialpayments_report', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	} 
	
	
	
	
public function investigation_sales(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$payment_method = $this->input->get('payment_method', true);
			$export_billing = $this->input->get('export-billing', true);
			$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			if (isset($export_billing)){
				$data = $this->accounts_model->export_investigation_data($start_date, $status, $end_date, $center, $patient_id, $payment_method);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Investigation-Sales-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name,Receipt Number, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type,Investigation, Date, Status, Series Number';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$paid_amount = $paid_amount +  (int)$val['payment_done'];
					$total_package = $total_package +  (int)$val['totalpackage'];
					$discounted_package = $discounted_package +  (int)$val['discounted_package'];
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['receipt_number'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at, $val['billing_type'],$val['investigation'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status'], $val['series_number']);
					fputcsv($fp, $lead_arr);
				}
				$final_arr = array("", "", "", $total_package, $discounted_package, $paid_amount, "", "", "", "", "", "", "");
				fputcsv($fp, $final_arr);
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/investigation_sales";
        	$config["total_rows"] = $this->accounts_model->patient_investigation_count($center, $status, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['investigate_result'] = $this->accounts_model->patient_investigation_list_patination($config["per_page"], $per_page, $center, $status, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/investigation_sales', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	
	
	
	public function medicie_sales(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
		$center = $this->input->get('billing_at', true);
		$start_date = $this->input->get('start_date', true);
		$end_date = $this->input->get('end_date', true);
		$patient_id = $this->input->get('iic_id', true);
		$payment_method = $this->input->get('payment_method', true);
		$status = $this->input->get('status', true);
		$export_billing = $this->input->get('export-billing', true);
		
		$config = array();
    	$config["base_url"] = base_url() . "accounts/medicie_sales";
    	$config["total_rows"] = $this->accounts_model->patient_investigation_count($center, $status, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 50;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['investigate_result'] = $this->accounts_model->patient_investigation_list_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/medicie_sales', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
		


public function procedure_reports(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$biller_id = $this->input->get('biller_id', true);
			$payment_method = $this->input->get('payment_method', true);
			$export_billing = $this->input->get('export-billing', true);
			$paid_amount = 0;
$discounted_package = 0;
$total_package = 0;
$total_remaining = 0;

if (isset($export_billing)) {
    $data = $this->accounts_model->export_procedure_data($start_date, $end_date, $center, $patient_id, $payment_method, $biller_id);

    // Dynamic clean filename using standard output buffer
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Procedure-Reports-' . $start_date . '-to-' . $end_date . '.csv');

    $fp = fopen('php://output', 'w');

    // Headers
    $headers = array('IIC ID', 'Patient Name', 'Receipt Number', 'Total package', 'Discounted Package', 'Paid Amount', 'Remaining Amount', 'Payment Method', 'Billing From', 'Billing At', 'Procedure Name', 'Type', 'Billing Type', 'Date', 'Status', 'Billing Source');
    fputcsv($fp, $headers);

    foreach ($data as $val) {
        $billing_from = $val['billing_from'];
        if ($billing_from != "IndiaIVF") {
            $billing_from = get_center_name($billing_from);
        }

        $billing_at = get_center_name($val['billing_at']);

        // Sum Totals for Footer Row
        $paid_amount += (float)$val['payment_done'];
        $total_package += (float)$val['totalpackage'];
        $discounted_package += (float)$val['discounted_package'];
        $total_remaining += (float)$val['remaining_amount'];

        $lead_arr = array(
            $val['patient_id'],
            $val['wife_name'],
            $val['receipt_number'],
            $val['totalpackage'],
            $val['discounted_package'],
            $val['payment_done'],       // Updated Total (Initial + Partial)
            $val['remaining_amount'],   // Updated Remaining Amount
            $val['payment_method'],
            $billing_from,
            $billing_at,
            $val['procedure_name'],
            $val['category'],
            $val['billing_type'],
            $val['on_date'],
            $val['status'],
            $val['hospital_id']
        );

        fputcsv($fp, $lead_arr);
    }

    // CSV Final Summary Row
    $final_arr = array("", "", "Total", $total_package, $discounted_package, $paid_amount, $total_remaining, "", "", "", "", "", "", "", "", "");
    fputcsv($fp, $final_arr);

    fclose($fp);
    exit();
}
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/procedure_reports";
        	$config["total_rows"] = $this->accounts_model->procedure_reports_count($center, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->procedure_reports_list_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$data["biller_id"] = $biller_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/procedure_reports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}


	public function procedure_origin(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$payment_method = $this->input->get('payment_method', true);
			$export_consumption = $this->input->get('export-consumption', true);
			$json_data = $this->input->get('json_data', true);
			
			if (isset($export_consumption)){
				$data = $this->accounts_model->export_consumption_medicine($center, $start_date, $end_date, $patient_id, $payment_method, $json_data);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Consumption-Report-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'Pkg Booking Date,Pkg Cancel Date, Pkg Month,Pkg Booking Yr,FInancial Year, Receipt Number,IIC ID,Paient Name,Nationality,UHID, Procedures Code, Procedures Name, Category,Sub_Category1,Sub_Category2,Origin Booking Centre,Billing From Centre,billing_at,Lead Id,Lead Source,Agent, Procedures Price, Procedures Discount,Procedure Final Amount, Booking Amount,CN Invoice,Status,Apr 2024,May 2024,Jun 2024,Jul 2024,Aug 2024,Sep 2024,Oct 2024,Nov 2024,Dec 2024,Jan 2025,Feb 2025,Mar 2025,Apr 2025,May 2025,Jun 2025,Jul 2025,Aug 2025,Sep 2025,Oct 2025,Nov 2025,Dec 2025,Jan 2026,Feb 2026,Mar 2026,Apr 2026,May 2026,Jun 2026,Jul 2026,Aug 2026,Sep 2026,Oct 2026,Nov 2026,Dec 2026,Jan 2027,Feb 2027,Mar 2027';
				//Add the headers
				
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}

					$billing_at = $val['billing_at'];
					if($billing_at != "IndiaIVF"){
						$billing_at = get_center_name($billing_at);
					}
					
					$Origin_Booking_Centre = $val['origins'];
					if($Origin_Booking_Centre != "IndiaIVF"){
						$Origin_Booking_Centre = get_center_name($Origin_Booking_Centre);
					}
					
					$employee_number = get_center_name($val['employee_number']);
					
					$customMonths = [
					1 => 'Apr',
					2 => 'May',
					3 => 'June',
					4 => 'July',
					5 => 'August',
					6 => 'Sep',
					7 => 'Oct',
					8 => 'nov',
					9 => 'dec',
					10 => 'jan',
					11 => 'feb',
					12 => 'mar'
				];

				// Get current standard month number (1-12)
				$standardMonth = date('n', strtotime($val['on_date']));

				// Convert to your custom number (April=1 to March=12)
				if ($standardMonth >= 4) {
					$customMonthNumber = $standardMonth - 3; // April(4)=1, May(5)=2,...December(12)=9
				} else {
					$customMonthNumber = $standardMonth + 9; // January(1)=10, February(2)=11, March(3)=12
				}
				
				// Prepare common variables
				$formatted_date = date('Y-m-d', strtotime($val['on_date']));
				$modifiedon = date('Y-m-d', strtotime($val['modified_on']));
				$pkg_month = date("$customMonthNumber.F.y", strtotime($val['on_date']));
				$pkg_booking_year = date("Y", strtotime($val['on_date']));
				$date = strtotime($val['on_date']);
				$year = date("Y", $date);
				$month = date("n", $date);

				$financial_year = ($month >= 4) ? $year . '-' . ($year + 1) : ($year - 1) . '-' . $year;
				
					$sql = "SELECT * FROM hms_appointments WHERE paitent_id='" . $val['patient_id'] . "' and paitent_type='new_patient'";
					$select_result = run_select_query($sql);
					
					//$sql3 = "SELECT * FROM hms_appointments WHERE wife_phone='" . $select_result['wife_phone'] . "' and paitent_type='new_patient'";
					//$select_result3 = run_select_query($sql3);
					
					$sql_patients = "SELECT * FROM hms_patients WHERE patient_id='" . $val['patient_id'] . "'";
					$select_patients_result = run_select_query($sql_patients);
					
					$sql_embryo_transfer = "SELECT * FROM embryo_transfer_discharge_summary WHERE iic_id='" . $val['patient_id'] . "' order by ID ASC";
					$select_embryo_transfer = run_select_query($sql_embryo_transfer);

					$date_of_admission = $select_embryo_transfer['date_of_addmission'];
					$formatted_admission_date = date('Y-m-d', strtotime($date_of_admission));
					$formatted_on_date = date('Y-m-d', strtotime($val['on_date']));

					$type = 'New';
					if (strtotime($date_of_admission) > strtotime($val['on_date'])) {
						$type = 'recycle';
					}
										
					$names = [];
					if (!empty($select_patients_result['wife_name'])) {
						$names[] = $select_patients_result['wife_name'];
					}
					if (!empty($select_patients_result['husband_name'])) {
						$names[] = $select_patients_result['husband_name'];
					}
					$patient_name = implode(' & ', $names);
					
					$nationality = $select_patients_result['nationality'];
					
					$sql4 = "SELECT * FROM hms_centers WHERE center_number='" . $select_result3['appoitment_for'] . "'";
					$select_result4 = run_select_query($sql4);
					
					$uhid = $select_result4['center_code']."/".$select_result3['uhid'];
					
					$sql2 = "SELECT * FROM hms_procedures WHERE ID='" . $val['sub_procedure'] . "'";
					$select_result2 = run_select_query($sql2);
					$name = $select_result2['procedure_name'];
					$category = $select_result2['category'];
					$lead_id = $select_result['crm_id'];
					$lead_source = $select_result['lead_source'];
					
					// Assuming you have an array of records with the same patient_id, appointment_id, and billing date
			$records = [
				['patient_id' => $val['patient_id'], 'appointment_id' => $val['appointment_id'], 'date' => $formatted_date, 'category' => 'IVF with Bed'],
				['patient_id' => $val['patient_id'], 'appointment_id' => $val['appointment_id'], 'date' => $formatted_date, 'category' => 'IVF with Bed'],
				['patient_id' => $val['patient_id'], 'appointment_id' => $val['appointment_id'], 'date' => $formatted_date, 'category' => 'Non IVF without Bed'],
				// ... more records
			];

			// Get all categories for this group
			$categories = array_column($records, 'category');

			// Count occurrences of each category
			$categoryCounts = array_count_values($categories);

			// Determine the package based on your business rules
			if (isset($categoryCounts['IVF with Bed']) && isset($categoryCounts['Non IVF without Bed'])) {
				// Case 1: Both IVF with Bed and Non IVF without Bed exist
				$package = 'Package IVF Bed Inc Add-on';
			} elseif (isset($categoryCounts['IVF with Bed']) && $categoryCounts['IVF with Bed'] > 0) {
				// Case 2: Only IVF with Bed exists
				$package = 'Package IVF with Bed Only';
			} elseif (isset($categoryCounts['Non IVF without Bed']) && $categoryCounts['Non IVF without Bed'] > 0) {
				// Case 3: Only Non IVF without Bed exists
				$package = 'Package of Add-ons Sold Separately';
			} else {
				// Default case
				$package = 'Other Package';
			}

			// Apply the package to all records in this group
			foreach ($records as &$record) {
				$record['package'] = $package;
			}

					$sql5 = "SELECT 
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-04' THEN payment_done ELSE 0 END) AS payment_2024_apr,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-05' THEN payment_done ELSE 0 END) AS payment_2024_may,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-06' THEN payment_done ELSE 0 END) AS payment_2024_jun,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-07' THEN payment_done ELSE 0 END) AS payment_2024_jul,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-08' THEN payment_done ELSE 0 END) AS payment_2024_aug,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-09' THEN payment_done ELSE 0 END) AS payment_2024_sep,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-10' THEN payment_done ELSE 0 END) AS payment_2024_oct,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-11' THEN payment_done ELSE 0 END) AS payment_2024_nov,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2024-12' THEN payment_done ELSE 0 END) AS payment_2024_dec,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-01' THEN payment_done ELSE 0 END) AS payment_2025_jan,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-02' THEN payment_done ELSE 0 END) AS payment_2025_feb,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-03' THEN payment_done ELSE 0 END) AS payment_2025_mar,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-04' THEN payment_done ELSE 0 END) AS payment_2025_apr,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-05' THEN payment_done ELSE 0 END) AS payment_2025_may,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-06' THEN payment_done ELSE 0 END) AS payment_2025_jun,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-07' THEN payment_done ELSE 0 END) AS payment_2025_jul,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-08' THEN payment_done ELSE 0 END) AS payment_2025_aug,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-09' THEN payment_done ELSE 0 END) AS payment_2025_sep,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-10' THEN payment_done ELSE 0 END) AS payment_2025_oct,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-11' THEN payment_done ELSE 0 END) AS payment_2025_nov,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2025-12' THEN payment_done ELSE 0 END) AS payment_2025_dec,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-01' THEN payment_done ELSE 0 END) AS payment_2026_jan,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-02' THEN payment_done ELSE 0 END) AS payment_2026_feb,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-03' THEN payment_done ELSE 0 END) AS payment_2026_mar,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-04' THEN payment_done ELSE 0 END) AS payment_2026_apr,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-05' THEN payment_done ELSE 0 END) AS payment_2026_may,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-06' THEN payment_done ELSE 0 END) AS payment_2026_jun,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-07' THEN payment_done ELSE 0 END) AS payment_2026_jul,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-08' THEN payment_done ELSE 0 END) AS payment_2026_aug,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-09' THEN payment_done ELSE 0 END) AS payment_2026_sep,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-10' THEN payment_done ELSE 0 END) AS payment_2026_oct,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-11' THEN payment_done ELSE 0 END) AS payment_2026_nov,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2026-12' THEN payment_done ELSE 0 END) AS payment_2026_dec,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2027-01' THEN payment_done ELSE 0 END) AS payment_2027_jan,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2027-02' THEN payment_done ELSE 0 END) AS payment_2027_feb,
			SUM(CASE WHEN DATE_FORMAT(on_date, '%Y-%m') = '2027-03' THEN payment_done ELSE 0 END) AS payment_2027_mar
			FROM hms_patient_payments WHERE billing_id = '" . $val['receipt_number'] . "' AND status = '1'";	
			$select_result5 = run_select_query($sql5);
			
			$total = number_format($val['sub_procedures_price'] - $val['sub_procedures_discount'], 2);
			
    				$lead_arr = array($formatted_date, $val['modified_on'], $pkg_month, $pkg_booking_year, $financial_year, $val['receipt_number'], $val['patient_id'], $patient_name,$nationality, $uhid, $val['sub_procedures_code'], $name, $category, $type,$package, $Origin_Booking_Centre,$billing_from,$billing_at, $lead_id,$lead_source,$val['agent'], $val['sub_procedures_price'], $val['sub_procedures_discount'],$total, $val['sub_procedures_paid_price'], $val['cn_invoice'], $val['status'], $select_result5['payment_2024_apr'],$select_result5['payment_2024_may'],$select_result5['payment_2024_jun'],$select_result5['payment_2024_jul'],$select_result5['payment_2024_aug'],$select_result5['payment_2024_sep'],$select_result5['payment_2024_oct'],$select_result5['payment_2024_nov'],$select_result5['payment_2024_dec'],$select_result5['payment_2025_jan'],$select_result5['payment_2025_feb'],$select_result5['payment_2025_mar'],
					$select_result5['payment_2025_apr'],$select_result5['payment_2025_may'],$select_result5['payment_2025_jun'],$select_result5['payment_2025_jul'],$select_result5['payment_2025_aug'],$select_result5['payment_2025_sep'],$select_result5['payment_2025_oct'],$select_result5['payment_2025_nov'],$select_result5['payment_2025_dec'],$select_result5['payment_2026_jan'],$select_result5['payment_2026_feb'],$select_result5['payment_2026_mar'],$select_result5['payment_2026_apr'],$select_result5['payment_2026_may'],$select_result5['payment_2026_jun'],$select_result5['payment_2026_jul'],$select_result5['payment_2026_aug'],$select_result5['payment_2026_sep'],$select_result5['payment_2026_oct'],$select_result5['payment_2026_nov'],$select_result5['payment_2026_dec'],$select_result5['payment_2027_jan'],$select_result5['payment_2027_feb'],$select_result5['payment_2027_mar']);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/procedure_origin";
        	$config["total_rows"] = $this->accounts_model->patient_procedure_origin_count($center, $start_date, $end_date, $patient_id, $payment_method, $json_data);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->patient_procedure_origin_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method, $json_data);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$data["json_data"] = $json_data;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/procedure_origin', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}


	public function patient_financial_clearance(){
    $logg = checklogin();
    // Temporarily uncomment line below if you want to see exact errors during testing:
    // ini_set('display_errors', 1); error_reporting(E_ALL);

    if($logg['status'] == true){

        $per_page = $this->input->get('per_page', true);
        if(empty($per_page)){
            $per_page = 0;
        }
        $center       = $this->input->get('billing_at', true);
        $start_date   = $this->input->get('start_date', true);
        $end_date     = $this->input->get('end_date', true);
        $patient_id   = $this->input->get('iic_id', true);
        $biller_id    = $this->input->get('biller_id', true);
        $clearance    = $this->input->get('clearance', true);
        $consultant   = $this->input->get('consultant', true);
        $accclearance = $this->input->get('accclearance', true);
        // Payment filter: default = sirf 100% received procedures, paid=all = sab
        $paid_filter  = ($this->input->get('paid', true) === 'all') ? 'all' : 'full';
        
        $config = array();
        $config["base_url"]             = base_url() . "accounts/patient_financial_clearance";
        $config["per_page"]             = 20;

        // Is list me kabhi "disapproved" wali rows nahi dikhti; "100% received only" me sirf poori payment wali rows.
        // Model ki SQL payments / is status ko nahi jaanti, isliye model se saari filtered rows lekar yahin chhantte hain
        // aur pagination bhi yahin se banate hain.
        {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
            $all_rows = $this->accounts_model->patient_procedure_reports_list_patination(1000000, 0, $center, $start_date, $end_date, $patient_id, $biller_id, $clearance, $consultant, $accclearance);
            if (!is_array($all_rows)) { $all_rows = array(); }

            // Har receipt ka total paid (hms_patient_payments) ek hi grouped query se
            $receipts = array();
            foreach ($all_rows as $r) {
                if (!empty($r['receipt_number'])) { $receipts[$r['receipt_number']] = true; }
            }
            $paid_map = array();
            foreach (($paid_filter === 'full' ? array_chunk(array_keys($receipts), 1000) : array()) as $chunk) {
                $rows = $this->db->select('billing_id, SUM(payment_done) AS total_paid', false)
                                 ->from('hms_patient_payments')
                                 ->where_in('billing_id', $chunk)
                                 ->group_by('billing_id')
                                 ->get()->result_array();
                foreach ($rows as $pr) { $paid_map[$pr['billing_id']] = (float)$pr['total_paid']; }
            }

            // Page ki tarah hi: received = payments ka total + billing ke waqt ka payment_done; 100% = received >= final fees
            $full_rows = array();
            foreach ($all_rows as $r) {
                $code = strtoupper(trim(isset($r['code']) ? (string)$r['code'] : ''));
                if (in_array($code, array('IP218', 'IP219'), true)) { continue; }   // page inhe dikhata hi nahi
                $st = strtolower(trim(isset($r['status']) ? (string)$r['status'] : ''));
                if ($st === 'disapproved' || $st === 'disapprove') { continue; }    // disapproved wali rows nahi chahiye
                if ($paid_filter === 'full') {
                    $fees     = isset($r['fees']) ? (float)$r['fees'] : 0;
                    $received = (isset($paid_map[$r['receipt_number']]) ? $paid_map[$r['receipt_number']] : 0)
                              + (isset($r['payment_done']) ? (float)$r['payment_done'] : 0);
                    if (!($fees > 0 && $received >= $fees)) { continue; }
                }
                $full_rows[] = $r;
            }
            unset($all_rows);

            $config["total_rows"] = count($full_rows);
            $page_rows = array_slice($full_rows, (int)$per_page, $config["per_page"]);
        }
        $config["uri_segment"]          = 2;
        $config['use_page_numbers']     = false; // Standard offset pagination
        $config['num_links']            = 5;
        $config['page_query_string']    = true;
        $config['reuse_query_string']   = true;
        $config['query_string_segment'] = 'per_page';

		$config['full_tag_open']  = '<ul class="pagination">';
		$config['full_tag_close'] = '</ul>';

		$config['first_link'] = 'First';
		$config['last_link']  = 'Last';

		$config['first_tag_open'] = '<li>';
		$config['first_tag_close'] = '</li>';

		$config['last_tag_open'] = '<li>';
		$config['last_tag_close'] = '</li>';

		$config['next_link'] = '&raquo;';
		$config['prev_link'] = '&laquo;';

		$config['next_tag_open'] = '<li>';
		$config['next_tag_close'] = '</li>';

		$config['prev_tag_open'] = '<li>';
		$config['prev_tag_close'] = '</li>';

		$config['cur_tag_open'] = '<li class="active"><a href="#">';
		$config['cur_tag_close'] = '</a></li>';

		$config['num_tag_open'] = '<li>';
		$config['num_tag_close'] = '</li>';

        $this->pagination->initialize($config);
        
        $data["links"]            = $this->pagination->create_links();
        $data['procedure_result'] = $page_rows;
        
        $data["billing_at"]   = $center;
        $data["start_date"]   = $start_date;
        $data["end_date"]     = $end_date;
        $data["patient_id"]   = $patient_id;
        $data["biller_id"]     = $biller_id;
        $data["clearance"]    = $clearance;
        $data["consultant"]   = $consultant;
        $data["accclearance"] = $accclearance;

        $template = get_header_template($logg['role']);
        $this->load->view($template['header']);
        $this->load->view('accounts/patient_financial_clearance', $data);
        $this->load->view($template['footer']);
    }else{
        redirect(base_url());
    }
}


/******** Freezing *******/	
	
	public function freezing_reports(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$origins = $this->input->get('origins', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('patient_id', true);
			$wife_name = $this->input->get('wife_name', true);
			$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			$config = array();
        	$config["base_url"] = base_url() . "accounts/freezing_reports";
        	$config["total_rows"] = $this->accounts_model->freezing_reports_count($origins, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
        	$config["per_page"] = 50;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->freezing_reports_list_patination($config["per_page"], $per_page, $origins, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
			$data["origins"] = $origins;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/freezing_reports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	/******End Freezing******/

/******** Clinical Report *******/	
	
	public function clinical_reports(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('center', true);
			$year = $this->input->get('year', true);
			$patient_id = $this->input->get('patient_id', true);
			$wife_name = $this->input->get('wife_name', true);
			$payment_method = $this->input->get('payment_method', true);
			$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			$config = array();
        	$config["base_url"] = base_url() . "accounts/clinical_reports";
        	$config["total_rows"] = $this->accounts_model->clinical_reports_count($center, $center, $year, $patient_id, $payment_method);
        	$config["per_page"] = 50;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->clinical_reports_patination($config["per_page"], $per_page, $center, $year, $end_date, $patient_id, $payment_method);
			$data["center"] = $center;
			$data["year"] = $year;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/clinical_reports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function updatereports_admin(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('center', true);
			$year = $this->input->get('year', true);
			$patient_id = $this->input->get('patient_id', true);
			$wife_name = $this->input->get('wife_name', true);
			$payment_method = $this->input->get('payment_method', true);
			$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			$config = array();
        	$config["base_url"] = base_url() . "accounts/updatereports_admin";
        	$config["total_rows"] = $this->accounts_model->clinical_reports_count($center, $center, $year, $patient_id, $payment_method);
        	$config["per_page"] = 50;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->clinical_reports_patination($config["per_page"], $per_page, $center, $year, $end_date, $patient_id, $payment_method);
			$data["center"] = $center;
			$data["year"] = $year;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/updatereports_admin', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function updatereports_embrology(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/updatereports_embrology', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function updatereports(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/updatereports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function add_clinical_reports(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/add_clinical_reports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	/******End Clinical Report******/

	
	public function psychological_reports(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$payment_method = $this->input->get('payment_method', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
			$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			if (isset($export_billing)){
				$data = $this->accounts_model->export_psychological_data($start_date, $end_date, $center, $patient_id, $payment_method);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Procedure-Psychological-Reports-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name,Receipt Number, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At,Procedure Name, Billing Type, Date, Status';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$paid_amount = $paid_amount +  (int)$val['payment_done'];
					$total_package = $total_package +  (int)$val['totalpackage'];
					$discounted_package = $discounted_package +  (int)$val['discounted_package'];
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'],$val['receipt_number'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at,$val['procedure_name'] ,$val['billing_type'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status']);
					fputcsv($fp, $lead_arr);
				}
				$final_arr = array("", "", "", $total_package, $discounted_package, $paid_amount, "", "", "", "", "", "", "");
				fputcsv($fp, $final_arr);
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/psychological_reports";
        	$config["total_rows"] = $this->accounts_model->patient_psychological_count($center, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->patient_psychological_list_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/psychological_reports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	
	   public function consultation_reports(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$reason_of_visit = $this->input->get('reason_of_visit', true);
			$payment_method = $this->input->get('payment_method', true);
			$export_billing = $this->input->get('export-billing', true);
			$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			if (isset($export_billing)){
				$data = $this->accounts_model->export_consultation_data($start_date, $end_date, $center, $patient_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Consultation-Reports-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name,Receipt number, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type, Date, Status';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$paid_amount = $paid_amount +  (int)$val['payment_done'];
					$total_package = $total_package +  (int)$val['totalpackage'];
					$discounted_package = $discounted_package +  (int)$val['discounted_package'];
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['receipt_number'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at, $val['billing_type'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status']);
					fputcsv($fp, $lead_arr);
				}
				$final_arr = array("", "", "", $total_package, $discounted_package, $paid_amount, "", "", "", "", "", "", "");
				fputcsv($fp, $final_arr);
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/consultation_reports";
        	$config["total_rows"] = $this->accounts_model->patient_consultation_count($center,$status, $start_date, $end_date, $patient_id,$doctor_id = null);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_result'] = $this->accounts_model->consultation_report_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id,$reason_of_visit,$payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["reason_of_visit"] = $reason_of_visit;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/consultation_reports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	} 
	
	
	public function consultation_origin(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$doctor_id = $this->input->get('doctor_id', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$reason_of_visit = $this->input->get('reason_of_visit');
    		$category = $this->input->get('category');
			$procedures = $this->input->get('procedures');
			$agent = $this->input->get('agent');
			$councellor = $this->input->get('councellor');
			$broad_procedure = $this->input->get('broad_procedure');
			$broad_procedure_count = $this->input->get('broad_procedure_count');
			$lead_source = $this->input->get('lead_source');
			$export_billing = $this->input->get('export-billing', true);
			$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			if (isset($export_billing)) {
				$data = $this->accounts_model->export_consultation_origin_data($start_date, $end_date, $center, $patient_id, $reason_of_visit);
				
				// Centers Lookup Array (ताकि बार-बार DB से न पूछना पड़े)
				$centers_query = $this->db->get('hms_centers')->result_array();
				$centers_map = array_column($centers_query, 'center_name', 'center_number');

				// Procedures Lookup Array
				$proc_query = $this->db->get('hms_procedures')->result_array();
				$proc_map = array_column($proc_query, 'category', 'ID');

				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Consultation-Reports-' . $start_date . '-' . $end_date . '.csv');
				$fp = fopen('php://output', 'w');

				// Updated headers (23 Columns)
				$headers = [
					'UHID', 'IIC ID', 'Patient Name', 'Receipt Number', 'Total Package', 
					'Discounted Package', 'Paid Amount', 'Remaining Amount', 'Payment Method', 
					'Billing From', 'Billing At', 'Billing Type', 'Reason Of Visit', 'Date', 
					'Status', 'Lead ID', 'Lead Source', 'Agent', 'Councellor', 
					'Doctor Name', 'Booking Status', 'Visit Type', 'Appointment Status'
				];
				fputcsv($fp, $headers);

				foreach ($data as $val) {
					$paid_amount += (int)$val['payment_done'];
					$total_package += (int)$val['totalpackage'];
					$discounted_package += (int)$val['discounted_package'];

					$billing_from = ($val['billing_from'] != "IndiaIVF") ? ($centers_map[$val['billing_from']] ?? $val['billing_from']) : "IndiaIVF";
					$billing_at = $centers_map[$val['billing_at']] ?? $val['billing_at'];
					
					$uhid = !empty($val['uhid']) ? ($val['center_code'] . "/" . $val['uhid']) : "";
					$visit_type = !empty($val['camp_selection']) ? "Camp Visit" : "Center Visit";

					// Category determination from pre-fetched procedure data
					$category = 'not booked';
					if (!empty($val['procedure_data'])) {
						$unserialized_data = @unserialize($val['procedure_data']);
						if (isset($unserialized_data['patient_procedures']['sub_procedure'])) {
							$sub_proc_id = $unserialized_data['patient_procedures']['sub_procedure'];
							$category = $proc_map[$sub_proc_id] ?? 'not found';
						} else {
							$category = 'data missing';
						}
					}

					$lead_arr = [
						$uhid,
						$val['patient_id'],
						$val['patient_name'],
						$val['receipt_number'],
						$val['totalpackage'],
						$val['discounted_package'],
						$val['payment_done'],
						$val['remaining_amount'],
						$val['payment_method'],
						$billing_from,
						$billing_at,
						'Consultation',
						$val['reason_of_visit'],
						!empty($val['date']) ? date('Y-m-d H:i:s', strtotime($val['date'])) : '',
						$val['status'],
						$val['lead_id'],
						$val['lead_source'],
						$val['agent'],
						$val['councellor'],
						$val['doctor_name'],
						$category,
						$visit_type,
						$val['appointment_status']
					];
					
					fputcsv($fp, $lead_arr);
				}

				$final_arr = ["", "", "", "Total:", $total_package, $discounted_package, $paid_amount];
				fputcsv($fp, $final_arr);
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/consultation_origin";
        	$config["total_rows"] = $this->accounts_model->patient_consultation_report_count($center, $start_date, $end_date, $patient_id, $reason_of_visit, $doctor_id,$lead_source,$category, $agent, $councellor);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_result'] = $this->accounts_model->patient_consultation_report_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $reason_of_visit, $doctor_id ,$agent, $councellor, $category,$procedures,$broad_procedure, $broad_procedure_count, $lead_source);
			$data['reason_counts'] = $this->accounts_model->patient_consultation_count_by_reason($center, $start_date, $end_date, $patient_id, $reason_of_visit, $doctor_id, $lead_source);
			$data['patient_counts'] = $this->accounts_model->patient_procedure_consultation_count($center, $start_date, $end_date, $patient_id,$reason_of_visit,$category,$procedures,$broad_procedure,$agent,$councellor,$broad_procedure_count);
			$data['lead_sources'] = $this->accounts_model->get_lead_source_dropdown_data();

			//$data['booked_patient_count'] = $booked_count; 
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["doctor_id"] = $doctor_id;
			$data["lead_source"] = $lead_source;
			//$data["reason_of_visit"] = $reason_of_visit;
			$data["category"] = $category;
			$data["procedures"] = $procedures;
			$data["broad_procedure"] = $broad_procedure;
			$data["broad_procedure_count"] = $broad_procedure_count;
			$data["agent"] = $agent;
			$data["councellor"] = $councellor;
			$data['selectedReason'] = $reason_of_visit;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/consultation_origin', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	} 
	
// In application/controllers/Accounts.php

public function export_consultation_csv() {
    // 1. Get ALL filters from URL (GET request)
    $center = $this->input->get('center');
    $start_date = $this->input->get('start_date');
    $end_date = $this->input->get('end_date');
    $patient_id = $this->input->get('patient_id');
    $reason_of_visit = $this->input->get('reason_of_visit');
    $doctor_id = $this->input->get('doctor_id');
    $agent = $this->input->get('agent');
    $councellor = $this->input->get('councellor');
    $lead_source = $this->input->get('lead_source');
    
    // New Procedure Filters
    $category = $this->input->get('category');
    $procedures = $this->input->get('procedures');
    $broad_procedure = $this->input->get('broad_procedure');
    $broad_procedure_count = $this->input->get('broad_procedure_count');
    
    // Get page parameter or default to 1
    $page = $this->input->get('page') ?: 1;

    // 2. Call Model with High Limit (to get all records)
    $report_data = $this->accounts_model->patient_export_report_patination(
        100000, // Limit
        $page,  // Page
        $center, $start_date, $end_date, $patient_id, $reason_of_visit, $doctor_id, $agent, $councellor, 
        $category, $procedures, $broad_procedure, $broad_procedure_count, $lead_source
    );

    // 3. Debug: Check if data is returned
    if (empty($report_data)) {
        // Add debug headers
        header("Content-Type: text/plain");
        echo "No data found. Check your filters and database.\n";
        echo "SQL Conditions: " . $this->db->last_query() . "\n";
        exit;
    }

    // 4. Create CSV File
    $filename = 'consultation_report_' . date('Ymd') . '.csv';
    header("Content-Description: File Transfer");
    header("Content-Disposition: attachment; filename=$filename");
    header("Content-Type: application/csv; ");
    
    $file = fopen('php://output', 'w');

    // 5. Write Headers
    $header = array(
        "Patient ID", "Agent", "Counsellor", "CRM ID", "Lead Source", "Visit Date", 
        "Reason", "Doctor ID", "Total Package", "Payment Done", 
        "Category", "Procedure", "Broad Procedure", "Broad Procedure Count"
    );
    fputcsv($file, $header);

    // 6. Write Data Rows
    foreach ($report_data as $row) {
        $line = array(
            $row['patient_id'] ?? '',
            $row['agent'] ?? '',
            $row['councellor'] ?? '',
            $row['crm_id'] ?? '',
            $row['lead_source'] ?? '',
            $row['on_date'] ?? '',
            $row['reason_of_visit'] ?? '',
            $row['doctor_id'] ?? '',
            $row['totalpackage'] ?? '0',
            $row['payment_done'] ?? '0',
            $row['category'] ?? '',
            $row['procedures'] ?? '',
            $row['broad_procedure'] ?? '',
            $row['broad_procedure_count'] ?? ''
        );
        fputcsv($file, $line);
    }

    fclose($file);
    exit;
}
/*
public function approve($request = NULL) {
    $logg = checklogin();
    if($logg['status'] == true) {
        $data = array();
        
        // Parameters catch karein safety checks ke saath
        $type = isset($_GET['t']) ? $_GET['t'] : '';
        $status = isset($_GET['u']) ? $_GET['u'] : '';
        $cn_invoice = isset($_GET['cn']) ? $_GET['cn'] : '';
        $used_amount = isset($_GET['ua']) ? $_GET['ua'] : 0;
        $reason = isset($_GET['r']) ? $_GET['r'] : '';
        $reason_of_cancle = isset($_GET['c']) ? $_GET['c'] : '';

        $patient_id = 0;
        $receipt_number = 0;

        // --- WALLET REFUND & ADJUST LOGIC ---
        if(in_array($status, ['cancel', 'disapproved', 'adjust'])) {
            $table_map = [
                'consultation' => 'consultation',
                'registation' => 'registation',
                'investigation' => 'patient_investigations',
                'medicine' => 'patient_medicine',
                'procedure' => 'patient_procedure',
            ];

            if(isset($table_map[$type])) {
                $billing_info = india_ivf_billing($request, $table_map[$type]);
                
                $refund_amt = 0;
                if(isset($billing_info['payment_done']) && $billing_info['payment_done'] > 0) {
                    $refund_amt = $billing_info['payment_done'];
                } elseif(isset($billing_info['amount']) && $billing_info['amount'] > 0) {
                    $refund_amt = $billing_info['amount'];
                }
                
                if(!empty($billing_info) && is_array($billing_info) && $refund_amt > 0) {
                    
                    $payment_method = '';
                    if(isset($billing_info['payment_method'])) {
                        $payment_method = strtolower(trim($billing_info['payment_method']));
                    } elseif(isset($billing_info['payment_mode'])) {
                        $payment_method = strtolower(trim($billing_info['payment_mode']));
                    }

                    // 🚀 MAIN CHANGE:
                    // Agar payment wallet se hui thi, YAA status 'adjust' ho, YAA 'cancel' ho, toh amount seedha wallet me jayega.
                    if (strpos($payment_method, 'wallet') !== false || in_array($status, ['adjust', 'cancel'])) {
                        
                        $p_id = $billing_info['patient_id'];
                        
                        // 🚀 ROUTING LOGIC: 
                        // 'procedure' -> Wallet 2 (Package Wallet)
                        // Baki sab ('investigation', 'registation', 'consultation') -> Wallet 1 (Money Wallet)
                        if ($type == 'procedure' || $type == 'payments') {
                            $wallet_column = 'wallet_2_balance';
                            $is_wallet_2 = true;
                        } else {
                            $wallet_column = 'wallet_1_balance';
                            $is_wallet_2 = false;
                        }

                        // Check karein kya purane record ke patient ka wallet data database me hai?
                        $check_wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $p_id))->row_array();
                        if(empty($check_wallet)) {
                            $this->db->insert('hms_patient_wallets', array(
                                'patient_id' => $p_id,
                                'wallet_1_balance' => 0,
                                'wallet_2_balance' => 0,
                                'created_at' => date('Y-m-d H:i:s')
                            ));
                        }

                        // Update Wallet Balance in Database
                        $this->db->query("UPDATE hms_patient_wallets SET $wallet_column = $wallet_column + ?, updated_at = NOW() WHERE patient_id = ?", array($refund_amt, $p_id));
                        
                        // Fetch fresh data for logs
                        $new_wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $p_id))->row_array();
                        
                        $w1_balance = isset($new_wallet['wallet_1_balance']) ? $new_wallet['wallet_1_balance'] : 0;
                        $w2_balance = isset($new_wallet['wallet_2_balance']) ? $new_wallet['wallet_2_balance'] : 0;

                        // Dynamic Remarks based on Action
                        if ($status === 'adjust') {
                            $log_remarks = 'Amount Adjusted to Wallet ('.ucfirst($type).')';
                        } elseif ($status === 'cancel') {
                            $log_remarks = 'Refund for Cancelled '.ucfirst($type).' credited to Wallet';
                        } else {
                            $log_remarks = 'Refund for '.ucfirst($status).' ('.ucfirst($type).')';
                        }

                        if(!empty($reason)) {
                            $log_remarks .= ' - Reason: ' . $reason;
                        } elseif (!empty($reason_of_cancle)) {
                            $log_remarks .= ' - Reason: ' . $reason_of_cancle;
                        }
                        
                        // Log Entry array mapping
                        $wallet_log = array(
                            'patient_id'  => $p_id,
                            'amount'      => $refund_amt,
                            'action_type' => 'credit',
                            'opening_w1'  => (!$is_wallet_2) ? ($w1_balance - $refund_amt) : $w1_balance,
                            'closing_w1'  => $w1_balance,
                            'opening_w2'  => ($is_wallet_2) ? ($w2_balance - $refund_amt) : $w2_balance,
                            'closing_w2'  => $w2_balance,
                            'reference_id'=> isset($billing_info['receipt_number']) ? $billing_info['receipt_number'] : $request,
                            'payment_method' => 'wallet',
                            'remarks'     => $log_remarks,
                            'created_by'  => isset($_SESSION['logged_accountant']['name']) ? $_SESSION['logged_accountant']['name'] : 'system',
                            'created_at'  => date('Y-m-d H:i:s'),
                            'status'      => 'success'
                        );
                        $this->db->insert('hms_wallet_logs', $wallet_log);
                    }
                }
            }
        }

        // --- BILLING PROCESS LOGIC ---
        if($type == 'consultation') {            
            $billing_records = india_ivf_billing($request, "consultation"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'registation') {
            $billing_records = india_ivf_billing($request, "registation"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'investigation') {
            $billing_records = india_ivf_billing($request, "patient_investigations"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'medicine') {
            $billing_records = india_ivf_billing($request, "patient_medicine"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'procedure') {
            $billing_records = india_ivf_billing($request, "patient_procedure"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_procedure_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice, $used_amount);
        }

        header("location:" .$_SERVER['HTTP_REFERER']. "?m=".base64_encode('Billing processed successfully').'&t='.base64_encode('success'));
        die();
    } else {
        header("location:" .base_url(). "");
        die();
    }
} */
public function approve($request = NULL) {
    $logg = checklogin();
    if($logg['status'] == true) {
        $data = array();
        
        // Parameters catch karein safety checks ke saath
        $type = isset($_GET['t']) ? $_GET['t'] : '';
        $status = isset($_GET['u']) ? $_GET['u'] : '';
        $cn_invoice = isset($_GET['cn']) ? $_GET['cn'] : '';
        $used_amount = isset($_GET['ua']) ? $_GET['ua'] : 0;
        $reason = isset($_GET['r']) ? $_GET['r'] : '';
        $reason_of_cancle = isset($_GET['c']) ? $_GET['c'] : '';

        $patient_id = 0;
        $receipt_number = 0;

        // --- WALLET REFUND & ADJUST LOGIC FOR MAIN BILLING ---
        if(in_array($status, ['cancel', 'disapproved', 'adjust'])) {
            $table_map = [
                'consultation' => 'consultation',
                'registation' => 'registation',
                'investigation' => 'patient_investigations',
                'medicine' => 'patient_medicine',
                'procedure' => 'patient_procedure',
            ];

            if(isset($table_map[$type])) {
                $billing_info = india_ivf_billing($request, $table_map[$type]);
                
                $refund_amt = 0;
                if(isset($billing_info['payment_done']) && $billing_info['payment_done'] > 0) {
                    $refund_amt = floatval($billing_info['payment_done']);
                } elseif(isset($billing_info['amount']) && $billing_info['amount'] > 0) {
                    $refund_amt = floatval($billing_info['amount']);
                }
                
                // FIX: Jo bill pehle se cancel / disapproved hai uska dobara refund na ho (double click / page refresh)
                $already_status = isset($billing_info['status']) ? strtolower(trim($billing_info['status'])) : '';
                if (in_array($already_status, array('cancel', 'cancelled', 'disapproved'))) { $refund_amt = 0; }

                if(!empty($billing_info) && is_array($billing_info) && $refund_amt > 0) {
                    
                    $payment_method = '';
                    if(isset($billing_info['payment_method'])) {
                        $payment_method = strtolower(trim($billing_info['payment_method']));
                    } elseif(isset($billing_info['payment_mode'])) {
                        $payment_method = strtolower(trim($billing_info['payment_mode']));
                    }

                    // Main billing refund to wallet
                    if (strpos($payment_method, 'wallet') !== false || in_array($status, ['adjust', 'cancel'])) {
                        
                        $p_id = $billing_info['patient_id'];
                        
                        if ($type == 'procedure' || $type == 'payments') {
                            $wallet_column = 'wallet_2_balance';
                            $is_wallet_2 = true;
                        } else {
                            $wallet_column = 'wallet_1_balance';
                            $is_wallet_2 = false;
                        }

                        $check_wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $p_id))->row_array();
                        if(empty($check_wallet)) {
                            $this->db->insert('hms_patient_wallets', array(
                                'patient_id' => $p_id,
                                'wallet_1_balance' => 0,
                                'wallet_2_balance' => 0,
                                'created_at' => date('Y-m-d H:i:s')
                            ));
                        }

                        // Update Main Procedure Wallet Balance
                        $this->db->query("UPDATE hms_patient_wallets SET $wallet_column = $wallet_column + ?, updated_at = NOW() WHERE patient_id = ?", array($refund_amt, $p_id));
                        
                        $new_wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $p_id))->row_array();
                        
                        $w1_balance = isset($new_wallet['wallet_1_balance']) ? floatval($new_wallet['wallet_1_balance']) : 0;
                        $w2_balance = isset($new_wallet['wallet_2_balance']) ? floatval($new_wallet['wallet_2_balance']) : 0;

                        if ($status === 'adjust') {
                            $log_remarks = 'Amount Adjusted to Wallet ('.ucfirst($type).')';
                        } elseif ($status === 'cancel') {
                            $log_remarks = 'Refund for Cancelled '.ucfirst($type).' credited to Wallet';
                        } else {
                            $log_remarks = 'Refund for '.ucfirst($status).' ('.ucfirst($type).')';
                        }

                        if(!empty($reason)) {
                            $log_remarks .= ' - Reason: ' . $reason;
                        } elseif (!empty($reason_of_cancle)) {
                            $log_remarks .= ' - Reason: ' . $reason_of_cancle;
                        }
                        
                        $wallet_log = array(
                            'patient_id'     => $p_id,
                            'amount'         => $refund_amt,
                            'action_type'    => 'credit',
                            'opening_w1'     => (!$is_wallet_2) ? ($w1_balance - $refund_amt) : $w1_balance,
                            'closing_w1'     => $w1_balance,
                            'opening_w2'     => ($is_wallet_2) ? ($w2_balance - $refund_amt) : $w2_balance,
                            'closing_w2'     => $w2_balance,
                            'reference_id'   => isset($billing_info['receipt_number']) ? $billing_info['receipt_number'] : $request,
                            'payment_method' => 'wallet',
                            'remarks'        => $log_remarks,
                            'created_by'     => isset($_SESSION['logged_accountant']['name']) ? $_SESSION['logged_accountant']['name'] : 'system',
                            'created_at'     => date('Y-m-d H:i:s'),
                            'status'         => 'success'
                        );
                        $this->db->insert('hms_wallet_logs', $wallet_log);
                    }
                }
            }
        }

        // --- BILLING PROCESS LOGIC ---
        if($type == 'consultation') {            
            $billing_records = india_ivf_billing($request, "consultation"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'registation') {
            $billing_records = india_ivf_billing($request, "registation"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'investigation') {
            $billing_records = india_ivf_billing($request, "patient_investigations"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'medicine') {
            $billing_records = india_ivf_billing($request, "patient_medicine"); 
            if(!empty($billing_records) && is_array($billing_records)) {
                $patient_id = $billing_records['patient_id'];
                $receipt_number = $billing_records['receipt_number'];
            }
            $data = $this->accounts_model->approve_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice);
        } else if($type == 'procedure') {
            
            // 1. Fetch exact receipt_number & patient_id directly from hms_patient_procedure
            $proc_query = $this->db->get_where('hms_patient_procedure', array('ID' => $request))->row_array();
            if(!empty($proc_query)) {
                $receipt_number = isset($proc_query['receipt_number']) ? $proc_query['receipt_number'] : 0;
                $patient_id = isset($proc_query['patient_id']) ? $proc_query['patient_id'] : 0;
            } else {
                $billing_records = india_ivf_billing($request, "patient_procedure"); 
                if(!empty($billing_records) && is_array($billing_records)) {
                    $receipt_number = isset($billing_records['receipt_number']) ? $billing_records['receipt_number'] : 0;
                    $patient_id = isset($billing_records['patient_id']) ? $billing_records['patient_id'] : 0;
                }
            }

            // 🚀 2. PARTIAL PAYMENTS WALLET REFUND (Excluding Status 2 & 3)
            if ($status == 'cancel') {
                
                // Status 2 (Disapproved) aur Status 3 (Cancelled) dono ko Exclude kar diya hai
                $sql = "SELECT * FROM hms_patient_payments 
                        WHERE (billing_id = ? OR billing_id = ?) 
                        AND status NOT IN (2, 3)";
                
                $active_partials = $this->db->query($sql, array($receipt_number, $request))->result_array();

                if (!empty($active_partials)) {
                    foreach ($active_partials as $p_pay) {
                        $p_amount = floatval($p_pay['payment_done']);
                        $p_patient = !empty($patient_id) ? $patient_id : (isset($p_pay['patient_id']) ? $p_pay['patient_id'] : 0);

                        if ($p_amount > 0 && !empty($p_patient)) {
                            // Ensure Wallet Exists
                            $check_wallet = $this->db->query("SELECT * FROM hms_patient_wallets WHERE patient_id = ?", array($p_patient))->row_array();
                            if (empty($check_wallet)) {
                                $this->db->insert('hms_patient_wallets', array(
                                    'patient_id' => $p_patient,
                                    'wallet_1_balance' => 0,
                                    'wallet_2_balance' => 0,
                                    'created_at' => date('Y-m-d H:i:s')
                                ));
                            }

                            // Credit Amount directly to Wallet 2
                            $this->db->query("UPDATE hms_patient_wallets SET wallet_2_balance = wallet_2_balance + ?, updated_at = NOW() WHERE patient_id = ?", array($p_amount, $p_patient));

                            // Get Updated Wallet Balance
                            $new_wallet = $this->db->query("SELECT * FROM hms_patient_wallets WHERE patient_id = ?", array($p_patient))->row_array();
                            $w1_bal = isset($new_wallet['wallet_1_balance']) ? floatval($new_wallet['wallet_1_balance']) : 0;
                            $w2_bal = isset($new_wallet['wallet_2_balance']) ? floatval($new_wallet['wallet_2_balance']) : 0;

                            $p_reason = !empty($reason) ? $reason : (!empty($reason_of_cancle) ? $reason_of_cancle : 'Procedure Cancelled');

                            // Insert Partial Payment Refund Log
                            $partial_log = array(
                                'patient_id'     => $p_patient,
                                'amount'         => $p_amount,
                                'action_type'    => 'credit',
                                'opening_w1'     => $w1_bal,
                                'closing_w1'     => $w1_bal,
                                'opening_w2'     => ($w2_bal - $p_amount),
                                'closing_w2'     => $w2_bal,
                                'reference_id'   => isset($p_pay['receipt_number']) ? $p_pay['receipt_number'] : $request,
                                'payment_method' => 'wallet',
                                'remarks'        => 'Refund for Cancelled Partial Payment (Procedure) - Reason: ' . $p_reason,
                                'created_by'     => isset($_SESSION['logged_accountant']['name']) ? $_SESSION['logged_accountant']['name'] : 'system',
                                'created_at'     => date('Y-m-d H:i:s'),
                                'status'         => 'success'
                            );
                            $this->db->insert('hms_wallet_logs', $partial_log);
                        }
                    }
                }
            }

            // 3. Procedure billing update via Model
            $data = $this->accounts_model->approve_procedure_billing($request, $type, $status, $reason, $reason_of_cancle, $cn_invoice, $used_amount);

            // 4. Update status in hms_patient_payments table (Status 2 wale touch nahi honge)
            if (in_array($status, ['cancel', 'disapproved'])) {
                $partial_status = ($status == 'cancel') ? 3 : 2; 
                
                // Status != 2 condition lagai hai taaki disapproved partial payments cancel me convert na ho
                $this->db->query("UPDATE hms_patient_payments SET status = ? WHERE (billing_id = ? OR billing_id = ?) AND status != 2", array($partial_status, $receipt_number, $request));
            }
        }

        header("location:" .$_SERVER['HTTP_REFERER']. "?m=".base64_encode('Billing processed successfully').'&t='.base64_encode('success'));
        die();
    } else {
        header("location:" .base_url(). "");
        die();
    }
}

public function partial_approve($request = NULL) {
    $logg = checklogin();
    if($logg['status'] == true) {
        $data = array();

        // Parameters catch karein
        $type = isset($_GET['t']) ? trim($_GET['t']) : '';
        $status = isset($_GET['u']) ? trim($_GET['u']) : '';
        $cn_invoice = isset($_GET['cn']) ? $_GET['cn'] : '';
        $used_amount = isset($_GET['ua']) ? (float)$_GET['ua'] : 0;
        $reason_of_cancle = isset($_GET['c']) ? $_GET['c'] : '';
        $request = (int)$request; // Bill / Payment ID

        // Fallback: Check if type is empty or partialpayments
        if (($type == 'partialpayments' || empty($type)) && $status == '3' && !empty($request)) { 
            
            // 1. Partial Payment ki details fetch karein
            $this->db->where('ID', $request);
            $payment_info = $this->db->get('hms_patient_payments')->row_array(); 

            if (!empty($payment_info)) {
                $p_id = $payment_info['patient_id'];
                
                // Refund Amount catch karein (Check multiple column names)
                $refund_amt = 0;
                if (isset($payment_info['payment_done']) && (float)$payment_info['payment_done'] > 0) {
                    $refund_amt = (float)$payment_info['payment_done'];
                } elseif (isset($payment_info['paid_amount']) && (float)$payment_info['paid_amount'] > 0) {
                    $refund_amt = (float)$payment_info['paid_amount'];
                } elseif (isset($payment_info['amount']) && (float)$payment_info['amount'] > 0) {
                    $refund_amt = (float)$payment_info['amount'];
                }

                // Payment mode fetch karein logging ke liye
                $payment_method = isset($payment_info['payment_method']) ? strtolower(trim($payment_info['payment_method'])) : 'partial_payment';

                // =========================================================================
                // 2. WALLET REFUND LOGIC (ALWAYS CREDIT TO WALLET 2 ON CANCELLATION)
                // =========================================================================
                if ($refund_amt > 0 && !empty($p_id)) {
                    
                    // Patient ka current wallet record Check / Fetch karein
                    $this->db->where('patient_id', $p_id);
                    $wallet = $this->db->get('hms_patient_wallets')->row_array();
                    
                    if (empty($wallet)) {
                        // Agar wallet record nahi hai to create karein
                        $this->db->insert('hms_patient_wallets', [
                            'patient_id' => $p_id,
                            'wallet_1_balance' => 0,
                            'wallet_2_balance' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        $w1_balance = 0.00;
                        $w2_balance = 0.00;
                    } else {
                        $w1_balance = (float)$wallet['wallet_1_balance'];
                        $w2_balance = (float)$wallet['wallet_2_balance'];
                    }

                    // Calculate New Balance for Wallet 2
                    $new_w2_balance = $w2_balance + $refund_amt;
                    
                    // A. Update Wallet 2 Balance
                    $this->db->where('patient_id', $p_id);
                    $this->db->update('hms_patient_wallets', [
                        'wallet_2_balance' => $new_w2_balance,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    // B. Entry in Wallet Logs
                    $log_remarks = 'Refund for Cancelled Partial Payment (Bill ID: ' . $request . ')';
                    if (!empty($reason_of_cancle)) {
                        $log_remarks .= ' - Reason: ' . $reason_of_cancle;
                    }

                    $wallet_log = [
                        'patient_id'    => $p_id,
                        'amount'        => $refund_amt,
                        'action_type'   => 'credit',
                        'opening_w1'    => $w1_balance,
                        'closing_w1'    => $w1_balance,     // Wallet 1 Untouched
                        'opening_w2'    => $w2_balance,
                        'closing_w2'    => $new_w2_balance, // Wallet 2 Refunded
                        'reference_id'  => $request,
                        'payment_method'=> $payment_method,
                        'remarks'       => $log_remarks,
                        'created_by'    => isset($_SESSION['logged_accountant']['name']) ? $_SESSION['logged_accountant']['name'] : (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'system'),
                        'created_at'    => date('Y-m-d H:i:s'),
                        'status'        => 'success'
                    ];
                    $this->db->insert('hms_wallet_logs', $wallet_log);
                }

                // =========================================================================
                // 3. UPDATE PAYMENT STATUS TO CANCELLED (3)
                // =========================================================================
                $update_data = [
                    'status' => 3, 
                    'reason_of_cancle' => $reason_of_cancle,
                    'cn_invoice' => $cn_invoice,
                    'modified_on' => date('Y-m-d H:i:s')
                ];
                
                if ($used_amount > 0) {
                    $update_data['used_amount'] = $used_amount;
                }

                $this->db->where('ID', $request);
                $this->db->update('hms_patient_payments', $update_data);
            }

            // Redirect Back with Success Message
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : base_url('accounts/partialpayments_request');
            
            // Remove previous URL query params if any
            $clean_referer = strtok($referer, '?');
            
            header("location:" . $clean_referer . "?m=" . base64_encode('Partial Payment Cancelled and Amount Refunded to Wallet 2 Successfully') . '&t=' . base64_encode('success'));
            die();
        }

    } else {
        header("location:" . base_url());
        die();
    }
}
	
	function approve_registation($ID){
		$approved = $this->accounts_model->approve_registation($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/registration_patients?m=".base64_encode('Registration approved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/registration_patients?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}
	
	function approve_consultation($ID){
		$approved = $this->accounts_model->approve_consultation($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/consultation_patients?m=".base64_encode('Consultation approved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/consultation_patients?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}
	
	
	function approve_procedure($ID){
		$approved = $this->accounts_model->approve_procedure($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/procedure_patients?m=".base64_encode('Procedure approved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/procedure_patients?m=".base64_encode('Procedure went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}
	
	function approve_medicine($ID){
		$approved = $this->accounts_model->approve_medicine($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/procedure_patients?m=".base64_encode('Procedure approved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/procedure_patients?m=".base64_encode('Procedure went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}
public function approve_counsellor_clearance()
{
    $logg = checklogin();

    if ($logg['status'] != true) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Session expired. Kripya punah login karein.'
        ]);
        exit;
    }

    $procedure_ids = $this->input->post('procedure_ids');

    // Debug
    log_message('error', 'COUNSELLOR SESSION: ' . print_r($_SESSION['logged_counselor'], true));
    log_message('error', 'USER SESSION ID: ' . print_r($_SESSION['user_id'], true));
    log_message('error', 'CHECKLOGIN: ' . print_r($logg, true));

    $counselor_id = '';

    if (!empty($_SESSION['logged_counselor']['employee_number'])) {
        $counselor_id = $_SESSION['logged_counselor']['employee_number'];
    } elseif (!empty($_SESSION['user_id'])) {
        $counselor_id = $_SESSION['user_id'];
    } elseif (!empty($logg['id'])) {
        $counselor_id = $logg['id'];
    }

    if (empty($procedure_ids) || !is_array($procedure_ids)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Koi procedure select nahi kiya gaya.'
        ]);
        exit;
    }

    if (empty($counselor_id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Counsellor ID nahi mili. Please login session check karein.'
        ]);
        exit;
    }

    date_default_timezone_set('Asia/Kolkata');

    $update_data = [
        'clearance'      => 'Yes',
        'counselor_id'   => $counselor_id,
        'clearance_date' => date('Y-m-d H:i:s')
    ];

    $this->db->where_in('ID', $procedure_ids);
    $this->db->update('hms_patient_procedure', $update_data);

    // Check DB error
    if ($this->db->error()['code'] != 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $this->db->error()['message']
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Selected procedure(s) clearance approved successfully!',
        'counselor_id' => $counselor_id
    ]);

    exit;
}

	
	
public function tally()
{
    // Initialize the master array
    $all_transactions = [];

   // =========================================================================
    // PART 1: MEDICINE SALES
    // =========================================================================
    
    // Today's timestamp starting at 12:00 AM (Midnight Reset)
    $today_start = date('Y-m-d 00:00:00');

    $sql_med = "
        SELECT 
            s.id AS sale_id, s.patient_id, s.patient_name, c.center_name, c.center_code, c.state_name, c.center_gst,
            s.sale_number, s.sale_date, s.updated_at, s.payment_method, s.payment_status,
            s.payment_approved_by_name, s.series_number, m.medicine_name, mb.batch_number,
            mb.expiry_date, m.hsn_code, m.gst_rate, m.pack_size, mb.mrp,
            sm.quantity_change, sm.total_value, s.tally_status
        FROM sales s
        INNER JOIN hms_centers c ON s.center_id = c.id
        INNER JOIN stock_movements sm ON s.id = sm.reference_id
        INNER JOIN medicine_batches mb ON sm.batch_id = mb.id
        INNER JOIN medicines m ON mb.medicine_id = m.id
        WHERE sm.movement_type = 'SALE' 
            AND sm.to_location_type = 'SALE'
            AND s.payment_status = 'PAID' 
            AND s.tally_status = 'APPROVED_TALLY'
            AND s.tally_send_date >= '{$today_start}'
        ORDER BY s.updated_at DESC 
        LIMIT 700";
    
    $med_results = $this->db->query($sql_med)->result_array();

    $med_grouped = [];
    $prefix = $this->config->item('db_prefix');

    if (!empty($med_results)) {
        foreach ($med_results as $row) {
            $saleId = $row['sale_id'];
            $patient_id = $row['patient_id'];

            // Fetch Appointment for THIS specific row
            $sql_app = "SELECT * FROM {$prefix}appointments WHERE paitent_id = '{$patient_id}' AND paitent_type = 'new_patient' LIMIT 1";
            $app_result = run_select_query($sql_app);

            // Fetch Center info based on the appointment
            $app_center_name = "N/A";
            if (!empty($app_result['appoitment_for'])) {
                $sql_center = "SELECT center_name, state_name, center_code, center_gst FROM {$prefix}centers WHERE center_number = '" . $app_result['appoitment_for'] . "' LIMIT 1";
                $center_data = run_select_query($sql_center);
                $app_center_name = $center_data['center_name'] ?? "N/A";
            }

            // Calculations & Safeguards
            $qty            = abs((int) ($row['quantity_change'] ?? 0));
            $packSize       = (!empty($row['pack_size']) && $row['pack_size'] > 0) ? $row['pack_size'] : 1;
            $mrpUnit        = ($row['mrp'] ?? 0) / $packSize; 
            $gstRate        = (float) ($row['gst_rate'] ?? 0);
            $total          = (float) ($row['total_value'] ?? 0);

            $mrpValue       = $mrpUnit * $qty;
            $taxableValue   = ($gstRate > 0) ? (($total * 100) / (100 + $gstRate)) : $total;
            $gstAmount      = $total - $taxableValue;
            $discountAmount = $mrpValue - $total;
            
            // Round-off Calculation
            $receiveRounded = round($total);               // Rounds total to nearest whole integer
            $roundOff       = $receiveRounded - $total;     // Difference (+ or -) for accounting balance
            
            // Grouping Logic
            if (!isset($med_grouped[$saleId])) {
                $med_grouped[$saleId] = [
                    'type'             => 'Medicine',
                    'patient_id'       => $row['patient_id'] ?? '',
                    'patient_name'     => $row['patient_name'] ?? '',
                    'billing_center'   => $row['center_name'] ?? 'N/A',
                    'origin_center'    => $app_center_name,
                    'cost_center'      => $row['center_name'] ?? 'N/A',
                    'center_code'      => $row['center_code'] ?? '',
                    'receipt_number'   => $row['sale_number'] ?? '',
                    'on_date'          => !empty($row['sale_date']) ? date("d-m-Y", strtotime($row['sale_date'])) : '',
                    'updated_date'     => !empty($row['updated_at']) ? date("d-m-Y", strtotime($row['updated_at'])) : '',
                    'biller_name'      => $row['payment_approved_by_name'] ?? 'N/A',
                    'payment_method'   => $row['payment_method'] ?? '',
                    'status'           => $row['payment_status'] ?? '',
                    'series_number'    => $row['series_number'] ?? '',
                    'company_state'    => $row['state_name'] ?? '',
                    'company_gstin'    => $row['center_gst'] ?? '',
                    'party_state'      => $row['state_name'] ?? '',
                    'place_of_supply'  => $row['state_name'] ?? '',
                    'items'            => []
                ];
            }

            // Add medicine as an item to this Sale ID
            $med_grouped[$saleId]['items'][] = [
                'item_name'       => $row['medicine_name'] ?? 'Unknown',
                'code'            => $row['hsn_code'] ?? '',
                'batch_no'        => $row['batch_number'] ?? '',
                'expiry'          => $row['expiry_date'] ?? '',
                'quantity'        => $qty,
                'unit_price'      => number_format($mrpUnit, 2, '.', ''),
                'discount_amt'    => number_format($discountAmount, 2, '.', ''),
                'taxable_value'   => number_format($taxableValue, 2, '.', ''),
                'gst_rate'        => number_format($gstRate, 2, '.', ''),
                'gst_amount'      => number_format($gstAmount, 2, '.', ''),
                'receive_amount'  => number_format($receiveRounded, 2, '.', '')
            ];
        }
    }

// 7. Final Merge into Master Array
if (!empty($med_grouped)) {
    foreach($med_grouped as $sale) {
        $all_transactions[] = $sale;
    }
}

	// =========================================================================
    // PART 2: MEDICINE RETURNS
    // =========================================================================
    
    // Today's timestamp starting at 12:00 AM (Midnight Reset)
    $today_start = date('Y-m-d 00:00:00');

    $sql_ret = "
        SELECT 
            r.id AS return_id, r.patient_id, r.patient_name, c.center_name, c.center_code, 
            c.state_name, c.center_gst,
            r.return_number, r.return_date, r.status, r.return_reason, r.updated_at,
            m.medicine_name, mb.batch_number, mb.expiry_date, 
            m.hsn_code, m.gst_rate, m.pack_size, mb.mrp,
            ri.quantity_returned, ri.total_amount AS item_total,
            ri.final_amount AS item_final, ri.discount_amount AS item_discount,
            r.tally_status
        FROM medicine_returns r
        LEFT JOIN hms_centers c ON r.center_id = c.id
        LEFT JOIN medicine_return_items ri ON r.id = ri.return_id
        LEFT JOIN medicine_batches mb ON ri.batch_id = mb.id
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        WHERE r.status = 'APPROVED' 
            AND r.tally_status = '1'
            AND r.tally_send_date >= '{$today_start}'
        ORDER BY r.id DESC
        LIMIT 700
    ";

    $ret_results = $this->db->query($sql_ret)->result_array();
    $ret_grouped = [];
    $prefix = $this->config->item('db_prefix');

    if (!empty($ret_results)) {
        foreach ($ret_results as $row) {
            $retId = $row['return_id'];
            $patient_id = $row['patient_id'];

            // 1. Grouping Initialization & Extra Data Fetching
            if (!isset($ret_grouped[$retId])) {
                
                // Fetch appointment for THIS specific row
                $sql_app = "SELECT * FROM {$prefix}appointments WHERE paitent_id = '{$patient_id}' AND paitent_type = 'new_patient' LIMIT 1";
                $app_result = run_select_query($sql_app);

                $app_center_name = "N/A";
                if (!empty($app_result['appoitment_for'])) {
                    $sql_center = "SELECT center_name, state_name, center_code, center_gst FROM {$prefix}centers WHERE center_number = '" . $app_result['appoitment_for'] . "' LIMIT 1";
                    $center_data = run_select_query($sql_center);
                    $app_center_name = $center_data['center_name'] ?? "N/A";
                }

                $ret_grouped[$retId] = [
                    'type'             => 'Medicine Return',
                    'patient_id'       => $row['patient_id'] ?? '',
                    'patient_name'     => $row['patient_name'] ?? '',
                    'billing_center'   => $row['center_name'] ?? 'N/A',
                    'origin_center'    => $app_center_name, 
                    'cost_center'      => $row['center_name'] ?? 'N/A',
                    'center_code'      => $row['center_code'] ?? '',
                    'receipt_number'   => $row['return_number'] ?? '',
                    'on_date'          => !empty($row['return_date']) ? date("d-m-Y", strtotime($row['return_date'])) : '',
                    'updated_date'     => !empty($row['updated_at']) ? date("d-m-Y", strtotime($row['updated_at'])) : '',
                    'biller_name'      => 'System Approved',
                    'payment_method'   => 'Refund',
                    'status'           => $row['status'] ?? '',
                    'reason'           => $row['return_reason'] ?? '',
                    'company_state'    => $row['state_name'] ?? '',
                    'company_gstin'    => $row['center_gst'] ?? '',
                    'party_state'      => $row['state_name'] ?? '',
                    'place_of_supply'  => $row['state_name'] ?? '',
                    'items'            => []
                ];
            }

            // 2. Calculations for Items
            $qty             = abs((int) ($row['quantity_returned'] ?? 0));
            $packSize        = (!empty($row['pack_size']) && $row['pack_size'] > 0) ? $row['pack_size'] : 1;
            $mrpUnit         = ($row['mrp'] ?? 0) / $packSize; 
            $gstRate         = (float) ($row['gst_rate'] ?? 0);
            $totalRefundItem = (float) ($row['item_final'] ?? 0); 

            $taxableValue    = ($gstRate > 0) ? (($totalRefundItem * 100) / (100 + $gstRate)) : $totalRefundItem;
            $gstAmount       = $totalRefundItem - $taxableValue;
            $discountAmount  = (float) ($row['item_discount'] ?? 0);

            // 3. Add Item to Group
            $ret_grouped[$retId]['items'][] = [
                'item_name'       => $row['medicine_name'] ?? 'Unknown',
                'code'            => $row['hsn_code'] ?? '',
                'batch_no'        => $row['batch_number'] ?? '',
                'expiry'          => $row['expiry_date'] ?? '',
                'quantity'        => $qty,
                'unit_price'      => number_format($mrpUnit, 2, '.', ''),
                'discount_amt'    => number_format($discountAmount, 2, '.', ''),
                'taxable_value'   => number_format($taxableValue, 2, '.', ''),
                'gst_rate'        => number_format($gstRate, 2, '.', ''),
                'gst_amount'      => number_format($gstAmount, 2, '.', ''),
                'receive_amount'  => number_format($totalRefundItem, 2, '.', '')
            ];
        }
    }

// 4. Master Array Merge
foreach($ret_grouped as $return) {
    $all_transactions[] = $return;
}
    // =========================================================================
    // PART 2: PROCEDURE SALES
    // =========================================================================
    $procedure_raw = $this->accounts_model->get_all_sales_for_tally(); 

    if (!empty($procedure_raw)) {
        foreach ($procedure_raw as $sale) {
            $pt = $this->db->query("SELECT * FROM hms_patients WHERE patient_id = ?", [$sale["patient_id"]])->row_array();
            $origin = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["origins"]])->row_array();
            $cost = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_at"]])->row_array();
			$billing = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_from"]])->row_array();
            $biller = $this->db->query("SELECT * FROM hms_employees WHERE employee_number = ?", [$sale["biller_id"]])->row_array();

            $formatted_proc = [
                'type'             => 'Procedure',
                'patient_id'       => $sale["patient_id"],
                'patient_name'     => ($pt['wife_name'] ?? '') . ' W/O ' . ($pt['husband_name'] ?? ''),
                'billing_center'   => $billing['center_name'] ?? 'N/A',
                'origin_center'    => $origin['center_name'] ?? 'N/A',
				'cost_center'    => $cost['center_name'] ?? 'N/A',
				'center_code'      => $cost['center_code'],
                'receipt_number'   => $sale["receipt_number"],
                'on_date'          => date("d-m-Y", strtotime($sale["on_date"])),
                'biller_name'      => $biller['name'] ?? 'N/A',
                'payment_method'   => $sale["payment_method"] ?? "",
                'status'           => $sale["status"] ?? "",
				'series_number'    => $sale["series_number"],
				'company_state'    => $billing['state_name'],
				'company_gstin'    => $billing['center_gst'],
				'party_state'      => $billing['state_name'],
				'place_of_supply'  => $billing['state_name'],
                'items'            => []
            ];

            if (!empty($sale['data'])) {
                $unserialized = @unserialize($sale['data']);
                if (isset($unserialized['patient_procedures']) && is_array($unserialized['patient_procedures'])) {
                    foreach($unserialized['patient_procedures'] as $p) {
                         $formatted_proc['items'][] = [
                            'item_name'       => $sale["procedure_name"] . ' - ' . ($p["sub_procedure"] ?? ''),
                            'code'            => $p["sub_procedures_code"] ?? '',
                            'batch_no'        => '',
                            'expiry'          => '',
                            'quantity'        => 1,
                            'unit_price'      => number_format((float)($p["sub_procedures_price"]??0), 2, '.', ''),
                            'discount_amt'    => number_format((float)($p["sub_procedures_discount"]??0), 2, '.', ''),
                            'taxable_value'   => '',
                            'gst_rate'        => 0,
                            'gst_amount'      => 0,
                            'receive_amount'    => number_format((float)($p["sub_procedures_paid_price"]??0), 2, '.', '')
                        ];
                    }
                }
            }
            $all_transactions[] = $formatted_proc;
        }
    }

 // =========================================================================
    // PART 3: CONSULTATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12 बजे के बाद से अब तक का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $this->db->select('hms_consultation.*, hms_patients.wife_name, hms_patients.husband_name, 
        bill_center.center_name as billing_center_name,
        bill_center.center_code as center_code,
        bill_center.state_name as center_state_name, 
        bill_center.center_gst as center_gst_number, 
        origin_center.center_name as origin_center_name,
        hms_employees.name as biller_name');
    $this->db->from('hms_consultation');
    $this->db->join('hms_patients', 'hms_patients.patient_id = hms_consultation.patient_id', 'left');
    
    // hms_centers टेबल बिलिंग सेंटर के रूप में जॉइन है
    $this->db->join('hms_centers as bill_center', 'bill_center.center_number = hms_consultation.billing_at', 'left');
    $this->db->join('hms_centers as origin_center', 'origin_center.center_number = hms_consultation.origins', 'left');
    $this->db->join('hms_employees', 'hms_employees.employee_number = hms_consultation.biller_id', 'left');
    
    $this->db->where_in('hms_consultation.status', ['adjust', 'approved']);
    $this->db->where('hms_consultation.tally_status', '1');
    
    // रात 12 बजे के बाद से अब तक का डेटा (Auto Filter)
    $this->db->where('hms_consultation.tally_send_date >=', $today_start);
    
    // फिक्स: $this0 की जगह $this किया गया है
    $this->db->limit(300);
    
    $consult_rows = $this->db->get()->result_array();

    if (!empty($consult_rows)) {
        foreach ($consult_rows as $row) {
            
            // Safe updated date check
            $updated_date = '';
            if (!empty($row['modified_on'])) {
                $updated_date = date("d-m-Y", strtotime($row['modified_on']));
            } elseif (!empty($row['updated_at'])) {
                $updated_date = date("d-m-Y", strtotime($row['updated_at']));
            }

            $all_transactions[] = [
                'type'             => 'Consultation',
                'patient_id'       => $row['patient_id'] ?? '',
                'patient_name'     => trim(($row['wife_name'] ?? '') . ' W/O ' . ($row['husband_name'] ?? '')),
                'billing_center'   => $row['billing_center_name'] ?? 'N/A',
                'origin_center'    => $row['origin_center_name'] ?? 'N/A',
                'cost_center'      => $row['billing_center_name'] ?? 'N/A',
                'center_code'      => $row['center_code'] ?? '',
                'receipt_number'   => $row["receipt_number"] ?? '',
                'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
                'updated_date'     => $updated_date,
                'biller_name'      => $row['biller_name'] ?? 'N/A',
                'payment_method'   => $row['payment_method'] ?? '',
                'series_number'    => $row["series_number"] ?? '',
                'status'           => $row['status'] ?? '',
                'company_state'    => $row['center_state_name'] ?? '',
                'company_gstin'    => $row['center_gst_number'] ?? '',
                'party_state'      => $row['center_state_name'] ?? '',
                'place_of_supply'  => $row['center_state_name'] ?? '',
                'items'            => [[
                    'item_name'       => 'Consultation Charges',
                    'code'            => 'CONS',
                    'batch_no'        => '',
                    'expiry'          => '',
                    'quantity'        => 1,
                    'unit_price'      => number_format((float)($row['totalpackage'] ?? 0), 2, '.', ''),
                    'discount_amt'    => number_format((float)($row['discount_amount'] ?? 0), 2, '.', ''),
                    'taxable_value'   => '', 
                    'gst_rate'        => 0,
                    'gst_amount'      => 0,
                    'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
                ]]
            ];
        }
    }

    // =========================================================================
    // PART 4: REGISTRATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12:00 AM के बाद का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $this->db->select('hms_registation.*, hms_patients.wife_name, hms_patients.husband_name, 
        bill_center.center_name as billing_center_name, bill_center.state_name, bill_center.center_code, 
        bill_center.center_gst, origin_center.center_name as origin_center_name,
        hms_employees.name as biller_name');
    $this->db->from('hms_registation');
    $this->db->join('hms_patients', 'hms_patients.patient_id = hms_registation.patient_id', 'left');
    $this->db->join('hms_centers as bill_center', 'bill_center.center_number = hms_registation.billing_at', 'left');
    $this->db->join('hms_centers as origin_center', 'origin_center.center_number = hms_registation.origins', 'left');
    $this->db->join('hms_employees', 'hms_employees.employee_number = hms_registation.biller_id', 'left');
    
    $this->db->where_in('hms_registation.status', ['approved', 'adjust', 'Approved', 'Adjust']);
    $this->db->where('hms_registation.tally_status', '1');
    
    // रात 12 बजे के बाद ऑटोमेटिक डेटा रिमूव/फिल्टर करने के लिए (Dynamic Midnight Reset)
    $this->db->where('hms_registation.tally_send_date >=', $today_start);
    
    $this->db->order_by('hms_registation.id', 'DESC');
    $this->db->limit(300);
    
    $reg_rows = $this->db->get()->result_array();

    foreach ($reg_rows as $row) {
        $all_transactions[] = [
            'type'             => 'Registration',
            'patient_id'       => $row['patient_id'] ?? '',
            'patient_name'     => ($row['wife_name'] ?? '') . ' W/O ' . ($row['husband_name'] ?? ''),
            'billing_center'   => $row['billing_center_name'] ?? 'N/A',
            'origin_center'    => $row['origin_center_name'] ?? 'N/A',
            'cost_center'      => $row['billing_center_name'] ?? 'N/A',
            'center_code'      => $row['center_code'] ?? '',
            'receipt_number'   => $row["receipt_number"] ?? '',
            'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
            'updated_date'     => !empty($row["modified_on"]) ? date("d-m-Y", strtotime($row["modified_on"])) : '',
            'biller_name'      => $row['biller_name'] ?? 'N/A',
            'payment_method'   => $row['payment_method'] ?? '',
            'series_number'    => $row['series_number'] ?? '',
            'status'           => $row['status'] ?? '',
            'company_state'    => $row['state_name'] ?? '',
            'company_gstin'    => $row['center_gst'] ?? '',
            'party_state'      => $row['state_name'] ?? '',
            'place_of_supply'  => $row['state_name'] ?? '',
            'items'            => [[
                'item_name'       => 'Registration Fees',
                'code'            => 'REG',
                'batch_no'        => '',
                'expiry'          => '',
                'quantity'        => 1,
                'unit_price'      => number_format((float)($row['totalpackage'] ?? 0), 2, '.', ''),
                'discount_amt'    => number_format((float)($row['discount_amount'] ?? 0), 2, '.', ''),
                'taxable_value'   => '',
                'gst_rate'        => 0,
                'gst_amount'      => 0,
                'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
            ]]
        ];
    }

// =========================================================================
    // PART 5: INVESTIGATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12 बजे के बाद से अब तक का डेटा)
    $today_start = date('Y-m-d 00:00:00');

    $invest_rows = $this->db->query("
        SELECT * FROM hms_patient_investigations 
        WHERE `status`='approved' 
          AND `tally_status`='1' 
          AND `tally_send_date` >= ? 
        ORDER BY id DESC 
        LIMIT 500", [$today_start])->result_array();

    foreach ($invest_rows as $sale) {
        $pt = $this->db->query("SELECT * FROM hms_patients WHERE patient_id = ?", [$sale["patient_id"]])->row_array();
        $bill_c = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_at"]])->row_array();
        $org_c = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["origins"]])->row_array();
        $biller = $this->db->query("SELECT * FROM hms_employees WHERE employee_number = ?", [$sale["biller_id"]])->row_array();

        $formatted_inv = [
            'type'             => 'Investigation',
            'patient_id'       => $sale["patient_id"],
            'patient_name'     => ($pt['wife_name'] ?? '') . ' W/O ' . ($pt['husband_name'] ?? ''),
            'billing_center'   => $bill_c['center_name'] ?? 'N/A',
            'origin_center'    => $org_c['center_name'] ?? 'N/A',
            'cost_center'      => $bill_c['center_name'] ?? 'N/A',
            'center_code'      => $bill_c['center_code'] ?? '',
            'receipt_number'   => $sale["receipt_number"] ?? '',
            'on_date'          => !empty($sale["on_date"]) ? date("d-m-Y", strtotime($sale["on_date"])) : '',
            'biller_name'      => $biller['name'] ?? 'N/A',
            'payment_method'   => $sale["payment_method"] ?? "",
            'status'           => $sale["status"] ?? "",
            'series_number'    => $sale['series_number'] ?? '',
            'company_state'    => $bill_c['state_name'] ?? '',
            'company_gstin'    => $bill_c['center_gst'] ?? '',
            'party_state'      => $bill_c['state_name'] ?? '',
            'place_of_supply'  => $bill_c['state_name'] ?? '',
            'items'            => []
        ];

        if (!empty($sale['investigations'])) {
            $unserialized = @unserialize($sale['investigations']);
            if ($unserialized && is_array($unserialized)) {
                $groups = [
                    'female_investigation' => ['name' => 'female_investigation_name', 'code' => 'female_investigation_code', 'price'=> 'female_investigation_price', 'disc' => 'female_investigation_discount'],
                    'male_investigation'   => ['name' => 'male_investigation_name', 'code' => 'male_investigation_code', 'price'=> 'male_investigation_price', 'disc' => 'male_investigation_discount']
                ];
                foreach ($groups as $group_key => $keys) {
                    if (!empty($unserialized[$group_key]) && is_array($unserialized[$group_key])) {
                        foreach ($unserialized[$group_key] as $item) {
                            if (!is_array($item)) continue;
                            
                            $price        = isset($item[$keys['price']]) ? (float)$item[$keys['price']] : 0;
                            $percent      = isset($item[$keys['disc']])  ? (float)$item[$keys['disc']]  : 0;
                            $discount_amt = ($price * $percent) / 100;
                            $exactTotal   = $price - $discount_amt;

                            // Round-off calculation
                            $receiveRounded = round($exactTotal);
                            $roundOff       = $receiveRounded - $exactTotal;

                            $inv_q = $this->db->query("
                                SELECT hms_master_investigations.investigation_name, hms_master_investigations.code 
                                FROM hms_investigation 
                                JOIN hms_master_investigations ON hms_investigation.master_id = hms_master_investigations.ID 
                                WHERE hms_investigation.ID = ?", [(int)$item[$keys['name']]])->row_array();

                            $formatted_inv['items'][] = [
                                'item_name'     => $inv_q['investigation_name'] ?? 'Unknown',
                                'code'          => $inv_q['code'] ?? '',
                                'batch_no'      => '',
                                'expiry'        => '',
                                'quantity'      => 1,
                                'unit_price'    => number_format($price, 2, '.', ''),
                                'discount_amt'  => number_format($discount_amt, 2, '.', ''),
                                'taxable_value' => '',
                                'gst_rate'      => 0,
                                'gst_amount'    => 0,
                                'receive_amount'=> number_format($receiveRounded, 2, '.', '')
                            ];
                        }
                    }
                }
            }
        }
        $all_transactions[] = $formatted_inv;
    }

    // =========================================================================
    // PART 6: FELLOWSHIP / TRAINING SALES
    // =========================================================================
    $fellow_rows = $this->db->query("SELECT * FROM hms_fellowship_training WHERE `status` IN ('1', '3') AND `tally_status`='1' LIMIT 20")->result_array();

    foreach ($fellow_rows as $row) {
        $status_text = ($row['status'] == '1') ? 'approved' : 'cancel';
        $price = $row['price'] ?? 0;
        $discount = $row['discount_amount'] ?? 0;
        
        $all_transactions[] = [
            'type'             => 'Fellowship',
            'patient_id'       => $row['studentid'],
            'patient_name'     => ($row['name'] ?? '') . ' S/O ' . ($row['fname'] ?? ''),
            'billing_center'   => $row['place_of_supply'] ?? 'N/A',
            'origin_center'    => 'N/A', 
			'cost_center'      => 'N/A',
			'center_code'      => 'N/A',
            'receipt_number'   => $row['receipt'] ?? '',
            'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
            'biller_name'      => 'N/A', 
            'payment_method'   => $row['payment_method'],
            'status'           => $status_text,
			'company_state'    => '',
			'company_gstin'    => '',
			'party_state'      => '',
			'place_of_supply'  => '',
            'items'            => [[
                'item_name'       => 'Fellowship: ' . ($row['course'] ?? ''),
                'code'            => $row['code'] ?? '',
                'batch_no'        => '',
                'expiry'          => '',
                'quantity'        => 1,
                'unit_price'      => number_format((float)$price, 2, '.', ''),
                'discount_amt'    => number_format((float)$discount, 2, '.', ''),
                'taxable_value'   => '',
                'gst_rate'        => number_format((float)($row['gst']??0), 2, '.', ''),
                'gst_amount'      => number_format((float)($row['gst_amount']??0), 2, '.', ''),
                'receive_amount'    => number_format((float)($row['payment_done']??0), 2, '.', '')
            ]]
        ];
    }

    // =========================================================================
    // FINAL OUTPUT: CORRECTION IS HERE
    // =========================================================================
    // Wrap the array in a named key (e.g., 'transactions') so Tally accepts it.
    $output_data = [
        "Sales_receipt" => $all_transactions
    ];

    header('Content-Type: application/json');
    echo json_encode($output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// New update push test

public function procedure_tally()
{
    // Force JSON header
    $this->output->set_content_type('application/json');

    $ids = $this->input->post('ids');

    if (empty($ids)) {
        echo json_encode([
            'success' => false,
            'message' => 'No IDs received.'
        ]);
        exit; // IMPORTANT
    }

    $success_count = 0;
    $error_count = 0;
    $already_sent_count = 0;

    // करंट डेट और टाइम निकालें (Tally में भेजने की तारीख)
    $current_date_time = date('Y-m-d H:i:s');

    foreach ($ids as $id) {

        $this->db->select('tally_status');
        $this->db->where('id', $id);
        $q = $this->db->get('hms_patient_procedure');
        $row = $q->row_array();

        if (!empty($row) && $row['tally_status'] == '1') {
            $already_sent_count++;
            continue;
        }

        $this->db->where('id', $id);
        // यहाँ tally_status के साथ-साथ tally_send_date को भी अपडेट एरे में जोड़ दिया है
        $update = $this->db->update('hms_patient_procedure', [
            'tally_status'    => 1,
            'tally_send_date' => $current_date_time
        ]);

        if ($update) {
            $success_count++;
        } else {
            $error_count++;
        }
    }

    $msg = "";

    if ($success_count > 0) {
        $msg .= "$success_count records marked as Sent. ";
    }

    if ($already_sent_count > 0) {
        $msg .= "($already_sent_count already sent). ";
    }

    if ($error_count > 0) {
        $msg .= "($error_count failed).";
    }

    if (empty($msg)) {
        $msg = "Failed to update records.";
    }

    echo json_encode([
        'success' => ($success_count > 0 || $already_sent_count > 0),
        'message' => trim($msg)
    ]);

    exit;   // 🔥 VERY IMPORTANT
}

public function partial_tally() {

    $payment_ids = $this->input->post('payment_ids');

    if (empty($payment_ids)) {
        echo json_encode(['success' => false, 'message' => 'No IDs received.']);
        return;
    }

    $success_count = 0;
    $error_count = 0;
    $already_sent_count = 0; // Counter for duplicates

    foreach ($payment_ids as $id) {

        // 1. Get the current status for THIS SPECIFIC ID
        $this->db->select('tally_status');
        $this->db->where('ID', $id);
        $q = $this->db->get('hms_patient_payments');
        
        $row = $q->row_array();

        // 2. CHECK: Does the row exist? Is it already sent?
        if (!empty($row) && $row['tally_status'] == '1') {
            $already_sent_count++;
            continue; // Skip to the next ID
        }

        // --- Actual Tally Logic would go here ---
        $result = true; 

        if ($result) {
            $success_count++;
            
            // Update status to 1 and record the send timestamp
            $update_data = [
                'tally_status'    => 1,
                'tally_send_date' => date('Y-m-d H:i:s')
            ];

            $this->db->where('ID', $id)->update('hms_patient_payments', $update_data);
        } else {
            $error_count++;
        }
    }

    // 3. Build the response message
    if ($success_count > 0 || $already_sent_count > 0) {
        
        $msg = "";
        
        if ($success_count > 0) {
            $msg .= "$success_count records sent successfully. ";
        }
        
        if ($already_sent_count > 0) {
            $msg .= "($already_sent_count records were already sent). ";
        }

        if ($error_count > 0) {
            $msg .= "($error_count failed).";
        }

        echo json_encode(['success' => true, 'message' => trim($msg)]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send records to Tally.']);
    }
}


public function partialpayment_tally() {
    
    $q = $this->db->query("SELECT * FROM hms_patient_payments WHERE status IN ('1', '3') AND tally_status='1' ORDER BY modified_on DESC LIMIT 20");
	$payment_rows = $q->result_array();

	$sales_data = []; 

    // 2. Loop through the database results
    foreach ($payment_rows as $payment_row) {
        
       $id = $payment_row['ID'];

        // A. Fetch PROCEDURE details linked to this receipt
        $receipt_no = $payment_row['billing_id'];
        
        // REMOVED 'echo' here because it breaks JSON
        $proc_q = $this->db->query("SELECT * FROM hms_patient_procedure WHERE receipt_number = '$receipt_no'");
        $procedures = $proc_q->result_array();

        // Format Procedures Array
        $proc_list = [];
        foreach($procedures as $proc) {
            $proc_list[] = [
                "procedure_name"              => $proc['procedure_name'],
                "category"                    => $proc['category'], 
                "sub_procedure"               => $proc['procedure_id'],
                "sub_procedures_code"         => $proc['code'], 
                "sub_procedures_price"        => $proc['totalpackage'],
                "sub_procedures_discount"     => $proc['discount_amount'],
                "sub_procedures_after_discount" => (float)$proc['totalpackage'] - (float)$proc['discount_amount'],
                "sub_procedures_paid_price"   => $proc['payment_done']
            ];
        }

        // C. Build the Record
        $sales_data[] = [
            "patient_id"      => $payment_row['patient_id'],
			"billing_id" => $payment_row['billing_id'],
			"receipt_number"  => $payment_row['refrence_number'],
			"payment_done"  => $payment_row['payment_done'],
            "on_date"         => date('d-m-Y', strtotime($payment_row['on_date'])),
            "biller_name"     => "N/A", 
            "procedure_type"  => "New",
            "patient_procedures" => $proc_list,
            "payment_method"  => $payment_row['payment_method'],
           "status" => ($payment_row['status'] == 1) ? 'Approved' : (($payment_row['status'] == 3) ? 'Cancel' : 'Pending')
        ];

    }

    // 3. Return JSON Response
    header('Content-Type: application/json');
    echo json_encode(["Sales_Details" => $sales_data], JSON_PRETTY_PRINT);
}

public function order_invoice()
{
    // Initialize the master array
    $all_transactions = [];

   // =========================================================================
    // PART 1: MEDICINE SALES
    // =========================================================================
    
    // Dynamic midnight timestamp for daily reset
    $today_start = date('Y-m-d 00:00:00');

    $sql_med = "
       SELECT 
            s.id AS sale_id, s.patient_id, s.patient_name, c.center_name, c.state_name, c.center_gst, c.center_code,
            s.sale_number, s.sale_date, s.tally_send_date, s.payment_method, s.payment_status,
            s.payment_approved_by_name, s.series_number, m.medicine_name, mb.batch_number,
            mb.expiry_date, m.hsn_code, m.gst_rate, m.pack_size, mb.mrp,
            sm.quantity_change, sm.total_value, s.tally_status, s.total_amount
        FROM sales s
        INNER JOIN hms_centers c ON s.center_id = c.id
        INNER JOIN stock_movements sm ON s.id = sm.reference_id
        INNER JOIN medicine_batches mb ON sm.batch_id = mb.id
        INNER JOIN medicines m ON mb.medicine_id = m.id
        WHERE sm.movement_type = 'SALE' 
            AND sm.to_location_type = 'SALE'
            AND s.payment_status = 'PAID' 
            AND s.tally_status = 'APPROVED_TALLY'
            AND s.tally_send_date >= '{$today_start}'
        ORDER BY s.updated_at DESC 
        LIMIT 800
    ";
    
    $med_results = $this->db->query($sql_med)->result_array();

    $med_grouped = [];
    $prefix = $this->config->item('db_prefix');

    if (!empty($med_results)) {
        foreach ($med_results as $row) {
            $saleId = $row['sale_id'];
            $patient_id = $row['patient_id'];

            // Fetch Appointment for THIS specific row
            $sql_app = "SELECT * FROM {$prefix}appointments WHERE paitent_id = '$patient_id' AND paitent_type = 'new_patient' LIMIT 1";
            $app_result = run_select_query($sql_app);

            // Fetch Center info based on the appointment
            $app_center_name = "N/A";
            if (!empty($app_result['appoitment_for'])) {
                $sql_center = "SELECT center_name, state_name, center_code, center_gst FROM {$prefix}centers WHERE center_number = '" . $app_result['appoitment_for'] . "' LIMIT 1";
                $center_data = run_select_query($sql_center);
                $app_center_name = $center_data['center_name'] ?? "N/A";
            }

            // Calculations
            $qty            = abs((int) ($row['quantity_change'] ?? 0));
            $packSize       = (!empty($row['pack_size']) && $row['pack_size'] > 0) ? $row['pack_size'] : 1;
            $mrpUnit        = ($row['mrp'] ?? 0) / $packSize; 
            $gstRate        = (float) ($row['gst_rate'] ?? 0);
            $total          = (float) ($row['total_value'] ?? 0);

            $mrpValue       = $mrpUnit * $qty;
            $taxableValue   = ($gstRate > 0) ? (($total * 100) / (100 + $gstRate)) : $total;
            $gstAmount      = $total - $taxableValue;
            $discountAmount = $mrpValue - $total;
            
            // Round-off Calculation for Item
            $receiveRounded = round($total);               // Rounds to nearest whole integer
            
            // Grouping Logic
            if (!isset($med_grouped[$saleId])) {
                // Round-off Calculation for Total Invoice Amount
                $rawTotalAmount = (float) ($row['total_amount'] ?? 0);
                $roundedTotal   = round($rawTotalAmount);
                $totalRoundOff  = $roundedTotal - $rawTotalAmount;

                $med_grouped[$saleId] = [
                    'type'             => 'Medicine',
                    'patient_id'       => $row['patient_id'] ?? '',
                    'patient_name'     => $row['patient_name'] ?? '',
                    'billing_center'   => $row['center_name'] ?? 'N/A',
                    'origin_center'    => $app_center_name,
                    'cost_center'      => $row['center_name'] ?? 'N/A',
                    'center_code'      => $row['center_code'] ?? '',
                    'receipt_number'   => $row['sale_number'] ?? '',
                    'on_date'          => !empty($row['sale_date']) ? date("d-m-Y", strtotime($row['sale_date'])) : '',
                    'tally_send_date'  => !empty($row['tally_send_date']) ? date("d-m-Y H:i:s", strtotime($row['tally_send_date'])) : '',
                    'biller_name'      => $row['payment_approved_by_name'] ?? 'N/A',
                    'payment_method'   => $row['payment_method'] ?? '',
                    'status'           => $row['payment_status'] ?? '',
                    'total_amount'     => number_format($roundedTotal, 2, '.', ''),
                    'total_round_off'  => number_format($totalRoundOff, 2, '.', ''),
                    'series_number'    => $row['series_number'] ?? '',
                    'company_state'    => $row['state_name'] ?? '',
                    'company_gstin'    => $row['center_gst'] ?? '',
                    'party_state'      => $row['state_name'] ?? '',
                    'place_of_supply'  => $row['state_name'] ?? '',
                    'items'            => []
                ];
            }

            // Add medicine as an item to this Sale ID
            $med_grouped[$saleId]['items'][] = [
                'item_name'       => $row['medicine_name'] ?? 'Unknown',
                'code'            => $row['hsn_code'] ?? '',
                'batch_no'        => $row['batch_number'] ?? '',
                'expiry'          => $row['expiry_date'] ?? '',
                'quantity'        => $qty,
                'unit_price'      => number_format($mrpUnit, 2, '.', ''),
                'discount_amt'    => number_format($discountAmount, 2, '.', ''),
                'taxable_value'   => number_format($taxableValue, 2, '.', ''),
                'gst_rate'        => number_format($gstRate, 2, '.', ''),
                'gst_amount'      => number_format($gstAmount, 2, '.', ''),
                'receive_amount'  => number_format($receiveRounded, 2, '.', '')
            ];
        }
    }

// 7. Final Merge into Master Array
if (!empty($med_grouped)) {
    foreach($med_grouped as $sale) {
        $all_transactions[] = $sale;
    }
}


// =========================================================================
    // PART 2: MEDICINE RETURNS
    // =========================================================================
    
    // Dynamic midnight timestamp for daily reset
    $today_start = date('Y-m-d 00:00:00');

    $sql_ret = "
        SELECT 
            r.id AS return_id, 
            r.patient_id, 
            r.patient_name, 
            r.return_number, 
            r.return_date, 
            r.tally_send_date,
            r.updated_at,
            r.status, 
            r.return_reason,
            r.tally_status,
            c.center_name,
            c.state_name,
            c.center_code,
            c.center_gst,
            m.medicine_name, 
            m.hsn_code, 
            m.gst_rate, 
            m.pack_size, 
            mb.batch_number, 
            mb.expiry_date, 
            mb.mrp,
            ri.quantity_returned,
            ri.total_amount AS item_total,
            ri.final_amount AS item_final, 
            ri.discount_amount AS item_discount
        FROM medicine_returns r
        LEFT JOIN hms_centers c ON r.center_id = c.id
        LEFT JOIN medicine_return_items ri ON r.id = ri.return_id
        LEFT JOIN medicine_batches mb ON ri.batch_id = mb.id
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        WHERE r.status = 'APPROVED' 
          AND r.tally_status = '1'
          AND r.tally_send_date >= '{$today_start}'
        ORDER BY r.id DESC
    ";

    $ret_results = $this->db->query($sql_ret)->result_array();

    // Group items by Return ID
    $ret_grouped = [];

    if (!empty($ret_results)) {
        foreach ($ret_results as $row) {
            $retId = $row['return_id'];
            
            $qty             = (int) $row['quantity_returned'];
            $packSize        = (!empty($row['pack_size']) && $row['pack_size'] > 0) ? (float) $row['pack_size'] : 1;
            $mrpUnit         = (float) $row['mrp'] / $packSize; 
            $gstRate         = (float) $row['gst_rate'];
            $totalRefundItem = (float) $row['item_final']; 

            // Reverse Tax calculation
            $taxableValue   = ($gstRate > 0) ? (($totalRefundItem * 100) / (100 + $gstRate)) : $totalRefundItem;
            $gstAmount      = $totalRefundItem - $taxableValue;
            $discountAmount = (float) ($row['item_discount'] ?? 0);
            
            if (!isset($ret_grouped[$retId])) {
                $ret_grouped[$retId] = [
                    'type'             => 'Medicine Return',
                    'patient_id'       => $row['patient_id'] ?? '',
                    'patient_name'     => $row['patient_name'] ?? '',
                    'billing_center'   => $row['center_name'] ?? 'N/A',
                    'origin_center'    => $row['center_name'] ?? 'N/A',
                    'center_code'      => $row['center_code'] ?? '',
                    'receipt_number'   => $row['return_number'] ?? '',
                    'on_date'          => !empty($row['return_date']) ? date("d-m-Y", strtotime($row['return_date'])) : '',
                    'tally_send_date'  => !empty($row['tally_send_date']) ? date("d-m-Y H:i:s", strtotime($row['tally_send_date'])) : '',
                    'updated_date'     => !empty($row['updated_at']) ? date("d-m-Y", strtotime($row['updated_at'])) : '',
                    'biller_name'      => 'System Approved',
                    'payment_method'   => 'Refund',
                    'status'           => $row['status'] ?? '',
                    'total_amount'     => 0.00, // Dynamically accumulated
                    'reason'           => $row['return_reason'] ?? '',
                    'company_state'    => $row['state_name'] ?? '',
                    'company_gstin'    => $row['center_gst'] ?? '',
                    'party_state'      => $row['state_name'] ?? '',
                    'place_of_supply'  => $row['state_name'] ?? '',
                    'items'            => []
                ];
            }

            // Dynamically add to total amount
            $ret_grouped[$retId]['total_amount'] += $totalRefundItem;

            // Add item details
            $ret_grouped[$retId]['items'][] = [
                'item_name'       => $row['medicine_name'] ?? 'Unknown',
                'code'            => $row['hsn_code'] ?? '',
                'batch_no'        => $row['batch_number'] ?? '',
                'expiry'          => $row['expiry_date'] ?? '',
                'quantity'        => $qty,
                'unit_price'      => number_format($mrpUnit, 2, '.', ''),
                'discount_amt'    => number_format($discountAmount, 2, '.', ''),
                'taxable_value'   => number_format($taxableValue, 2, '.', ''),
                'gst_rate'        => number_format($gstRate, 2, '.', ''),
                'gst_amount'      => number_format($gstAmount, 2, '.', ''),
                'receive_amount'  => number_format($totalRefundItem, 2, '.', '')
            ];
        }

        // Format total_amount for each return header
        foreach ($ret_grouped as &$return) {
            $return['total_amount'] = number_format($return['total_amount'], 2, '.', '');
        }
        unset($return);
    }

// Merge Returns into Master Array
foreach($ret_grouped as $return) {
    $all_transactions[] = $return;
}

    // =========================================================================
    // PART 2: PROCEDURE SALES
    // =========================================================================
    $procedure_raw = $this->accounts_model->get_all_sales_for_tally(); 
    if (!empty($procedure_raw)) {
        foreach ($procedure_raw as $sale) {
            $pt = $this->db->query("SELECT * FROM hms_patients WHERE patient_id = ?", [$sale["patient_id"]])->row_array();
            $origin = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["origins"]])->row_array();
            $billing = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_at"]])->row_array();
			$billing_from = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_from"]])->row_array();
            $biller = $this->db->query("SELECT * FROM hms_employees WHERE employee_number = ?", [$sale["biller_id"]])->row_array();

            $formatted_proc = [
                'type'             => 'Procedure',
                'patient_id'       => $sale["patient_id"],
                'patient_name'     => ($pt['wife_name'] ?? '') . ' W/O ' . ($pt['husband_name'] ?? ''),
                'billing_center'   => $billing['center_name'] ?? 'N/A',
                'origin_center'    => $origin['center_name'] ?? 'N/A',
				'center_code'      => $billing['center_code'],
                'receipt_number'   => $sale["receipt_number"],
                'on_date'          => date("d-m-Y", strtotime($sale["on_date"])),
				'updated_date'          => date("d-m-Y", strtotime($sale["modified_on"])),
                'biller_name'      => $biller['name'] ?? 'N/A',
                'payment_method'   => $sale["payment_method"] ?? "",
                'status'           => $sale["status"] ?? "",
				'total_amount'     => $sale['payment_done'],
				'series_number'    => $sale['series_number'],
				'company_state'    => $billing_from['state_name'],
				'company_gstin'    => $billing_from['center_gst'],
				'party_state'      => $billing_from['state_name'],
				'place_of_supply'  => $billing_from['state_name'],
                'items'            => []
            ];

            if (!empty($sale['data'])) {
                $unserialized = @unserialize($sale['data']);
                if (isset($unserialized['patient_procedures']) && is_array($unserialized['patient_procedures'])) {
                    foreach($unserialized['patient_procedures'] as $p) {
                         $formatted_proc['items'][] = [
                            'item_name'       => $sale["procedure_name"] . ' - ' . ($p["sub_procedure"] ?? ''),
                            'code'            => $p["sub_procedures_code"] ?? '',
                            'batch_no'        => '', 'expiry' => '', 'quantity' => 1,
                            'unit_price'      => number_format((float)($p["sub_procedures_price"]??0), 2, '.', ''),
                            'discount_amt'    => number_format((float)($p["sub_procedures_discount"]??0), 2, '.', ''),
                            'taxable_value'   => '', 'gst_rate' => 0, 'gst_amount' => 0,
                            'receive_amount'    => number_format((float)($p["sub_procedures_paid_price"]??0), 2, '.', '')
                        ];
                    }
                }
            }
            $all_transactions[] = $formatted_proc;
        }
    }

   // =========================================================================
    // PART 3: CONSULTATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12 बजे के बाद से अब तक का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $this->db->select('hms_consultation.*, hms_patients.wife_name, hms_patients.husband_name, 
        bill_center.center_name as billing_center_name,
        bill_center.state_name as center_state_name,
        bill_center.center_code as center_code, 
        bill_center.center_gst as center_gst_number, 
        origin_center.center_name as origin_center_name,
        hms_employees.name as biller_name');
    $this->db->from('hms_consultation');
    $this->db->join('hms_patients', 'hms_patients.patient_id = hms_consultation.patient_id', 'left');
    $this->db->join('hms_centers as bill_center', 'bill_center.center_number = hms_consultation.billing_at', 'left');
    $this->db->join('hms_centers as origin_center', 'origin_center.center_number = hms_consultation.origins', 'left');
    $this->db->join('hms_employees', 'hms_employees.employee_number = hms_consultation.biller_id', 'left');
    
    $this->db->where_in('hms_consultation.status', ['adjust', 'approved']);
    $this->db->where('hms_consultation.tally_status', '1');
    
    // रात 12 बजे के बाद ऑटोमेटिक डेटा रिमूव/फिल्टर करने के लिए
    $this->db->where('hms_consultation.tally_send_date >=', $today_start);
    $this->db->limit(300);
    
    $consult_rows = $this->db->get()->result_array();

    foreach ($consult_rows as $row) {
        $all_transactions[] = [
            'type'             => 'Consultation',
            'patient_id'       => $row['patient_id'] ?? '',
            'patient_name'     => ($row['wife_name'] ?? '') . ' W/O ' . ($row['husband_name'] ?? ''),
            'billing_center'   => $row['billing_center_name'] ?? 'N/A',
            'origin_center'    => $row['origin_center_name'] ?? 'N/A',
            'center_code'      => $row['center_code'] ?? '',
            'receipt_number'   => $row["receipt_number"] ?? '',
            'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
            // बग फिक्स: $sale['modified_on'] की जगह $row का सही वेरिएबल इस्तेमाल किया गया है
            'updated_date'     => !empty($row["modified_on"]) ? date("d-m-Y", strtotime($row["modified_on"])) : '',
            'biller_name'      => $row['biller_name'] ?? 'N/A',
            'payment_method'   => $row['payment_method'] ?? '',
            'total_amount'     => $row['payment_done'] ?? 0,
            'series_number'    => $row['series_number'] ?? '',
            'status'           => $row['status'] ?? '',
            'company_state'    => $row['center_state_name'] ?? '',
            'company_gstin'    => $row['center_gst_number'] ?? '',
            'party_state'      => $row['center_state_name'] ?? '',
            'place_of_supply'  => $row['center_state_name'] ?? '',
            'items'            => [[
                'item_name'       => 'Consultation Charges',
                'code'            => 'CONS',
                'batch_no'        => '', 
                'expiry'          => '', 
                'quantity'        => 1,
                'unit_price'      => number_format((float)($row['totalpackage'] ?? 0), 2, '.', ''),
                'discount_amt'    => number_format((float)($row['discount_amount'] ?? 0), 2, '.', ''),
                'taxable_value'   => '', 
                'gst_rate'        => 0, 
                'gst_amount'      => 0,
                'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
            ]]
        ];
    }

    // =========================================================================
    // PART 4: REGISTRATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12:00 AM के बाद का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $this->db->select('hms_registation.*, hms_patients.wife_name, hms_patients.husband_name, 
        bill_center.center_name as billing_center_name, bill_center.state_name as center_state_name,
        bill_center.center_code as center_code, 
        bill_center.center_gst as center_gst_number, origin_center.center_name as origin_center_name,
        hms_employees.name as biller_name');
    $this->db->from('hms_registation');
    $this->db->join('hms_patients', 'hms_patients.patient_id = hms_registation.patient_id', 'left');
    $this->db->join('hms_centers as bill_center', 'bill_center.center_number = hms_registation.billing_at', 'left');
    $this->db->join('hms_centers as origin_center', 'origin_center.center_number = hms_registation.origins', 'left');
    $this->db->join('hms_employees', 'hms_employees.employee_number = hms_registation.biller_id', 'left');
    
    $this->db->where_in('hms_registation.status', ['approved', 'adjust', 'Approved', 'Adjust']);
    $this->db->where('hms_registation.tally_status', '1');
    
    // रात 12 बजे के बाद पिछला डेटा हटाने के लिए (Dynamic Midnight Reset)
    $this->db->where('hms_registation.tally_send_date >=', $today_start);
    
    $this->db->order_by('hms_registation.id', 'DESC');
    $this->db->limit(200);
    
    $reg_rows = $this->db->get()->result_array();

    foreach ($reg_rows as $row) {
        $all_transactions[] = [
            'type'             => 'Registration',
            'patient_id'       => $row['patient_id'] ?? '',
            'patient_name'     => ($row['wife_name'] ?? '') . ' W/O ' . ($row['husband_name'] ?? ''),
            'billing_center'   => $row['billing_center_name'] ?? 'N/A',
            'origin_center'    => $row['origin_center_name'] ?? 'N/A',
            'center_code'      => $row['center_code'] ?? '',
            'receipt_number'   => $row["receipt_number"] ?? '',
            'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
            // फिक्स: $sale की जगह $row और Safe Check
            'updated_date'     => !empty($row["modified_on"]) ? date("d-m-Y", strtotime($row["modified_on"])) : '',
            'biller_name'      => $row['biller_name'] ?? 'N/A',
            'payment_method'   => $row['payment_method'] ?? '',
            'total_amount'     => $row['payment_done'] ?? 0,
            'series_number'    => $row['series_number'] ?? '',
            'status'           => $row['status'] ?? '',
            'company_state'    => $row['center_state_name'] ?? '',
            'company_gstin'    => $row['center_gst_number'] ?? '',
            'party_state'      => $row['center_state_name'] ?? '',
            'place_of_supply'  => $row['center_state_name'] ?? '',
            'items'            => [[
                'item_name'       => 'Registration Fees',
                'code'            => 'REG',
                'batch_no'        => '', 
                'expiry'          => '', 
                'quantity'        => 1,
                'unit_price'      => number_format((float)($row['totalpackage'] ?? 0), 2, '.', ''),
                'discount_amt'    => number_format((float)($row['discount_amount'] ?? 0), 2, '.', ''),
                'taxable_value'   => '', 
                'gst_rate'        => 0, 
                'gst_amount'      => 0,
                'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
            ]]
        ];
    }

   // =========================================================================
    // PART 5: INVESTIGATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12 बजे के बाद से अब तक का डेटा)
    $today_start = date('Y-m-d 00:00:00');

    $invest_rows = $this->db->query("
        SELECT * FROM hms_patient_investigations 
        WHERE `status`='approved' 
          AND `tally_status`='1' 
          AND `tally_send_date` >= ? 
        ORDER BY id DESC 
        LIMIT 500", [$today_start])->result_array();

    foreach ($invest_rows as $sale) {
        $pt = $this->db->query("SELECT * FROM hms_patients WHERE patient_id = ?", [$sale["patient_id"]])->row_array();
        $bill_c = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_at"]])->row_array();
        $org_c = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["origins"]])->row_array();
        $biller = $this->db->query("SELECT * FROM hms_employees WHERE employee_number = ?", [$sale["biller_id"]])->row_array();

        // Header Total Amount Round-off Calculation
        $rawTotalAmount  = (float) ($sale['payment_done'] ?? 0);
        $roundedTotal    = round($rawTotalAmount);
        $totalRoundOff   = $roundedTotal - $rawTotalAmount;

        $formatted_inv = [
            'type'             => 'Investigation',
            'patient_id'       => $sale["patient_id"],
            'patient_name'     => ($pt['wife_name'] ?? '') . ' W/O ' . ($pt['husband_name'] ?? ''),
            'billing_center'   => $bill_c['center_name'] ?? 'N/A',
            'origin_center'    => $org_c['center_name'] ?? 'N/A',
            'cost_center'      => $bill_c['center_name'] ?? 'N/A',
            'center_code'      => $bill_c['center_code'] ?? '',
            'receipt_number'   => $sale["receipt_number"] ?? '',
            'on_date'          => !empty($sale["on_date"]) ? date("d-m-Y", strtotime($sale["on_date"])) : '',
            'biller_name'      => $biller['name'] ?? 'N/A',
            'payment_method'   => $sale["payment_method"] ?? "",
            'status'           => $sale["status"] ?? "",
            'series_number'    => $sale['series_number'] ?? '',
            'company_state'    => $bill_c['state_name'] ?? '',
            'company_gstin'    => $bill_c['center_gst'] ?? '',
            'party_state'      => $bill_c['state_name'] ?? '',
            'place_of_supply'  => $bill_c['state_name'] ?? '',
            'total_amount'     => number_format($roundedTotal, 2, '.', ''),
           
			'items'            => []
        ];

        if (!empty($sale['investigations'])) {
            $unserialized = @unserialize($sale['investigations']);
            if ($unserialized && is_array($unserialized)) {
                $groups = [
                    'female_investigation' => ['name' => 'female_investigation_name', 'code' => 'female_investigation_code', 'price'=> 'female_investigation_price', 'disc' => 'female_investigation_discount'],
                    'male_investigation'   => ['name' => 'male_investigation_name', 'code' => 'male_investigation_code', 'price'=> 'male_investigation_price', 'disc' => 'male_investigation_discount']
                ];
                foreach ($groups as $group_key => $keys) {
                    if (!empty($unserialized[$group_key]) && is_array($unserialized[$group_key])) {
                        foreach ($unserialized[$group_key] as $item) {
                            if (!is_array($item)) continue;
                            
                            $price        = isset($item[$keys['price']]) ? (float)$item[$keys['price']] : 0;
                            $percent      = isset($item[$keys['disc']])  ? (float)$item[$keys['disc']]  : 0;
                            $discount_amt = ($price * $percent) / 100;
                            $exactReceive = $price - $discount_amt;

                            // Item Receive Amount Round-off Calculation
                            $roundedReceive = round($exactReceive);
                            $itemRoundOff   = $roundedReceive - $exactReceive;

                            $inv_q = $this->db->query("
                                SELECT hms_master_investigations.investigation_name, hms_master_investigations.code 
                                FROM hms_investigation 
                                JOIN hms_master_investigations ON hms_investigation.master_id = hms_master_investigations.ID 
                                WHERE hms_investigation.ID = ?", [(int)$item[$keys['name']]])->row_array();

                            $formatted_inv['items'][] = [
                                'item_name'     => $inv_q['investigation_name'] ?? 'Unknown',
                                'code'          => $inv_q['code'] ?? '',
                                'batch_no'      => '',
                                'expiry'        => '',
                                'quantity'      => 1,
                                'unit_price'    => number_format($price, 2, '.', ''),
                                'discount_amt'  => number_format($discount_amt, 2, '.', ''),
                                'taxable_value' => '',
                                'gst_rate'      => 0,
                                'gst_amount'    => 0,
                                'receive_amount'=> number_format($roundedReceive, 2, '.', '')
                            ];
                        }
                    }
                }
            }
        }
        $all_transactions[] = $formatted_inv;
    }

   // =========================================================================
    // PART 6: FELLOWSHIP / TRAINING SALES
    // =========================================================================
    $fellow_rows = $this->db->query("SELECT * FROM hms_fellowship_training WHERE `status` IN ('1', '3') AND `tally_status`='1' LIMIT 200")->result_array();

    foreach ($fellow_rows as $row) {
        $status_text = ($row['status'] == '1') ? 'approved' : 'cancel';
        $all_transactions[] = [
            'type'             => 'Fellowship',
            'patient_id'       => $row['studentid'] ?? '',
            'patient_name'     => ($row['name'] ?? '') . ' S/O ' . ($row['fname'] ?? ''),
            'billing_center'   => $row['place_of_supply'] ?? 'N/A',
            'origin_center'    => 'N/A',
            'center_code'      => 'N/A', 
            'receipt_number'   => $row['receipt'] ?? '',
            'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
            // फिक्स: $sale की जगह $row का इस्तेमाल और Safe Fallback Check
            'updated_date'     => !empty($row["modified_on"]) ? date("d-m-Y", strtotime($row["modified_on"])) : (!empty($row["updated_at"]) ? date("d-m-Y", strtotime($row["updated_at"])) : ''),
            'biller_name'      => 'N/A', 
            'payment_method'   => $row['payment_method'] ?? '',
            'status'           => $status_text,
            'total_amount'     => "",
            'items'            => [[
                'item_name'       => 'Fellowship: ' . ($row['course'] ?? ''),
                'code'            => $row['code'] ?? '',
                'batch_no'        => '', 
                'expiry'          => '', 
                'quantity'        => 1,
                'unit_price'      => number_format((float)($row['price'] ?? 0), 2, '.', ''),
                'discount_amt'    => number_format((float)($row['discount_amount'] ?? 0), 2, '.', ''),
                'taxable_value'   => '', 
                'gst_rate'        => number_format((float)($row['gst'] ?? 0), 2, '.', ''),
                'gst_amount'      => number_format((float)($row['gst_amount'] ?? 0), 2, '.', ''),
                'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
            ]]
        ];
    }

 // =========================================================================
    // PART 7: PARTIAL PAYMENTS
    // =========================================================================
    // [FIXED] DATE(tally_send_date) = CURDATE() lene se raat 12 baje ke baad pichhle din ke records automatic hat jayenge.
    $partial_q = $this->db->query("SELECT * FROM hms_patient_payments WHERE status IN ('1', '3') AND tally_status='1' AND DATE(tally_send_date) = CURDATE() ORDER BY modified_on DESC LIMIT 500");
    $payment_rows = $partial_q->result_array();

    // यहाँ से पुरानी गलत $bill_c वाली लाइन हटा दी गई है

foreach ($payment_rows as $payment_row) {
    // 1. Patient data fetch
    $pt = $this->db->query("SELECT wife_name, husband_name FROM hms_patients WHERE patient_id = ?", [$payment_row["patient_id"]])->row_array();
    
    // [FIX] सेंटर की जानकारी को हर मरीज की पेमेंट रो के 'billing_at' के आधार पर लूप के अंदर फेच करें
    $bill_c = [];
    if (!empty($payment_row["billing_at"])) {
        $bill_c = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$payment_row["billing_at"]])->row_array();
    }
    
    $receipt_no = $payment_row['billing_id'];
    
    // 2. Procedure data fetch
    $proc_q = $this->db->query("SELECT * FROM hms_patient_procedure WHERE receipt_number = ?", [$receipt_no]);
    $procedures = $proc_q->result_array();

    $partial_items = [];
    $series_number = ''; // Default khali rakhein
    
    if (!empty($procedures)) {
        // Fix: Series number pehle row se nikal lein
        $series_number = isset($procedures[0]['series_number']) ? $procedures[0]['series_number'] : '';
        
        foreach($procedures as $proc) {
            $partial_items[] = [
                'item_name'       => $proc['procedure_name'],
                'code'            => $proc['code'] ?? '',
                'batch_no'        => '', 
                'expiry'          => '', 
                'quantity'        => 1,
                'unit_price'      => number_format((float)$proc['totalpackage'], 2, '.', ''),
                'discount_amt'    => number_format((float)$proc['discount_amount'], 2, '.', ''),
                'taxable_value'   => '', 
                'gst_rate'        => 0, 
                'gst_amount'      => 0,
                'receive_amount'  => number_format((float)$payment_row['payment_done'], 2, '.', ''),
            ];
        }
    } else {
        // Agar us payment ke against procedure delete ho gaya ho ya na mile
        $partial_items[] = [
            'item_name'       => 'Unknown Procedure',
            'code'            => '',
            'batch_no'        => '', 'expiry' => '', 'quantity' => 1,
            'unit_price'      => 0.00,
            'discount_amt'    => 0.00,
            'taxable_value'   => '', 'gst_rate' => 0, 'gst_amount' => 0,
            'receive_amount'  => number_format((float)$payment_row['payment_done'], 2, '.', ''),
        ];
    }

    $all_transactions[] = [
        'type'             => 'Partial Payment',
        'patient_id'       => $payment_row['patient_id'],
        'patient_name'     => ($pt['wife_name'] ?? '') . ' W/O ' . ($pt['husband_name'] ?? ''),
        'billing_center'   => 'N/A',
        'origin_center'    => 'N/A',
        'center_code'      => 'N/A',
        'order_number'     => $payment_row['billing_id'],
        'receipt_number'   => $payment_row['refrence_number'],
        'on_date'          => date('d-m-Y', strtotime($payment_row['on_date'])),
        'biller_name'      => 'N/A',
        'payment_method'   => $payment_row['payment_method'],
        'total_amount'     => $payment_row['payment_done'],
        'status'           => ($payment_row['status'] == 1) ? 'Approved' : 'Cancel',
        'series_number'    => $series_number, 
        'company_state'    => $bill_c['state_name'] ?? '', 
        'company_gstin'    => $bill_c['center_gst'] ?? '', 
        'party_state'      => $bill_c['state_name'] ?? '',
        'place_of_supply'  => $bill_c['state_name'] ?? '',
        'items'            => $partial_items
    ];
}

    // =========================================================================
    // FINAL OUTPUT
    // =========================================================================
    $output_data = [
        "Sales_invoice" => $all_transactions
    ];

    header('Content-Type: application/json');
    echo json_encode($output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}



public function investigations_send_tally() {
    // Force clean JSON output
    header('Content-Type: application/json');
    
    try {
        $payment_ids = $this->input->post('payment_ids');

        if (empty($payment_ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs received.']);
            return;
        }

        $success_count = 0;
        $already_sent_count = 0;
        $current_datetime = date('Y-m-d H:i:s'); // Current timestamp for tally_send_date

        foreach ($payment_ids as $id) {
            $q = $this->db->select('tally_status')->where('ID', $id)->get('hms_patient_investigations');
            $row = $q->row_array();

            if (!empty($row) && $row['tally_status'] == '1') {
                $already_sent_count++;
                continue; 
            }

            // --- IMPORTANT: LIVE TALLY CONNECTION ---
            $result = true; 

            if ($result) {
                // Update both tally_status and tally_send_date
                $this->db->where('ID', $id)->update('hms_patient_investigations', [
                    'tally_status'    => '1',
                    'tally_send_date' => $current_datetime
                ]);
                $success_count++;
            }
        }

        echo json_encode([
            'success' => true, 
            'message' => "Success: $success_count sent. ($already_sent_count already existed)."
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    }
}

public function consultation_send_tally() {
    // Prevent the script from timing out on live server
    set_time_limit(300); 
    ignore_user_abort(true);

    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    try {
        $payment_ids = $this->input->post('payment_ids');

        if (empty($payment_ids)) {
            echo json_encode(['success' => false, 'message' => 'No IDs received.']);
            return;
        }

        $success_count = 0;
        $already_sent_count = 0;
        $current_datetime = date('Y-m-d H:i:s'); // Current timestamp for tally_send_date

        foreach ($payment_ids as $id) {
            // Double check table name case sensitivity on Live
            $q = $this->db->select('tally_status')->where('ID', $id)->get('hms_consultation');
            $row = $q->row_array();

            if (!empty($row) && $row['tally_status'] == '1') {
                $already_sent_count++;
                continue;
            }

            // Update status and send date
            $update = $this->db->where('ID', $id)->update('hms_consultation', [
                'tally_status'    => '1',
                'tally_send_date' => $current_datetime
            ]);
            
            if($update) {
                $success_count++;
            }
        }

        echo json_encode([
            'success' => true, 
            'message' => "Successfully processed $success_count records. ($already_sent_count already existed)."
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    }
}

public function registration_send_tally() {
    // Force clean JSON output
    header('Content-Type: application/json');

    $payment_ids = $this->input->post('payment_ids');

    if (empty($payment_ids)) {
        echo json_encode(['success' => false, 'message' => 'No IDs received.']);
        return;
    }

    $success_count = 0;
    $error_count = 0;
    $already_sent_count = 0; // Track how many were skipped
    $current_datetime = date('Y-m-d H:i:s'); // Current timestamp for tally_send_date

    foreach ($payment_ids as $id) {

        // 1. Get the current status of THIS specific ID
        $this->db->select('tally_status');
        $this->db->where('ID', $id);
        $q = $this->db->get('hms_registation');
        
        $row = $q->row_array();

        // 2. CHECK: If row exists AND tally_status is already 1, skip it
        if (!empty($row) && $row['tally_status'] == '1') {
            $already_sent_count++;
            continue; 
        }

        // --- Tally Logic Here ---
        $result = true; 

        if ($result) {
            $success_count++;
            // Update tally_status and tally_send_date
            $this->db->where('ID', $id)->update('hms_registation', [
                'tally_status'    => '1',
                'tally_send_date' => $current_datetime
            ]);
        } else {
            $error_count++;
        }
    }

    // 3. Build the response message
    if ($success_count > 0 || $already_sent_count > 0) {
        
        $msg = "";
        
        if ($success_count > 0) {
            $msg .= "$success_count records sent successfully. ";
        }
        
        if ($already_sent_count > 0) {
            $msg .= "($already_sent_count records were already sent). ";
        }

        if ($error_count > 0) {
            $msg .= "($error_count failed).";
        }

        echo json_encode(['success' => true, 'message' => trim($msg)]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send records to Tally.']);
    }
}


public function sales_completion_order()
{
    // Initialize the master array
    $all_transactions = [];

   // =========================================================================
    // PART 1: MEDICINE SALES
    // =========================================================================
    
    // आज की तारीख (रात 12:00 AM के बाद का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $sql_med = "
        SELECT 
            s.id AS sale_id, s.patient_id, s.patient_name, c.center_name, c.state_name, c.center_gst, c.center_code,
            s.sale_number, s.sale_date, s.updated_at, s.payment_method, s.payment_status,
            s.payment_approved_by_name, s.series_number, m.medicine_name, mb.batch_number,
            mb.expiry_date, m.hsn_code, m.gst_rate, m.pack_size, mb.mrp,
            sm.quantity_change, sm.total_value, s.tally_status, s.total_amount
        FROM sales s
        INNER JOIN hms_centers c ON s.center_id = c.id
        INNER JOIN stock_movements sm ON s.id = sm.reference_id
        INNER JOIN medicine_batches mb ON sm.batch_id = mb.id
        INNER JOIN medicines m ON mb.medicine_id = m.id
        WHERE sm.movement_type = 'SALE' 
            AND sm.to_location_type = 'SALE'
            AND s.payment_status = 'PAID' 
            AND s.tally_status = 'APPROVED_TALLY'
            AND s.tally_send_date >= '{$today_start}'
        ORDER BY s.id DESC 
        LIMIT 700";
    
    $med_results = $this->db->query($sql_med)->result_array();

    $med_grouped = [];
    $prefix = $this->config->item('db_prefix');

    if (!empty($med_results)) {
        foreach ($med_results as $row) {
            $saleId = $row['sale_id'];
            $patient_id = $row['patient_id'];

            // Fetch Appointment for THIS specific row
            $sql_app = "SELECT * FROM {$prefix}appointments WHERE paitent_id = '$patient_id' AND paitent_type = 'new_patient' LIMIT 1";
            $app_result = run_select_query($sql_app);

            // Fetch Center info based on the appointment
            $app_center_name = "N/A"; 
            if (!empty($app_result['appoitment_for'])) {
                $sql_center = "SELECT center_name FROM {$prefix}centers WHERE center_number = '" . $app_result['appoitment_for'] . "' LIMIT 1";
                $center_data = run_select_query($sql_center);
                $app_center_name = $center_data['center_name'] ?? "N/A";
            }

            // Calculations & Null Safeguards
            $qty            = abs((int) ($row['quantity_change'] ?? 0));
            $packSize       = (!empty($row['pack_size']) && $row['pack_size'] > 0) ? $row['pack_size'] : 1;
            $mrpUnit        = ($row['mrp'] ?? 0) / $packSize; 
            $gstRate        = (float) ($row['gst_rate'] ?? 0);
            $total          = (float) ($row['total_value'] ?? 0);

            $mrpValue       = $mrpUnit * $qty;
            $taxableValue   = ($gstRate > 0) ? (($total * 100) / (100 + $gstRate)) : $total;
            $gstAmount      = $total - $taxableValue;
            $discountAmount = $mrpValue - $total;
            
            // Item Receive Amount Round-off Calculation
            $roundedReceive = round($total);
            $itemRoundOff   = $roundedReceive - $total;

            // Grouping Logic
            if (!isset($med_grouped[$saleId])) {
                // Header Total Amount Round-off Calculation
                $rawTotalAmount = (float) ($row['total_amount'] ?? 0);
                $roundedTotal   = round($rawTotalAmount);
                $totalRoundOff  = $roundedTotal - $rawTotalAmount;

                $med_grouped[$saleId] = [
                    'type'             => 'Medicine',
                    'patient_id'       => $row['patient_id'] ?? '',
                    'patient_name'     => $row['patient_name'] ?? '',
                    'billing_center'   => $row['center_name'] ?? 'N/A',
                    'origin_center'    => $app_center_name,
                    'cost_center'      => $row['center_name'] ?? 'N/A',
                    'center_code'      => $row['center_code'] ?? '',
                    'receipt_number'   => $row['sale_number'] ?? '',
                    'on_date'          => !empty($row['sale_date']) ? date("d-m-Y", strtotime($row['sale_date'])) : '',
                    'updated_date'     => !empty($row['updated_at']) ? date("d-m-Y", strtotime($row['updated_at'])) : '',
                    'biller_name'      => $row['payment_approved_by_name'] ?? 'N/A',
                    'payment_method'   => $row['payment_method'] ?? '',
                    'status'           => $row['payment_status'] ?? '',
                    'total_amount'     => number_format($roundedTotal, 2, '.', ''),
                    'series_number'    => $row['series_number'] ?? '',
                    'company_state'    => $row['state_name'] ?? '', 
                    'company_gstin'    => $row['center_gst'] ?? '',
                    'party_state'      => $row['state_name'] ?? '',
                    'place_of_supply'  => $row['state_name'] ?? '',
                    'items'            => []
                ];
            }

            // Add medicine as an item to this Sale ID
            $med_grouped[$saleId]['items'][] = [
                'item_name'       => $row['medicine_name'] ?? 'Unknown',
                'code'            => $row['hsn_code'] ?? '',
                'batch_no'        => $row['batch_number'] ?? '',
                'expiry'          => $row['expiry_date'] ?? '',
                'quantity'        => $qty,
                'unit_price'      => number_format($mrpUnit, 2, '.', ''),
                'discount_amt'    => number_format($discountAmount, 2, '.', ''),
                'taxable_value'   => number_format($taxableValue, 2, '.', ''),
                'gst_rate'        => number_format($gstRate, 2, '.', ''),
                'gst_amount'      => number_format($gstAmount, 2, '.', ''),
                'receive_amount'  => number_format($roundedReceive, 2, '.', '')
            ];
        }
    }

// 7. Final Merge into Master Array
if (!empty($med_grouped)) {
    foreach($med_grouped as $sale) {
        $all_transactions[] = $sale;
    }
}
    
// =========================================================================
    // PART 2: MEDICINE RETURNS
    // =========================================================================
    
    // आज की तारीख (रात 12:00 AM के बाद का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $sql_ret = "
        SELECT 
            r.id AS return_id, r.patient_id, r.patient_name, c.center_name, c.state_name, c.center_gst, c.center_code,
            r.return_number, r.return_date, r.status, r.return_reason, r.updated_at,
            m.medicine_name, mb.batch_number, mb.expiry_date, 
            m.hsn_code, m.gst_rate, m.pack_size, mb.mrp,
            ri.quantity_returned, ri.total_amount AS item_total,
            ri.final_amount AS item_final, ri.discount_amount AS item_discount,
            r.tally_status
        FROM medicine_returns r
        LEFT JOIN hms_centers c ON r.center_id = c.id
        LEFT JOIN medicine_return_items ri ON r.id = ri.return_id
        LEFT JOIN medicine_batches mb ON ri.batch_id = mb.id
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        WHERE r.status = 'APPROVED' 
            AND r.tally_status = '1'
            AND r.tally_send_date >= '{$today_start}'
        ORDER BY r.id DESC
        LIMIT 700
    ";

    $ret_results = $this->db->query($sql_ret)->result_array();

    // Group items by Return ID
    $ret_grouped = [];

    if (!empty($ret_results)) {
        foreach ($ret_results as $row) {
            $retId = $row['return_id'];
            
            $qty             = abs((int) ($row['quantity_returned'] ?? 0));
            $packSize        = (!empty($row['pack_size']) && $row['pack_size'] > 0) ? (float) $row['pack_size'] : 1;
            $mrpUnit         = ($row['mrp'] ?? 0) / $packSize; 
            $gstRate         = (float) ($row['gst_rate'] ?? 0);
            
            // final_amount is usually the amount being refunded to the patient
            $totalRefundItem = (float) ($row['item_final'] ?? 0); 

            // Calculations (Reverse Tax calculation)
            $taxableValue    = ($gstRate > 0) ? (($totalRefundItem * 100) / (100 + $gstRate)) : $totalRefundItem;
            $gstAmount       = $totalRefundItem - $taxableValue;
            $mrpTotalValue   = $mrpUnit * $qty;
            $discountAmount  = (float) ($row['item_discount'] ?? 0);
            
            if (!isset($ret_grouped[$retId])) {
                $ret_grouped[$retId] = [
                    'type'             => 'Medicine Return',
                    'patient_id'       => $row['patient_id'] ?? '',
                    'patient_name'     => $row['patient_name'] ?? '',
                    'billing_center'   => $row['center_name'] ?? 'N/A',
                    'origin_center'    => $row['center_name'] ?? 'N/A',
                    'center_code'      => $row['center_code'] ?? '',
                    'receipt_number'   => $row['return_number'] ?? '',
                    'on_date'          => !empty($row['return_date']) ? date("d-m-Y", strtotime($row['return_date'])) : '',
                    'updated_date'     => !empty($row['updated_at']) ? date("d-m-Y", strtotime($row['updated_at'])) : '',
                    'biller_name'      => 'System Approved',
                    'payment_method'   => 'Refund',
                    'status'           => $row['status'] ?? '',
                    'reason'           => $row['return_reason'] ?? '',
                    'series_number'    => '',
                    'company_state'    => $row['state_name'] ?? '', 
                    'company_gstin'    => $row['center_gst'] ?? '',
                    'party_state'      => $row['state_name'] ?? '',
                    'place_of_supply'  => $row['state_name'] ?? '',
                    'items'            => []
                ];
            }

            $ret_grouped[$retId]['items'][] = [
                'item_name'       => $row['medicine_name'] ?? 'Unknown',
                'code'            => $row['hsn_code'] ?? '',
                'batch_no'        => $row['batch_number'] ?? '',
                'expiry'          => $row['expiry_date'] ?? '',
                'quantity'        => $qty,
                'unit_price'      => number_format($mrpUnit, 2, '.', ''),
                'discount_amt'    => number_format($discountAmount, 2, '.', ''),
                'taxable_value'   => number_format($taxableValue, 2, '.', ''),
                'gst_rate'        => number_format($gstRate, 2, '.', ''),
                'gst_amount'      => number_format($gstAmount, 2, '.', ''),
                'receive_amount'  => number_format($totalRefundItem, 2, '.', '')
            ];
        }
    }

// Merge Returns into Master Array
foreach($ret_grouped as $return) {
    $all_transactions[] = $return;
}

    // =========================================================================
    // PART 2: PROCEDURE SALES
    // =========================================================================
    $procedure_raw = $this->accounts_model->get_all_sales_for_tally(); 
//$all_transactions = [];

if (!empty($procedure_raw)) {
    foreach ($procedure_raw as $sale) {
        // Fetch related data
        $pt      = $this->db->query("SELECT wife_name, husband_name FROM hms_patients WHERE patient_id = ?", [$sale["patient_id"]])->row_array();
        $origin  = $this->db->query("SELECT center_name FROM hms_centers WHERE center_number = ?", [$sale["origins"]])->row_array();
        $billing = $this->db->query("SELECT center_name FROM hms_centers WHERE center_number = ?", [$sale["billing_at"]])->row_array();
		$billing_from = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_from"]])->row_array();
        $biller  = $this->db->query("SELECT name FROM hms_employees WHERE employee_number = ?", [$sale["biller_id"]])->row_array();

        $formatted_proc = [
            'type'             => 'Procedure',
            'patient_id'       => $sale["patient_id"],
            'patient_name'     => ($pt['wife_name'] ?? '') . ' W/O ' . ($pt['husband_name'] ?? ''),
            'billing_center'   => $billing['center_name'] ?? 'N/A',
            'origin_center'    => $origin['center_name'] ?? 'N/A',
			'center_code'      =>  $billing['center_code'],
            'receipt_number'   => $sale["receipt_number"],
            'on_date'          => date("d-m-Y", strtotime($sale["on_date"])),
			'updated_date'          => date("d-m-Y", strtotime($sale["modified_on"])),
            'biller_name'      => $biller['name'] ?? 'N/A',
            'payment_method'   => $sale["payment_method"] ?? "",
            'status'           => $sale["status"] ?? "",
			'series_number'    => $sale['series_number'],
			'company_state'    => $billing_from['state_name'] ?? '', 
        	'company_gstin'    => $billing_from['center_gst'] ?? '', // [FIX] यहाँ gst_number की जगह center_gst कर दिया है
        	'party_state'      => $billing_from['state_name'] ?? '',
        	'place_of_supply'  => $billing_from['state_name'] ?? '',
            'items'            => []
        ];

        if (!empty($sale['data'])) {
            $unserialized = @unserialize($sale['data']);
            
            if (isset($unserialized['patient_procedures']) && is_array($unserialized['patient_procedures'])) {
                foreach($unserialized['patient_procedures'] as $p) {
                    
                // Get broad procedure (default ICSI if empty)
$broad_procedure = $sale["broad_procedure"] ?? "ICSI";

/* Fetch embryo procedure date based on procedure type */
if ($broad_procedure === "ICSI") {

    $embryo = $this->db->query("SELECT date_of_procedure FROM ovum_discharge_summary WHERE iic_id = ? AND ICSI = 'Yes' LIMIT 1", [$sale["patient_id"]] )->row_array();

} elseif ($broad_procedure === "IVF") {

    $embryo = $this->db->query("SELECT date_of_procedure FROM ovum_discharge_summary WHERE iic_id = ? AND IVF = 'Yes' LIMIT 1", [$sale["patient_id"]])->row_array();

} elseif ($broad_procedure === "Blastocyst") {$embryo = $this->db->query("SELECT date_of_procedure FROM ovum_discharge_summary WHERE iic_id = ? AND Micro_Fluidics = 'Yes' LIMIT 1", [$sale["patient_id"]])->row_array();

} elseif ($broad_procedure === "Frozen Embryo Transfer (FET)") {$embryo = $this->db->query("SELECT date_of_procedure FROM embryology_discharge_summary WHERE iic_id = ? AND FET = 'Yes' LIMIT 1", [$sale["patient_id"]])->row_array();

} elseif ($broad_procedure === "Diagnostic Procedures") {$embryo = $this->db->query("SELECT on_date FROM hms_patient_procedure WHERE patient_id = ? AND broad_procedure = 'Diagnostic Procedures' LIMIT 1", [$sale["patient_id"]])->row_array();

} else {
    $embryo = null;
}

/* Safely format completion date */
if (!empty($embryo['date_of_procedure']) &&
    $embryo['date_of_procedure'] !== '0000-00-00'
) {
    $completion_date = date("d-m-Y", strtotime($embryo['date_of_procedure']));
} else {
    $completion_date = date("d-m-Y", strtotime($sale["on_date"]));
}

                    $formatted_proc['items'][] = [
                        'item_name'       => ($sale["procedure_name"] ?? $broad_procedure) . ' - ' . ($p["sub_procedure"] ?? ''),
                        'code'            => $p["sub_procedures_code"] ?? '',
                        'batch_no'        => '',
                        'expiry'          => '',
                        'completion_date' => $completion_date, // Assigned completion date here
                        'quantity'        => 1,
                        'unit_price'      => number_format((float)($p["sub_procedures_price"] ?? 0), 2, '.', ''),
                        'discount_amt'    => number_format((float)($p["sub_procedures_discount"] ?? 0), 2, '.', ''),
                        'taxable_value'   => '',
                        'gst_rate'        => 0,
                        'gst_amount'      => 0,
                        'receive_amount'  => number_format((float)($p["sub_procedures_paid_price"] ?? 0), 2, '.', '')
                    ];
                }
            }
        }
        $all_transactions[] = $formatted_proc;
    }
}

    // =========================================================================
    // PART 3: CONSULTATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12:00 AM के बाद का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $this->db->select('hms_consultation.*, hms_patients.wife_name, hms_patients.husband_name, 
        bill_center.center_name as billing_center_name, bill_center.state_name as center_state_name, 
        bill_center.center_code as center_code, bill_center.center_gst as center_gst_number, 
        origin_center.center_name as origin_center_name,
        hms_employees.name as biller_name');
    $this->db->from('hms_consultation');

    // Joins
    $this->db->join('hms_patients', 'hms_patients.patient_id = hms_consultation.patient_id', 'left');
    $this->db->join('hms_centers as bill_center', 'bill_center.center_number = hms_consultation.billing_at', 'left');
    $this->db->join('hms_centers as origin_center', 'origin_center.center_number = hms_consultation.origins', 'left');
    $this->db->join('hms_employees', 'hms_employees.employee_number = hms_consultation.biller_id', 'left');

    // Filters
    $this->db->where_in('hms_consultation.status', ['approved', 'adjust', 'Approved', 'Adjust']); 
    $this->db->where('hms_consultation.tally_status', '1');
    
    // रात 12 बजे के बाद पिछला डेटा हटाने के लिए (Dynamic Midnight Reset)
    $this->db->where('hms_consultation.tally_send_date >=', $today_start);

    // Ordering & Limit
    $this->db->order_by('hms_consultation.id', 'DESC'); 
    $this->db->limit(700);

    $consult_rows = $this->db->get()->result_array();

    foreach ($consult_rows as $row) {
        $all_transactions[] = [
            'type'             => 'Consultation',
            'patient_id'       => $row['patient_id'] ?? '',
            'patient_name'     => ($row['wife_name'] ?? '') . ' W/O ' . ($row['husband_name'] ?? ''),
            'billing_center'   => $row['billing_center_name'] ?? 'N/A',
            'origin_center'    => $row['origin_center_name'] ?? 'N/A',
            'center_code'      => $row['center_code'] ?? '',
            'receipt_number'   => $row["receipt_number"] ?? '',
            'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
            // फिक्स: $sale की जगह $row का इस्तेमाल और Safe Check
            'updated_date'     => !empty($row["modified_on"]) ? date("d-m-Y", strtotime($row["modified_on"])) : '',
            'biller_name'      => $row['biller_name'] ?? 'N/A',
            'payment_method'   => $row['payment_method'] ?? '',
            'status'           => $row['status'] ?? '',
            'series_number'    => $row['series_number'] ?? '',
            'company_state'    => $row['center_state_name'] ?? '',
            'company_gstin'    => $row['center_gst_number'] ?? '',
            'party_state'      => $row['center_state_name'] ?? '',
            'place_of_supply'  => $row['center_state_name'] ?? '',
            'items'            => [[
                'item_name'       => 'Consultation Charges',
                'code'            => 'CONS',
                'batch_no'        => '',
                'expiry'          => '',
                'quantity'        => 1,
                'unit_price'      => number_format((float)($row['totalpackage'] ?? 0), 2, '.', ''),
                'discount_amt'    => number_format((float)($row['discount_amount'] ?? 0), 2, '.', ''),
                'taxable_value'   => '', 
                'gst_rate'        => 0,
                'gst_amount'      => 0,
                'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
            ]]
        ];
    }

    // =========================================================================
    // PART 4: REGISTRATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12:00 AM के बाद का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $this->db->select('hms_registation.*, hms_patients.wife_name, hms_patients.husband_name, 
        bill_center.center_name as billing_center_name, bill_center.state_name as center_state_name,
        bill_center.center_code as center_code, 
        bill_center.center_gst as center_gst_number, origin_center.center_name as origin_center_name,
        hms_employees.name as biller_name');
    $this->db->from('hms_registation');
    $this->db->join('hms_patients', 'hms_patients.patient_id = hms_registation.patient_id', 'left');
    $this->db->join('hms_centers as bill_center', 'bill_center.center_number = hms_registation.billing_at', 'left');
    $this->db->join('hms_centers as origin_center', 'origin_center.center_number = hms_registation.origins', 'left');
    $this->db->join('hms_employees', 'hms_employees.employee_number = hms_registation.biller_id', 'left');
    
    $this->db->where_in('hms_registation.status', ['approved', 'adjust', 'Approved', 'Adjust']);
    $this->db->where('hms_registation.tally_status', '1');
    
    // रात 12 बजे के बाद पिछला डेटा हटाने के लिए (Dynamic Midnight Reset)
    $this->db->where('hms_registation.tally_send_date >=', $today_start);
    
    $this->db->order_by('hms_registation.id', 'DESC');
    $this->db->limit(400);
    
    $reg_rows = $this->db->get()->result_array();

    foreach ($reg_rows as $row) {
        $all_transactions[] = [
            'type'             => 'Registration',
            'patient_id'       => $row['patient_id'] ?? '',
            'patient_name'     => ($row['wife_name'] ?? '') . ' W/O ' . ($row['husband_name'] ?? ''),
            'billing_center'   => $row['billing_center_name'] ?? 'N/A',
            'origin_center'    => $row['origin_center_name'] ?? 'N/A',
            'center_code'      => $row['center_code'] ?? '',
            'receipt_number'   => $row["receipt_number"] ?? '',
            'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
            // बग फिक्स: $sale की जगह सही $row वेरिएबल का इस्तेमाल
            'updated_date'     => !empty($row["modified_on"]) ? date("d-m-Y", strtotime($row["modified_on"])) : '',
            'biller_name'      => $row['biller_name'] ?? 'N/A',
            'payment_method'   => $row['payment_method'] ?? '',
            'status'           => $row['status'] ?? '',
            'series_number'    => $row['series_number'] ?? '',
            'company_state'    => $row['center_state_name'] ?? '',
            'company_gstin'    => $row['center_gst_number'] ?? '',
            'party_state'      => $row['center_state_name'] ?? '',
            'place_of_supply'  => $row['center_state_name'] ?? '',
            'items'            => [[
                'item_name'       => 'Registration Fees',
                'code'            => 'REG',
                'batch_no'        => '',
                'expiry'          => '',
                'quantity'        => 1,
                'unit_price'      => number_format((float)($row['totalpackage'] ?? 0), 2, '.', ''),
                'discount_amt'    => number_format((float)($row['discount_amount'] ?? 0), 2, '.', ''),
                'taxable_value'   => '',
                'gst_rate'        => 0,
                'gst_amount'      => 0,
                'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
            ]]
        ];
    }

    // =========================================================================
    // PART 5: INVESTIGATION SALES
    // =========================================================================
    
    // आज की तारीख (रात 12 बजे के बाद से अब तक का डेटा)
    $today_start = date('Y-m-d 00:00:00');

    $invest_rows = $this->db->query("
        SELECT * FROM hms_patient_investigations 
        WHERE `status`='approved' 
          AND `tally_status`='1' 
          AND `tally_send_date` >= ? 
        ORDER BY id DESC 
        LIMIT 500", [$today_start])->result_array();

    foreach ($invest_rows as $sale) {
        $pt = $this->db->query("SELECT * FROM hms_patients WHERE patient_id = ?", [$sale["patient_id"]])->row_array();
        $bill_c = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["billing_at"]])->row_array();
        $org_c = $this->db->query("SELECT * FROM hms_centers WHERE center_number = ?", [$sale["origins"]])->row_array();
        $biller = $this->db->query("SELECT * FROM hms_employees WHERE employee_number = ?", [$sale["biller_id"]])->row_array();

        // Total Amount Round-off Calculation
        $rawTotalAmount = (float) ($sale['payment_done'] ?? 0);
        $roundedTotal   = round($rawTotalAmount);
        $totalRoundOff  = $roundedTotal - $rawTotalAmount;

        $formatted_inv = [
            'type'             => 'Investigation',
            'patient_id'       => $sale["patient_id"],
            'patient_name'     => ($pt['wife_name'] ?? '') . ' W/O ' . ($pt['husband_name'] ?? ''),
            'billing_center'   => $bill_c['center_name'] ?? 'N/A',
            'origin_center'    => $org_c['center_name'] ?? 'N/A',
            'cost_center'      => $bill_c['center_name'] ?? 'N/A',
            'center_code'      => $bill_c['center_code'] ?? '',
            'receipt_number'   => $sale["receipt_number"] ?? '',
            'on_date'          => !empty($sale["on_date"]) ? date("d-m-Y", strtotime($sale["on_date"])) : '',
            'biller_name'      => $biller['name'] ?? 'N/A',
            'payment_method'   => $sale["payment_method"] ?? "",
            'status'           => $sale["status"] ?? "",
            'series_number'    => $sale['series_number'] ?? '',
            'company_state'    => $bill_c['state_name'] ?? '',
            'company_gstin'    => $bill_c['center_gst'] ?? '',
            'party_state'      => $bill_c['state_name'] ?? '',
            'place_of_supply'  => $bill_c['state_name'] ?? '',
            'total_amount'     => number_format($roundedTotal, 2, '.', ''),
            'items'            => []
        ];

        if (!empty($sale['investigations'])) {
            $unserialized = @unserialize($sale['investigations']);
            if ($unserialized && is_array($unserialized)) {
                $groups = [
                    'female_investigation' => ['name' => 'female_investigation_name', 'code' => 'female_investigation_code', 'price'=> 'female_investigation_price', 'disc' => 'female_investigation_discount'],
                    'male_investigation'   => ['name' => 'male_investigation_name', 'code' => 'male_investigation_code', 'price'=> 'male_investigation_price', 'disc' => 'male_investigation_discount']
                ];
                foreach ($groups as $group_key => $keys) {
                    if (!empty($unserialized[$group_key]) && is_array($unserialized[$group_key])) {
                        foreach ($unserialized[$group_key] as $item) {
                            if (!is_array($item)) continue;
                            
                            $price        = isset($item[$keys['price']]) ? (float)$item[$keys['price']] : 0;
                            $percent      = isset($item[$keys['disc']])  ? (float)$item[$keys['disc']]  : 0;
                            $discount_amt = ($price * $percent) / 100;
                            $exactReceive = $price - $discount_amt;

                            // Item Receive Amount Round-off Calculation
                            $roundedReceive = round($exactReceive);
                            $itemRoundOff   = $roundedReceive - $exactReceive;

                            $inv_q = $this->db->query("
                                SELECT hms_master_investigations.investigation_name, hms_master_investigations.code 
                                FROM hms_investigation 
                                JOIN hms_master_investigations ON hms_investigation.master_id = hms_master_investigations.ID 
                                WHERE hms_investigation.ID = ?", [(int)$item[$keys['name']]])->row_array();

                            $formatted_inv['items'][] = [
                                'item_name'     => $inv_q['investigation_name'] ?? 'Unknown',
                                'code'          => $inv_q['code'] ?? '',
                                'batch_no'      => '',
                                'expiry'        => '',
                                'quantity'      => 1,
                                'unit_price'    => number_format($price, 2, '.', ''),
                                'discount_amt'  => number_format($discount_amt, 2, '.', ''),
                                'taxable_value' => '',
                                'gst_rate'      => 0,
                                'gst_amount'    => 0,
                                'receive_amount'=> number_format($roundedReceive, 2, '.', '')
                            ];
                        }
                    }
                }
            }
        }
        $all_transactions[] = $formatted_inv;
    }

    // =========================================================================
    // PART 6: FELLOWSHIP / TRAINING SALES
    // =========================================================================
    
    // आज की तारीख (रात 12:00 AM के बाद का टाइमस्टैम्प)
    $today_start = date('Y-m-d 00:00:00');

    $fellow_rows = $this->db->query("
        SELECT * FROM hms_fellowship_training 
        WHERE `status` IN ('1', '3') 
          AND `tally_status` = '1' 
          AND `tally_send_date` >= '{$today_start}' 
        ORDER BY id DESC 
        LIMIT 700
    ")->result_array();

    if (!empty($fellow_rows)) {
        foreach ($fellow_rows as $row) {
            $status_text = ($row['status'] == '1') ? 'approved' : 'cancel';
            $price       = $row['price'] ?? 0;
            $discount    = $row['discount_amount'] ?? 0;
            
            // Safe updated date check
            $updated_date = '';
            if (!empty($row['modified_on'])) {
                $updated_date = date("d-m-Y", strtotime($row['modified_on']));
            } elseif (!empty($row['updated_at'])) {
                $updated_date = date("d-m-Y", strtotime($row['updated_at']));
            }

            $all_transactions[] = [
                'type'             => 'Fellowship',
                'patient_id'       => $row['studentid'] ?? '',
                'patient_name'     => trim(($row['name'] ?? '') . ' S/O ' . ($row['fname'] ?? '')),
                'billing_center'   => $row['place_of_supply'] ?? 'N/A',
                'origin_center'    => 'N/A',
                'center_code'      => 'N/A',  
                'receipt_number'   => $row['receipt'] ?? '',
                'on_date'          => !empty($row["on_date"]) ? date("d-m-Y", strtotime($row["on_date"])) : '',
                'updated_date'     => $updated_date,
                'biller_name'      => 'N/A', 
                'payment_method'   => $row['payment_method'] ?? '',
                'status'           => $status_text,
                'series_number'    => $row['series_number'] ?? '',
                'items'            => [[
                    'item_name'       => 'Fellowship: ' . ($row['course'] ?? ''),
                    'code'            => $row['code'] ?? '',
                    'batch_no'        => '',
                    'expiry'          => '',
                    'quantity'        => 1,
                    'unit_price'      => number_format((float)$price, 2, '.', ''),
                    'discount_amt'    => number_format((float)$discount, 2, '.', ''),
                    'taxable_value'   => '',
                    'gst_rate'        => number_format((float)($row['gst'] ?? 0), 2, '.', ''),
                    'gst_amount'      => number_format((float)($row['gst_amount'] ?? 0), 2, '.', ''),
                    'receive_amount'  => number_format((float)($row['payment_done'] ?? 0), 2, '.', '')
                ]]
            ];
        }
    }

    // =========================================================================
    // FINAL OUTPUT: CORRECTION IS HERE
    // =========================================================================
    // Wrap the array in a named key (e.g., 'transactions') so Tally accepts it.
    $output_data = [
        "Sales_receipt" => $all_transactions
    ];

    header('Content-Type: application/json');
    echo json_encode($output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}


public function front_approve($request = NULL){
			$data = array();
			$type = $_GET['t'];
			$status = $_GET['u'];
			$reason = '';
			if($status == 'disapproved'){
				$reason = $_GET['r'];
			}
			if($type == 'consultation'){			
			    $billing_records  = india_ivf_billing($request, "consultation"); 
			    if(!empty($billing_records)){
    			    $patient_id = $billing_records['patient_id'];
    			    $receipt_number = $billing_records['receipt_number'];
			    }
				$data = $this->accounts_model->approve_billing($request, $type, $status, $reason);
			}else if($type == 'investigation'){
                $billing_records  = india_ivf_billing($request, "patient_investigations"); 
			    if(!empty($billing_records)){
    			    $patient_id = $billing_records['patient_id'];
    			    $receipt_number = $billing_records['receipt_number'];
			    }
				$data = $this->accounts_model->approve_billing($request, $type, $status, $reason);
			}else if($type == 'procedure'){
                $billing_records  = india_ivf_billing($request, "patient_procedure"); 
			    if(!empty($billing_records)){
    			    $patient_id = $billing_records['patient_id'];
    			    $receipt_number = $billing_records['receipt_number'];
			    }
				$data = $this->accounts_model->approve_billing($request, $type, $status, $reason);
			}else{
				header("location:" .base_url(). "discount-approval?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));
				die();
			}
			header("location:" .base_url(). "discount-approval?m=".base64_encode('Billing '.$status.' successfully').'&t='.base64_encode('success'));
			die();
	}

	function ajax_center_patient_ledger(){
		$center = $_SESSION['logged_accountant']['center'];
		$start = $_POST['start'];
		$end = $_POST['end'];
		$data = $this->accounts_model->ajax_center_patient_ledger_data($start, $end, $center);
		echo json_encode($data);
		die;
	}	

	function download_ledger(){
		$center = $_SESSION['logged_accountant']['center'];
		$start = isset($_GET['start'])?$_GET['start']:"";
		$end = isset($_GET['end'])?$_GET['end']:"";

		$data = $this->accounts_model->download_center_patient_ledger_data($start, $end, $center);

		//ob_clean();		
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=patient-ledger-'.$start.'-'.$end.'.csv');
		$fp = fopen('php://output','w');
		$headers = 'IIC ID, Name of Patient, Discounted Package, Received Amount,Remaining amount, IIC Share, Centre Share, Amount Recevied at IIC, Amount Received at Centre';
		//Add the headers
		fwrite($fp, $headers. "\r\n");
		foreach ($data as $key => $val) {
			fputcsv($fp, $val);
		}    
		//close file
		fclose($fp);
		exit();
	}

	public function accepted(){

		$logg = checklogin();

		if($logg['status'] == true){		

			$data = array();

			$template = get_header_template($logg['role']);

			$data = $this->accounts_model->accept_billing();

			$this->load->view($template['header']);

			$this->load->view('accounts/accepted', $data);

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	

	public function center_accepted(){

		$logg = checklogin();

		if($logg['status'] == true){		

			$data = array();

			$template = get_header_template($logg['role']);

			$data = $this->accounts_model->center_accept_billing();

			$this->load->view($template['header']);

			$this->load->view('accounts/center_accepted', $data);

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	

	public function accept($receipt = NULL){

		$logg = checklogin();

		if($logg['status'] == true){

			$data = array();

			$type = $_GET['t'];

			if($type == 'consultation'){			

				$data = $this->accounts_model->update_accept_billing($receipt, $type);

			}else if($type == 'investigation'){

				$data = $this->accounts_model->update_accept_billing($receipt, $type);

			}else if($type == 'procedure'){

				$data = $this->accounts_model->update_accept_billing($receipt, $type);

			}else{

				header("location:" .base_url(). "accounts/accepted");

				die();

			}

			header("location:" .base_url(). "accounts/accepted?m=".base64_encode('Billing accepted successfully').'&t='.base64_encode('success'));

			die();

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	

	//Admin Reconcilation

	public function reconciliation(){

		$logg = checklogin();

		if($logg['status'] == true){		

			$data = array();

			$template = get_header_template($logg['role']);

			$data['data'] = $this->accounts_model->reconciliation();

			$this->load->view($template['header']);

			$this->load->view('accounts/reconciliation', $data);

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	//Center Reconcilation

	public function center_reconciliation(){

		$logg = checklogin();

		if($logg['status'] == true){		

			$data = array();

			$template = get_header_template($logg['role']);

			$data['data'] = $this->accounts_model->center_reconciliation();

			$this->load->view($template['header']);

			$this->load->view('accounts/center_reconciliation', $data);

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	//Patient Reconcilation

	public function patient_reconcile($receipt){

		$logg = checklogin();

		if($logg['status'] == true){		

			$data = array();

			if(!isset($_GET['t']) && empty($_GET['t'])){

				header("location:" .base_url(). "accounts/requests?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));

				die();

			}

			

			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'update_patient_reconcile'){

				unset($_POST['action']);

				$receipt_number =  $_POST['receipt_number'];

				$type =  $_POST['type'];

				$payment_done =  $_POST['payment_done'];				

				$patient_reconcile = $this->accounts_model->update_patient_reconcile($receipt, $type, $payment_done);	

				if($patient_reconcile > 0){

					header("location:" .base_url(). "accounts/requests?m=".base64_encode('Reconciled successfully').'&t='.base64_encode('success'));

					die();

				}else{

					header("location:" .base_url(). "accounts/requests?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));

					die();

				}				

			}

			

			$type = $_GET['t'];

			$template = get_header_template($logg['role']);

			$data['billing_data'] = $this->billings_model->get_billings_details($receipt, $type);

			$patient = $data['billing_data']['patient_id'];

			$data['patient_data'] = $this->accounts_model->patient_details($patient);

			$this->load->view($template['header']);

			$this->load->view('accounts/patient_reconcile', $data);

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	

	public function patient_ledger(){

		$logg = checklogin();

		if($logg['status'] == true){		

			$data = array();

			$template = get_header_template($logg['role']);

			$data['data'] = $this->accounts_model->patient_ledger();

			$this->load->view($template['header']);

			$this->load->view('accounts/patient_ledger', $data);

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	

	public function ajax_patient_ledger(){ 

		if($_POST['type'] == 'month_wise'){

			$month = $_POST['month'];

			$data = $this->accounts_model->ajax_patient_month_ledger($month);

		}

		if($_POST['type'] == 'custom_wise'){

			$start = $_POST['start'];

			$end = $_POST['end'];

			$data = $this->accounts_model->ajax_patient_custom_ledger($start, $end);

		}

		echo json_encode($data);

		die;

	}

	

	public function patient_details($patient_id){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$data['data'] = $this->accounts_model->patient_details($patient_id);
			$data['form_data'] = $this->accounts_model->get_discharge_form_data();
			$data['form_data_embrology'] = $this->accounts_model->get_discharge_form_data_embrology();
			$data['formdata'] = $this->accounts_model->get_discharge_data();
			$data['investigation_reports'] = $this->accounts_model->investigation_reports($patient_id);
			$data['procedure_reports'] = $this->accounts_model->procedure_reports($patient_id);
			$data['patient_discharge'] = $this->accounts_model->patient_discharge($patient_id);
			$this->load->view($template['header']);
			$this->load->view('accounts/patient_details', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function patient_update(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/patient_update', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function updatefreezing(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/updatefreezing', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function addfreezing(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/addfreezing', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	/************* Mou *************/
	
	public function mou(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/mou', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function add_mou(){
		if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_mou'){

		unset($_POST['action']);
		$_POST['on_date'] = date("Y-m-d H:i:s");
		//$_POST['status'] = '1';
		$transaction_img = '';
		if(!empty($_FILES['transaction_img']['tmp_name'])){
			$dest_path = $this->config->item('upload_path');
			$destination = $dest_path.'patient_files/';
			$NewImageName = rand(4,10000)."-". $_FILES['transaction_img']['name'];
			$transaction_img = base_url().'assets/patient_files/'.$NewImageName;
			move_uploaded_file($_FILES['transaction_img']['tmp_name'], $destination.$NewImageName);
			$_POST['transaction_img'] = $transaction_img;
		}

		//$settle = $this->accounts_model->insert_add_mou($_POST);
		 echo "<br><pre>";
            print_r($this->accounts_model->insert_add_mou($_POST)); 
            echo "</pre>";
		//var_dump($settle = $this->accounts_model->insert_add_mou($_POST));die;
		if($settle > 0){
			header("location:" .base_url(). "accounts/mou?m=".base64_encode('Settled successfully!').'&t='.base64_encode('success'));
			die();
		}else{
				header("location:" .base_url(). "accounts/mou?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));
				die();
			}
	}
	}
	
	public function update_mou(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			if(isset($_GET['ID'])){ $ID = $_GET['ID']; }
			if(isset($_POST['ID'])) { $ID = $_POST['ID']; }
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'update_mou_item'){
				unset($_POST['action']);
				
				$transaction_img = '';
		if(!empty($_FILES['transaction_img']['tmp_name'])){
			$dest_path = $this->config->item('upload_path');
			$destination = $dest_path.'patient_files/';
			$NewImageName = rand(4,10000)."-". $_FILES['transaction_img']['name'];
			$transaction_img = base_url().'assets/patient_files/'.$NewImageName;
			move_uploaded_file($_FILES['transaction_img']['tmp_name'], $destination.$NewImageName);
			$_POST['transaction_img'] = $transaction_img;
		}
				
				$data = $this->accounts_model->update_mou($_POST,$ID,$transaction_img);
				
				if($data > 0){
					header("location:" .base_url(). "accounts/update_mou?m=".base64_encode('Item updated successfully !').'&t='.base64_encode('success').'&ID='.$ID);
					die();
				}else{
					header("location:" .base_url(). "accounts/update_mou?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error').'&ID='.$ID);
					die();
				}				
			}
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/update_mou', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

public function moulist(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$status = $this->input->get('status', true);
			$party_name = $this->input->get('party_name', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/moulist";
        	$config["total_rows"] = $this->accounts_model->mou_count($status, $party_name, $start_date, $end_date);
        	$config["per_page"] = 40;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->mou_patination($config["per_page"], $per_page, $status, $party_name, $start_date, $end_date);
			$data["status"] = $status;
			$data["party_name"] = $party_name;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/moulist', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	/***********End Mou **********/

	public function center_patient_ledger(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$data['data'] = $this->accounts_model->center_patient_ledger();
			$this->load->view($template['header']);
			$this->load->view('accounts/center_patient_ledger', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function patient_payments($patient_id){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$data['data'] = $this->accounts_model->patient_payments($patient_id);
			$this->load->view($template['header']);
			$this->load->view('accounts/patient_payments', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function partial_payments(){

		$logg = checklogin();

		if($logg['status'] == true){		

			$data = array();

			$template = get_header_template($logg['role']);

			$data['data'] = $this->accounts_model->partial_payments();

			$this->load->view($template['header']);

			$this->load->view('accounts/partial_payments', $data);

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}

	

	function approve_payment($id){

		$payment = $this->accounts_model->approve_payment($id);
		// Kisne approve kiya + kab (Account Ledger popup me "Approved by")
		// (model ka return aakhri query ke affected rows hota hai - isliye status se check karo)
		$pp_chk = $this->db->select('status')->where('ID', (int)$id)->get('hms_patient_payments')->row_array();
		if (!empty($pp_chk) && (string)$pp_chk['status'] === '1') { $this->record_payment_approver($id); }
		if($payment > 0){

			header("location:" .base_url(). "accounts/partial_payments?m=".base64_encode('Payment approved').'&t='.base64_encode('success'));

			die();

		}else{

			header("location:" .base_url(). "accounts/partial_payments?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));

			die();

		}

	}

	

	function disapprove_payment($id){
    // लॉग इन यूजर की डिटेल्स निकालें (लॉग्स में एंट्री के लिए)
    $logg = checklogin(); 
    
    $disapprove_reason = isset($_GET['t']) ? $_GET['t'] : "";
    
    // --- STEP 1: Fetch Payment Details BEFORE Disapproving ---
    // (ध्यान दें: आपको अपने मॉडल में पेमेंट की डिटेल्स निकालने वाले फंक्शन का सही नाम इस्तेमाल करना होगा)
    // उदाहरण के लिए मैंने get_payment_details माना है, आप इसे अपने डेटाबेस टेबल के हिसाब से बदल सकते हैं
    $payment_details = $this->db->get_where('hms_patient_payments', ['id' => $id])->row(); 
    // ^ कृपया 'patient_payments_table' को अपनी असली टेबल के नाम से रिप्लेस करें

    // अब पेमेंट डिसअप्रूव करें
    $payment = $this->accounts_model->disapprove_payment($id, $disapprove_reason);
    
    if($payment > 0){
        
        // --- WALLET REFUND LOGIC START (Package Wallet - W2) ---
        if(!empty($payment_details)) {
            $payment_method = strtolower(trim($payment_details->payment_method));
            $patient_id = $payment_details->patient_id;
            
            // अमाउंट कॉलम का नाम अपने डेटाबेस के हिसाब से रखें (जैसे payment_done, amount, etc.)
            $refund_amount = (float)$payment_details->payment_done; 
            
            // चेक करें कि पेमेंट वॉलेट से हुआ था या नहीं
            if((strpos($payment_method, 'wallet') !== false) && $refund_amount > 0 && $patient_id > 0) {
                
                // A. Fetch current wallet balances
                $wallet = $this->db->get_where('hms_patient_wallets', ['patient_id' => $patient_id])->row();
                
                if($wallet) {
                    // B. Refund mathematical mapping (Adding back to Wallet 2)
                    $opening_w1 = (float)$wallet->wallet_1_balance;
                    $closing_w1 = $opening_w1; // Wallet 1 untouched
                    
                    $opening_w2 = (float)$wallet->wallet_2_balance;
                    $closing_w2 = $opening_w2 + $refund_amount; // Amount Added Back (+)
                    
                    // C. Update Wallet 2 Balance directly
                    $this->db->query("UPDATE `hms_patient_wallets` SET `wallet_2_balance` = " . (float)$closing_w2 . ", `updated_at` = NOW() WHERE `patient_id` = " . $this->db->escape($patient_id));
                    
                    $created_by_user = isset($logg['employee_number']) ? $logg['employee_number'] : 'system';
                    
                    // D. Insert Refund Log into hms_wallet_logs
                    $log_data = [
                        'patient_id'     => $patient_id,
                        'amount'         => $refund_amount,
                        'action_type'    => 'REFUND', // पैसे वापस आ रहे हैं
                        'opening_w1'     => $opening_w1,
                        'closing_w1'     => $closing_w1,
                        'opening_w2'     => $opening_w2,
                        'closing_w2'     => $closing_w2,
                        'reference_id'   => $id, 
                        'payment_method' => 'Wallet', 
                        'remarks'        => 'Refund for Disapproved Payment. Reason: ' . $disapprove_reason,
                        'created_by'     => $created_by_user,
                        'created_at'     => date('Y-m-d H:i:s'),
                        'status'         => 'success'
                    ];
                    $this->db->insert('hms_wallet_logs', $log_data);
                }
            }
        }
        // --- WALLET REFUND LOGIC END ---

        header("location:" .base_url(). "accounts/partial_payments?m=".base64_encode('Payment disapproved and refunded to wallet').'&t='.base64_encode('success'));
        die();
    }else{
        header("location:" .base_url(). "accounts/partial_payments?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
        die();
    }
}
	
	function cancle_payment($id){
		$disapprove_reason = isset($_GET['t'])?$_GET['t']:"";
		$payment = $this->accounts_model->cancle_payment($id, $disapprove_reason);
		if($payment > 0){
			header("location:" .base_url(). "accounts/partial_payments?m=".base64_encode('Payment disapproved').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/partial_payments?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}
	

	function get_current_balance($patient_id){
		$current_balance = $this->accounts_model->get_current_balance($patient_id);
		return $current_balance;
	}

	

	function get_payment_at($patient_id){

		$payment_at = $this->accounts_model->get_payment_at($patient_id);

		return $payment_at;

	}

		

	function update_billing(){

		$billing = $_POST['billing'];

		$type = $_POST['type'];

		$data = $this->accounts_model->update_billing($billing, $type);

		echo json_encode($data);

		die;

	}

	

	function settle($patient_id){

		$logg = checklogin();

		if($logg['status'] == true){	

			$patient = $this->accounts_model->check_patient($patient_id);

			if(count($patient) > 0){

				$data = array();

				$template = get_header_template($logg['role']);

				$data['patient_id'] = $patient_id;

				$this->load->view($template['header']);

				$this->load->view('accounts/settle', $data);

				$this->load->view($template['footer']);

			}else{

				header("location:" .base_url(). "accounts/center_reconciliation?m=".base64_encode('Patient not found!').'&t='.base64_encode('error'));

				die();

			}

		}else{

				header("location:" .base_url(). "");

				die();

		}	

	}

	

	function add_settle(){

		if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_settle'){

			unset($_POST['action']);

			$_POST['on_date'] = date("Y-m-d H:i:s");

			$_POST['status'] = '1';

			$transaction_img = '';

			if(!empty($_FILES['transaction_img']['tmp_name'])){

				$dest_path = $this->config->item('upload_path');

				$destination = $dest_path.'patient_files/';

				$NewImageName = rand(4,10000)."-".$_POST['patient_id']."-". $_FILES['transaction_img']['name'];

				$transaction_img = base_url().'assets/patient_files/'.$NewImageName;

				move_uploaded_file($_FILES['transaction_img']['tmp_name'], $destination.$NewImageName);

				$_POST['transaction_img'] = $transaction_img;

			}

			//var_dump($_POST);die;

			$settle = $this->accounts_model->insert_settle($_POST);

			if($settle > 0){

				header("location:" .base_url(). "accounts/center_reconciliation?m=".base64_encode('Settled successfully!').'&t='.base64_encode('success'));

				die();

			}else{

				header("location:" .base_url(). "accounts/center_reconciliation?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));

				die();

			}

		}else{

			header("location:" .base_url(). "accounts/center_reconciliation?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));

			die();

		}

	}

	

	function details($receipt = null){
		$logg = checklogin();
		if($logg['status'] == true){
			if(empty($receipt)){
				header("location:" .base_url(). "dashboard?m=".base64_encode('Receipt number is required!').'&t='.base64_encode('error'));
				die();
			}
			$data = array();
			$type = $_GET['t'];
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			if($type == 'consultation'){			
				$data['data'] = $this->accounts_model->get_details($receipt, $type);
				$this->load->view('accounts/consultation_details', $data);
			}else if($type == 'registation'){
				$data['data'] = $this->accounts_model->get_details($receipt, $type);
				$this->load->view('accounts/registation_details', $data);
			}else if($type == 'investigation'){
				$data['data'] = $this->accounts_model->get_details($receipt, $type);
				$this->load->view('accounts/investigation_details', $data);
			}else if($type == 'procedure'){
				$data['data'] = $this->accounts_model->get_details($receipt, $type);
				$this->load->view('accounts/procedure_details', $data);
			}else if($type == 'package'){
				$data['data'] = $this->accounts_model->get_details($receipt, $type);
				$this->load->view('accounts/package_details', $data);	
			}else if($type == 'medicine'){
				$data['data'] = $this->accounts_model->get_details($receipt, $type);
				$this->load->view('accounts/medicine_details', $data);	
			}else{
				header("location:" .base_url(). "dashboard?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));
				die();
			}

			if(empty($data['data'])){

				header("location:" .base_url(). "dashboard?m=".base64_encode('Details not found!').'&t='.base64_encode('error'));

				die();

			}

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

	}



public function financial_clearance_details($receipt = null) {
    // 1. Check Login
    $logg = checklogin();
    if ($logg['status'] !== true) {
        header("location:" . base_url());
        die();
    }

    // 2. Validate Receipt Parameter
    if (empty($receipt)) {
		$msg = base64_encode('Receipt number is required!');
		
		// Corrected Line:
		header("Location: " . base_url() . "dashboard?m=" . $msg . "&t=" . base64_encode('error'));
		
		die();
	}

    // 3. Get Input (Use CodeIgniter input helper for security)
    $type = $this->input->get('t'); 

    // 4. Fetch Data
    $data_result = $this->accounts_model->get_financial_clearance_details($receipt);
	$partial_data_result = $this->accounts_model->get_financial_partial_clearance_details($receipt);

    // 5. Validate Data (Check this BEFORE loading any views)
    if (empty($data_result)) {
        $msg = base64_encode('Details not found!');
        $type = base64_encode('error');
        header("location:" . base_url() . "dashboard?m=" . $msg . "&t=" . $type);
        die();
    }

    // 6. Prepare Data for View
   $view_data['data_result'] = $data_result;
	$view_data['partial_data_result'] = $partial_data_result;

    // 7. Load Views (Only now do we output HTML)
    $template = get_header_template($logg['role']);
    
    $this->load->view($template['header']);
    
    // ** MISSING PART ADDED **: You need to load the actual page content here
    // Change 'accounts/financial_clearance_view' to your actual view file name
    $this->load->view('accounts/financial_clearance_details', $view_data); 
    
    $this->load->view($template['footer']);
}

	

	function check_settle($patient_id){

		$settle = $this->accounts_model->check_settle($patient_id);

		return $settle;

	}

	

	function procedure_name($id){

		$name = $this->accounts_model->get_procedure_name($id);

		return $name;

	}

	

	function get_doctor_name($doctor){

		$name = $this->accounts_model->get_doctor_name($doctor);

		return $name;

	}
	

	

	function get_investigation_name($investig){
		$name = $this->accounts_model->get_investigation_name($investig);
		return $name;
	}
	
	function get_master_investigation_name($investig){
		$name = $this->accounts_model->get_master_investigation_name($investig);
		return $name;
	}
	
	function get_master_investigation_code($investig){
		$name = $this->accounts_model->get_master_investigation_code($investig);
		return $name;
	}
	
	function get_procedure_name($procedure){

		$name = $this->accounts_model->get_procedure_name($procedure);

		return $name;

	}

	

	function get_item_name($item){

		$name = $this->accounts_model->get_item_name($item);

		return $name;

	}

		

	function get_center_name($center){

		$name = $this->accounts_model->get_center_name($center);

		return $name;

	}

	function get_lead($paitent_id){

		$crm_id = $this->accounts_model->get_lead($paitent_id);

		return $crm_id;

	}
/*
	function get_agent_name($paitent_id){

		$name = $this->accounts_model->get_councellor($paitent_id);

		return $name;

	}

	function get_leadsource_list($paitent_id){

		$name = $this->accounts_model->get_leadsource_list($paitent_id);

		return $name;

	}	*/

 	function get_lead_source($paitent_id){
        $name = $this->accounts_model->get_lead_source($paitent_id);
        return $name;
    }

	/*function get_counselor($appointment_id){
        $name = $this->accounts_model->get_counselor_name($appointment_id);
        return $name;
    }*/

	function get_counselor_name($appointment_id){
        $name = $this->accounts_model->get_counselor_name($appointment_id);
        return $name;
    }

/*
	function get_center_list($patient_id){

		$name = $this->accounts_model->get_center_list($patient_id);

		return $name;

	}	*/

	

	function get_patient_name($patient_id){

		$name = $this->billings_model->get_patient_name($patient_id);

		return $name;

	}

	function get_husband_name($patient_id){

		$name = $this->billings_model->get_husband_name($patient_id);

		return $name;

	}

	

	function get_patient_items($receipt, $patient_id){

		$lists = $this->stock_model->get_patient_name($receipt, $patient_id);

		return $lists;

	}

	

	public function get_discount_approval(){
		//var_dump($_POST);die;
		$employee = $_POST['accountant'];
		$employee_data = get_employee_detail($employee);
		$discount = $_POST['discount'];
		$receipt_number = $_POST['receipt_number'];
		$patient_id = $_POST['patient_id'];
		$patient_data = get_patient_detail($patient_id);
		$billing_type = $_POST['billing_type'];
		//var_dump($patient_data);die;
		$admin_email = $this->accounts_model->get_admin_email();
		$currency = '';
		$currency = 'Rs.'.$discount;
		$coupon_code = self::coupon_code();
		$date = date('Y-m-d H:i:s');

		$patitnet_detls = "";
		if(!empty($patient_data)){
			$patitnet_detls = $patient_data['wife_name']." (".$patient_data['patient_id'].")";
		}else{
			$patitnet_detls = $patient_id;
		}
		$dscnt_arr = array();
		$dscnt_arr['patient_id'] = $patient_id;
		$dscnt_arr['code'] = $coupon_code;
		$dscnt_arr['amount'] = $discount;
		$dscnt_arr['receipt_number'] = $receipt_number;
		$dscnt_arr['type'] = $billing_type;
		$dscnt_arr['date'] = $date;
		$dscnt_arr['biller'] = $employee;
		$billing_discount = $this->accounts_model->billing_discount($dscnt_arr, $patient_id, $receipt_number, $billing_type);
		$response = array();
		if($billing_discount == 1){		
			$subject = 'Billing discount approval request';
			$mail_msg = 'Hi IndiaIVF, <br/><br/> '.$employee_data['name'].'('.$employee_data['employee_number'].') asked for discount approval of '.$currency.' for patient '.$patitnet_detls.' against '.$billing_type.' billing.<br/><br/><strong>One-time coupon code</strong>: '.$coupon_code.' <br/><br/> Click here for <a href="'.base_url().'discount-request?p='.base64_encode($patient_id).'&r='.base64_encode($receipt_number).'&s='.base64_encode('1').'&t='.base64_encode($billing_type).'&f='.base64_encode('mail').'">Approve</a> or <a href="'.base_url().'discount-request?p='.base64_encode($patient_id).'&r='.base64_encode($receipt_number).'&s='.base64_encode('2').'&t='.base64_encode($billing_type).'&f='.base64_encode('mail').'">Decline</a>';
			$result = send_mail($admin_email, $subject, $mail_msg);
			$response = array('status' => 1);
		}else if($billing_discount == 2){
			$response = array('status' => 2);
		}else{
			$response = array('status' => 0);
		}
		echo json_encode($response);
		die;
	}

	function coupon_code($j = 8){

		$string = "";

		for($i=0;$i < $j;$i++){

			srand((double)microtime()*1234567);

			$x = mt_rand(0,2);

			switch($x){

				case 0:$string.= chr(mt_rand(97,122));break;

				case 1:$string.= chr(mt_rand(65,90));break;

				case 2:$string.= chr(mt_rand(48,57));break;

			}

		}

		return strtoupper($string); //to uppercase

	}

	

	function discount_request(){
		$patient = base64_decode($_GET['p']);
		$receipt = base64_decode($_GET['r']);
		$status = base64_decode($_GET['s']);
		$billing_type = base64_decode($_GET['t']);
		$from = base64_decode($_GET['f']);
		$disapprove_reason = isset($_GET['rs'])?$_GET['rs']:"";
		$disapprove_amount = isset($_GET['da'])?$_GET['da']:"";
		
		$redirect = '';
		if($from == 'dashboard'){
			$redirect = 'billings/billing_discount';
		}else{
			$redirect = 'discount-approval';
		}

		$check_discount_billing = check_discount_billing($patient, $receipt, $billing_type);
		if(empty($check_discount_billing)){
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, billing not created yet!').'&t='.base64_encode('error'));
			die();
		}
		
		$billing_discount = $this->accounts_model->discount_request($billing_type, $patient, $status, $receipt, $disapprove_reason, $disapprove_amount);
		if($billing_discount == 2){
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, billing not created yet!').'&t='.base64_encode('error'));
			die();
		}
		if($billing_discount == 3){
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, something went wrong!').'&t='.base64_encode('error'));
			die();
		}
		if($billing_discount == 1){
			$patient_data = get_patient_detail($patient);
			$currency = "";
			// if($patient_data['nationality'] == "indian"){$currency = "Rs.";}else{$currency = "USD";}
			$discount_data = $this->accounts_model->get_discount_biller($patient, $receipt);
			$text_status = ""; $coupon = ""; if($status == 1){$text_status = 'approved'; $coupon = "Your one-time discount code of amount ".$currency.$discount_data['amount']." :- <strong>".$discount_data['code']."</strong><br/><br/>";}else if($status == 2){$text_status = 'declined';}
			$approve_billing_by_receipt = $this->accounts_model->approve_billing_by_receipt($receipt, $billing_type, 'pending', 'discount '.$text_status.'');
			if(!empty($discount_data)){
				$biller_data = get_employee_detail($discount_data['biller']);
				$mail_msg = "";
				$mail_msg = "Hi ".$biller_data['name']." (".$biller_data['username'].")<br/><br/>";
				$mail_msg .= "Your request for discount against billing receipt number (".$receipt."), has been ".$text_status.".<br/><br/> ".$coupon." Thanks, <br/>IndiaIVF";
				$result = send_mail($biller_data['email'], 'Billing discount status', $mail_msg);
			}
			header("location:" .base_url(). "$redirect?m=".base64_encode('Discount '.$text_status.'!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, discount status already updated/discount request not found!').'&t='.base64_encode('error'));
			die();
		}
	}

	function discount_disapprove_request(){
		$patient = base64_decode($_GET['p']);
		$receipt = base64_decode($_GET['r']);
		$status = base64_decode($_GET['s']);
		$billing_type = base64_decode($_GET['t']);
		$from = base64_decode($_GET['f']);
		$disapprove_reason = isset($_GET['rs'])?$_GET['rs']:"";
		$disapprove_amount = isset($_GET['da'])?$_GET['da']:"";
		
		$redirect = '';
		if($from == 'dashboard'){
			$redirect = 'billings/billing_discount';
		}else{
			$redirect = 'discount-approval';
		}

		$check_discount_billing = check_discount_billing($patient, $receipt, $billing_type);
		if(empty($check_discount_billing)){
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, billing not created yet!').'&t='.base64_encode('error'));
			die();
		}
		
		$billing_discount = $this->accounts_model->discount_request($billing_type, $patient, $status, $receipt, $disapprove_reason, $disapprove_amount);
		if($billing_discount == 2){
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, billing not created yet!').'&t='.base64_encode('error'));
			die();
		}
		if($billing_discount == 3){
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, something went wrong!').'&t='.base64_encode('error'));
			die();
		}
		if($billing_discount == 1){
			$patient_data = get_patient_detail($patient);
			$currency = "";
			// if($patient_data['nationality'] == "indian"){$currency = "Rs.";}else{$currency = "USD";}
			$discount_data = $this->accounts_model->get_discount_biller($patient, $receipt);
			$text_status = ""; $coupon = ""; if($status == 1){$text_status = 'approved'; $coupon = "Your one-time discount code of amount ".$currency.$discount_data['amount']." :- <strong>".$discount_data['code']."</strong><br/><br/>";}else if($status == 2){$text_status = 'declined';}
			$approve_billing_by_receipt = $this->accounts_model->approve_billing_by_receipt($receipt, $billing_type, 'disapproved', 'discount '.$text_status.'');
			if(!empty($discount_data)){
				$biller_data = get_employee_detail($discount_data['biller']);
				$mail_msg = "";
				$mail_msg = "Hi ".$biller_data['name']." (".$biller_data['username'].")<br/><br/>";
				$mail_msg .= "Your request for discount against billing receipt number (".$receipt."), has been ".$text_status.".<br/><br/> ".$coupon." Thanks, <br/>IndiaIVF";
				$result = send_mail($biller_data['email'], 'Billing discount status', $mail_msg);
			}
			header("location:" .base_url(). "$redirect?m=".base64_encode('Discount '.$text_status.'!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "$redirect?m=".base64_encode('Oops, discount status already updated/discount request not found!').'&t='.base64_encode('error'));
			die();
		}
	}

	function check_billing_discount($patient, $receipt_number){
		$discount = $this->accounts_model->check_billing_discount($patient, $receipt_number);
		return $discount;
	}	

	public function billing_discount(){
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			$template = get_header_template($logg['role']);
			$data['data'] = $this->accounts_model->get_billing_discount();
			$this->load->view($template['header']);
			$this->load->view('accounts/billing-discount', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url()."");
			die();
		}
	}

	public function partial_payment_receipt($payment_id){
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			$template = get_header_template($logg['role']);
			$data = $this->accounts_model->get_partial_payment_receipt($payment_id);
			if(!empty($data)){
				$data['data'] = $data;
				$this->load->view($template['header']);
				$this->load->view('accounts/partial_payment_receipt', $data);
				$this->load->view($template['footer']);
			}else{
				header("location:" .base_url(). "accounts/accounts?m=".base64_encode('Oops, record not found!').'&t='.base64_encode('error'));
				die();
			}
		}else{
			header("location:" .base_url()."");
			die();
		}
	}
	

	function get_center(){
		if(isset($_SESSION['logged_accountant'])){ 
			$username = $_SESSION['logged_accountant']['username'];
			$center = $this->billings_model->get_center($username);
			return $center;
		}
	}
	
	function discount_applied($receipt){
		$check_discound_applied = $this->accounts_model->check_discound_applied($receipt);
		return $check_discound_applied;
	}
	
	function discount_applied_status($receipt){
		$check_discound_applied = $this->accounts_model->discount_applied_status($receipt);
		return $check_discound_applied;
	}
	
	function get_medicine_name($medicine){
		$name = $this->billings_model->get_medicine_name($medicine);
		return $name;
	}

	function get_brand_name($brand){
		$name = $this->billings_model->get_brand_name($brand);
		return $name;
	}
	
	function get_all_centers(){
		$all_centers = $this->center_model->get_centers();
		return $all_centers;
	}

	function get_all_procedures_category(){
		$all_procedures_category = $this->procedures_model->get_procedures_category();
		return $all_procedures_category;
	}

	function get_all_broad_procedure(){
		$all_broad_procedure = $this->procedures_model->get_broad_procedure();
		return $all_broad_procedure;
	}

	function get_all_procedures(){
		$all_procedures = $this->procedures_model->get_origin_procedures();
		return $all_procedures;
	}

	function get_all_booked_status(){
		$all_booked_status = $this->procedures_model->get_booked_status();
		return $all_booked_status;
	}

	function get_all_counselor(){
		$all_counselor = $this->accounts_model->get_counselor();
		return $all_counselor;
	}
	
	function get_all_status(){
		$all_status = $this->accounts_model->get_status();
		return $all_status;
	}

	function get_all_broad_procedure_count(){
		$all_broad_procedure_count = $this->procedures_model->get_broad_procedure_count();
		return $all_broad_procedure_count;
	}
	
	public function procedure_billings(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
	
			$config = array();
        	$config["base_url"] = base_url() . "accounts/procedure_billings";
        	$config["total_rows"] = $this->accounts_model->procedure_billings_count($center, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->procedure_billing_list_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/procedure_billings', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function investigation_billings(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
		$center = $this->input->get('billing_at', true);
		$start_date = $this->input->get('start_date', true);
		$end_date = $this->input->get('end_date', true);
		$patient_id = $this->input->get('iic_id', true);
		$status = $this->input->get('status', true);
		$payment_method = $this->input->get('payment_method', true);
		$export_billing = $this->input->get('export-billing', true);
		
		$config = array();
    	$config["base_url"] = base_url() . "accounts/investigation_billings";
    	$config["total_rows"] = $this->accounts_model->patient_investigation_count($center, $status, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['investigate_result'] = $this->accounts_model->investigation_billing_list_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/investigation_billings', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	} 
	
		public function consultation_billings(){
			$logg = checklogin();
			error_reporting(0);
			if($logg['status'] == true){

				$per_page = $this->input->get('per_page', true);
				if(empty($per_page)){
					$per_page = 0;
				}
				$center = $this->input->get('billing_at', true);
				$start_date = $this->input->get('start_date', true);
				$end_date = $this->input->get('end_date', true);
				$patient_id = $this->input->get('iic_id', true);
				$export_billing = $this->input->get('export-billing', true);
				
				$config = array();
				$config["base_url"] = base_url() . "accounts/consultation_billings";
				$config["total_rows"] = $this->accounts_model->patient_consultation_count($center,$status, $start_date, $end_date, $patient_id,$doctor_id = null);
				$config["per_page"] = 10;
				$config["uri_segment"] = 2;
				$config['use_page_numbers'] = true;
				$config['num_links'] = 5;
				$config['page_query_string'] = true;
				$config['reuse_query_string'] = true;
				$this->pagination->initialize($config);
				$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
				
				$data["links"] = $this->pagination->create_links();
				$data['consultation_result'] = $this->accounts_model->patient_consultation_report_patination($config["per_page"], $per_page, $center,$status, $start_date, $end_date, $patient_id,$type,$doctor_id=null);
				$data["billing_at"] = $center;
				$data["start_date"] = $start_date;
				$data["end_date"] = $end_date;
				$data["patient_id"] = $patient_id;
				$template = get_header_template($logg['role']);
				$this->load->view($template['header']);
				$this->load->view('accounts/consultation_billings', $data);
				$this->load->view($template['footer']);
			}else{
				header("location:" .base_url(). "");
				die();
			}
		} 
		
			/*******************Medicine Report Account Panel****************/
	
	   public function medicine_patients(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$employee_number = $this->input->get('employee_number', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('patient_id', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_allmedicie_data($employee_number, $start_date, $end_date,  $patient_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Medicine-Patients-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name, Receipt Number,Total Amount, Discount Amount, Discounted Amount, Paid Amount, Payment Method, Cash Payment,Card Payment,UPI Payment, NEFT Payment, Billing Type, Date, Status,Center Name';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					
					$sql_center = "SELECT * FROM hms_centers WHERE center_number='" . $val['billing_at'] . "'";
					$select_center = run_select_query($sql_center);
					$center_name = $select_center['center_name'];
					
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['receipt_number'], $val['fees'], $val['discount_amount'], $val['fees'] - $val['discount_amount'], $val['payment_done'], $val['payment_method'], $val['cash_payment'],$val['card_payment'],$val['upi_payment'],$val['neft_payment'], $val['billing_type'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status'],$center_name);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/medicine_patients";
        	$config["total_rows"] = $this->accounts_model->patient_investigation_count2($employee_number, $start_date, $end_date, $patient_id);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['investigate_result'] = $this->accounts_model->patient_investigation_list_patination2($config["per_page"], $per_page, $employee_number, $start_date, $end_date, $patient_id);
			
			$data["employee_number"] = $employee_number;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/medicine_patients', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function appointment_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			
			$config = array();
        	$data['appointments_result'] = $this->accounts_model->dashboard_appointment_list($center, $start_date, $end_date);
			
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/appointment_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	

	public function reports(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			//$per_page = $this->input->get('per_page', true);
			$center = $this->input->get('billing_at', true);
			$payment_method = $this->input->get('payment_method', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			
			$config = array();
        	$data['procedure_result'] = $this->accounts_model->dashboard_procedure_list_patination($center, $start_date, $end_date);
			$data['investigation_result'] = $this->accounts_model->dashboard_investigation_list_patination($center, $start_date, $end_date);
			$data['consultation_result'] = $this->accounts_model->dashboard_consultation_report_patination($center, $start_date, $end_date);
			$data['partial_payment'] = $this->accounts_model->dashboard_partial_payment($center, $start_date, $end_date);
			$data['partial_payment_investigation'] = $this->accounts_model->dashboard_partial_payment_investigation($center, $start_date, $end_date);
			$data['registration_payment'] = $this->accounts_model->dashboard_registration_payment($center, $start_date, $end_date);
			$data['medicine_payment'] = $this->accounts_model->dashboard_medicine_payment($center, $start_date, $end_date);
			$data['medicine_sale_payment'] = $this->accounts_model->dashboard_medicine_sale_payment($center, $start_date, $end_date);
			$data['medicine_return'] = $this->accounts_model->dashboard_medicine_return($center, $start_date, $end_date);
			$data['product_advisory'] = $this->accounts_model->dashboard_product_advisory($start_date, $end_date);
			$data['fellowship_training'] = $this->accounts_model->dashboard_fellowship_training($start_date, $end_date);
			
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/reports', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	
	public function update_investigation(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			if(isset($_GET['ID'])){ $ID = $_GET['ID']; }
			if(isset($_POST['ID'])) { $ID = $_POST['ID']; }
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'update_investigation_item'){
				unset($_POST['action']);
				$data = $this->accounts_model->update_investigation($_POST,$ID);
				
				if($data > 0){
					header("location:" .base_url(). "accounts/update_investigation?m=".base64_encode('Item updated successfully !').'&t='.base64_encode('success').'&ID='.$ID);
					die();
				}else{
					header("location:" .base_url(). "accounts/update_investigation?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error').'&ID='.$ID);
					die();
				}				
			}
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/update_investigation', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
		/************* Liason *************/
	
	public function liason(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
		if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_liason'){
		unset($_POST['action']);
		$_POST['on_date'] = date("Y-m-d H:i:s");
		$transaction_img = '';
		if(!empty($_FILES['transaction_img']['tmp_name'])){
			$dest_path = $this->config->item('upload_path');
			$destination = $dest_path.'patient_files/';
			$NewImageName = rand(4,10000)."-". $_FILES['transaction_img']['name'];
			$transaction_img = base_url().'assets/patient_files/'.$NewImageName;
			move_uploaded_file($_FILES['transaction_img']['tmp_name'], $destination.$NewImageName);
			$_POST['transaction_img'] = $transaction_img;
		}

		$settle = $this->accounts_model->insert_add_liason($_POST);
		if($settle > 0){
			header("location:" .base_url(). "accounts/liason?m=".base64_encode('Settled successfully!').'&t='.base64_encode('success'));
			die();
		}else{
				header("location:" .base_url(). "accounts/liason?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));
				die();
			}
	}
	$template = get_header_template($logg['role']);
	$this->load->view($template['header']);
	$this->load->view('accounts/liason', $data);
	$this->load->view($template['footer']);
	}else{
	header("location:" .base_url(). "");
	die();
		}
	}
	
	public function update_liason(){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			if(isset($_GET['ID'])){ $ID = $_GET['ID']; }
			if(isset($_POST['ID'])) { $ID = $_POST['ID']; }
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'update_liason_item'){
				unset($_POST['action']);
				$data = $this->accounts_model->update_liason($_POST,$ID);
				if($data > 0){
					header("location:" .base_url(). "accounts/update_liason?m=".base64_encode('Item updated successfully !').'&t='.base64_encode('success').'&ID='.$ID);
					die();
				}else{
					header("location:" .base_url(). "accounts/update_liason?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error').'&ID='.$ID);
					die();
				}				
			}
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/update_liason', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

public function liasonlist(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('center', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/liasonlist";
        	$config["total_rows"] = $this->accounts_model->liason_count($center, $start_date, $end_date);
        	$config["per_page"] = 40;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->liason_patination($config["per_page"], $per_page, $center, $start_date, $end_date);
			$data["center"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/liasonlist', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	/***********End Liason **********/
	
	public function indiaivf_document(){
		$logg = checklogin();

		if($logg['status'] == true){
			$data = array();
			
		if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_document'){

		unset($_POST['action']);
		$_POST['on_date'] = date("Y-m-d H:i:s");
		$transaction_img = '';
		if(!empty($_FILES['transaction_img']['tmp_name'])){
			$dest_path = $this->config->item('upload_path');
			$destination = $dest_path.'patient_files/';
			$NewImageName = rand(4,10000)."-". $_FILES['transaction_img']['name'];
			$transaction_img = base_url().'assets/patient_files/'.$NewImageName;
			move_uploaded_file($_FILES['transaction_img']['tmp_name'], $destination.$NewImageName);
			$_POST['transaction_img'] = $transaction_img;
		}
		
		$settle = $this->accounts_model->insert_add_indiaivf_document($_POST);
		 //echo "<br><pre>";
         //   print_r($this->accounts_model->insert_add_indiaivf_document($_POST)); 
          //  echo "</pre>";
		if($settle > 0){
			header("location:" .base_url(). "accounts/indiaivf_document?m=".base64_encode('Settled successfully!').'&t='.base64_encode('success'));
			die();
		}else{
				header("location:" .base_url(). "accounts/indiaivf_document?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));
				die();
			}
	}
	$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/indiaivf_document', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
public function indiaivf_document_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$document_name = $this->input->get('document_name', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/indiaivf_document_list";
        	$config["total_rows"] = $this->accounts_model->indiaivf_document_count($document_name);
        	$config["per_page"] = 40;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->indiaivf_document_patination($config["per_page"], $per_page, $document_name);
			$data["document_name"] = $document_name;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/indiaivf_document_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	/**********Investigation Origin**********/
	
    public function investigation_origin(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$payment_method = $this->input->get('payment_method', true);
			$export_investigation = $this->input->get('export-investigation', true);
			$json_data = $this->input->get('json_data', true);
			if (isset($export_investigation)){
				$data = $this->accounts_model->export_investigation_medicine($start_date, $end_date, $center, $patient_id, $origins);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Investigation-Report-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'Date, IIC ID, Investigation Name, Investigation Code, Investigation Price, Investigation Discount, Investigation Final Price';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$employee_number = get_center_name($val['employee_number']);
					$total = (int)$val['female_investigation_price'] - (int)$val['female_investigation_discount'];
					$lead_arr = array($val['on_date'], $val['patient_id'], $val['female_investigation_name'], $val['female_investigation_code'], $val['female_investigation_price'], $val['female_investigation_discount'], $total);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}
			$male_investigation = $this->input->get('export-male-investigation', true);
			if (isset($male_investigation)){
				$data = $this->accounts_model->export_male_investigation($start_date, $end_date, $center, $patient_id, $origins);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Investigation-Male-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'Date, IIC ID, Investigation Name, Investigation Code, Investigation Price, Investigation Discount, Investigation Final Price';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$employee_number = get_center_name($val['employee_number']);
					$total = (int)$val['male_investigation_price'] - (int)$val['male_investigation_discount'];
					$lead_arr = array($val['on_date'], $val['patient_id'], $val['male_investigation_name'], $val['male_investigation_code'], $val['male_investigation_price'], $val['male_investigation_discount'], $total);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}
			

			$config = array();
        	$config["base_url"] = base_url() . "accounts/investigation_origin";
        	$config["total_rows"] = $this->accounts_model->patient_investigation_origin_count($center, $start_date, $end_date, $patient_id, $payment_method, $json_data);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['investigation_result'] = $this->accounts_model->patient_investigation_origin_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method, $json_data);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["payment_method"] = $payment_method;
			$data["json_data"] = $json_data;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/investigation_origin', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	
	/***********End Company Document **********/
	
	public function admission_form_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){
			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$patient_id = $this->input->get('patient_id', true);			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/admission_form_list";
        	$config["total_rows"] = $this->accounts_model->admission_form_count($iic_id);
        	$config["per_page"] = 40;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;			
        	$data["links"] = $this->pagination->create_links();
			$data['admission_result'] = $this->accounts_model->admission_form_patination($config["per_page"], $per_page, $patient_id);
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/admission_form_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	/*public function partial_procedure_billing()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			if(isset($_GET['item_number'])){ $item_id = $_GET['item_number']; }
			if(isset($_POST['item_number'])) { $item_id = $_POST['item_number']; }
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'partial_procedure_billing'){
				unset($_POST['action']);
				$item_number = $_POST['item_number'];
				$company = $_POST['company'];
				$item_name = $_POST['item_name'];
				$generic_name = $_POST['generic_name'];
				$batch_number = $_POST['batch_number'];
				$vendor_price = $_POST['vendor_price'];
				$quantity = $_POST['quantity'];
				$quantity_in = $_POST['quantity_in'];
				$closingstock = (float) $quantity + (float) $quantity_in;
				$expiry = $_POST['expiry'];
				$mrp = $_POST['mrp'];
				$status = $_POST['status'];
				//echo "<pre>";
				//print_r($this->stock_model->update_item_data($_POST, $item_id));
				//echo "</pre>";
				//exit();
				//var_dump($this->stock_model->update_item_data($_POST, $item_id, $company, $generic_name, $batch_number, $mrp, $vendor_price, $quantity_in, $hsn, $pack_size, $gstrate, $gstdivision, $expiry, $status));die;
				$data = $this->stock_model->update_item_data($_POST, $item_id, $company, $item_name, $generic_name, $batch_number, $mrp, $vendor_price, $quantity_in, $hsn, $pack_size, $gstrate, $gstdivision, $expiry, $status);
				if($data > 0){
					//$data = $this->stock_model->update_central_stock_report($_POST, $item_id, $company, $item_name, $batch_number, $expiry, $expiry_day, $add_date, $vendor_price, $quantity_in, $mrp, $gstrate, $gstdivision, $quantity, $closingstock);
					header("location:" .base_url(). "billing_view/partial_procedure_billing?m=".base64_encode('Item updated successfully !').'&t='.base64_encode('success').'&item_number='.$item_id);
					die();
				}else{
					header("location:" .base_url(). "billing_view/partial_procedure_billing?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error').'&item_number='.$item_id);
					die();
				}				
			}
			//$data['data'] = $this->stock_model->get_item_data($item_id);
			//$data['categories'] = $this->stock_model->get_categories();
			//$data['brands'] = $this->stock_model->get_brands();
			$data['data'] = $this->accounts_model->get_details($receipt, $type);
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/partial_procedure_billing', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}*/
	
function partial_procedure_billing($receipt){

    $logg = checklogin();
    if($logg['status'] == true){
        $data = array();
        $type = $_GET['t'];
        $template = get_header_template($logg['role']);
        $this->load->view($template['header']);

        if($type == 'procedure'){
            if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_partial_procedure'){
                unset($_POST['action']);
                
                $post_arr = array();

                $post_arr['patient_id'] = $_POST['patient_id'];unset($_POST['patient_id']);
                
                $post_arr['billing_id'] = isset($_POST['billing_id'])?$_POST['billing_id']:''; unset($_POST['billing_id']);
                
                $post_arr['refrence_number'] = getGUID();
                
                $post_arr['type'] = 'procedure';
                
                $post_arr['transaction_id'] = $_POST['transaction_id'];unset($_POST['transaction_id']);

                $post_arr['billing_from'] = $_POST['billing_from'];unset($_POST['billing_from']);

                $post_arr['billing_at'] = $_POST['billing_at'];unset($_POST['billing_at']);

                $post_arr['employee_number'] = $_POST['employee_number'];unset($_POST['employee_number']);

                $post_arr['on_date'] = date("Y-m-d H:i:s");unset($_POST['on_date']);

                $transaction_img = '';

                if(!empty($_FILES['transaction_img']['tmp_name'])){

                    $dest_path = $this->config->item('upload_path');
                    $destination = $dest_path.'patient_files/';
                    $NewImageName = rand(4,10000)."-".$post_arr['patient_id']."-". $_FILES['transaction_img']['name'];
                    $transaction_img = base_url().'assets/patient_files/'.$NewImageName;

                    move_uploaded_file($_FILES['transaction_img']['tmp_name'], $destination.$NewImageName);
                    $post_arr['transaction_img'] = $transaction_img;
                }

                $post_arr['payment_method'] = $_POST['payment_method'];unset($_POST['payment_method']);
                // Reserving individual payment fields into post array safely
                $post_arr['biller'] = $_POST['biller'];unset($_POST['biller']);
                $post_arr['origins'] = $_POST['origins']; unset($_POST['origins']);
                $post_arr['series_number'] = $_POST['series_number'] ; unset($_POST['series_number']);

                $post_arr['status'] = '0';

                // ==================================================================
                // --- STEP 1: CALCULATE TOTAL PAID AMOUNT EARLY ---
                // ==================================================================
                $calculated_payment = 0;
                foreach($_POST as $key => $val){
                    if (strpos($key, 'sub_procedures_paid_price_') !== false) {
                        $calculated_payment += (float)$val;
                    }
                }
                
                // Set total paid amount (check if payment_done is sent directly, else use calculation)
                $total_paid_amount = isset($_POST['payment_done']) ? (float)$_POST['payment_done'] : $calculated_payment;
                
                // Store in post_arr for later use
                $post_arr['payment_done'] = $total_paid_amount;

// ==================================================================
                // --- SMART WALLET DEDUCTION LOGIC START (Package Wallet - W2) ---
                // ==================================================================
                $total_wallet_amount_to_deduct = 0;

                // Use the newly calculated $total_paid_amount
                if (isset($_POST['wallet_payment']) && (float)$_POST['wallet_payment'] > 0) {
                    $total_wallet_amount_to_deduct = (float)$_POST['wallet_payment'];
                } else {
                    $global_method = isset($post_arr['payment_method']) ? strtolower(trim($post_arr['payment_method'])) : '';
                    if ($global_method == 'wallet' || $global_method == 'package wallet' || strpos($global_method, 'wallet') !== false) {
                        $total_wallet_amount_to_deduct = $total_paid_amount;
                    }
                }

                // DEDUCT THE MONEY: Agar valid tracking price mila tabhi execute hoga
                if ($total_wallet_amount_to_deduct > 0) {
                    $wallet_patient_id = $post_arr['patient_id'];

                    // A. Fetch current wallet balances
                    $wallet = $this->db->get_where('hms_patient_wallets', ['patient_id' => $wallet_patient_id])->row();

                    // Auto-create wallet configuration entry structure if completely empty
                    if (!$wallet) {
                        $this->db->insert('hms_patient_wallets', [
                            'patient_id'       => $wallet_patient_id,
                            'wallet_1_balance' => 0,
                            'wallet_2_balance' => 0,
                            'created_at'       => date('Y-m-d H:i:s'),
                            'updated_at'       => date('Y-m-d H:i:s')
                        ]);
                        $wallet = $this->db->get_where('hms_patient_wallets', ['patient_id' => $wallet_patient_id])->row();
                    }

                    // B. Balance Check inside Wallet 2 (Package Wallet)
                    if ((float)$wallet->wallet_2_balance < $total_wallet_amount_to_deduct) {
                        header("location:" . base_url() . "accounts/partial_procedure_billing/".$post_arr['billing_id']."?t=procedure&m=" . base64_encode('Insufficient balance in Package Wallet!'));
                        die();
                    }

                    // C. New Mathematical Balances Mapping (Changes applied here for W2)
                    $opening_w1 = (float)$wallet->wallet_1_balance;
                    $closing_w1 = $opening_w1; // Wallet 1 remains untouched
                    
                    $opening_w2 = (float)$wallet->wallet_2_balance;
                    $closing_w2 = $opening_w2 - $total_wallet_amount_to_deduct; // Successfully deducted from Wallet 2

                    // D. Force Direct SQL Core Update command on Wallet 2 balance row
                    $this->db->query("UPDATE `hms_patient_wallets` SET `wallet_2_balance` = " . (float)$closing_w2 . ", `updated_at` = NOW() WHERE `patient_id` = " . $this->db->escape($wallet_patient_id));

                    // Normalizing user logs properties
                    $created_by_user = !empty($post_arr['biller']) ? $post_arr['biller'] : 'system';

                    // E. Insert Log History into hms_wallet_logs table
                    $log_data = [
                        'patient_id'     => $wallet_patient_id,
                        'amount'         => $total_wallet_amount_to_deduct,
                        'action_type'    => 'PROCEDURE_USAGE', 
                        'opening_w1'     => $opening_w1,
                        'closing_w1'     => $closing_w1,
                        'opening_w2'     => $opening_w2,
                        'closing_w2'     => $closing_w2,
                        'reference_id'   => $receipt, 
                        'payment_method' => 'Wallet', 
                        'remarks'        => 'Partial Procedure Payment deducted from Package Wallet. Bill ID: ' . $post_arr['billing_id'],
                        'created_by'     => $created_by_user,
                        'created_at'     => date('Y-m-d H:i:s'),
                        'status'         => 'success'
                    ];
                    $this->db->insert('hms_wallet_logs', $log_data);
                }

                // Cleaning up array mapping constraint after logic ends safely
                if(isset($_POST['wallet_payment'])) {
                    unset($_POST['wallet_payment']);
                }
                // ==================================================================
                // --- SMART WALLET DEDUCTION LOGIC END ---
                // ==================================================================
            
                $extract_procedure_array = $procedure_array = array();
                $c_counte = array();
                $c_counter = array();
                
                foreach($_POST as $key => $val){
                    $pos_c = strpos($key, 'sub_procedure_');
                    if ($pos_c === false) {} else {
                        $cid = (int) filter_var($key, FILTER_SANITIZE_NUMBER_INT);
                        $c_counter[] = $cid;
                    }   
                }
                
                if(!empty($c_counter)){
                    foreach($c_counter as $key => $ccounte){
                        if($_POST['sub_procedure_'.$ccounte] == ''){
                            unset($_POST['sub_procedure_'.$ccounte]);
                            unset($_POST['sub_procedures_code_'.$ccounte]);
                            unset($_POST['sub_procedures_price_'.$ccounte]);
                            unset($_POST['sub_procedures_paid_price_'.$ccounte]);
                        } else {
                            // insert query array push
                            $c_counte[] = array(
                                'sub_procedure'             => $_POST['sub_procedure_'.$ccounte],
                                'sub_procedures_code'       => $_POST['sub_procedures_code_'.$ccounte],
                                'sub_procedures_price'      => $_POST['sub_procedures_price_'.$ccounte],
                                'sub_procedures_paid_price' => $_POST['sub_procedures_paid_price_'.$ccounte]
                            );
                        }
                    }
                }
                
                $details = array();
                $details['patient_procedures'] = $c_counte;
                $post_arr['data'] = serialize($details);
                
                // NOTE: Here I have removed the old hardcoded $post_arr['payment_done'] line 
                // because we already calculated and assigned it at STEP 1 above.
                    
                $curl = curl_init();
                curl_setopt_array($curl, array(
                  CURLOPT_URL => 'https://hms.indiaivf.website/old_reports/patient_payment/',
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => '',
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'POST',
                  CURLOPT_POSTFIELDS => json_encode($post_arr),
                  CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                  ),
                ));
                
                $response = curl_exec($curl);
                curl_close($curl);
                
                echo $response;
                    
                $p_procd = $this->accounts_model->partial_procedure_insert($post_arr);
                
                if($p_procd > 0){
                    header("location:" .base_url(). "accounts/partial_procedure_billing/".$post_arr['billing_id']."?t=procedure");
                    die();
                } else {
                    header("location:" .base_url(). "accounts/partial_procedure_billing?m=".base64_encode('something went wrong!').'&t='.base64_encode('error'));
                    die();
                }
            }
            
            $data['data'] = $this->accounts_model->get_details($receipt, $type);
            $this->load->view('accounts/partial_procedure_billing', $data);
        }
        $this->load->view($template['footer']);
    } else {
        header("location:" .base_url(). "");
        die();
    }
}
	
		public function add_donor()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_donor'){
				unset($_POST['action']);
				//var_dump($_POST);die;
				//$hsn = $_POST['hsn'];
				
				$post_arr['uhid'] = $_POST['uhid'];unset($_POST['uhid']);
				$post_arr['patient_id'] = $_POST['patient_id']; unset($_POST['patient_id']);
				$post_arr['PatientName'] = $_POST['PatientName'] ; unset($_POST['PatientName']);
				$post_arr['donor_uhid'] = $_POST['donor_uhid'] ; unset($_POST['donor_uhid']);
				$post_arr['donor_patient_id'] = $_POST['donor_patient_id'] ; unset($_POST['donor_patient_id']);
				$post_arr['donor_PatientName'] = $_POST['donor_PatientName'] ; unset($_POST['donor_PatientName']);
				$post_arr['type'] = $_POST['type'] ; unset($_POST['type']);
				$post_arr['date'] = $_POST['date'] = date("Y-m-d H:i:s");
				
				$data = $this->accounts_model->donor_insert($post_arr);
				if($data > 0){
					header("location:" .base_url(). "accounts/add_donor?m=".base64_encode('Item added successfully !').'&t='.base64_encode('success'));
					die();
				}else{
					header("location:" .base_url(). "accounts/add_donor?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
					die();
				}				
			}
			$data = array();
			$data['uhid'] = $this->accounts_model->get_uhid();
			$data['medicines'] = $this->accounts_model->get_paitent_id();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/add_donor', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function donor_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$patient_id = $this->input->get('patient_id', true);
			$donor_patient_id = $this->input->get('donor_patient_id', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/donor_list";
        	$config["total_rows"] = $this->accounts_model->donor_count($patient_id, $donor_patient_id);
        	$config["per_page"] = 40;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->donor_patination($config["per_page"], $per_page, $patient_id, $donor_patient_id);
			$data["patient_id"] = $patient_id;
			$data["donor_patient_id"] = $donor_patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/donor_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function cancel_procedure_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_cancel_billing = $this->input->get('export-cancel-billing', true);
			if (isset($export_cancel_billing)){
				$data = $this->accounts_model->export_cancel_procedure_data($start_date, $end_date, $center, $patient_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Cancel-Procedure-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type, Billing Date,Cancel Date, Status';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at, $val['billing_type'], $val['on_date'], $val['modified_on'], $val['status']);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}
	
			$config = array();
        	$config["base_url"] = base_url() . "accounts/cancel_procedure_list";
        	$config["total_rows"] = $this->accounts_model->procedure_cancel_count($center, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->procedure_cancel_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/cancel_procedure_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function cancel_medicine_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
	
			$config = array();
        	$config["base_url"] = base_url() . "accounts/cancel_medicine_list";
        	$config["total_rows"] = $this->accounts_model->medicine_cancel_count($center, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['medicine_cancel_result'] = $this->accounts_model->medicine_cancel_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/cancel_medicine_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function cancel_consultation_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
	
			$config = array();
        	$config["base_url"] = base_url() . "accounts/cancel_consultation_list";
        	$config["total_rows"] = $this->accounts_model->consultation_cancel_count($center, $start_date, $end_date, $patient_id, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_cancel_result'] = $this->accounts_model->consultation_cancel_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/cancel_consultation_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	
function wallet($receipt_number){

		$logg = checklogin();

		if($logg['status'] == true){

			$data = array();

			$type = $_GET['t'];

			$template = get_header_template($logg['role']);

			$this->load->view($template['header']);

				$data['data'] = $this->accounts_model->get_wallet_details($receipt_number, $type);

				$this->load->view('accounts/wallet', $data);

			if(empty($data['data'])){

				header("location:" .base_url(). "dashboard?m=".base64_encode('Details not found!').'&t='.base64_encode('error'));

				die();

			}

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

}
	
function consultation_wallet($receipt_number){

		$logg = checklogin();

		if($logg['status'] == true){

			$data = array();

			$template = get_header_template($logg['role']);

			$this->load->view($template['header']);

				$data['data'] = $this->accounts_model->get_consultation_wallet($receipt_number);

				$this->load->view('accounts/consultation_wallet', $data);

			if(empty($data['data'])){

				header("location:" .base_url(). "dashboard?m=".base64_encode('Details not found!').'&t='.base64_encode('error'));

				die();

			}

			$this->load->view($template['footer']);

		}else{

			header("location:" .base_url(). "");

			die();

		}

}
	
	function get_employee_list(){
		$employee = $this->accounts_model->get_employee_list();
		return $employee;	
	}
	function get_employee_name($employee){
		$name = $this->accounts_model->get_employee_name($employee);
		return $name;	
	}

	function get_stock_user(){
		$user = $this->stock_model->get_stock_user();
		return $user;	
	}

	public function add_training()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_training'){
				unset($_POST['action']);
				$data = $this->accounts_model->training_insert($_POST);
				if($data > 0){
					header("location:" .base_url(). "accounts/add_training?m=".base64_encode('Item added successfully !').'&t='.base64_encode('success'));
					die();
				}else{
					header("location:" .base_url(). "accounts/add_training?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
					die();
				}				
			}
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/add_training', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function training_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$training_name = $this->input->get('training_name', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/training_list";
        	$config["total_rows"] = $this->accounts_model->training_count($training_name);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_cancel_result'] = $this->accounts_model->training_pagination($config["per_page"], $per_page, $training_name);
			$data["training_name"] = $training_name;
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/training_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	function approve_training($ID){
		$approved = $this->accounts_model->approve_training($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/training_list?m=".base64_encode('Product Advisory Fee approved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/training_list?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}
	
	function disapprove_training($ID){
		$approved = $this->accounts_model->disapprove_training($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/training_list?m=".base64_encode('Product Advisory Fee Disapproved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/training_list?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}

	public function add_fellowship()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_fellowship'){
				unset($_POST['action']);
				$data = $this->accounts_model->fellowship_insert($_POST);
				if($data > 0){
					header("location:" .base_url(). "accounts/add_fellowship?m=".base64_encode('Item added successfully !').'&t='.base64_encode('success'));
					die();
				}else{
					header("location:" .base_url(). "accounts/add_fellowship?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
					die();
				}				
			}
			$data = array();
			$template = get_header_template($logg['role']);
			$data['courses'] = $this->accounts_model->get_courses_name();
			$this->load->view($template['header']);
			$this->load->view('accounts/add_fellowship', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function fellowship_and_training(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$name = $this->input->get('name', true);
			$export_fellowship = $this->input->get('export-billing', true);
			if (isset($export_fellowship)){
				$data = $this->accounts_model->export_fellowship_and_training($name);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=fellowship-reports-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'Student ID, Name, Fname, Course, Code, HSN, Price, Discount Amount, Payment Done, Gst Amount, Remaining Amount,GST,Payment Method,Address,Place of Supply,Gst Number,On Date,Receipt,Invoice No, Status';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$lead_arr = array($val['studentid'], $val['name'], $val['fname'], $val['course'], $val['code'], $val['hsn'], $val['price'], $val['discount_amount'], $val['payment_done'], $val['gst_amount'],$val['remaining_amount'],$val['gst'],$val['payment_method'],$val['address'],$val['place_of_supply'],$val['gst_number'],$val['on_date'],$val['receipt'],$val['invoice_no'], $val['status']);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}
			$config = array();
        	$config["base_url"] = base_url() . "accounts/fellowship_and_training";
        	$config["total_rows"] = $this->accounts_model->fellowship_count($name);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_cancel_result'] = $this->accounts_model->fellowship_pagination($config["per_page"], $per_page, $name);
			$data["name"] = $name;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/fellowship_and_training', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function fellowship_payment(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$name = $this->input->get('name', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/fellowship_payment";
        	$config["total_rows"] = $this->accounts_model->fellowship_payment_count($name);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_cancel_result'] = $this->accounts_model->fellowship_payment_pagination($config["per_page"], $per_page, $name);
			$data["name"] = $name;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/fellowship_payment', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	function approve_fellowship($ID) {
		// Generate a voucher code
		$voucherCode = generateVoucherCode(12);  // 12-character voucher code

		// Call the model to approve the fellowship and pass the voucher code
		$approved = $this->accounts_model->approve_fellowship($ID, $voucherCode);

		// Check if the fellowship was approved successfully
		if ($approved > 0) {
			header("location:" .base_url(). "accounts/fellowship_and_training?m=".base64_encode('Product Advisory Fee approved! Voucher code: ' . $voucherCode).'&t='.base64_encode('success'));
			die();
		} else {
			header("location:" .base_url(). "accounts/fellowship_and_training?m=".base64_encode('Something went wrong!').'&t='.base64_encode('error'));
			die();
		}
	}
	
	function disapprove_fellowship($ID){
		$approved = $this->accounts_model->disapprove_fellowship($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/fellowship_and_training?m=".base64_encode('Product Advisory Fee Disapproved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/fellowship_and_training?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}
	
	function cancel_fellowship($ID){
		$approved = $this->accounts_model->cancel_fellowship($ID);
		if($approved > 0){
			header("location:" .base_url(). "accounts/fellowship_and_training?m=".base64_encode('Product Advisory Fee Disapproved!').'&t='.base64_encode('success'));
			die();
		}else{
			header("location:" .base_url(). "accounts/fellowship_and_training?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
			die();
		}
	}

	function fellow_details($receipt){

		$logg = checklogin();

		if($logg['status'] == true){

			$data = array();

			$template = get_header_template($logg['role']);

			$this->load->view($template['header']);

				$data['data'] = $this->accounts_model->get_fellowship_details($receipt);

				$this->load->view('accounts/fellow_details', $data);
			
			$this->load->view($template['footer']);

		}else{

			header("Location: " . base_url() . "accounts/fellowship_and_training");

			die();

		}

	}

	function product_advisory_details($invoice_no){

		$logg = checklogin();

		if($logg['status'] == true){

			$data = array();

			$template = get_header_template($logg['role']);

			$this->load->view($template['header']);

				$data['data'] = $this->accounts_model->get_productadvisory_details($invoice_no);

				$this->load->view('accounts/product_advisory_details', $data);
			
			$this->load->view($template['footer']);

		}else{

			header("Location: " . base_url() . "accounts/training_list");

			die();

		}

	}


	public function wallet_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$patient_id = $this->input->get('patient_id', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/wallet_list";
        	$config["total_rows"] = $this->accounts_model->wallet_count($patient_id);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['wallet_result'] = $this->accounts_model->wallet_pagination($config["per_page"], $per_page, $patient_id);
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/wallet_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	function wallet_refund($receipt_number){
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			$type = $_GET['t'];
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
				$data['data'] = $this->accounts_model->get_wallet_refund($receipt_number);
				$this->load->view('accounts/wallet_refund', $data);
			if(empty($data['data'])){
				header("location:" .base_url(). "dashboard?m=".base64_encode('Details not found!').'&t='.base64_encode('error'));
				die();
			}
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

public function procedure_advice(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$patient_id = $this->input->get('iic_id', true);
			$config = array();
        	$config["per_page"] = 50;
        	$data['app_result'] = $this->accounts_model->doctor_patient_lists_pagination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/procedure_advice', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	/*public function add_fellowship_payments()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_fellowship_payments'){
				unset($_POST['action']);
				$data = $this->accounts_model->fellowship_insert_payment($_POST);
				if($data > 0){
					header("location:" .base_url(). "accounts/add_fellowship_payments?m=".base64_encode('Item added successfully !').'&t='.base64_encode('success'));
					die();
				}else{
					header("location:" .base_url(). "accounts/add_fellowship_payments?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
					die();
				}				
			}
			$data = array();
			$template = get_header_template($logg['role']);
			$data['courses'] = $this->accounts_model->get_courses_name();
			$this->load->view($template['header']);
			$this->load->view('accounts/add_fellowship_payments', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}*/



function add_fellowship_payments($ID){
	$logg = checklogin();
	if($logg['status'] == true){
		if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_fellowship_payments'){
			unset($_POST['action']);
			$data = $this->accounts_model->fellowship_insert_payment($_POST);
			if($data > 0){
				header("location:" .base_url(). "accounts/add_fellowship_payments?m=".base64_encode('Item added successfully !').'&t='.base64_encode('success'));
				die();
			}else{
				header("location:" .base_url(). "accounts/add_fellowship_payments?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
				die();
			}				
		}
		$data = array();
		$template = get_header_template($logg['role']);
		$this->load->view($template['header']);
		$data['data'] = $this->accounts_model->get_fellowship_payments($ID);
		$this->load->view('accounts/add_fellowship_payments', $data);
		$this->load->view($template['footer']);
	}else{
		header("Location: " . base_url() . "accounts/add_fellowship_payments");
		die();
	}
}


/***********Start Partial Payment***********/
public function partial_procedure(){
	$logg = checklogin();
	error_reporting(0);
	if($logg['status'] == true){

		$per_page = $this->input->get('per_page', true);
		if(empty($per_page)){
			$per_page = 0;
		}
		$center = $this->input->get('billing_at', true);
		$start_date = $this->input->get('start_date', true);
		$end_date = $this->input->get('end_date', true);
		$patient_id = $this->input->get('iic_id', true);
		$payment_method = $this->input->get('payment_method', true);
		$export_billing = $this->input->get('export-consumption', true);
		$json_data = $this->input->get('json_data', true);
		
		$paid_amount = 0;
			$discounted_package = 0;
			$total_package = 0;
			if (isset($export_billing)){
				$data = $this->accounts_model->export_partial_procedure_data($start_date, $end_date, $center, $patient_id, $payment_method, $biller_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Procedure-Reports-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name,Paid Amount, Payment Method, Billing From, Billing At,Procedure Name, Billing Type, Date, Status';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$paid_amount = $paid_amount +  (int)$val['payment_done'];
					$total_package = $total_package +  (int)$val['totalpackage'];
					$discounted_package = $discounted_package +  (int)$val['discounted_package'];
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at,$val['procedure_name'] ,$val['billing_type'], $val['on_date'], $val['status']);
					fputcsv($fp, $lead_arr);
				}
				$final_arr = array("", "", "", $total_package, $discounted_package, $paid_amount, "", "", "", "", "", "", "");
				fputcsv($fp, $final_arr);
				fclose($fp);
				exit();
			}

		$config = array();
		$config["base_url"] = base_url() . "accounts/partial_procedure";
		$config["total_rows"] = $this->accounts_model->partial_procedure_payment_count($center, $start_date, $end_date, $patient_id, $payment_method, $json_data);
		$config["per_page"] = 20;
		$config["uri_segment"] = 2;
		$config['use_page_numbers'] = true;
		$config['num_links'] = 5;
		$config['page_query_string'] = true;
		$config['reuse_query_string'] = true;
		$this->pagination->initialize($config);
		$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
		
		$data["links"] = $this->pagination->create_links();
		$data['procedure_result'] = $this->accounts_model->partial_procedure_payment_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $payment_method, $json_data);
		$data["billing_at"] = $center;
		$data["start_date"] = $start_date;
		$data["end_date"] = $end_date;
		$data["patient_id"] = $patient_id;
		$data["payment_method"] = $payment_method;
		$data["json_data"] = $json_data;
		$template = get_header_template($logg['role']);
		$this->load->view($template['header']);
		$this->load->view('accounts/partial_procedure', $data);
		$this->load->view($template['footer']);
	}else{
		header("location:" .base_url(). "");
		die();
	}
}

 public function revenue_potential(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('patient_id', true);
			$center_number = $this->input->get('center_number', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/revenue_potential";
        	$config["total_rows"] = $this->accounts_model->get_doctor_patient_count($start_date, $end_date, $patient_id, $center_number);
        	$config["per_page"] = 20;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['app_result'] = $this->accounts_model->patient_lists_pagination($config["per_page"], $per_page, $start_date, $end_date, $patient_id, $center_number);
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["center_number"] = $center_number;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/revenue_potential', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	
	public function revenue_potential_details($patient_id){
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
			$data['data'] = $this->accounts_model->revenue_potential_details($patient_id);
			//$data['form_data'] = $this->accounts_model->get_discharge_form_data();
			//$data['form_data_embrology'] = $this->accounts_model->get_discharge_form_data_embrology();
			//$data['formdata'] = $this->accounts_model->get_discharge_data();
			//$data['investigation_reports'] = $this->accounts_model->investigation_reports($patient_id);
			//$data['procedure_reports'] = $this->accounts_model->procedure_reports($patient_id);
			//$data['patient_discharge'] = $this->accounts_model->patient_discharge($patient_id);
			$this->load->view($template['header']);
			$this->load->view('accounts/revenue_potential_details', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function export_consultation_data($patient_id){
		$logg = checklogin();
		if($logg['status'] == true){
			error_reporting(0);
			ini_set('display_errors', 0);
			$patient_data = get_patient_detail($patient_id);
			$patient_name = 'N/A';
			if (is_array($patient_data)) {
				$patient_name = isset($patient_data['wife_name']) ? $patient_data['wife_name'] : 
							   (isset($patient_data['wife_name']) ? $patient_data['wife_name'] : 'N/A');
			}
			$sql = "SELECT * FROM `hms_doctor_consultation` WHERE `patient_id`='".$patient_id."' ORDER BY `ID` DESC";
			$query = $this->db->query($sql);
			$results = $query->result();
			
			// Set headers for CSV download
			$filename = 'consultation_data_' . $patient_id . '_' . date('Y-m-d_H-i-s') . '.csv';
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			// Create file pointer
			$output = fopen('php://output', 'w');
			// Add patient information header
			fputcsv($output, array('Patient ID: ' . $patient_id));
			fputcsv($output, array('Patient Name: ' . $patient_name));
			fputcsv($output, array('')); // Empty row for spacing
			// CSV headers
			$headers = array(
				'Date',
				'Doctor Name',
				'Investigation Advice',
				'Investigation Billing Price',
				'Procedure Advice',
				'Procedure Billing',
				'Medicine Advice'
			);
			fputcsv($output, $headers);
			
			if (!empty($results)) {
				foreach ($results as $row) {
					// Get doctor name safely
					$doctor_name = 'N/A';
					if (!empty($row->doctor_id)) {
						$doc_sql = "SELECT * FROM `hms_doctors` WHERE ID='".$row->doctor_id."'";
						$doctor_result = run_select_query($doc_sql);
						$doctor_name = !empty($doctor_result) ? $doctor_result['name'] : 'N/A';
					}
					
					// Process investigation advice
					$investigation_advice = '';
					$investigation_billing_price = 0;
					
					// Female investigations
					if (!empty($row->female_investigation_suggestion_list)) {
						$female_investigations = @unserialize($row->female_investigation_suggestion_list);
						if ($female_investigations !== false && is_array($female_investigations)) {
							foreach ($female_investigations as $key => $value) {
								if (!empty($value)) {
									$inv_sql = "SELECT * FROM `hms_investigation` WHERE ID IN ($value)";
									$inv_result = run_select_query($inv_sql);
									if (!empty($inv_result)) {
										$investigation_advice .= "Investigation (Female): " . $inv_result['investigation'] . "; ";
										$investigation_billing_price += (float)$inv_result['price'];
									}
								}
							}
						}
					}
					
					// Male investigations
					if (!empty($row->male_investigation_suggestion_list)) {
						$male_investigations = @unserialize($row->male_investigation_suggestion_list);
						if ($male_investigations !== false && is_array($male_investigations)) {
							foreach ($male_investigations as $key => $value) {
								if (!empty($value)) {
									$inv_sql = "SELECT * FROM `hms_investigation` WHERE ID IN ($value)";
									$inv_result = run_select_query($inv_sql);
									if (!empty($inv_result)) {
										$investigation_advice .= "Investigation (Male): " . $inv_result['investigation'] . "; ";
										$investigation_billing_price += (float)$inv_result['price'];
									}
								}
							}
						}
					}
					
					// Get actual investigation billing from approved investigations
					if (!empty($row->appointment_id)) {
						$inv_billing_sql = "SELECT investigations, totalpackage, discount_amount FROM hms_patient_investigations WHERE appointment_id='" . $row->appointment_id . "' AND patient_id='" . $row->patient_id . "' AND status='approved'";
						$inv_billing_result = run_select_query($inv_billing_sql);
						if ($inv_billing_result && isset($inv_billing_result['totalpackage'])) {
							$investigation_billing_price = $inv_billing_result['totalpackage'] - $inv_billing_result['discount_amount'];
						}
					}
					
					// Process procedure advice
					$procedure_advice = '';
					$procedure_billing = 0;
					
					if (!empty($row->sub_procedure_suggestion_list)) {
						$procedures = @unserialize($row->sub_procedure_suggestion_list);
						if ($procedures !== false && is_array($procedures)) {
							foreach ($procedures as $key => $value) {
								if (!empty($value)) {
									$proc_sql = "SELECT * FROM `hms_procedures` WHERE ID IN ($value)";
									$proc_result = run_select_query($proc_sql);
									if (!empty($proc_result)) {
										$procedure_advice .= "Procedure: " . $proc_result['procedure_name'] . "; ";
										$procedure_billing += (float)$proc_result['price'];
									}
								}
							}
						}
					}
					
					// Get actual procedure billing from approved procedures
					if (!empty($row->appointment_id)) {
						$proc_billing_sql = "SELECT data, totalpackage, discount_amount FROM `hms_patient_procedure` WHERE appointment_id = ? AND patient_id = ? AND status = 'approved'";
						$proc_billing_result = $this->db->query($proc_billing_sql, array($row->appointment_id, $row->patient_id))->row_array();
						if ($proc_billing_result && isset($proc_billing_result['totalpackage'])) {
							$procedure_billing = $proc_billing_result['totalpackage'] - $proc_billing_result['discount_amount'];
						}
					}
					
					// Process medicine advice
					$medicine_advice = '';
					$medicine_total = 0;
					
					// Male medicines
					if (!empty($row->male_medicine_suggestion_list)) {
						$male_medicines = @unserialize($row->male_medicine_suggestion_list);
						if ($male_medicines !== false && isset($male_medicines['male_medicine_suggestion_list']) && is_array($male_medicines['male_medicine_suggestion_list'])) {
							foreach ($male_medicines['male_medicine_suggestion_list'] as $med) {
								if (is_array($med) && isset($med['male_medicine_name'])) {
									// Get medicine details from database
									$medicine_details = $this->get_medicine_details($med['male_medicine_name']);
									$medicine_name = !empty($medicine_details) && isset($medicine_details['item_name']) ? $medicine_details['item_name'] : $med['male_medicine_name'];
									
									// Calculate pricing
									$unit_price = 0;
									$subtotal = 0;
									if (!empty($medicine_details)) {
										$unit_price = product_vendor_cost($medicine_details['product_id'], $medicine_details['brand_name'], $medicine_details['vendor_number']);
										
										// Check if it's syrup type
										$sql = "Select * from ".$this->config->item('db_prefix')."stock_products where ID='".$medicine_details['product_id']."'";
										$select_result = run_select_query($sql);
										
										if ($select_result['type'] == "Cyrup") {
											$subtotal = (1 * 1) * ($unit_price * 1);
										} else {
											$frequency = medical_frequency($med['male_medicine_frequency']);
											$subtotal = ($med['male_medicine_days'] * $frequency) * ($unit_price * $med['male_medicine_dosage']);
										}
										$medicine_total += $subtotal;
									}
									
									$medicine_advice .= "Medicine (Male): " . $medicine_name . " - " . 
													   (isset($med['male_medicine_dosage']) ? $med['male_medicine_dosage'] : 'N/A') . " for " . 
													   (isset($med['male_medicine_days']) ? $med['male_medicine_days'] : 'N/A') . " days - " .
													   "Unit Price: Rs." . round($unit_price, 2) . " - " .
													   "Subtotal: Rs." . round($subtotal, 2) . "\n";
								}
							}
						}
					}
					
					// Female medicines
					if (!empty($row->female_medicine_suggestion_list)) {
						$female_medicines = @unserialize($row->female_medicine_suggestion_list);
						if ($female_medicines !== false && isset($female_medicines['female_medicine_suggestion_list']) && is_array($female_medicines['female_medicine_suggestion_list'])) {
							foreach ($female_medicines['female_medicine_suggestion_list'] as $med) {
								if (is_array($med) && isset($med['female_medicine_name'])) {
									// Get medicine details from database
									$medicine_details = $this->get_medicine_details($med['female_medicine_name']);
									$medicine_name = !empty($medicine_details) && isset($medicine_details['item_name']) ? $medicine_details['item_name'] : $med['female_medicine_name'];
									
									// Calculate pricing
									$unit_price = 0;
									$subtotal = 0;
									if (!empty($medicine_details)) {
										$unit_price = product_vendor_cost($medicine_details['product_id'], $medicine_details['brand_name'], $medicine_details['vendor_number']);
										
										// Check if it's syrup type
										$sql = "Select * from ".$this->config->item('db_prefix')."stock_products where ID='".$medicine_details['product_id']."'";
										$select_result = run_select_query($sql);
										
										if ($select_result['type'] == "Cyrup") {
											$subtotal = (1 * 1) * ($unit_price * 1);
										} else {
											$frequency = medical_frequency($med['female_medicine_frequency']);
											$subtotal = ($med['female_medicine_days'] * $frequency) * ($unit_price * $med['female_medicine_dosage']);
										}
										$medicine_total += $subtotal;
									}
									
									$medicine_advice .= "Medicine (Female): " . $medicine_name . " - " . 
													   (isset($med['female_medicine_dosage']) ? $med['female_medicine_dosage'] : 'N/A') . " for " . 
													   (isset($med['female_medicine_days']) ? $med['female_medicine_days'] : 'N/A') . " days - " .
													   "Unit Price: Rs." . round($unit_price, 2) . " - " .
													   "Subtotal: Rs." . round($subtotal, 2) . "\n";
								}
							}
						}
					}
					
					// Add total medicine cost
					if ($medicine_total > 0) {
						$medicine_advice .= "TOTAL MEDICINE COST: Rs." . round($medicine_total, 2) . "\n";
					}
					
					// Write row to CSV (without patient ID and name)
					$csv_row = array(
						$row->consultation_date,
						$doctor_name,
						$investigation_advice,
						$investigation_billing_price,
						$procedure_advice,
						$procedure_billing,
						$medicine_advice
					);
					fputcsv($output, $csv_row);
				}
			}
			
			fclose($output);
			exit();
		} else {
			header("location:" . base_url() . "");
			die();
		}
	}
	
	public function patient_center_wise_report(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_procedure_center_data($start_date, $end_date, $center, $patient_id);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Procedure-Patients-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'Visit Month, First Visit Date, Booking Month, Booking Date, CH/FC Name, Doctor consulted, IIC ID, Patients Name, Centre Booking, Centre Procedure, Patients Source, Patient Type(New/Follow Up/ Recycle),Procedure Type(Add-on/IVF-procedure),Status of Booking,Procedure name (Same Day all procedure in same Column) IVF with Bed,Package Amount,Discount Amount,Package After Discount,Payment received,Balance Amount,Status(on Track/Delayed/Likely Cancellation),Comment/Current Status of Treatment( Latest Remarks and Date of Doctos),Next Steps - Follow UP,Std Withdrawal Date (T),Actual Withdrawal Date,Withdrawl Status (Done/Pending),Std Stimulation Date (40% of amount) (T+10),Actual Stimulation Start Date,Stimulation Start Status (Done/Pending),Amount (stimulation date),Std Trigger Date (50% of Package)(T+18),Actual Trigger Date,Amount (Billing Date),Trigger Status (Done/Pending),Std OPU Date(T +20),Actual OPU Date,Amount (Billing Date),OPU Status (Done/Pending)';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$billing_at = get_center_name($val['billing_at']);
					
					$sql = "Select * from ".$this->config->item('db_prefix')."appointments where paitent_id='".$val['patient_id']."'";
					$select_result = run_select_query($sql);
		
					$sql2 = "Select * from ".$this->config->item('db_prefix')."appointments where wife_phone='".$select_result['wife_phone']."' and paitent_type='new_patient' and status='consultation_done'";
					$select_result2 = run_select_query($sql2);
					
					$sql3 = "SELECT SUM(payment_done) AS total_payment_done, MAX(on_date) AS last_on_date  FROM hms_patient_payments WHERE billing_id = '" . $val['receipt_number'] . "' AND status = '1'";
					$select_result3 = run_select_query($sql3);
					
					$sql4 = "SELECT * FROM hms_doctor_consultation WHERE appointment_id='" . $val['appointment_id'] . "'";
					$select_result4 = run_select_query($sql4);
					
					$sql5 = "SELECT * from ovulation_induction_protocol WHERE receipt_number='" . $val['receipt_number'] . "'";
					$select_result5 = run_select_query($sql5);
					
					$sql6 = "SELECT * FROM trigger_module WHERE receipt_number='" . $val['receipt_number'] . "'";
					$select_result6 = run_select_query($sql6);
					
					$sql7 = "SELECT * FROM hms_appointments WHERE ID = '" . $val['appointment_id'] . "'";
					$select_result7 = run_select_query($sql7);
					
					$sql8 = "SELECT * FROM hms_doctors WHERE ID='" . $select_result4['doctor_id'] . "'";
					$select_result8 = run_select_query($sql8);
					
					$date_value = $select_result5['date1'] ?? ''; // Get date from array
					if (!empty($date_value)) {
					$sql9 = "SELECT SUM(payment_done) AS total_stdate_payment_done FROM hms_patient_payments WHERE billing_id = '" . $val['receipt_number'] . "' AND status = '1' AND DATE(on_date) <= '".$select_result5['date1']."'";
					$select_result9 = run_select_query($sql9);
					}
					
					$date_value_last_inj_fsh = $select_result6['last_inj_fsh'] ?? ''; // Get date from array
					if (!empty($date_value_last_inj_fsh)) {
					$sql10 = "SELECT SUM(payment_done) AS total_stdate_payment_done FROM hms_patient_payments WHERE billing_id = '" . $val['receipt_number'] . "' AND status = '1' AND DATE(on_date) <= '".$select_result6['last_inj_fsh']."'";
					$select_result10 = run_select_query($sql10);
					}
					
					$date_value_ovum_pickup = $select_result6['ovum_pick_up_on'] ?? ''; // Get date from array
					if (!empty($date_value_ovum_pickup)) {
					$sql11 = "SELECT SUM(payment_done) AS total_stdate_payment_done FROM hms_patient_payments WHERE billing_id = '" . $val['receipt_number'] . "' AND status = '1' AND DATE(on_date) <= '".$select_result6['ovum_pick_up_on']."'";
					$select_result11 = run_select_query($sql11);
					}
					
					$total_payment_done = isset($select_result3['total_payment_done']) ? (float)$select_result3['total_payment_done'] : 0;
					$total = (int)$val['sub_procedures_price'] - (int)$val['sub_procedures_discount'];
					$total_receive = $vl['payment_done'] + $total_payment_done ;
					$pending_amount = $vl['fees'] - $total_receive;

					$type = isset($select_result7['paitent_type']) ? $select_result7['paitent_type'] : null;

					$date = $select_result2['appoitmented_date']; // Original date (YYYY-MM-DD)
					$formatted_date = date("F y", strtotime($date)); // Output: "June 24"
					
					$on_date = $vl['on_date']; // Get the original date
					$new_date = date('Y-m-d', strtotime($on_date . ' +10 days')); // Add 10 days
					
					$on_date = $vl['on_date']; // Get the original date
					$new_date20 = date('Y-m-d', strtotime($on_date . ' +10 days')); // Add 10 days
					
					$appoitmented_date = $select_result2['appoitmented_date'];
					
					$visit_date = $val['on_date']; // Original date (YYYY-MM-DD)
					$formatted_date = date("F y", strtotime($visit_date));
					
					$amount_stimulation = $val['payment_done'] + (float)$select_result['total_stdate_payment_done'];
					
					$amount_billing_date = $val['payment_done'] + $select_result11['total_stdate_payment_done'];
					
					$vl['payment_done'] + $select_result10['total_stdate_payment_done'];
					
					$lead_arr = array($date, $appoitmented_date, $visit_date, $val['on_date'], 'FC NAME', $select_result8['name'], $val['patient_id'], $val['wife_name'], $billing_at, $select_result6['admit_at'],'Telecalling', $type, $v2_data5['sub_procedures_code'], $val['status'],$select_result8['category'], $val['totalpackage'], $val['discount_amount'], $val['fees'], $total_receive, $pending_amount, '', '', $select_result4['follow_up_date'], $val['on_date'], '', 'Done', $new_date, $select_result5['date1'], 'Done', $amount_stimulation, $new_date,'',$select_result6['last_inj_fsh'],'Done', $new_date20, $select_result6['ovum_pick_up_on'],$amount_billing_date,$procedure_name1);
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/patient_center_wise_report";
        	$config["total_rows"] = $this->accounts_model->patient_report_count($center, $status, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
        	$config["per_page"] = 50;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->patient_report_list_patination($config["per_page"], $per_page, $center, $status, $start_date, $end_date, $patient_id, $payment_method, $biller_id);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/patient_center_wise_report', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	
	
	function get_medicine_details($medicine){

		$details = $this->billingmodel_model->get_medicine_details($medicine);

		return $details;
	}
	
	function get_centerlist(){
		$center = $this->center_model->get_centers();
		return $center;
	}
	
	public function package_collections(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$center = $this->input->get('billing_at', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			$patient_id = $this->input->get('iic_id', true);
			$status = $this->input->get('status', true);
			$export_billing = $this->input->get('export-billing', true);
			if (isset($export_billing)){
				$data = $this->accounts_model->export_partialpayments_data($start_date, $end_date, $center, $patient_id, $status);
				header('Content-Type: text/csv; charset=utf-8');
				header('Content-Disposition: attachment; filename=Partialpayments-'.$start_date.'-'.$end_date.'.csv');
				$fp = fopen('php://output','w');
				$headers = 'IIC ID, Patient Name, Total package, Discounted Package, Paid Amount, Remaining Amount, Payment Method, Billing From, Billing At, Billing Type, Date, Status';
				//Add the headers
				fwrite($fp, $headers. "\r\n");
				foreach ($data as $key => $val) {//var_dump($val);die;
					$billing_from = $val['billing_from'];
					if($billing_from != "IndiaIVF"){
						$billing_from = get_center_name($billing_from);
					}
					$billing_at = get_center_name($val['billing_at']);
					$lead_arr = array($val['patient_id'], $val['wife_name'], $val['totalpackage'], $val['discounted_package'], $val['payment_done'], $val['remaining_amount'], $val['payment_method'], $billing_from, $billing_at,$val['procedure_name'] ,$val['billing_type'], date('Y-m-d H:i:s', strtotime($val['date'])), $val['status']);
					
					fputcsv($fp, $lead_arr);
				}
				fclose($fp);
				exit();
			}

			$config = array();
        	$config["base_url"] = base_url() . "accounts/package_collections";
        	$config["total_rows"] = $this->accounts_model->package_collections_count($center, $start_date, $end_date, $patient_id, $status, $payment_method);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['partialpayments_result'] = $this->accounts_model->package_collections_patination($config["per_page"], $per_page, $center, $start_date, $end_date, $patient_id, $status, $payment_method);
			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["patient_id"] = $patient_id;
			$data["status"] = $status;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/package_collections', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function add_doctor_referral()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'add_doctor_referral'){
				unset($_POST['action']);
				$post_arr['doctor_name'] = $_POST['doctor_name'];unset($_POST['doctor_name']);
				$post_arr['date'] = $_POST['date'] = date("Y-m-d H:i:s");
				$post_arr['status'] = $_POST['status']; unset($_POST['status']);
				$data = $this->accounts_model->doctor_referral_insert($post_arr);
				if($data > 0){
					header("location:" .base_url(). "accounts/add_doctor_referral?m=".base64_encode('Doctor added successfully !').'&t='.base64_encode('success'));
					die();
				}else{
					header("location:" .base_url(). "accounts/add_doctor_referral?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
					die();
				}				
			}
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/add_doctor_referral', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function doctor_referral_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){
			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$config = array();
        	$config["base_url"] = base_url() . "accounts/doctor_referral_list";
        	$config["total_rows"] = $this->accounts_model->doctor_referral_count();
        	$config["per_page"] = 40;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
        	$data["links"] = $this->pagination->create_links();
			$data['procedure_result'] = $this->accounts_model->doctor_referral_patination($config["per_page"], $per_page);
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/doctor_referral_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}
	
	public function doctor_referral_edit()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			
			if(isset($_GET['id'])){
				if(isset($_GET['id'])){ $item_id = $_GET['id']; }
				if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'update_doctor_referral'){
					unset($_POST['action']);unset($_POST['id']);
					
					$post_arr['doctor_name'] = $_POST['doctor_name'];unset($_POST['doctor_name']);
					$post_arr['status'] = $_POST['status'];unset($_POST['status']);
					
					$data = $this->accounts_model->update_doctor_referral_data($post_arr, $item_id);
					if($data > 0){
						header("location:" .base_url(). "accounts/doctor_referral_edit?m=".base64_encode('Doctor Name updated successfully !').'&t='.base64_encode('success').'&id='.$item_id);
						die();
					}else{
						header("location:" .base_url(). "accounts/doctor_referral_edit?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error').'&id='.$item_id);
						die();
					}				
				}
				$data['data'] = $this->accounts_model->get_doctor_referral($item_id);
				$template = get_header_template($logg['role']);
				$this->load->view($template['header']);
				$this->load->view('accounts/doctor_referral_edit', $data);
				$this->load->view($template['footer']);
			}else{
				header("location:" .base_url(). "procedures");
				die();
			}
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

     		
    public function purchase_order()
	{	
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/purchase_order', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}


	public function get_center_name_by_number($center_number)
	{
		$this->db->select('center_name');
		$this->db->from('hms_centers');
		$this->db->where('center_number', $center_number);
		$query = $this->db->get();
		$result = $query->row_array();
		return $result['center_name'];
	}

	public function save_purchase_order()
	{
		$logg = checklogin();
		if ($this->input->post('action') == 'add_purchase_orders') {
			$approved_by = $this->input->post('approved_by');
			$approved_by_str = !empty($approved_by) ? implode(", ", $approved_by) : null;
			$uploaded_file = null;
			$uploaded_files = $this->_handle_multiple_uploads('po_supporting_documents');
			if ($uploaded_files['status'] == 'error') {
				$this->session->set_flashdata('error', $uploaded_files['message']);
				redirect('accounts/purchase_order'); 
				return;
			}
			// if (!empty($_FILES['po_supporting_documents']['name'])) {
			// 	// Use the configured upload path from config
			// 	$dest_path = $this->config->item('upload_path');
			// 	$config['upload_path']   = $dest_path . 'purchase_orders/';
			// 	$config['allowed_types'] = 'pdf|jpg|jpeg|png|webp|gif|bmp';
			// 	$config['max_size']      = 10240; // 10 MB
			// 	$config['file_ext_tolower'] = TRUE;
			// 	$config['remove_spaces'] = TRUE;
			// 	$config['overwrite']     = FALSE;
			// 	if (!is_dir($config['upload_path'])) {
			// 		mkdir($config['upload_path'], 0777, true);
			// 	}
			// 	$this->load->library('upload', $config);
			// 	if (!$this->upload->do_upload('po_supporting_documents')) {
			// 		$error = $this->upload->display_errors('', '');
			// 		$this->session->set_flashdata('error', 'File upload failed: ' . $error);
			// 		redirect('accounts/purchase_order');
			// 		return;
			// 	}
				
			// 	$upload_data  = $this->upload->data();
			// 	$uploaded_file = $upload_data['file_name'];
			// }
			$approval_token = bin2hex(random_bytes(16));
			$created_by = null;
			if (isset($_SESSION['logged_administrator'])) {
				$created_by = $_SESSION['logged_administrator']['employee_number'];
			} elseif (isset($_SESSION['logged_accountant'])) {
				$created_by = $_SESSION['logged_accountant']['employee_number'];
			} elseif (isset($_SESSION['logged_user'])) {
				$created_by = $_SESSION['logged_user']['employee_number'];
			}
			$po_number = $this->Purchase_order_model->generate_po_number();
			$data = [
				'po_number'                          => $po_number,
				'po_centre'                          =>  $this->input->post('po_centre') ?? null,
				'po_department'                      => $this->input->post('po_department'),
				'po_nature_of_expenditure'           => $this->input->post('po_nature_of_expenditure'),
				'po_budget_head'                     => $this->input->post('po_budget_head'),
				'po_budget_item'                     => $this->input->post('po_budget_item'),
				'po_approved_by'                     => $approved_by_str,
				'po_name_of_vendor'                  => $this->input->post('po_name_of_vendor'),
				'po_remarks_or_comment_or_narration' => $this->input->post('po_remarks_or_comment_or_narration'),
				'po_basic_amount'                    => $this->input->post('po_basic_amount'),
				'po_gst_amount'                      => $this->input->post('po_gst_amount'),
				'po_other_charges_and_taxes'         => $this->input->post('po_other_charges_and_taxes'),
				'po_po_total'                        => $this->input->post('po_po_total'),
				'po_others_name'                     => $this->input->post('po_others_name'),
				// 'po_supporting_documents'            => $uploaded_file,
				'po_supporting_documents'            => json_encode($uploaded_files['filenames']),
				'approval_token'                     => $approval_token,
				'created_by'                         => $created_by,
				'created_at'                         => date('Y-m-d H:i:s'),
				'status'                             => '2',
			];
			$this->load->model('Purchase_order_model');
			$po_id = $this->Purchase_order_model->insert_purchase_order($data);
			// --- 4. Save Line Items to 'hms_purchase_order_items' ---
			$item_descriptions = $this->input->post('item_description');
			$item_quantities   = $this->input->post('quantity');
			$item_rates        = $this->input->post('rate');
			$item_gst_rates    = $this->input->post('item_gst_rate');
			$item_basic_totals = $this->input->post('item_basic_total');
			$item_gst_amounts  = $this->input->post('item_gst_amount');

			$items_batch_data = [];
			if (!empty($item_descriptions) && !empty($item_quantities) && !empty($item_rates)) {
				$item_count = count($item_descriptions);
				for ($i = 0; $i < $item_count; $i++) {
					// Check if required array elements exist before accessing them
					if (isset($item_descriptions[$i]) && !empty($item_descriptions[$i]) && 
						isset($item_quantities[$i]) && isset($item_rates[$i])) {
						
						// Get values with defaults
						$quantity = isset($item_quantities[$i]) ? floatval($item_quantities[$i]) : 0;
						$rate = isset($item_rates[$i]) ? floatval($item_rates[$i]) : 0;
						$gst_rate = isset($item_gst_rates[$i]) ? floatval($item_gst_rates[$i]) : 0;
						$basic_total = isset($item_basic_totals[$i]) ? floatval($item_basic_totals[$i]) : ($quantity * $rate);
						$gst_amount = isset($item_gst_amounts[$i]) ? floatval($item_gst_amounts[$i]) : ($basic_total * ($gst_rate / 100));
						
						// Calculate total amount (basic + GST)
						$total_amount = $basic_total + $gst_amount;
						
						$items_batch_data[] = [
							'po_id'             => $po_id,
							'item_description'  => $item_descriptions[$i],
							'quantity'          => $quantity,
							'rate'              => $rate,
							'gst_rate'          => $gst_rate,
							'gst_amount'        => $gst_amount,
							'total_amount'      => $total_amount
						];
					}
				}
			}
			if (!empty($items_batch_data) && $po_id) {
				$insert_result = $this->Purchase_order_model->add_po_items($items_batch_data);
				if (!$insert_result) {
					log_message('error', 'Failed to insert PO items for PO ID: ' . $po_id);
				}
			}
			if ($po_id) {
				$email_sent_count = 0;
				$total_approvers = count($approved_by);
				$approver_tokens = [];
				foreach ($approved_by as $approver) {
					$approver_token = bin2hex(random_bytes(16));
					$approver_tokens[] = [
						'email' => $approver,
						'token' => $approver_token,
						'status' => 'pending',
						'created_at' => date('Y-m-d H:i:s')
					];
					$email_sent = $this->_send_po_email($approver, $po_number, $approver_token, $data);
					if ($email_sent) {
						$email_sent_count++;
					}
				}
				// Store all approver tokens as JSON in the purchase order
				$this->Purchase_order_model->store_approver_tokens($po_number, $approver_tokens);
				if ($email_sent_count > 0) {
					if ($email_sent_count == $total_approvers) {
						$this->session->set_flashdata('success', "Purchase Order added successfully! Approval emails sent to all {$total_approvers} approver(s).");
					} else {
						$this->session->set_flashdata('warning', "Purchase Order added successfully! Approval emails sent to {$email_sent_count} out of {$total_approvers} approver(s).");
					}
				} else {
					$this->session->set_flashdata('warning', 'Purchase Order added successfully! However, approval emails could not be sent. Please contact administrator.');
				}
			} else {
				$this->session->set_flashdata('error', 'Failed to save Purchase Order. Please try again.');
			}
			redirect('accounts/purchase-orders-list');
		}
	}
	private function _handle_multiple_uploads($field_name)
    {
        $this->load->library('upload');
        $uploaded_filenames = [];
        if (empty($_FILES[$field_name]['name'][0])) {
            return ['status' => 'success', 'filenames' => [], 'message' => 'No files uploaded.'];
        }
        $file_count = count($_FILES[$field_name]['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $_FILES['userfile']['name']     = $_FILES[$field_name]['name'][$i];
            $_FILES['userfile']['type']     = $_FILES[$field_name]['type'][$i];
            $_FILES['userfile']['tmp_name'] = $_FILES[$field_name]['tmp_name'][$i];
            $_FILES['userfile']['error']    = $_FILES[$field_name]['error'][$i];
            $_FILES['userfile']['size']     = $_FILES[$field_name]['size'][$i];
			$dest_path = $this->config->item('upload_path');
			$config['upload_path']   = $dest_path . 'purchase_orders/';
            // $config['upload_path']   = './uploads/po_documents/'; 
            $config['allowed_types'] = 'pdf|jpg|jpeg|png|webp|gif|bmp';
            $config['max_size']      = 10240;
            $config['encrypt_name']  = TRUE; 
            $this->upload->initialize($config);
            if ($this->upload->do_upload('userfile')) {
                $upload_data = $this->upload->data();
                $uploaded_filenames[] = $upload_data['file_name'];
            } else {
                $error_message = 'Failed to upload file: ' . $_FILES['userfile']['name'] . '. Error: ' . $this->upload->display_errors('', '');
                return ['status' => 'error', 'filenames' => $uploaded_filenames, 'message' => $error_message];
            }
        }
        return ['status' => 'success', 'filenames' => $uploaded_filenames, 'message' => 'All files uploaded.'];
    }

	private function _send_po_email($approver_email, $po_number, $token, $data)
	{
		// try {
			$review_url = base_url("accounts/review_po/{$token}");
			$message = "
			<!DOCTYPE html>
			<html>
			<head>
				<meta charset='UTF-8'>
				<meta name='viewport' content='width=device-width, initial-scale=1.0'>
				<title>Purchase Order Review Required</title>
				<style>
					body { 
						font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
						line-height: 1.6; 
						color: #333; 
						background-color: #f8f9fa; 
						margin: 0; 
						padding: 20px; 
					}
					.container { 
						max-width: 600px; 
						margin: 0 auto; 
						background: white; 
						border-radius: 12px; 
						box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
						overflow: hidden; 
					}
					.header { 
						background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
						color: white; 
						padding: 30px; 
						text-align: center; 
					}
					.header h1 { 
						margin: 0 0 10px 0; 
						font-size: 28px; 
						font-weight: 300; 
					}
					.header .subtitle { 
						font-size: 16px; 
						opacity: 0.9; 
					}
					.content { 
						padding: 30px; 
					}
					.po-summary { 
						background: #f8f9fa; 
						border-radius: 8px; 
						padding: 20px; 
						margin: 20px 0; 
						border-left: 4px solid #667eea; 
					}
					.po-grid { 
						display: grid; 
						grid-template-columns: 1fr 1fr; 
						gap: 15px; 
						margin: 20px 0; 
					}
					.po-item { 
						background: white; 
						padding: 15px; 
						border-radius: 6px; 
						border: 1px solid #e9ecef; 
					}
					.po-label { 
						font-size: 12px; 
						color: #6c757d; 
						text-transform: uppercase; 
						letter-spacing: 0.5px; 
						margin-bottom: 5px; 
					}
					.po-value { 
						font-size: 16px; 
						font-weight: 600; 
						color: #333; 
					}
					.total-amount { 
						background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
						color: white; 
						padding: 20px; 
						border-radius: 8px; 
						text-align: center; 
						margin: 20px 0; 
					}
					.total-amount .amount { 
						font-size: 32px; 
						font-weight: bold; 
						margin: 10px 0; 
					}
					.action-section { 
						background: #fff3cd; 
						border: 1px solid #ffeaa7; 
						border-radius: 8px; 
						padding: 20px; 
						margin: 20px 0; 
						text-align: center; 
					}
					.action-section h3 { 
						color: #856404; 
						margin: 0 0 15px 0; 
					}
					.btn-review { 
						display: inline-block; 
						padding: 16px 32px; 
						background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); 
						color: white; 
						text-decoration: none; 
						border-radius: 8px; 
						font-weight: 600; 
						font-size: 16px; 
						box-shadow: 0 4px 15px rgba(0,123,255,0.3); 
						transition: all 0.3s ease; 
					}
					.btn-review:hover { 
						transform: translateY(-2px); 
						box-shadow: 0 6px 20px rgba(0,123,255,0.4); 
					}
					.footer { 
						background: #f8f9fa; 
						padding: 20px; 
						text-align: center; 
						color: #6c757d; 
						font-size: 14px; 
					}
					@media (max-width: 600px) {
						.po-grid { 
							grid-template-columns: 1fr; 
						}
						.container { 
							margin: 10px; 
						}
					}
				</style>
			</head>
			<body>
				<div class='container'>
					<div class='header'>
						<h1> Purchase Order Review Required</h1>
						<div class='subtitle'>New PO submitted and awaiting your approval</div>
					</div>
					
					<div class='content'>
						<div class='po-summary'>
							<h3 style='margin: 0 0 15px 0; color: #667eea;'>PO Summary</h3>
							<div class='po-grid'>
								<div class='po-item'>
									<div class='po-label'>PO Number</div>
									<div class='po-value'>{$po_number}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Centre/Cluster/Region</div>
									<div class='po-value'>{$this->get_center_name_by_number($data['po_centre'])}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Department</div>
									<div class='po-value'>{$data['po_department']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Nature of Expenditure</div>
									<div class='po-value'>{$data['po_nature_of_expenditure']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Budget Head</div>
									<div class='po-value'>{$data['po_budget_head']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Vendor</div>
									<div class='po-value'>{$data['po_name_of_vendor']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Budget Item</div>
									<div class='po-value'>{$data['po_budget_item']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Basic Amount</div>
									<div class='po-value'>{$data['po_basic_amount']}</div>
								</div>	
								<div class='po-item'>
									<div class='po-label'>GST Amount</div>
									<div class='po-value'>{$data['po_gst_amount']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Other Charges and Taxes</div>
									<div class='po-value'>{$data['po_other_charges_and_taxes']}</div>
								</div>
							</div>
						</div>
						
						<div class='total-amount'>
							<div class='po-label' style='color: rgba(255,255,255,0.8);'>Total Amount</div>
							<div class='amount'>&#8377;" . number_format($data['po_po_total'], 2) . "</div>
							<div style='font-size: 14px; opacity: 0.9;'>Including GST & All Charges</div>
						</div>
						
						<div class='action-section'>
							<h3>Action Required</h3>
							<p style='margin: 0 0 20px 0; color: #856404;'>Please review all details and make your approval decision</p>
							<a href='{$review_url}' class='btn-review' style='color: white; text-decoration: none;' >
							   Review Purchase Order Details
							</a>
						</div>
						
						<div style='background: #e9ecef; padding: 15px; border-radius: 6px; margin: 20px 0;'>
							<p style='margin: 0; font-size: 14px; color: #495057;'>
								<strong>Note:</strong> You will be able to see all form details before making your approval decision.
							</p>
						</div>
					</div>
					
					<div class='footer'>
						This is an automated message from the Purchase Order System<br>
						<small>Please do not reply to this email</small>
					</div>
				</div>
			</body>
			</html>";
			$subject = "Approval Needed: Purchase Order #{$po_number}";
			$to_email = !empty($approver_email) ? $approver_email : 'accounts@indiaivf.in';
			$sent = send_mail($to_email, $subject, $message);
			if (!$sent) {
				log_message('error', 'PO Approval Email Failed for PO: ' . $po_number);
				return false;
			}
			return true;
		// } catch (Exception $e) {
		// 	log_message('error', 'PO Email Exception: ' . $e->getMessage());
		// 	return false;
		// }
	}
	/**
	 * Send status update email to PO creator
	 */
	private function _send_po_status_update_email($po_data, $status, $status_text = null)
	{
		// echo "<pre>";
		// print_r($po_data);
		// echo "<br>";
		// print_r($status);
		// echo "<br>";
		// print_r($status_text);
		// echo "<br>";
		// die();
		try {
			if ($status_text === null) {
				// Use the status text passed from the main function
				// This ensures consistency between the approval page and email
				$status_text = 'Status Update';
			}
			// Better status color and icon mapping for email
			if ($status == '1') {
				$status_color = 'green';
				$status_icon = '✓';
			} elseif ($status == '0') {
				$status_color = 'red';
				$status_icon = '✗';
			} else {
				$status_color = '#007bff'; // Blue for "Awaiting Approval"
				$status_icon = '⏳';
			}
			
			$message = "
			<!DOCTYPE html>
			<html>
			<head>
				<meta charset='UTF-8'>
				<meta name='viewport' content='width=device-width, initial-scale=1.0'>
				<title>Purchase Order {$status_text}</title>
				<style>
					body { 
						font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
						line-height: 1.6; 
						color: #333; 
						background-color: #f8f9fa; 
						margin: 0; 
						padding: 20px; 
					}
					.container { 
						max-width: 600px; 
						margin: 0 auto; 
						background: white; 
						border-radius: 12px; 
						box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
						overflow: hidden; 
					}
					.header { 
						background: linear-gradient(135deg, " . ($status == '1' ? '#28a745 0%, #20c997 100%' : (($status == '0') ? '#dc3545 0%, #c82333 100%' : '#007bff 0%, #0056b3 100%')) . "); 
						color: white; 
						padding: 30px; 
						text-align: center; 
					}
					.header h1 { 
						margin: 0 0 10px 0; 
						font-size: 28px; 
						font-weight: 300; 
					}
					.header .subtitle { 
						font-size: 16px; 
						opacity: 0.9; 
					}
					.content { 
						padding: 30px; 
					}
					.status-badge { 
						display: inline-block; 
						padding: 8px 16px; 
						background: " . ($status == '1' ? '#28a745' : (($status == '0') ? '#dc3545' : '#007bff')) . "; 
						color: white; 
						border-radius: 20px; 
						font-weight: 600; 
						font-size: 14px; 
						margin: 10px 0; 
					}
					.po-summary { 
						background: #f8f9fa; 
						border-radius: 8px; 
						padding: 20px; 
						margin: 20px 0; 
						border-left: 4px solid " . ($status == '1' ? '#28a745' : (($status == '0') ? '#dc3545' : '#007bff')) . "; 
					}
					.po-grid { 
						display: grid; 
						grid-template-columns: 1fr 1fr; 
						gap: 15px; 
						margin: 20px 0; 
					}
					.po-item { 
						background: white; 
						padding: 15px; 
						border-radius: 6px; 
						border: 1px solid #e9ecef; 
					}
					.po-label { 
						font-size: 12px; 
						color: #6c757d; 
						text-transform: uppercase; 
						letter-spacing: 0.5px; 
						margin-bottom: 5px; 
					}
					.po-value { 
						font-size: 16px; 
						font-weight: 600; 
						color: #333; 
					}
					.total-amount { 
						background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
						color: white; 
						padding: 20px; 
						border-radius: 8px; 
						text-align: center; 
						margin: 20px 0; 
					}
					.total-amount .amount { 
						font-size: 32px; 
						font-weight: bold; 
						margin: 10px 0; 
					}
					.financial-details { 
						background: #e9ecef; 
						border-radius: 8px; 
						padding: 20px; 
						margin: 20px 0; 
					}
					.financial-grid { 
						display: grid; 
						grid-template-columns: 1fr 1fr; 
						gap: 15px; 
						margin: 15px 0; 
					}
					.financial-item { 
						background: white; 
						padding: 12px; 
						border-radius: 6px; 
						text-align: center; 
					}
					.financial-label { 
						font-size: 11px; 
						color: #6c757d; 
						text-transform: uppercase; 
						letter-spacing: 0.5px; 
						margin-bottom: 5px; 
					}
					.financial-value { 
						font-size: 18px; 
						font-weight: 600; 
						color: #333; 
					}
					.footer { 
						background: #f8f9fa; 
						padding: 20px; 
						text-align: center; 
						color: #6c757d; 
						font-size: 14px; 
					}
					@media (max-width: 600px) {
						.po-grid, .financial-grid { 
							grid-template-columns: 1fr; 
						}
						.container { 
							margin: 10px; 
						}
					}
				</style>
			</head>
			<body>
				<div class='container'>
					<div class='header'>
						<h1>Purchase Order {$status_text}</h1>
						<div class='subtitle'>Your purchase order has been <strong>{$status_text}</strong></div>
						<div class='status-badge'>{$status_text}</div>
					</div>
					
					<div class='content'>
						<div class='po-summary'>
							<h3 style='margin: 0 0 15px 0; color: " . ($status == '1' ? '#28a745' : (($status == '0') ? '#dc3545' : '#ffc107')) . ";'>PO Summary</h3>
							<div class='po-grid'>
								<div class='po-item'>
									<div class='po-label'>PO Number</div>
									<div class='po-value'>{$po_data['po_number']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Centre/Cluster/Region</div>
									<div class='po-value'>{$this->get_center_name_by_number($po_data['po_centre'])}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Department</div>
									<div class='po-value'>{$po_data['po_department']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Nature of Expenditure</div>
									<div class='po-value'>{$po_data['po_nature_of_expenditure']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Budget Head</div>
									<div class='po-value'>{$po_data['po_budget_head']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Vendor</div>
									<div class='po-value'>{$po_data['po_name_of_vendor']}</div>
								</div>
								<div class='po-item'>
									<div class='po-label'>Budget Item</div>
									<div class='po-value'>{$po_data['po_budget_item']}</div>
								</div>
							</div>
						</div>
						
						<div class='total-amount'>
							<div class='po-label' style='color: rgba(255,255,255,0.8);'>PO Total Amount</div>
							<div class='amount'>&#8377;" . number_format($po_data['po_po_total'], 2) . "</div>
							<div style='font-size: 14px; opacity: 0.9;'>Including GST & All Charges</div>
						</div>
						
						<div class='financial-details'>
							<h4 style='margin: 0 0 15px 0; color: #495057;'>Financial Breakdown</h4>
							<div class='financial-grid'>
								<div class='financial-item'>
									<div class='financial-label'>Basic Amount (Ex GST)</div>
									<div class='financial-value'>&#8377;" . number_format($po_data['po_basic_amount'], 2) . "</div>
								</div>
								<div class='financial-item'>
									<div class='financial-label'>GST Amount</div>
									<div class='financial-value'>&#8377;" . number_format($po_data['po_gst_amount'], 2) . "</div>
								</div>
								<div class='financial-item'>
									<div class='financial-label'>Other Charges & Taxes</div>
									<div class='financial-value'>&#8377;" . number_format($po_data['po_other_charges_and_taxes'], 2) . "</div>
								</div>
								<div class='financial-item'>
									<div class='financial-label'>Total Amount</div>
									<div class='financial-value' style='color: #28a745;'>&#8377;" . number_format($po_data['po_po_total'], 2) . "</div>
								</div>
							</div>
						</div>
						
						<div style='background: #e9ecef; padding: 15px; border-radius: 6px; margin: 20px 0;'>
							<h4 style='margin: 0 0 10px 0; color: #495057;'>Additional Details</h4>
							<p style='margin: 0 0 10px 0;'><strong>Remarks/Comment/Narration:</strong> {$po_data['po_remarks_or_comment_or_narration']}</p>
							<p style='margin: 0 0 10px 0;'><strong>Status Updated:</strong> " . date('Y-m-d H:i:s') . "</p>
							" . (!empty($po_data['po_supporting_documents']) ? "
							<p style='margin: 0;'><strong>Supporting Documents:</strong> <a href='" . base_url("assets/purchase_orders/{$po_data['po_supporting_documents']}") . "' target='_blank' style='color: #007bff; text-decoration: underline;'> View Document</a></p>" : "") . "
						</div>
						
						<div style='background: #d4edda; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px solid #c3e6cb;'>
							<p style='margin: 0; font-size: 14px; color: #155724;'>
								<strong>Next Steps:</strong> You can view the updated status in the Purchase Order system.
							</p>
						</div>
					</div>
					
					<div class='footer'>
						This is an automated message from the Purchase Order System<br>
						<small>Please do not reply to this email</small>
					</div>
				</div>
			</body>
			</html>";
			
			$subject = "Purchase Order #{$po_data['po_number']} - {$status_text}";
			// Get creator email from session or use default
			$creator_email = 'accounts@indiaivf.in'; // Default fallback
			$sent = send_mail($creator_email, $subject, $message);
			if (!$sent) {
				log_message('error', 'PO Status Update Email Failed for PO: ' . $po_data['po_number']);
				return false;
			}
			return true;
		} catch (Exception $e) {
			log_message('error', 'PO Status Update Email Exception: ' . $e->getMessage());
			return false;
		}
	}


    public function approve_po($token, $action)
    {
        try {
            $this->load->model('Purchase_order_model');
            
            // Get the approver token details from the JSON field
            $approver_token = $this->Purchase_order_model->get_approver_token_details($token);
            if (!$approver_token) {
                throw new Exception("Invalid or expired approval link.");
            }
            
            // Check if this approver has already responded (via dashboard or email)
            if ($approver_token['status'] !== 'pending') {
                $status_text = ucfirst($approver_token['status']);
                $status_color = ($approver_token['status'] === 'approved') ? '#28a745' : '#dc3545';
                $status_icon = ($approver_token['status'] === 'approved') ? '✓' : '✗';
                
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                echo "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset=\"UTF-8\">
                    <title>Purchase Order Already {$status_text}</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background-color: #f5f5f5; }
                        .status-box { 
                            border: 2px solid {$status_color}; 
                            border-radius: 10px; 
                            padding: 30px; 
                            margin: 20px auto; 
                            max-width: 500px;
                            background-color: #f9f9f9;
                        }
                        .status-icon { font-size: 48px; color: {$status_color}; }
                        .status-text { color: {$status_color}; font-weight: bold; font-size: 24px; }
                        .back-link { margin-top: 20px; }
                        .back-link a { 
                            color: #007bff; 
                            text-decoration: none; 
                            padding: 10px 20px; 
                            border: 1px solid #007bff; 
                            border-radius: 5px;
                        }
                        .back-link a:hover { background-color: #007bff; color: white; }
                    </style>
                </head>
                <body>
                    <div class='status-box'>
                        <div class='status-icon'>{$status_icon}</div>
                        <div class='status-text'>Purchase Order Already {$status_text}</div>
                        <p>This purchase order has already been {$approver_token['status']} by you.</p>
                        <p>You can no longer change your decision through this email link.</p>
                        <div class='back-link'>
                            <a href='" . base_url() . "'>Go to Dashboard</a>
                        </div>
                    </div>
                </body>
                </html>";
                return;
            }
            
            // Get the purchase order details using the PO number from the approver token
            $po = $this->Purchase_order_model->get_purchase_order_by_id($approver_token['po_number']);
            if (!$po) {
                throw new Exception("Purchase order not found.");
            }
            // Update the approver token status
            $approver_status = ($action == 'Approved') ? 'approved' : 'rejected';
            $approver_updated = $this->Purchase_order_model->update_approver_token_status(
                $token, 
                $approver_status, 
                $action == 'Approved' ? 'Approved by ' . $approver_token['approver_email'] : 'Rejected by ' . $approver_token['approver_email']
            );
            if (!$approver_updated) {
				throw new Exception("Failed to update approver status.");
            }
            
            // Check if all approvers have responded by parsing the JSON field
            $all_tokens = json_decode($po['approver_tokens'], true);
            if (!$all_tokens) {
                throw new Exception("Failed to parse approver tokens.");
            }
            
            $all_approved = true;
            $any_rejected = false;
            
            foreach ($all_tokens as $token_data) {
                if ($token_data['status'] === 'pending') {
                    $all_approved = false;
                } elseif ($token_data['status'] === 'rejected') {
                    $any_rejected = true;
                }
            }
            
            // Update main PO status based on approver responses
            $total_approvers = count($all_tokens);
            $approved_count = 0;
            $rejected_count = 0;
            
            foreach ($all_tokens as $token_data) {
                if ($token_data['status'] === 'approved') {
                    $approved_count++;
                } elseif ($token_data['status'] === 'rejected') {
                    $rejected_count++;
                }
            }
            // Fixed logic: Handle single approver and multiple approver scenarios properly
            if ($total_approvers == 1) {
                // Single approver scenario - direct decision
                if ($approved_count == 1) {
                    $status = '1'; // Approved
                } elseif ($rejected_count == 1) {
                    $status = '0'; // Rejected
                } else {
                    // For single approver, use status '2' to indicate "Awaiting Decision"
                    // This distinguishes it from "new PO" (status '0') and "approved/rejected"
                    $status = '2'; // Awaiting decision from single approver
                }
            } else {
                // Multiple approvers scenario - majority rule
                $majority_threshold = ceil($total_approvers / 2);
                
                if ($approved_count >= $majority_threshold) {
                    $status = '1'; // Approved (majority approved)
                } elseif ($rejected_count >= $majority_threshold) {
                    $status = '0'; // Rejected (majority rejected)
                } else {
                    $status = '2'; // Partially approved (waiting for more responses or mixed)
                }
            }
            
            // Update the main PO status
            $po_updated = $this->Purchase_order_model->update_status($po['po_number'], $status);
            if (!$po_updated) {
                throw new Exception("Failed to update PO status.");
            }
            // Fixed status text mapping based on scenario
            if ($total_approvers == 1) {
                // Single approver - clear status mapping
                if ($status == '1') {
                    $status_text = 'Approved';
                } elseif ($status == '0') {
                    $status_text = 'Rejected';
                } else {
                    $status_text = 'Awaiting Decision'; // Single approver waiting for decision
                }
            } else {
                // Multiple approvers - use standard status
                $status_text = ($status == '1') ? 'Approved' : (($status == '0') ? 'Rejected' : 'Partially Approved');
            }
            log_message('info', "PO #{$po['po_number']} has been {$status_text} by token: {$token}");
            $this->_send_po_status_update_email($po, $status, $status_text);
            // Fixed status color mapping based on scenario
            if ($total_approvers == 1 && $status == '2') {
                $status_color = '#007bff'; // Blue for "Awaiting Decision" in single approver scenario
            } else {
                $status_color = ($status == '1') ? 'green' : (($status == '0') ? 'red' : 'orange');
            }
            // Fixed icon mapping based on scenario
            if ($total_approvers == 1 && $status == '2') {
                $icon = '⏳'; // Hourglass for "Awaiting Decision" in single approver scenario
            } else {
                $icon = ($status == '1') ? '✓' : (($status == '0') ? '✗' : '⏳');
            }
            log_message('info', 'About to output HTML for PO approval');
            if (ob_get_level()) {
                ob_end_clean();
            }
            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset=\"UTF-8\">
                <title>Purchase Order " . ($status == '1' ? 'Approved' : (($status == '0') ? 'Rejected' : ($total_approvers == 1 ? 'Awaiting Decision' : 'Partially Approved'))) . "</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .status-box { 
                        border: 2px solid {$status_color}; 
                        border-radius: 10px; 
                        padding: 30px; 
                        margin: 20px auto; 
                        max-width: 500px;
                        background-color: #f9f9f9;
                    }
                    .status-icon { font-size: 48px; color: {$status_color}; }
                    .status-text { color: {$status_color}; font-weight: bold; font-size: 24px; }
                    .po-details { margin: 20px 0; text-align: left; }
                    .po-details table { width: 100%; border-collapse: collapse; margin: 10px 0; border: 1px solid #dee2e6; }
                    .po-details td { padding: 8px; border: 1px solid #dee2e6; }
                    .po-details tr:nth-child(even) { background-color: #f8f9fa; }
                    .po-details tr:first-child { background-color: #e9ecef; }
                    .po-details .total-row { background-color: #e9ecef; font-weight: bold; color: #28a745; }
                    .back-link { margin-top: 20px; }
                    .back-link a { 
                        color: #007bff; 
                        text-decoration: none; 
                        padding: 10px 20px; 
                        border: 1px solid #007bff; 
                        border-radius: 5px;
                    }
                    .back-link a:hover { background-color: #007bff; color: white; }
                </style>
            </head>
            <body>
                <div class='status-box'>
                    <div class='status-icon'>{$icon}</div>
                                         <div class='status-text'>Purchase Order " . ($status == '1' ? 'Approved' : (($status == '0') ? 'Rejected' : ($total_approvers == 1 ? 'Awaiting Decision' : 'Partially Approved'))) . "</div>
                    <div style='text-align: center; margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; font-size: 14px; color: #6c757d;'>
                        <strong>Approval Details:</strong><br>
                        Total Approvers: {$total_approvers}<br>
                        Approved: {$approved_count} | Rejected: {$rejected_count}<br>
                        Final Status: {$status_text} (Code: {$status})
                    </div>
                    <div class='po-details'>
                        <h4 style='margin-bottom: 15px; color: #333;'>Complete Purchase Order Details:</h4>
                        <table style='width: 100%; border-collapse: collapse; margin: 10px 0; border: 1px solid #dee2e6;'>
                            <tr style='background-color: #e9ecef;'>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold; width: 40%;'>PO Number:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$po['po_number']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Centre/Cluster/Region:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$this->get_center_name_by_number($po['po_centre'])}</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Department:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$po['po_department']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Nature of Expenditure:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$po['po_nature_of_expenditure']}</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Budget Head:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$po['po_budget_head']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Budget Item:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$po['po_budget_item']}</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Vendor Name:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$po['po_name_of_vendor']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Basic Amount (Ex GST):</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>&#8377;" . number_format($po['po_basic_amount'], 2) . "</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>GST Amount:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>&#8377;" . number_format($po['po_gst_amount'], 2) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Other Charges & Taxes:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>&#8377;" . number_format($po['po_other_charges_and_taxes'], 2) . "</td>
                            </tr>
                            <tr style='background-color: #e9ecef;'>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Total Amount (Inc GST & All Charges):</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold; color: #28a745;'>&#8377;" . number_format($po['po_po_total'], 2) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Remarks/Comment/Narration:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>{$po['po_remarks_or_comment_or_narration']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Status Updated:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>" . date('Y-m-d H:i:s') . "</td>
                            </tr>
                            " . (!empty($po['po_supporting_documents']) ? "
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 8px; border: 1px solid #dee2e6; font-weight: bold;'>Supporting Documents:</td>
                                <td style='padding: 8px; border: 1px solid #dee2e6;'>Attached</td>
                            </tr>" : "") . "
                        </table>
                    </div>
                    <div class='back-link'>
                        <a href='" . base_url('/') . "'>Back to Home Page</a>
                    </div>
                </div>
            </body>
            </html>";
        } catch (Exception $e) {
            log_message('error', 'PO Approval Failed: ' . $e->getMessage());
            
            // Debug output
            log_message('info', 'About to output HTML for PO error');
            
            // Ensure no output buffering issues
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset=\"UTF-8\">
                <title>Approval Error</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .error-box { 
                        border: 2px solid #dc3545; 
                        border-radius: 10px; 
                        padding: 30px; 
                        margin: 20px auto; 
                        max-width: 500px;
                        background-color: #f9f9f9;
                    }
                    .error-icon { font-size: 48px; color: #dc3545; }
                    .error-text { color: #dc3545; font-weight: bold; font-size: 24px; }
                    .back-link { margin-top: 20px; }
                    .back-link a { 
                        color: #007bff; 
                        text-decoration: none; 
                        padding: 10px 20px; 
                        border: 1px solid #007bff; 
                        border-radius: 5px;
                    }
                    .back-link a:hover { background-color: #007bff; color: white; }
                </style>
            </head>
            <body>
                <div class='error-box'>
                    <div class='error-icon'>⚠</div>
                    <div class='error-text'>Approval Error</div>
                    <p>{$e->getMessage()}</p>
                    <div class='back-link'>
                        <a href='" . base_url('accounts/purchase-orders-list') . "'>Back to Purchase Orders</a>
                    </div>
                </div>
            </body>
            </html>";
        }
    }

    public function review_po($token)
    {
        try {
            $this->load->model('Purchase_order_model');
            
            // Get the approver token details from the JSON field
            $approver_token = $this->Purchase_order_model->get_approver_token_details($token);
            if (!$approver_token) {
                throw new Exception("Invalid or expired approval link.");
            }
            
            // Check if this approver has already responded (via dashboard or email)
            if ($approver_token['status'] !== 'pending') {
                $status_text = ucfirst($approver_token['status']);
                $status_color = ($approver_token['status'] === 'approved') ? '#28a745' : '#dc3545';
                $status_icon = ($approver_token['status'] === 'approved') ? '✓' : '✗';
                
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                echo "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset=\"UTF-8\">
                    <title>Purchase Order Already {$status_text}</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background-color: #f5f5f5; }
                        .status-box { 
                            border: 2px solid {$status_color}; 
                            border-radius: 10px; 
                            padding: 30px; 
                            margin: 20px auto; 
                            max-width: 500px;
                            background-color: #f9f9f9;
                        }
                        .status-icon { font-size: 48px; color: {$status_color}; }
                        .status-text { color: {$status_color}; font-weight: bold; font-size: 24px; }
                        .back-link { margin-top: 20px; }
                        .back-link a { 
                            color: #007bff; 
                            text-decoration: none; 
                            padding: 10px 20px; 
                            border: 1px solid #007bff; 
                            border-radius: 5px;
                        }
                        .back-link a:hover { background-color: #007bff; color: white; }
                    </style>
                </head>
                <body>
                    <div class='status-box'>
                        <div class='status-icon'>{$status_icon}</div>
                        <div class='status-text'>Purchase Order Already {$status_text}</div>
                        <p>This purchase order has already been {$approver_token['status']} by you.</p>
                        <p>You can no longer change your decision through this email link.</p>
                        <div class='back-link'>
                            <a href='" . base_url() . "'>Go to Dashboard</a>
                        </div>
                    </div>
                </body>
                </html>";
                return;
            }
            
            // Get the purchase order details using the PO number from the approver token
            $po = $this->Purchase_order_model->get_purchase_order_by_id($approver_token['po_number']);
            if (!$po) {
                throw new Exception("Purchase order not found.");
            }
            
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset=\"UTF-8\">
                <title>Review Purchase Order #{$po['po_number']}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5; }
                    .container { max-width: 1000px; margin: 0 auto; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { color: #333; margin-bottom: 10px; }
                    .header .po-number { color: #007bff; font-size: 18px; font-weight: bold; }
                    .po-details { 
                        background: white; 
                        border-radius: 10px; 
                        padding: 30px; 
                        margin-bottom: 30px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    .po-details h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
                    .po-details table { width: 100%; border-collapse: collapse; margin: 10px 0; border: 1px solid #dee2e6; }
                    .po-details td { padding: 12px; border: 1px solid #dee2e6; }
                    .po-details tr:nth-child(even) { background-color: #f8f9fa; }
                    .po-details tr:first-child { background-color: #e9ecef; }
                    .po-details .total-row { background-color: #e9ecef; font-weight: bold; color: #28a745; }
                    .action-buttons { 
                        text-align: center; 
                        margin: 30px 0; 
                        padding: 20px;
                        background: white;
                        border-radius: 10px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    .btn { 
                        display: inline-block;
                        padding: 15px 30px; 
                        margin: 0 15px;
                        border: none; 
                        border-radius: 5px; 
                        font-size: 16px;
                        font-weight: bold;
                        text-decoration: none;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }
                    .btn-approve { 
                        background-color: #28a745; 
                        color: white; 
                    }
                    .btn-approve:hover { background-color: #218838; }
                    .btn-reject { 
                        background-color: #dc3545; 
                        color: white; 
                    }
                    .btn-reject:hover { background-color: #c82333; }
                    .btn-cancel { 
                        background-color: #6c757d; 
                        color: white; 
                    }
                    .btn-cancel:hover { background-color: #5a6268; }
                    .warning { 
                        background-color: #fff3cd; 
                        border: 1px solid #ffeaa7; 
                        color: #856404; 
                        padding: 15px; 
                        border-radius: 5px; 
                        margin: 20px 0;
                        text-align: center;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Purchase Order Review</h1>
                        <div class='po-number'>PO Number: {$po['po_number']}</div>
                    </div>
                    
                    <div class='po-details'>
                        <h2>Complete Purchase Order Details</h2>
                        <table>
                            <tr style='background-color: #e9ecef;'>
                                <td style='font-weight: bold; width: 40%;'>PO Number:</td>
                                <td>{$po['po_number']}</td>
                            </tr>
                            <tr>
                                <td style='font-weight: bold;'>Centre/Cluster/Region:</td>
                                <td>{$this->get_center_name_by_number($po['po_centre'])}</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='font-weight: bold;'>Department:</td>
                                <td>{$po['po_department']}</td>
                            </tr>
                            <tr>
                                <td style='font-weight: bold;'>Nature of Expenditure (Capex/Opex):</td>
                                <td>{$po['po_nature_of_expenditure']}</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='font-weight: bold;'>Budget Head:</td>
                                <td>{$po['po_budget_head']}</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='font-weight: bold;'>Name of Vendor:</td>
                                <td>{$po['po_name_of_vendor']}</td>
                            </tr>
                            <tr>
                                <td style='font-weight: bold;'>Budget Item:</td>
                                <td>{$po['po_budget_item']}</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='font-weight: bold;'>Remarks/Comment/Narration:</td>
                                <td>{$po['po_remarks_or_comment_or_narration']}</td>
                            </tr>
                            <tr>
                                <td style='font-weight: bold;'>Basic Amount (Ex GST):</td>
                                <td>&#8377;" . number_format($po['po_basic_amount'], 2) . "</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='font-weight: bold;'>GST Amount:</td>
                                <td>&#8377;" . number_format($po['po_gst_amount'], 2) . "</td>
                            </tr>
                            <tr>
                                <td style='font-weight: bold;'>Other Charges & Taxes:</td>
                                <td>&#8377;" . number_format($po['po_other_charges_and_taxes'], 2) . "</td>
                            </tr>
                            <tr style='background-color: #e9ecef;'>
                                <td style='font-weight: bold;'>PO Total (Inc GST & All Charges):</td>
                                <td style='font-weight: bold; color: #28a745;'>&#8377;" . number_format($po['po_po_total'], 2) . "</td>
                            </tr>
                            " . (!empty($po['po_supporting_documents']) ? "
                            <tr style='background-color: #f8f9fa;'>
                                <td style='font-weight: bold;'>Supporting Documents:</td>
                                <td><a href='" . base_url("assets/purchase_orders/{$po['po_supporting_documents']}") . "' target='_blank' style='color: #007bff; text-decoration: underline;'> View Document</a></td>
                            </tr>" : "") . "
                        </table>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Important:</strong> Please review all details carefully before making your decision. 
                        This action cannot be undone.
                    </div>
                    
                    <div class='action-buttons'>
                        <a href='" . base_url("accounts/approve_po/{$token}/Approved") . "' class='btn btn-approve' onclick='return confirm(\"Are you sure you want to APPROVE this Purchase Order?\")'>
                            ✓ Approve Purchase Order
                        </a>
                        <a href='" . base_url("accounts/approve_po/{$token}/Rejected") . "' class='btn btn-reject' onclick='return confirm(\"Are you sure you want to REJECT this Purchase Order?\")'>
                            ✗ Reject Purchase Order
                        </a>
                        <a href='" . base_url('/') . "' class='btn btn-cancel'>
                            Cancel
                        </a>
                    </div>
                </div>
            </body>
            </html>";
            
        } catch (Exception $e) {
            log_message('error', 'PO Review Failed: ' . $e->getMessage());
            
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset=\"UTF-8\">
                <title>Review Error</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .error-box { 
                        border: 2px solid #dc3545; 
                        border-radius: 10px; 
                        padding: 30px; 
                        margin: 20px auto; 
                        max-width: 500px;
                        background-color: #f9f9fa;
                    }
                    .error-icon { font-size: 48px; color: #dc3545; }
                    .error-text { color: #dc3545; font-weight: bold; font-size: 24px; }
                    .back-link { margin-top: 20px; }
                    .back-link a { 
                        color: #007bff; 
                        text-decoration: none; 
                        padding: 10px 20px; 
                        border: 1px solid #007bff; 
                        border-radius: 5px;
                    }
                    .back-link a:hover { background-color: #007bff; color: white; }
                </style>
            </head>
            <body>
                <div class='error-box'>
                    <div class='error-icon'>⚠</div>
                    <div class='error-text'>Review Error</div>
                    <p>{$e->getMessage()}</p>
                    <div class='back-link'>
                        <a href='" . base_url('/') . "'>Back to Home Page</a>
                    </div>
                </div>
            </body>
            </html>";
        }
    }
    
    public function purchase_order_list()
	{
		$this->load->model('Purchase_order_model');
		$filters = [
			'status'                  => $this->input->get('status'),
			'start_date'              => $this->input->get('start_date'),
			'end_date'                => $this->input->get('end_date'),
			'po_centre'               => $this->input->get('po_centre'),
			'po_department'           => $this->input->get('po_department'),
			'po_nature_of_expenditure'=> $this->input->get('po_nature_of_expenditure'),
			'approval_status'         => $this->input->get('approval_status'),
		];
		$per_page = $this->input->get('per_page', true) ?: 0;
		$config["base_url"] = base_url("accounts/purchase-orders-list");
		$config["total_rows"] = $this->Purchase_order_model->purchase_order_count($filters);
		$config["per_page"] = 20;
		$config["page_query_string"] = true;
		$this->pagination->initialize($config);
		$data["links"] = $this->pagination->create_links();
		$data['purchase_order_result'] = $this->Purchase_order_model->purchase_order_pagination($config["per_page"], $per_page, $filters);
		$data['filters'] = $filters; // send back to view
		
		// Check GRN status for each PO
		$this->load->model('Grn_model');
		$grn_status = [];
		foreach ($data['purchase_order_result'] as $po) {
			$grn_status[$po['po_number']] = $this->Grn_model->grn_exists_for_po($po['po_number']);
		}
		$data['grn_status'] = $grn_status;
		
		$template = get_header_template(checklogin()['role']);
		$data['user_role'] =checklogin()['role'];
		$this->load->view($template['header']);
		$this->load->view('accounts/purchase_order_list', $data);
		$this->load->view($template['footer']);
	}

	public function update_status()
	{
		$this->load->model('Purchase_order_model');
		$id     = $this->input->post('id');
		$status = $this->input->post('status');
		if (empty($id) || !in_array($status, ['0', '1'])) {
			return $this->output->set_content_type('application/json')
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => 'Invalid request!']));
		}
		
		$updated = $this->Purchase_order_model->update_status($id, $status);
		if ($updated) {
			$message = 'Purchase Order status updated successfully!';
		} else {
			$message = 'Failed to update status. Please try again.';
		}
		return $this->output->set_content_type('application/json')
			->set_status_header(200)
			->set_output(json_encode(['status' => $updated ? 'success' : 'error', 'message' => $message]));
	}

	public function bulk_update_status()
	{
		$this->load->model('Purchase_order_model');
		$ids    = $this->input->post('ids');
		$status = $this->input->post('status');
		$remarks = $this->input->post('remarks');
		
		if (empty($ids) || !is_array($ids) || !in_array($status, ['0', '1'])) {
			return $this->output->set_content_type('application/json')
				->set_status_header(400)
				->set_output(json_encode(['status' => 'error', 'message' => 'Invalid request!']));
		}
		
		$success_count = 0;
		$total_count = count($ids);
		
		foreach ($ids as $id) {
			if ($this->Purchase_order_model->update_status($id, $status, $remarks)) {
				$success_count++;
			}
		}
		
		if ($success_count == $total_count) {
			$message = "All {$total_count} purchase order(s) updated successfully!";
			$response_status = 'success';
		} elseif ($success_count > 0) {
			$message = "{$success_count} out of {$total_count} purchase order(s) updated successfully!";
			$response_status = 'warning';
		} else {
			$message = 'Failed to update any purchase orders. Please try again.';
			$response_status = 'error';
		}
		
		return $this->output->set_content_type('application/json')
			->set_status_header(200)
			->set_output(json_encode(['status' => $response_status, 'message' => $message]));
	}

	public function purchase_order_payment($po_number)
	{
		$this->load->model('Purchase_order_model');
		$purchase_order = $this->Purchase_order_model->get_purchase_order_by_id($po_number);
		$data['purchase_order'] = $purchase_order;
		$template = get_header_template(checklogin()['role']);
		$this->load->view($template['header']);
		$this->load->view('accounts/purchase_order_payment', $data);
		$this->load->view($template['footer']);
	}

	public function save_payment_purchase_order()
	{
		$po_number   = $this->input->post('po_number');
		$amount_paid = $this->input->post('amount_paid');
		$user_id     = $this->session->userdata['logged_administrator']['employee_number'];
		$purchase_order = $this->Purchase_order_model->get_purchase_order_by_id($po_number);
		if (!$purchase_order) {
			$this->session->set_flashdata('error', 'Invalid Purchase Order Number');
			redirect('accounts/purchase-orders-list');
			return;
		}
		// Validate amount paid
		if ($amount_paid <= 0) {
			$this->session->set_flashdata('error', 'Amount Paid must be greater than 0!');
			redirect('accounts/purchase-orders-list');
			return;
		}
		
		// Allow overpayments but warn if excessive
		if ($amount_paid > $purchase_order['po_po_total'] * 1.5) {
			$this->session->set_flashdata('error', 'Overpayment amount is too high (max 50% over PO total)!');
			redirect('accounts/purchase-orders-list');
			return;
		}
		$uploaded_file = null;
		if (!empty($_FILES['payment_proof']['name'])) {
			$dest_path = $this->config->item('upload_path');
			$config['upload_path']   = $dest_path . 'purchase_orders/';
			$config['allowed_types'] = 'pdf|jpg|jpeg|png|webp|gif|bmp';
			$config['max_size']      = 10240; // 10 MB
			$config['file_ext_tolower'] = TRUE;
			$config['remove_spaces'] = TRUE;
			$config['overwrite']     = FALSE;
			if (!is_dir($config['upload_path'])) {
				mkdir($config['upload_path'], 0777, true);
			}
			$this->load->library('upload', $config);
			if (!$this->upload->do_upload('payment_proof')) {
				$error = $this->upload->display_errors('', '');
				$this->session->set_flashdata('error', 'File upload failed: ' . $error);
				redirect('accounts/purchase-orders-list');
				return;
			}
			$upload_data   = $this->upload->data();
			$uploaded_file = 'purchase_orders/' . $upload_data['file_name'];
		}
		$balance = $purchase_order['po_po_total'] - $amount_paid;
		$payment_status = '';
		if ($balance < 0) {
			$payment_status = 'overpaid';
		} elseif ($balance == 0) {
			$payment_status = 'fully_paid';
		} else {
			$payment_status = 'partial';
		}
		$payment_data = [
			'po_number'       => $po_number,
			'user_id'         => $user_id,
			'amount_paid'     => $amount_paid,
			'balance'  => $balance,
			'payment_status'  => $payment_status,
			'transaction_img' => $uploaded_file,
		];
		$this->Purchase_order_model->save_purchase_order_payment($payment_data);
		$this->Purchase_order_model->update_purchase_order($po_number, $amount_paid);
		if ($balance < 0) {
			$message = 'Payment saved successfully! Overpayment of &#8377;' . number_format(abs($balance), 2) . ' recorded as credit.';
		} elseif ($balance == 0) {
			$message = 'Payment saved successfully! Purchase Order is now fully paid.';
		} else {
			$message = 'Payment saved successfully! Remaining balance: &#8377;' . number_format($balance, 2);
		}
		
		// Set flash message instead of URL parameters
		$this->session->set_flashdata('success', $message);
		redirect('accounts/purchase-orders-list');
	}

	/**
	 * Dashboard approval for logged-in users
	 * Allows users to approve/reject POs they are assigned to approve
	 */
	public function dashboard_approve_po($po_number, $action)
	{
		$logg = checklogin();
		if($logg['status'] != true){
			header("location:" .base_url(). "");
			die();
		}

		try {
			$this->load->model('Purchase_order_model');
			
			// Get current user's email from session
			$user_email = $this->_get_current_user_email();
			if (!$user_email) {
				throw new Exception("User email not found in session.");
			}
			
			// Get the purchase order details
			$po = $this->Purchase_order_model->get_purchase_order_by_id($po_number);
			if (!$po) {
				throw new Exception("Purchase order not found.");
			}
			
			// Check if user is assigned as an approver for this PO
			if (empty($po['approver_tokens'])) {
				throw new Exception("No approvers assigned to this purchase order.");
			}
			
			$approver_tokens = json_decode($po['approver_tokens'], true);
			if (!$approver_tokens) {
				throw new Exception("Invalid approver data.");
			}
			
			// Find the user's token in the approver list
			$user_token = null;
			foreach ($approver_tokens as $token_data) {
				if ($token_data['email'] === $user_email) {
					$user_token = $token_data['token'];
					break;
				}
			}
			
			if (!$user_token) {
				throw new Exception("You are not authorized to approve this purchase order.");
			}
			
			// Check if user has already responded
			$user_token_data = $this->Purchase_order_model->get_approver_token_details($user_token);
			if (!$user_token_data) {
				throw new Exception("Invalid approval token.");
			}
			
			if ($user_token_data['status'] !== 'pending') {
				throw new Exception("You have already responded to this purchase order.");
			}
			
			// Process the approval using existing logic
			$approver_status = ($action == 'approve') ? 'approved' : 'rejected';
			$approver_updated = $this->Purchase_order_model->update_approver_token_status(
				$user_token, 
				$approver_status, 
				$action == 'approve' ? 'Approved via dashboard by ' . $user_email : 'Rejected via dashboard by ' . $user_email
			);
			
			if (!$approver_updated) {
				throw new Exception("Failed to update approval status.");
			}
			
			// Update main PO status using existing logic
			$all_tokens = json_decode($po['approver_tokens'], true);
			$total_approvers = count($all_tokens);
			$approved_count = 0;
			$rejected_count = 0;
			
			foreach ($all_tokens as $token_data) {
				if ($token_data['status'] === 'approved') {
					$approved_count++;
				} elseif ($token_data['status'] === 'rejected') {
					$rejected_count++;
				}
			}
			
			// Determine final status
			if ($total_approvers == 1) {
				$status = ($approved_count == 1) ? '1' : (($rejected_count == 1) ? '0' : '2');
			} else {
				$majority_threshold = ceil($total_approvers / 2);
				if ($approved_count >= $majority_threshold) {
					$status = '1';
				} elseif ($rejected_count >= $majority_threshold) {
					$status = '0';
				} else {
					$status = '2';
				}
			}
			
			// Update the main PO status
			$po_updated = $this->Purchase_order_model->update_status($po['po_number'], $status);
			if (!$po_updated) {
				throw new Exception("Failed to update PO status.");
			}
			
			// Send status update email
			$status_text = ($status == '1') ? 'Approved' : (($status == '0') ? 'Rejected' : 'Partially Approved');
			$this->_send_po_status_update_email($po, $status, $status_text);
			
			// Set success message
			$action_text = ($action == 'approve') ? 'approved' : 'rejected';
			$this->session->set_flashdata('success', "Purchase Order #{$po_number} has been {$action_text} successfully!");
			
		} catch (Exception $e) {
			log_message('error', 'Dashboard PO Approval Failed: ' . $e->getMessage());
			$this->session->set_flashdata('error', 'Error: ' . $e->getMessage());
		}
		
		redirect('accounts/purchase_order_list');
	}

	/**
	 * Get current user's email from session
	 */
	private function _get_current_user_email()
	{
		$logg = checklogin();
		if (!$logg['status']) {
			return null;
		}

		$role = $logg['role'];
		$session_key = 'logged_' . $role;
		
		if (isset($_SESSION[$session_key]['email'])) {
			return $_SESSION[$session_key]['email'];
		}
		
		return null;
	}

	/**
	 * Get all users for filter dropdown - only users involved in PO approvals
	 */
	private function get_all_users()
	{
		$users = [];
		
		try {
			// Get users from approver_tokens in purchase orders (only users who have been involved in PO approvals)
			$this->db->select('approver_tokens');
			$this->db->from('hms_purchase_orders');
			$this->db->where('approver_tokens IS NOT NULL');
			$this->db->where('approver_tokens !=', '');
			$query = $this->db->get();
			
			$seen_emails = [];
			foreach ($query->result_array() as $row) {
				$tokens = json_decode($row['approver_tokens'], true);
				if ($tokens) {
					foreach ($tokens as $token_data) {
						if (!empty($token_data['email']) && !in_array($token_data['email'], $seen_emails)) {
							// Try to get the user's name from employees table
							$name = $this->get_user_name_by_email($token_data['email']);
							
							$users[] = [
								'name' => $name ? $name : $token_data['email'], // Use actual name if found, otherwise use email
								'email' => $token_data['email']
							];
							$seen_emails[] = $token_data['email'];
						}
					}
				}
			}
		} catch (Exception $e) {
			log_message('error', 'Failed to get users from purchase orders: ' . $e->getMessage());
		}
		
		// Sort by name
		usort($users, function($a, $b) {
			return strcmp($a['name'], $b['name']);
		});
		
		return $users;
	}

	/**
	 * Get user name by email from employees table
	 */
	private function get_user_name_by_email($email)
	{
		try {
			$query = $this->db->select('name')
				->from('hms_employees')
				->where('email', $email)
				->limit(1)
				->get();
			
			if ($query->num_rows() > 0) {
				$result = $query->row_array();
				return $result['name'];
			}
		} catch (Exception $e) {
			log_message('error', 'Failed to get user name for email ' . $email . ': ' . $e->getMessage());
		}
		
		return null;
	}

	/**
	 * Check pending orders for any specific user
	 */
	public function check_user_pending_orders()
	{
		$user_email = $this->input->get('user_email');
		
		if (empty($user_email)) {
			$this->output->set_content_type('application/json')
				->set_status_header(400)
				->set_output(json_encode([
					'status' => 'error', 
					'message' => 'User email is required'
				]));
			return;
		}
		
		$this->load->model('Purchase_order_model');
		$pending_orders = $this->Purchase_order_model->get_pending_orders_for_user($user_email);
		$pending_count = count($pending_orders);
		
		$this->output->set_content_type('application/json')
			->set_status_header(200)
			->set_output(json_encode([
				'status' => 'success',
				'user_email' => $user_email,
				'pending_count' => $pending_count,
				'pending_orders' => $pending_orders
			]));
	}

	/**
	 * Check user approval statistics - standalone feature
	 */
	public function user_approval_stats()
	{
		$logg = checklogin();
		if($logg['status'] != true){
			header("location:" .base_url(). "");
			die();
		}

		$data = array();
		$data['user_stats'] = null;
		$data['searched_user'] = '';
		$current_user_email = $this->_get_current_user_email();
		$filters = [
			'status_filter' => $this->input->get('status_filter'),
			'po_number' => $this->input->get('po_number')
		];
		$data['filters'] = $filters;
		$search_user = $this->input->get('user_email');
		if (empty($search_user) && !empty($current_user_email)) {
			$search_user = $current_user_email;
		}
		if (!empty($search_user)) {
			$this->load->model('Purchase_order_model');
			$data['user_stats'] = $this->Purchase_order_model->get_user_approval_stats($search_user, $filters);
			$data['searched_user'] = $search_user;
		}
		$data['all_users'] = $this->get_all_users();
		$data['current_user_email'] = $current_user_email;
		$template = get_header_template($logg['role']);
		$data['user_role'] = $logg['role'];
		$this->load->view($template['header']);
		$this->load->view('accounts/user_approval_stats', $data);
		$this->load->view($template['footer']);
	}

	/**
	 * Debug method to check database tables
	 */
	public function debug_users()
	{
		$logg = checklogin();
		if($logg['status'] != true){
			header("location:" .base_url(). "");
			die();
		}

		echo "<h3>Debug: User Tables Check</h3>";
		
		// Check hms_employees table
		echo "<h4>hms_employees table:</h4>";
		try {
			$query = $this->db->select('name, email, username, status')
				->from('hms_employees')
				->limit(10)
				->get();
			
			echo "<p>Total rows: " . $query->num_rows() . "</p>";
			if ($query->num_rows() > 0) {
				echo "<table border='1'><tr><th>Name</th><th>Email</th><th>Username</th><th>Status</th></tr>";
				foreach ($query->result_array() as $row) {
					echo "<tr><td>{$row['name']}</td><td>{$row['email']}</td><td>{$row['username']}</td><td>{$row['status']}</td></tr>";
				}
				echo "</table>";
			}
		} catch (Exception $e) {
			echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
		}
		
		// Check purchase orders for approver tokens
		echo "<h4>Purchase Orders with Approver Tokens:</h4>";
		try {
			$query = $this->db->select('po_number, approver_tokens')
				->from('hms_purchase_orders')
				->where('approver_tokens IS NOT NULL')
				->where('approver_tokens !=', '')
				->limit(5)
				->get();
			
			echo "<p>Total POs with tokens: " . $query->num_rows() . "</p>";
			foreach ($query->result_array() as $row) {
				echo "<p><strong>PO:</strong> {$row['po_number']}<br>";
				echo "<strong>Tokens:</strong> " . htmlspecialchars($row['approver_tokens']) . "</p>";
			}
		} catch (Exception $e) {
			echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
		}
		
		// Test get_all_users method
		echo "<h4>get_all_users() method result (PO Approvers Only):</h4>";
		$users = $this->get_all_users();
		echo "<p>Found " . count($users) . " users who have been involved in PO approvals</p>";
		foreach ($users as $user) {
			echo "<p>{$user['name']} - {$user['email']}</p>";
		}
	}

	/**
	 * Get pending approvals for current user
	 */
	public function get_pending_approvals()
	{
		$logg = checklogin();
		if($logg['status'] != true){
			header("location:" .base_url(). "");
			die();
		}

		$user_email = $this->_get_current_user_email();
		if (!$user_email) {
			return [];
		}

		$this->load->model('Purchase_order_model');
		
		// Get all POs where user is an approver and status is pending
		$this->db->select('*');
		$this->db->from('hms_purchase_orders');
		$this->db->like('approver_tokens', $user_email);
		$this->db->where('status', '2'); // Pending status
		$query = $this->db->get();
		$all_pos = $query->result_array();
		
		$pending_approvals = [];
		foreach ($all_pos as $po) {
			if (!empty($po['approver_tokens'])) {
				$approver_tokens = json_decode($po['approver_tokens'], true);
				if ($approver_tokens) {
					foreach ($approver_tokens as $token_data) {
						if ($token_data['email'] === $user_email && $token_data['status'] === 'pending') {
							$pending_approvals[] = $po;
							break;
						}
					}
				}
			}
		}
		
		return $pending_approvals;
	}

	/**
	 * My Approvals page - shows POs pending user's approval
	 */
	public function my_approvals()
	{
		$logg = checklogin();
		if($logg['status'] != true){
			header("location:" .base_url(). "");
			die();
		}

		$user_email = $this->_get_current_user_email();
		if (!$user_email) {
			$this->session->set_flashdata('error', 'User email not found in session.');
			redirect('dashboard');
		}

		// Get pending approvals for current user
		$pending_approvals = $this->get_pending_approvals();
		
		$data = array();
		$data['pending_approvals'] = $pending_approvals;
		$data['user_email'] = $user_email;
		
		$template = get_header_template($logg['role']);
		$this->load->view($template['header']);
		$this->load->view('accounts/my_approvals', $data);
		$this->load->view($template['footer']);
	}
   
	public function daily_sales_reporting(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			//$per_page = $this->input->get('per_page', true);
			$center_number = $this->input->get('billing_at', true);
			$payment_method = $this->input->get('payment_method', true);
			$start_date = $this->input->get('start_date', true);
			$end_date = $this->input->get('end_date', true);
			
			$config = array();
        	$data['medicine_daily_result'] = $this->accounts_model->dashboard_medicine_daily_sales($center, $start_date, $end_date);
			$data['investigations_daily_result'] = $this->accounts_model->dashboard_investigation_daily_sales($center, $start_date, $end_date);
			$data['advance_daily_result'] = $this->accounts_model->dashboard_advance_daily_sales($center, $start_date, $end_date);
			$data['consultation_daily_result'] = $this->accounts_model->dashboard_consultation_daily_sales($center, $start_date, $end_date);
			$data['registration_daily_result'] = $this->accounts_model->dashboard_registration_daily_sales($center, $start_date, $end_date);
			$data['procedure_daily_result'] = $this->accounts_model->dashboard_procedure_daily_sales($center, $start_date, $end_date);
			$data['partial_daily_result'] = $this->accounts_model->dashboard_partial_daily_sales($center, $start_date, $end_date);
			$data['patient_procedure_daily_result'] = $this->accounts_model->dashboard_procedure_reports_list_patination($center, $start_date, $end_date);
			$data['patient_partial_daily_result'] = $this->accounts_model->dashboard_partial_daily_sales_report($center, $start_date, $end_date);
			$data['patient_medicine_daily_result'] = $this->accounts_model->dashboard_medicine_reports_list_patination($center, $start_date, $end_date);
			$data['patient_diagnostic_daily_result'] = $this->accounts_model->dashboard_diagnostic_reports_list_patination($center, $start_date, $end_date);
			$data['patient_consultation_daily_result'] = $this->accounts_model->dashboard_consultation_reports_list_patination($center, $start_date, $end_date);
			$data['patient_registration_daily_result'] = $this->accounts_model->dashboard_registration_daily_result_patination($center, $start_date, $end_date);
			$data['patient_advance_daily_result'] = $this->accounts_model->dashboard_advance_reports_list_patination($center, $start_date, $end_date);
			

			$data["billing_at"] = $center;
			$data["start_date"] = $start_date;
			$data["end_date"] = $end_date;
			$data["payment_method"] = $payment_method;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/daily_sales_reporting', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

// 1. SAVE DRAFT (For Billing Manager)
public function save_draft_report() {
    $logg = checklogin();
    if(!$logg['status']) { echo json_encode(['success'=>false, 'message'=>'Login required']); return; }

    // Prepare Data
    $data = $this->_get_post_report_data(); // Helper function below
    $data['approval_status'] = 'Pending';   // Set status to Pending
    
    if ($this->accounts_model->save_daily_sales_report($data)) {
        echo json_encode(['success' => true, 'message' => 'Report saved as Draft (Pending Approval).']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database Error. Could not save.']);
    }
}

// 2. APPROVE & SEND (For Counsellor)
public function approve_and_send_email() {
    $logg = checklogin();
    
    // Security Check: Only Counsellors can approve
    if (!isset($_SESSION['logged_counselor'])) {
        echo json_encode(['success' => false, 'message' => 'Permission Denied: Only Counsellors can approve.']);
        return;
    }

    // A. Save/Update the Report as APPROVED
    $data = $this->_get_post_report_data();
    $data['approval_status'] = 'Approved';
    $data['approved_by']     = $_SESSION['logged_counselor']['name'];
    $data['approved_at']     = date('Y-m-d H:i:s');

    if (!$this->accounts_model->save_daily_sales_report($data)) {
        echo json_encode(['success' => false, 'message' => 'Database Error during approval.']);
        return;
    }

    // B. Send the Email (Now that it is approved)
    $recipient_email = $this->input->post('recipient_email');
    $email_subject   = $this->input->post('email_subject');
    
    // Get data for email view
    $email_data['report_data']  = $_POST; // Use POST data for the view logic
    $email_data['details_html'] = urldecode($this->input->post('details_html'));

    $email_body = $this->load->view('emails/daily_report_template', $email_data, TRUE);

    $this->load->library('email');
    $this->email->from('ranjeet.kumar@indiaivf.in', 'IndiaIVF Reports'); // Use your config email
    $this->email->to($recipient_email);
    $this->email->subject("APPROVED: " . $email_subject); // Add APPROVED prefix
    $this->email->message($email_body);
    $this->email->set_mailtype("html");

    if ($this->email->send()) {
        echo json_encode(['success' => true, 'message' => 'Report Approved, Saved, and Email Sent!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Approved & Saved, but Email Failed: ' . $this->email->print_debugger()]);
    }
}

// Helper to collect POST data (prevents repeating code)
private function _get_post_report_data() {
    $center = isset($_SESSION['logged_billing_manager']['center']) ? $_SESSION['logged_billing_manager']['center'] : $_SESSION['logged_counselor']['center'];
    return array(
        'report_date' => $this->input->post('start_date') ? $this->input->post('start_date') : date('Y-m-d'),
        'center'      => $center,
        'ivf_cycles_sold_c_count' => $this->input->post('ivf_cycles_sold_c_count'),
        'ivf_cycles_sold_b_count' => $this->input->post('ivf_cycles_sold_b_count'),
        'ivf_cycles_sold_amount'  => $this->input->post('ivf_cycles_sold_amount'),
        'ivf_with_bed_c_count' => $this->input->post('ivf_with_bed_c_count'),
        'ivf_with_bed_b_count' => $this->input->post('ivf_with_bed_b_count'),
        'ivf_with_bed_amount'  => $this->input->post('ivf_with_bed_amount'),
        'non_ivf_with_bed_c_count' => $this->input->post('non_ivf_with_bed_c_count'),
        'non_ivf_with_bed_b_count' => $this->input->post('non_ivf_with_bed_b_count'),
        'non_ivf_with_bed_amount'  => $this->input->post('non_ivf_with_bed_amount'),
        'non_ivf_without_bed_c_count' => $this->input->post('non_ivf_without_bed_c_count'),
        'non_ivf_without_bed_b_count' => $this->input->post('non_ivf_without_bed_b_count'),
        'non_ivf_without_bed_amount'  => $this->input->post('non_ivf_without_bed_amount'),
        'not_tagged_c_count' => $this->input->post('not_tagged_c_count'),
        'not_tagged_b_count' => $this->input->post('not_tagged_b_count'),
        'not_tagged_amount'  => $this->input->post('not_tagged_amount'),
        'package_customer_count' => $this->input->post('package_customer_count'),
        'package_bill_count'     => $this->input->post('package_bill_count'),
        'package_amount'         => $this->input->post('package_amount'),
        'medicine_customer_count' => $this->input->post('medicine_customer_count'),
        'medicine_bill_count'     => $this->input->post('medicine_bill_count'),
        'medicine_amount'         => $this->input->post('medicine_amount'),
        'diagnosis_customer_count' => $this->input->post('diagnosis_customer_count'),
        'diagnosis_bill_count'     => $this->input->post('diagnosis_bill_count'),
        'diagnosis_amount'         => $this->input->post('diagnosis_amount'),
        'consultation_customer_count' => $this->input->post('consultation_customer_count'),
        'consultation_bill_count'     => $this->input->post('consultation_bill_count'),
        'consultation_amount'         => $this->input->post('consultation_amount'),
    );
}	

public function get_doctors_by_center() {
    // Enable CORS if needed
    header('Content-Type: application/json');
    
    $logg = checklogin();
    if ($logg['status'] != true) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }
    
    // Get center_id from POST
    $center_id = $this->input->post('center_id');
    
    // Debug: Log the received data
    error_log("Received center_id: " . $center_id);
    
    if (empty($center_id)) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Center ID required',
            'received_data' => $_POST // Debug info
        ]);
        exit;
    }
    
    // Load your model
    $this->load->model('accounts_model');
    
    try {
        $doctors = $this->accounts_model->get_doctors_by_center($center_id);
        
        if ($doctors && count($doctors) > 0) {
            echo json_encode([
                'status' => 'success',
                'doctors' => $doctors,
                'count' => count($doctors)
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'doctors' => [],
                'message' => 'No doctors found for this center',
                'center_id' => $center_id // Debug info
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
    exit;
}

public function save_advance_payment()
	{
		$logg = checklogin();
		if($logg['status'] == true){
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'save_advance_payment'){
				unset($_POST['action']);
				$data = $this->accounts_model->advance_payment_insert($_POST);
				if($data > 0){
					header("location:" .base_url(). "accounts/save_advance_payment?m=".base64_encode('Item added successfully !').'&t='.base64_encode('success'));
					die();
				}else{
					header("location:" .base_url(). "accounts/save_advance_payment?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
					die();
				}				
			}
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/save_advance_payment', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}


	public function advance_payment_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$training_name = $this->input->get('training_name', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/advance_payment_list";
        	$config["total_rows"] = $this->accounts_model->advance_payment_count($training_name);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['consultation_cancel_result'] = $this->accounts_model->advance_payment_pagination($config["per_page"], $per_page, $training_name);
			$data["training_name"] = $training_name;
			
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/advance_payment_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}	


public function send_daily_report_email()
{
    // 1. Get inputs
    $raw_recipients = $this->input->post('recipient_email');
    $email_subject  = $this->input->post('email_subject');

    // 2. Prepare Data & View (Do this ONCE to save performance)
    $data['report_data']  = $_POST;
    $data['details_html'] = urldecode($this->input->post('details_html'));
    
    // Generate the HTML body once
    $email_body = $this->load->view('emails/daily_report_template', $data, TRUE);

    // 3. Process Recipients
    // Split the string by comma into an array
    $recipient_list = explode(',', $raw_recipients);
    
    $sent_count = 0;
    $failed_emails = [];

    // 4. Loop through each email and send individually
    foreach ($recipient_list as $email) {
        $email = trim($email); // Remove spaces (Important!)
        
        if (!empty($email)) {
            // Send the email to this specific user
            $result = send_mail($email, $email_subject, $email_body);
            
            if ($result) {
                $sent_count++;
            } else {
                $failed_emails[] = $email;
            }
        }
    }

    // 5. Return Response
    if ($sent_count > 0) {
        $response = [
            'success'   => true,
            'message'   => "Report sent to $sent_count recipients.",
            'recipient' => $raw_recipients,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Failed to send email to any recipient.',
            'failed'  => $failed_emails
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}

/*
// Add this helper method to get patient names
private function get_patient_name($patient_id) {
    // Implement your patient name lookup logic here
    // This should match your existing get_patient_name method
    // Example: return $this->Your_model->get_patient_name_by_id($patient_id);
    return "Patient Name"; // Replace with actual implementation
}*/
    /**
     * Handles the export of purchase orders to a CSV file.
     * It uses the same filters as the purchase_order_list page.
     */
    public function export_purchase_orders()
    {
        // Check for login, permissions, etc. if necessary
        // if (!checklogin()['status']) {
        //     redirect('login');
        // }

        $this->load->model('Purchase_order_model');
        $filters = [
            'status'                   => $this->input->get('status'),
            'start_date'               => $this->input->get('start_date'),
            'end_date'                 => $this->input->get('end_date'),
            'po_centre'                => $this->input->get('po_centre'),
            'po_department'            => $this->input->get('po_department'),
            'po_nature_of_expenditure' => $this->input->get('po_nature_of_expenditure'),
            'approval_status'          => $this->input->get('approval_status'), // Kept for completeness
        ];
        $purchase_orders = $this->Purchase_order_model->get_all_purchase_orders($filters);
        $filename = "purchase_orders_export_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        $headers = [
            'PO Number',
            'Centre',
            'Department',
            'Nature Of Expenditure',
            'Budget Head',
            'Budget Item',
            'Vendor',
            'Basic Amount (Ex GST)',
            'GST Amount',
            'Other Charges & Taxes',
            'PO Total (Inc GST)',
            'Approval Status',
            'Approvers',
            'Final Status (Manual)'
        ];
        fputcsv($output, $headers);
        if (!empty($purchase_orders)) {
            foreach ($purchase_orders as $po) {
                $approval_status_info = $this->_get_approval_status_summary($po['approver_tokens']);
                $approvers_list = $this->_get_approvers_list($po['approver_tokens']);
                $manual_status = $this->_get_manual_status_text($po['status']);

                $row = [
                    $po['po_number'],
                    $po['po_centre'],
                    $po['po_department'],
                    $po['po_nature_of_expenditure'],
                    $po['po_budget_head'],
                    $po['po_budget_item'],
                    $po['po_name_of_vendor'],
                    $po['po_basic_amount'],
                    $po['po_gst_amount'],
                    $po['po_other_charges_and_taxes'],
                    number_format($po['po_po_total'], 2),
                    $approval_status_info, // e.g., "Approved (3/3)"
                    $approvers_list,       // e.g., "user1@example.com (Approved), user2@example.com (Pending)"
                    $manual_status         // e.g., "Approved"
                ];
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }
    private function _get_approval_status_summary($approver_tokens_json)
    {
        if (empty($approver_tokens_json)) {
            return 'No Approvers';
        }
        $approvers = json_decode($approver_tokens_json, true);
        if (empty($approvers)) {
            return 'No Approvers';
        }
        $pending_count = 0;
        $approved_count = 0;
        $rejected_count = 0;
        $total_approvers = count($approvers);
        foreach ($approvers as $token_data) {
            if ($token_data['status'] === 'pending') $pending_count++;
            elseif ($token_data['status'] === 'approved') $approved_count++;
            elseif ($token_data['status'] === 'rejected') $rejected_count++;
        }

        if ($rejected_count > 0) {
            return 'Rejected (' . $rejected_count . ' of ' . $total_approvers . ')';
        } elseif ($pending_count > 0) {
            return 'Pending (' . $approved_count . '/' . $total_approvers . ' Approved)';
        } else {
            return 'All Approved (' . $total_approvers . '/' . $total_approvers . ')';
        }
    }
    private function _get_approvers_list($approver_tokens_json)
    {
        if (empty($approver_tokens_json)) {
            return 'N/A';
        }
        $approvers = json_decode($approver_tokens_json, true);
        if (empty($approvers)) {
            return 'N/A';
        }
        
        $list = [];
        foreach ($approvers as $token_data) {
            $list[] = $token_data['email'] . ' (' . ucfirst($token_data['status']) . ')';
        }
        return implode('; ', $list);
    }
    private function _get_manual_status_text($status_code)
    {
        switch ($status_code) {
            case '1': return 'Approved';
            case '0': return 'Disapproved';
            case '2': return 'Pending';
            default: return 'Unknown';
        }
    }

    // ==================== GRN (Goods Receipt Note) Methods ====================

    /**
     * Display GRN list page
     */
    public function grn_list()
    {
        $logg = checklogin();
        if ($logg['status'] != true) {
            header("location:" . base_url() . "");
            die;
        }

        $this->load->model('Grn_model');
        $filters = [
            'grn_number' => $this->input->get('grn_number'),
            'po_number' => $this->input->get('po_number'),
            'vendor_name' => $this->input->get('vendor_name'),
            'start_date' => $this->input->get('start_date'),
            'end_date' => $this->input->get('end_date'),
            'status' => $this->input->get('status'),
        ];

        $per_page = $this->input->get('per_page', true) ?: 0;
        $config["base_url"] = base_url("accounts/grn-list");
        $config["total_rows"] = $this->Grn_model->grn_count($filters);
        $config["per_page"] = 20;
        $config["page_query_string"] = true;
        $this->pagination->initialize($config);
        $data["links"] = $this->pagination->create_links();
        $data['grn_result'] = $this->Grn_model->grn_pagination($config["per_page"], $per_page, $filters);
        $data['filters'] = $filters;
        $template = get_header_template(checklogin()['role']);
        $data['user_role'] = checklogin()['role'];
        $this->load->view($template['header']);
        $this->load->view('accounts/grn_list', $data);
        $this->load->view($template['footer']);
    }

    /**
     * Display form to create GRN from Purchase Order
     */
    public function add_grn($po_number)
    {
        $logg = checklogin();
        if ($logg['status'] != true) {
            header("location:" . base_url() . "");
            die;
        }

        $this->load->model('Purchase_order_model');
        $this->load->model('Grn_model');

        // Get purchase order details
        $purchase_order = $this->Purchase_order_model->get_purchase_order_by_id($po_number);
        if (!$purchase_order) {
            $this->session->set_flashdata('error', 'Purchase Order not found!');
            redirect('accounts/purchase-orders-list');
            return;
        }

        // Check if PO is approved
        $is_approved = false;
        if (!empty($purchase_order['approver_tokens'])) {
            $approver_tokens = json_decode($purchase_order['approver_tokens'], true);
            if ($approver_tokens) {
                $all_approved = true;
                $any_rejected = false;
                foreach ($approver_tokens as $token_data) {
                    if ($token_data['status'] === 'pending') {
                        $all_approved = false;
                    } elseif ($token_data['status'] === 'rejected') {
                        $any_rejected = true;
                    }
                }
                $is_approved = $all_approved && !$any_rejected;
            }
        } else {
            // Fallback: check status field
            $is_approved = ($purchase_order['status'] == '1');
        }

        if (!$is_approved) {
            $this->session->set_flashdata('error', 'GRN can only be created for approved Purchase Orders!');
            redirect('accounts/purchase-orders-list');
            return;
        }

        // Check if GRN already exists for this PO
        if ($this->Grn_model->grn_exists_for_po($po_number)) {
            $this->session->set_flashdata('warning', 'GRN already exists for this Purchase Order!');
            redirect('accounts/grn-list');
            return;
        }

        // Get PO items
        $po_items = $this->Grn_model->get_po_items($po_number);

        $data['purchase_order'] = $purchase_order;
        $data['po_items'] = $po_items;
        $data['grn_number'] = $this->Grn_model->generate_grn_number();

        $template = get_header_template(checklogin()['role']);
        $this->load->view($template['header']);
        $this->load->view('accounts/add_grn', $data);
        $this->load->view($template['footer']);
    }

    /**
     * Save GRN
     */
    public function save_grn()
    {
        $logg = checklogin();
        if ($logg['status'] != true) {
            header("location:" . base_url() . "");
            die;
        }

        $this->load->model('Grn_model');
        $this->load->model('Purchase_order_model');

        $po_number = $this->input->post('po_number');
        $purchase_order = $this->Purchase_order_model->get_purchase_order_by_id($po_number);

        if (!$purchase_order) {
            $this->session->set_flashdata('error', 'Purchase Order not found!');
            redirect('accounts/purchase-orders-list');
            return;
        }

        // Get current user
        $created_by = '';
        $logg = checklogin();
        if ($logg['status']) {
            $role = $logg['role'];
            $session_key = 'logged_' . $role;
            if (isset($_SESSION[$session_key]['employee_number'])) {
                $created_by = $_SESSION[$session_key]['employee_number'];
            }
        }

        // Prepare GRN data
        $grn_data = [
            'grn_number' => $this->input->post('grn_number'),
            'po_number' => $po_number,
            'po_id' => $purchase_order['id'],
            'grn_date' => $this->input->post('grn_date'),
            'vendor_name' => $purchase_order['po_name_of_vendor'],
            'centre' => $purchase_order['po_centre'],
            'department' => $purchase_order['po_department'],
            'received_by' => $this->input->post('received_by'),
            'inspected_by' => $this->input->post('inspected_by'),
            'remarks' => $this->input->post('remarks'),
            'total_items' => $this->input->post('total_items'),
            'total_quantity' => $this->input->post('total_quantity'),
            'status' => '1', // Active
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Insert GRN
        $grn_inserted = $this->Grn_model->insert_grn($grn_data);
         if (!$grn_inserted) {
            $this->session->set_flashdata('error', 'Failed to save GRN. Please try again.');
            redirect('accounts/purchase-orders-list');
            return;
        }

        // Get the inserted GRN ID
        $grn_id = $this->db->insert_id();

        // Save GRN items
        $item_descriptions = $this->input->post('item_description');
        $item_quantities = $this->input->post('quantity');
        $item_received_quantities = $this->input->post('received_quantity');
        $item_rates = $this->input->post('rate');
        $item_gst_rates = $this->input->post('gst_rate');
        $item_basic_totals = $this->input->post('basic_total');
        $item_gst_amounts = $this->input->post('gst_amount');
        $item_conditions = $this->input->post('item_condition');

        $items_batch_data = [];
        if (!empty($item_descriptions)) {
            for ($i = 0; $i < count($item_descriptions); $i++) {
                if (!empty($item_descriptions[$i])) {
                    $items_batch_data[] = [
                        'grn_id' => $grn_id,
                        'po_number' => $po_number,
                        'item_description' => $item_descriptions[$i],
                        'quantity' => $item_quantities[$i] ?? 0,
                        'received_quantity' => $item_received_quantities[$i] ?? 0,
                        'rate' => $item_rates[$i] ?? 0,
                        'gst_rate' => $item_gst_rates[$i] ?? 0,
                        'basic_total' => $item_basic_totals[$i] ?? 0,
                        'gst_amount' => $item_gst_amounts[$i] ?? 0,
                        'item_condition' => $item_conditions[$i] ?? 'Good',
                    ];
                }
            }
        }

        if (!empty($items_batch_data)) {
            $this->Grn_model->insert_grn_items($items_batch_data);
        }

        $this->session->set_flashdata('success', 'GRN created successfully!');
        redirect('accounts/grn-list');
    }

    /**
     * View GRN details
     */
    public function view_grn($grn_id)
    {
        $logg = checklogin();
        if ($logg['status'] != true) {
            header("location:" . base_url() . "");
            die;
        }

        $this->load->model('Grn_model');
        $grn = $this->Grn_model->get_grn_by_id($grn_id);
        
        if (!$grn) {
            $this->session->set_flashdata('error', 'GRN not found!');
            redirect('accounts/grn-list');
            return;
        }

        $grn_items = $this->Grn_model->get_grn_items($grn_id);
        $data['grn'] = $grn;
        $data['grn_items'] = $grn_items;

        $template = get_header_template(checklogin()['role']);
        $this->load->view($template['header']);
        $this->load->view('accounts/view_grn', $data);
        $this->load->view($template['footer']);
    }

    /**
     * Print Purchase Order
     */
    public function print_purchase_order($po_id)
    {
        $logg = checklogin();
        if ($logg['status'] != true) {
            header("location:" . base_url() . "");
            die;
        }

        $this->load->model('Purchase_order_model');
        $po = $this->Purchase_order_model->get_purchase_order_by_print($po_id);
        
        if (!$po) {
            $this->session->set_flashdata('error', 'Purchase Order not found!');
            redirect('accounts/purchase-orders-list');
            return;
        }

        // Check if PO is approved (for printing)
        $can_print = false;
        if (!empty($po['approver_tokens'])) {
            $approver_tokens = json_decode($po['approver_tokens'], true);
            if ($approver_tokens) {
                $all_approved = true;
                $any_rejected = false;
                foreach ($approver_tokens as $token_data) {
                    if ($token_data['status'] === 'pending') {
                        $all_approved = false;
                    } elseif ($token_data['status'] === 'rejected') {
                        $any_rejected = true;
                    }
                }
                $can_print = $all_approved && !$any_rejected;
            }
        } else {
            // Fallback: check status field
            $can_print = ($po['status'] == '1');
        }

        if (!$can_print) {
            $this->session->set_flashdata('error', 'Only approved Purchase Orders can be printed!');
            redirect('accounts/purchase-orders-list');
            return;
        }

        // Get purchase order items
        $po_items = $this->Purchase_order_model->get_purchase_order_items_by_number($po['po_number']);
        
        $data['po'] = $po;
        $data['po_items'] = $po_items;
        $this->load->view('accounts/print_purchase_order_view', $data);
    }

	public function patient_final_billing() 
	{
		$logg = checklogin();
		if($logg['status'] != true){
			header("location:" .base_url(). "");
			die();
		}
		
		$data = array();
		$data['all_centers'] = $this->get_all_centers();
		$template = get_header_template($logg['role']);
		$data['user_role'] = $logg['role'];
		if(isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
			$patient_id = $_GET['patient_id'];
			$billing_data = $this->accounts_model->get_patient_final_billing_data($patient_id);
			
			if($billing_data) {
				$data['billing_data'] = $billing_data;
				$data['show_bill'] = true;
			} else {
				$data['error'] = 'Patient not found or no billing data available.';
			}
		}
		
		$this->load->view($template['header']);
		$this->load->view('accounts/patient_final_billing', $data);
		$this->load->view($template['footer']);
	}
		/**
	 * Generate final bill for a patient
	 */
	public function generate_final_bill($patient_id) 
	{
		$logg = checklogin();
		if($logg['status'] != true){
			redirect(base_url());
			return;
		}
		
		$billing_data = $this->accounts_model->get_patient_final_billing_data($patient_id);
		
		if(!$billing_data) {
			redirect(base_url() . "accounts/patient_final_billing?error=" . urlencode('Patient not found or no billing data available.'));
			return;
		}
		
		// Check if all procedures are fully paid
		if(!$billing_data['payment_status']['all_paid']) {
			redirect(base_url() . "accounts/patient_final_billing?error=" . urlencode('Cannot generate final bill. Patient has outstanding payments.'));
			return;
		}
		
		$data['billing_data'] = $billing_data;
		$data['show_bill'] = true;
		
		$this->load->view('accounts/patient_final_billing', $data);
	}
	
		/**
	 * Check if patient is eligible for final billing
	 */
	public function check_final_billing_eligibility() 
	{
		$logg = checklogin();
		if($logg['status'] != true){
			echo json_encode(array('status' => false, 'message' => 'Unauthorized'));
			return;
		}
		
		$patient_id = $this->input->post('patient_id');
		
		if(empty($patient_id)) {
			echo json_encode(array('status' => false, 'message' => 'Patient ID is required'));
			return;
		}
		
		$billing_data = $this->accounts_model->get_patient_final_billing_data($patient_id);
		
		if(!$billing_data) {
			echo json_encode(array('status' => false, 'message' => 'Patient not found or no billing data available'));
			return;
		}
		
		$payment_status = $billing_data['payment_status'];
		
		$response = array(
			'status' => true,
			'eligible' => $payment_status['all_paid'],
			'total_amount' => $payment_status['total_amount'],
			'total_paid' => $payment_status['total_paid'],
			'remaining_amount' => $payment_status['total_remaining'],
			'procedures_count' => count($payment_status['procedures']),
			'message' => $payment_status['all_paid'] ? 'Patient is eligible for final billing' : 'Patient has outstanding payments'
		);
		
		echo json_encode($response);
	}
	/**
	 * Search patients for final billing
	 */
	public function search_patients_for_final_billing() 
	{
		$logg = checklogin();
		if($logg['status'] != true){
			echo json_encode(array('status' => false, 'message' => 'Unauthorized'));
			return;
		}
		
		$search_term = $this->input->post('search_term');
		
		if(empty($search_term)) {
			echo json_encode(array('status' => false, 'message' => 'Search term is required'));
			return;
		}
		
		// Search patients by ID, name, or phone
		$this->db->select('patient_id, wife_name, husband_name, wife_phone');
		$this->db->from('hms_patients');
		$this->db->where("(patient_id LIKE '%$search_term%' OR wife_name LIKE '%$search_term%' OR husband_name LIKE '%$search_term%' OR wife_phone LIKE '%$search_term%')");
		$this->db->limit(10);
		$query = $this->db->get();
		
		$patients = $query->result_array();
		
		echo json_encode(array('status' => true, 'patients' => $patients));
	}
	
	
	/**
	 * Get detailed procedure information for a patient
	 */
	public function get_patient_procedure_details() 
	{
		$logg = checklogin();
		if($logg['status'] != true){
			echo json_encode(array('status' => false, 'message' => 'Unauthorized'));
			return;
		}
		
		$patient_id = $this->input->post('patient_id');
		
		if(empty($patient_id)) {
			echo json_encode(array('status' => false, 'message' => 'Patient ID is required'));
			return;
		}
		
		$billing_data = $this->accounts_model->get_patient_final_billing_data($patient_id);
		
		if(!$billing_data) {
			echo json_encode(array('status' => false, 'message' => 'Patient not found or no billing data available'));
			return;
		}
		
        // Get all payments for this patient to calculate actual paid amounts
        $this->db->select('data, payment_done, on_date');
        $this->db->from('hms_patient_payments');
        $this->db->where('patient_id', $patient_id);
        $this->db->where('status', 0); // Only approved payments
        $payments_query = $this->db->get();
        $all_payments = $payments_query->result_array();
        
        // Group payments by procedure code
        $procedure_payments = array();
        foreach ($all_payments as $payment) {
            $payment_data = unserialize($payment['data']);
            if (isset($payment_data['patient_procedures'])) {
                foreach ($payment_data['patient_procedures'] as $proc) {
                    $code = $proc['sub_procedures_code'];
                    if (!isset($procedure_payments[$code])) {
                        $procedure_payments[$code] = array(
                            'total_paid' => 0,
                            'payments' => array()
                        );
                    }
                    $procedure_payments[$code]['total_paid'] += $payment['payment_done'];
                    $procedure_payments[$code]['payments'][] = array(
                        'amount' => $payment['payment_done'],
                        'date' => $payment['on_date']
                    );
                }
            }
        }
        
        $procedures = array();
        foreach ($billing_data['payment_status']['procedures'] as $procedure) {
            $procedure_data = unserialize($procedure['data']);
            if (isset($procedure_data['patient_procedures'])) {
                foreach ($procedure_data['patient_procedures'] as $proc) {
                    // Get procedure name from hms_procedures table
                    $procedure_name = isset($proc['sub_procedure']) ? $proc['sub_procedure'] : 'Unknown Procedure';
                    if (!empty($proc['sub_procedures_code'])) {
                        $this->db->select('procedure_name');
                        $this->db->from('hms_procedures');
                        $this->db->where('code', $proc['sub_procedures_code']);
                        $proc_query = $this->db->get();
                        if ($proc_query->num_rows() > 0) {
                            $proc_result = $proc_query->row_array();
                            $procedure_name = $proc_result['procedure_name'];
                        }
                    }

                    // Handle null/empty values
                    $price = !empty($proc['sub_procedures_price']) ? floatval($proc['sub_procedures_price']) : 0;
                    $discount = !empty($proc['sub_procedures_discount']) ? floatval($proc['sub_procedures_discount']) : 0;
                    
                    // Get actual total paid from payments table
                    $code = $proc['sub_procedures_code'];
                    $actual_paid = isset($procedure_payments[$code]) ? $procedure_payments[$code]['total_paid'] : 0;

                    // Skip procedures with invalid data
                    if ($price == 0 && $discount == 0 && $actual_paid == 0) {
                        continue;
                    }

                    $net_price = $price - $discount;
                    $remaining = $net_price - $actual_paid;

                    $procedures[] = array(
                        'procedure_name' => $procedure_name,
                        'procedure_code' => !empty($proc['sub_procedures_code']) ? $proc['sub_procedures_code'] : 'N/A',
                        'price' => $price,
                        'discount' => $discount,
                        'paid_price' => $actual_paid,
                        'billing_id' => $procedure['billing_id'],
                        'on_date' => $procedure['on_date'],
                        'remaining_amount' => $remaining,
                        'total_package' => $procedure['totalpackage'],
                        'payment_done' => $procedure['payment_done'],
                        'status' => $procedure['status']
                    );
                }
            }
        }
		
		echo json_encode(array('status' => true, 'procedures' => $procedures));
	}


function assessment_form($patient_id = 0){ // Keep the = 0 fix from before
    $logg = checklogin();
    if($logg['status'] == true){

	
        if(isset($_POST['action']) && !empty($_POST['action']) && $_POST['action'] == 'add_assessment_form'){
            unset($_POST['action']);
            
            // FIX: Pass $patient_id as the first argument
            $data = $this->accounts_model->assessment_form_insert_payment($patient_id, $_POST);
            
            if($data > 0){
                header("location:" .base_url(). "accounts/assessment_form_list/");
                die();
            }else{
                header("location:" .base_url(). "accounts/assessment_form?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
                die();
            }               
        }
        $data = array();
        $template = get_header_template($logg['role']);
        $this->load->view($template['header']);
        
        if($patient_id > 0){
            $data['data'] = $this->accounts_model->get_assessment_appointment_form($patient_id);
        } else {
            $data['data'] = array();
        }
        
        $this->load->view('accounts/assessment_form', $data);
        $this->load->view($template['footer']);
    }else{
        header("Location: " . base_url() . "accounts/assessment_form");
        die();
    }
}



private function get_assessment_center_nationality()
{
    $center = '';

    if (isset($_SESSION['logged_doctor']['center'])) {
        $center = trim((string) $_SESSION['logged_doctor']['center']);
    }

    if ($center === '') {
        return 'Indian';
    }

    $row = $this->db
        ->select('nationality')
        ->from('hms_centers')
        ->where('center_number', $center)
        ->limit(1)
        ->get()
        ->row_array();

    if (empty($row)) {
        $row = $this->db
            ->select('nationality')
            ->from('hms_centers')
            ->where('center_code', $center)
            ->limit(1)
            ->get()
            ->row_array();
    }

    if (empty($row) && ctype_digit($center)) {
        $row = $this->db
            ->select('nationality')
            ->from('hms_centers')
            ->where('ID', (int) $center)
            ->limit(1)
            ->get()
            ->row_array();
    }

    if (!empty($row['nationality'])) {
        return trim($row['nationality']);
    }

    return 'Indian';
}


	public function assessment_form_list(){
		$logg = checklogin();
		error_reporting(0);
		if($logg['status'] == true){

			$per_page = $this->input->get('per_page', true);
			if(empty($per_page)){
				$per_page = 0;
			}
			$patient_id = $this->input->get('patient_id', true);
			
			$config = array();
        	$config["base_url"] = base_url() . "accounts/assessment_form_list";
        	$config["total_rows"] = $this->accounts_model->assessment_form_count($patient_id);
        	$config["per_page"] = 10;
        	$config["uri_segment"] = 2;
			$config['use_page_numbers'] = true;
			$config['num_links'] = 5;
			$config['page_query_string'] = true;
			$config['reuse_query_string'] = true;
        	$this->pagination->initialize($config);
        	$page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;
			
        	$data["links"] = $this->pagination->create_links();
			$data['wallet_result'] = $this->accounts_model->assessment_form_pagination($config["per_page"], $per_page, $patient_id);
			$data["patient_id"] = $patient_id;
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/assessment_form_list', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}


	
public function assessment_form_list_non_indian()
{
    $logg = checklogin();
    error_reporting(0);

    if ($logg['status'] == true) {

        $per_page = $this->input->get('per_page', true);
        if (empty($per_page)) {
            $per_page = 0;
        }

        $patient_id = $this->input->get('patient_id', true);

        $config = array();
        $config["base_url"] = base_url() . "accounts/assessment_form_list_non_indian";
        $config["total_rows"] = $this->accounts_model->assessment_form_count_non_indian($patient_id);
        $config["per_page"] = 10;
        $config["uri_segment"] = 2;
        $config['use_page_numbers'] = true;
        $config['num_links'] = 5;
        $config['page_query_string'] = true;
        $config['reuse_query_string'] = true;

        $this->pagination->initialize($config);

        $page = ($this->uri->segment(2)) ? $this->uri->segment(2) : 0;

        $data["links"] = $this->pagination->create_links();

        $data['wallet_result'] = $this->accounts_model->assessment_form_pagination_non_indian(
            $config["per_page"],
            $per_page,
            $patient_id
        );

        $data["patient_id"] = $patient_id;

        $template = get_header_template($logg['role']);

        $this->load->view($template['header']);
        $this->load->view('accounts/assessment_form_list_non_indian', $data);
        $this->load->view($template['footer']);

    } else {
        header("location:" . base_url());
        die();
    }
}



public function assessment_form_non_indian($patient_id = 0)
{
    $logg = checklogin();

    if ($logg['status'] == true) {

        if (
            isset($_POST['action']) &&
            !empty($_POST['action']) &&
            $_POST['action'] == 'add_assessment_form'
        ) {
            unset($_POST['action']);

      $logged_doctor = $this->session->userdata('logged_doctor');

$logged_in_center_number = isset($logged_doctor['center'])
    ? $logged_doctor['center']
    : '';

if (!empty($logged_in_center_number)) {
    $_POST['centre'] = $logged_in_center_number;
}

$data = $this->accounts_model->assessment_form_insert_payment(
$patient_id,
$_POST
);

            if ($data > 0) {
                header("location:" . base_url() . "accounts/assessment_form_list_non_indian/");
                die();
            } else {
                header(
                    "location:" . base_url() .
                    "accounts/assessment_form_non_indian/" .
                    $patient_id .
                    "?m=" . base64_encode('Something went wrong !') .
                    '&t=' . base64_encode('error')
                );
                die();
            }
        }

        $data = array();
        $template = get_header_template($logg['role']);

        $this->load->view($template['header']);

        if ($patient_id > 0) {
            $data['data'] = $this->accounts_model
                ->get_assessment_appointment_form($patient_id);
        } else {
            $data['data'] = array();
        }

        $this->load->view('accounts/assessment_form_non_indian', $data);

        $this->load->view($template['footer']);

    } else {
        header("Location: " . base_url() . "accounts/assessment_form_non_indian");
        die();
    }
}  


	public function yearly_monthly_details(){
	$logg = checklogin();
    $year   = $this->input->get('year');
 
	if (empty($year)) {
        show_error('Invalid Year');
    }
	// ROLE CHECK
    if (!empty($_SESSION['logged_doctor']['center'])) {
        // Doctor login
        $center = $_SESSION['logged_doctor']['center'];
    } else {
        // Admin login → ALL centers
        $center = null;
    }

    $data['year']   = $year;
    $data['center'] = $center;

    $data['consult_monthly'] = $this->accounts_model->monthly_consultation($center, $year);

	$data['first_appointment_monthly'] = $this->accounts_model->monthly_first_appointment($center, $year);

    $data['stem_monthly'] = $this->accounts_model->monthly_ovarian_stem($center, $year);

	$data['ovarian_prp_monthly'] = $this->accounts_model->monthly_ovarian_prp($center, $year);	

    $data['testi_monthly'] = $this->accounts_model->monthly_testicular_stem($center, $year);

	$data['ovum_pickup_monthly'] = $this->accounts_model->monthly_ovum_pickup($center, $year);
	
	$data['embryo_transfer_monthly'] = $this->accounts_model->monthly_embryo_transfer($center, $year);

	$data['fet_monthly'] = $this->accounts_model->monthly_fet($center, $year);

	$data['iui_monthly'] = $this->accounts_model->iui_monthly($center, $year);

	$data['ivf_monthly'] = $this->accounts_model->ivf_monthly($center, $year);

	$data['icsi_monthly'] = $this->accounts_model->icsi_monthly($center, $year);

	$data['tesa_mtesa_monthly'] = $this->accounts_model->tesa_mtesa_monthly($center, $year);

	$data['testicular_prp_monthly'] = $this->accounts_model->testicular_prp_monthly($center, $year);

	$data['Sperm_Mobil_monthly'] = $this->accounts_model->Sperm_Mobil_monthly($center, $year);

	$data['Blastocyst_monthly'] = $this->accounts_model->Blastocyst_monthly($center, $year);

	$data['lah_monthly'] = $this->accounts_model->lah_monthly($center, $year);

	$data['Embryo_Glue_monthly'] = $this->accounts_model->Embryo_Glue_monthly($center, $year);

	$data['hysteroscopy_diagnostic_monthly'] = $this->accounts_model->hysteroscopy_diagnostic_monthly($center, $year);

	$data['laproscopy_hysteroscopy_monthly'] = $this->accounts_model->laproscopy_hysteroscopy_monthly($center, $year);

	$data['semen_freezing_monthly'] = $this->accounts_model->semen_freezing_monthly($center, $year);

	$data['embryo_freezing_monthly'] = $this->accounts_model->embryo_freezing_monthly($center, $year);

	$data['egg_freezing_monthly'] = $this->accounts_model->egg_freezing_monthly($center, $year);
	$template = get_header_template($logg['role']);
	$this->load->view($template['header']);
    $this->load->view('accounts/yearly_monthly_details', $data);
	$this->load->view($template['footer']);
}

public function clinical_details() {
	 $logg = checklogin();
    if ($logg['status'] != true) {
        redirect(base_url());
        return;
    }

	$center = null;
	 if (!empty($_SESSION['logged_doctor']['center'])) {
        $center = $_SESSION['logged_doctor']['center'];
    } else {
        // Admin login → ALL centers
        $center = null;
    }

	//$this->load->model('all_method');
    $year = $this->input->get('year');
    $type = $this->input->get('type');
	$month = $this->input->get('month');
    
    $data['year'] = $year;
    // Replace underscores with spaces and capitalize for the title (e.g. "ovarian_prp" -> "OVARIAN PRP")
    $data['type_title'] = str_replace('_', ' ', strtoupper($type)); 

    // Fetch records based on the type selected
    switch ($type) {
        case 'consultation':
            $data['records'] = $this->accounts_model->get_yearly_consultations($year, $type, $month, $center);
            break;

		case 'first_visit':
            $data['records'] = $this->accounts_model->get_yearly_first_visit($year, $type, $month, $center);
            break;	

        case 'stem_cell':
            $data['records'] = $this->accounts_model->get_yearly_ovarian_stem($year, $type, $month, $center);
            break;

        case 'testicular_stem_cell':
            $data['records'] = $this->accounts_model->get_yearly_testicular_stem($year, $type, $month, $center);
            break;

        case 'ovarian_prp':
            $data['records'] = $this->accounts_model->get_yearly_ovarian_prp($year, $type, $month, $center);
            break;

        case 'ovum_pickup':
            $data['records'] = $this->accounts_model->get_yearly_ovum_pickup($year, $type, $month, $center);
            break;

        case 'fresh_cycle_et':
            $data['records'] = $this->accounts_model->get_yearly_embryo_transfer($year, $type, $month, $center);
            break;

        case 'fet':
            $data['records'] = $this->accounts_model->get_yearly_fet($year, $type, $month, $center);
            break;

        case 'iui':
            $data['records'] = $this->accounts_model->get_yearly_iui($year, $type, $month, $center);
            break;

        case 'ivf':
            $data['records'] = $this->accounts_model->get_yearly_ivf($year, $type, $month, $center);
            break;

        case 'icsi':
            $data['records'] = $this->accounts_model->get_yearly_icsi($year, $type, $month, $center);
            break;

        case 'tesa_mtesa':
            $data['records'] = $this->accounts_model->get_yearly_tesa_mtesa($year, $type, $month, $center);
            break;

        case 'testicular_prp':
            $data['records'] = $this->accounts_model->get_yearly_testicular_prp($year, $type, $month, $center);
            break;

		case 'sperm_mobil':
			$data['records'] = $this->accounts_model->get_yearly_sperm_mobil($year, $type, $month, $center);
			break;	

		case 'blastocyst':
            $data['records'] = $this->accounts_model->get_yearly_blastocyst($year, $type, $month, $center);
            break;
			
		case 'lah':
            $data['records'] = $this->accounts_model->get_yearly_lah($year, $type, $month, $center);
            break;	

		case 'embryo_glue':
            $data['records'] = $this->accounts_model->get_yearly_embryo_glue($year, $type, $month, $center);
            break;	

		case 'hysteroscopy_diagnostic':
            $data['records'] = $this->accounts_model->get_yearly_hysteroscopy_diagnostic($year, $type, $month, $center);
            break;	
			
		case 'laproscopy_hysteroscopy':
            $data['records'] = $this->accounts_model->get_yearly_laproscopy_hysteroscopy($year, $type, $month, $center);
            break;	
			
		case 'semen_freezing':
            $data['records'] = $this->accounts_model->get_yearly_semen_freezing($year, $type, $month, $center);
            break;	
			
		case 'embryo_freezing':
            $data['records'] = $this->accounts_model->get_yearly_embryo_freezing($year, $type, $month, $center);
            break;	
			
		case 'egg_freezing':
            $data['records'] = $this->accounts_model->get_yearly_egg_freezing($year, $type, $month, $center);
            break;	

        default:
            $data['records'] = [];
            break;
    }
	//$data['all_method'] = $this->all_method;
 	$template = get_header_template($logg['role']);
	$this->load->view($template['header']);
    $this->load->view('accounts/clinical_details_view', $data);
	$this->load->view($template['footer']);
    
}

public function export_wallet_data($patient_id) {
    // 1. Setup Database and Helper
    $ci = &get_instance();
    $ci->load->database();
    $db_prefix = $ci->config->config['db_prefix'];
    
    $filename = "Wallet_Export_" . $patient_id . "_" . date('Ymd') . ".csv";
    
    // 2. Open Memory Stream
    $f = fopen('php://memory', 'w');
    
    // 3. Define CSV Headers
    fputcsv($f, array('Type', 'Receipt Number', 'Patient Id', 'On Date', 'Paid Amount', 'Payment Method','Cancle Date', 'Status'));

    // 4. Data Map (Source Table => [Label, Status Filter])
    $data_sources = array(
        'consultation'           => array('Consultation', "status='adjust'"),
        'registation'            => array('Registration', "status='adjust'"),
        'patient_procedure'      => array('Procedure', "status='cancel'"),
        'patient_investigations' => array('Investigation', "status='cancel'"),
        'patient_medicine'       => array('Medicine', "status='cancel'")
    );

    foreach ($data_sources as $table => $info) {
        $label = $info[0];
        $status_filter = $info[1];
        
        $sql = "SELECT receipt_number, patient_id, on_date, fees,payment_method, payment_done, modified_on, status 
                FROM " . $db_prefix . $table . " 
                WHERE " . $status_filter . " AND patient_id = '" . $this->db->escape_str($patient_id) . "'";
        
        $query = $this->db->query($sql);
        
        foreach ($query->result_array() as $row) {
            fputcsv($f, array(
                $label,
                $row['receipt_number'],
				$row['patient_id'],
                $row['on_date'],
                $row['payment_done'],
				$row['payment_method'],
				$row['modified_on'],
                $row['status']
            ));
        }
    }

    // 5. Add Partial Payments (Status 3)
    $pay_sql = "SELECT billing_id, billing_at, payment_done FROM " . $db_prefix . "patient_payments 
                WHERE patient_id = '" . $this->db->escape_str($patient_id) . "' AND status = '3'";
    $pay_query = $this->db->query($pay_sql);
    
    foreach ($pay_query->result_array() as $pay_row) {
        fputcsv($f, array('Partial Payment', $pay_row['billing_id'], $pay_row['billing_at'], 'N/A', '0', $pay_row['payment_done'], '0', 'Paid'));
    }

    // 6. Output headers and download
    fseek($f, 0);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    fpassthru($f);
    exit;
}

public function export_wallet_used_history($patient_id) {
    $ci = &get_instance();
    $ci->load->database();
    $db_prefix = $ci->config->config['db_prefix'];
    
    $filename = "Wallet_Used_History_" . $patient_id . "_" . date('Ymd') . ".csv";
    $f = fopen('php://memory', 'w');
    
    // Header (5 Columns) - Fixed to match your requirement
    fputcsv($f, array(
		'Type', 
        'Receipt number', 
		'Patient ID',        
        'On Date',  
        'Paid amount', 
        'Payment Method',
		'Status'
    ));

    // Mapping tables to their labels
    // We removed the individual status filters since you want all where payment_method is wallet
    $data_sources = array(
        'consultation'           => 'Consultation',
        'registation'            => 'Registration',
        'patient_procedure'      => 'Procedure',
        'patient_investigations' => 'Investigation',
        'patient_payments'       => 'Partial Payments'
    );

    foreach ($data_sources as $table => $label) {
        
        // Corrected SQL: Standardized to look for payment_method = 'wallet'
        $sql = "SELECT * FROM " . $db_prefix . $table . " 
                WHERE patient_id = " . $this->db->escape($patient_id) . " 
                AND payment_method = 'wallet'";
        
        $query = $this->db->query($sql);
        
        if ($query) {
            foreach ($query->result_array() as $row) {
                // Building the row with exactly 5 columns to match the header
                fputcsv($f, array(
					$label,
                    $row['receipt_number'] ?? $row['billing_id'] ?? '',
					$row['patient_id'],
                    $row['on_date'] ?? $row['billing_at'] ?? $row['created_at'] ?? '',
                    $row['payment_done'] ?? $row['amount'] ?? '0',
                    $row['payment_method'] ?? 'wallet',
					$row['status']
                ));
            }
        }
    }

    fseek($f, 0);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    fpassthru($f);
    exit;
}



public function all_patient_wallet_summary($export = false) {
    $logg = checklogin();
    $this->load->library('pagination');
    $db_prefix = $this->config->item('db_prefix');

    // --- 1. GET FILTERS ---
    $search = $this->input->get('search');
    $start_date = $this->input->get('start_date');
    $end_date = $this->input->get('end_date');
    $type_filter = $this->input->get('type');

    // --- 2. BUILD THE BASE QUERY ---
    // Note: billing_at is used as Center Number here
    $subquery = "
        SELECT patient_id, 'Procedure' AS type, on_date, billing_at, receipt_number, status, payment_done, payment_method FROM {$db_prefix}patient_procedure WHERE payment_method = 'wallet'
        UNION ALL
        SELECT patient_id, 'Consultation' AS type, on_date, billing_at, receipt_number, status, payment_done, payment_method FROM {$db_prefix}consultation WHERE payment_method = 'wallet'
        UNION ALL
        SELECT patient_id, 'Investigation' AS type, on_date, billing_at, receipt_number, status, payment_done, payment_method FROM {$db_prefix}patient_investigations WHERE payment_method = 'wallet'
        UNION ALL
        SELECT patient_id, 'Partial Payment' AS type, on_date, billing_at, refrence_number as receipt_number, status, payment_done, payment_method FROM {$db_prefix}patient_payments WHERE payment_method = 'wallet' AND status != '3'
    ";

    // --- 3. APPLY FILTERS ---
    $where = " WHERE 1=1";
    if (!empty($search)) {
        $where .= " AND (p.wife_name LIKE '%".$this->db->escape_like_str($search)."%' OR data.patient_id LIKE '%".$this->db->escape_like_str($search)."%')";
    }
    // Filtering by on_date since billing_at is the Center Number
    if (!empty($start_date)) {
        $where .= " AND DATE(data.on_date) >= '".$this->db->escape_str($start_date)."'";
    }
    if (!empty($end_date)) {
        $where .= " AND DATE(data.on_date) <= '".$this->db->escape_str($end_date)."'";
    }
    if (!empty($type_filter)) {
        $where .= " AND data.type = '".$this->db->escape_str($type_filter)."'";
    }

    // Join Patients for Name and Centers for Center Name using data.billing_at
    $final_query_base = "FROM ($subquery) AS data 
                         LEFT JOIN {$db_prefix}patients p ON data.patient_id = p.id 
                         LEFT JOIN {$db_prefix}centers c ON data.billing_at = c.center_number 
                         $where";

    // --- 4. PAGINATION ---
    $total_rows = $this->db->query("SELECT COUNT(*) as count $final_query_base")->row()->count;
    $per_page = 20;
    $page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;

    $config['base_url'] = base_url('accounts/all_patient_wallet_summary/false');
    $config['total_rows'] = $total_rows;
    $config['per_page'] = $per_page;
    $config['reuse_query_string'] = TRUE;
    
    // --- PAGINATION DESIGN (BOOTSTRAP) ---
	$config['full_tag_open'] = '<ul class="pagination pagination-sm no-margin pull-right">';
	$config['full_tag_close'] = '</ul>';

	$config['first_link'] = 'First';
	$config['first_tag_open'] = '<li>';
	$config['first_tag_close'] = '</li>';

	$config['last_link'] = 'Last';
	$config['last_tag_open'] = '<li>';
	$config['last_tag_close'] = '</li>';

	$config['next_link'] = '&raquo;';
	$config['next_tag_open'] = '<li>';
	$config['next_tag_close'] = '</li>';

	$config['prev_link'] = '&laquo;';
	$config['prev_tag_open'] = '<li>';
	$config['prev_tag_close'] = '</li>';

	$config['cur_tag_open'] = '<li class="active"><a href="#">';
	$config['cur_tag_close'] = '</a></li>';

	$config['num_tag_open'] = '<li>';
	$config['num_tag_close'] = '</li>';

	$this->pagination->initialize($config);
    // --- 5. FETCH DATA ---
    $limit_sql = ($export === 'true') ? "" : "LIMIT $page, $per_page";
    // Selecting center_name and using on_date for sorting
    $sql = "SELECT data.*, p.wife_name as patient_name, c.center_name $final_query_base ORDER BY data.on_date DESC $limit_sql";
    
    $query = $this->db->query($sql);
    $data['wallet_list'] = $query->result_array();
    $data['pagination'] = $this->pagination->create_links();

    if ($export === 'true') {
        $this->export_wallet_summary_csv($data['wallet_list']);
    } else {
        $template = get_header_template($logg['role']);
        $this->load->view($template['header']);
        $this->load->view('accounts/all_patient_wallet_view', $data);
        $this->load->view($template['footer']);
    }
}

private function export_wallet_summary_csv($list) {
    $filename = "All_Patient_Wallet_Summary_" . date('Ymd') . ".csv";
    $f = fopen('php://memory', 'w');
    
    // Header (8 Columns) - Removed "Center ID / billing_at"
    fputcsv($f, array(
        'Patient ID', 
        'Type', 
        'Date', 
        'Receipt Number', 
        'Status', 
        'Paid Amount', 
        'Payment Method', 
        'Center'
    ));

    foreach ($list as $row) {
        fputcsv($f, array(
            $row['patient_id'],
            $row['type'],
            $row['on_date'],
            $row['receipt_number'],
            $row['status'],
            $row['payment_done'],
            $row['payment_method'],
            $row['center_name'] // This shows the text "India IVF Noida", not the ID
        ));
    }
    
    fseek($f, 0);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    fpassthru($f);
    exit;
}


public function patient_wallet_add_summary($export = false) {
    $logg = checklogin();
    $db_prefix = $this->config->item('db_prefix');

    // --- 1. GET FILTERS ---
    $search = $this->input->get('search');
    $start_date = $this->input->get('start_date');
    $end_date = $this->input->get('end_date');
    $type_filter = $this->input->get('type');

    // --- 2. BUILD THE BASE QUERY ---
    // Filtering for 'cancel' and 'adjust' as requested
    $subquery = "
        SELECT patient_id, 'Procedure' AS type, on_date, billing_at, receipt_number, status, payment_done, payment_method, modified_on FROM {$db_prefix}patient_procedure WHERE status = 'cancel'
        UNION ALL
        SELECT patient_id, 'Consultation' AS type, on_date, billing_at, receipt_number, status, payment_done, payment_method, modified_on FROM {$db_prefix}consultation WHERE status = 'adjust'
        UNION ALL
        SELECT patient_id, 'Investigation' AS type, on_date, billing_at, receipt_number, status, payment_done, payment_method, modified_on FROM {$db_prefix}patient_investigations WHERE status = 'cancel'
        UNION ALL
        SELECT patient_id, 'Wallet Deposit' AS type, on_date, billing_at, refrence_number as receipt_number, status, payment_done, payment_method, modified_on FROM {$db_prefix}patient_payments WHERE status = '3' ";

    // --- 3. APPLY SEARCH FILTERS ---
    $where = " WHERE 1=1";
    if (!empty($search)) {
        $where .= " AND (p.wife_name LIKE '%".$this->db->escape_like_str($search)."%' OR data.patient_id LIKE '%".$this->db->escape_like_str($search)."%')";
    }
    if (!empty($start_date)) {
        $where .= " AND DATE(data.on_date) >= '".$this->db->escape_str($start_date)."'";
    }
    if (!empty($end_date)) {
        $where .= " AND DATE(data.on_date) <= '".$this->db->escape_str($end_date)."'";
    }
    if (!empty($type_filter)) {
        $where .= " AND data.type = '".$this->db->escape_str($type_filter)."'";
    }

    $final_query_base = "FROM ($subquery) AS data 
                         LEFT JOIN {$db_prefix}patients p ON data.patient_id = p.id 
                         LEFT JOIN {$db_prefix}centers c ON data.billing_at = c.center_number 
                         $where";

    // --- 4. PAGINATION ---
	
    $total_rows = $this->db->query("SELECT COUNT(*) as count $final_query_base")->row()->count;
    $per_page = 20;
    $page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;

    $config['base_url'] = base_url('accounts/patient_wallet_add_summary/false');
    $config['total_rows'] = $total_rows;
    $config['per_page'] = $per_page;
    $config['reuse_query_string'] = TRUE;
   // --- PAGINATION DESIGN (BOOTSTRAP) ---
$config['full_tag_open'] = '<ul class="pagination pagination-sm no-margin pull-right">';
$config['full_tag_close'] = '</ul>';

$config['first_link'] = 'First';
$config['first_tag_open'] = '<li>';
$config['first_tag_close'] = '</li>';

$config['last_link'] = 'Last';
$config['last_tag_open'] = '<li>';
$config['last_tag_close'] = '</li>';

$config['next_link'] = '&raquo;';
$config['next_tag_open'] = '<li>';
$config['next_tag_close'] = '</li>';

$config['prev_link'] = '&laquo;';
$config['prev_tag_open'] = '<li>';
$config['prev_tag_close'] = '</li>';

$config['cur_tag_open'] = '<li class="active"><a href="#">';
$config['cur_tag_close'] = '</a></li>';

$config['num_tag_open'] = '<li>';
$config['num_tag_close'] = '</li>';

$this->pagination->initialize($config);

    // --- 5. FETCH DATA ---
    $limit_sql = ($export === 'true') ? "" : "LIMIT $page, $per_page";
    $sql = "SELECT data.*, p.wife_name as patient_name, c.center_name $final_query_base ORDER BY data.on_date DESC $limit_sql";
    
    $query = $this->db->query($sql);
    $data['wallet_list'] = $query ? $query->result_array() : array();
    $data['pagination'] = $this->pagination->create_links();

    if ($export === 'true') {
        $this->export_wallet_add_summary_csv($data['wallet_list']);
    } else {
        $template = get_header_template($logg['role']);
        $this->load->view($template['header']);
        $this->load->view('accounts/patient_wallet_add_summary', $data);
        $this->load->view($template['footer']);
    }
}

private function export_wallet_add_summary_csv($list) {
    $filename = "Wallet_Cancel_Adjust_Report_" . date('Ymd') . ".csv";
    $f = fopen('php://memory', 'w');
    
    // Header
    fputcsv($f, array('Patient ID', 'Patient Name', 'Type', 'Date', 'Receipt Number', 'Status', 'Amount', 'Method', 'Center','Wallete Add Date'));

    foreach ($list as $row) {
        fputcsv($f, array(
            $row['patient_id'],
            $row['patient_name'],
            $row['type'],
            $row['on_date'],
            $row['receipt_number'],
            $row['status'],
            $row['payment_done'],
            $row['payment_method'],
            $row['center_name'],
			$row['modified_on']
			
        ));
    }
    
    fseek($f, 0);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    fpassthru($f);
    exit;
}

public function procedure_list() {
    $logg = checklogin();
    if ($logg["status"] == true) {
        $this->load->library('pagination');
        $this->load->model('accounts_model');

        // 1. Collect Filters from GET
        $filter = [
            'search'     => $this->input->get('search'),
            'center_id'  => $this->input->get('center_id'),
            'status'     => $this->input->get('status'),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'tally_status' => $this->input->get('tally_status')
        ];

        // 2. Pagination Configuration
        $config['base_url'] = base_url('accounts/procedure_list');
        $config['total_rows'] = $this->accounts_model->count_procedures($filter);
        $config['per_page'] = 25;
        $config['uri_segment'] = 3; // Corrected from 5 to 3
        $config['reuse_query_string'] = TRUE; 

        // Bootstrap Styling for Pagination
        $config['full_tag_open'] = '<ul class="pagination pagination-sm no-margin pull-right">';
        $config['full_tag_close'] = '</ul>';
        $config['num_tag_open'] = '<li>';
        $config['num_tag_close'] = '</li>';
        $config['cur_tag_open'] = '<li class="active"><a href="#">';
        $config['cur_tag_close'] = '</a></li>';
        $config['next_tag_open'] = '<li>';
        $config['next_tag_close'] = '</li>';
        $config['prev_tag_open'] = '<li>';
        $config['prev_tag_close'] = '</li>';
        $config['first_tag_open'] = '<li>';
        $config['first_tag_close'] = '</li>';
        $config['last_tag_open'] = '<li>';
        $config['last_tag_close'] = '</li>';
        $config['first_link'] = '&laquo; First';
        $config['last_link'] = 'Last &raquo;';

        $this->pagination->initialize($config);
        
        // Fetch the offset from segment 3
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

        // 3. Fetch Data
        $data["procedures"] = $this->accounts_model->get_procedures_paged($filter, $config['per_page'], $page);
        $data["centers"] = $this->db->get_where('hms_centers', array('status' => '1'))->result_array();
        $data["pagination"] = $this->pagination->create_links();
        $data["total_records"] = $config['total_rows'];

        $template = get_header_template($logg["role"]);
        $this->load->view($template["header"]);
        $this->load->view("accounts/procedure_list_view", $data);
        $this->load->view($template["footer"]);
    } else {
        redirect(base_url());
    }
}

public function export_procedure_list() {
    $this->load->model('accounts_model');
    
    // 1. Capture filters directly from GET
    $filter = [
        'search'     => $this->input->get('search'),
        'center_id'  => $this->input->get('center_id'),
        'status'     => $this->input->get('status'),
        'start_date' => $this->input->get('start_date'),
        'end_date'   => $this->input->get('end_date')
    ];

    // 2. Fetch data (Passing 0 as limit for "All Records")
    $data = $this->accounts_model->get_procedures_paged($filter, 0, 0);

    // 3. CRITICAL: Clear buffer to prevent blank files
    if (ob_get_level()) ob_end_clean();

    $filename = "Procedures_Export_".date('Ymd_His').".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    $output = fopen('php://output', 'w');
    
    // 4. Add BOM for Excel UTF-8 Support
    fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

    // 5. Headers
    fputcsv($output, ['Date', 'Patient ID', 'Center', 'Patient Name', 'Code', 'Procedure', 'Status']);

    // 6. Data
    if(!empty($data)) {
        foreach($data as $row) {
            fputcsv($output, [
                date('d-M-Y', strtotime($row->on_date)),
                $row->patient_id,
                $row->center_name,
                strtoupper($row->wife_name),
                $row->code,
                $row->procedure_name,
                strtoupper($row->status)
            ]);
        }
    }
    fclose($output);
    exit; 
}

public function patient_journey() {
    $logg = checklogin();
    if ($logg["status"] == true) {
        $this->load->model('Accounts_model');
        $this->load->library('pagination');

        // 1. Capture Filters
        $filter = [
            'billing_at' => $this->input->get('billing_at'),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'iic_id'     => $this->input->get('iic_id'),
			'code' => $this->input->get('code')
        ];

        // 2. Pagination Setup
        $config['base_url'] = base_url('accounts/patient_journey');
        $config['total_rows'] = $this->Accounts_model->count_journey_records($filter);
        $config['per_page'] = 25;
        $config['uri_segment'] = 3;
        $config['reuse_query_string'] = TRUE;

       // Existing tags
		$config['full_tag_open'] = '<ul class="pagination pagination-sm no-margin pull-right">';
		$config['full_tag_close'] = '</ul>';
		$config['num_tag_open'] = '<li>';
		$config['num_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="#">';
		$config['cur_tag_close'] = '</a></li>';

		// FIX: Add <li> tags to these 4 configurations
		$config['next_tag_open'] = '<li>';
		$config['next_tag_close'] = '</li>';
		$config['prev_tag_open'] = '<li>';
		$config['prev_tag_close'] = '</li>';

		$config['first_tag_open'] = '<li>';
		$config['first_tag_close'] = '</li>';
		$config['last_tag_open'] = '<li>';
		$config['last_tag_close'] = '</li>';

		// Optional: Change the labels to icons for a cleaner look
		$config['first_link'] = '&laquo; First';
		$config['last_link'] = 'Last &raquo;';
		$config['next_link'] = '&gt;';
		$config['prev_link'] = '&lt;';

        $this->pagination->initialize($config);
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

        // 3. Fetch Data
        $data["procedure_result"] = $this->Accounts_model->get_journey_report($filter, $config['per_page'], $page);
        $data["links"] = $this->pagination->create_links();
        
        // Pass filter values back to view for the inputs
        $data["billing_at"] = $filter['billing_at'];
        $data["start_date"] = $filter['start_date'];
        $data["end_date"]   = $filter['end_date'];
        $data["iic_id"]     = $filter['iic_id'];
		$data["code"]     = $filter['code'];
        $data["total_records"] = $config['total_rows'];

        $template = get_header_template($logg["role"]);
        $this->load->view($template["header"]);
        $this->load->view("accounts/patient_journey", $data);
        $this->load->view($template["footer"]);
    } else {
        redirect(base_url());
    }
}

public function export_patient_journey() {
    $this->load->model('Accounts_model');
    $all_method =& get_instance();

    // Ensure 'code' is captured from the URL (Search uses 'code', not 'procedure_code')
    $filter = [
        'billing_at' => trim($this->input->get('billing_at')),
        'start_date' => trim($this->input->get('start_date')),
        'end_date'   => trim($this->input->get('end_date')),
        'iic_id'     => trim($this->input->get('iic_id')),
        'code'       => trim($this->input->get('code')) 
    ];

    // Passing 0 here now works because of the Model fix above
    $data = $this->Accounts_model->get_journey_report($filter, 0, 0);

    if (ob_get_level()) ob_end_clean();

    $filename = "Journey_Export_" . date('Y-m-d_Hi') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $fp = fopen('php://output', 'w');

    // Add headers
    fputcsv($fp, ['S.No.', 'Booking Date', 'Patient ID', 'Patient Name', 'Center', 'Code', 'Procedure', 'Withdrawal Date', 'Stimulation Start', 'Trigger Date', 'OPU Date', 'ET Date', 'HCG Date']);

    if (!empty($data)) {
        $count = 1;
        foreach ($data as $vl) {
            // Withdrawal Date Query
            $withdrawal = $this->db->select('withdrawal_date')
                       ->from('hms_doctor_consultation')
                       ->where('patient_id', $vl['patient_id'])
                       ->where('withdrawal_date !=', '0000-00-00')
                       ->order_by('ID', 'DESC')->limit(1)->get()->row_array();

            fputcsv($fp, [
                $count++,
                date('d-M-y', strtotime($vl['on_date'])),
                $vl['patient_id'],
                strtoupper($all_method->get_patient_name($vl['patient_id'])),
                $all_method->get_center_name($vl['billing_at']),
                $vl['code'],
                $vl['procedure_name'],
                (!empty($withdrawal['withdrawal_date']) ? date('d-M-Y', strtotime($withdrawal['withdrawal_date'])) : '-'),
                $vl['stim_date'],
                $vl['trigger_date'],
                $vl['opu_date'],
                $vl['et_date'],
                $vl['hcg_date']
            ]);
        }
    }
    fclose($fp);
    exit;
}

/******** Wallet ***** */

	public function wallet_balance($paitent_id)
	{
		$logg = checklogin();
		if($logg['status'] == true){
			
			if(isset($_POST['action']) && isset($_POST['action']) && $_POST['action'] == 'wallet_balance'){
				unset($_POST['action']);
				$data = $this->accounts_model->balancereturn_insert($_POST);
				if($data > 0){
					header("location:" .base_url(). "accounts/wallet_balance?m=".base64_encode('Item added successfully !').'&t='.base64_encode('success'));
					die();
				}else{
					header("location:" .base_url(). "accounts/wallet_balance?m=".base64_encode('Something went wrong !').'&t='.base64_encode('error'));
					die();
				}				
			}
			$data = array();
			$template = get_header_template($logg['role']);
			$data['patient_data'] = get_patient_detail($paitent_id);
			$data['wallets'] = $this->Wallet_model->get_wallets($paitent_id);
			$data['paitent_id'] = $paitent_id;
			$this->load->view($template['header']);
			$this->load->view('accounts/wallet_balance', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}

	public function add_money_to_w1() {
		$p_id = $this->input->post('patient_id');
		$amt  = (float)$this->input->post('amount');
		$payment_method = $this->input->post('payment_method');
		$reference_id       = $this->input->post('reference_id');
		$receipt_number       = $this->input->post('receipt_number');
		$remarks = $this->input->post('remarks');
		$user = $this->session->userdata('user_id');
		$screenshot_name = ""; // Default empty

		// 1. Image Upload Logic
		if (!empty($_FILES['screenshot']['name'])) {
			$config['upload_path']   = './uploads/wallet_screenshots/';
			$config['allowed_types'] = 'jpg|jpeg|png|gif';
			$config['file_name']     = 'WLT_' . time() . '_' . $p_id;

			$this->load->library('upload', $config);

			// Folder create karein agar nahi hai
			if (!is_dir($config['upload_path'])) {
				mkdir($config['upload_path'], 0777, TRUE);
			}

			if ($this->upload->do_upload('screenshot')) {
				$uploadData = $this->upload->data();
				$screenshot_name = $uploadData['file_name']; // File ka naam variable mein aaya
			} else {
				// Agar upload fail ho toh error dekhein (Debugging ke liye)
				// echo $this->upload->display_errors(); die();
			}
		}

		// 2. Model call karte waqt screenshot_name ZAROOR bhejein
		$status = $this->Wallet_model->deposit_w1(
            $p_id,             // 1. $patient_id
            $amt,              // 2. $amount
            $payment_method,   // 3. $payment_method
            $remarks,          // 4. $remarks  <-- Yeh pehle aayega
            $user,             // 5. $user_id
            $reference_id,     // 6. $reference_id
            $receipt_number,   // 7. $receipt_number
            $screenshot_name   // 8. $screenshot
        );
		if($status) {
			$this->session->set_flashdata('success', 'Amount Deposited Successfully');
		}
		redirect($_SERVER['HTTP_REFERER']);
	}

	public function transfer_wallet_money() {
		$patient_id = $this->input->post('patient_id');
		$amount = (float)$this->input->post('amount');
		$remarks = $this->input->post('remarks');
		$user_id = $this->session->userdata('user_id'); // Current employee

		if ($amount <= 0) {
			$this->session->set_flashdata('error', 'Invalid amount.');
			redirect($_SERVER['HTTP_REFERER']);
		}

		// Call Model Function
		$result = $this->Wallet_model->move_money_w1_to_w2($patient_id, $amount, $remarks, $user_id);

		if ($result === true) {
			$this->session->set_flashdata('success', 'Money transferred to Wallet B.');
		} else {
			$this->session->set_flashdata('error', $result); // Error message like 'Insufficient Balance'
		}
		redirect($_SERVER['HTTP_REFERER']);
	}

	public function check_balance($patient_id) {
		$logg = checklogin();
		if($logg['status'] == true){		
			$data = array();
			$template = get_header_template($logg['role']);
		
		// 1. Current Balances fetch karein
		 $wallets = $this->Wallet_model->get_wallets($patient_id);
		
		// 2. Transaction History fetch karein
		$data['wallets'] = $wallets;
    	$data['history'] = $this->Wallet_model->get_wallet_history($patient_id);
    	$data['patient_id'] = $patient_id;

		// 3. View load karein
		
		$this->load->view($template['header']);
		$this->load->view('accounts/wallet_history_view', $data);
		$this->load->view($template['footer']);
	}else{
			header("location:" .base_url(). "");
			die();
		}
	}

public function wallet_transfer_requests() {
    $logg = checklogin();
    if($logg['status'] == true) {
        
        $this->load->library('pagination');

        // 1. Filter Inputs Check
        $filter_patient_id  = $this->input->get('patient_id') ? trim($this->input->get('patient_id')) : '';
        $filter_status      = $this->input->get('status') ? trim($this->input->get('status')) : '';
        $filter_start_date  = $this->input->get('start_date') ? trim($this->input->get('start_date')) : '';
        $filter_end_date    = $this->input->get('end_date') ? trim($this->input->get('end_date')) : '';
        $filter_remarks     = $this->input->get('remarks') ? trim($this->input->get('remarks')) : '';
        $filter_action_type = $this->input->get('action_type') ? trim($this->input->get('action_type')) : ''; // 👈 NEW FILTER

        // 2. WHERE Conditions Sub-query String Generator
        $where_logs = " WHERE 1=1";
        $where_adj  = " WHERE 1=1";

        if (!empty($filter_patient_id)) {
            $pid_esc = $this->db->escape_like_str($filter_patient_id);
            $where_logs .= " AND wl.patient_id LIKE '%{$pid_esc}%'";
            $where_adj  .= " AND adj.patient_id LIKE '%{$pid_esc}%'";
        }
        if (!empty($filter_status)) {
            $st_esc = $this->db->escape($filter_status);
            $where_logs .= " AND wl.status = {$st_esc}";
            $where_adj  .= " AND adj.status = {$st_esc}";
        }
        if (!empty($filter_start_date)) {
            $sdate_esc = $this->db->escape($filter_start_date);
            $where_logs .= " AND DATE(wl.created_at) >= {$sdate_esc}";
            $where_adj  .= " AND DATE(adj.created_at) >= {$sdate_esc}";
        }
        if (!empty($filter_end_date)) {
            $edate_esc = $this->db->escape($filter_end_date);
            $where_logs .= " AND DATE(wl.created_at) <= {$edate_esc}";
            $where_adj  .= " AND DATE(adj.created_at) <= {$edate_esc}";
        }
        if (!empty($filter_remarks)) {
            $rem_esc = $this->db->escape_like_str($filter_remarks);
            $where_logs .= " AND wl.remarks LIKE '%{$rem_esc}%'";
            $where_adj  .= " AND adj.remarks LIKE '%{$rem_esc}%'";
        }
        // 👈 NEW: Action Type (Wallet Action) Filter
        if (!empty($filter_action_type)) {
            $act_esc = $this->db->escape($filter_action_type);
            $where_logs .= " AND wl.action_type = {$act_esc}";
            
            // Adjustments Table ke liye condition (yadi adjustments me same types ya NULL match karna ho)
            $where_adj .= " AND 1=0"; // Kyunki action_types sirf hms_wallet_logs me hote hain
        }

        // 3. Count Total Rows Combined
        $count_sql = "
            SELECT COUNT(*) AS total FROM (
                SELECT wl.log_id FROM hms_wallet_logs wl {$where_logs}
                UNION ALL
                SELECT adj.id FROM hms_wallet_adjustments adj {$where_adj}
            ) AS combined_records
        ";
        $total_rows = $this->db->query($count_sql)->row()->total;

        // 4. Pagination & Query String Configuration
        $config['base_url']             = base_url('accounts/wallet_transfer_requests');
        $config['total_rows']           = $total_rows;
        $config['per_page']             = 10; 
        $config['page_query_string']    = TRUE; 
        $config['query_string_segment'] = 'per_page'; 
        $config['reuse_query_string']   = TRUE; 

        // Bootstrap Styling Tags
        $config['full_tag_open']   = '<ul class="pagination" style="margin:0;">';
        $config['full_tag_close']  = '</ul>';
        $config['num_tag_open']    = '<li>';
        $config['num_tag_close']   = '</li>';
        $config['cur_tag_open']    = '<li class="active"><a href="#">';
        $config['cur_tag_close']   = '</a></li>';
        $config['next_tag_open']   = '<li>';
        $config['next_tag_close']  = '</li>';
        $config['prev_tag_open']   = '<li>';
        $config['prev_tag_close']  = '</li>';
        $config['first_tag_open']  = '<li>';
        $config['first_tag_close'] = '</li>';
        $config['last_tag_open']   = '<li>';
        $config['last_tag_close']  = '</li>';

        $this->pagination->initialize($config);
        $page = $this->input->get('per_page') ? intval($this->input->get('per_page')) : 0;

        // 5. UNION Query (action_type field include kiya gaya hai)
        $main_sql = "
            SELECT * FROM (
                SELECT 
                    'transfer' AS record_type,
                    wl.log_id AS record_id,
                    wl.patient_id,
                    wl.amount,
                    wl.action_type,
                    wl.status,
                    wl.remarks,
                    wl.created_at,
                    wl.updated_at,
                    wl.created_by,
                    wl.approved_by,
                    wl.payment_method,
                    wl.screenshot,
                    emp1.name AS employee_name,
                    emp2.name AS approved_by_name
                FROM hms_wallet_logs wl
                LEFT JOIN hms_employees emp1 ON emp1.employee_number = wl.created_by
                LEFT JOIN hms_employees emp2 ON emp2.employee_number = wl.approved_by
                {$where_logs}

                UNION ALL

                SELECT 
                    'adjustment' AS record_type,
                    adj.id AS record_id,
                    adj.patient_id,
                    adj.amount,
                    'ADJUSTMENT' AS action_type,
                    adj.status,
                    CONCAT('Adjustment (', UPPER(adj.wallet_type), ' - ', UPPER(adj.adjustment_type), '): ', adj.remarks) AS remarks,
                    adj.created_at,
                    adj.updated_at,
                    adj.requested_by AS created_by,
                    adj.approved_by,
                    NULL AS payment_method,
                    NULL AS screenshot,
                    emp3.name AS employee_name,
                    emp4.name AS approved_by_name
                FROM hms_wallet_adjustments adj
                LEFT JOIN hms_employees emp3 ON emp3.employee_number = adj.requested_by
                LEFT JOIN hms_employees emp4 ON emp4.employee_number = adj.approved_by
                {$where_adj}
            ) AS combined
            ORDER BY (status = 'pending') DESC, created_at DESC
            LIMIT {$page}, {$config['per_page']}
        ";

        $data['pending_logs']     = $this->db->query($main_sql)->result_array();
        $data['pagination_links'] = $this->pagination->create_links();

        // Pass filter values back to view
        $data['search_patient_id']  = $filter_patient_id;
        $data['search_status']      = $filter_status;
        $data['search_start_date']  = $filter_start_date;
        $data['search_end_date']    = $filter_end_date;
        $data['search_remarks']     = $filter_remarks;
        $data['search_action_type'] = $filter_action_type; // 👈 NEW PASS BACK

        // View load engine
        $template = get_header_template($logg['role']);
        $this->load->view($template['header']);
        $this->load->view('accounts/wallet_transfer_list', $data); 
        $this->load->view($template['footer']);
        
    } else {
        redirect(base_url());
    }
}

public function approve_wallet_transfer($log_id) {
    $logg = checklogin();
    $approver_name = isset($_SESSION['logged_accountant']['name']) ? $_SESSION['logged_accountant']['name'] : 'Accountant';

    // 1. Modal form se aane wale remarks ko capture karein
    $update_remarks = $this->input->post('remarks');

    // Pending record nikalein
    $log = $this->db->get_where('hms_wallet_logs', ['log_id' => $log_id, 'status' => 'pending'])->row_array();
    
    if($log) {
        $patient_id = $log['patient_id'];
        $amt = floatval($log['amount']);
        $action_type = isset($log['action_type']) ? trim($log['action_type']) : '';
        
        // Patient ka current balances fetch karein
        $wallet = $this->db->get_where('hms_patient_wallets', ['patient_id' => $patient_id])->row_array();
        
        if(!empty($wallet)) {
            $current_w1 = floatval($wallet['wallet_1_balance']);
            $current_w2 = floatval($wallet['wallet_2_balance']);
            
            $new_w1 = $current_w1;
            $new_w2 = $current_w2;
            
            // 🎯 CONDITION: Wallet 2 se Wallet 1 me Transfer
            if ($action_type === 'TRANSFER_PACKAGE_WALLET_TO_MONEY_WALLET') {
                
                // 🔴 NEW CHECK: Verify Wallet 2 has enough balance before approval
                if ($current_w2 < $amt) {
                    $this->session->set_flashdata('error', 'Cannot approve: Insufficient balance in Package Wallet. Current Balance: ₹' . number_format($current_w2, 2));
                    redirect($_SERVER['HTTP_REFERER']);
                    return; // Execution yahin rok dein, approve nahi hoga
                }
                // 🔴 END NEW CHECK
                
                $new_w1 = $current_w1 + $amt; // Wallet 1 (Money) me Add hua
                $new_w2 = $current_w2 - $amt; // Wallet 2 (Package) se Deduct hua
                
                $this->db->where('patient_id', $patient_id)->update('hms_patient_wallets', [
                    'wallet_1_balance' => $new_w1,
                    'wallet_2_balance' => $new_w2,
                    'updated_at'       => date('Y-m-d H:i:s')
                ]);
                $msg = 'Transfer Approved! Amount moved from Package Wallet to Money Wallet.';
                
            } else {
                $msg = 'Request Approved Successfully. Status Updated.';
            }
            
            // Pending log ko approved mark karein aur UPDATE REMARKS save karein
            $this->db->where('log_id', $log_id)->update('hms_wallet_logs', [
                'status'         => 'approved',
                'update_remarks' => $update_remarks, 
                'opening_w1'     => $current_w1,
                'closing_w1'     => $new_w1,
                'opening_w2'     => $current_w2,
                'closing_w2'     => $new_w2,
                'approved_by'    => $approver_name,
                'updated_at'     => date('Y-m-d H:i:s')
            ]);
            
            $this->session->set_flashdata('success', $msg);
        } else {
            $this->session->set_flashdata('error', 'Patient wallet record not found.');
        }
    } else {
        $this->session->set_flashdata('error', 'Invalid request or already processed.');
    }
    redirect($_SERVER['HTTP_REFERER']);
}

public function disapprove_wallet_transfer($log_id) {
    $logg = checklogin();
    $approver_name = isset($_SESSION['logged_accountant']['name']) ? $_SESSION['logged_accountant']['name'] : 'Accountant';

    // 1. Modal form se aane wale remarks ko capture karein
    $update_remarks = $this->input->post('remarks');

    // Pending log record check karein
    $log = $this->db->get_where('hms_wallet_logs', ['log_id' => $log_id, 'status' => 'pending'])->row_array();
    
    if($log) {
        $patient_id = $log['patient_id'];
        $amount_action = floatval($log['amount']);
        $action_type = isset($log['action_type']) ? trim($log['action_type']) : '';

        // Current wallet balances get karein
        $wallet = $this->db->get_where('hms_patient_wallets', ['patient_id' => $patient_id])->row_array();
        
        if (!empty($wallet)) {
            $current_w1 = floatval($wallet['wallet_1_balance']);
            $current_w2 = floatval($wallet['wallet_2_balance']);
            
            $new_w1 = $current_w1;
            $new_w2 = $current_w2;
            $log_remarks = "";
            $new_action_type = 'reversed_due_to_disapproval';
            $update_balance_flag = true;

            // 🌟 1. WALLET 2 SE WALLET 1 TRANSFER REJECT
            if ($action_type === 'TRANSFER_PACKAGE_WALLET_TO_MONEY_WALLET') {
                $update_balance_flag = false; 
                $log_remarks = "Transfer Disapproved: No balance changes made as request was pending.";
                $new_action_type = 'transfer_w2_w1_rejected';
            }
            
            // 🌟 2. WALLET 1 SE WALLET 2 TRANSFER REJECT
            elseif ($action_type === 'TRANSFER_MONEY_WALLET_TO_PACKAGE_WALLET') {
                $new_w1 = $current_w1 + $amount_action; 
                $new_w2 = $current_w2 - $amount_action; 
                $log_remarks = "Transfer Disapproved: Amount returned back to Money Wallet and deducted from Package Wallet.";
                $new_action_type = 'transfer_w1_w2_rejected';
            }
            
            // 🌟 3. ALL BILLING / USAGE REJECTIONS
            elseif (in_array($action_type, ['PACKAGE_USAGE', 'INVESTIGATION_USAGE', 'Medicine_Sale','Consultation_Billing'])) {
                if ($action_type === 'PACKAGE_USAGE') {
                    $new_w2 = $current_w2 + $amount_action; 
                    $log_remarks = "Billing Disapproved: Amount reversed and credited back to Package Wallet.";
                } else {
                    $new_w1 = $current_w1 + $amount_action; 
                    $log_remarks = "Billing Disapproved: Amount reversed and credited back to Money Wallet.";
                }
                $new_action_type = 'billing_reversed';
            }
            
            // 🌟 4. NORMAL MONEY ADD REJECTIONS
            else {
                if ($action_type === 'DEPOSIT_PACKAGE_WALLET') {
                    $new_w2 = $current_w2 - $amount_action; 
                    $log_remarks = "Add Money Disapproved: Amount deducted from Package Wallet.";
                } else {
                    $new_w1 = $current_w1 - $amount_action; 
                    $log_remarks = "Add Money Disapproved: Amount deducted from Money Wallet.";
                }
                $new_action_type = 'add_money_rejected';
            }

            if ($update_balance_flag) {
                $this->db->where('patient_id', $patient_id)->update('hms_patient_wallets', [
                    'wallet_1_balance' => $new_w1,
                    'wallet_2_balance' => $new_w2,
                    'updated_at'       => date('Y-m-d H:i:s')
                ]);
            }

            // Ledger traceability log
            $new_log_data = [
                'patient_id'       => $patient_id,
                'amount'           => $amount_action,
                'action_type'      => $new_action_type, 
                'opening_w1'       => $current_w1,
                'closing_w1'       => $new_w1,
                'opening_w2'       => $current_w2,
                'closing_w2'       => $new_w2,
                'reference_id'     => $log['reference_id'],
                'receipt_number'   => $log['receipt_number'],
                'payment_method'   => $log['payment_method'],
                'status'           => 'disapproved', 
                'remarks'          => $log_remarks,
                'update_remarks'   => $update_remarks, // <-- New Log me bhi show ho jayega (optional)
                'center'           => $log['center'],
                'created_by'       => $approver_name, 
                'approved_by'      => $approver_name,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s')
            ];
            $this->db->insert('hms_wallet_logs', $new_log_data);
        }
        
        // Original pending log row ko update mark karein aur UPDATE REMARKS save karein
        $this->db->where('log_id', $log_id)->update('hms_wallet_logs', [
            'status'         => 'disapproved',
            'update_remarks' => $update_remarks, // <-- YAHAN ADD KIYA HAI
            'approved_by'    => $approver_name,
            'updated_at'     => date('Y-m-d H:i:s') 
        ]);
        
        $this->session->set_flashdata('success', 'Request Disapproved Successfully. Wallet structured reversal completed.');
    } else {
        $this->session->set_flashdata('error', 'Invalid Request or Request already processed.');
    }
    
    redirect($_SERVER['HTTP_REFERER']);
}

public function request_w2_to_w1() {
    $p_id = $this->input->post('patient_id');
    $amt  = (float)$this->input->post('amount');
    $rem  = $this->input->post('remarks');
    
    // Yahan par humne $_SESSION['logged_billing_manager']['name'] set kar diya hai
    $user = isset($_SESSION['logged_billing_manager']['name']) ? $_SESSION['logged_billing_manager']['name'] : 'Billing Manager';

    $result = $this->Wallet_model->request_transfer_2_to_1($p_id, $amt, $rem, $user);

    if ($result === true) {
        $this->session->set_flashdata('success', 'Transfer request sent to Accountant.');
    } else {
        $this->session->set_flashdata('error', $result);
    }
    redirect($_SERVER['HTTP_REFERER']);
}

public function add_money_to_package() {
    $patient_id = $this->input->post('patient_id');
    $amount     = (float)$this->input->post('amount');
    $payment_method = $this->input->post('payment_method');
    $reference_id   = $this->input->post('reference_id');
    $receipt_number = $this->input->post('receipt_number');
    $remarks    = $this->input->post('remarks');
    
    // 🎯 [FIX 1]: Default empty string rakhein, post() se file data nahi uthaya jata
    $screenshot_name = ""; 

    // 1. Get current balance for Wallet 2 (Package Wallet)
    $wallet = $this->db->get_where('hms_patient_wallets', ['patient_id' => $patient_id])->row();
    
    // FIX: Agar patient ka wallet nahi mila, toh use AUTO-CREATE karein
    if (!$wallet) {
        $this->db->insert('hms_patient_wallets', [
            'patient_id'       => $patient_id,
            'wallet_1_balance' => 0,
            'wallet_2_balance' => 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s')
        ]);
        
        $wallet = $this->db->get_where('hms_patient_wallets', ['patient_id' => $patient_id])->row();
    }

    $opening_w2 = (float)$wallet->wallet_2_balance;
    $closing_w2 = $opening_w2 + $amount;

    // 🎯 [FIX 2]: Handle Screenshot Upload (INSERT se PEHLE hona chahiye)
    if (!empty($_FILES['screenshot']['name'])) {
        $config['upload_path']   = './uploads/screenshots/';
        $config['allowed_types'] = 'jpg|jpeg|png|gif';
        $config['max_size']      = 2048; // 2MB Max
        $config['file_name']     = 'PKG_WLT_' . time() . '_' . $patient_id; // Unique Name

        // Folder checking & creation backup
        if (!is_dir($config['upload_path'])) {
            mkdir($config['upload_path'], 0777, TRUE);
        }

        $this->load->library('upload', $config);

        if ($this->upload->do_upload('screenshot')) {
            $uploadData = $this->upload->data();
            $screenshot_name = $uploadData['file_name']; // Successfully uploaded file name
        } else {
            // Debugging ke liye agar upload fail ho toh flashdata mein error dikhegi
            // $this->session->set_flashdata('error', $this->upload->display_errors());
        }
    }

    // 2. Update the Balance in hms_patient_wallets
    $this->db->where('patient_id', $patient_id);
    $this->db->update('hms_patient_wallets', [
        'wallet_2_balance' => $closing_w2,
        'updated_at'       => date('Y-m-d H:i:s')
    ]);

    // 3. Insert Log into hms_wallet_logs
    $log_data = [
        'patient_id'  => $patient_id,
        'amount'      => $amount,
        'action_type' => 'DEPOSIT_PACKAGE_WALLET', 
        'opening_w1'  => $wallet->wallet_1_balance,
        'closing_w1'  => $wallet->wallet_1_balance,
        'opening_w2'  => $opening_w2,
        'closing_w2'  => $closing_w2,
        'reference_id' => $reference_id,
        'receipt_number' => $receipt_number,
        'payment_method' => $payment_method,
        'screenshot'  => $screenshot_name, // 🎯 [FIX 3]: Ab sahi file name database mein jayega
        'created_by'  => $_SESSION['logged_billing_manager']['employee_number'],
        'center'      => $_SESSION['logged_billing_manager']['center'],
        'remarks'     => $remarks,
        'created_at'  => date('Y-m-d H:i:s'),
        'status'      => 'pending'
    ];

    // 🎯 Final Database Insertion
    $this->db->insert('hms_wallet_logs', $log_data);

    // 4. Success Message and Redirect
    $this->session->set_flashdata('success', 'Money added to Package Wallet successfully!');
    redirect($_SERVER['HTTP_REFERER']);
}

public function apply_discount() {
    $code = $this->input->post('coupon_code');
    $type = $this->input->post('billing_type'); // medicine, consultant, etc.
    $total = $this->input->post('total_amount');

    $result = $this->Coupon_model->validate_coupon($code, $type, $total);

    if ($result['status']) {
        $coupon = $result['data'];
        $discount = ($coupon->discount_type == 'percentage') 
                    ? ($total * ($coupon->discount_value / 100)) 
                    : $coupon->discount_value;

        echo json_encode(['new_total' => $total - $discount, 'discount' => $discount]);
    } else {
        echo json_encode(['error' => $result['message']]);
    }
}
public function print_invoice($reference_id = '') {
    $logg = checklogin();
    if(empty($reference_id)){ show_404(); }

    // 💡 कदम 1: l.created_by = e.employee_number करके कर्मचारी का असली नाम निकाला (JOIN लगाया)
    $this->db->select('l.*, p.wife_name, p.husband_name, p.wife_phone, p.patient_id as pt_id, e.name as employee_name');
    $this->db->from('hms_wallet_logs l');
    $this->db->join('hms_patients p', 'p.patient_id = l.patient_id', 'left');
    $this->db->join('hms_employees e', 'e.employee_number = l.created_by', 'left'); // 🎯 कर्मचारी नाम के लिए JOIN
    $this->db->where('l.reference_id', $reference_id);
    $log_data = $this->db->get()->row_array();

    // अगर reference_id से न मिले तो log_id से ढूंढने का बैकअप
    if (empty($log_data)) {
        $this->db->select('l.*, p.wife_name, p.husband_name, p.wife_phone, p.patient_id as pt_id, e.name as employee_name');
        $this->db->from('hms_wallet_logs l');
        $this->db->join('hms_patients p', 'p.patient_id = l.patient_id', 'left');
        $this->db->join('hms_employees e', 'e.employee_number = l.created_by', 'left'); // 🎯 कर्मचारी नाम के लिए JOIN
        $this->db->where('l.log_id', $reference_id);
        $log_data = $this->db->get()->row_array();
    }

    // अगर रिकॉर्ड मिल जाता है तो ऑन-द-स्पॉट ब्रांड न्यू रसीद ड्रा करें
    if(!empty($log_data)) {
        $this->generate_brand_new_receipt($log_data);
    } else {
        echo "<script>alert('No transaction log found for this receipt.'); window.close();</script>";
        die();
    }
}

// 💡 कदम 2: न्यू डायनेमिक रसीद का HTML/CSS लेआउट इंजन
private function generate_brand_new_receipt($log) {
    // 🎯 [BACKEND DETECT]: log data ke center validation se dynamic data uthana
    $CI =& get_instance();
    $center_data = array('center_name' => 'Main Center', 'center_gst' => 'N/A'); // Fallback structure

    if (!empty($log['center'])) {
        $db_prefix = $CI->config->item('db_prefix');
        $sql_center = "SELECT center_name, center_gst FROM `".$db_prefix."centers` WHERE center_number = '".$log['center']."' LIMIT 1";
        $res_center = $CI->db->query($sql_center)->row_array();
        if (!empty($res_center)) {
            $center_data = $res_center;
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Transaction Receipt #<?php echo $log['receipt_number'] ?? 'N/A'; ?></title>
        <style>
            body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 40px; background: #f9f9f9; color: #333; }
            .invoice-card { max-width: 700px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-top: 6px solid #aa183c; }
            .header-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 25px; }
            .logo-area img { max-height: 55px; display: block; margin-bottom: 5px; }
            /* 🎯 Center Text dynamic layout styling */
            .center-details-text { font-size: 11px; color: #475569; line-height: 1.4; font-weight: 500; margin-top: 5px; }
            .title-area { text-align: right; }
            .title-area h2 { margin: 0; color: #aa183c; font-size: 24px; font-weight: 700; }
            .title-area p { margin: 4px 0 0 0; color: #64748b; font-size: 13px; font-weight: 600; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; font-size: 13px; line-height: 1.6; }
            .info-block strong { color: #0f172a; }
            .info-block span { color: #475569; }
            .receipt-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            .receipt-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 12px; text-transform: uppercase; padding: 12px; border-bottom: 2px solid #e2e8f0; text-align: left; }
            .receipt-table td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; }
            .amount-big { font-size: 20px; font-weight: 800; color: #16a34a; }
            .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
            .badge-pending { background: #fef3c7; color: #d97706; }
            .badge-approved { background: #dcfce7; color: #15803d; }
            .badge-disapproved { background: #fee2e2; color: #b91c1c; }
            .footer-note { text-align: center; margin-top: 40px; font-size: 11px; color: #94a3b8; border-top: 1px dashed #e2e8f0; padding-top: 15px; }
            .no-print-btn { background: #0f172a; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; float: right; font-size: 12px; }
            .no-print-btn:hover { background: #1e293b; }
            @media print { .no-print { display: none !important; } body { padding: 0; background: #fff; } .invoice-card { box-shadow: none; padding: 0; } }
        </style>
    </head>
    <body>

    <div class="invoice-card">
        <div class="header-row">
            <div class="logo-area">
                <img src="https://www.indiaivf.in/images/IVF-Centre-IndiaIVF.webp" alt="Logo">
                <!-- 🎯 [DYNAMIC BLOCK]: Displaying center name and center gst dynamically -->
                <div class="center-details-text">
                    <strong>Address:</strong> <?php echo $center_data['center_name']; ?><br>
                    <strong>GSTIN:</strong> <?php echo !empty($center_data['center_gst']) ? $center_data['center_gst'] : 'N/A'; ?>
                </div>
            </div>
            <div class="title-area">
                <h2>INDIA IVF CENTRE</h2>
                <p>Transaction Receipt</p>
            </div>
        </div>

       

        <div class="info-grid">
            <div class="info-block">
                <strong>Patient Details:</strong><br>
                <span>IIC ID: <?php echo $log['pt_id'] ?? $log['patient_id'] ?? 'N/A'; ?></span><br>
                <span>Patient Name: <?php echo $log['wife_name'] ?? 'N/A'; ?></span><br>
                <span>Spouse Name: <?php echo $log['husband_name'] ?? 'N/A'; ?></span><br>
            </div>
            <div class="info-block" style="text-align: right;">
                <strong>Receipt Details:</strong><br>
                <span>Receipt No: <?php echo $log['receipt_number'] ?? 'N/A'; ?></span><br>
                <span>Created By: <?php echo !empty($log['employee_name']) ? $log['employee_name'] : (!empty($log['created_by']) ? $log['created_by'] : 'System'); ?></span><br>
                <span>Date: <?php echo date('d-m-Y, h:i A', strtotime($log['created_at'])); ?></span><br>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="width: 35%;">Transaction Description</th>
                    <th style="width: 20%;">Method</th>
                    <th style="width: 25%;">Transaction No / Ref ID</th>
                    <th style="width: 20%; text-align: right;">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo ucfirst(str_replace('_', ' ', $log['action_type'] ?? 'Wallet Transfer')); ?></strong><br>
                        <small style="color: #64748b; font-size: 11px;">
                            Remarks: <?php echo !empty($log['remarks']) ? $log['remarks'] : 'Wallet request processed.'; ?>
                        </small>
                    </td>
                    <td><span style="text-transform: uppercase;"><?php echo !empty($log['payment_method']) ? $log['payment_method'] : 'Wallet System'; ?></span></td>
                    <td><?php echo !empty($log['reference_id']) ? $log['reference_id'] : '<span class="text-muted">-</span>'; ?></td>
                    <td style="text-align: right;" class="amount-big">₹<?php echo number_format($log['amount'], 2); ?></td>
                </tr>
                <?php if(!empty($log['approved_by'])): ?>
                <tr>
                    <td colspan="4" style="text-align: right; font-size: 11px; color: #64748b; padding-top: 15px; border-bottom: none;">
                        <strong>Processed By:</strong> <?php echo $log['approved_by']; ?> 
                        <?php if(!empty($log['updated_at'])) { echo " on ".date('d-m-Y, h:i A', strtotime($log['updated_at'])); } ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-note">
            <p><strong>*Note:</strong> This is a provisional computer-generated receipt. The credited balance is subject to actual realization of funds and final approval by the Accounts Department. If a payment (UPI/Cheque/Card/Loan) is declined or reversed by the bank, the equivalent balance & bank charge, if any will be automatically deducted and/or reversed.*</p>
            <p>This is a certified digital receipt issued against your wallet action. For any queries, please visit the billing desk.</p>
            <p style="margin-top: 5px; font-weight: bold;">© <?php echo date('Y'); ?> India IVF Centre. All Rights Reserved.</p>
        </div>
		 <button class="no-print no-print-btn" onclick="window.print()"><i class="fa fa-print"></i> Print Receipt</button>
    </div>

    </body>
    </html>
    <?php
}


public function submit_cancel_request() {
    $ID = $this->input->post('ID'); 
    $reason = $this->input->post('reason_of_cancle');

    $center_id = isset($_SESSION['logged_doctor']['center']) ? $_SESSION['logged_doctor']['center'] : 'N/A';
    $doctor_name = isset($_SESSION['logged_doctor']['name']) ? $_SESSION['logged_doctor']['name'] : 'Doctor';
    $doctor_id = isset($_SESSION['logged_doctor']['id']) ? $_SESSION['logged_doctor']['id'] : 0;

    $this->db->select('p.patient_id as iic_id, p.procedure_name,p.receipt_number, p.code,p.totalpackage,p.discount_amount,p.fees,p.payment_done, pt.wife_name as patient_name');
    $this->db->from('hms_patient_procedure p');
    $this->db->join('hms_patients pt', 'pt.patient_id = p.patient_id', 'left');
    $this->db->where('p.ID', $ID);
	$proc_data = $this->db->get()->row();

	$iic_id = !empty($proc_data->iic_id) ? $proc_data->iic_id : 'N/A';
    $patient_name = !empty($proc_data->patient_name) ? $proc_data->patient_name : 'N/A';
    $procedure_name = !empty($proc_data->procedure_name) ? $proc_data->procedure_name : 'N/A';
    $code = !empty($proc_data->code) ? $proc_data->code : 'N/A';
	$totalpackage = !empty($proc_data->totalpackage) ? $proc_data->totalpackage : 'N/A';
	$discount_amount = !empty($proc_data->discount_amount) ? $proc_data->discount_amount : 'N/A';
	$fees = !empty($proc_data->fees) ? $proc_data->fees : 'N/A';
	$payment_done     = !empty($proc_data->payment_done) ? $proc_data->payment_done : 0;
	$receipt_number     = !empty($proc_data->receipt_number) ? $proc_data->receipt_number : 0;

	$this->db->select_sum('payment_done', 'total_paid');
    $this->db->where('billing_id', $receipt_number);
    
    $payment_query = $this->db->get('hms_patient_payments')->row();

    $total_paid = !empty($payment_query->total_paid) ? $payment_query->total_paid : 0;
	$grand_total = $payment_done + $total_paid;

    $insert_data = array(
        'procedure_id'     => $ID,
        'center_id'        => $center_id,
        'reason_of_cancel' => $reason,
        'status'           => 'pending', 
        'requested_by'     => $doctor_id,
        'created_at'       => date('Y-m-d H:i:s')
    );
    $this->db->insert('hms_cancel_requests', $insert_data);
    $request_id = $this->db->insert_id(); 

    // --- Dynamic Emails Fetch Logic ---
    $to_emails = array(); // Admin
    $cc_emails = array(); // Staff

    $this->db->select('email, role');
    $this->db->from('hms_employees');
    $this->db->where('status', '1');
    $this->db->where_in('role', array('administrator', 'accountant'));
    $global_staff = $this->db->get()->result();
    foreach($global_staff as $staff) {
        if(!empty($staff->email)) {
            if(strtolower($staff->role) == 'administrator') $to_emails[] = $staff->email;
            else $cc_emails[] = $staff->email;
        }
    }

    if(!empty($center_id)) {
        $this->db->select('email');
        $this->db->from('hms_employees');
        $this->db->where('status', '1');
        $this->db->where('center_id', $center_id);
        $this->db->where_in('role', array('center_head', 'billing_manager', 'counselor'));
        $center_staff = $this->db->get()->result();
        foreach($center_staff as $staff) {
            if(!empty($staff->email)) $cc_emails[] = $staff->email;
        }

        $this->db->select('email');
        $this->db->from('hms_doctors');
        $this->db->where('status', '1');
        $this->db->where('center_id', $center_id);
        $doctors = $this->db->get()->result();
        foreach($doctors as $doc) {
            if(!empty($doc->email)) $cc_emails[] = $doc->email;
        }
    }

    $to_emails = array_unique(array_filter($to_emails));
    $cc_emails = array_unique(array_filter($cc_emails));
    if(empty($to_emails)) $to_emails = array('webdesign@indiaivf.in'); 

    $salt = "hms_secure_salt_123"; 
    $token = md5($request_id . $salt);
    $approve_link = base_url('accounts/action_cancel_request/'.$request_id.'/approve/'.$token);
    $reject_link = base_url('accounts/action_cancel_request/'.$request_id.'/reject/'.$token);

    // --- COMMON MESSAGE BASE ---
    $email_subject = 'Procedure Cancellation Request - IIC ID: ' . $iic_id;
    
    $base_message = "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 600px; font-family: Arial, sans-serif;'>";
    $base_message .= "<tr><td style='background:#f2f2f2; width:35%;'><strong>IIC ID</strong></td><td>{$iic_id}</td></tr>";
    $base_message .= "<tr><td style='background:#f2f2f2;'><strong>Patient Name</strong></td><td>{$patient_name}</td></tr>";
    $base_message .= "<tr><td style='background:#f2f2f2;'><strong>Center Name</strong></td><td>{$center_id}</td></tr>";
    $base_message .= "<tr><td style='background:#f2f2f2;'><strong>Doctor</strong></td><td>{$doctor_name}</td></tr>";
	$base_message .= "<tr><td style='background:#f2f2f2;'><strong>Procedure Code</strong></td><td>{$code}</td></tr>";
    $base_message .= "<tr><td style='background:#f2f2f2;'><strong>Procedure Name</strong></td><td>{$procedure_name}</td></tr>";   
	$base_message .= "<tr><td style='background:#f2f2f2;'><strong>Package Amount</strong></td><td>{$totalpackage}</td></tr>";
	$base_message .= "<tr><td style='background:#f2f2f2;'><strong>Discount Amount</strong></td><td>{$discount_amount}</td></tr>";
	$base_message .= "<tr><td style='background:#f2f2f2;'><strong>After Discount</strong></td><td>{$fees}</td></tr>";
	$base_message .= "<tr><td style='background:#f2f2f2;'><strong>Booking Amount</strong></td><td>{$payment_done}</td></tr>";
    $base_message .= "<tr><td style='background:#f2f2f2;'><strong>Total Paid Amount</strong></td><td>{$grand_total}</td></tr>";
	$base_message .= "<tr><td style='background:#f2f2f2;'><strong>Balance Amount</strong></td><td>{$grand_total}</td></tr>";
	$base_message .= "<tr><td style='background:#f2f2f2;'><strong>Reason</strong></td><td style='color:red;'>{$reason}</td></tr>";
	$base_message .= "</table><br><br>";

    $this->load->library('email');

    // ==========================================
    // MAIL 1: SIRF ADMIN KO (BUTTONS KE SATH)
    // ==========================================
    $this->email->clear();
    $this->email->set_mailtype("html"); 
    $this->email->from('webdesign@indiaivf.in', 'HMS Automated System');
    $this->email->to($to_emails);
    $this->email->subject($email_subject);

    $admin_msg = "Dear Administrator,<br><br>A new procedure cancellation request has been submitted. Details are as follows:<br><br>" . $base_message;
    $admin_msg .= "<strong>Please take an action below:</strong><br><br>";
    $admin_msg .= "<a href='{$approve_link}' style='background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-family: Arial; font-weight: bold; margin-right: 15px;'>Approve</a>";
    $admin_msg .= "<a href='{$reject_link}' style='background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-family: Arial; font-weight: bold;'>Disapprove</a>";
    
    $this->email->message($admin_msg);
    $this->email->send();

    // ==========================================
    // MAIL 2: BAAKI STAFF KO (BINA BUTTONS KE)
    // ==========================================
    if(!empty($cc_emails)) {
        $this->email->clear();
        $this->email->set_mailtype("html"); 
        $this->email->from('webdesign@indiaivf.in', 'HMS Automated System');
        $this->email->to($cc_emails); // Inhe direct bheja taaki separate jaye
        $this->email->subject($email_subject);

        $staff_msg = "Dear Team,<br><br>A new procedure cancellation request has been submitted by the doctor. Details are as follows:<br><br>" . $base_message;
        $staff_msg .= "<em style='color:orange;'>Note: This request is currently pending Administrator approval. You will be notified once an action is taken.</em>";
        
        $this->email->message($staff_msg);
        $this->email->send();
    }

    $this->session->set_flashdata('success', 'Cancellation request submitted. Administrator and team notified.');
    redirect('accounts/procedure_list');
}


public function action_cancel_request($request_id, $action, $token) {
    // 1. Verify Token
    $salt = "hms_secure_salt_123";
    $expected_token = md5($request_id . $salt);

    if($token !== $expected_token) {
        die("<h3>Invalid Security Token. Request Denied.</h3>");
    }

    // 2. Database se Original Request Fetch karna
    $this->db->select('cr.*, p.patient_id as iic_id');
    $this->db->from('hms_cancel_requests cr');
    $this->db->join('hms_patient_procedure p', 'p.ID = cr.procedure_id', 'left');
    $this->db->where('cr.id', $request_id);
    $req_data = $this->db->get()->row();

    if(empty($req_data)) {
        die("<h3>Request not found.</h3>");
    }

    // Agar pehle se action liya ja chuka hai toh rok do
    if($req_data->status != 'pending') {
        die("<h3 style='color:blue;'>Action has already been taken on this request (Current Status: ".strtoupper($req_data->status).").</h3>");
    }

    $new_status = ($action == 'approve') ? 'approved' : 'rejected';

    // 3. Update 'hms_cancel_requests' table
    $update_data = array(
        'status' => $new_status,
        'action_taken_by' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0, 
        'action_taken_at' => date('Y-m-d H:i:s')
    );
    $this->db->where('id', $request_id);
    $this->db->update('hms_cancel_requests', $update_data);

    // 4. Agar Approve hua, toh procedure cancel kar do
    if($action == 'approve') {
        $this->db->where('ID', $req_data->procedure_id);
        $this->db->update('hms_patient_procedure', array('status' => 'cancel')); 
    }

    // ==========================================
    // 5. TRAIL MAIL BHEJNA (Sabko ek sath)
    // ==========================================
    $to_emails = array();
    $cc_emails = array();
    $center_id = $req_data->center_id;

    // Admin & Accountant
    $this->db->select('email, role');
    $this->db->from('hms_employees');
    $this->db->where('status', '1');
    $this->db->where_in('role', array('administrator', 'accountant'));
    $global_staff = $this->db->get()->result();
    foreach($global_staff as $staff) {
        if(!empty($staff->email)) {
            if(strtolower($staff->role) == 'administrator') $to_emails[] = $staff->email;
            else $cc_emails[] = $staff->email;
        }
    }

    // Center Staff
    if(!empty($center_id)) {
        $this->db->select('email');
        $this->db->from('hms_employees');
        $this->db->where('status', '1');
        $this->db->where('center_id', $center_id);
        $this->db->where_in('role', array('center_head', 'billing_manager', 'counselor'));
        $center_staff = $this->db->get()->result();
        foreach($center_staff as $staff) {
            if(!empty($staff->email)) $cc_emails[] = $staff->email;
        }

        $this->db->select('email');
        $this->db->from('hms_doctors');
        $this->db->where('status', '1');
        $this->db->where('center_id', $center_id);
        $doctors = $this->db->get()->result();
        foreach($doctors as $doc) {
            if(!empty($doc->email)) $cc_emails[] = $doc->email;
        }
    }

    $to_emails = array_unique(array_filter($to_emails));
    $cc_emails = array_unique(array_filter($cc_emails));
    if(empty($to_emails)) $to_emails = array('webdesign@indiaivf.in'); 

    $this->load->library('email');
    $this->email->clear();
    $this->email->set_mailtype("html"); 
    $this->email->from('webdesign@indiaivf.in', 'HMS Automated System');
    $this->email->to($to_emails);
    if(!empty($cc_emails)) $this->email->cc($cc_emails);

    // EXACT SAME SUBJECT TAQI THREAD BAN JAYE
    $this->email->subject('Procedure Cancellation Request - IIC ID: ' . $req_data->iic_id);

    $color = ($action == 'approve') ? '#28a745' : '#dc3545';
    
    $trail_message = "Dear Team,<br><br>";
    $trail_message .= "The administrator has taken an action on the procedure cancellation request.<br><br>";
    $trail_message .= "<strong>Decision:</strong> <span style='color: white; background-color: {$color}; padding: 3px 8px; border-radius: 3px; font-weight:bold;'>" . strtoupper($new_status) . "</span><br><br>";
    $trail_message .= "<strong>Procedure ID:</strong> " . $req_data->procedure_id . "<br>";
    $trail_message .= "<strong>Center ID:</strong> " . $center_id . "<br><br>";
    $trail_message .= "Thank you.<br><strong>HMS Automated System</strong>";

    $this->email->message($trail_message);
    $this->email->send();
    // ==========================================

    // Admin ko Screen par Success Dikhayein
    echo "<div style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h2 style='color: " . ($action == 'approve' ? 'green' : 'red') . ";'>";
    echo "Cancellation Request has been successfully <strong>" . strtoupper($new_status) . "</strong>!";
    echo "</h2>";
    echo "<p>Trail email sent to all stakeholders. You can safely close this window now.</p>";
    echo "</div>";
}

// 1. Fetch Patient Wallet Balance Info via AJAX
    public function get_patient_wallet_info() {
        $patient_id = $this->input->post('patient_id');
        
        if(!empty($patient_id)) {
            $wallet = get_universal_wallet($patient_id); 
            
            $w1 = isset($wallet['wallet_1']) ? $wallet['wallet_1'] : 0;
            $w2 = isset($wallet['wallet_2']) ? $wallet['wallet_2'] : 0;

            $patient_info = $this->Accounts_model->get_patient_basic_info($patient_id);
            
            $response = array(
                'status'       => 'success',
                'wallet_1'     => $w1,
                'wallet_2'     => $w2,
                'patient_name' => isset($patient_info['wife_name']) ? $patient_info['wife_name'] : '',
                'phone'        => isset($patient_info['wife_phone']) ? $patient_info['wife_phone'] : '',
                'email'        => isset($patient_info['wife_email']) ? $patient_info['wife_email'] : ''
            );
        } else {
            $response = array('status' => 'error', 'message' => 'IIC ID is empty');
        }
        
        ob_clean();
        echo json_encode($response);
        exit;
    }

    // 2. STEP 1: Center Head / Counselor Submits Initial Refund Request
    public function save_refund() {
        $patient_id    = $this->input->post('patient_id');
        $patient_name  = $this->input->post('patient_name');
        $contact       = $this->input->post('contact_number');
        $email_address = $this->input->post('email_address');
        $service       = $this->input->post('service_purchased');
        $receipt       = $this->input->post('receipt_number');
        $reason        = $this->input->post('refund_reason');
        $other_reason  = $this->input->post('other_reason');
        
        $wallet            = get_universal_wallet($patient_id); 
        $actual_w1_balance = isset($wallet['wallet_1']) ? (float)$wallet['wallet_1'] : 0;

        $total_deductions  = (float) $this->input->post('total_deductions');
        $net_refund        = $actual_w1_balance - $total_deductions;
        if ($net_refund < 0) { $net_refund = 0; }

        $bank_details = array(
            'bank_name'           => $this->input->post('bank_name'),
            'account_holder_name' => $this->input->post('account_holder_name'),
            'account_number'      => $this->input->post('account_number'),
            'ifsc_code'           => $this->input->post('ifsc_code')
        );

        $return_details = array(
            'return_product_name' => $this->input->post('return_product_name'),
            'return_quantity'     => $this->input->post('return_quantity'),
            'product_condition'   => $this->input->post('product_condition'),
            'expiration_date'     => $this->input->post('expiration_date')
        );

        $deduction_details = array(
            'opd'  => array('qty' => $this->input->post('deduct_opd_qty'), 'total' => (float)$this->input->post('deduct_opd_total')),
            'usg'  => array('qty' => $this->input->post('deduct_usg_qty'), 'total' => (float)$this->input->post('deduct_usg_total')),
            'file' => array('qty' => $this->input->post('deduct_file_qty'), 'total' => (float)$this->input->post('deduct_file_total')),
            'reg'  => array('qty' => $this->input->post('deduct_reg_qty'), 'total' => (float)$this->input->post('deduct_reg_total')),
            'inj'  => array('qty' => $this->input->post('deduct_inj_qty'), 'total' => (float)$this->input->post('deduct_inj_total')),
            'con'  => array('qty' => $this->input->post('deduct_con_qty'), 'total' => (float)$this->input->post('deduct_con_total')),
            'sub'  => array('qty' => $this->input->post('deduct_sub_qty'), 'total' => (float)$this->input->post('deduct_sub_total')),
            'misc' => array('qty' => $this->input->post('deduct_misc_qty'), 'total' => (float)$this->input->post('deduct_misc_total'))
        );

        $data = array(
            'patient_id'          => $patient_id,
            'patient_name'        => $patient_name,
            'contact_number'      => $contact,
            'email_address'       => $email_address,
            'purchase_date'       => $this->input->post('purchase_date'),
            'receipt_number'      => $receipt,
            'service_purchased'   => $service,
            'total_amount_paid'   => $actual_w1_balance, 
            'total_deductions'    => $total_deductions,
            'net_refund_amount'   => $net_refund,
            'refund_reason'       => $reason,
            'other_reason'        => $other_reason,
            'return_details'      => json_encode($return_details),
            'bank_details'        => json_encode($bank_details),
            'deduction_details'   => json_encode($deduction_details),
            'satisfaction_rating' => $this->input->post('satisfaction'),
            'feedback_comments'   => $this->input->post('feedback_comments'),
            'status'              => 'Pending Accountant Review',
            'created_by'          => isset($_SESSION['logged_billing_manager']['employee_number']) ? $_SESSION['logged_billing_manager']['employee_number'] : 'Center Head'
        );

        $this->Accounts_model->insert_refund_request($data);
        $refund_id = $this->db->insert_id(); 

        $full_details_html = $this->_generate_refund_email_html($data, $deduction_details, $bank_details, $actual_w1_balance, $total_deductions, $net_refund);

        // A. Patient Acknowledgement Email
        if (!empty($email_address)) {
            $this->_init_email();
            $this->email->clear();
            $this->email->from('noreply@indiaivf.in', 'India IVF Accounts'); 
            $this->email->to($email_address);
            $this->email->subject('Refund Request Initiated Successfully');
            
            $msg1 = "<h3>Refund Request Acknowledgement</h3>";
            $msg1 .= "<p>Dear <b>{$patient_name}</b>,</p>";
            $msg1 .= "<p>Your refund request has been initiated by the Center Head and is currently under verification by our Accounts Team. Details below:</p>";
            $msg1 .= $full_details_html;
            $msg1 .= "<p>We will keep you updated as it moves through verification and approval.</p>";
            
            $this->email->message($msg1);
            @$this->email->send();
        }

        // B. Send Email to ACCOUNTANTS for Review
        $accountants = $this->db->get_where('hms_employees', array('role' => 'accountant'))->result_array();
        $review_link = site_url("Accounts/review_refund_form/" . $refund_id); 

        foreach ($accountants as $acc) {
            if (!empty($acc['email'])) {
                $this->_init_email();
                $this->email->clear();
                $this->email->from('noreply@indiaivf.in', 'HMS System'); 
                $this->email->to($acc['email']);
                $this->email->subject('Action Required: New Refund Request for Verification');
                
                $msg2 = "<h3>New Refund Request Initiated</h3>";
                $msg2 .= "<p>A new refund request has been initiated by the Center Head. Please verify the deductions and forward it to the CEO with your suggestions.</p>";
                $msg2 .= $full_details_html;
                $msg2 .= '<a href="' . $review_link . '" style="padding:12px 24px; background-color:#ff9800; color:white; text-decoration:none; border-radius:4px; font-weight:bold; display:inline-block;">Review & Verify Deductions</a><br><br>';
                
                $this->email->message($msg2);
                @$this->email->send();
            }
        }

        $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : base_url();
        header("location:" . $redirect_url . "?m=" . base64_encode('Refund Request Sent to Accounts Team for Review.') . "&t=success");
        die();
    }

    // 3. STEP 2: Accountant Reviews, Adjusts Deductions & Forwards to CEO
    // 3. STEP 2: Accountant Reviews, Adjusts Deductions & Forwards to CEO
    public function accountant_submit_review() {
        $this->load->model('Accounts_model');
        
        $refund_id           = $this->input->post('refund_id');
        $accountant_comments = $this->input->post('accountant_comments');
        $adjusted_deductions = (float) $this->input->post('total_deductions');
        
        $refund_data         = $this->Accounts_model->get_refund_by_id($refund_id);
        $actual_w1_balance   = (float) $refund_data['total_amount_paid'];
        $new_net_refund      = $actual_w1_balance - $adjusted_deductions;
        if ($new_net_refund < 0) { $new_net_refund = 0; }

        // 🎯 FIX: Accountant द्वारा फॉर्म में अपडेट किए गए नए Breakdown को कैप्चर करें
        $updated_deduction_details = array(
            'opd'  => array('qty' => $this->input->post('deduct_opd_qty'), 'total' => (float)$this->input->post('deduct_opd_total')),
            'usg'  => array('qty' => $this->input->post('deduct_usg_qty'), 'total' => (float)$this->input->post('deduct_usg_total')),
            'file' => array('qty' => $this->input->post('deduct_file_qty'), 'total' => (float)$this->input->post('deduct_file_total')),
            'reg'  => array('qty' => $this->input->post('deduct_reg_qty'), 'total' => (float)$this->input->post('deduct_reg_total')),
            'inj'  => array('qty' => $this->input->post('deduct_inj_qty'), 'total' => (float)$this->input->post('deduct_inj_total')),
            'con'  => array('qty' => $this->input->post('deduct_con_qty'), 'total' => (float)$this->input->post('deduct_con_total')),
            'sub'  => array('qty' => $this->input->post('deduct_sub_qty'), 'total' => (float)$this->input->post('deduct_sub_total')),
            'misc' => array('qty' => $this->input->post('deduct_misc_qty'), 'total' => (float)$this->input->post('deduct_misc_total'))
        );

        // 🎯 Update Database with BOTH Total & Updated Breakdown JSON
        $update_data = array(
            'total_deductions'    => $adjusted_deductions,
            'net_refund_amount'   => $new_net_refund,
            'deduction_details'   => json_encode($updated_deduction_details), // 👈 नया JSON डेटाबेस में सेव होगा
            'accountant_comments' => $accountant_comments,
            'status'              => 'Pending CEO Approval',
            'accountant_id'       => isset($_SESSION['logged_billing_manager']['employee_number']) ? $_SESSION['logged_billing_manager']['employee_number'] : 'Accountant (Email Link)'
        );

        $this->db->where('id', $refund_id);
        $this->db->update('hms_refund_requests', $update_data);

        // Fetch Fresh Updated Data for Email
        $updated_refund = $this->Accounts_model->get_refund_by_id($refund_id);
        $bank           = json_decode($updated_refund['bank_details'], true);
        $deductions     = json_decode($updated_refund['deduction_details'], true);
        
        $approve_link = site_url("Accounts/ceo_approve_refund/" . $refund_id); 
        $full_details_html = $this->_generate_refund_email_html($updated_refund, $deductions, $bank, $actual_w1_balance, $adjusted_deductions, $new_net_refund, $approve_link, 'Click Here to Final Approve Refund');

        // Send Email to ADMINISTRATORS (CEO)
        $admins = $this->db->get_where('hms_employees', array('role' => 'administrator'))->result_array();

        foreach ($admins as $admin) {
            if (!empty($admin['email'])) {
                $this->_init_email();
                $this->email->clear();
                $this->email->from('noreply@indiaivf.in', 'HMS System'); 
                $this->email->to($admin['email']);
                $this->email->subject('Action Required: Refund Request Verified by Accountant');
                $this->email->message($full_details_html);
                @$this->email->send();
            }
        }

        echo "<div style='text-align:center; margin-top:80px; font-family:Arial, sans-serif;'>";
        echo "<div style='display:inline-block; border:1px solid #d6e9c6; padding:30px 50px; background-color:#dff0d8; color:#3c763d; border-radius:8px;'>";
        echo "<h2><i class='fa fa-check-circle'></i> Verified & Forwarded Successfully!</h2>";
        echo "<p style='font-size:16px;'>The refund deductions have been verified and forwarded to the CEO for final approval.</p>";
        echo "<p style='color:#777;'>You may close this browser tab now.</p>";
        echo "</div></div>";
        exit;
    }

    // 4. STEP 3: CEO APPROVE REFUND (PUBLIC / WITHOUT LOGIN)
    public function ceo_approve_refund($refund_id) {
        $this->Accounts_model->update_refund_status($refund_id, 'Approved by CEO');
        $refund_data = $this->Accounts_model->get_refund_by_id($refund_id);
        
        $bank       = json_decode($refund_data['bank_details'], true);
        $deductions = json_decode($refund_data['deduction_details'], true);

        $process_link = site_url("Accounts/accounts_process_refund/" . $refund_id); 
        $full_details_html = $this->_generate_refund_email_html($refund_data, $deductions, $bank, $refund_data['total_amount_paid'], $refund_data['total_deductions'], $refund_data['net_refund_amount'], $process_link, 'Process Payment & Deduct Wallet Balance');

        $accountants = $this->db->get_where('hms_employees', array('role' => 'accountant'))->result_array();

        foreach ($accountants as $acc) {
            if (!empty($acc['email'])) {
                $this->_init_email();
                $this->email->clear();
                $this->email->from('noreply@indiaivf.in', 'HMS System');
                $this->email->to($acc['email']); 
                $this->email->subject('Action Required: CEO Approved Refund - Process Payment');
                $this->email->message($full_details_html);
                @$this->email->send();
            }
        }

        echo "<div style='text-align:center; margin-top:80px; font-family:Arial, sans-serif;'>";
        echo "<div style='display:inline-block; border:1px solid #d6e9c6; padding:30px 50px; background-color:#dff0d8; color:#3c763d; border-radius:8px;'>";
        echo "<h2><i class='fa fa-check-circle'></i> Approved Successfully!</h2>";
        echo "<p style='font-size:16px;'>The refund request (ID: <b>{$refund_id}</b>) has been approved by CEO.</p>";
        echo "<p>Notification email dispatched to the accounts department to process the payout.</p>";
        echo "<p style='color:#777;'>You can close this window now.</p>";
        echo "</div></div>";
        exit;
    }

    // 5. STEP 4: Accounts Executes Payment & Deducts Wallet Balance
    // 5. STEP 4: Email Button Link - Confirmation Form Loads First
    public function accounts_process_refund($refund_id) {
        $this->load->model('Accounts_model');
        $this->load->helper('billing'); // Universal Wallet Helper

        $refund_data = $this->Accounts_model->get_refund_by_id($refund_id);

        if (empty($refund_data)) {
            show_404();
        }

        // यदि पहले ही प्रोसेस हो चुका है
        if ($refund_data['status'] == 'Processed & Wallet Deducted') {
            echo "<div style='text-align:center; margin-top:80px; font-family:Arial, sans-serif;'>";
            echo "<div style='display:inline-block; border:1px solid #ebccd1; padding:30px 50px; background-color:#f2dede; color:#a94442; border-radius:8px;'>";
            echo "<h2><i class='fa fa-info-circle'></i> Already Processed!</h2>";
            echo "<p style='font-size:16px;'>This refund request has already been processed and deducted from the wallet.</p>";
            echo "</div></div>";
            exit;
        }

        // यूनिवर्सल वॉलेट हेल्पर से W1 बैलेंस निकालें
        $wallet = get_universal_wallet($refund_data['patient_id']); 
        $current_w1 = isset($wallet['wallet_1']) ? floatval($wallet['wallet_1']) : 0;
        
        $data['refund_data']   = $refund_data;
        $data['refund_id']     = $refund_id;
        $data['current_w1']    = $current_w1;
        $data['refund_amount'] = floatval($refund_data['net_refund_amount']);

        // मैनुअल रिफंड कन्फर्मेशन व्यू लोड करें
        $this->load->view('billing_view/manual_refund_confirm', $data);
    }

   // 6. STEP 5: Final Action - Process & Actual Deduct Wallet
    public function execute_manual_refund() {
        $this->load->model('Accounts_model');
        $this->load->helper('billing');
        
        $refund_id     = $this->input->post('refund_id');
        $patient_id    = $this->input->post('patient_id');
        $refund_amount = floatval($this->input->post('refund_amount'));
        $payment_mode  = $this->input->post('payment_mode');
        $txn_ref       = $this->input->post('txn_reference');
        $accounts_note = $this->input->post('accounts_note');

        $refund_data = $this->Accounts_model->get_refund_by_id($refund_id);

        if (!empty($refund_data) && $refund_data['status'] != 'Processed & Wallet Deducted') {
            
            // 🎯 1. मौज़ूदा वॉलेट बैलेंस (W1) निकालें
            $wallet = get_universal_wallet($patient_id);
            $current_w1 = isset($wallet['wallet_1']) ? floatval($wallet['wallet_1']) : 0;
            
            $new_wallet_balance = $current_w1 - $refund_amount;
            if ($new_wallet_balance < 0) { 
                $new_wallet_balance = 0; 
            }

            $logged_user = isset($_SESSION['logged_billing_manager']['id']) ? $_SESSION['logged_billing_manager']['id'] : 1;

            // 🎯 2. सिस्टम के Built-in Stock / Wallet Model से डिडक्शन करें
            if ($this->load->model('Stock_model_new') && method_exists($this->Stock_model_new, 'deduct_wallet_balance')) {
                // Stock Model के ज़रिए वॉलेट से बैलेंस घटाएं
                $this->Stock_model_new->deduct_wallet_balance($patient_id, $refund_amount, 'RefundID-' . $refund_id, $logged_user);
            } else if ($this->load->model('Wallet_model') && method_exists($this->Wallet_model, 'deduct_wallet')) {
                // Wallet Model के ज़रिए वॉलेट से बैलेंस घटाएं
                $this->Wallet_model->deduct_wallet($patient_id, $refund_amount);
            }

            // 🎯 3. डेटाबेस टेबल्स में सीधे बैलेंस अपडेट/डेबिट एंट्री (बैकअप के लिए)
            if ($this->db->table_exists('hms_patient_wallet')) {
                $this->db->where('patient_id', $patient_id)->update('hms_patient_wallet', array('wallet_1' => $new_wallet_balance));
            }
            
            if ($this->db->table_exists('hms_patient_wallet_history')) {
                $this->db->insert('hms_patient_wallet_history', array(
                    'patient_id'    => $patient_id,
                    'debit_amount'  => $refund_amount,
                    'credit_amount' => 0,
                    'balance'       => $new_wallet_balance,
                    'type'          => 'debit',
                    'remark'        => 'Refund Processed (' . $payment_mode . ') Ref: ' . $txn_ref,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'on_date'       => date('Y-m-d')
                ));
            }

            // 🎯 4. रिफंड रिक्वेस्ट का स्टेटस अपडेट करें
            $update_fields = array(
                'status' => 'Processed & Wallet Deducted'
            );

            if ($this->db->field_exists('payout_mode', 'hms_refund_requests')) {
                $update_fields['payout_mode'] = $payment_mode;
            }
            if ($this->db->field_exists('payout_txn_ref', 'hms_refund_requests')) {
                $update_fields['payout_txn_ref'] = $txn_ref;
            }
            if ($this->db->field_exists('payout_accounts_notes', 'hms_refund_requests')) {
                $update_fields['payout_accounts_notes'] = $accounts_note;
            }
            if ($this->db->field_exists('processed_at', 'hms_refund_requests')) {
                $update_fields['processed_at'] = date('Y-m-d H:i:s');
            }

            $this->db->where('id', $refund_id)->update('hms_refund_requests', $update_fields);

            // 🎯 5. ताज़ा डिडक्टेड बैलेंस को री-वेरिफाई करें
            $updated_wallet = get_universal_wallet($patient_id);
            $verified_w1 = isset($updated_wallet['wallet_1']) ? floatval($updated_wallet['wallet_1']) : $new_wallet_balance;

            echo "<div style='text-align:center; margin-top:80px; font-family:Arial, sans-serif;'>";
            echo "<div style='display:inline-block; border:1px solid #d6e9c6; padding:30px 50px; background-color:#dff0d8; color:#3c763d; border-radius:8px;'>";
            echo "<h2><i class='fa fa-check-circle'></i> Refund Confirmed & Processed Successfully!</h2>";
            echo "<p style='font-size:16px;'>Refund Amount Deducted: <b style='color:red;'>₹" . number_format($refund_amount, 2) . "</b></p>";
            echo "<p style='font-size:16px;'>Updated System Wallet Balance: <b style='color:green;'>₹" . number_format($verified_w1, 2) . "</b></p>";
            echo "<p style='color:#777;'>You can close this tab now.</p>";
            echo "</div></div>";
            exit;
        } else {
            show_error("Invalid or Already Processed Refund Request.");
        }
    }

    // 🎯 SCREENSHOT EXACT EMAIL BUILDER HELPER
    private function _generate_refund_email_html($data, $deductions, $bank, $w1_balance, $total_deductions, $net_refund, $action_link = '', $button_text = '') {
        $deduction_html = "";
        if (isset($deductions['opd']['total']) && $deductions['opd']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>OPD Consultations:</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['opd']['total'], 2) . "</td></tr>";
        if (isset($deductions['usg']['total']) && $deductions['usg']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>Ultrasounds (USG):</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['usg']['total'], 2) . "</td></tr>";
        if (isset($deductions['file']['total']) && $deductions['file']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>File Charges:</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['file']['total'], 2) . "</td></tr>";
        if (isset($deductions['reg']['total']) && $deductions['reg']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>Registration Charges:</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['reg']['total'], 2) . "</td></tr>";
        if (isset($deductions['inj']['total']) && $deductions['inj']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>Injection Amount:</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['inj']['total'], 2) . "</td></tr>";
        if (isset($deductions['con']['total']) && $deductions['con']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>Consumables:</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['con']['total'], 2) . "</td></tr>";
        if (isset($deductions['sub']['total']) && $deductions['sub']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>Subvention Charges (EMI):</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['sub']['total'], 2) . "</td></tr>";
        if (isset($deductions['misc']['total']) && $deductions['misc']['total'] > 0) $deduction_html .= "<tr><td align='right' style='padding:4px 8px;'>Miscellaneous:</td><td style='color:red; text-align:right; padding:4px 8px;'>- Rs. " . number_format($deductions['misc']['total'], 2) . "</td></tr>";
        
        if ($deduction_html == "") {
            $deduction_html = "<tr><td align='right' style='padding:4px 8px;'>No Deductions</td><td style='color:gray; text-align:right; padding:4px 8px;'>Rs. 0.00</td></tr>";
        }

        $patient_name = isset($data['patient_name']) ? $data['patient_name'] : '';
        $patient_id   = isset($data['patient_id']) ? $data['patient_id'] : '';
        $service      = isset($data['service_purchased']) ? $data['service_purchased'] : '';
        $receipt      = isset($data['receipt_number']) ? $data['receipt_number'] : '';
        $reason       = isset($data['refund_reason']) ? $data['refund_reason'] : '';
        $other_reason = isset($data['other_reason']) ? $data['other_reason'] : '';
        $remarks      = isset($data['accountant_comments']) ? $data['accountant_comments'] : '';

        $full_reason = $reason . (!empty($other_reason) ? " - " . $other_reason : "");

        $html = "<div style='font-family: Arial, sans-serif; font-size: 13px; color: #333; line-height: 1.5;'>";
        $html .= "<h3 style='margin-bottom: 5px; color: #111; font-size: 15px;'>Refund Request Details</h3>";
        
        if(!empty($remarks)) {
            $html .= "<p style='margin-bottom: 15px; font-size: 13px;'><b>Accountant Remarks:</b> " . htmlspecialchars($remarks) . "</p>";
        }
        
        $html .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 650px; border-color: #e2e2e2; font-size: 13px;'>";
        $html .= "<tr style='background-color: #f2f2f2;'><th colspan='2' align='left' style='padding: 8px;'>Patient & Request Details</th></tr>";
        $html .= "<tr><td width='35%' style='padding: 8px;'><b>Patient Name:</b></td><td style='padding: 8px;'>{$patient_name} (ID: {$patient_id})</td></tr>";
        $html .= "<tr><td style='padding: 8px;'><b>Service / Receipt:</b></td><td style='padding: 8px;'>{$service} (Receipt: {$receipt})</td></tr>";
        $html .= "<tr><td style='padding: 8px;'><b>Reason for Refund:</b></td><td style='padding: 8px;'>{$full_reason}</td></tr>";
        
        $html .= "<tr style='background-color: #f2f2f2;'><th colspan='2' align='left' style='padding: 8px;'>Financial Summary</th></tr>";
        $html .= "<tr><td style='padding: 8px;'><b>Actual Wallet Balance (W1):</b></td><td style='padding: 8px;'><b style='font-size: 14px;'>Rs. " . number_format($w1_balance, 2) . "</b></td></tr>";
        
        $html .= "<tr><td colspan='2' style='padding: 0;'>";
        $html .= "<table width='100%' cellpadding='4' cellspacing='0' style='background-color: #fff5f5; border-collapse: collapse;'>";
        $html .= "<tr><td colspan='2' style='padding: 8px 8px 4px 8px; color: #d9534f; font-weight: bold;'>Deductions Breakdown:</td></tr>";
        $html .= $deduction_html;
        $html .= "<tr><td align='right' style='padding: 6px 8px; border-top: 1px solid #ffcccc;'><b>Total Deductions:</b></td>";
        $html .= "<td style='padding: 6px 8px; border-top: 1px solid #ffcccc; color: red; font-weight: bold; text-align: right;'>- Rs. " . number_format($total_deductions, 2) . "</td></tr>";
        $html .= "</table></td></tr>";
        
        $html .= "<tr><td style='padding: 10px 8px;'><b>Final Refund Amount:</b></td>";
        $html .= "<td style='padding: 10px 8px;'><b style='color: #28a745; font-size: 16px;'>Rs. " . number_format($net_refund, 2) . "</b></td></tr>";
        
        $html .= "<tr style='background-color: #f2f2f2;'><th colspan='2' align='left' style='padding: 8px;'>Bank Details For Transfer</th></tr>";
        $html .= "<tr><td style='padding: 8px;'><b>Bank Name:</b></td><td style='padding: 8px;'>" . (isset($bank['bank_name']) ? $bank['bank_name'] : '') . "</td></tr>";
        $html .= "<tr><td style='padding: 8px;'><b>A/C Holder Name:</b></td><td style='padding: 8px;'>" . (isset($bank['account_holder_name']) ? $bank['account_holder_name'] : '') . "</td></tr>";
        $html .= "<tr><td style='padding: 8px;'><b>A/C Number:</b></td><td style='padding: 8px;'>" . (isset($bank['account_number']) ? $bank['account_number'] : '') . "</td></tr>";
        $html .= "<tr><td style='padding: 8px;'><b>IFSC Code:</b></td><td style='padding: 8px;'>" . (isset($bank['ifsc_code']) ? $bank['ifsc_code'] : '') . "</td></tr>";
        $html .= "</table>";
        
        if (!empty($action_link)) {
            $html .= "<div style='margin-top: 20px;'>";
            $html .= "<a href='{$action_link}' style='background-color: #28a745; color: #ffffff; padding: 12px 22px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 13px; display: inline-block;'>{$button_text}</a>";
            $html .= "</div>";
        }

        $html .= "</div>";
        return $html;
    }

    // ACCOUNTANT REVIEW FORM PAGE
    public function review_refund_form($refund_id = '') {
        if(empty($refund_id)) { show_404(); }

        $data = array();
        $data['refund_data'] = $this->Accounts_model->get_refund_by_id($refund_id);
        
        if(empty($data['refund_data'])) { show_404(); }

        $data['refund_id'] = $refund_id;
        $this->load->view('billing_view/review_refund_form', $data); 
    }

	// 🎯 Patient Wallet Freeze / Unfreeze Toggle
    public function toggle_wallet_freeze() {
        $patient_id    = $this->input->post('patient_id');
        $status        = (int) $this->input->post('status'); // 1 = Freeze, 0 = Unfreeze
        $freeze_reason = $this->input->post('freeze_reason');
        $logged_user   = isset($_SESSION['logged_billing_manager']['id']) ? $_SESSION['logged_billing_manager']['id'] : 1;

        if (!empty($patient_id)) {
            
            // 1. Fetch Current Wallet Balance
            $wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $patient_id))->row_array();
            
            if (empty($wallet)) {
                echo json_encode(['status' => 'error', 'message' => 'Wallet not found for this patient.']);
                exit;
            }

            // 2. Update Freeze Status in hms_patient_wallets
            $this->db->where('patient_id', $patient_id)->update('hms_patient_wallets', array(
                'is_frozen'     => $status,
                'freeze_reason' => $freeze_reason,
                'updated_at'    => date('Y-m-d H:i:s')
            ));

            // 3. Insert Action Entry into hms_wallet_logs
            $action_label = ($status == 1) ? 'FREEZE' : 'UNFREEZE';
            $log_data = array(
                'patient_id'    => $patient_id,
                'amount'        => 0,
                'action_type'   => $action_label,
                'opening_w1'    => $wallet['wallet_1_balance'],
                'closing_w1'    => $wallet['wallet_1_balance'],
                'opening_w2'    => $wallet['wallet_2_balance'],
                'closing_w2'    => $wallet['wallet_2_balance'],
                'remarks'       => 'Wallet ' . $action_label . 'D. Reason: ' . $freeze_reason,
                'created_by'    => $logged_user,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
                'status'        => 'completed'
            );

            $this->db->insert('hms_wallet_logs', $log_data);

            $msg = ($status == 1) ? 'Patient Wallet has been FROZEN.' : 'Patient Wallet has been UNFROZEN.';
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Patient ID is missing.']);
        }
        exit;
    }

public function search_doctor() {
    $centre_id = $this->input->post('centre_id', true);
    
    if (empty($centre_id)) {
        echo "<option value=''>--Select Doctor--</option>";
        exit;
    }

    // Ensure model is loaded
    if (!isset($this->Accounts_model)) {
        $this->load->model('Accounts_model');
    }

    $doctors = $this->Accounts_model->center_doctors($centre_id);
    
    $option = "<option value=''>--Select Doctor--</option>";
    if (!empty($doctors)) {
        foreach ($doctors as $val) {
            $doc_id = isset($val['ID']) ? $val['ID'] : '';
            $doc_name = isset($val['name']) ? $val['name'] : '';
            if ($doc_id) {
                $option .= "<option value='" . html_escape($doc_id) . "'>" . html_escape($doc_name) . "</option>";
            }
        }
    } else {
        $option = "<option value=''>No Doctors Available</option>";
    }
    
    echo $option;
    exit;
}

public function doctor_slots() {
    $booking_date = $this->input->post('selected', true);
    $doctor_id    = $this->input->post('appoitmented_doctor', true);

    if (empty($booking_date) || empty($doctor_id)) {
        echo "<option value=''>--Select slot--</option>";
        exit;
    }

    // Ensure doctors_model is loaded
    if (!isset($this->doctors_model)) {
        $this->load->model('doctors_model');
    }

    // Convert date formats cleanly
    $selected_timestamp = strtotime($booking_date);
    $formatted_db_date  = date('Y-m-d', $selected_timestamp);
    $booking_day        = strtolower(date('l', $selected_timestamp));

    // 1. Check Holiday Status
    $doctor_available = 0;
    $doctor_on_holiday = $this->doctors_model->doctor_on_holiday($doctor_id);

    if (!empty($doctor_on_holiday) && $doctor_on_holiday != '0') {
        $explode = explode(" - ", $doctor_on_holiday);
        if (count($explode) == 2) {
            $contractDateBegin = date('Y-m-d', strtotime(trim($explode[0])));
            $contractDateEnd   = date('Y-m-d', strtotime(trim($explode[1])));

            if (($formatted_db_date >= $contractDateBegin) && ($formatted_db_date <= $contractDateEnd)) {
                $doctor_available = 1;
            }
        }
    }

    if ($doctor_available == 1) {
        echo "<option value=''>No slot - doctor on holiday</option>";
        exit;
    }

    // 2. Fetch Weekly Master Slots for the Doctor
    $doctor_slots = $this->doctors_model->doctor_slots($doctor_id, $booking_day);

    // If slots are serialized or JSON string, convert to array
    if (is_string($doctor_slots)) {
        $doctor_slots = @unserialize($doctor_slots) ?: json_decode($doctor_slots, true) ?: array();
    }

    // 3. Fetch Booked Appointments
    $doctor_appointments = $this->doctors_model->doctor_appointments($doctor_id, $formatted_db_date);
    $appointmented_slots = array();

    if (!empty($doctor_appointments)) {
        foreach ($doctor_appointments as $vla) {
            if (!empty($vla['appoitmented_slot'])) {
                $appointmented_slots[] = trim($vla['appoitmented_slot']);
            }
        }
    }

    // 4. Build Available Slot Options
    if (!empty($doctor_slots) && is_array($doctor_slots)) {
        $option = "<option value=''>--Select slot--</option>";
        $available_count = 0;

        foreach ($doctor_slots as $slot) {
            $slot_str = is_array($slot) ? ($slot['slot'] ?? '') : $slot;
            $slot_clean = trim($slot_str);

            if (!empty($slot_clean) && !in_array($slot_clean, $appointmented_slots, true)) {
                $option .= "<option value='" . html_escape($slot_clean) . "'>" . html_escape($slot_clean) . "</option>";
                $available_count++;
            }
        }

        if ($available_count > 0) {
            echo $option;
        } else {
            echo "<option value=''>All slots booked for this date</option>";
        }
    } else {
        echo "<option value=''>No available slot for " . ucfirst($booking_day) . "</option>";
    }
    exit;
}

    // 1. Adjustment Form Page View (With Employee Name JOIN)
    public function wallet_adjustment() {
        $logg = checklogin();
        $template = get_header_template($logg['role']);

        // 🎯 hms_patients और hms_employees दोनों टेबल्स से JOIN करके Name फेच करें
        $this->db->select('hms_wallet_adjustments.*, 
                           hms_patients.wife_name, 
                           hms_patients.husband_name, 
                           req_emp.name as requested_by_name, 
                           app_emp.name as approved_by_name');
        
        // Dynamic Patient Name Field Check
        if ($this->db->field_exists('patient_name', 'hms_patients')) {
            $this->db->select('hms_patients.patient_name');
        } else if ($this->db->field_exists('p_name', 'hms_patients')) {
            $this->db->select('hms_patients.p_name as patient_name');
        } else if ($this->db->field_exists('name', 'hms_patients')) {
            $this->db->select('hms_patients.name as patient_name');
        }

        $this->db->from('hms_wallet_adjustments');
        $this->db->join('hms_patients', 'hms_patients.patient_id = hms_wallet_adjustments.patient_id', 'left');
        
        // 🎯 Requested By Employee Name JOIN
        $this->db->join('hms_employees as req_emp', 'req_emp.employee_number = hms_wallet_adjustments.requested_by', 'left');
        
        // 🎯 Approved By Employee Name JOIN
        $this->db->join('hms_employees as app_emp', 'app_emp.employee_number = hms_wallet_adjustments.approved_by', 'left');

        $this->db->order_by('hms_wallet_adjustments.id', 'DESC');
        $data['adjustments'] = $this->db->get()->result_array();

        // Dynamically load view
        $view_path = '';
        if (file_exists(APPPATH . 'views/accounts/wallet_adjustment_view.php')) {
            $view_path = 'accounts/wallet_adjustment_view';
        } else if (file_exists(APPPATH . 'views/billing_view/wallet_adjustment_view.php')) {
            $view_path = 'billing_view/wallet_adjustment_view';
        }

        if (!empty($view_path)) {
            $this->load->view($template['header'], $data);
            $this->load->view($view_path, $data);
            $this->load->view($template['footer'], $data);
        } else {
            show_error('Wallet adjustment view file not found in views directory.');
        }
    }

    // 2. AJAX: Patient Search by ID with Real-Time Wallet Balances
    public function search_patient_for_adjustment() {
        $patient_id = trim($this->input->post('patient_id'));
        
        if(!empty($patient_id)) {
            $patient = $this->db->get_where('hms_patients', array('patient_id' => $patient_id))->row_array();
            
            if (!empty($patient)) {
                $wife_name = $patient['wife_name'] ?? $patient['patient_name'] ?? '';
                $husband_name = $patient['husband_name'] ?? '';
                $formatted_name = !empty($husband_name) ? $wife_name . ' w/o ' . $husband_name : $wife_name;

                // Fetch Wallet Balances from `hms_patient_wallets`
                $wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $patient_id))->row_array();
                $w1_bal = isset($wallet['wallet_1_balance']) ? floatval($wallet['wallet_1_balance']) : 0;
                $w2_bal = isset($wallet['wallet_2_balance']) ? floatval($wallet['wallet_2_balance']) : 0;

                echo json_encode([
                    'status'       => 'success', 
                    'patient_name' => $formatted_name,
                    'w1_balance'   => $w1_bal,
                    'w2_balance'   => $w2_bal
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Patient ID not found!']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Please enter Patient ID.']);
        }
        exit;
    }

	public function patient_update_new()
{
    $logg = checklogin();
	

    if ($logg['status'] != true) {
        redirect(base_url());
        return;
    }

    $this->load->model('Appointment_model');

$ID = (int) $this->input->get('ID');

if ($ID <= 0) {
    $ID = (int) $this->input->post('appointment_id');
}
    if ($ID <= 0) {
        show_error('Invalid appointment ID.', 400);
        return;
    }

    // FETCH EXISTING APPOINTMENT
    $appointment = $this->Appointment_model
        ->get_existing_appointment($ID);

    if (empty($appointment)) {
        show_404();
        return;
    }

    // UPDATE THE SAME APPOINTMENT
    if ($this->input->method() === 'post') {

        $update_data = array(
            'wife_name' => trim(
                (string) $this->input->post('wife_name', true)
            ),

            'husband_name' => trim(
                (string) $this->input->post('husband_name', true)
            ),

            'camp_selection' => trim(
                (string) $this->input->post('camp_selection', true)
            ),

            'appoitmented_doctor' => trim(
                (string) $this->input->post('appoitmented_doctor', true)
            ),

            'appoitmented_date' => trim(
                (string) $this->input->post('appoitmented_date', true)
            ),

            'appoitmented_slot' => trim(
                (string) $this->input->post('appoitmented_slot', true)
            ),

            'status' => trim(
                (string) $this->input->post('status', true)
            )
        );

        // ALLOWED APPOINTMENT STATUSES
      $allowed_statuses = array(
    'booked',
    'in_clinic',
    'cancelled',
    'rescheduled',
    'visited',
    'consultation_done'
);

        // REQUIRED FIELD VALIDATION
        if (
            $update_data['wife_name'] === '' ||
            $update_data['camp_selection'] === '' ||
            $update_data['appoitmented_doctor'] === '' ||
            $update_data['appoitmented_date'] === '' ||
            $update_data['appoitmented_slot'] === '' ||
            !in_array(
                $update_data['status'],
                $allowed_statuses,
                true
            )
        ) {
            $this->session->set_flashdata(
                'error',
                'Please fill all required fields correctly.'
            );

            redirect('accounts/patient_update_new?ID=' . $ID);
            return;
        }

        // VALIDATE DATE FORMAT
        $date = DateTime::createFromFormat(
            'Y-m-d',
            $update_data['appoitmented_date']
        );

        if (
            !$date ||
            $date->format('Y-m-d') !==
            $update_data['appoitmented_date']
        ) {
            $this->session->set_flashdata(
                'error',
                'Please select a valid appointment date.'
            );

            redirect('accounts/patient_update_new?ID=' . $ID);
            return;
        }

        // UPDATE EXISTING RECORD ONLY
        $updated = $this->Appointment_model
            ->update_existing_appointment($ID, $update_data);

        if ($updated === false) {

            $db_error = $this->db->error();

            log_message(
                'error',
                'Appointment update failed. ID: ' . $ID .
                ' | DB Error: ' . $db_error['message']
            );

            $this->session->set_flashdata(
                'error',
                'Appointment update failed. ' .
                $db_error['message']
            );

        } else {

            $this->session->set_flashdata(
                'success',
                'Appointment updated successfully.'
            );
        }

        redirect('accounts/patient_update_new?ID=' . $ID);
        return;
    }

    // FETCH DROPDOWN DATA
    $data['appointment'] = $appointment;

   $data['camps'] = $this->Appointment_model
    ->get_active_camps_by_center($appointment->appoitment_for);

    $data['doctors'] = $this->Appointment_model
        ->get_active_doctors();

    // LOAD HEADER, VIEW AND FOOTER
    $template = get_header_template($logg['role']);

    $this->load->view($template['header']);
    $this->load->view('accounts/patient_update_new', $data);
    $this->load->view($template['footer']);
}




public function patient_update_international()
{
    $logg = checklogin();

    if ($logg['status'] != true) {
        redirect(base_url());
        return;
    }

    $this->load->model('Appointment_model');

    $ID = (int) $this->input->get('ID');

    if ($ID <= 0) {
        $ID = (int) $this->input->post('appointment_id');
    }

    if ($ID <= 0) {
        show_error('Invalid appointment ID.', 400);
        return;
    }

    // FETCH EXISTING APPOINTMENT
    $appointment = $this->Appointment_model
        ->get_existing_appointment($ID);

    if (empty($appointment)) {
        show_404();
        return;
    }

    // ONLY INTERNATIONAL APPOINTMENTS
    if (empty(trim((string) $appointment->country))) {
        show_404();
        return;
    }

    // UPDATE EXISTING APPOINTMENT
    if ($this->input->method() === 'post') {

        $update_data = array(
            'wife_name' => trim((string) $this->input->post('wife_name', true)),
            'husband_name' => trim((string) $this->input->post('husband_name', true)),
            'country' => trim((string) $this->input->post('country', true)),
            'appoitmented_doctor' => trim((string) $this->input->post('appoitmented_doctor', true)),
            'appoitmented_date' => trim((string) $this->input->post('appoitmented_date', true)),
            'appoitmented_slot' => trim((string) $this->input->post('appoitmented_slot', true)),
            'status' => trim((string) $this->input->post('status', true))
        );

        $allowed_statuses = array(
            'booked',
            'in_clinic',
            'cancelled',
            'rescheduled',
            'visited',
            'consultation_done'
        );

        if (
            $update_data['wife_name'] === '' ||
            $update_data['country'] === '' ||
            $update_data['appoitmented_doctor'] === '' ||
            $update_data['appoitmented_date'] === '' ||
            $update_data['appoitmented_slot'] === '' ||
            !in_array($update_data['status'], $allowed_statuses, true)
        ) {
            $this->session->set_flashdata(
                'error',
                'Please fill all required fields correctly.'
            );

            redirect('accounts/patient_update_international?ID=' . $ID);
            return;
        }

        // VALIDATE DATE FORMAT
        $date = DateTime::createFromFormat(
            'Y-m-d',
            $update_data['appoitmented_date']
        );

        if (
            !$date ||
            $date->format('Y-m-d') !== $update_data['appoitmented_date']
        ) {
            $this->session->set_flashdata(
                'error',
                'Please select a valid appointment date.'
            );

            redirect('accounts/patient_update_international?ID=' . $ID);
            return;
        }

        // UPDATE EXISTING RECORD
        $updated = $this->Appointment_model
            ->update_existing_appointment($ID, $update_data);

        if ($updated === false) {

            $db_error = $this->db->error();

            log_message(
                'error',
                'International appointment update failed. ID: ' .
                $ID . ' | DB Error: ' . $db_error['message']
            );

            $this->session->set_flashdata(
                'error',
                'Appointment update failed. ' . $db_error['message']
            );

        } else {

            $this->session->set_flashdata(
                'success',
                'International appointment updated successfully.'
            );
        }

        redirect('accounts/patient_update_international?ID=' . $ID);
        return;
    }

    // FETCH DROPDOWN DATA
    $data['appointment'] = $appointment;

    $data['doctors'] = $this->Appointment_model
        ->get_active_doctors();

    // LOAD VIEW
    $template = get_header_template($logg['role']);

    $this->load->view($template['header']);
    $this->load->view('accounts/patient_update_international', $data);
    $this->load->view($template['footer']);
}

    // 3. Submit Adjustment Request (With Max Deduction Limit Check)
    public function save_wallet_adjustment() {
        $patient_id      = trim($this->input->post('patient_id'));
        $wallet_type     = $this->input->post('wallet_type'); // wallet_1 or wallet_2
        $adjustment_type = $this->input->post('adjustment_type'); // add or deduct
        $amount          = floatval($this->input->post('amount'));
        $remarks         = $this->input->post('remarks');
        $logged_user     = $_SESSION['logged_billing_manager']['employee_number'] ?? $_SESSION['logged_accountant']['employee_number'] ?? 'Staff';

        // 🎯 Deduction Validation Check (Backend Security)
        if ($adjustment_type == 'deduct') {
            $wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $patient_id))->row_array();
            $avail_bal = ($wallet_type == 'wallet_1') ? floatval($wallet['wallet_1_balance'] ?? 0) : floatval($wallet['wallet_2_balance'] ?? 0);

            if ($amount > $avail_bal) {
                $this->session->set_flashdata('error', 'Deduction Amount (₹' . number_format($amount, 2) . ') cannot be greater than Available Wallet Balance (₹' . number_format($avail_bal, 2) . ')!');
                redirect('accounts/wallet_adjustment');
                exit;
            }
        }

        // File Attachment Upload
        $file_name = '';
        if (!empty($_FILES['attachment']['name'])) {
            $config['upload_path']   = './uploads/adjustment_attachments/';
            $config['allowed_types'] = 'jpg|jpeg|png|pdf|doc|docx';
            $config['max_size']      = 5120; // 5MB
            $config['encrypt_name']  = TRUE;

            if (!is_dir($config['upload_path'])) {
                mkdir($config['upload_path'], 0777, true);
            }

            $this->load->library('upload', $config);
            if ($this->upload->do_upload('attachment')) {
                $upload_data = $this->upload->data();
                $file_name   = $upload_data['file_name'];
            }
        }

        $data = array(
            'patient_id'      => $patient_id,
            'wallet_type'     => $wallet_type,
            'adjustment_type' => $adjustment_type,
            'amount'          => $amount,
            'remarks'         => $remarks,
            'attachment'      => $file_name,
            'status'          => 'pending',
            'requested_by'    => $logged_user,
            'created_at'      => date('Y-m-d H:i:s')
        );

        $this->db->insert('hms_wallet_adjustments', $data);

        $this->session->set_flashdata('success', 'Adjustment request submitted successfully. Pending approval.');
        redirect('accounts/wallet_adjustment');
    }

    // 4. Approval & Cancellation Logic (Updates `hms_patient_wallets` & `hms_wallet_logs`)
    public function process_adjustment_status($id, $action) {
        $adj = $this->db->get_where('hms_wallet_adjustments', array('id' => $id))->row_array();
        $logged_user = $_SESSION['logged_billing_manager']['employee_number'] ?? $_SESSION['logged_accountant']['employee_number'] ?? 'Admin';

        if (!empty($adj) && $adj['status'] == 'pending') {
            
            if ($action == 'approve') {
                $patient_id  = $adj['patient_id'];
                $amount      = floatval($adj['amount']);
                $wallet_type = $adj['wallet_type']; // wallet_1 or wallet_2
                
                // Fetch Current Wallet State
                $wallet = $this->db->get_where('hms_patient_wallets', array('patient_id' => $patient_id))->row_array();
                
                if (!empty($wallet)) {
                    // Check Wallet Freeze Guard
                    if (isset($wallet['is_frozen']) && $wallet['is_frozen'] == 1) {
                        $this->session->set_flashdata('error', 'Operation blocked! Patient wallet is currently frozen.');
                        redirect('accounts/wallet_adjustment');
                    }

                    $opening_w1 = floatval($wallet['wallet_1_balance']);
                    $opening_w2 = floatval($wallet['wallet_2_balance']);

                    if ($wallet_type == 'wallet_1') {
                        $closing_w1 = ($adj['adjustment_type'] == 'add') ? ($opening_w1 + $amount) : ($opening_w1 - $amount);
                        if ($closing_w1 < 0) { $closing_w1 = 0; }
                        $closing_w2 = $opening_w2;
                        $update_col = 'wallet_1_balance';
                        $target_closing = $closing_w1;
                    } else {
                        $closing_w2 = ($adj['adjustment_type'] == 'add') ? ($opening_w2 + $amount) : ($opening_w2 - $amount);
                        if ($closing_w2 < 0) { $closing_w2 = 0; }
                        $closing_w1 = $opening_w1;
                        $update_col = 'wallet_2_balance';
                        $target_closing = $closing_w2;
                    }

                    // 🎯 1. Update `hms_patient_wallets`
                    $this->db->where('patient_id', $patient_id)->update('hms_patient_wallets', array(
                        $update_col  => $target_closing,
                        'updated_at' => date('Y-m-d H:i:s')
                    ));

                    // 🎯 2. Insert Transaction Record in `hms_wallet_logs`
                    $action_label   = ($adj['adjustment_type'] == 'add') ? 'ADJUSTMENT_CREDIT' : 'ADJUSTMENT_DEBIT';
                    $receipt_no     = 'ADJ/' . date('Ymd') . '/' . $id; // Unique Receipt No Fix

                    $log_data = array(
                        'patient_id'     => $patient_id,
                        'amount'         => $amount,
                        'action_type'    => $action_label,
                        'opening_w1'     => $opening_w1,
                        'closing_w1'     => $closing_w1,
                        'opening_w2'     => $opening_w2,
                        'closing_w2'     => $closing_w2,
                        'reference_id'   => 'ADJ-REQ-' . $id,
                        'receipt_number' => $receipt_no,
                        'payment_method' => 'Adjustment',
                        'remarks'        => 'Wallet Adjustment (' . strtoupper($adj['adjustment_type']) . '). Reason: ' . $adj['remarks'],
                        'screenshot'     => $adj['attachment'],
                        'created_by'     => $adj['requested_by'],
                        'approved_by'    => $logged_user,
                        'created_at'     => date('Y-m-d H:i:s'),
                        'updated_at'     => date('Y-m-d H:i:s'),
                        'status'         => 'completed'
                    );

                    $this->db->insert('hms_wallet_logs', $log_data);

                    // 🎯 3. Update Status in `hms_wallet_adjustments`
                    $this->db->where('id', $id)->update('hms_wallet_adjustments', array(
                        'status'      => 'approved',
                        'approved_by' => $logged_user,
                        'updated_at'  => date('Y-m-d H:i:s')
                    ));

                    $this->session->set_flashdata('success', 'Adjustment Request Approved & Wallet Balance Updated!');
                } else {
                    $this->session->set_flashdata('error', 'Patient wallet record not found.');
                }

            } elseif ($action == 'cancel') {
                // Cancel Action
                $this->db->where('id', $id)->update('hms_wallet_adjustments', array(
                    'status'      => 'cancelled',
                    'approved_by' => $logged_user,
                    'updated_at'  => date('Y-m-d H:i:s')
                ));

                $this->session->set_flashdata('error', 'Adjustment Request Cancelled.');
            }
        }

        redirect('accounts/wallet_adjustment');
    }

	public function export_wallet_requests() {
    $logg = checklogin();
    if($logg['status'] == true) {

        // 1. Filter Inputs Capture
        $filter_patient_id  = $this->input->get('patient_id') ? trim($this->input->get('patient_id')) : '';
        $filter_status      = $this->input->get('status') ? trim($this->input->get('status')) : '';
        $filter_start_date  = $this->input->get('start_date') ? trim($this->input->get('start_date')) : '';
        $filter_end_date    = $this->input->get('end_date') ? trim($this->input->get('end_date')) : '';
        $filter_remarks     = $this->input->get('remarks') ? trim($this->input->get('remarks')) : '';
        $filter_action_type = $this->input->get('action_type') ? trim($this->input->get('action_type')) : '';

        // 2. WHERE Conditions
        $where_logs = " WHERE 1=1";
        $where_adj  = " WHERE 1=1";

        if (!empty($filter_patient_id)) {
            $pid_esc = $this->db->escape_like_str($filter_patient_id);
            $where_logs .= " AND wl.patient_id LIKE '%{$pid_esc}%'";
            $where_adj  .= " AND adj.patient_id LIKE '%{$pid_esc}%'";
        }
        if (!empty($filter_status)) {
            $st_esc = $this->db->escape($filter_status);
            $where_logs .= " AND wl.status = {$st_esc}";
            $where_adj  .= " AND adj.status = {$st_esc}";
        }
        if (!empty($filter_start_date)) {
            $sdate_esc = $this->db->escape($filter_start_date);
            $where_logs .= " AND DATE(wl.created_at) >= {$sdate_esc}";
            $where_adj  .= " AND DATE(adj.created_at) >= {$sdate_esc}";
        }
        if (!empty($filter_end_date)) {
            $edate_esc = $this->db->escape($filter_end_date);
            $where_logs .= " AND DATE(wl.created_at) <= {$edate_esc}";
            $where_adj  .= " AND DATE(adj.created_at) <= {$edate_esc}";
        }
        if (!empty($filter_remarks)) {
            $rem_esc = $this->db->escape_like_str($filter_remarks);
            $where_logs .= " AND wl.remarks LIKE '%{$rem_esc}%'";
            $where_adj  .= " AND adj.remarks LIKE '%{$rem_esc}%'";
        }
        if (!empty($filter_action_type)) {
            $act_esc = $this->db->escape($filter_action_type);
            $where_logs .= " AND wl.action_type = {$act_esc}";
            $where_adj  .= " AND 1=0";
        }

        // 3. Main Query (Without Limit for Full Data Export)
        $main_sql = "
            SELECT * FROM (
                SELECT 
                    'transfer' AS record_type,
                    wl.log_id AS record_id,
                    wl.patient_id,
                    wl.amount,
                    wl.action_type,
                    wl.status,
                    wl.remarks,
                    wl.created_at,
                    wl.updated_at,
                    wl.created_by,
                    wl.approved_by,
                    emp1.name AS employee_name,
                    emp2.name AS approved_by_name
                FROM hms_wallet_logs wl
                LEFT JOIN hms_employees emp1 ON emp1.employee_number = wl.created_by
                LEFT JOIN hms_employees emp2 ON emp2.employee_number = wl.approved_by
                {$where_logs}

                UNION ALL

                SELECT 
                    'adjustment' AS record_type,
                    adj.id AS record_id,
                    adj.patient_id,
                    adj.amount,
                    'ADJUSTMENT' AS action_type,
                    adj.status,
                    CONCAT('Adjustment (', UPPER(adj.wallet_type), ' - ', UPPER(adj.adjustment_type), '): ', adj.remarks) AS remarks,
                    adj.created_at,
                    adj.updated_at,
                    adj.requested_by AS created_by,
                    adj.approved_by,
                    emp3.name AS employee_name,
                    emp4.name AS approved_by_name
                FROM hms_wallet_adjustments adj
                LEFT JOIN hms_employees emp3 ON emp3.employee_number = adj.requested_by
                LEFT JOIN hms_employees emp4 ON emp4.employee_number = adj.approved_by
                {$where_adj}
            ) AS combined
            ORDER BY created_at DESC
        ";

        $records = $this->db->query($main_sql)->result_array();

        // 4. File Headers Set karein Excel CSV Download ke liye
        $filename = "Wallet_Requests_" . date('Ymd_His') . ".csv";
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $output = fopen("php://output", "w");

        // CSV Heading Row Write karein
        fputcsv($output, [
            'S.No.',
            'Record ID',
            'Record Type',
            'Action Type',
            'Patient ID',
            'Amount (INR)',
            'Requested By',
            'Requested Date & Time',
            'Remarks',
            'Action By',
            'Action Date & Time',
            'Status'
        ]);

        // CSV Rows Write karein
        if (!empty($records)) {
            $count = 1;
            foreach ($records as $row) {
                $requested_by = !empty($row['employee_name']) ? $row['employee_name'] : (!empty($row['created_by']) ? $row['created_by'] : 'System');
                $action_by    = !empty($row['approved_by_name']) ? $row['approved_by_name'] : (!empty($row['approved_by']) ? $row['approved_by'] : '-');
                $action_time  = ($row['status'] != 'pending' && !empty($row['updated_at'])) ? date('d-m-Y h:i A', strtotime($row['updated_at'])) : '-';

                fputcsv($output, [
                    $count++,
                    '#' . $row['record_id'],
                    strtoupper($row['record_type']),
                    $row['action_type'],
                    $row['patient_id'],
                    number_format($row['amount'], 2, '.', ''),
                    $requested_by,
                    date('d-m-Y h:i A', strtotime($row['created_at'])),
                    $row['remarks'],
                    $action_by,
                    $action_time,
                    ucfirst($row['status'])
                ]);
            }
        }

        fclose($output);
        exit;

    } else {
        redirect(base_url());
    }
}

public function assessment_form_details(){
		$logg = checklogin();
		if($logg['status'] == true){
			$data = array();
			$template = get_header_template($logg['role']);
			$this->load->view($template['header']);
			$this->load->view('accounts/assessment_form_details', $data);
			$this->load->view($template['footer']);
		}else{
			header("location:" .base_url(). "");
			die();
		}
	}



	// =====================================================================
	// Account Ledger popup: receipt ki billing (biller, date, counsellor, accounts approval)
	// + saare partial payments (biller, date, approved by + date)
	// URL: accounts/receipt_payment_info  (POST receipt)
	// =====================================================================
	// Logged-in user ka employee number (accountant / billing manager / counsellor / admin ...)
	private function current_employee_number()
	{
		foreach (array('logged_accountant', 'logged_billing_manager', 'logged_counselor', 'logged_central_stock_manager', 'logged_administrator', 'logged_doctor') as $k) {
			if (!empty($_SESSION[$k]['employee_number'])) { return $_SESSION[$k]['employee_number']; }
			if (!empty($_SESSION[$k]['id'])) { return $_SESSION[$k]['id']; }
		}
		return '';
	}

	private function ensure_payment_approver_cols()
	{
		foreach (array('approved_by' => 'VARCHAR(50) NULL', 'approved_at' => 'DATETIME NULL') as $c => $def) {
			if (!$this->db->field_exists($c, 'hms_patient_payments')) {
				$dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->db->query("ALTER TABLE `hms_patient_payments` ADD `$c` $def");
				$this->db->db_debug = $dbg;
			}
		}
		$this->db->data_cache = array();
	}

	private function record_payment_approver($payment_id)
	{
		$this->ensure_payment_approver_cols();
		if (!$this->db->field_exists('approved_by', 'hms_patient_payments')) { return; }
		date_default_timezone_set('Asia/Kolkata');
		$dbg = $this->db->db_debug; $this->db->db_debug = false;
		$this->db->where('ID', (int)$payment_id)->update('hms_patient_payments', array('approved_by' => $this->current_employee_number(), 'approved_at' => date('Y-m-d H:i:s')));
		$this->db->db_debug = $dbg;
	}

	public function receipt_payment_info()
	{
		header('Content-Type: application/json');
		$logg = checklogin();
		if ($logg['status'] != true) { echo json_encode(array('status' => 'error', 'message' => 'Session expired')); exit; }
		$receipt = trim((string)$this->input->post('receipt', true));
		if ($receipt === '') { echo json_encode(array('status' => 'error', 'message' => 'Receipt missing')); exit; }

		$self = $this;
		$emp = function ($id) use ($self) {
			if ($id === null || $id === '' || $id === '0') { return ''; }
			$n = $self->get_employee_name($id);
			return (is_string($n) && $n !== '' && $n !== 'Array') ? $n : '';
		};
		$dt = function ($d) {
			$d = trim((string)$d);
			// approve_payment ke purane code me date_default_timezone_set() ka "1" date ke aage jud jaata tha: "12026-03-06 18:15:37"
			if (preg_match('/^1(\d{4}-\d{2}-\d{2}.*)$/', $d, $mm)) { $d = $mm[1]; }
			if ($d === '' || strpos($d, '0000-00-00') === 0) { return ''; }
			$t = strtotime($d);
			return $t ? date('d-m-Y H:i', $t) : (string)$d;   // 24 ghante (AM/PM nahi)
		};

		$b = $this->db->where('receipt_number', $receipt)->order_by('ID', 'ASC')->limit(1)->get('hms_patient_procedure')->row_array();
		$billing = array();
		if (!empty($b)) {
			$billing = array(
				'biller'          => $emp(isset($b['biller_id']) ? $b['biller_id'] : ''),
				'billing_date'    => $dt(isset($b['on_date']) ? $b['on_date'] : ''),
				'counsellor'      => $emp(isset($b['counselor_id']) ? $b['counselor_id'] : ''),
				'counsellor_date' => $dt(isset($b['clearance_date']) ? $b['clearance_date'] : ''),
				'fc1_status'      => isset($b['clearance']) ? (string)$b['clearance'] : '',
				'accountant'      => $emp(isset($b['accountant_id']) ? $b['accountant_id'] : ''),
				'accountant_date' => $dt(!empty($b['accclearance_date']) && strpos((string)$b['accclearance_date'], '0000-00-00') !== 0 ? $b['accclearance_date'] : (isset($b['modified_on']) ? $b['modified_on'] : '')),
				'status'          => isset($b['status']) ? (string)$b['status'] : '',
			);
			// Booking: billing ke waqt mila amount
			$modes = array();
			foreach (array('cash_payment' => 'Cash', 'card_payment' => 'Card', 'upi_payment' => 'UPI', 'neft_payment' => 'NEFT', 'wallet_payment' => 'Wallet') as $c => $l) {
				if (isset($b[$c]) && (float)$b[$c] > 0) { $modes[] = $l.' '.number_format((float)$b[$c], 2); }
			}
			$billing['booking'] = array(
				'amount'   => (float)(isset($b['payment_done']) ? $b['payment_done'] : 0),
				'date'     => $dt(isset($b['on_date']) ? $b['on_date'] : ''),
				'mode'     => !empty($modes) ? implode(', ', $modes) : (isset($b['payment_method']) ? (string)$b['payment_method'] : ''),
				'txn'      => isset($b['transaction_id']) ? (string)$b['transaction_id'] : '',
				'txn_img'  => isset($b['transaction_img']) ? (string)$b['transaction_img'] : '',
				'biller'   => $billing['biller'],
				'series'   => isset($b['series_number']) ? (string)$b['series_number'] : '',
				'package'  => isset($b['totalpackage']) ? (string)$b['totalpackage'] : '',
				'discount' => isset($b['discount_amount']) ? (string)$b['discount_amount'] : '',
				'fees'     => isset($b['fees']) ? (string)$b['fees'] : '',
			);
		}
		$has_appr = $this->db->field_exists('approved_by', 'hms_patient_payments');

		$payments = array();
		foreach ($this->db->where('billing_id', $receipt)->order_by('on_date', 'ASC')->order_by('ID', 'ASC')->get('hms_patient_payments')->result_array() as $p) {
			$st = (string)$p['status'];
			$payments[] = array(
				'ref'           => isset($p['refrence_number']) ? (string)$p['refrence_number'] : '',
				'biller'        => $emp(isset($p['biller']) ? $p['biller'] : ''),
				'date'          => $dt($p['on_date']),
				'status'        => ($st === '1' ? 'Approved' : ($st === '0' ? 'Pending' : ucfirst($st))),
				// Approver: naya column approved_by (approve karte waqt save hota hai); purane payments me sirf time (modified_on)
				'approved_by'   => ($st === '1' || stripos($st, 'approv') === 0) ? $emp($has_appr && !empty($p['approved_by']) ? $p['approved_by'] : '') : '',
				'approved_date' => ($st === '1' || stripos($st, 'approv') === 0) ? $dt($has_appr && !empty($p['approved_at']) ? $p['approved_at'] : (isset($p['modified_on']) ? $p['modified_on'] : '')) : '',
			);
		}
		// Wallet se hue payments: hms_wallet_logs (is receipt ke)
		$wallet_logs = array();
		if ($this->db->table_exists('hms_wallet_logs')) {
			$wl_q = $this->db->where('receipt_number', $receipt)->order_by('created_at', 'ASC')->get('hms_wallet_logs');
			foreach (($wl_q ? $wl_q->result_array() : array()) as $w) {
				$shot = isset($w['screenshot']) ? trim((string)$w['screenshot']) : '';
				if ($shot !== '' && !preg_match('#^https?://#i', $shot)) { $shot = base_url(ltrim($shot, '/')); }
				$wst = strtolower(trim((string)$w['status']));
				$wallet_logs[] = array(
					'log_id'     => $w['log_id'],
					'reference'  => isset($w['reference_id']) ? (string)$w['reference_id'] : '',
					'action'     => ucwords(str_replace('_', ' ', (string)$w['action_type'])),
					'amount'     => (float)$w['amount'],
					'method'     => (string)$w['payment_method'],
					'w1'         => array((float)$w['opening_w1'], (float)$w['closing_w1']),
					'w2'         => array((float)$w['opening_w2'], (float)$w['closing_w2']),
					'remarks'    => trim((string)$w['remarks'].((string)$w['update_remarks'] !== '' ? ' | '.$w['update_remarks'] : '')),
					'screenshot' => $shot,
					'created_by' => $emp($w['created_by']) !== '' ? $emp($w['created_by']) : (string)$w['created_by'],
					'created_at' => $dt($w['created_at']),
					'approved_by'=> $emp($w['approved_by']) !== '' ? $emp($w['approved_by']) : (string)$w['approved_by'],
					'updated_at' => $dt($w['updated_at']),
					'status'     => ($wst === '1' ? 'Approved' : ($wst === '0' ? 'Pending' : ucfirst((string)$w['status']))),
				);
			}
		}
		echo json_encode(array('status' => 'success', 'billing' => $billing, 'payments' => $payments, 'wallet_logs' => $wallet_logs));
		exit;
	}

	/* =========================================================
   ASSESSMENT RESCHEDULE - PERMISSION
   ========================================================= */

private function assessment_reschedule_allowed()
{
    return (
        !empty($this->session->userdata('logged_doctor')) ||
        !empty($this->session->userdata('logged_counselor'))
    );
}

private function assessment_reschedule_json($data)
{
    $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode($data));
}

/* =========================================================
   LOAD CENTRES
   ========================================================= */

public function assessment_reschedule_centers()
{
    $logg = checklogin();

    if (empty($logg['status']) || !$this->assessment_reschedule_allowed()) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Permission denied.'
        ));
    }

    $centers = $this->db
        ->select('center_number, center_name')
        ->from('hms_centers')
        ->where('status', '1')
        ->order_by('center_name', 'ASC')
        ->get()
        ->result_array();

    return $this->assessment_reschedule_json(array(
        'success' => true,
        'centers' => $centers
    ));
}

/* =========================================================
   LOAD LATEST APPOINTMENT FOR PATIENT
   ========================================================= */

public function assessment_reschedule_latest()
{
    $logg = checklogin();

    if (empty($logg['status']) || !$this->assessment_reschedule_allowed()) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Permission denied.'
        ));
    }

    $patient_id = trim((string)$this->input->get('patient_id', true));

    if ($patient_id === '') {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Patient ID is required.'
        ));
    }

    // Latest appointment means highest appointment ID.
    $appointment = $this->db
        ->where('paitent_id', $patient_id)
        ->order_by('ID', 'DESC')
        ->limit(1)
        ->get('hms_appointments')
        ->row_array();

    if (empty($appointment)) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'No appointment found for this patient.'
        ));
    }

    if (!empty($appointment['appoitmented_date'])) {
        $appointment['appoitmented_date'] =
            date('Y-m-d', strtotime($appointment['appoitmented_date']));
    }

    return $this->assessment_reschedule_json(array(
        'success' => true,
        'appointment' => $appointment
    ));
}

/* =========================================================
   LOAD AVAILABLE SLOTS FOR RESCHEDULE
   ========================================================= */

public function assessment_reschedule_slots()
{
    $logg = checklogin();

    if (empty($logg['status']) || !$this->assessment_reschedule_allowed()) {
        echo '<option value="">Permission denied</option>';
        return;
    }

    $booking_date = trim((string)$this->input->post('selected', true));
    $doctor_id = (int)$this->input->post('appoitmented_doctor', true);
    $appointment_id = (int)$this->input->post('appointment_id', true);

    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date) ||
        !$doctor_id
    ) {
        echo '<option value="">Select doctor and date first</option>';
        return;
    }

    $date_parts = explode('-', $booking_date);

    if (!checkdate((int)$date_parts[1], (int)$date_parts[2], (int)$date_parts[0])) {
        echo '<option value="">Invalid date</option>';
        return;
    }

    $this->load->model('doctors_model');

    $doctor = $this->db
        ->select('ID')
        ->from('hms_doctors')
        ->where('ID', $doctor_id)
        ->where('status', '1')
        ->get()
        ->row_array();

    if (empty($doctor)) {
        echo '<option value="">Invalid or inactive doctor</option>';
        return;
    }

    // Check doctor's holiday using existing model method.
    $doctor_on_holiday = $this->doctors_model->doctor_on_holiday($doctor_id);

    if (!empty($doctor_on_holiday) && $doctor_on_holiday != '0') {
        $holiday_dates = explode(' - ', $doctor_on_holiday);

        if (count($holiday_dates) === 2) {
            $holiday_start = date('Y-m-d', strtotime(trim($holiday_dates[0])));
            $holiday_end = date('Y-m-d', strtotime(trim($holiday_dates[1])));

            if ($booking_date >= $holiday_start && $booking_date <= $holiday_end) {
                echo '<option value="">No slot - doctor on holiday</option>';
                return;
            }
        }
    }

    $booking_day = strtolower(date('l', strtotime($booking_date)));

    $doctor_slots = $this->doctors_model->doctor_slots($doctor_id, $booking_day);

    if (is_string($doctor_slots)) {
        $doctor_slots = @unserialize($doctor_slots);
        if (!is_array($doctor_slots)) {
            $doctor_slots = json_decode(
                $this->doctors_model->doctor_slots($doctor_id, $booking_day),
                true
            );
        }
    }

    if (!is_array($doctor_slots) || empty($doctor_slots)) {
        echo '<option value="">No available slot for '
            . html_escape(ucfirst($booking_day)) . '</option>';
        return;
    }

    /*
     * Read bookings directly so appointment ID is available.
     * Exclude cancelled appointments and the appointment being edited.
     */
    $booked_rows = $this->db
        ->select('ID, appoitmented_slot')
        ->from('hms_appointments')
        ->where('appoitmented_doctor', (string)$doctor_id)
        ->where('DATE(appoitmented_date) =', $booking_date, false)
        ->where('LOWER(status) !=', 'cancelled')
        ->get()
        ->result_array();

    $booked_slots = array();

    foreach ($booked_rows as $booked) {
        if ((int)$booked['ID'] === $appointment_id) {
            continue;
        }

        if (isset($booked['appoitmented_slot']) && trim($booked['appoitmented_slot']) !== '') {
            $booked_slots[] = trim($booked['appoitmented_slot']);
        }
    }

    $html = '<option value="">--Select slot--</option>';
    $available_count = 0;

    foreach ($doctor_slots as $slot) {
        $slot_value = is_array($slot)
            ? (isset($slot['slot']) ? $slot['slot'] : '')
            : $slot;

        $slot_value = trim((string)$slot_value);

        if ($slot_value !== '' && !in_array($slot_value, $booked_slots, true)) {
            $html .= '<option value="' . html_escape($slot_value) . '">'
                . html_escape($slot_value) . '</option>';
            $available_count++;
        }
    }

    if ($available_count === 0) {
        echo '<option value="">All slots booked for this date</option>';
        return;
    }

    echo $html;
}

/* =========================================================
   SAVE RESCHEDULE - UPDATE EXISTING APPOINTMENT ONLY
   ========================================================= */




public function assessment_reschedule_save()
{
    $logg = checklogin();

    if (empty($logg['status']) || !$this->assessment_reschedule_allowed()) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Permission denied.'
        ));
    }

    $appointment_id = (int) $this->input->post('appointment_id', true);
    $patient_id = trim((string) $this->input->post('patient_id', true));
    $center_number = trim((string) $this->input->post('appoitment_for', true));
    $doctor_id = (int) $this->input->post('appoitmented_doctor', true);
    $appointment_date = trim((string) $this->input->post('appoitmented_date', true));
    $appointment_slot = trim((string) $this->input->post('appoitmented_slot', true));
    $counsellor = trim((string) $this->input->post('councellor', true));
    $purpose = trim((string) $this->input->post('reason_of_visit', true));
    $reason = trim((string) $this->input->post('reschedule_reason', true));

    if (
        $appointment_id <= 0 ||
        $patient_id === '' ||
        $center_number === '' ||
        $doctor_id <= 0 ||
        $appointment_date === '' ||
        $appointment_slot === '' ||
        $reason === ''
    ) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Please fill all required fields.'
        ));
    }

    if (strlen($reason) > 1000 || strlen($counsellor) > 150) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Reason or counsellor field is too long.'
        ));
    }

    $date_parts = explode('-', $appointment_date);

    if (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date) ||
        count($date_parts) !== 3 ||
        !checkdate(
            (int) $date_parts[1],
            (int) $date_parts[2],
            (int) $date_parts[0]
        )
    ) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Invalid appointment date.'
        ));
    }

    if (!in_array($purpose, array('TVS', 'FOLLOW UP VISIT', 'PROCEDURE'), true)) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Invalid follow-up purpose.'
        ));
    }

    // Fetch existing appointment.
    $old = $this->db
        ->where('ID', $appointment_id)
        ->where('paitent_id', $patient_id)
        ->limit(1)
        ->get('hms_appointments')
        ->row_array();

    if (empty($old)) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Appointment not found. Please refresh the page.'
        ));
    }

    // Identify International appointment from hms_appointments.nationality.
    $is_international = (
        !empty($old['nationality']) &&
        strtolower(trim($old['nationality'])) === 'non-indian'
    );

    // Verify active centre.
    $center = $this->db
        ->select('center_number')
        ->from('hms_centers')
        ->where('center_number', $center_number)
        ->where('status', '1')
        ->limit(1)
        ->get()
        ->row_array();

    if (empty($center)) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Please select a valid active centre.'
        ));
    }

    // Verify doctor belongs to selected centre.
    $this->load->model('Accounts_model');

    $center_doctors = $this->Accounts_model->center_doctors($center_number);
    $doctor_allowed = false;

    if (is_array($center_doctors)) {
        foreach ($center_doctors as $doctor) {
            if (
                isset($doctor['ID']) &&
                (int) $doctor['ID'] === $doctor_id
            ) {
                $doctor_allowed = true;
                break;
            }
        }
    }

    if (!$doctor_allowed) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Selected doctor is not assigned to this centre.'
        ));
    }

    $this->load->model('doctors_model');

    // Validate configured slots.
    $day = strtolower(date('l', strtotime($appointment_date)));
    $doctor_slots = $this->doctors_model->doctor_slots($doctor_id, $day);

    if (is_string($doctor_slots)) {
        $decoded_slots = @unserialize($doctor_slots);

        if (!is_array($decoded_slots)) {
            $decoded_slots = json_decode($doctor_slots, true);
        }

        $doctor_slots = is_array($decoded_slots)
            ? $decoded_slots
            : array();
    }

    $slot_exists = false;

    if (is_array($doctor_slots)) {
        foreach ($doctor_slots as $slot) {
            $slot_value = is_array($slot)
                ? (isset($slot['slot']) ? $slot['slot'] : '')
                : $slot;

            if (trim((string) $slot_value) === $appointment_slot) {
                $slot_exists = true;
                break;
            }
        }
    }

    if (!$slot_exists) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Selected slot is not configured for this doctor and day.'
        ));
    }

    // Check doctor holiday.
    $holiday = $this->doctors_model->doctor_on_holiday($doctor_id);

    if (!empty($holiday) && $holiday != '0') {
        $holiday_dates = explode(' - ', $holiday);

        if (count($holiday_dates) === 2) {
            $holiday_start = date('Y-m-d', strtotime(trim($holiday_dates[0])));
            $holiday_end = date('Y-m-d', strtotime(trim($holiday_dates[1])));

            if (
                $appointment_date >= $holiday_start &&
                $appointment_date <= $holiday_end
            ) {
                return $this->assessment_reschedule_json(array(
                    'success' => false,
                    'message' => 'Doctor is on holiday for the selected date.'
                ));
            }
        }
    }

    // Prevent double booking.
    $conflict = $this->db
        ->from('hms_appointments')
        ->where('appoitmented_doctor', (string) $doctor_id)
        ->where('DATE(appoitmented_date) =', $appointment_date, false)
        ->where('appoitmented_slot', $appointment_slot)
        ->where('ID !=', $appointment_id)
        ->where_not_in('status', array('cancelled', 'CANCELLED'))
        ->count_all_results();

    if ($conflict > 0) {
        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'This slot has already been booked. Please choose another slot.'
        ));
    }

    // Generate secure approval token.
    try {
        $token = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        log_message('error', 'Could not generate appointment reschedule token.');

        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Could not generate approval token.'
        ));
    }

    $token_hash = hash('sha256', $token);

    $center_details = $this->db
        ->select('center_name')
        ->from('hms_centers')
        ->where('center_number', $center_number)
        ->where('status', '1')
        ->limit(1)
        ->get()
        ->row_array();

    $doctor_details = $this->db
        ->select('name')
        ->from('hms_doctors')
        ->where('ID', $doctor_id)
        ->limit(1)
        ->get()
        ->row_array();

    $patient_name = !empty($old['wife_name'])
        ? $old['wife_name']
        : $patient_id;

    $center_name = !empty($center_details['center_name'])
        ? $center_details['center_name']
        : $center_number;

    $doctor_name = !empty($doctor_details['name'])
        ? $doctor_details['name']
        : (string) $doctor_id;

    $approval_url = site_url(
        'accounts/assessment_reschedule_approve/' . $token
    );

    $disapproval_url = site_url(
        'accounts/assessment_reschedule_disapprove/' . $token
    );

    // Save request only. Appointment will not be updated here.
    $request_data = array(
        'appointment_id'         => $appointment_id,
        'patient_id'             => $patient_id,
        'patient_name'           => $patient_name,
        'proposed_center_number' => $center_number,
        'proposed_doctor_id'     => $doctor_id,
        'proposed_date'          => $appointment_date,
        'proposed_slot'          => $appointment_slot,
        'proposed_counsellor'    => $counsellor,
        'proposed_purpose'       => $purpose,
        'reason'                 => $reason,
        'token_hash'             => $token_hash,
        'approval_status'        => 'email_pending',
        'created_at'             => date('Y-m-d H:i:s'),
        'expires_at'             => date('Y-m-d H:i:s', strtotime('+48 hours'))
    );

    $this->db->trans_begin();

    $this->db->insert(
        'hms_appointment_reschedule_requests',
        $request_data
    );

    $request_id = $this->db->insert_id();

    if (!$request_id || $this->db->trans_status() === false) {
        $this->db->trans_rollback();

        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Could not save approval request.'
        ));
    }

    // Save counsellor history.
    $old_counsellor = isset($old['councellor'])
        ? trim((string) $old['councellor'])
        : '';

    $new_counsellor = trim((string) $counsellor);

    $updated_by = !empty($logg['name'])
        ? $logg['name']
        : 'Assessment Reschedule';

    $log_data = array(
        'appointment_id' => $appointment_id,
        'field_changed'  => 'Counsellor',
        'old_value'      => $old_counsellor,
        'new_value'      => $new_counsellor,
        'updated_by'     => $updated_by,
        'created_at'     => date('Y-m-d H:i:s'),
        'reason'         => 'Reschedule approval request #' . $request_id
    );

    $this->db->insert('hms_appointment_logs', $log_data);

    if ($this->db->trans_status() === false) {
        $this->db->trans_rollback();

        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Could not save counsellor history.'
        ));
    }

    $this->db->trans_commit();

    // Prepare approval email.
    $email_data = array(
        'patient_id'      => $patient_id,
        'patient_name'    => $patient_name,
        'center_name'     => $center_name,
		'lead_source' => !empty($old['lead_source'])
    ? $old['lead_source']
    : 'N/A',
        'doctor_name'     => $doctor_name,
        'counsellor'      => $counsellor,
        'old_date'        => isset($old['appoitmented_date'])
            ? $old['appoitmented_date'] : '',
        'old_slot'        => isset($old['appoitmented_slot'])
            ? $old['appoitmented_slot'] : '',
        'new_date'        => $appointment_date,
        'new_slot'        => $appointment_slot,
        'purpose'         => $purpose,
        'reason'          => $reason,
        'approval_url'    => $approval_url,
        'disapproval_url' => $disapproval_url
    );

    // Select email template according to appointment nationality.
    $email_template = $is_international
        ? 'emails/appointment_reschedule_approval_international'
        : 'emails/appointment_reschedule_approval';

    $message = $this->load->view(
        $email_template,
        $email_data,
        true
    );

    // Escape values used in HTML template.
    $replacements = array(
        '{{patient_id}}'      => html_escape($patient_id),
        '{{patient_name}}'    => html_escape($patient_name),
        '{{center_name}}'     => html_escape($center_name),
        '{{doctor_name}}'     => html_escape($doctor_name),
		'{{lead_source}}' => $email_data['lead_source'],
        '{{counsellor}}'      => html_escape($counsellor),
        '{{old_date}}'        => html_escape($email_data['old_date']),
        '{{old_slot}}'        => html_escape($email_data['old_slot']),
        '{{new_date}}'        => html_escape($appointment_date),
        '{{new_slot}}'        => html_escape($appointment_slot),
        '{{purpose}}'         => html_escape($purpose),
        '{{reason}}'          => nl2br(html_escape($reason)),
        '{{approval_url}}'    => $approval_url,
        '{{disapproval_url}}' => $disapproval_url
    );

    $message = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $message
    );

    // Send approval email.
    $this->load->library('email');

    $email_config = array(
        'protocol'    => 'smtp',
        'smtp_host'   => config_item('mail_host'),
        'smtp_user'   => config_item('mail_username'),
        'smtp_pass'   => config_item('mail_password'),
        'smtp_port'   => (int) config_item('mail_port'),
        'smtp_crypto' => config_item('mail_encryption'),
        'mailtype'    => 'html',
        'charset'     => 'utf-8',
        'newline'     => "\r\n",
        'crlf'        => "\r\n"
    );

    $this->email->initialize($email_config);

    $this->email->from(
        config_item('mail_from_emailid'),
        config_item('mail_from_name')
    );

    $this->email->to('divyansh@indiaivf.in');

   $email_subject = $is_international
    ? 'International Patient Center Allotment Request - ' . $patient_id
    : 'Camp Center Allotment Request - ' . $patient_id;

$this->email->subject($email_subject);

    $this->email->message($message);

    $sent = $this->email->send();

    if (!$sent) {
        $debug_message = $this->email->print_debugger();

        log_message(
            'error',
            'Reschedule approval email failed. Request ID: ' . $request_id
        );

        log_message(
            'error',
            'Reschedule SMTP Debug: ' . $debug_message
        );

        $this->db
            ->where('id', $request_id)
            ->update(
                'hms_appointment_reschedule_requests',
                array('approval_status' => 'email_failed')
            );

        return $this->assessment_reschedule_json(array(
            'success' => false,
            'message' => 'Email could not be sent. Appointment has not been changed.'
        ));
    }

    $this->db
        ->where('id', $request_id)
        ->where('approval_status', 'email_pending')
        ->update(
            'hms_appointment_reschedule_requests',
            array('approval_status' => 'pending')
        );

    return $this->assessment_reschedule_json(array(
        'success' => true,
        'message' => 'Approval request sent. Appointment will change after approval.'
    ));
}



public function assessment_reschedule_approve($token = '')
{
    $this->load->helper(array('url', 'form'));

    // Default existing Indian/Camp view.
    $approval_view = 'emails/reschedule_approval_confirm';

    // Validate token.
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'Invalid approval link.'
            )
        );
    }

    $token_hash = hash('sha256', $token);

    // GET: Show confirmation page only.
    if ($this->input->method(true) !== 'POST') {
        $request = $this->db
            ->where('token_hash', $token_hash)
            ->where('approval_status', 'pending')
            ->limit(1)
            ->get('hms_appointment_reschedule_requests')
            ->row_array();

        if (empty($request)) {
            return $this->load->view(
                $approval_view,
                array(
                    'request' => array(),
                    'token' => '',
                    'message' => 'This request is invalid, already processed, or unavailable.'
                )
            );
        }

        // Select correct view using original appointment nationality.
        $approval_view = $this->is_international_reschedule(
            $request['appointment_id'],
            $request['patient_id']
        )
            ? 'emails/reschedule_approval_confirm_international'
            : 'emails/reschedule_approval_confirm';

        if (strtotime($request['expires_at']) < time()) {
            $this->db
                ->where('id', $request['id'])
                ->where('approval_status', 'pending')
                ->update(
                    'hms_appointment_reschedule_requests',
                    array('approval_status' => 'expired')
                );

            return $this->load->view(
                $approval_view,
                array(
                    'request' => array(),
                    'token' => '',
                    'message' => 'This approval link has expired.'
                )
            );
        }

        return $this->load->view(
            $approval_view,
            array(
                'request' => $request,
                'token' => $token,
                'message' => ''
            )
        );
    }

    // POST: Explicit approval confirmation required.
    if ($this->input->post('confirm_approval') !== '1') {
        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'Please use the Confirm Approval button.'
            )
        );
    }

    $this->db->trans_begin();

    // Lock request row.
    $request = $this->db->query(
        "SELECT * FROM hms_appointment_reschedule_requests
         WHERE token_hash = ?
         LIMIT 1 FOR UPDATE",
        array($token_hash)
    )->row_array();

    if (
        empty($request) ||
        $request['approval_status'] !== 'pending'
    ) {
        $this->db->trans_rollback();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'This request has already been processed or is unavailable.'
            )
        );
    }

    // Select correct view for this request.
    $approval_view = $this->is_international_reschedule(
        $request['appointment_id'],
        $request['patient_id']
    )
        ? 'emails/reschedule_approval_confirm_international'
        : 'emails/reschedule_approval_confirm';

    if (strtotime($request['expires_at']) < time()) {
        $this->db
            ->where('id', $request['id'])
            ->update(
                'hms_appointment_reschedule_requests',
                array('approval_status' => 'expired')
            );

        $this->db->trans_commit();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'This approval link has expired.'
            )
        );
    }

    // Fetch original appointment.
    $old = $this->db
        ->where('ID', (int) $request['appointment_id'])
        ->where('paitent_id', $request['patient_id'])
        ->limit(1)
        ->get('hms_appointments')
        ->row_array();

    if (empty($old)) {
        $this->db->trans_rollback();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'The original appointment could not be found. No changes were made.'
            )
        );
    }

    $center_number = $request['proposed_center_number'];
    $doctor_id = (int) $request['proposed_doctor_id'];
    $appointment_date = $request['proposed_date'];
    $appointment_slot = $request['proposed_slot'];

    // Recheck centre is active.
    $center = $this->db
        ->where('center_number', $center_number)
        ->where('status', '1')
        ->limit(1)
        ->get('hms_centers')
        ->row_array();

    if (empty($center)) {
        $this->db->trans_rollback();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'The requested centre is no longer active. No changes were made.'
            )
        );
    }

    // Recheck doctor assignment.
    $this->load->model('Accounts_model');

    $center_doctors = $this->Accounts_model->center_doctors($center_number);
    $doctor_allowed = false;

    if (is_array($center_doctors)) {
        foreach ($center_doctors as $doctor) {
            if (
                isset($doctor['ID']) &&
                (int) $doctor['ID'] === $doctor_id
            ) {
                $doctor_allowed = true;
                break;
            }
        }
    }

    if (!$doctor_allowed) {
        $this->db->trans_rollback();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'The selected doctor is no longer assigned to this centre. No changes were made.'
            )
        );
    }

    // Recheck configured doctor slots.
    $this->load->model('doctors_model');

    $day = strtolower(date('l', strtotime($appointment_date)));
    $doctor_slots = $this->doctors_model->doctor_slots($doctor_id, $day);

    if (is_string($doctor_slots)) {
        $decoded_slots = @unserialize($doctor_slots);

        if (!is_array($decoded_slots)) {
            $decoded_slots = json_decode($doctor_slots, true);
        }

        $doctor_slots = is_array($decoded_slots)
            ? $decoded_slots
            : array();
    }

    $slot_exists = false;

    if (is_array($doctor_slots)) {
        foreach ($doctor_slots as $slot) {
            $slot_value = is_array($slot)
                ? (isset($slot['slot']) ? $slot['slot'] : '')
                : $slot;

            if (trim((string) $slot_value) === $appointment_slot) {
                $slot_exists = true;
                break;
            }
        }
    }

    if (!$slot_exists) {
        $this->db->trans_rollback();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'The selected slot is no longer configured. No changes were made.'
            )
        );
    }

    // Recheck doctor holiday.
    $holiday = $this->doctors_model->doctor_on_holiday($doctor_id);

    if (!empty($holiday) && $holiday != '0') {
        $holiday_dates = explode(' - ', $holiday);

        if (count($holiday_dates) === 2) {
            $holiday_start = date('Y-m-d', strtotime(trim($holiday_dates[0])));
            $holiday_end = date('Y-m-d', strtotime(trim($holiday_dates[1])));

            if (
                $appointment_date >= $holiday_start &&
                $appointment_date <= $holiday_end
            ) {
                $this->db->trans_rollback();

                return $this->load->view(
                    $approval_view,
                    array(
                        'request' => array(),
                        'token' => '',
                        'message' => 'The doctor is on holiday for the selected date. No changes were made.'
                    )
                );
            }
        }
    }

    // Recheck double booking.
    $conflict = $this->db
        ->from('hms_appointments')
        ->where('appoitmented_doctor', (string) $doctor_id)
        ->where('DATE(appoitmented_date) =', $appointment_date, false)
        ->where('appoitmented_slot', $appointment_slot)
        ->where('ID !=', (int) $request['appointment_id'])
        ->where_not_in('status', array('cancelled', 'CANCELLED'))
        ->count_all_results();

    if ($conflict > 0) {
        $this->db->trans_rollback();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'This slot has since been booked. No changes were made.'
            )
        );
    }

    // Update existing appointment only after approval.
    $new_values = array(
        'appoitment_for' => $center_number,
        'appoitmented_doctor' => (string) $doctor_id,
        'appoitmented_date' => $appointment_date,
        'appoitmented_slot' => $appointment_slot,
        'councellor' => $request['proposed_counsellor'],
        'reason_of_visit' => $request['proposed_purpose'],
        'status' => 'rescheduled'
    );

    $this->db
        ->where('ID', (int) $request['appointment_id'])
        ->where('paitent_id', $request['patient_id'])
        ->update('hms_appointments', $new_values);

    // Preserve counsellor-change audit logging.
    $old_counsellor = isset($old['councellor'])
        ? trim((string) $old['councellor'])
        : '';

    $new_counsellor = trim((string) $request['proposed_counsellor']);

    if ($old_counsellor !== $new_counsellor) {
        $this->db->insert('hms_appointment_logs', array(
            'appointment_id' => (int) $request['appointment_id'],
            'field_changed' => 'Counsellor',
            'old_value' => $old_counsellor,
            'new_value' => $new_counsellor,
            'updated_by' => 'Approval: divyansh@indiaivf.in',
            'created_at' => date('Y-m-d H:i:s'),
            'reason' => $request['reason']
        ));
    }

    // Mark request approved.
    $this->db
        ->where('id', (int) $request['id'])
        ->where('approval_status', 'pending')
        ->update(
            'hms_appointment_reschedule_requests',
            array(
                'approval_status' => 'approved',
                'approved_at' => date('Y-m-d H:i:s')
            )
        );

    if ($this->db->trans_status() === false) {
        $this->db->trans_rollback();

        return $this->load->view(
            $approval_view,
            array(
                'request' => array(),
                'token' => '',
                'message' => 'Database error. Approval was not completed.'
            )
        );
    }

    $this->db->trans_commit();

    return $this->load->view(
        $approval_view,
        array(
            'request' => array(),
            'token' => '',
            'message' => 'Appointment reschedule approved successfully. The existing appointment has been updated.'
        )
    );
}




public function assessment_reschedule_disapprove($token = '')
{
    $this->load->helper(array('url', 'form'));

    // Default existing Indian/Camp views.
    $disapprove_view = 'emails/reschedule_disapprove_confirm';
    $disapprove_success_view = 'emails/reschedule_disapprove_success';

    // Validate token.
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        show_error('Invalid disapproval link.', 400);
        return;
    }

    $token_hash = hash('sha256', $token);

    // GET: Show reason form.
    if ($this->input->method(true) !== 'POST') {

        $request = $this->db
            ->where('token_hash', $token_hash)
            ->where('approval_status', 'pending')
            ->limit(1)
            ->get('hms_appointment_reschedule_requests')
            ->row_array();

        if (empty($request)) {
            show_error('This request is invalid, already processed, or unavailable.', 404);
            return;
        }

        // Select views according to original appointment nationality.
        $is_international = $this->is_international_reschedule(
            $request['appointment_id'],
            $request['patient_id']
        );

        $disapprove_view = $is_international
            ? 'emails/reschedule_disapprove_confirm_international'
            : 'emails/reschedule_disapprove_confirm';

        $disapprove_success_view = $is_international
            ? 'emails/reschedule_disapprove_success_international'
            : 'emails/reschedule_disapprove_success';

        // Check expiry.
        if (strtotime($request['expires_at']) < time()) {

            $this->db
                ->where('id', $request['id'])
                ->where('approval_status', 'pending')
                ->update(
                    'hms_appointment_reschedule_requests',
                    array('approval_status' => 'expired')
                );

            show_error('This disapproval link has expired.', 400);
            return;
        }

        $data = array(
            'request' => $request,
            'token' => $token,
            'message' => '',
            'error' => ''
        );

        $this->load->view(
            $disapprove_view,
            $data
        );

        return;
    }

    // POST: Get mandatory disapproval reason.
    $disapproval_reason = trim(
        (string) $this->input->post('disapproval_reason', true)
    );

    // Lock and fetch pending request.
    $this->db->trans_begin();

    $request = $this->db->query(
        "SELECT * FROM hms_appointment_reschedule_requests
         WHERE token_hash = ?
         LIMIT 1 FOR UPDATE",
        array($token_hash)
    )->row_array();

    if (
        empty($request) ||
        $request['approval_status'] !== 'pending'
    ) {
        $this->db->trans_rollback();

        show_error('This request has already been processed or is unavailable.', 400);
        return;
    }

    // Select views according to original appointment nationality.
    $is_international = $this->is_international_reschedule(
        $request['appointment_id'],
        $request['patient_id']
    );

    $disapprove_view = $is_international
        ? 'emails/reschedule_disapprove_confirm_international'
        : 'emails/reschedule_disapprove_confirm';

    $disapprove_success_view = $is_international
        ? 'emails/reschedule_disapprove_success_international'
        : 'emails/reschedule_disapprove_success';

    // Check expiry again.
    if (strtotime($request['expires_at']) < time()) {

        $this->db
            ->where('id', $request['id'])
            ->where('approval_status', 'pending')
            ->update(
                'hms_appointment_reschedule_requests',
                array('approval_status' => 'expired')
            );

        $this->db->trans_commit();

        show_error('This disapproval link has expired.', 400);
        return;
    }

    // Validate reason.
    if ($disapproval_reason === '' || strlen($disapproval_reason) > 1000) {

        $this->db->trans_rollback();

        $data = array(
            'request' => $request,
            'token' => $token,
            'message' => '',
            'error' => ($disapproval_reason === '')
                ? 'Please enter disapproval reason.'
                : 'Reason cannot exceed 1000 characters.'
        );

        $this->load->view(
            $disapprove_view,
            $data
        );

        return;
    }

    // Update only reschedule request.
    // Existing appointment will NOT be changed.
    $this->db
        ->where('id', (int) $request['id'])
        ->where('approval_status', 'pending')
        ->update(
            'hms_appointment_reschedule_requests',
            array(
                'approval_status' => 'disapproved',
                'disapproval_reason' => $disapproval_reason
            )
        );

    if ($this->db->trans_status() === false) {

        $this->db->trans_rollback();

        show_error('Database error. Disapproval was not completed.', 500);
        return;
    }

    $this->db->trans_commit();

    $data = array(
        'request' => $request,
        'disapproval_reason' => $disapproval_reason
    );

    $this->load->view(
        $disapprove_success_view,
        $data
    );

    return;
}

private function is_international_reschedule($appointment_id, $patient_id)
{
    $appointment = $this->db
        ->select('nationality')
        ->from('hms_appointments')
        ->where('ID', (int) $appointment_id)
        ->where('paitent_id', $patient_id)
        ->limit(1)
        ->get()
        ->row_array();

    return (
        !empty($appointment['nationality']) &&
        strtolower(trim($appointment['nationality'])) === 'non-indian'
    );
}






public function assessment_reschedule_requests()
{
    $logg = checklogin();
    error_reporting(0);

    if ($logg['status'] == true) {

        $per_page = 10;

        $patient_id = $this->input->get('patient_id', true);
        $center = $this->input->get('center', true);
        $doctor = $this->input->get('doctor', true);
        $old_counsellor = $this->input->get('old_counsellor', true);
        $new_counsellor = $this->input->get('new_counsellor', true);
        $approval_status = $this->input->get('approval_status', true);

        $page = (int) $this->input->get('page', true);

        if ($page < 1) {
            $page = 1;
        }

        $offset = ($page - 1) * $per_page;

        $this->load->model('Appointment_model', 'appointment_model');

        // Dropdown data
     $data['centers'] = $this->db
    ->where('status', 1)
    ->order_by('center_name', 'ASC')
    ->get('hms_centers')
    ->result_array();

     $data['doctors'] = $this->db
    ->where('status', 1)
    ->order_by('name', 'ASC')
    ->get('hms_doctors')
    ->result_array();

        $data['counsellors'] = $this->db
            ->where('role', 'counselor')
            ->where('status', 1)
            ->order_by('name', 'ASC')
            ->get('hms_employees')
            ->result_array();

        $filters = array(
            'patient_id' => $patient_id,
            'center' => $center,
            'doctor' => $doctor,
            'old_counsellor' => $old_counsellor,
            'new_counsellor' => $new_counsellor,
            'approval_status' => $approval_status
        );

        $config = array();

        $config['base_url'] = base_url() . 'accounts/assessment_reschedule_requests';
        $config['total_rows'] = $this->appointment_model->assessment_reschedule_requests_count($filters);
        $config['per_page'] = $per_page;

        $config['page_query_string'] = true;
        $config['query_string_segment'] = 'page';
        $config['use_page_numbers'] = true;
        $config['reuse_query_string'] = true;
        $config['num_links'] = 5;

        $this->pagination->initialize($config);

        $data['links'] = $this->pagination->create_links();

        $data['wallet_result'] = $this->appointment_model->assessment_reschedule_requests_pagination(
            $per_page,
            $offset,
            $filters
        );

        $data['patient_id'] = $patient_id;
        $data['center'] = $center;
        $data['doctor'] = $doctor;
        $data['old_counsellor'] = $old_counsellor;
        $data['new_counsellor'] = $new_counsellor;
        $data['approval_status'] = $approval_status;
        $data['page'] = $page;

        $template = get_header_template($logg['role']);

        $this->load->view($template['header']);
        $this->load->view('accounts/assessment_reschedule_requests', $data);
        $this->load->view($template['footer']);

    } else {

        redirect(base_url());
        die();

    }
}




public function assessment_reschedule_requests_export()
{
    $logg = checklogin();
    error_reporting(0);

    if ($logg['status'] == true) {

        $this->load->model('Appointment_model', 'appointment_model');

        $filters = array(
            'patient_id' => $this->input->get('patient_id', true),
            'center' => $this->input->get('center', true),
            'doctor' => $this->input->get('doctor', true),
            'old_counsellor' => $this->input->get('old_counsellor', true),
            'new_counsellor' => $this->input->get('new_counsellor', true),
            'approval_status' => $this->input->get('approval_status', true)
        );

        $result = $this->appointment_model
            ->assessment_reschedule_requests_export($filters);

        $filename = 'Assessment_Reschedule_Requests_' . date('Y-m-d') . '.csv';

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // CSV column headings
        fputcsv($output, array(
            'S.No.',
            'Patient ID',
            'Patient Name',
            'Appointment ID',
            'Centre Name',
            'Doctor Name',
            'Proposed Date',
            'Proposed Slot',
            'Old Counsellor',
            'New Counsellor',
            'Purpose',
            'Reason',
            'Approval Status',
            'Created At'
        ));

        $count = 1;

        foreach ($result as $vl) {

            $status = !empty($vl['approval_status'])
                ? ucwords(str_replace('_', ' ', $vl['approval_status']))
                : 'N/A';

            fputcsv($output, array(
                $count++,
                $vl['patient_id'],
                $vl['patient_name'],
                $vl['appointment_id'],

                !empty($vl['center_name'])
                    ? $vl['center_name']
                    : $vl['proposed_center_number'],

                !empty($vl['doctor_name'])
                    ? $vl['doctor_name']
                    : $vl['proposed_doctor_id'],

                $vl['proposed_date'],
                $vl['proposed_slot'],

                !empty($vl['old_counsellor'])
                    ? $vl['old_counsellor']
                    : 'N/A',

                !empty($vl['new_counsellor'])
                    ? $vl['new_counsellor']
                    : 'N/A',

                $vl['proposed_purpose'],
                $vl['reason'],
                $status,
                $vl['created_at']
            ));
        }

        fclose($output);
        exit;

    } else {

        redirect(base_url());
        die();

    }
}

} // End of class - MAKE SURE THIS IS THE LAST LINE