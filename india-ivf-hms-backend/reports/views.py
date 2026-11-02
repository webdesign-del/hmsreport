import json
import hashlib
import calendar
from datetime import date
from django.shortcuts import render, redirect
from django.http import JsonResponse
from django.db import connection
from django.views.decorators.csrf import csrf_exempt
from django.utils import timezone
from django.contrib import messages
from django.conf import settings as dj_settings
from django.core.mail import send_mail

# Dashboard lists only show records dated on/after this day (1 April 2024).
DATA_START_DATE = '2024-04-01'


# ============================================================
# 1. LEGACY HMS_DOCTORS DIRECT AUTHENTICATION & SESSIONS
# ============================================================

# Maps the workspace button clicked on the frontend to the hms_employees.role value
EMPLOYEE_WORKSPACE_ROLES = {
    'centre_head': 'center_head',
    'fc': 'counselor',
    'accounts': 'accountant',
    # Management workspace: the DB role may be 'viewer' or an administrator-type role.
    'management': ('viewer', 'administrator', 'admin', 'superadmin', 'super_admin', 'management'),
    'embryologist': 'embryologist',
    # View Only workspace: read-only users (the frontend + middleware block every write for this role).
    'view_only': ('view_only', 'viewonly', 'view-only', 'readonly', 'read_only'),
}


@csrf_exempt
def login_view(request):
    if request.method == "POST":
        # Handle both JSON payloads and Form Data
        if request.content_type == 'application/json':
            try:
                data = json.loads(request.body)
                username_input = data.get('username')
                password_input = data.get('password')
                role_input = data.get('role')
            except Exception:
                username_input = request.POST.get('username')
                password_input = request.POST.get('password')
                role_input = request.POST.get('role')
        else:
            username_input = request.POST.get('username')
            password_input = request.POST.get('password')
            role_input = request.POST.get('role')

        if not username_input or not password_input:
            return JsonResponse({'status': 'error', 'message': 'Username and password are required.'}, status=400)

        # MD5 hashing logic sequence matching standard
        password_md5 = hashlib.md5(password_input.encode('utf-8')).hexdigest()

        # Non-doctor workspaces (Centre Head, Financial Counsellor, Accounts Team,
        # Management) authenticate against hms_employees, scoped to their DB role.
        if role_input in EMPLOYEE_WORKSPACE_ROLES:
            expected = EMPLOYEE_WORKSPACE_ROLES[role_input]
            expected_roles = list(expected) if isinstance(expected, (tuple, list)) else [expected]
            role_marks = ", ".join(["%s"] * len(expected_roles))
            employee_query = f"""
                SELECT e.id, e.name, e.email, e.username, e.password, e.role, e.status, e.center_id, c.center_name
                FROM hms_employees e
                LEFT JOIN hms_centers c ON e.center_id = c.center_number
                WHERE (e.username = %s OR e.email = %s) AND LOWER(TRIM(e.role)) IN ({role_marks})
                LIMIT 1;
            """
            with connection.cursor() as cursor:
                cursor.execute(employee_query, [username_input, username_input] + expected_roles)
                employee_row = cursor.fetchone()

            if employee_row:
                emp_id, emp_name, emp_email, emp_username, emp_password, emp_role, emp_status, emp_center_id, emp_center_name = employee_row

                if emp_password == password_md5 or emp_password == password_input:
                    if not emp_status:
                        return JsonResponse({'status': 'error', 'message': 'This account has been deactivated.'}, status=403)

                    request.session['employee_id'] = emp_id
                    request.session['employee_name'] = emp_name
                    request.session['employee_email'] = emp_email
                    request.session['employee_username'] = emp_username
                    request.session['employee_center_id'] = emp_center_id
                    request.session['user_role'] = emp_role

                    return JsonResponse({
                        'status': 'success',
                        'message': 'Logged in successfully',
                        'user': {
                            'id': emp_id,
                            'name': emp_name,
                            'email': emp_email,
                            'username': emp_username,
                            'role': emp_role,
                            'center_id': emp_center_id,
                            'center_name': emp_center_name,
                        }
                    })

            # Diagnostic: right password but the account's DB role isn't allowed in this workspace.
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT role, password FROM hms_employees WHERE (username = %s OR email = %s) LIMIT 1",
                    [username_input, username_input])
                other = cursor.fetchone()
            if other and (other[1] == password_md5 or other[1] == password_input):
                return JsonResponse({
                    'status': 'error',
                    'message': f"This account's role is '{other[0]}', which is not allowed in this workspace. Pick the matching workspace.",
                }, status=400)

            return JsonResponse({'status': 'error', 'message': 'Invalid credentials or username context.'}, status=400)

        # Default / 'doctor' workspace: legacy hms_doctors direct authentication.
        # Query checks against BOTH username and email
        query = """
            SELECT d.ID, d.name, d.email, d.username, d.password, d.center_id, c.center_name, d.allowed_centers
            FROM hms_doctors d
            LEFT JOIN hms_centers c ON d.center_id = c.center_number
            WHERE (d.username = %s OR d.email = %s)
            LIMIT 1;
        """

        with connection.cursor() as cursor:
            cursor.execute(query, [username_input, username_input])
            doctor_row = cursor.fetchone()

        if doctor_row:
            db_id, db_name, db_email, db_username, db_password, db_center_id, db_center_name, db_allowed_centers = doctor_row

            # Check MD5 hash OR Plain-text fallback
            if db_password == password_md5 or db_password == password_input:
                request.session['doctor_id'] = db_id
                request.session['doctor_name'] = db_name
                request.session['doctor_email'] = db_email
                request.session['doctor_username'] = db_username
                request.session['user_role'] = 'doctor'

                # A doctor with more than one entry in allowed_centers works across centres
                # (e.g. a director-level account) and should see all centres, not just their
                # home center_id.
                allowed_list = [c.strip() for c in (db_allowed_centers or '').split(',') if c.strip()]
                is_multi_centre = len(allowed_list) > 1

                return JsonResponse({
                    'status': 'success',
                    'message': 'Logged in successfully',
                    'user': {
                        'id': db_id,
                        'name': db_name,
                        'email': db_email,
                        'username': db_username,
                        'center_id': None if is_multi_centre else db_center_id,
                        'center_name': None if is_multi_centre else db_center_name,
                    }
                })

        return JsonResponse({'status': 'error', 'message': 'Invalid credentials or username context.'}, status=400)

    return JsonResponse({'status': 'error', 'message': 'Use POST method for login.'}, status=405)


def logout_view(request):
    request.session.flush()
    return JsonResponse({'status': 'success', 'message': 'Logged out successfully'})


def doctor_workspace_view(request):
    if 'doctor_id' not in request.session:
        messages.error(request, "Please sign in to access the Doctor Workspace.")
        return redirect('login')

    logged_doctor_name = request.session.get('doctor_name', 'Dr. Consultation Workspace')
    active_center_name = "Noida Centre"

    context = {
        'active_center': active_center_name,
        'doctor_name': logged_doctor_name,
    }
    return render(request, 'doctor_workspace.html', context)


# ============================================================
# 2. STANDARD HTML PAGE ROUTERS & ROOT API
# ============================================================

def index(request):
    return JsonResponse({
        "status": "success",
        "message": "India IVF HMS Django API Backend is running successfully!",
        "endpoints": {
            "get_dynamic_booked_patients": "/api/get_dynamic_booked_patients/",
            "get_cnb_data": "/api/get_cnb_data/",
            "get_patient_profile_detail": "/api/get_patient_profile_detail/",
            "get_centre_comparison": "/api/get_centre_comparison/"
        }
    })


def doctor_view(request):
    if 'doctor_id' not in request.session:
        return redirect('login')
    return render(request, 'doctor.html')


def centre_head_view(request):
    return render(request, 'centre-head.html')


def fc_view(request):
    return render(request, 'financial-counsellor.html')


def accounts_view(request):
    return render(request, 'accounts.html')


def management_view(request):
    return render(request, 'management.html')


def procedure_billing_view(request):
    if 'doctor_id' not in request.session:
        return redirect('login')
    return render(request, 'doctor.html') 


# ============================================================
# 3. CLINICAL FUNNEL (CNB) PIPELINE API ENDPOINTS
# ============================================================

def get_cnb_data(request):
    query = """
        SELECT 
            a.id AS appointment_internal_id,
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS patient_id, 
            a.wife_name AS name, 
            a.appoitmented_date AS date,
            a.councellor, 
            c.center_name, 
            d.name AS doctor_name,
            r.quality,
            r.fc_comment,
            r.latest_connected_date,
            r.latest_comment
        FROM hms_appointments a
        LEFT JOIN hms_patient_procedure p ON a.id = p.appointment_id
        LEFT JOIN hms_centers c ON a.appoitment_for = c.center_number
        LEFT JOIN hms_doctors d ON a.appoitmented_doctor = d.ID
        LEFT JOIN reports_patientcnb r ON COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) = r.patient_id
        WHERE a.paitent_type = 'new_patient' 
          AND a.status = 'consultation_done'
          AND p.appointment_id IS NULL
    """
    with connection.cursor() as cursor:
        cursor.execute(query)
        columns = [col[0] for col in cursor.description]
        results = [dict(zip(columns, row)) for row in cursor.fetchall()]
    return JsonResponse(results, safe=False)


@csrf_exempt 
def save_cnb_edits(request):
    if request.method == 'POST':
        try:
            data = json.loads(request.body)
            edits = data.get('edits', [])
            current_now = timezone.now() 

            with connection.cursor() as cursor:
                for edit in edits:
                    patient_id = edit.get('patient_id')
                    if not patient_id or str(patient_id).strip() == "" or str(patient_id).lower() == "none" or str(patient_id) == "—":
                        continue 

                    patient_id = str(patient_id).strip()
                    quality = edit.get('quality') or 'Cold'
                    fc_comment = edit.get('fc_comment') or ''
                    latest_comment = edit.get('latest_comment') or ''
                    latest_connected_date = edit.get('latest_connected_date')

                    if not latest_connected_date or str(latest_connected_date).strip() == '':
                        latest_connected_date = None

                    cursor.execute("SELECT id FROM reports_patientcnb WHERE patient_id = %s", [patient_id])
                    row = cursor.fetchone()

                    if row:
                        cursor.execute("""
                            UPDATE reports_patientcnb 
                            SET quality = %s, fc_comment = %s, latest_connected_date = %s, latest_comment = %s, updated_at = %s
                            WHERE patient_id = %s
                        """, [quality, fc_comment, latest_connected_date, latest_comment, current_now, patient_id])
                    else:
                        cursor.execute("""
                            INSERT INTO reports_patientcnb (patient_id, quality, fc_comment, latest_connected_date, latest_comment, created_at, updated_at) 
                            VALUES (%s, %s, %s, %s, %s, %s, %s)
                        """, [patient_id, quality, fc_comment, latest_connected_date, latest_comment, current_now, current_now])

            return JsonResponse({'status': 'success', 'message': f'Successfully saved {len(edits)} records!'})
        except Exception as e:
            return JsonResponse({'status': 'error', 'message': f'Database operational error: {str(e)}'}, status=500)

    return JsonResponse({'status': 'error', 'message': 'Use POST.'}, status=405)


# ============================================================
# 4. PATIENT-WISE REGISTRY LAYER FOR BILLING TABLES & BOOKED LISTS
# ============================================================

