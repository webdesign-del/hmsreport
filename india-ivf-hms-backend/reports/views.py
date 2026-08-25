import json
import hashlib
from django.shortcuts import render, redirect
from django.http import JsonResponse
from django.db import connection
from django.views.decorators.csrf import csrf_exempt 
from django.utils import timezone
from django.contrib import messages


# ============================================================
# 1. LEGACY HMS_DOCTORS DIRECT AUTHENTICATION & SESSIONS
# ============================================================

@csrf_exempt
def login_view(request):
    if request.method == "POST":
        # Handle both JSON payloads and Form Data
        if request.content_type == 'application/json':
            try:
                data = json.loads(request.body)
                username_input = data.get('username')
                password_input = data.get('password')
            except Exception:
                username_input = request.POST.get('username')
                password_input = request.POST.get('password')
        else:
            username_input = request.POST.get('username')
            password_input = request.POST.get('password')

        if not username_input or not password_input:
            return JsonResponse({'status': 'error', 'message': 'Username and password are required.'}, status=400)

        # MD5 hashing logic sequence matching standard
        password_md5 = hashlib.md5(password_input.encode('utf-8')).hexdigest()

        # Query checks against BOTH username and email
        query = """
            SELECT ID, name, email, username, password 
            FROM hms_doctors 
            WHERE (username = %s OR email = %s)
            LIMIT 1;
        """

        with connection.cursor() as cursor:
            cursor.execute(query, [username_input, username_input])
            doctor_row = cursor.fetchone()

        if doctor_row:
            db_id, db_name, db_email, db_username, db_password = doctor_row
            
            # Check MD5 hash OR Plain-text fallback
            if db_password == password_md5 or db_password == password_input:
                request.session['doctor_id'] = db_id
                request.session['doctor_name'] = db_name
                request.session['doctor_email'] = db_email
                request.session['doctor_username'] = db_username
                request.session['user_role'] = 'doctor'

                return JsonResponse({
                    'status': 'success',
                    'message': 'Logged in successfully',
                    'user': {
                        'id': db_id,
                        'name': db_name,
                        'email': db_email,
                        'username': db_username
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
            proc.fees,
            proc.on_date,
            IFNULL(pay_clean.total_paid, 0) AS payment_done,
            (proc.fees - IFNULL(pay_clean.total_paid, 0)) AS pending,
            GROUP_CONCAT(DISTINCT pf.ID SEPARATOR '||') AS form_ids,
            GROUP_CONCAT(DISTINCT pf.form_name SEPARATOR '||') AS form_names,
            GROUP_CONCAT(DISTINCT pf.form_area SEPARATOR '||') AS form_areas,
            GROUP_CONCAT(DISTINCT pf.type SEPARATOR '||') AS form_types,
            GROUP_CONCAT(DISTINCT IFNULL(pf.form_for, '') SEPARATOR '||') AS form_fors,
            GROUP_CONCAT(DISTINCT CONCAT(IFNULL(pay_hist.on_date, ''), '::', IFNULL(pay_hist.billing_id, ''), '::', IFNULL(pay_hist.payment_done, 0), '::Cash') SEPARATOR '||') AS payment_history
        FROM hms_patient_procedure proc
        LEFT JOIN hms_form_relationship fr ON proc.procedure_id = fr.procedure_id
        LEFT JOIN hms_procedure_forms pf ON fr.form_id = pf.ID
        LEFT JOIN hms_patient_payments pay_hist ON proc.receipt_number = pay_hist.billing_id AND pay_hist.status IN ('0', '1')
        LEFT JOIN (
            SELECT billing_id, SUM(payment_done) AS total_paid 
            FROM hms_patient_payments 
            WHERE status IN ('0', '1')
            GROUP BY billing_id
        ) pay_clean ON proc.receipt_number = pay_clean.billing_id
        WHERE proc.patient_id = %s OR proc.receipt_number = %s
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

            for row in proc_rows:
                p_dict = dict(zip(proc_cols, row))
                p_dict['fees'] = float(p_dict['fees']) if p_dict['fees'] else 0.0
                p_dict['payment_done'] = float(p_dict['payment_done']) if p_dict['payment_done'] else 0.0
                p_dict['pending'] = float(p_dict['pending']) if p_dict['pending'] else 0.0

                if not booked_date and p_dict.get('on_date'):
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

                forms_array = []
                for idx in range(len(f_names)):
                    table_name = f_areas[idx] if idx < len(f_areas) else ""
                    is_filled_in_db = check_form_data_exists(actual_patient_id, p_dict['receipt_number'], p_dict['procedure_id'], table_name)
                    forms_array.append({
                        'form_id': f_ids[idx] if idx < len(f_ids) else None,
                        'form_name': f_names[idx],
                        'form_area': table_name,
                        'type': f_types[idx] if idx < len(f_types) else 'general',
                        'form_for': f_fors[idx] if idx < len(f_fors) else '',
                        'is_filled': is_filled_in_db
                    })
                p_dict['linked_forms'] = forms_array
                procedures_list.append(p_dict)

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
    query = """
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
        GROUP BY c.center_number, c.center_name
        ORDER BY c.center_name ASC;
    """
    try:
        with connection.cursor() as cursor:
            cursor.execute(query)
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

@csrf_exempt
def get_aging_snapshot(request):
    by_centre_query = """
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
            AND (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
        GROUP BY c.center_number, c.center_name
        ORDER BY c.center_name ASC;
    """

    patients_query = """
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
            p.receipt_number AS invoice
        FROM hms_patient_procedure p
        LEFT JOIN hms_appointments a ON a.id = p.appointment_id
        LEFT JOIN hms_centers c ON p.billing_at = c.center_number
        WHERE p.remaining_amount > 0
          AND (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
        ORDER BY days_overdue DESC;
    """

    try:
        with connection.cursor() as cursor:
            cursor.execute(by_centre_query)
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
            cursor.execute(patients_query)
            columns = [col[0] for col in cursor.description]
            rows = [dict(zip(columns, row)) for row in cursor.fetchall()]

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
                "lastFu": iso(r["last_fu"]),
                "referred": "",
                "status": r["status"] or "",
                "invoice": r["invoice"] or "",
                "history": "",
                "payments": payments_by_receipt.get(r["invoice"], []),
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
    scheduled_query = """
        SELECT
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS id,
            a.wife_name AS name,
            DATE(a.appoitmented_date) AS date,
            c.center_name AS centre
        FROM hms_appointments a
        LEFT JOIN hms_centers c ON a.center = c.center_number
        WHERE a.status = 'booked'
        ORDER BY a.appoitmented_date DESC;
    """

    missed_query = """
        SELECT
            COALESCE(NULLIF(TRIM(a.paitent_id), ''), CAST(a.id AS CHAR)) AS id,
            a.wife_name AS name,
            DATE(a.appoitmented_date) AS date,
            c.center_name AS centre
        FROM hms_appointments a
        LEFT JOIN hms_centers c ON a.center = c.center_number
        WHERE a.status = 'no_show'
        ORDER BY a.appoitmented_date DESC;
    """

    # A consultation (hms_consultation) is "consulted not booked" when its appointment never
    # produced a row in hms_patient_procedure. hms_patient_procedure.appointment_id has no
    # index, so a JOIN against it forces a full nested-loop scan (20-30s+); NOT IN against the
    # small distinct-appointment_id subquery lets MySQL hash it instead, which is near-instant.
    cnb_query = """
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
        ORDER BY hc.on_date DESC;
    """

    def run(query):
        with connection.cursor() as cursor:
            cursor.execute(query)
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