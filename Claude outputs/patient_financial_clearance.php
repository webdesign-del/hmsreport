<?php $all_method =& get_instance(); ?>        
    <div class="col-md-12 card">
      <div class="row" style="margin-bottom:20px;">
      <div class="col-md-12"><h3> Procedure Financial Clearance </h3></div>
      <div class="clearfix"></div>
        <form action="<?php echo base_url().'accounts/patient_financial_clearance'; ?>" method="get">
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>Filter by billing at</label>
                <select class="form-control" id="billing_at" name="billing_at">
                    <option value=''>--Select From--</option>
                    <?php 
                    $all_centers = method_exists($all_method, 'get_all_centers') ? $all_method->get_all_centers() : array();
                    if(!empty($all_centers)) {
                        foreach($all_centers as $key => $val){
                            $selected = (isset($billing_at) && $billing_at == $val['center_number']) ? 'selected' : '';
                            echo '<option value="'.$val['center_number'].'" '.$selected.'>'.$val['center_name'].'</option>';
                        }
                    } 
                    ?>
                </select>
            </div>
           
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>Start Date</label>
                <input type="text" class="particular_date_filter form-control" id="start_date" name="start_date" value="<?php echo isset($start_date) ? $start_date : ''; ?>" />
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>End Date</label>
                <input type="text" class="particular_date_filter form-control" id="end_date" name="end_date" value="<?php echo isset($end_date) ? $end_date : ''; ?>" />
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>IIC ID </label>
                <input type="text" class="form-control" id="iic_id" name="iic_id" value="<?php echo isset($patient_id) ? $patient_id : ''; ?>" />
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>Clearance by Counsellor</label>
                <select class="form-control" id="clearance" name="clearance">
                    <option value=''>--Select From--</option>
                    <option value="Yes" <?php echo (isset($clearance) && $clearance == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                    <option value="No" <?php echo (isset($clearance) && $clearance == 'No') ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>Doctor Consultant </label>
                <select class="form-control" id="consultant" name="consultant">
                    <option value=''>--Select From--</option>
                    <option value="Done" <?php echo (isset($consultant) && $consultant == 'Done') ? 'selected' : ''; ?>>Done</option>
                </select>
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>Payment</label>
                <select class="form-control" id="paid" name="paid">
                    <option value="full" <?php echo (!isset($_GET['paid']) || $_GET['paid'] !== 'all') ? 'selected' : ''; ?>>100% received only</option>
                    <option value="all" <?php echo (isset($_GET['paid']) && $_GET['paid'] === 'all') ? 'selected' : ''; ?>>All procedures</option>
                </select>
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-top:10px;">
                <label>Clearance by Accounts</label>
                <select class="form-control" id="accclearance" name="accclearance">
                    <option value=''>--Select From--</option>
                    <option value="Yes" <?php echo (isset($accclearance) && $accclearance == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                    <option value="No" <?php echo (isset($accclearance) && $accclearance == 'No') ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
            <div class="col-sm-3" style="margin-top: 25px;">
                
                <button name="btnsearch" id="btnsearch" type="submit" class="btn btn-primary">Search</button>
                <a href="<?php echo base_url().'accounts/patient_financial_clearance'; ?>" style="text-decoration: none;">
                    <button name="btnreset" id="btnreset" type="button" class="btn btn-default">RESET</button>
                </a>
                <button type="button"
                        name="btn_approve_counselor"
                        id="btn_approve_counselor"
                        class="btn btn-success">
                    Approve
                </button>
            </div>
        </form>  
        
         </div>
        <div class="clearfix"></div>
        <div class="card-content">
          <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover" id="procedure_billing_list">
                    <thead>
                        <tr>
                            <th style="text-align:center; width: 40px;">
                                <input type="checkbox" id="select_all_procedures">
                            </th>
                            <th>S.No.</th>
                            <th>IIC ID</th>
                            <th>Patient name</th>
                            <th>Receipt number</th>
                            <th>Billing Center</th>
                            <th>Origins Center</th>
                            <th>Code</th>
                            <th>Procedure</th>
                            <th>Package Amount</th>
                            <th>Final Package Amount</th>
                            <th>Total Amount Receive</th>
                            <th>Final Package Outstanding Amount</th>
                            <th>Status</th>
                            <th>Centre Head Approval</th>
                            <th>Account Reconciliation Status</th>
                            <th>Account Approved By</th>
                        </tr>
                    </thead>
                    <tbody id="procedure_result">
                  <?php 
if(!empty($procedure_result)):
    $count = 1; 

    foreach($procedure_result as $ky => $vl):

        // IP218 / IP219 (TVS/TAS under package) is list me nahi dikhenge
        $pfc_code = strtoupper(trim(isset($vl['code']) ? (string)$vl['code'] : ''));
        if (in_array($pfc_code, array('IP218', 'IP219'), true)) { continue; }

        $current_receipt = isset($vl['receipt_number']) 
            ? $vl['receipt_number'] 
            : ''; 

        $sum_result_paid = 0;

        if(!empty($current_receipt)){

            $sum_sql = "SELECT SUM(payment_done) as total_paid 
                        FROM hms_patient_payments 
                        WHERE billing_id = '$current_receipt'";

            $sum_query = $this->db->query($sum_sql);

            if($sum_query && $sum_query->num_rows() > 0) {

                $sum_res = $sum_query->row();

                $sum_result_paid = floatval($sum_res->total_paid);
            }
        }

        $fees = floatval(
            isset($vl['fees']) ? $vl['fees'] : 0
        );

        $p_done = floatval(
            isset($vl['payment_done']) ? $vl['payment_done'] : 0
        );

        // Total payment received
        $total_received = $sum_result_paid + $p_done;


        // ===== Popup ke liye: is receipt ke SAARE payments =====
        $pfc_pay = array();
        $pfc_mode = function ($r) {
            $m = array();
            foreach (array('cash_payment' => 'Cash', 'card_payment' => 'Card', 'upi_payment' => 'UPI', 'neft_payment' => 'NEFT', 'wallet_payment' => 'Wallet') as $c => $l) {
                if (isset($r[$c]) && (float)$r[$c] > 0) { $m[] = $l.' '.number_format((float)$r[$c], 2); }
            }
            return !empty($m) ? implode(', ', $m) : (isset($r['payment_method']) ? $r['payment_method'] : '');
        };
        // Employee id / number -> name (name na mile to jo value hai wahi dikha do)
        $pfc_emp = function ($v) use ($all_method) {
            $v = trim((string)$v);
            if ($v === '' || $v === '0') { return ''; }
            $n = method_exists($all_method, 'get_employee_name') ? $all_method->get_employee_name($v) : '';
            return !empty($n) ? $n : $v;
        };
        // a) Billing ke waqt mila amount
        //    Billed by / on  = hms_patient_procedure.biller_id / on_date
        //    Approved by / on = hms_patient_procedure.accountant_id / accclearance_date (Accounts approval)
        $pfc_pay[] = array('type' => 'Billing', 'date' => isset($vl['on_date']) ? $vl['on_date'] : '', 'amount' => $p_done,
                           'mode' => $pfc_mode($vl), 'txn' => isset($vl['transaction_id']) ? $vl['transaction_id'] : '',
                           'status' => isset($vl['status']) ? $vl['status'] : '', 'img' => isset($vl['transaction_img']) ? $vl['transaction_img'] : '',
                           'billed_by' => $pfc_emp(isset($vl['biller_id']) ? $vl['biller_id'] : ''),
                           'approved_by' => $pfc_emp(isset($vl['accountant_id']) ? $vl['accountant_id'] : ''),
                           'approved_on' => isset($vl['accclearance_date']) ? $vl['accclearance_date'] : '');
        // b) Baad me aaye payments (hms_patient_payments)
        if (!empty($current_receipt)) {
            foreach ($this->db->query("SELECT * FROM hms_patient_payments WHERE billing_id = '".addslashes($current_receipt)."' ORDER BY on_date ASC, ID ASC")->result_array() as $pp) {
                $pst = (string)$pp['status'];
                // Billed by  = hms_patient_payments.biller (khali ho to employee_number)
                // Approved   = status '1' hone par: by = employee_number, on = modified_on
                // (agar approver ka column alag hai to sirf neeche ki 3 lines badalni hain)
                $pp_billed   = !empty($pp['biller']) ? $pp['biller'] : (isset($pp['employee_number']) ? $pp['employee_number'] : '');
                $pp_appr_by  = ($pst === '1' && isset($pp['employee_number'])) ? $pp['employee_number'] : '';
                $pp_appr_on  = ($pst === '1' && !empty($pp['modified_on'])) ? $pp['modified_on'] : '';
                $pfc_pay[] = array('type' => !empty($pp['type']) ? ucfirst($pp['type']) : 'Payment', 'date' => $pp['on_date'], 'amount' => (float)$pp['payment_done'],
                                   'mode' => $pfc_mode($pp), 'txn' => $pp['transaction_id'],
                                   'status' => ($pst === '1' ? 'Approved' : ($pst === '0' ? 'Pending' : $pst)), 'img' => $pp['transaction_img'],
                                   'ref' => isset($pp['refrence_number']) ? $pp['refrence_number'] : '',
                                   'billed_by' => $pfc_emp($pp_billed), 'approved_by' => $pfc_emp($pp_appr_by), 'approved_on' => $pp_appr_on);
            }
        }
        $pfc_json = htmlspecialchars(json_encode(array(
            'receipt' => $current_receipt, 'procedure' => isset($vl['procedure_name']) ? $vl['procedure_name'] : '', 'code' => isset($vl['code']) ? $vl['code'] : '',
            'package' => (float)(isset($vl['totalpackage']) ? $vl['totalpackage'] : 0), 'discount' => (float)(isset($vl['discount_amount']) ? $vl['discount_amount'] : 0),
            'fees' => $fees, 'received' => $total_received, 'balance' => $fees - $total_received, 'payments' => $pfc_pay,
            'counsellor' => $pfc_emp(isset($vl['counselor_id']) ? $vl['counselor_id'] : ''),
            'counsellor_date' => isset($vl['clearance_date']) ? $vl['clearance_date'] : '',
            'details_url' => base_url().'accounts/financial_clearance_details/'.$current_receipt,
        )), ENT_QUOTES, 'UTF-8');

        // Remaining balance
        $balance = $fees - $total_received;

        // 100% approval: poori payment mili ho AND billing + har partial payment approved ho.
        // Tabhi "approved" aur approver ka naam / date dikhega, warna "pending" aur "-".
        $pfc_all_appr = (strtolower(trim(isset($vl['status']) ? (string)$vl['status'] : '')) === 'approved');
        foreach ($pfc_pay as $pfc_i => $pfc_p) {
            if ($pfc_i === 0) { continue; }                       // 0 = billing row (procedure ka apna status)
            $pfc_ps = strtolower(trim((string)$pfc_p['status']));
            if (strpos($pfc_ps, 'cancel') === 0 || strpos($pfc_ps, 'disapprov') === 0) { continue; }   // cancelled / disapproved payments ginti me nahi
            if ($pfc_ps !== 'approved') { $pfc_all_appr = false; break; }
        }
        $pfc_final_approved = ($fees > 0 && $total_received >= $fees && $pfc_all_appr);

        // Already approved?
        $is_counsellor_approved = (
            isset($vl['clearance']) &&
            (
                strtolower($vl['clearance']) == 'yes' ||
                strtolower($vl['clearance']) == 'approve'
            )
        );
?>

<tr class="odd gradeX">

    <td style="text-align:center;">
        <?php
        // Logged-in counsellor check
        $is_counsellor = !empty(
            $_SESSION['logged_counselor']['employee_number']
        );

        // Payment 100% completed
        $is_payment_complete = (
            $fees > 0 &&
            $total_received >= $fees
        );
        ?>

        <?php if($is_counsellor_approved): ?>

            <span class="label label-success" style="font-size:10px;">
                Approved
            </span>

        <?php elseif($is_counsellor && $is_payment_complete): ?>

            <input type="checkbox"
                   class="procedure_checkbox"
                   value="<?php echo $vl['ID']; ?>">

        <?php endif; ?>
    </td>
                            <td><?php echo $count; ?></td>
                            <td><a href="<?php echo base_url().'patient_details/'.$vl['patient_id']; ?>"><?php echo $vl['patient_id']; ?></a></td>
                            <td>
                                <?php 
                                if(function_exists('get_patient_name')){
                                    echo strtoupper(get_patient_name($vl['patient_id']));
                                } elseif(method_exists($all_method, 'get_patient_name')) {
                                    echo strtoupper($all_method->get_patient_name($vl['patient_id']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><a href="javascript:void(0);" class="pfc-pay-link" data-pay="<?php echo $pfc_json; ?>" title="Click to see all payments"><?php echo $vl['receipt_number']; ?></a></td>
                            <td><?php echo method_exists($all_method, 'get_center_name') ? $all_method->get_center_name($vl['billing_at']) : '-'; ?></td>
                            <td><?php echo method_exists($all_method, 'get_center_name') ? $all_method->get_center_name($vl['origins']) : '-'; ?></td>
                            <td><?php echo isset($vl['code']) ? $vl['code'] : ''; ?></td>
                            <td><?php echo isset($vl['procedure_name']) ? $vl['procedure_name'] : ''; ?></td>
                            <td><?php echo isset($vl['totalpackage']) ? $vl['totalpackage'] : '0.00'; ?></td>
                            <td><?php echo number_format($fees, 2); ?></td>
                            <td><?php echo number_format($total_received, 2); ?></td>
                            <td><?php echo number_format($balance, 2); ?></td>
                            <td><?php echo !empty($vl['clearance']) ? $vl['clearance'] : 'Pending'; ?></td>
                            <td>
                                <?php 
                                $c_name = (isset($vl['counselor_id']) && method_exists($all_method, 'get_employee_name')) ? $all_method->get_employee_name($vl['counselor_id']) : '';
                                echo !empty($c_name) ? $c_name . ' (' . (isset($vl['clearance_date']) ? $vl['clearance_date'] : '') . ')' : '-';
                                ?>
                            </td>
                            <td><?php
                                $pfc_st_raw = isset($vl['status']) ? (string)$vl['status'] : '';
                                echo (strtolower(trim($pfc_st_raw)) === 'approved' && !$pfc_final_approved) ? 'pending' : $pfc_st_raw;
                            ?></td>
                            <td>
                                <?php 
                                $a_name = (isset($vl['accountant_id']) && method_exists($all_method, 'get_employee_name')) ? $all_method->get_employee_name($vl['accountant_id']) : '';
                                echo ($pfc_final_approved && !empty($a_name)) ? $a_name . ' (' . (isset($vl['accclearance_date']) ? $vl['accclearance_date'] : '') . ')' : '-';
                                ?>
                            </td>
                        </tr>
                    <?php 
                        $count++; 
                        endforeach; 
                        if ($count == 1): ?>
                        <tr><td colspan="22" class="text-center text-muted" style="padding: 20px;">No records found matching your filters.</td></tr>
                        <?php endif;
                    else: 
                    ?>
                        <tr>
                            <td colspan="22" class="text-center text-muted" style="padding: 20px;">No records found matching your filters.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>            
                </table>
            </div>

            <?php if(!empty($links)): ?>
                <div class="row" style="margin-top:15px;">
                    <div class="col-md-12 text-center">
                        <p class="custom-pagination"><?php echo $links; ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Materialize / Framework Checkbox Fix */
input[type="checkbox"].procedure_checkbox, 
#select_all_procedures {
    position: relative !important;
    left: 0 !important;
    opacity: 1 !important;
    visibility: visible !important;
    cursor: pointer;
    width: 16px;
    height: 16px;
    margin: 0 auto;
    display: inline-block !important;
}
</style>

<script>
$(document).ready(function() {
    // Select All Logic
    $('#select_all_procedures').on('change', function() {
        $('.procedure_checkbox').prop('checked', $(this).is(':checked'));
    });

    $(document).on('change', '.procedure_checkbox', function() {
        if ($('.procedure_checkbox:checked').length === $('.procedure_checkbox').length && $('.procedure_checkbox').length > 0) {
            $('#select_all_procedures').prop('checked', true);
        } else {
            $('#select_all_procedures').prop('checked', false);
        }
    });

    // Counselor Approve Action
    $('#btn_approve_counselor').on('click', function() {
    var selected_ids = [];

    $('.procedure_checkbox:checked').each(function() {
        selected_ids.push($(this).val());
    });

    if (selected_ids.length === 0) {
        alert('Kripya kam se kam ek procedure select karein.');
        return false;
    }

    if (confirm('Kya aap ' + selected_ids.length + ' procedures ko approve karna chahte hain?')) {
        $.ajax({
            url: '<?php echo base_url("accounts/approve_counsellor_clearance"); ?>',
            type: 'POST',
            data: {
                procedure_ids: selected_ids
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Approval request fail ho gayi.');
            }
        });
    }
});
});
</script>

<!-- ===== Payment details popup ===== -->
<div id="pfc_modal" class="pfc-modal" role="dialog" aria-modal="true" aria-labelledby="pfc_title" style="display:none;">
  <div class="pfc-box">
    <div class="pfc-head">
      <div>
        <h4 id="pfc_title">Payment Details</h4>
        <div class="pfc-sub" id="pfc_sub"></div>
      </div>
      <button type="button" class="pfc-close" aria-label="Close">&times;</button>
    </div>
    <div class="pfc-sum" id="pfc_sum"></div>
    <div class="pfc-clr" id="pfc_clr"></div>
    <div class="pfc-table-wrap">
      <table class="pfc-table">
        <thead><tr><th>#</th><th>Billed by</th><th>Type</th><th>Mode</th><th>Transaction / Ref.</th><th>Status</th><th>Approved by</th><th>Approved on</th><th style="text-align:right;">Amount</th></tr></thead>
        <tbody id="pfc_rows"></tbody>
        <tfoot><tr><td colspan="8" style="text-align:right;">Total Received</td><td style="text-align:right;" id="pfc_total"></td></tr></tfoot>
      </table>
    </div>
    <div class="pfc-foot"><a id="pfc_details" href="#" target="_blank">Open full clearance details &rarr;</a></div>
  </div>
</div>
<style>
.pfc-modal { position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,.45); display:flex; align-items:flex-start; justify-content:center; padding:60px 16px; overflow:auto; }
.pfc-box { background:#fff; border-radius:8px; width:100%; max-width:1180px; box-shadow:0 10px 40px rgba(0,0,0,.25); font-family:'Open Sans', Arial, sans-serif; color:#1f2a37; }
.pfc-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:16px 20px; border-bottom:2px solid #1f2a37; }
.pfc-head h4 { margin:0; font-size:20px; font-weight:600; }
.pfc-sub { color:#5f6b7a; font-size:13px; margin-top:3px; }
.pfc-close { border:0; background:none; font-size:28px; line-height:1; cursor:pointer; color:#5f6b7a; padding:0 4px; }
.pfc-close:focus-visible { outline:2px solid #c23a6b; }
.pfc-sum { display:flex; flex-wrap:wrap; gap:10px; padding:14px 20px; }
.pfc-sum div { flex:1 1 140px; border:1px solid #dfe5ec; border-radius:6px; padding:8px 12px; }
.pfc-sum span { display:block; font-size:12px; color:#5f6b7a; }
.pfc-sum b { font-size:17px; }
.pfc-sum .pfc-due b { color:#b02a37; } .pfc-sum .pfc-ok b { color:#0f766e; }
.pfc-clr { padding:0 20px 10px; font-size:13px; color:#5f6b7a; }
.pfc-clr b { color:#1f2a37; }
.pfc-table-wrap { padding:0 20px; overflow-x:auto; }
.pfc-table { width:100%; border-collapse:collapse; font-size:13px; }
.pfc-table th { background:#f6f8fb; color:#5f6b7a; font-weight:600; text-align:left; padding:8px; border-bottom:1px solid #dfe5ec; }
.pfc-table td { padding:8px; border-top:1px solid #eef1f4; vertical-align:top; }
.pfc-table tfoot td { font-weight:700; border-top:2px solid #dfe5ec; }
.pfc-st { display:inline-block; padding:1px 8px; border-radius:10px; font-size:11px; font-weight:600; background:#f1f3f6; }
.pfc-st.ok { background:#e6f4f2; color:#0f766e; } .pfc-st.pend { background:#fff4e0; color:#8a5a00; } .pfc-st.bad { background:#fbeef3; color:#b02a37; }
.pfc-foot { padding:12px 20px 16px; text-align:right; }
.pfc-foot a { color:#0f766e; font-weight:600; }
</style>
<script>
(function () {
    function money(n) { n = parseFloat(n) || 0; return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function stClass(st) { st = String(st || '').toLowerCase(); if (st.indexOf('approv') === 0) { return 'ok'; } if (st.indexOf('pend') === 0) { return 'pend'; } if (st.indexOf('cancel') === 0 || st.indexOf('disapprov') === 0 || st.indexOf('reject') === 0) { return 'bad'; } return ''; }
    function closeModal() { $('#pfc_modal').hide(); $('body').css('overflow', ''); }
    $(document).on('click', '.pfc-pay-link', function () {
        var d; try { d = JSON.parse($(this).attr('data-pay')); } catch (e) { return; }
        $('#pfc_title').text('Payment Details - ' + d.receipt);
        $('#pfc_sub').text((d.procedure || '') + (d.code ? ' (' + d.code + ')' : ''));
        var bal = parseFloat(d.balance) || 0;
        $('#pfc_sum').html(
            '<div><span>Package</span><b>' + money(d.package) + '</b></div>' +
            '<div><span>Discount</span><b>' + money(d.discount) + '</b></div>' +
            '<div><span>Final Amount</span><b>' + money(d.fees) + '</b></div>' +
            '<div class="pfc-ok"><span>Received</span><b>' + money(d.received) + '</b></div>' +
            '<div class="' + (bal > 0 ? 'pfc-due' : 'pfc-ok') + '"><span>Outstanding</span><b>' + money(bal) + '</b></div>');
        var rows = '', i = 0;
        $.each(d.payments || [], function (_, p) {
            i++;
            var txn = esc(p.txn || '') + (p.ref ? '<br><small>' + esc(p.ref) + '</small>' : '') + (p.img ? '<br><a href="' + esc(p.img) + '" target="_blank">View proof</a>' : '');
            rows += '<tr><td>' + i + '</td><td>' + esc(p.billed_by || '-') + '<br><small style="color:#5f6b7a;">' + esc(p.date || '') + '</small></td><td>' + esc(p.type) + '</td><td>' + esc(p.mode) + '</td><td>' + txn +
                    '</td><td><span class="pfc-st ' + stClass(p.status) + '">' + esc(p.status || '-') + '</span></td><td>' + esc(p.approved_by || '-') +
                    '</td><td>' + esc(p.approved_on || '-') + '</td><td style="text-align:right;">' + money(p.amount) + '</td></tr>';
        });
        $('#pfc_rows').html(rows || '<tr><td colspan="9" style="text-align:center;color:#5f6b7a;">No payments found.</td></tr>');
        $('#pfc_clr').html(d.counsellor ? 'Counsellor clearance: <b>' + esc(d.counsellor) + '</b>' + (d.counsellor_date ? ' (' + esc(d.counsellor_date) + ')' : '') : '');
        $('#pfc_total').text(money(d.received));
        $('#pfc_details').attr('href', d.details_url);
        $('#pfc_modal').css('display', 'flex'); $('body').css('overflow', 'hidden');
        $('#pfc_modal .pfc-close').focus();
    });
    $(document).on('click', '#pfc_modal .pfc-close', closeModal);
    $(document).on('click', '#pfc_modal', function (e) { if (e.target === this) { closeModal(); } });
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && $('#pfc_modal').is(':visible')) { closeModal(); } });
})();
</script>