def get_procedure_billing_data(request):
    query = """
        SELECT 
            MIN(a.id) AS appointment_internal_id,
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS patient_id, 
            MIN(a.wife_name) AS name, 
            MIN(a.husband_name) AS husband_name,
            MIN(p.receipt_number) AS receipt_number,
            MIN(a.appoitmented_date) AS date,
            MIN(p.on_date) AS on_date,
            MIN(p.councellor) AS councellor, 
            MIN(c.center_name) AS center_name, 
            MIN(d.name) AS doctor_name,
            GROUP_CONCAT(DISTINCT p.code SEPARATOR ', ') AS code,
            SUM(p.fees) AS fees,
            IFNULL(SUM(pay.payment_done), 0) AS total_payment_done,
            (SUM(p.fees) - IFNULL(SUM(pay.payment_done), 0)) AS pending_amount
        FROM hms_appointments a
        INNER JOIN hms_patient_procedure p ON a.id = p.appointment_id
        LEFT JOIN hms_doctor_consultation dc ON a.id = dc.appointment_id
        LEFT JOIN hms_doctors d ON dc.doctor_id = d.ID 
        LEFT JOIN hms_centers c ON p.billing_at = c.center_number
        LEFT JOIN hms_patient_payments pay ON p.receipt_number = pay.billing_id AND pay.status IN ('0', '1')
        WHERE a.paitent_type = 'new_patient' 
          AND a.status = 'consultation_done'
          AND p.status IN ('pending', 'approved')
        GROUP BY COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR))
        ORDER BY MIN(a.appoitmented_date) DESC;
    """
    with connection.cursor() as cursor:
        cursor.execute(query)
        columns = [col[0] for col in cursor.description]
        results = [dict(zip(columns, row)) for row in cursor.fetchall()]

    for row in results:
        row['fees'] = float(row['fees']) if row['fees'] else 0.0
        row['total_payment_done'] = float(row['total_payment_done']) if row['total_payment_done'] else 0.0
        row['pending_amount'] = float(row['pending_amount']) if row['pending_amount'] else 0.0

    return JsonResponse(results, safe=False)


def get_dynamic_booked_patients(request):
    center_id = request.GET.get('center_id')
    centre_filter_sql = "AND p.billing_at = %s" if center_id else ""
    params = [center_id] if center_id else []

    query = f"""
        SELECT
            MIN(a.id) AS appointment_internal_id,
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS patient_id,
            MIN(a.wife_name) AS name,
            MIN(a.husband_name) AS husband_name,
            MIN(p.receipt_number) AS receipt_number,
            MIN(a.appoitmented_date) AS date,
            MIN(p.on_date) AS on_date,
            MIN(p.councellor) AS councellor,
            MIN(c.center_name) AS center_name,
            MIN(d.name) AS doctor_name,
            GROUP_CONCAT(DISTINCT p.code SEPARATOR ', ') AS code,
            SUM(p.fees) AS fees,
            COALESCE(SUM(pay_clean.total_paid), 0) AS total_payment_done,
            (SUM(p.fees) - COALESCE(SUM(pay_clean.total_paid), 0)) AS pending_amount
        FROM hms_appointments a
        INNER JOIN hms_patient_procedure p ON a.id = p.appointment_id
        LEFT JOIN hms_doctor_consultation dc ON a.id = dc.appointment_id
        LEFT JOIN hms_doctors d ON dc.doctor_id = d.ID
        LEFT JOIN hms_centers c ON p.billing_at = c.center_number
        LEFT JOIN (
            SELECT billing_id, SUM(payment_done) AS total_paid
            FROM hms_patient_payments
            WHERE status IN ('0', '1')
            GROUP BY billing_id
        ) pay_clean ON p.receipt_number = pay_clean.billing_id
        WHERE a.paitent_type = 'new_patient'
          AND a.status = 'consultation_done'
          AND p.status IN ('pending', 'approved')
          AND a.appoitmented_date >= '{DATA_START_DATE}'
          {centre_filter_sql}
        GROUP BY COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR))
        ORDER BY MIN(a.appoitmented_date) DESC;
    """
    with connection.cursor() as cursor:
        cursor.execute(query, params)
        columns = [col[0] for col in cursor.description]
        results = [dict(zip(columns, row)) for row in cursor.fetchall()]

    for row in results:
        row['fees'] = float(row['fees']) if row['fees'] else 0.0
        row['total_payment_done'] = float(row['total_payment_done']) if row['total_payment_done'] else 0.0
        row['pending_amount'] = float(row['pending_amount']) if row['pending_amount'] else 0.0

    return JsonResponse(results, safe=False)


# ============================================================
# 5. ALL PROCEDURES LIST LOOKUP UNDER UNIQUE PATIENT PROFILE DETAILS
# ============================================================

# reports/views.py

def check_form_data_exists(patient_id, receipt_number, procedure_id, form_area_table):
    """
    Checks if form record exists in dynamic form_area table using patient_id, receipt_number, and procedure_id
    """
    if not form_area_table or form_area_table.strip() == "":
        return False

    table_name = form_area_table.strip().lower()
    
    # Dynamic SQL query to check if record exists in the form table
    query = f"SELECT 1 FROM `{table_name}` WHERE `patient_id` = %s AND `receipt_number` = %s AND `procedure_id` = %s LIMIT 1;"
    
    try:
        with connection.cursor() as cursor:
            cursor.execute(query, [patient_id, receipt_number, procedure_id])
            row = cursor.fetchone()
            return row is not None
    except Exception as e:
        # Table might not exist or columns might differ
        return False


def get_patient_profile_detail(request):
    incoming_token = request.GET.get('receipt_number')
    if not incoming_token:
        return JsonResponse({'status': 'error', 'message': 'Receipt token parameter missing.'}, status=400)

    # 1. Demographics
    demographics_query = """
        SELECT 
            p.ID, p.patient_id, p.wife_name, p.husband_name, 
            p.patient_phone, p.wife_phone, p.wife_age, p.husband_age,
            p.wife_photo, p.husband_photo
        FROM hms_patients p
        LEFT JOIN hms_patient_procedure proc ON p.patient_id = proc.patient_id
        WHERE p.patient_id = %s OR proc.receipt_number = %s
        LIMIT 1;
    """

    # 2. Consultations (First Consult & CNB Visits)
    consultation_dates_query = """
        SELECT appoitmented_date
        FROM hms_appointments
        WHERE paitent_id = %s 
          AND status = 'consultation_done' 
          AND paitent_type = 'new_patient'
        ORDER BY appoitmented_date ASC;
    """

    # 3. Donor/Surrogate
    donor_query = """
        SELECT ID, uhid, patient_id, PatientName, donor_uhid, donor_patient_id, donor_PatientName, type, date 
        FROM hms_donor 
        WHERE patient_id = %s;
    """

    # 4. Procedures & Payment History
    procedures_query = """
        SELECT 
            proc.ID AS procedure_entry_id,
            proc.patient_id,
            IFNULL(proc.appointment_id, 0) AS appointment_id,
            proc.receipt_number,
            proc.procedure_id,
            proc.procedure_name,
            proc.code,
            proc.category,
            proc.broad_procedure,
            proc_master.broad_procedure_code AS cycle_group_code,
            proc_master.cycle AS cycle_stage_label,
            proc.fees,
            proc.on_date,
            IFNULL(pay_clean.total_paid, 0) AS payment_done,
            (proc.fees - IFNULL(pay_clean.total_paid, 0)) AS pending,
            GROUP_CONCAT(DISTINCT pf.ID SEPARATOR '||') AS form_ids,
            GROUP_CONCAT(DISTINCT pf.form_name SEPARATOR '||') AS form_names,
            GROUP_CONCAT(DISTINCT pf.form_area SEPARATOR '||') AS form_areas,
            GROUP_CONCAT(DISTINCT pf.type SEPARATOR '||') AS form_types,
            GROUP_CONCAT(DISTINCT IFNULL(pf.form_for, '') SEPARATOR '||') AS form_fors,
            GROUP_CONCAT(DISTINCT IFNULL(pf.unlock_percent, 0) SEPARATOR '||') AS form_unlock_percents,
            GROUP_CONCAT(DISTINCT cf.id SEPARATOR '||') AS concern_form_ids,
            GROUP_CONCAT(DISTINCT cf.form_name SEPARATOR '||') AS concern_form_names,
            GROUP_CONCAT(DISTINCT IFNULL(cf.form_for, '') SEPARATOR '||') AS concern_form_fors,
            GROUP_CONCAT(DISTINCT IFNULL(cf.type, '') SEPARATOR '||') AS concern_form_types,
            GROUP_CONCAT(DISTINCT CONCAT(IFNULL(pay_hist.on_date, ''), '::', IFNULL(pay_hist.billing_id, ''), '::', IFNULL(pay_hist.payment_done, 0), '::Cash') SEPARATOR '||') AS payment_history
        FROM hms_patient_procedure proc
        LEFT JOIN hms_procedures proc_master ON proc.procedure_id = proc_master.ID
        LEFT JOIN hms_form_relationship fr ON proc.procedure_id = fr.procedure_id
        LEFT JOIN hms_procedure_forms pf ON fr.form_id = pf.ID
        LEFT JOIN hms_concern_relation cr ON proc.procedure_id = cr.procedures_id
        LEFT JOIN hms_concern_forms cf ON cr.concern_id = cf.id AND cf.status = 'active'
        LEFT JOIN hms_patient_payments pay_hist ON proc.receipt_number = pay_hist.billing_id AND pay_hist.status IN ('0', '1')
        LEFT JOIN (
            SELECT billing_id, SUM(payment_done) AS total_paid 
            FROM hms_patient_payments 
            WHERE status IN ('0', '1')
            GROUP BY billing_id
        ) pay_clean ON proc.receipt_number = pay_clean.billing_id
        WHERE (proc.patient_id = %s OR proc.receipt_number = %s)
          AND proc.status IN ('pending', 'approved')
        GROUP BY proc.ID
        ORDER BY proc.on_date DESC, proc.ID DESC;
    """

    # 5. Updated Stage Queries
    stimulation_query = """
        SELECT date1, updated_at, last_menstrual 
        FROM ovulation_induction_protocol 
        WHERE receipt_number = %s OR patient_id = %s 
        ORDER BY ID DESC LIMIT 1;
    """
    trigger_opu_query = """
        SELECT last_inj_fsh, ovum_pick_up_on 
        FROM trigger_module 
        WHERE receipt_number = %s OR patient_id = %s 
        ORDER BY ID DESC LIMIT 1;
    """
    et_query = """
        SELECT transfer_date 
        FROM embryo_transfer 
        WHERE receipt_number = %s OR patient_id = %s 
        ORDER BY ID DESC LIMIT 1;
    """
    hcg_cardiac_query = """
        SELECT date, cardiac_activity_no 
        FROM hms_serum_bete_hcg_on 
        WHERE receipt_number = %s OR patient_id = %s 
        ORDER BY ID DESC LIMIT 1;
    """

    try:
        with connection.cursor() as cursor:
            # Demographics
            cursor.execute(demographics_query, [incoming_token, incoming_token])
            demo_row = cursor.fetchone()
            if not demo_row:
                return JsonResponse({'status': 'error', 'message': 'Patient record not found.'}, status=404)

            demo_cols = [col[0] for col in cursor.description]
            result_data = dict(zip(demo_cols, demo_row))
            actual_patient_id = result_data['patient_id']

            # Donors
            cursor.execute(donor_query, [actual_patient_id])
            donor_rows = cursor.fetchall()
            donor_cols = [col[0] for col in cursor.description]
            result_data['donors'] = [dict(zip(donor_cols, r)) for r in donor_rows]

            # Consultations
            cursor.execute(consultation_dates_query, [actual_patient_id])
            appointment_rows = cursor.fetchall()
            first_consult_date = appointment_rows[0][0] if len(appointment_rows) > 0 else None
            cnb_visit_date = appointment_rows[1][0] if len(appointment_rows) > 1 else None

            # Fetch Dates From Stage Queries (Pass both incoming_token and actual_patient_id)
            cursor.execute(stimulation_query, [incoming_token, actual_patient_id])
            stim_row = cursor.fetchone()
            stimulation_date = stim_row[0] if stim_row and stim_row[0] else None

            cursor.execute(trigger_opu_query, [incoming_token, actual_patient_id])
            trig_row = cursor.fetchone()
            trigger_date = trig_row[0] if trig_row and trig_row[0] else None
            opu_date = trig_row[1] if trig_row and trig_row[1] else None

            cursor.execute(et_query, [incoming_token, actual_patient_id])
            et_row = cursor.fetchone()
            embryo_transfer_date = et_row[0] if et_row and et_row[0] else None

            cursor.execute(hcg_cardiac_query, [incoming_token, actual_patient_id])
            hcg_row = cursor.fetchone()
            beta_hcg_date = hcg_row[0] if hcg_row and hcg_row[0] else None
            cardiac_activity_val = hcg_row[1] if hcg_row and hcg_row[1] else None

            # Procedures
            cursor.execute(procedures_query, [incoming_token, incoming_token])
            proc_rows = cursor.fetchall()
            proc_cols = [col[0] for col in cursor.description]

            procedures_list = []
            booked_date = None
            ivf_booked_date = None

            for row in proc_rows:
                p_dict = dict(zip(proc_cols, row))
                p_dict['fees'] = float(p_dict['fees']) if p_dict['fees'] else 0.0
                p_dict['payment_done'] = float(p_dict['payment_done']) if p_dict['payment_done'] else 0.0
                p_dict['pending'] = float(p_dict['pending']) if p_dict['pending'] else 0.0

                # "Booked" stage date = the day the IVF-with-Bed package was billed (earliest IVF
                # procedure). Falls back to the earliest booking of any kind when there is no IVF
                # procedure — never the First Consult date, and never a later add-on procedure.
                if p_dict.get('on_date'):
                    is_ivf_row = (
                        str(p_dict.get('broad_procedure') or '').strip().upper() == 'IVF'
                        or str(p_dict.get('category') or '').strip().lower().startswith('ivf')
                    )
                    if is_ivf_row and (ivf_booked_date is None or p_dict['on_date'] < ivf_booked_date):
                        ivf_booked_date = p_dict['on_date']
                    if booked_date is None or p_dict['on_date'] < booked_date:
                        booked_date = p_dict['on_date']

                # Payment Breakups
                raw_pay_history = p_dict['payment_history'].split('||') if p_dict.get('payment_history') else []
                payment_breakups = []
                for ph in raw_pay_history:
                    parts = ph.split('::')
                    if len(parts) == 4 and float(parts[2] or 0) > 0:
                        payment_breakups.append({
                            'date': parts[0],
                            'receipt_no': parts[1],
                            'amount': float(parts[2]),
                            'mode': parts[3]
                        })
                p_dict['payment_breakups'] = payment_breakups

                # Forms Array
                f_ids = p_dict['form_ids'].split('||') if p_dict.get('form_ids') else []
                f_names = p_dict['form_names'].split('||') if p_dict.get('form_names') else []
                f_areas = p_dict['form_areas'].split('||') if p_dict.get('form_areas') else []
                f_types = p_dict['form_types'].split('||') if p_dict.get('form_types') else []
                f_fors = p_dict['form_fors'].split('||') if p_dict.get('form_fors') else []
                f_unlocks = p_dict['form_unlock_percents'].split('||') if p_dict.get('form_unlock_percents') else []

                forms_array = []
                for idx in range(len(f_names)):
                    table_name = f_areas[idx] if idx < len(f_areas) else ""
                    is_filled_in_db = check_form_data_exists(actual_patient_id, p_dict['receipt_number'], p_dict['procedure_id'], table_name)
                    try:
                        unlock_percent = int(float(f_unlocks[idx])) if idx < len(f_unlocks) and f_unlocks[idx] != '' else 0
                    except (ValueError, TypeError):
                        unlock_percent = 0
                    forms_array.append({
                        'form_id': f_ids[idx] if idx < len(f_ids) else None,
                        'form_name': f_names[idx],
                        'form_area': table_name,
                        'type': f_types[idx] if idx < len(f_types) else 'general',
                        'form_for': f_fors[idx] if idx < len(f_fors) else '',
                        'unlock_percent': unlock_percent,
                        'is_filled': is_filled_in_db
                    })
                p_dict['linked_forms'] = forms_array

                # Consent / Compliance Forms (hms_concern_relation -> hms_concern_forms,
                # already filtered to active forms via cf.status = 'active' in the query)
                cf_ids = p_dict['concern_form_ids'].split('||') if p_dict.get('concern_form_ids') else []
                cf_names = p_dict['concern_form_names'].split('||') if p_dict.get('concern_form_names') else []
                cf_fors = p_dict['concern_form_fors'].split('||') if p_dict.get('concern_form_fors') else []
                cf_types = p_dict['concern_form_types'].split('||') if p_dict.get('concern_form_types') else []

                concern_forms_array = []
                for idx in range(len(cf_names)):
                    if not cf_names[idx]:
                        continue
                    concern_forms_array.append({
                        'form_id': cf_ids[idx] if idx < len(cf_ids) else None,
                        'form_name': cf_names[idx],
                        'form_for': cf_fors[idx] if idx < len(cf_fors) else '',
                        'type': cf_types[idx] if idx < len(cf_types) else 'general',
                    })
                p_dict['linked_concern_forms'] = concern_forms_array

                procedures_list.append(p_dict)

            booked_date = ivf_booked_date or booked_date

            # Stage Map
            stages_list = [
                {
                    "key": "First Consult",
                    "day": "Day 1",
                    "status": "done" if first_consult_date else "upcoming",
                    "completed_date": first_consult_date or "—"
                },
                {
                    "key": "CNB Visits",
                    "day": "Day 2",
                    "status": "done" if cnb_visit_date else "upcoming",
                    "completed_date": cnb_visit_date or "—"
                },
                {
                    "key": "Booked",
                    "day": "Day 3",
                    "status": "done" if booked_date else "upcoming",
                    "completed_date": booked_date or "—"
                },
                { "key": "Pre-Procedure", "day": "Day 4 - 12", "status": "upcoming", "completed_date": "—" },
                {
                    "key": "Ovarian Stimulation",
                    "day": "Day 13",
                    "status": "done" if stimulation_date else "upcoming",
                    "completed_date": stimulation_date or "—"
                },
                { "key": "Endometrial Preparation", "day": "Day 14", "status": "upcoming", "completed_date": "—" },
                {
                    "key": "Trigger",
                    "day": "Day 15 - 18",
                    "status": "done" if trigger_date else "upcoming",
                    "completed_date": trigger_date or "—"
                },
                {
                    "key": "OPU",
                    "day": "Day 19",
                    "status": "done" if opu_date else "upcoming",
                    "completed_date": opu_date or "—"
                },
                { "key": "Progesterone Change", "day": "Day 30", "status": "upcoming", "completed_date": "—" },
                {
                    "key": "Embryo Transfer",
                    "day": "Day 30",
                    "status": "done" if embryo_transfer_date else "upcoming",
                    "completed_date": embryo_transfer_date or "—"
                },
                {
                    "key": "B-HCG",
                    "day": "Day 30",
                    "status": "done" if beta_hcg_date else "upcoming",
                    "completed_date": beta_hcg_date or "—"
                },
                {
                    "key": "Cardiac Activity",
                    "day": "Day 30",
                    "status": "done" if cardiac_activity_val else "upcoming",
                    "completed_date": cardiac_activity_val or "—"
                },
            ]

            return JsonResponse({
                'status': 'success',
                'data': result_data,
                'procedures': procedures_list,
                'stages': stages_list
            })

    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ============================================================
# 6. CENTRE-WISE COLLECTION COMPARISON REPORT
# ============================================================

@csrf_exempt
def get_centre_comparison(request):
    center_id = request.GET.get('center_id')
    from_date = request.GET.get('from')
    to_date = request.GET.get('to')
    centre_filter_sql = "WHERE c.center_number = %s" if center_id else ""
    centre_params = [center_id] if center_id else []

    if from_date and to_date:
        # Period selected: EXPECTED/AGING scope to procedures booked in the window (p.on_date),
        # ACTUAL scopes to the payment transactions actually received in that same window —
        # p.payment_done is a running lifetime total, not date-filterable, so we sum the ledger
        # (hms_patient_payments) directly instead.
        query = """
            SELECT
                c.center_number,
                c.center_name AS CENTRE,
                COALESCE(SUM(p.totalpackage), 0) AS EXPECTED,
                COALESCE(SUM(pay_clean.total_paid), 0) AS ACTUAL,
                ROUND(
                    IF(SUM(p.totalpackage) > 0,
                       (COALESCE(SUM(pay_clean.total_paid), 0) / SUM(p.totalpackage)) * 100,
                       0
                    ), 2
                ) AS COLLECTION_ADHERENCE_PERCENT,
                COALESCE(SUM(p.remaining_amount), 0) AS AGING_OUTSTANDING
            FROM hms_centers c
            LEFT JOIN hms_patient_procedure p
                ON p.billing_at = c.center_number
                AND (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
                AND DATE(p.on_date) BETWEEN %s AND %s
            LEFT JOIN (
                SELECT billing_id, SUM(payment_done) AS total_paid
                FROM hms_patient_payments
                WHERE status IN ('0', '1') AND DATE(on_date) BETWEEN %s AND %s
                GROUP BY billing_id
            ) pay_clean ON pay_clean.billing_id = p.receipt_number
        """ + centre_filter_sql + """
            GROUP BY c.center_number, c.center_name
            ORDER BY c.center_name ASC;
        """
        params = [from_date, to_date, from_date, to_date] + centre_params
    else:
        query = f"""
            SELECT
                c.center_number,
                c.center_name AS CENTRE,
                COALESCE(SUM(p.totalpackage), 0) AS EXPECTED,
                COALESCE(SUM(p.payment_done), 0) AS ACTUAL,
                ROUND(
                    IF(SUM(p.totalpackage) > 0,
                       (SUM(p.payment_done) / SUM(p.totalpackage)) * 100,
                       0
                    ), 2
                ) AS COLLECTION_ADHERENCE_PERCENT,
                COALESCE(SUM(p.remaining_amount), 0) AS AGING_OUTSTANDING
            FROM hms_centers c
            LEFT JOIN hms_patient_procedure p
                ON p.billing_at = c.center_number
                AND (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
            {centre_filter_sql}
            GROUP BY c.center_number, c.center_name
            ORDER BY c.center_name ASC;
        """
        params = centre_params

    try:
        with connection.cursor() as cursor:
            cursor.execute(query, params)
            columns = [col[0] for col in cursor.description]
            results = [dict(zip(columns, row)) for row in cursor.fetchall()]

        for row in results:
            row['EXPECTED'] = float(row['EXPECTED']) if row['EXPECTED'] else 0.0
            row['ACTUAL'] = float(row['ACTUAL']) if row['ACTUAL'] else 0.0
            row['COLLECTION_ADHERENCE_PERCENT'] = float(row['COLLECTION_ADHERENCE_PERCENT']) if row['COLLECTION_ADHERENCE_PERCENT'] else 0.0
            row['AGING_OUTSTANDING'] = float(row['AGING_OUTSTANDING']) if row['AGING_OUTSTANDING'] else 0.0

        response = JsonResponse(results, safe=False)
        response["Access-Control-Allow-Origin"] = "*"
        response["Access-Control-Allow-Methods"] = "GET, OPTIONS"
        response["Access-Control-Allow-Headers"] = "*"
        return response

    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ============================================================
# 7. AGING SNAPSHOT (COMPANY BUCKETS + BY-CENTRE + PATIENT LIST)
# ============================================================

AGING_FOLLOWUP_STATUSES = [
    "Active",
    "Branch Action Required",
    "On Hold",
    "Revert to Telecaller team",
    "Cancellation Request",
]


def ensure_aging_followup_table():
    with connection.cursor() as cursor:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_agingfollowup (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice VARCHAR(100) NOT NULL,
                patient_id VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL,
                remarks TEXT,
                referred_date DATE NULL,
                history_log LONGTEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_invoice (invoice)
            )
        """)


@csrf_exempt
def save_aging_followup(request):
    if request.method != 'POST':
        return JsonResponse({'status': 'error', 'message': 'Use POST.'}, status=405)

    try:
        data = json.loads(request.body)
        invoice = str(data.get('invoice') or '').strip()
        patient_id = str(data.get('patient_id') or '').strip()
        new_status = str(data.get('status') or '').strip()
        remarks = str(data.get('remarks') or '').strip()

        if not invoice or not new_status:
            return JsonResponse({'status': 'error', 'message': 'invoice and status are required.'}, status=400)
        if new_status not in AGING_FOLLOWUP_STATUSES:
            return JsonResponse({'status': 'error', 'message': 'Unrecognized status.'}, status=400)

        ensure_aging_followup_table()

        current_now = timezone.now()
        referred_date = current_now.date() if new_status == 'Revert to Telecaller team' else None
        log_user = str(data.get('user') or '').strip()
        log_line = _fmt_log(log_user, f"{new_status}: {remarks}" if remarks else new_status)

        with connection.cursor() as cursor:
            cursor.execute("SELECT id, history_log, referred_date FROM reports_agingfollowup WHERE invoice = %s", [invoice])
            row = cursor.fetchone()

            if row:
                existing_id, existing_history, existing_referred = row
                new_history = f"{existing_history}\n{log_line}" if existing_history else log_line
                final_referred = referred_date or existing_referred
                cursor.execute("""
                    UPDATE reports_agingfollowup
                    SET status = %s, remarks = %s, referred_date = %s, history_log = %s, updated_at = %s
                    WHERE id = %s
                """, [new_status, remarks, final_referred, new_history, current_now, existing_id])
            else:
                cursor.execute("""
                    INSERT INTO reports_agingfollowup (invoice, patient_id, status, remarks, referred_date, history_log, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """, [invoice, patient_id, new_status, remarks, referred_date, log_line, current_now, current_now])

        return JsonResponse({'status': 'success', 'message': 'Follow-up saved.', 'logLine': log_line})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ============================================================
# 7b. STAGE DELAY REASONS (Clinical Journey: Stimulation / Trigger / OPU running late)
# ============================================================

STAGE_DELAY_REASONS = ['Financial Problem', 'Medical Issues', 'Personal Issues']


def ensure_stage_delay_table():
    with connection.cursor() as cursor:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_stagedelay (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id VARCHAR(50) NOT NULL,
                cycle_key VARCHAR(255) NOT NULL DEFAULT '',
                stage_key VARCHAR(100) NOT NULL,
                reason VARCHAR(50) NOT NULL,
                remarks TEXT,
                ideal_date DATE NULL,
                actual_date DATE NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_patient_cycle_stage (patient_id, cycle_key, stage_key)
            )
        """)


@csrf_exempt
def save_stage_delay(request):
    if request.method != 'POST':
        return JsonResponse({'status': 'error', 'message': 'Use POST.'}, status=405)
    try:
        data = json.loads(request.body)
        patient_id = str(data.get('patient_id') or '').strip()
        cycle_key = str(data.get('cycle_key') or '').strip()[:255]
        stage_key = str(data.get('stage_key') or '').strip()
        reason = str(data.get('reason') or '').strip()
        remarks = str(data.get('remarks') or '').strip()
        ideal_date = str(data.get('ideal_date') or '').strip()[:10] or None
        actual_date = str(data.get('actual_date') or '').strip()[:10] or None

        if not patient_id or not stage_key or not reason:
            return JsonResponse({'status': 'error', 'message': 'patient_id, stage_key and reason are required.'}, status=400)
        if reason not in STAGE_DELAY_REASONS:
            return JsonResponse({'status': 'error', 'message': 'Unrecognized reason.'}, status=400)

        ensure_stage_delay_table()
        with connection.cursor() as cursor:
            cursor.execute("""
                INSERT INTO reports_stagedelay (patient_id, cycle_key, stage_key, reason, remarks, ideal_date, actual_date)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), remarks = VALUES(remarks),
                                        ideal_date = VALUES(ideal_date), actual_date = VALUES(actual_date)
            """, [patient_id, cycle_key, stage_key, reason, remarks, ideal_date, actual_date])
        return JsonResponse({'status': 'success', 'message': 'Delay reason saved.'})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


@csrf_exempt
def get_stage_delays(request):
    patient_id = str(request.GET.get('patient_id') or '').strip()
    if not patient_id:
        return JsonResponse({'status': 'success', 'delays': []})
    try:
        ensure_stage_delay_table()
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT cycle_key, stage_key, reason, remarks
                FROM reports_stagedelay
                WHERE patient_id = %s
            """, [patient_id])
            delays = [
                {'cycle_key': r[0] or '', 'stage_key': r[1], 'reason': r[2], 'remarks': r[3] or ''}
                for r in cursor.fetchall()
            ]
        return JsonResponse({'status': 'success', 'delays': delays})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ============================================================
# 7c. AGING STATUS + ACTIONS (Treatment Completed / On Track / Delayed / Likely Cancellation,
#     Change Centre, Payment received)
# ============================================================

AGING_DELAY_REASONS = ['Financial Problem', 'Medical Issues', 'Personal Issues', 'Relocated']
AGING_CANCEL_REASONS = [
    'Financial', 'Medical issues', 'Divorce', 'Adoption', 'Self conceived',
    'Done from other IVF centre', 'Relocated', 'Personal Issues',
]


def ensure_aging_action_tables():
    with connection.cursor() as cursor:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_agingstatus (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice VARCHAR(100) NOT NULL,
                patient_id VARCHAR(50) NOT NULL,
                status_override VARCHAR(50) NOT NULL DEFAULT '',
                reason VARCHAR(80) NOT NULL DEFAULT '',
                remarks TEXT,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_invoice (invoice)
            )
        """)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_notifyrecipient (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kind VARCHAR(20) NOT NULL,
                email VARCHAR(190) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_kind_email (kind, email)
            )
        """)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_agingworkflow (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice VARCHAR(100) NOT NULL,
                patient_id VARCHAR(50) NOT NULL,
                state VARCHAR(60) NOT NULL DEFAULT '',
                reason VARCHAR(80) NOT NULL DEFAULT '',
                refund_amount DECIMAL(14,2) NULL,
                comment TEXT,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_invoice (invoice)
            )
        """)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_agingaction (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice VARCHAR(100) NOT NULL,
                patient_id VARCHAR(50) NOT NULL,
                action_type VARCHAR(30) NOT NULL,
                to_value VARCHAR(255) NOT NULL DEFAULT '',
                log_line TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_invoice (invoice)
            )
        """)


def _aging_auto_status(j, today):
    """
    Auto status from the patient's hms_patient_journey row (same milestone fields the dashboard
    stage widget uses):
      - Treatment Completed : embryo transfer already done
      - Delayed             : the next milestone is past its ideal date (booking + 10 days for
                              stimulation, + 18 for trigger, + 20 for OPU), or OPU was done later
                              than booking + 20 days
      - On Track            : otherwise
    Returns '' when there is no journey row to calculate from.
    """
    from datetime import timedelta
    if not j or not j.get('booking_date'):
        return ''
    booking = j['booking_date']
    if j.get('transfer_date'):
        return 'Treatment Completed'
    if j.get('opu_status') == 'Done':
        opu_date = j.get('actual_opu_date')
        if opu_date and opu_date > booking + timedelta(days=20):
            return 'Delayed'
        return 'On Track'
    if j.get('trigger_status') == 'Done':
        offset = 20
    elif j.get('stimulation_start_status') == 'Done':
        offset = 18
    else:
        offset = 10
    return 'Delayed' if today > booking + timedelta(days=offset) else 'On Track'



# ---- Complete History Log format:  {05-Jun-26 11:27 am} (user@indiaivf.in) - Action: remark ----
import re as _re_log
from datetime import datetime as _dt_log

_LOG_NEW = _re_log.compile(r'^\{(\d{2}-[A-Za-z]{3}-\d{2}) (\d{1,2}:\d{2}) (am|pm)\}')
_LOG_OLD = _re_log.compile(r'^\[(\d{2}-[A-Za-z]{3}-\d{4}) (\d{2}:\d{2})\]\s*(.*)$')


def _log_now():
    now = timezone.now()
    return timezone.localtime(now) if timezone.is_aware(now) else now


def _fmt_log(user, text, when=None):
    when = when or _log_now()
    stamp = when.strftime('%d-%b-%y %I:%M %p')[:-2] + when.strftime('%p').lower()
    who = f" ({user})" if user else ""
    return f"{{{stamp}}}{who} - {text}"


def _log_sort_key(line):
    """datetime a history line was written at, or None when it can't be read."""
    try:
        m = _LOG_NEW.match(line)
        if m:
            return _dt_log.strptime(f"{m.group(1)} {m.group(2)} {m.group(3).upper()}", '%d-%b-%y %I:%M %p')
        m = _LOG_OLD.match(line)
        if m:
            return _dt_log.strptime(f"{m.group(1)} {m.group(2)}", '%d-%b-%Y %H:%M')
    except Exception:
        pass
    return None


def _normalize_log_line(line):
    """Turn old '[30-Oct-2026 14:05] text' lines into the new '{30-Oct-26 02:05 pm} - text' format."""
    m = _LOG_OLD.match(line)
    if not m:
        return line
    try:
        when = _dt_log.strptime(f"{m.group(1)} {m.group(2)}", '%d-%b-%Y %H:%M')
    except Exception:
        return line
    return _fmt_log('', m.group(3), when)


def _history_text(*sources):
    """Merge log lines from several sources, newest first, one entry per line."""
    items = []
    for src in sources:
        for raw in (src or []):
            for line in str(raw).split("\n"):
                line = line.strip()
                if line:
                    items.append(line)
    keyed = [(_log_sort_key(l), i, _normalize_log_line(l)) for i, l in enumerate(items)]
    dated = sorted([k for k in keyed if k[0] is not None], key=lambda k: (k[0], k[1]), reverse=True)
    undated = [k for k in keyed if k[0] is None]
    return "\n".join([k[2] for k in dated] + [k[2] for k in undated])



# ---- SMTP settings editable from the frontend (stored in DB, no .env edit / restart needed) ----
def ensure_mail_config_table():
    with connection.cursor() as cursor:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_mailconfig (
                k VARCHAR(40) NOT NULL PRIMARY KEY,
                v TEXT
            )
        """)


def _load_mail_config():
    """Effective SMTP config: values saved from the frontend win, otherwise the .env / settings.py ones."""
    cfg = {}
    try:
        ensure_mail_config_table()
        with connection.cursor() as cursor:
            cursor.execute("SELECT k, v FROM reports_mailconfig")
            cfg = {k: (v or '') for k, v in cursor.fetchall()}
    except Exception:
        cfg = {}
    if cfg.get('host'):
        return {
            'source': 'database',
            'host': cfg.get('host', ''),
            'port': int(cfg.get('port') or 587),
            'use_tls': (cfg.get('use_tls', '1') == '1'),
            'user': cfg.get('user', ''),
            'password': cfg.get('password', ''),
            'from_email': cfg.get('from_email') or cfg.get('user', ''),
        }
    if str(dj_settings.EMAIL_BACKEND).endswith('smtp.EmailBackend'):
        return {
            'source': 'env',
            'host': getattr(dj_settings, 'EMAIL_HOST', ''),
            'port': int(getattr(dj_settings, 'EMAIL_PORT', 587) or 587),
            'use_tls': bool(getattr(dj_settings, 'EMAIL_USE_TLS', True)),
            'user': getattr(dj_settings, 'EMAIL_HOST_USER', ''),
            'password': getattr(dj_settings, 'EMAIL_HOST_PASSWORD', ''),
            'from_email': dj_settings.DEFAULT_FROM_EMAIL,
        }
    return {'source': 'none', 'host': '', 'port': 587, 'use_tls': True, 'user': '', 'password': '', 'from_email': dj_settings.DEFAULT_FROM_EMAIL}


@csrf_exempt
def mail_settings(request):
    """GET -> SMTP settings (never the password).  POST {host, port, useTls, user, password, fromEmail} saves; {op:'clear'} removes."""
    try:
        ensure_mail_config_table()
        if request.method == 'POST':
            data = json.loads(request.body)
            if str(data.get('op') or '') == 'clear':
                with connection.cursor() as cursor:
                    cursor.execute("DELETE FROM reports_mailconfig")
            else:
                host = str(data.get('host') or '').strip()
                user = str(data.get('user') or '').strip()
                from_email = str(data.get('fromEmail') or '').strip()
                password = str(data.get('password') or '')
                try:
                    port = int(data.get('port') or 587)
                except (TypeError, ValueError):
                    return JsonResponse({'status': 'error', 'message': 'Port must be a number.'}, status=400)
                if not host:
                    return JsonResponse({'status': 'error', 'message': 'SMTP host is required.'}, status=400)
                if from_email and '@' not in from_email:
                    return JsonResponse({'status': 'error', 'message': 'Enter a valid From email.'}, status=400)
                pairs = {'host': host, 'port': str(port), 'use_tls': '1' if data.get('useTls', True) else '0',
                         'user': user, 'from_email': from_email}
                if password:                      # blank = keep the saved password
                    pairs['password'] = password
                with connection.cursor() as cursor:
                    for k, v in pairs.items():
                        cursor.execute(
                            "INSERT INTO reports_mailconfig (k, v) VALUES (%s, %s) ON DUPLICATE KEY UPDATE v = VALUES(v)", [k, v])
        cfg = _load_mail_config()
        return JsonResponse({
            'status': 'success', 'source': cfg['source'], 'host': cfg['host'], 'port': cfg['port'],
            'useTls': cfg['use_tls'], 'user': cfg['user'], 'fromEmail': cfg['from_email'],
            'passwordSet': bool(cfg['password']), 'mailMode': _mail_mode(),
        })
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


NOTIFY_KINDS = ('admin', 'telecalling')


def _get_notify_recipients():
    ensure_aging_action_tables()
    out = {k: [] for k in NOTIFY_KINDS}
    with connection.cursor() as cursor:
        cursor.execute("SELECT kind, email FROM reports_notifyrecipient ORDER BY id ASC")
        for kind, email in cursor.fetchall():
            if kind in out:
                out[kind].append(email)
    return out


@csrf_exempt
def notify_recipients(request):
    """GET -> {admin: [...], telecalling: [...]}.  POST {kind, email, op: add|remove} -> updated lists."""
    import re
    try:
        if request.method == 'POST':
            data = json.loads(request.body)
            kind = str(data.get('kind') or '').strip()
            email = str(data.get('email') or '').strip().lower()
            op = str(data.get('op') or 'add').strip()
            if kind not in NOTIFY_KINDS:
                return JsonResponse({'status': 'error', 'message': 'Unknown recipient list.'}, status=400)
            if op == 'test':
                to_addr = str(data.get('to') or '').strip().lower()
                if to_addr and not re.match(r'^[^@\s]+@[^@\s]+\.[^@\s]+$', to_addr):
                    return JsonResponse({'status': 'error', 'message': 'Enter a valid email address.'}, status=400)
                result = _send_aging_mail(
                    [kind],
                    "[India IVF HMS] Test mail",
                    ["This is a test mail from the Aging page.", f"List: {kind}"],
                    extra_to=to_addr or None,
                )
                return JsonResponse({'status': 'success', 'mail': result, 'mailMode': _mail_mode(),
                                     'fromEmail': _load_mail_config()['from_email'], **_get_notify_recipients()})
            if not re.match(r'^[^@\s]+@[^@\s]+\.[^@\s]+$', email):
                return JsonResponse({'status': 'error', 'message': 'Enter a valid email address.'}, status=400)
            ensure_aging_action_tables()
            with connection.cursor() as cursor:
                if op == 'remove':
                    cursor.execute("DELETE FROM reports_notifyrecipient WHERE kind = %s AND email = %s", [kind, email])
                else:
                    cursor.execute("INSERT IGNORE INTO reports_notifyrecipient (kind, email) VALUES (%s, %s)", [kind, email])
        return JsonResponse({'status': 'success', 'mailMode': _mail_mode(),
                             'fromEmail': _load_mail_config()['from_email'], **_get_notify_recipients()})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


def _mail_mode():
    """'smtp' when real delivery is configured (EMAIL_HOST in .env), else 'console' (printed only)."""
    return 'smtp' if _load_mail_config()['host'] else 'console'


def _send_aging_mail(targets, subject, lines, extra_to=None):
    """Email the saved recipients for the given lists. Never raises — returns a small result dict."""
    try:
        lists = _get_notify_recipients()
        recipients = []
        for t in targets:
            for e in lists.get(t, []):
                if e not in recipients:
                    recipients.append(e)
        if extra_to:
            recipients = [extra_to]
        if not recipients:
            return {'sent': 0, 'mode': _mail_mode(), 'note': 'No ' + ' / '.join(targets) + ' email configured.'}
        body = "\n".join(lines) + "\n\n— India IVF HMS"
        cfg = _load_mail_config()
        if cfg['host']:
            from django.core.mail import get_connection
            conn = get_connection(
                backend='django.core.mail.backends.smtp.EmailBackend',
                host=cfg['host'], port=cfg['port'], username=cfg['user'], password=cfg['password'],
                use_tls=cfg['use_tls'], timeout=20, fail_silently=False,
            )
            send_mail(subject, body, cfg['from_email'] or cfg['user'], recipients, fail_silently=False, connection=conn)
        else:
            send_mail(subject, body, dj_settings.DEFAULT_FROM_EMAIL, recipients, fail_silently=False)
        return {'sent': len(recipients), 'mode': _mail_mode()}
    except Exception as e:
        return {'sent': 0, 'mode': _mail_mode(), 'error': str(e)}


@csrf_exempt
def save_aging_action(request):
    if request.method != 'POST':
        return JsonResponse({'status': 'error', 'message': 'Use POST.'}, status=405)
    try:
        data = json.loads(request.body)
        kind = str(data.get('kind') or '').strip()
        invoice = str(data.get('invoice') or '').strip()
        patient_id = str(data.get('patient_id') or '').strip()
        remarks = str(data.get('remarks') or '').strip()
        if not invoice or not kind:
            return JsonResponse({'status': 'error', 'message': 'invoice and kind are required.'}, status=400)

        ensure_aging_action_tables()
        stamp = timezone.now().strftime('%d-%b-%Y %H:%M')
        log_user = str(data.get('user') or '').strip()
        to_value = ''
        override = ''
        reason = str(data.get('reason') or '').strip()
        ctx = data.get('ctx') if isinstance(data.get('ctx'), dict) else {}
        notify_to = []        # recipient lists to email: 'admin' and/or 'telecalling'
        notify_subject = ""

        if kind == 'status':
            mode = str(data.get('mode') or 'auto').strip()
            if mode == 'likely_cancellation':
                if reason not in AGING_CANCEL_REASONS:
                    return JsonResponse({'status': 'error', 'message': 'Select a cancellation reason.'}, status=400)
                override = 'Likely Cancellation'
                log_line = f"[{stamp}] Status → Likely Cancellation — Reason: {reason}"
            else:
                if reason and reason not in AGING_DELAY_REASONS:
                    return JsonResponse({'status': 'error', 'message': 'Unrecognized delay reason.'}, status=400)
                log_line = f"[{stamp}] Status → auto" + (f" · Delay reason: {reason}" if reason else "")
            if remarks:
                log_line += f" — {remarks}"
            with connection.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO reports_agingstatus (invoice, patient_id, status_override, reason, remarks)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE status_override = VALUES(status_override), reason = VALUES(reason),
                                            remarks = VALUES(remarks)
                """, [invoice, patient_id, override, reason, remarks])

        elif kind == 'change_centre':
            from_centre = str(data.get('from_centre') or '').strip()
            to_centre = str(data.get('to_centre') or '').strip()
            if not to_centre or not reason:
                return JsonResponse({'status': 'error', 'message': 'New centre and reason are required.'}, status=400)
            if to_centre == from_centre:
                return JsonResponse({'status': 'error', 'message': 'Choose a different centre.'}, status=400)
            to_value = to_centre
            log_line = f"[{stamp}] Sales reporting centre: {from_centre or '—'} → {to_centre} — Reason: {reason}"

        elif kind in ('revert_tele', 'direct_cancellation', 'review_cancellation'):
            with connection.cursor() as cursor:
                cursor.execute("SELECT state FROM reports_agingworkflow WHERE invoice = %s", [invoice])
                row = cursor.fetchone()
            current_state = row[0] if row else ''
            new_state = ''
            refund_amount = None
            wf_comment = remarks
            wf_reason = ''
            mark_referred = False
            reactivate = False

            if kind == 'revert_tele':
                mode = str(data.get('mode') or 'revert').strip()
                if mode == 'revert':
                    new_state = 'With Telecalling (Megha)'
                    mark_referred = True
                    log_line = f"[{stamp}] Reverted to Telecalling Team (Megha)"
                    notify_to, notify_subject = ['telecalling'], "Patient reverted to Telecalling Team"
                elif mode == 'reactive':
                    new_state = 'ReActive – Returned to Branch'
                    reactivate = True
                    log_line = f"[{stamp}] ReActive — returned to branch"
                elif mode == 'cancellation_request':
                    if reason not in AGING_CANCEL_REASONS:
                        return JsonResponse({'status': 'error', 'message': 'Select a cancellation reason.'}, status=400)
                    new_state = 'Cancellation Requested'
                    wf_reason = reason
                    log_line = f"[{stamp}] Cancellation request (via Telecalling) — Reason: {reason}"
                    notify_to, notify_subject = ['admin'], "Cancellation request — approval needed"
                else:
                    return JsonResponse({'status': 'error', 'message': 'Unknown option.'}, status=400)

            elif kind == 'direct_cancellation':
                if reason not in AGING_CANCEL_REASONS:
                    return JsonResponse({'status': 'error', 'message': 'Select a cancellation reason.'}, status=400)
                new_state = 'Cancellation Requested'
                wf_reason = reason
                log_line = f"[{stamp}] Direct cancellation request — Reason: {reason}"
                notify_to, notify_subject = ['admin'], "Direct cancellation request — approval needed"

            else:  # review_cancellation (Superadmin)
                if current_state != 'Cancellation Requested':
                    return JsonResponse({'status': 'error', 'message': 'No pending cancellation request to review.'}, status=400)
                decision = str(data.get('decision') or '').strip()
                with connection.cursor() as cursor:
                    cursor.execute("SELECT reason FROM reports_agingworkflow WHERE invoice = %s", [invoice])
                    wf_reason = (cursor.fetchone() or [''])[0] or ''
                if decision == 'approved':
                    try:
                        refund_amount = float(data.get('refund_amount') or 0)
                    except (TypeError, ValueError):
                        return JsonResponse({'status': 'error', 'message': 'Enter a valid refund amount.'}, status=400)
                    if refund_amount < 0:
                        return JsonResponse({'status': 'error', 'message': 'Refund amount cannot be negative.'}, status=400)
                    new_state = 'Cancellation Approved'
                    log_line = f"[{stamp}] Cancellation APPROVED — Refund ₹{refund_amount:,.0f}"
                    notify_to, notify_subject = ['admin'], "Cancellation APPROVED"
                elif decision == 'not_approved':
                    route = str(data.get('route') or '').strip()
                    if route == 'forward_megha':
                        new_state = 'Forwarded to Megha'
                        mark_referred = True
                        log_line = f"[{stamp}] Cancellation NOT approved — forwarded to Megha"
                        notify_to, notify_subject = ['telecalling'], "Cancellation not approved — forwarded to Telecalling"
                    elif route == 'return_centre':
                        if not remarks:
                            return JsonResponse({'status': 'error', 'message': 'A comment is required when returning to the centre.'}, status=400)
                        new_state = 'Returned to Centre'
                        log_line = f"[{stamp}] Cancellation NOT approved — returned to centre"
                        notify_to, notify_subject = ['admin'], "Cancellation not approved — returned to centre"
                    else:
                        return JsonResponse({'status': 'error', 'message': 'Choose where to send it: Megha or the centre.'}, status=400)
                else:
                    return JsonResponse({'status': 'error', 'message': 'Choose Approved or Not Approved.'}, status=400)
            if remarks:
                log_line += f" — {remarks}"

            with connection.cursor() as cursor:
                cursor.execute("""
                    INSERT INTO reports_agingworkflow (invoice, patient_id, state, reason, refund_amount, comment)
                    VALUES (%s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE state = VALUES(state), reason = VALUES(reason),
                                            refund_amount = VALUES(refund_amount), comment = VALUES(comment)
                """, [invoice, patient_id, new_state, wf_reason, refund_amount, wf_comment])

                # Keep the existing follow-up columns (Follow-up Status / Date Referred to Tele) in sync.
                if mark_referred or reactivate:
                    fu_status = 'Revert to Telecaller team' if mark_referred else 'Active'
                    referred = timezone.now().date() if mark_referred else None
                    cursor.execute("SELECT id FROM reports_agingfollowup WHERE invoice = %s", [invoice])
                    if cursor.fetchone():
                        if referred:
                            cursor.execute("UPDATE reports_agingfollowup SET status = %s, referred_date = %s WHERE invoice = %s",
                                           [fu_status, referred, invoice])
                        else:
                            cursor.execute("UPDATE reports_agingfollowup SET status = %s WHERE invoice = %s", [fu_status, invoice])
                    else:
                        cursor.execute("""
                            INSERT INTO reports_agingfollowup (invoice, patient_id, status, remarks, referred_date, history_log)
                            VALUES (%s, %s, %s, %s, %s, %s)
                        """, [invoice, patient_id, fu_status, '', referred, ''])
        else:
            return JsonResponse({'status': 'error', 'message': 'Unknown action.'}, status=400)

        # Re-wrap the text in the standard history format: {date time am/pm} (user) - Action: remark
        _prefix = f"[{stamp}] "
        if log_line.startswith(_prefix):
            log_line = log_line[len(_prefix):]
        log_line = _fmt_log(log_user, log_line)

        with connection.cursor() as cursor:
            cursor.execute("""
                INSERT INTO reports_agingaction (invoice, patient_id, action_type, to_value, log_line)
                VALUES (%s, %s, %s, %s, %s)
            """, [invoice, patient_id, kind, to_value, log_line])

        resp = {'status': 'success', 'message': 'Saved.', 'logLine': log_line}
        if notify_to:
            mail_lines = [
                f"Action   : {notify_subject}",
                f"Patient  : {ctx.get('name') or '—'}  ({patient_id or '—'})",
                f"Invoice  : {invoice}",
                f"Centre   : {ctx.get('centre') or '—'}",
                f"Package  : {ctx.get('pkg') or '—'}",
                f"Outstanding : ₹{float(ctx.get('outstanding') or 0):,.0f}",
            ]
            if kind in ('revert_tele', 'direct_cancellation', 'review_cancellation'):
                if wf_reason:
                    mail_lines.append(f"Reason   : {wf_reason}")
                if refund_amount is not None:
                    mail_lines.append(f"Refund   : ₹{refund_amount:,.0f}")
                if wf_comment:
                    mail_lines.append(f"Comment  : {wf_comment}")
            mail_lines += ["", log_line]
            resp['mail'] = _send_aging_mail(notify_to, "[India IVF HMS] " + notify_subject, mail_lines)
        if kind in ('revert_tele', 'direct_cancellation', 'review_cancellation'):
            resp.update({
                'requestState': new_state,
                'requestReason': wf_reason,
                'refundAmount': refund_amount,
                'requestComment': wf_comment,
                'followStatus': ('Revert to Telecaller team' if mark_referred else ('Active' if reactivate else None)),
                'referredToday': bool(mark_referred),
            })
        return JsonResponse(resp)
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


@csrf_exempt
def get_aging_snapshot(request):
    center_id = request.GET.get('center_id')
    by_centre_filter_sql = "WHERE c.center_number = %s" if center_id else ""
    patients_filter_sql = "AND p.billing_at = %s" if center_id else ""
    params = [center_id] if center_id else []

    by_centre_query = f"""
        SELECT
            c.center_name AS centre,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), p.on_date) <= 30 THEN p.remaining_amount ELSE 0 END), 0) AS b0,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), p.on_date) BETWEEN 31 AND 60 THEN p.remaining_amount ELSE 0 END), 0) AS b1,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), p.on_date) BETWEEN 61 AND 90 THEN p.remaining_amount ELSE 0 END), 0) AS b2,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), p.on_date) BETWEEN 91 AND 180 THEN p.remaining_amount ELSE 0 END), 0) AS b3,
            COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), p.on_date) > 180 THEN p.remaining_amount ELSE 0 END), 0) AS b4
        FROM hms_centers c
        LEFT JOIN hms_patient_procedure p
            ON p.billing_at = c.center_number
            AND p.remaining_amount > 0
            AND p.on_date >= '{DATA_START_DATE}'
            AND (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
        {by_centre_filter_sql}
        GROUP BY c.center_number, c.center_name
        ORDER BY c.center_name ASC;
    """

    patients_query = f"""
        SELECT
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS id,
            a.wife_name AS name,
            c.center_name AS centre,
            p.councellor AS fc,
            p.procedure_name AS pkg,
            p.category AS pkg_desc,
            DATE(p.on_date) AS signup,
            p.totalpackage AS gross,
            p.discount_amount AS discount_amount,
            p.remaining_amount AS remaining_amount,
            DATEDIFF(CURDATE(), p.on_date) AS days_overdue,
            DATE(p.modified_on) AS last_fu,
            p.status AS status,
            p.receipt_number AS invoice,
            f.status AS follow_status,
            f.referred_date AS follow_referred,
            f.history_log AS follow_history,
            DATE(f.updated_at) AS follow_updated
        FROM hms_patient_procedure p
        LEFT JOIN hms_appointments a ON a.id = p.appointment_id
        LEFT JOIN hms_centers c ON p.billing_at = c.center_number
        LEFT JOIN reports_agingfollowup f ON f.invoice = p.receipt_number
        WHERE p.remaining_amount > 0
          AND p.on_date >= '{DATA_START_DATE}'
          AND (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
          {patients_filter_sql}
        ORDER BY p.on_date DESC;
    """

    try:
        ensure_aging_followup_table()
        with connection.cursor() as cursor:
            cursor.execute(by_centre_query, params)
            columns = [col[0] for col in cursor.description]
            centre_rows = [dict(zip(columns, row)) for row in cursor.fetchall()]

        by_centre = []
        company_buckets = [0.0] * 5
        for row in centre_rows:
            buckets = [float(row[f"b{i}"] or 0) for i in range(5)]
            for i, v in enumerate(buckets):
                company_buckets[i] += v
            by_centre.append({"centre": row["centre"], "buckets": buckets, "total": sum(buckets)})

        with connection.cursor() as cursor:
            cursor.execute(patients_query, params)
            columns = [col[0] for col in cursor.description]
            rows = [dict(zip(columns, row)) for row in cursor.fetchall()]

        # --- Auto status (journey milestones), manual overrides, and logged actions ---
        ensure_aging_action_tables()
        journey_by_patient = {}
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT patient_id, DATE(booking_date), stimulation_start_status, trigger_status, opu_status,
                       DATE(actual_opu_date), DATE(transfer_date)
                FROM hms_patient_journey
                WHERE booking_date IS NOT NULL
                ORDER BY booking_date ASC
            """)
            for pid, bdate, stim, trig, opu, opu_date, tr_date in cursor.fetchall():
                journey_by_patient[str(pid or "").strip()] = {
                    "booking_date": bdate,
                    "stimulation_start_status": stim,
                    "trigger_status": trig,
                    "opu_status": opu,
                    "actual_opu_date": opu_date,
                    "transfer_date": tr_date,
                }  # ascending order -> the latest booking wins

        overrides_by_invoice = {}
        with connection.cursor() as cursor:
            cursor.execute("SELECT invoice, status_override, reason, remarks FROM reports_agingstatus")
            for inv, ov, rsn, rmk in cursor.fetchall():
                overrides_by_invoice[inv] = {"override": ov or "", "reason": rsn or "", "remarks": rmk or ""}

        workflow_by_invoice = {}
        with connection.cursor() as cursor:
            cursor.execute("SELECT invoice, state, reason, refund_amount, comment FROM reports_agingworkflow")
            for inv, st, rsn, refund, cmt in cursor.fetchall():
                workflow_by_invoice[inv] = {
                    "state": st or "", "reason": rsn or "",
                    "refund": float(refund) if refund is not None else None, "comment": cmt or "",
                }

        action_lines = {}
        sales_centre_by_invoice = {}
        with connection.cursor() as cursor:
            cursor.execute("SELECT invoice, action_type, to_value, log_line FROM reports_agingaction ORDER BY id ASC")
            for inv, atype, to_val, line in cursor.fetchall():
                action_lines.setdefault(inv, []).append(line or "")
                if atype == "change_centre" and to_val:
                    sales_centre_by_invoice[inv] = to_val

        today_date = date.today()

        receipt_numbers = [r["invoice"] for r in rows if r["invoice"]]
        payments_by_receipt = {}
        if receipt_numbers:
            placeholders = ", ".join(["%s"] * len(receipt_numbers))
            pay_query = f"""
                SELECT billing_id, DATE_FORMAT(on_date, '%%Y-%%m') AS ym, SUM(payment_done) AS amt
                FROM hms_patient_payments
                WHERE status IN ('0', '1') AND billing_id IN ({placeholders})
                GROUP BY billing_id, ym
            """
            with connection.cursor() as cursor:
                cursor.execute(pay_query, receipt_numbers)
                for billing_id, ym, amt in cursor.fetchall():
                    payments_by_receipt.setdefault(billing_id, []).append({"m": ym, "a": float(amt or 0)})

        def bucket_idx(days):
            if days <= 30:
                return 0
            if days <= 60:
                return 1
            if days <= 90:
                return 2
            if days <= 180:
                return 3
            return 4

        def iso(value):
            if not value:
                return ""
            s = value.isoformat() if hasattr(value, "isoformat") else str(value)
            return "" if s.startswith("0000-00-00") else s

        bucket_counts = [0] * 5
        patients = []
        for r in rows:
            days = r["days_overdue"] or 0
            bucket_counts[bucket_idx(days)] += 1

            gross = float(r["gross"] or 0)
            discount_amount = float(r["discount_amount"] or 0)
            disc_pct = round((discount_amount / gross * 100), 2) if gross else 0.0

            patients.append({
                "id": r["id"],
                "name": r["name"] or "",
                "centre": r["centre"] or "Unassigned",
                "fc": r["fc"] or "",
                "pkg": r["pkg"] or "",
                "desc": r["pkg_desc"] or "",
                "signup": iso(r["signup"]),
                "gross": gross,
                "discPct": disc_pct,
                "incGst": gross - discount_amount,
                "daysOverdue": days,
                "lastFu": iso(r["follow_updated"]) or iso(r["last_fu"]),
                "referred": iso(r["follow_referred"]),
                "status": r["follow_status"] or r["status"] or "",
                "invoice": r["invoice"] or "",
                "history": _history_text([r["follow_history"] or ""], action_lines.get(r["invoice"], [])),
                "payments": payments_by_receipt.get(r["invoice"], []),
                "autoStatus": _aging_auto_status(journey_by_patient.get(str(r["id"] or "").strip()), today_date),
                "statusOverride": overrides_by_invoice.get(r["invoice"], {}).get("override", ""),
                "statusReason": overrides_by_invoice.get(r["invoice"], {}).get("reason", ""),
                "salesCentre": sales_centre_by_invoice.get(r["invoice"], ""),
                "requestState": workflow_by_invoice.get(r["invoice"], {}).get("state", ""),
                "requestReason": workflow_by_invoice.get(r["invoice"], {}).get("reason", ""),
                "refundAmount": workflow_by_invoice.get(r["invoice"], {}).get("refund"),
                "requestComment": workflow_by_invoice.get(r["invoice"], {}).get("comment", ""),
            })

        response = JsonResponse({
            "companyBuckets": company_buckets,
            "bucketCounts": bucket_counts,
            "byCentre": by_centre,
            "patients": patients,
        }, safe=False)
        response["Access-Control-Allow-Origin"] = "*"
        response["Access-Control-Allow-Methods"] = "GET, OPTIONS"
        response["Access-Control-Allow-Headers"] = "*"
        return response

    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ============================================================
# 8. PREBOOK LISTS (SCHEDULED / MISSED / CONSULTED-NOT-BOOKED)
# ============================================================

@csrf_exempt
def get_prebook_data(request):
    center_id = request.GET.get('center_id')
    appt_centre_filter_sql = "AND a.center = %s" if center_id else ""
    cnb_centre_filter_sql = "AND hc.billing_at = %s" if center_id else ""
    centre_params = [center_id] if center_id else []

    scheduled_query = f"""
        SELECT
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS id,
            a.wife_name AS name,
            DATE(a.appoitmented_date) AS date,
            c.center_name AS centre
        FROM hms_appointments a
        LEFT JOIN hms_centers c ON a.center = c.center_number
        WHERE a.status = 'booked'
          AND a.appoitmented_date >= '{DATA_START_DATE}'
          {appt_centre_filter_sql}
        ORDER BY a.appoitmented_date DESC;
    """

    missed_query = f"""
        SELECT
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS id,
            a.wife_name AS name,
            DATE(a.appoitmented_date) AS date,
            c.center_name AS centre
        FROM hms_appointments a
        LEFT JOIN hms_centers c ON a.center = c.center_number
        WHERE a.status = 'no_show'
          AND a.appoitmented_date >= '{DATA_START_DATE}'
          {appt_centre_filter_sql}
        ORDER BY a.appoitmented_date DESC;
    """

    # A consultation (hms_consultation) is "consulted not booked" when its appointment never
    # produced a row in hms_patient_procedure. hms_patient_procedure.appointment_id has no
    # index, so a JOIN against it forces a full nested-loop scan (20-30s+); NOT IN against the
    # small distinct-appointment_id subquery lets MySQL hash it instead, which is near-instant.
    # Rule (patient level): a patient is CNB when they have a row in hms_consultation and NO row at
    # all in hms_patient_procedure (any status, any appointment). Excluding by appointment alone
    # isn't enough — a patient who booked earlier and consulted again on a new appointment would
    # still show up — so the patient_id check below is what actually decides it.
    cnb_query = f"""
        SELECT
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS id,
            a.wife_name AS name,
            DATE(hc.on_date) AS date,
            c.center_name AS centre,
            d.name AS doctor,
            hc.reason_of_visit AS treatment,
            r.quality AS saved_quality,
            r.fc_comment AS saved_fc_comment,
            r.latest_connected_date AS saved_last_conn,
            r.latest_comment AS saved_last_comment
        FROM hms_consultation hc
        LEFT JOIN hms_appointments a ON a.id = hc.appointment_id
        LEFT JOIN hms_doctors d ON d.ID = hc.doctor_id
        LEFT JOIN hms_centers c ON c.center_number = hc.billing_at
        LEFT JOIN reports_patientcnb r
            ON r.patient_id = CONVERT(COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) USING utf8mb4) COLLATE utf8mb4_general_ci
        WHERE hc.appointment_id NOT IN (SELECT DISTINCT appointment_id FROM hms_patient_procedure)
          AND (
                COALESCE(NULLIF(TRIM(hc.patient_id), ''), NULLIF(TRIM(a.paitent_id), '')) IS NULL
                OR CONVERT(COALESCE(NULLIF(TRIM(hc.patient_id), ''), TRIM(a.paitent_id)) USING utf8mb4) COLLATE utf8mb4_general_ci NOT IN (
                    SELECT DISTINCT CONVERT(TRIM(bp.patient_id) USING utf8mb4) COLLATE utf8mb4_general_ci
                    FROM hms_patient_procedure bp
                    WHERE bp.patient_id IS NOT NULL
                      AND TRIM(bp.patient_id) <> ''
                )
              )
          AND hc.on_date >= '{DATA_START_DATE}'
          {cnb_centre_filter_sql}
        ORDER BY hc.on_date DESC;
    """

    def run(query):
        with connection.cursor() as cursor:
            cursor.execute(query, centre_params)
            columns = [col[0] for col in cursor.description]
            return [dict(zip(columns, row)) for row in cursor.fetchall()]

    def iso(value):
        if not value:
            return ""
        s = value.isoformat() if hasattr(value, "isoformat") else str(value)
        return "" if s.startswith("0000-00-00") else s

    try:
        results = []
        for row in run(scheduled_query):
            results.append({
                "id": row["id"],
                "name": row["name"] or "",
                "date": iso(row["date"]),
                "centre": row["centre"] or "Unassigned",
                "type": "scheduled",
            })
        for row in run(missed_query):
            results.append({
                "id": row["id"],
                "name": row["name"] or "",
                "date": iso(row["date"]),
                "centre": row["centre"] or "Unassigned",
                "type": "missed",
            })
        # A patient can have several hms_consultation rows (one per suggested procedure/visit);
        # collapse to a single row per patient, keeping the most recent (query is ORDER BY date DESC).
        seen_cnb_ids = set()
        for row in run(cnb_query):
            if row["id"] in seen_cnb_ids:
                continue
            seen_cnb_ids.add(row["id"])
            results.append({
                "id": row["id"],
                "name": row["name"] or "",
                "date": iso(row["date"]),
                "centre": row["centre"] or "Unassigned",
                "type": "cnb",
                "doctor": row["doctor"] or "",
                "treatment": row["treatment"] or "",
                "quality": row["saved_quality"] or "",
                "fcComment": row["saved_fc_comment"] or "",
                "lastConn": iso(row["saved_last_conn"]),
                "lastComment": row["saved_last_comment"] or "",
            })

        response = JsonResponse(results, safe=False)
        response["Access-Control-Allow-Origin"] = "*"
        response["Access-Control-Allow-Methods"] = "GET, OPTIONS"
        response["Access-Control-Allow-Headers"] = "*"
        return response

    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ============================================================
# 9. RED TRIGGER PILE-UP (MISSED COLLECTIONS, RANKED BY STAGE)
# ============================================================

@csrf_exempt
def get_red_triggers(request):
    """
    A patient falls into the pile-up when their journey row still has money
    owed (balance_amount > 0). The stage they're "stuck" at is the first of
    Stimulation / Trigger / OPU whose status is still Pending; a miss at OPU
    is flagged as the most critical trigger type, matching the ranking rule
    already shown on the /triggers page.
    """
    center_id = request.GET.get('center_id')
    # centre_booking is mostly a centre name, but a large chunk of rows (e.g. most of
    # Noida) store the raw center_number as text instead — match either form, and
    # resolve numeric values back to a display name via hms_centers.
    centre_filter_sql = "AND (j.centre_booking = (SELECT center_name FROM hms_centers WHERE center_number = %s) OR j.centre_booking = %s)" if center_id else ""
    params = [center_id, center_id] if center_id else []

    query = f"""
        SELECT
            j.patient_id,
            j.patients_name,
            COALESCE(c.center_name, j.centre_booking) AS centre_booking,
            DATE(j.booking_date) AS booking_date,
            j.balance_amount,
            j.stimulation_start_status,
            j.trigger_status,
            j.opu_status,
            DATEDIFF(CURDATE(), j.booking_date) AS days_since_booking
        FROM hms_patient_journey j
        LEFT JOIN hms_centers c ON j.centre_booking REGEXP '^[0-9]+$' AND c.center_number = CAST(j.centre_booking AS UNSIGNED)
        WHERE j.balance_amount > 0
          AND j.booking_date IS NOT NULL
          {centre_filter_sql}
        ORDER BY j.balance_amount DESC;
    """

    try:
        with connection.cursor() as cursor:
            cursor.execute(query, params)
            columns = [col[0] for col in cursor.description]
            rows = [dict(zip(columns, row)) for row in cursor.fetchall()]

        results = []
        for r in rows:
            if (r["opu_status"] or "Pending") != "Done":
                if (r["trigger_status"] or "Pending") != "Done":
                    stage = "Stimulation" if (r["stimulation_start_status"] or "Pending") != "Done" else "Trigger"
                else:
                    stage = "OPU"
            else:
                stage = "Post-OPU"

            trigger_type = "OPU miss" if stage == "OPU" else "Missed collection"
            days = r["days_since_booking"] or 0

            results.append({
                "id": str(r["patient_id"]),
                "type": trigger_type,
                "name": (r["patients_name"] or "").strip().title(),
                "centre": r["centre_booking"] or "Unassigned",
                "stage": stage,
                "value": float(r["balance_amount"] or 0),
                "days": int(days),
            })

        response = JsonResponse(results, safe=False)
        response["Access-Control-Allow-Origin"] = "*"
        response["Access-Control-Allow-Methods"] = "GET, OPTIONS"
        response["Access-Control-Allow-Headers"] = "*"
        return response

    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)

# ============================================================
# 10. DASHBOARD SUMMARY (KPIs, CLINICAL STAGE DISTRIBUTION, 7-DAY TREND)
# ============================================================

DASHBOARD_STAGE_LABELS = ["Booking", "LMP", "Stimulation", "Trigger", "OPU", "Embryology", "Embryo Transfer", "Beta HCG"]


def _classify_journey_stage(row):
    """
    hms_patient_journey only stores completion status for each milestone
    (withdrawal, stimulation-start, trigger, OPU, transfer) — there is no
    explicit "current stage" column, and status entry is sparse/inconsistent:
    many rows have opu_status='Done' while every earlier field is still blank
    (entered directly at whatever milestone the branch was updating). A strict
    front-to-back waterfall would misfile all of those as "just booked", so we
    instead take the MOST ADVANCED milestone marked done, in the same
    Stimulation/Trigger/OPU naming convention already used by
    get_red_triggers (a status field being "Done" means that step is behind
    them and they're working toward the next one). The two gaps with no
    status field at all (OPU -> Embryology, Transfer -> Beta HCG) are split
    with a 1-day-since-milestone heuristic: "today" is the just-finished task,
    "1+ day later" is the waiting task. LMP has no distinguishing signal in
    this schema and will stay at 0 until a real field for it exists.
    """
    opu_done = row["opu_status"] == "Done"
    trig_done = row["trigger_status"] == "Done"
    stim_done = row["stimulation_start_status"] == "Done"
    withdrawl_done = row["withdrawl_status"] == "Done"

    if opu_done:
        transfer_date = row["transfer_date"]
        if not transfer_date:
            opu_date = row["actual_opu_date"]
            days = (date.today() - opu_date).days if opu_date else 99
            return 4 if days <= 1 else 5  # OPU (just done) vs Embryology (culturing)
        days = (date.today() - transfer_date).days
        return 6 if days <= 1 else 7  # Embryo Transfer (just done) vs Beta HCG (waiting)
    if trig_done:
        return 4  # trigger given, working toward OPU
    if stim_done:
        return 3  # stimulating, working toward trigger
    if withdrawl_done:
        return 2  # withdrawal done, about to start stimulation
    return 0  # Booking


@csrf_exempt
def get_stage_patients(request):
    """
    Patient list behind the dashboard "clinical stage" widget (/stage/<idx>). Uses the exact same
    hms_patient_journey rows and _classify_journey_stage() as get_dashboard_summary, so the
    per-stage counts here always match the dashboard tiles. Package / net amount come from the
    patient's live bookings in hms_patient_procedure.
    """
    center_id = request.GET.get('center_id')
    journey_filter_sql = "AND (j.centre_booking = (SELECT center_name FROM hms_centers WHERE center_number = %s) OR j.centre_booking = %s)" if center_id else ""
    journey_params = [center_id, center_id] if center_id else []

    stage_query = f"""
        SELECT j.patient_id, j.patients_name,
               COALESCE(c.center_name, j.centre_booking) AS centre_booking,
               DATE(j.booking_date) AS booking_date,
               j.withdrawl_status, j.stimulation_start_status, j.trigger_status, j.opu_status,
               j.actual_opu_date, j.transfer_date
        FROM hms_patient_journey j
        LEFT JOIN hms_centers c ON j.centre_booking REGEXP '^[0-9]+$' AND c.center_number = CAST(j.centre_booking AS UNSIGNED)
        WHERE j.booking_date IS NOT NULL
          {journey_filter_sql}
        ORDER BY j.booking_date DESC;
    """

    pkg_query = """
        SELECT patient_id,
               GROUP_CONCAT(DISTINCT code ORDER BY code SEPARATOR ', ') AS pkg,
               COALESCE(SUM(fees), 0) AS net
        FROM hms_patient_procedure
        WHERE patient_id IS NOT NULL AND TRIM(patient_id) <> ''
          AND (status IS NULL OR status NOT IN ('cancelled', 'disapproved'))
        GROUP BY patient_id;
    """

    def iso(value):
        if not value:
            return ""
        s = value.isoformat() if hasattr(value, "isoformat") else str(value)
        return "" if s.startswith("0000-00-00") else s

    try:
        with connection.cursor() as cursor:
            cursor.execute(stage_query, journey_params)
            columns = [col[0] for col in cursor.description]
            journey_rows = [dict(zip(columns, row)) for row in cursor.fetchall()]

        with connection.cursor() as cursor:
            cursor.execute(pkg_query)
            pkg_by_patient = {
                str(pid).strip(): (pkg or "", float(net or 0))
                for pid, pkg, net in cursor.fetchall()
            }

        patients = []
        for r in journey_rows:
            pid = str(r["patient_id"] or "").strip()
            pkg, net = pkg_by_patient.get(pid, ("", 0.0))
            patients.append({
                "id": pid,
                "name": (r["patients_name"] or "").strip().title(),
                "centre": r["centre_booking"] or "Unassigned",
                "signup": iso(r["booking_date"]),
                "stage": _classify_journey_stage(r),
                "pkg": pkg,
                "net": net,
            })

        response = JsonResponse({"stageLabels": DASHBOARD_STAGE_LABELS, "patients": patients}, safe=False)
        response["Access-Control-Allow-Origin"] = "*"
        response["Access-Control-Allow-Methods"] = "GET, OPTIONS"
        response["Access-Control-Allow-Headers"] = "*"
        return response
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


@csrf_exempt
def get_dashboard_summary(request):
    center_id = request.GET.get('center_id')
    billing_filter_sql = "AND p.billing_at = %s" if center_id else ""
    billing_params = [center_id] if center_id else []
    # centre_booking is mostly a centre name, but a large chunk of rows (e.g. most of
    # Noida) store the raw center_number as text instead — match either form.
    journey_filter_sql = "AND (centre_booking = (SELECT center_name FROM hms_centers WHERE center_number = %s) OR centre_booking = %s)" if center_id else ""
    journey_params = [center_id, center_id] if center_id else []

    try:
        exp_query = f"""
            SELECT
                COALESCE(SUM(CASE WHEN DATE(p.on_date) = CURDATE() THEN p.totalpackage ELSE 0 END), 0) AS exp_today,
                COALESCE(SUM(CASE WHEN YEARWEEK(p.on_date, 1) = YEARWEEK(CURDATE(), 1) THEN p.totalpackage ELSE 0 END), 0) AS exp_week,
                COALESCE(SUM(CASE WHEN MONTH(p.on_date) = MONTH(CURDATE()) AND YEAR(p.on_date) = YEAR(CURDATE()) THEN p.totalpackage ELSE 0 END), 0) AS exp_month
            FROM hms_patient_procedure p
            WHERE (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
              {billing_filter_sql}
        """
        with connection.cursor() as cursor:
            cursor.execute(exp_query, billing_params)
            exp_today, exp_week, exp_month = cursor.fetchone()

        act_query = f"""
            SELECT
                COALESCE(SUM(CASE WHEN DATE(pay.on_date) = CURDATE() THEN pay.payment_done ELSE 0 END), 0) AS act_today,
                COALESCE(SUM(CASE WHEN YEARWEEK(pay.on_date, 1) = YEARWEEK(CURDATE(), 1) THEN pay.payment_done ELSE 0 END), 0) AS act_week,
                COALESCE(SUM(CASE WHEN MONTH(pay.on_date) = MONTH(CURDATE()) AND YEAR(pay.on_date) = YEAR(CURDATE()) THEN pay.payment_done ELSE 0 END), 0) AS act_month
            FROM hms_patient_payments pay
            INNER JOIN hms_patient_procedure p ON p.receipt_number = pay.billing_id
            WHERE pay.status IN ('0', '1')
              {billing_filter_sql}
        """
        with connection.cursor() as cursor:
            cursor.execute(act_query, billing_params)
            act_today, act_week, act_month = cursor.fetchone()

        trend_query = f"""
            SELECT DATE(pay.on_date) AS d, COALESCE(SUM(pay.payment_done), 0) AS amt
            FROM hms_patient_payments pay
            INNER JOIN hms_patient_procedure p ON p.receipt_number = pay.billing_id
            WHERE pay.status IN ('0', '1')
              AND pay.on_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
              {billing_filter_sql}
            GROUP BY DATE(pay.on_date)
            ORDER BY d ASC
        """
        with connection.cursor() as cursor:
            cursor.execute(trend_query, billing_params)
            trend_by_day = {row[0].isoformat(): float(row[1] or 0) for row in cursor.fetchall()}
        week_trend = []
        for i in range(6, -1, -1):
            d = date.today() - timezone.timedelta(days=i)
            week_trend.append(trend_by_day.get(d.isoformat(), 0.0))

        triggers_count_query = f"""
            SELECT COUNT(*) FROM hms_patient_journey
            WHERE balance_amount > 0 AND booking_date IS NOT NULL
            {journey_filter_sql}
        """
        with connection.cursor() as cursor:
            cursor.execute(triggers_count_query, journey_params)
            red_triggers = cursor.fetchone()[0]

        ensure_aging_followup_table()
        with connection.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) FROM reports_agingfollowup WHERE status = %s", ['Cancellation Request'])
            approvals_pending = cursor.fetchone()[0]

        stage_query = f"""
            SELECT COALESCE(c.center_name, j.centre_booking) AS centre_booking,
                   j.withdrawl_status, j.stimulation_start_status, j.trigger_status, j.opu_status,
                   j.actual_opu_date, j.transfer_date
            FROM hms_patient_journey j
            LEFT JOIN hms_centers c ON j.centre_booking REGEXP '^[0-9]+$' AND c.center_number = CAST(j.centre_booking AS UNSIGNED)
            WHERE j.booking_date IS NOT NULL
              {journey_filter_sql}
        """
        with connection.cursor() as cursor:
            cursor.execute(stage_query, journey_params)
            columns = [col[0] for col in cursor.description]
            journey_rows = [dict(zip(columns, row)) for row in cursor.fetchall()]

        stage_counts = [0] * 8
        centre_stage_counts = {}
        for row in journey_rows:
            idx = _classify_journey_stage(row)
            stage_counts[idx] += 1
            centre = row["centre_booking"] or "Unassigned"
            centre_stage_counts.setdefault(centre, [0] * 8)
            centre_stage_counts[centre][idx] += 1

        stage_by_centre = [
            {"centre": centre, "counts": counts, "total": sum(counts)}
            for centre, counts in sorted(centre_stage_counts.items())
        ]

        today = date.today()
        days_in_week = today.isoweekday()
        days_in_month = today.day
        total_days_in_month = calendar.monthrange(today.year, today.month)[1]
        week_projection = (float(act_week) / days_in_week * 7) if days_in_week else float(act_week)
        month_projection = (float(act_month) / days_in_month * total_days_in_month) if days_in_month else float(act_month)

        return JsonResponse({
            "kpi": {
                "today": {"exp": float(exp_today), "act": float(act_today), "redTriggers": red_triggers, "approvalsPending": approvals_pending},
                "week": {"exp": float(exp_week), "act": float(act_week), "projection": week_projection, "redTriggers": red_triggers},
                "month": {"exp": float(exp_month), "act": float(act_month), "projection": month_projection, "redTriggers": red_triggers},
            },
            "stageLabels": DASHBOARD_STAGE_LABELS,
            "stageCounts": stage_counts,
            "stageByCentre": stage_by_centre,
            "weekTrend": week_trend,
        })
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)
