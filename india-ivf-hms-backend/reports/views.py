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

# ---- "D/S" lead source: a patient with ANY hms_appointments row whose lead_source is 'D/S'
# must not show up anywhere in this app (lists, counts, journey, aging, dashboard). ----
import re as _re_ds
import time as _time_ds

_DS_CACHE = {'at': 0.0, 'ids': []}
_DS_TTL_SECONDS = 120


def ds_patient_ids():
    """Patient IDs that have ANY hms_appointments row with lead_source 'D/S'.
    Looked up once (cached ~2 min per worker) so every list query only needs a plain
    `NOT IN (<ids>)` - a per-query subquery was far too slow on the live database."""
    now = _time_ds.time()
    if now - _DS_CACHE['at'] < _DS_TTL_SECONDS:
        return _DS_CACHE['ids']
    ids = _DS_CACHE['ids']
    try:
        with connection.cursor() as cursor:
            cursor.execute(
                "SELECT DISTINCT TRIM(paitent_id) FROM hms_appointments "
                "WHERE lead_source = 'D/S' AND paitent_id IS NOT NULL AND paitent_id <> ''"
            )
            ids = sorted({str(r[0]) for r in cursor.fetchall()
                          if r[0] and _re_ds.fullmatch(r'[A-Za-z0-9_-]+', str(r[0]))})
    except Exception:
        pass  # keep the previous list (or none) rather than breaking every page
    _DS_CACHE['at'] = now
    _DS_CACHE['ids'] = ids
    return ids


def _ds_list_sql():
    return ", ".join("'" + i + "'" for i in ds_patient_ids())


def ds_in(col):
    """SQL condition: <col> (a patient id) belongs to a D/S patient."""
    lst = _ds_list_sql()
    return f"{col} IN ({lst})" if lst else "1 = 0"


def ds_not(col):
    """SQL fragment 'AND (<col> is not a D/S patient)' - col holds a patient id."""
    lst = _ds_list_sql()
    if not lst:
        return ""
    return f"AND ({col} IS NULL OR {col} NOT IN ({lst}))"


# the appointment row itself is D/S (covers appointments that have no patient id yet)
DS_A_ROW = "AND IFNULL(a.lead_source, '') <> 'D/S'"


def ds_a():
    return DS_A_ROW + " " + ds_not("a.paitent_id")   # appointment-based queries (alias a)


def ds_p():
    return ds_not("p.patient_id")                    # hms_patient_procedure (alias p)


def ds_j():
    return ds_not("j.patient_id")                    # hms_patient_journey (alias j)


def ds_hc():
    return ds_not("hc.patient_id")                   # hms_consultation (alias hc)


def is_ds_patient_token(token):
    """True when token (a patient id OR a receipt number) belongs to a D/S patient."""
    ids = set(ds_patient_ids())
    if not ids:
        return False
    token = str(token).strip()
    if token in ids:
        return True
    with connection.cursor() as cursor:
        cursor.execute("SELECT patient_id FROM hms_patient_procedure WHERE receipt_number = %s LIMIT 1", [token])
        row = cursor.fetchone()
    return bool(row and str(row[0]).strip() in ids)


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


def _pw_ok(stored, plain):
    """Match a stored password against what was typed: plain text, MD5 / SHA-1 / SHA-256 hex, or a Django / bcrypt hash."""
    stored = (stored or '').strip()
    if not stored:
        return False
    if stored == plain:
        return True
    raw = plain.encode('utf-8')
    low = stored.lower()
    if len(low) == 32 and low == hashlib.md5(raw).hexdigest():
        return True
    if len(low) == 40 and low == hashlib.sha1(raw).hexdigest():
        return True
    if len(low) == 64 and low == hashlib.sha256(raw).hexdigest():
        return True
    if stored.startswith('$'):
        try:
            import bcrypt
            if bcrypt.checkpw(raw, stored.replace('$2y$', '$2b$', 1).encode('utf-8')):
                return True
        except Exception:
            pass
    if '$' in stored:
        try:
            from django.contrib.auth.hashers import check_password
            if check_password(plain, stored):
                return True
        except Exception:
            pass
    return False


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

        username_input = (username_input or '').strip()
        password_input = password_input or ''
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
                WHERE (LOWER(TRIM(e.username)) = LOWER(%s) OR LOWER(TRIM(e.email)) = LOWER(%s))
                  AND (LOWER(TRIM(e.role)) IN ({role_marks}) OR LOWER(TRIM(e.other_role)) IN ({role_marks}))
                LIMIT 1;
            """
            with connection.cursor() as cursor:
                cursor.execute(employee_query, [username_input, username_input] + expected_roles + expected_roles)
                employee_row = cursor.fetchone()

            if employee_row:
                emp_id, emp_name, emp_email, emp_username, emp_password, emp_role, emp_status, emp_center_id, emp_center_name = employee_row

                if _pw_ok(emp_password, password_input):
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
                    "SELECT role, password, status FROM hms_employees "
                    "WHERE (LOWER(TRIM(username)) = LOWER(%s) OR LOWER(TRIM(email)) = LOWER(%s)) LIMIT 1",
                    [username_input, username_input])
                other = cursor.fetchone()
            # Server-side hint (shown in the Django terminal only — never sent to the browser).
            if not employee_row:
                print(f"[login] workspace={role_input!r} user={username_input!r}: "
                      + ("no account with that username/email" if not other
                         else f"account found but its role is {other[0]!r} (allowed here: {expected_roles}); "
                              f"password {'matches' if _pw_ok(other[1], password_input) else 'does NOT match'}"))
            else:
                print(f"[login] workspace={role_input!r} user={username_input!r}: account found, password does NOT match")
            if other and _pw_ok(other[1], password_input):
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
          """ + ds_a() + """
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
          """ + ds_a() + """
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
          {ds_a()}
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

    # D/S lead-source patients are never shown in this app.
    try:
        if is_ds_patient_token(incoming_token):
            return JsonResponse({'status': 'error', 'message': 'No patient profile match found in live database.'}, status=404)
    except Exception:
        pass

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
                """ + ds_p() + """
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
                {ds_p()}
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
        for col, ddl in (('next_followup_date', 'DATE NULL'), ('next_followup_set_at', 'DATETIME NULL')):
            cursor.execute("SHOW COLUMNS FROM reports_agingfollowup LIKE %s", [col])
            if not cursor.fetchone():
                cursor.execute(f"ALTER TABLE reports_agingfollowup ADD COLUMN {col} {ddl}")


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
        followup_only = bool(data.get('followup_only'))
        if followup_only and not remarks:
            return JsonResponse({'status': 'error', 'message': 'Please write the follow-up remarks.'}, status=400)
        next_date = None
        if followup_only:
            try:
                next_date = _dt_log.strptime(str(data.get('next_date') or '').strip(), '%Y-%m-%d').date()
            except ValueError:
                return JsonResponse({'status': 'error', 'message': 'Select the next follow-up date.'}, status=400)
            if next_date < timezone.localdate():
                return JsonResponse({'status': 'error', 'message': 'The next follow-up date cannot be in the past.'}, status=400)
        # A plain follow-up note never changes the status or the "referred to tele" date.
        referred_date = None if followup_only else (current_now.date() if new_status == 'Revert to Telecaller team' else None)
        log_user = str(data.get('user') or '').strip()
        # The follow-up popup may also flip the case between Active and On Hold.
        set_status = bool(followup_only and data.get('set_status') and new_status in ('Active', 'On Hold'))
        status_changed_note = ''
        if set_status:
            with connection.cursor() as cursor:
                cursor.execute("SELECT status FROM reports_agingfollowup WHERE invoice = %s", [invoice])
                prev = cursor.fetchone()
            if not prev or (prev[0] or '') != new_status:
                status_changed_note = f" — Status: {new_status}"
        if followup_only:
            log_line = _fmt_log(log_user, f"Follow-up: {remarks} — Next follow-up: {next_date.strftime('%d-%b-%y')}{status_changed_note}")
        else:
            log_line = _fmt_log(log_user, f"{new_status}: {remarks}" if remarks else new_status)

        with connection.cursor() as cursor:
            cursor.execute("SELECT id, history_log, referred_date FROM reports_agingfollowup WHERE invoice = %s", [invoice])
            row = cursor.fetchone()

            if row:
                existing_id, existing_history, existing_referred = row
                new_history = f"{existing_history}\n{log_line}" if existing_history else log_line
                final_referred = referred_date or existing_referred
                if followup_only and set_status:
                    cursor.execute("""
                        UPDATE reports_agingfollowup
                        SET status = %s, remarks = %s, history_log = %s, updated_at = %s
                        WHERE id = %s
                    """, [new_status, remarks, new_history, current_now, existing_id])
                elif followup_only:
                    cursor.execute("""
                        UPDATE reports_agingfollowup
                        SET remarks = %s, history_log = %s, updated_at = %s
                        WHERE id = %s
                    """, [remarks, new_history, current_now, existing_id])
                else:
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

        if next_date:
            with connection.cursor() as cursor:
                cursor.execute("UPDATE reports_agingfollowup SET next_followup_date = %s, next_followup_set_at = NOW() WHERE invoice = %s",
                               [next_date, invoice])
        _push_notif(invoice, patient_id, log_line)
        return JsonResponse({'status': 'success', 'message': 'Follow-up saved.', 'logLine': log_line,
                             'nextFu': next_date.isoformat() if next_date else '',
                             'followStatus': new_status if set_status else ''})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ============================================================
# 7b. STAGE DELAY REASONS (Clinical Journey: Stimulation / Trigger / OPU running late)
# ============================================================

STAGE_DELAY_REASONS = ['Financial Problem', 'Medical Issues', 'Personal Issues', 'Other']


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
        cursor.execute("SHOW COLUMNS FROM reports_stagedelay")
        have = {r[0] for r in cursor.fetchall()}
        for col, ddl in [
            ('admin_reply', 'TEXT NULL'),
            ('replied_by', "VARCHAR(150) NOT NULL DEFAULT ''"),
            ('replied_at', 'DATETIME NULL'),
            ('requested_by', "VARCHAR(150) NOT NULL DEFAULT ''"),
            ('requester_email', "VARCHAR(190) NOT NULL DEFAULT ''"),
            ('patient_name', "VARCHAR(255) NOT NULL DEFAULT ''"),
        ]:
            if col not in have:
                cursor.execute(f"ALTER TABLE reports_stagedelay ADD COLUMN {col} {ddl}")


@csrf_exempt
def save_stage_delay(request):
    if request.method != 'POST':
        return JsonResponse({'status': 'error', 'message': 'Use POST.'}, status=405)
    try:
        data = json.loads(request.body)
        patient_id = str(data.get('patient_id') or '').strip()
        cycle_key = str(data.get('cycle_key') or '').strip()[:255]
        stage_key = str(data.get('stage_key') or '').strip()
        log_user = str(data.get('user') or '').strip()
        user_role = str(data.get('role') or '').strip()

        # Administrator (Management) replying to a recorded delay reason
        if str(data.get('op') or '') == 'reply':
            if user_role not in ('management', 'superadmin'):
                return JsonResponse({'status': 'error', 'message': 'Only the Administrator can reply.'}, status=403)
            reply = str(data.get('reply') or '').strip()
            if not patient_id or not stage_key or not reply:
                return JsonResponse({'status': 'error', 'message': 'Please write your reply.'}, status=400)
            ok, msg, mail = _save_delay_reply(patient_id, cycle_key, stage_key, reply, log_user or 'Administrator')
            if not ok:
                return JsonResponse({'status': 'error', 'message': msg}, status=400)
            return JsonResponse({'status': 'success', 'message': msg, 'mail': mail, 'reply': reply,
                                 'repliedBy': log_user or 'Administrator'})

        reason = str(data.get('reason') or '').strip()
        remarks = str(data.get('remarks') or '').strip()
        ideal_date = str(data.get('ideal_date') or '').strip()[:10] or None
        actual_date = str(data.get('actual_date') or '').strip()[:10] or None
        req_email = str(data.get('requester_email') or '').strip().lower()
        if '@' not in req_email:
            req_email = ''
        patient_name = str(data.get('patient_name') or '').strip()[:255]

        if user_role == 'view_only':
            return JsonResponse({'status': 'error', 'message': 'View-only access — you cannot make changes.'}, status=403)
        if not patient_id or not stage_key or not reason:
            return JsonResponse({'status': 'error', 'message': 'patient_id, stage_key and reason are required.'}, status=400)
        if reason not in STAGE_DELAY_REASONS:
            return JsonResponse({'status': 'error', 'message': 'Unrecognized reason.'}, status=400)
        if reason == 'Other' and not remarks:
            return JsonResponse({'status': 'error', 'message': 'Please describe the reason in the remarks.'}, status=400)

        ensure_stage_delay_table()
        with connection.cursor() as cursor:
            cursor.execute("SELECT reason, remarks FROM reports_stagedelay WHERE patient_id = %s AND cycle_key = %s AND stage_key = %s",
                           [patient_id, cycle_key, stage_key])
            existing = cursor.fetchone()
            cursor.execute("""
                INSERT INTO reports_stagedelay (patient_id, cycle_key, stage_key, reason, remarks, ideal_date, actual_date,
                                                requested_by, requester_email, patient_name)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), remarks = VALUES(remarks),
                                        ideal_date = VALUES(ideal_date), actual_date = VALUES(actual_date),
                                        requested_by = VALUES(requested_by), requester_email = VALUES(requester_email),
                                        patient_name = IF(VALUES(patient_name) = '', patient_name, VALUES(patient_name))
            """, [patient_id, cycle_key, stage_key, reason, remarks, ideal_date, actual_date, log_user[:150], req_email[:190], patient_name])
        resp = {'status': 'success', 'message': 'Delay reason saved.'}
        changed = (not existing) or existing[0] != reason or (existing[1] or '') != remarks
        if changed:
            info = _delay_row(patient_id, cycle_key, stage_key)
            resp['mail'] = _send_delay_mail(request, data, info)
        return JsonResponse(resp)
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
                SELECT cycle_key, stage_key, reason, remarks, admin_reply, replied_by, replied_at, requested_by
                FROM reports_stagedelay
                WHERE patient_id = %s
            """, [patient_id])
            delays = [
                {'cycle_key': r[0] or '', 'stage_key': r[1], 'reason': r[2], 'remarks': r[3] or '',
                 'reply': r[4] or '', 'replied_by': r[5] or '',
                 'replied_at': r[6].strftime('%d-%b-%y %I:%M %p') if r[6] else '', 'requested_by': r[7] or ''}
                for r in cursor.fetchall()
            ]
        return JsonResponse({'status': 'success', 'delays': delays})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


# ---- Journey stage date changes ("Change dates" in the Change-in-Plan column) ----
def ensure_stage_date_change_table():
    with connection.cursor() as cursor:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_stagedatechange (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id VARCHAR(50) NOT NULL,
                cycle_key VARCHAR(255) NOT NULL DEFAULT '',
                stage_key VARCHAR(100) NOT NULL,
                from_date DATE NULL,
                to_date DATE NULL,
                reason TEXT,
                changed_by VARCHAR(150) NOT NULL DEFAULT '',
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_patient_cycle_stage (patient_id, cycle_key, stage_key)
            )
        """)


@csrf_exempt
def save_stage_date_change(request):
    if request.method != 'POST':
        return JsonResponse({'status': 'error', 'message': 'Use POST.'}, status=405)
    try:
        data = json.loads(request.body)
        patient_id = str(data.get('patient_id') or '').strip()
        cycle_key = str(data.get('cycle_key') or '').strip()[:255]
        stage_key = str(data.get('stage_key') or '').strip()[:100]
        from_date = str(data.get('from_date') or '').strip()[:10] or None
        to_date = str(data.get('to_date') or '').strip()[:10] or None
        reason = str(data.get('reason') or '').strip()
        user = str(data.get('user') or '').strip()[:150]
        role = str(data.get('role') or '').strip()
        if role == 'view_only':
            return JsonResponse({'status': 'error', 'message': 'View-only access - you cannot make changes.'}, status=403)
        if not patient_id or not stage_key or not from_date or not to_date:
            return JsonResponse({'status': 'error', 'message': 'patient_id, stage and dates are required.'}, status=400)
        if to_date < from_date:
            return JsonResponse({'status': 'error', 'message': 'TO date cannot be before FROM date.'}, status=400)
        if not reason:
            return JsonResponse({'status': 'error', 'message': 'Please enter the reason for the change.'}, status=400)
        ensure_stage_date_change_table()
        with connection.cursor() as cursor:
            cursor.execute("""
                INSERT INTO reports_stagedatechange (patient_id, cycle_key, stage_key, from_date, to_date, reason, changed_by)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE from_date = VALUES(from_date), to_date = VALUES(to_date),
                                        reason = VALUES(reason), changed_by = VALUES(changed_by)
            """, [patient_id, cycle_key, stage_key, from_date, to_date, reason, user])
        return JsonResponse({'status': 'success', 'message': 'Dates saved.'})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


@csrf_exempt
def get_stage_date_changes(request):
    patient_id = str(request.GET.get('patient_id') or '').strip()
    if not patient_id:
        return JsonResponse({'status': 'success', 'changes': []})
    try:
        ensure_stage_date_change_table()
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT cycle_key, stage_key, from_date, to_date, reason, changed_by, updated_at
                FROM reports_stagedatechange WHERE patient_id = %s
            """, [patient_id])
            changes = [
                {'cycle_key': r[0] or '', 'stage_key': r[1],
                 'from_date': r[2].strftime('%Y-%m-%d') if r[2] else '',
                 'to_date': r[3].strftime('%Y-%m-%d') if r[3] else '',
                 'reason': r[4] or '', 'changed_by': r[5] or '',
                 'updated_at': r[6].strftime('%d-%b-%y %I:%M %p') if r[6] else ''}
                for r in cursor.fetchall()
            ]
        return JsonResponse({'status': 'success', 'changes': changes})
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
            CREATE TABLE IF NOT EXISTS reports_centrechange (
                invoice VARCHAR(100) NOT NULL PRIMARY KEY,
                patient_id VARCHAR(50) NOT NULL,
                from_centre VARCHAR(255) NOT NULL DEFAULT '',
                to_centre VARCHAR(255) NOT NULL DEFAULT '',
                reason VARCHAR(500) NOT NULL DEFAULT '',
                state VARCHAR(20) NOT NULL DEFAULT '',
                requested_by VARCHAR(150) NOT NULL DEFAULT '',
                decided_by VARCHAR(150) NOT NULL DEFAULT '',
                decision_comment VARCHAR(500) NOT NULL DEFAULT '',
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        """)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_cancelrequest (
                invoice VARCHAR(100) NOT NULL PRIMARY KEY,
                patient_id VARCHAR(50) NOT NULL DEFAULT '',
                patient_name VARCHAR(255) NOT NULL DEFAULT '',
                centre VARCHAR(255) NOT NULL DEFAULT '',
                pkg VARCHAR(255) NOT NULL DEFAULT '',
                outstanding DECIMAL(14,2) NOT NULL DEFAULT 0,
                collected DECIMAL(14,2) NOT NULL DEFAULT 0,
                reason VARCHAR(255) NOT NULL DEFAULT '',
                remarks VARCHAR(500) NOT NULL DEFAULT '',
                requested_by VARCHAR(150) NOT NULL DEFAULT '',
                requester_email VARCHAR(190) NOT NULL DEFAULT '',
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

# ---- SMTP connection with a reliable CA bundle (fixes "CERTIFICATE_VERIFY_FAILED" on machines whose Python has no CA certs) ----
import ssl as _ssl
from django.core.mail.backends.smtp import EmailBackend as _DjangoSMTPBackend
from django.utils.functional import cached_property as _cached_property


class _VerifiedSMTPBackend(_DjangoSMTPBackend):
    """SMTP backend that verifies the server certificate against the 'certifi' bundle (plus the system store)."""
    @_cached_property
    def ssl_context(self):
        ctx = _ssl.create_default_context()
        try:
            import certifi
            ctx.load_verify_locations(cafile=certifi.where())
        except Exception:
            pass
        return ctx


class _UnverifiedSMTPBackend(_DjangoSMTPBackend):
    """Encrypted but WITHOUT certificate checking — only for networks that intercept TLS."""
    @_cached_property
    def ssl_context(self):
        ctx = _ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = _ssl.CERT_NONE
        return ctx


def _smtp_connection(cfg):
    from django.core.mail import get_connection
    backend = _UnverifiedSMTPBackend if not cfg.get('verify_ssl', True) else _VerifiedSMTPBackend
    use_ssl = int(cfg['port']) == 465            # implicit-SSL port
    return get_connection(
        backend=f"{backend.__module__}.{backend.__name__}",
        host=cfg['host'], port=cfg['port'], username=cfg['user'], password=cfg['password'],
        use_tls=(cfg['use_tls'] and not use_ssl), use_ssl=use_ssl, timeout=20, fail_silently=False,
    )


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
            'verify_ssl': (cfg.get('verify_ssl', '1') == '1'),
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
            'verify_ssl': True,
            'user': getattr(dj_settings, 'EMAIL_HOST_USER', ''),
            'password': getattr(dj_settings, 'EMAIL_HOST_PASSWORD', ''),
            'from_email': dj_settings.DEFAULT_FROM_EMAIL,
        }
    return {'source': 'none', 'host': '', 'port': 587, 'use_tls': True, 'verify_ssl': True, 'user': '', 'password': '', 'from_email': dj_settings.DEFAULT_FROM_EMAIL}


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
                         'verify_ssl': '1' if data.get('verifySsl', True) else '0',
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
            'useTls': cfg['use_tls'], 'verifySsl': cfg.get('verify_ssl', True), 'user': cfg['user'], 'fromEmail': cfg['from_email'],
            'passwordSet': bool(cfg['password']), 'mailMode': _mail_mode(),
        })
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


FINANCE_ROLES = ('accounts', 'management', 'superadmin')   # may approve / reject a centre change request
DIRECT_CENTRE_ROLES = ('management', 'superadmin')          # may change a centre directly, without a request


def centre_list(request):
    """Every centre (id + name), independent of the signed-in user's centre scope — used by the Change Centre picker."""
    try:
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT center_number, center_name FROM hms_centers
                WHERE center_name IS NOT NULL AND TRIM(center_name) <> ''
                ORDER BY center_name ASC
            """)
            rows = [{'id': r[0], 'name': str(r[1]).strip()} for r in cursor.fetchall()]
        return JsonResponse({'status': 'success', 'centres': rows})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)


NOTIFY_KINDS = ('admin', 'telecalling', 'accountant')


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
            if not _re_log.match(r'^[^@\s]+@[^@\s]+\.[^@\s]+$', email):
                return JsonResponse({'status': 'error', 'message': 'Enter a valid email address.'}, status=400)
            ensure_aging_action_tables()
            if op == 'remove' and str(data.get('role') or '').strip().lower() not in DIRECT_CENTRE_ROLES:
                return JsonResponse({'status': 'error', 'message': 'Only the Administrator can remove an email.'}, status=403)
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
            conn = _smtp_connection(cfg)
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
        user_role = str(data.get('role') or '').strip()
        centre_change = None
        new_sales_centre = None
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
            if user_role == 'view_only':
                return JsonResponse({'status': 'error', 'message': 'View-only access — you cannot make changes.'}, status=403)
            with connection.cursor() as cursor:
                if user_role == 'accounts':
                    return JsonResponse({'status': 'error', 'message': 'Accounts can approve or reject centre change requests, not change the centre directly.'}, status=403)
                if user_role in DIRECT_CENTRE_ROLES:
                    # Management / Superadmin change it straight away.
                    to_value = to_centre
                    log_line = f"[{stamp}] Sales reporting centre: {from_centre or '—'} → {to_centre} — Reason: {reason}"
                    cursor.execute("""
                        INSERT INTO reports_centrechange (invoice, patient_id, from_centre, to_centre, reason, state, requested_by, decided_by)
                        VALUES (%s, %s, %s, %s, %s, 'Approved', %s, %s)
                        ON DUPLICATE KEY UPDATE from_centre = VALUES(from_centre), to_centre = VALUES(to_centre), reason = VALUES(reason),
                                                state = 'Approved', requested_by = VALUES(requested_by), decided_by = VALUES(decided_by),
                                                decision_comment = ''
                    """, [invoice, patient_id, from_centre, to_centre, reason, log_user, log_user])
                    centre_change = {'state': 'Approved', 'to': to_centre, 'reason': reason, 'by': log_user}
                    new_sales_centre = to_centre
                else:
                    # Counsellor / centre staff only REQUEST it — Accounts approves.
                    to_value = ''
                    log_line = f"[{stamp}] Centre change requested: {from_centre or '—'} → {to_centre} — Reason: {reason}"
                    cursor.execute("""
                        INSERT INTO reports_centrechange (invoice, patient_id, from_centre, to_centre, reason, state, requested_by, decided_by)
                        VALUES (%s, %s, %s, %s, %s, 'Requested', %s, '')
                        ON DUPLICATE KEY UPDATE from_centre = VALUES(from_centre), to_centre = VALUES(to_centre), reason = VALUES(reason),
                                                state = 'Requested', requested_by = VALUES(requested_by), decided_by = '',
                                                decision_comment = ''
                    """, [invoice, patient_id, from_centre, to_centre, reason, log_user])
                    centre_change = {'state': 'Requested', 'to': to_centre, 'reason': reason, 'by': log_user}

        elif kind == 'review_centre_change':
            if user_role not in FINANCE_ROLES:
                return JsonResponse({'status': 'error', 'message': 'Only the Accounts team can approve a centre change.'}, status=403)
            decision = str(data.get('decision') or '').strip()
            with connection.cursor() as cursor:
                cursor.execute("SELECT from_centre, to_centre, reason, state, requested_by FROM reports_centrechange WHERE invoice = %s", [invoice])
                row = cursor.fetchone()
            if not row or row[3] != 'Requested':
                return JsonResponse({'status': 'error', 'message': 'There is no pending centre change request for this invoice.'}, status=400)
            req_from, req_to, req_reason, _st, req_by = row
            if decision == 'approved':
                pick = str(data.get('to_centre') or '').strip()
                if pick and pick != req_to:          # Accounts chose a different centre than the one requested
                    req_to = pick
                    with connection.cursor() as cursor:
                        cursor.execute("UPDATE reports_centrechange SET to_centre = %s WHERE invoice = %s", [req_to, invoice])
                to_value = req_to
                log_line = f"[{stamp}] Centre change APPROVED: {req_from or '—'} → {req_to}"
                new_state_cc = 'Approved'
                new_sales_centre = req_to
            elif decision == 'rejected':
                to_value = ''
                log_line = f"[{stamp}] Centre change NOT approved: {req_from or '—'} → {req_to}"
                new_state_cc = 'Rejected'
            else:
                return JsonResponse({'status': 'error', 'message': 'Choose Approve or Reject.'}, status=400)
            if remarks:
                log_line += f" — {remarks}"
            with connection.cursor() as cursor:
                cursor.execute("""
                    UPDATE reports_centrechange SET state = %s, decided_by = %s, decision_comment = %s WHERE invoice = %s
                """, [new_state_cc, log_user, remarks[:500], invoice])
            centre_change = {'state': new_state_cc, 'to': req_to, 'reason': req_reason, 'by': req_by}

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
            send_tele_mail = False

            if kind == 'revert_tele':
                mode = str(data.get('mode') or 'revert').strip()
                if mode == 'revert':
                    new_state = 'With Telecalling'
                    mark_referred = True
                    log_line = f"[{stamp}] Reverted to Telecalling Team"
                    notify_to, notify_subject = [], ""
                    send_tele_mail = True      # HTML mail with the two buttons is built after the log line is final
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
                notify_to, notify_subject = [], ""      # mails are built below (approval links + requester + accountant cc)

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
                    notify_to, notify_subject = [], ""
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
                        notify_to, notify_subject = [], ""
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
        _push_notif(invoice, patient_id, log_line)

        resp = {'status': 'success', 'message': 'Saved.', 'logLine': log_line}
        if centre_change is not None:
            resp['centreChange'] = centre_change
            resp['salesCentre'] = new_sales_centre
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
        if kind == 'revert_tele' and send_tele_mail:
            resp['mail'] = _send_revert_mail(request, data, ctx, invoice, patient_id, remarks, log_user, log_line)
        if kind == 'direct_cancellation':
            resp['mail'] = _send_cancel_request_mails(request, data, ctx, invoice, patient_id, wf_reason, remarks, log_user)
        if kind == 'review_cancellation':
            fb = {'patient_id': patient_id, 'name': ctx.get('name') or '', 'centre': ctx.get('centre') or '', 'pkg': ctx.get('pkg') or '',
                  'reason': wf_reason}
            dec_mail = _notify_cancel_decision(invoice, 'approved' if new_state == 'Cancellation Approved' else 'disapproved',
                                               refund_amount, wf_comment, log_user, fallback=fb)
            resp['mailDecision'] = dec_mail
            if 'mail' not in resp:
                resp['mail'] = dec_mail
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
            {ds_p()}
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
            DATE(f.updated_at) AS follow_updated,
            f.next_followup_date AS next_fu,
            f.next_followup_set_at AS next_fu_set,
            p.modified_on AS proc_modified
        FROM hms_patient_procedure p
        LEFT JOIN hms_appointments a ON a.id = p.appointment_id
        LEFT JOIN hms_centers c ON p.billing_at = c.center_number
        LEFT JOIN reports_agingfollowup f ON f.invoice = p.receipt_number
        WHERE p.remaining_amount > 0
          AND p.on_date >= '{DATA_START_DATE}'
          AND (p.status IS NULL OR p.status NOT IN ('cancelled', 'disapproved'))
          {ds_p()}
          {DS_A_ROW}
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

        centre_change_by_invoice = {}
        try:
            with connection.cursor() as cursor:
                cursor.execute("SELECT invoice, state, to_centre, reason, requested_by, decision_comment FROM reports_centrechange")
                for inv, st, to_c, rsn, by, cmt in cursor.fetchall():
                    centre_change_by_invoice[inv] = {"state": st or "", "to": to_c or "", "reason": rsn or "", "by": by or "", "comment": cmt or ""}
        except Exception:
            centre_change_by_invoice = {}

        action_lines = {}
        sales_centre_by_invoice = {}
        with connection.cursor() as cursor:
            cursor.execute("SELECT invoice, action_type, to_value, log_line FROM reports_agingaction ORDER BY id ASC")
            for inv, atype, to_val, line in cursor.fetchall():
                action_lines.setdefault(inv, []).append(line or "")
                if atype in ("change_centre", "review_centre_change") and to_val:
                    sales_centre_by_invoice[inv] = to_val
            cursor.execute("SELECT invoice, MAX(created_at) FROM reports_agingaction GROUP BY invoice")
            last_action_at = {inv: ts for inv, ts in cursor.fetchall()}

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

            # Next follow-up: "Overdue" when the date has passed and nothing happened on this procedure since it was set
            # (no new action / comment and no change on the procedure itself, e.g. a payment).
            next_fu = r.get("next_fu")
            fu_overdue, fu_days = False, 0
            if next_fu and next_fu < today_date:
                set_at = r.get("next_fu_set")
                acts = [t for t in (last_action_at.get(r["invoice"]), r.get("proc_modified")) if t]
                try:
                    last_act = max(acts) if acts else None
                    active_since = bool(set_at and last_act and last_act > set_at)
                except TypeError:
                    active_since = False
                fu_overdue = not active_since
                fu_days = (today_date - next_fu).days if fu_overdue else 0

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
                "nextFu": iso(next_fu),
                "fuOverdue": fu_overdue,
                "fuOverdueDays": fu_days,
                "status": r["follow_status"] or r["status"] or "",
                "invoice": r["invoice"] or "",
                "history": _history_text([r["follow_history"] or ""], action_lines.get(r["invoice"], [])),
                "payments": payments_by_receipt.get(r["invoice"], []),
                "autoStatus": _aging_auto_status(journey_by_patient.get(str(r["id"] or "").strip()), today_date),
                "statusOverride": overrides_by_invoice.get(r["invoice"], {}).get("override", ""),
                "statusReason": overrides_by_invoice.get(r["invoice"], {}).get("reason", ""),
                "salesCentre": sales_centre_by_invoice.get(r["invoice"], ""),
                "centreChangeState": centre_change_by_invoice.get(r["invoice"], {}).get("state", ""),
                "centreChangeTo": centre_change_by_invoice.get(r["invoice"], {}).get("to", ""),
                "centreChangeReason": centre_change_by_invoice.get(r["invoice"], {}).get("reason", ""),
                "centreChangeBy": centre_change_by_invoice.get(r["invoice"], {}).get("by", ""),
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
def _php_unserialize(raw):
    """Minimal PHP unserialize (arrays / strings / ints) for hms_doctor_consultation list columns."""
    b = raw if isinstance(raw, bytes) else str(raw).encode('utf-8')
    pos = 0

    def parse():
        nonlocal pos
        t = b[pos:pos + 1]
        if t == b'N':
            pos += 2
            return None
        if t in (b'i', b'd', b'b'):
            end = b.index(b';', pos)
            v = b[pos + 2:end]
            pos = end + 1
            return float(v) if t == b'd' else int(v)
        if t == b's':
            colon = b.index(b':', pos + 2)
            n = int(b[pos + 2:colon])
            start = colon + 2
            v = b[start:start + n]
            pos = start + n + 2
            return v.decode('utf-8', 'ignore')
        if t == b'a':
            colon = b.index(b':', pos + 2)
            n = int(b[pos + 2:colon])
            pos = colon + 2
            out = {}
            for _ in range(n):
                k = parse()
                out[k] = parse()
            pos += 1
            return out
        raise ValueError('unsupported serialized value')

    return parse()


def _advised_procedure_ids(raw):
    """Procedure IDs (hms_procedures.ID) from hms_doctor_consultation.sub_procedure_suggestion_list.
    The column is a PHP-serialized array whose items are procedure IDs (an item may itself be a
    comma-separated list of IDs); a plain '12,34' string is accepted too."""
    ids = []

    def collect(v):
        if isinstance(v, dict):
            for x in v.values():
                collect(x)
        elif isinstance(v, int):
            if v > 0:
                ids.append(v)
        elif isinstance(v, str):
            for part in v.split(','):
                part = part.strip()
                if part.isdigit() and int(part) > 0:
                    ids.append(int(part))

    if not raw:
        return ids
    try:
        collect(_php_unserialize(raw))
    except Exception:
        collect(str(raw))
    seen = set()
    return [i for i in ids if not (i in seen or seen.add(i))]


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
          {ds_a()}
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
          {ds_a()}
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
            hc.appointment_id AS appointment_id,
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
          {ds_a()}
          {ds_hc()}
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
        cnb_rows = run(cnb_query)

        # "Treatment advised" = the procedures the doctor advised in that consultation
        # (hms_doctor_consultation.sub_procedure_suggestion_list -> hms_procedures.procedure_name).
        advised_by_appt = {}
        appt_ids = sorted({int(r["appointment_id"]) for r in cnb_rows if str(r.get("appointment_id") or "").isdigit()})
        if appt_ids:
            ph = ", ".join(["%s"] * len(appt_ids))
            with connection.cursor() as cursor:
                cursor.execute(
                    f"SELECT appointment_id, sub_procedure_suggestion_list FROM hms_doctor_consultation "
                    f"WHERE appointment_id IN ({ph}) ORDER BY ID ASC", appt_ids)
                for appt_id, raw in cursor.fetchall():
                    ids = _advised_procedure_ids(raw)
                    if ids:
                        advised_by_appt[int(appt_id)] = ids  # latest non-empty advice wins
        all_ids = sorted({i for ids in advised_by_appt.values() for i in ids})
        proc_names = {}
        if all_ids:
            ph = ", ".join(["%s"] * len(all_ids))
            with connection.cursor() as cursor:
                cursor.execute(f"SELECT ID, procedure_name FROM hms_procedures WHERE ID IN ({ph})", all_ids)
                proc_names = {int(i): (n or "").strip() for i, n in cursor.fetchall()}

        def advised_list(appt):
            try:
                ids = advised_by_appt.get(int(appt), [])
            except (TypeError, ValueError):
                ids = []
            return [proc_names[i] for i in ids if proc_names.get(i)]

        for row in cnb_rows:
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
                "treatment": ", ".join(advised_list(row.get("appointment_id"))),
                "treatments": advised_list(row.get("appointment_id")),
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
          {ds_j()}
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
          {ds_j()}
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
              {ds_p()}
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
              {ds_p()}
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
              {ds_p()}
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
            {ds_not('patient_id')}
            {journey_filter_sql}
        """
        with connection.cursor() as cursor:
            cursor.execute(triggers_count_query, journey_params)
            red_triggers = cursor.fetchone()[0]

        ensure_aging_followup_table()
        with connection.cursor() as cursor:
            _ds_lst = _ds_list_sql()
            _ds_clause = (
                "AND f.invoice NOT IN (SELECT p.receipt_number FROM hms_patient_procedure p "
                "WHERE p.receipt_number IS NOT NULL AND p.patient_id IN (" + _ds_lst + "))"
            ) if _ds_lst else ""
            cursor.execute(
                "SELECT COUNT(*) FROM reports_agingfollowup f WHERE f.status = %s " + _ds_clause,
                ['Cancellation Request'])
            approvals_pending = cursor.fetchone()[0]

        stage_query = f"""
            SELECT COALESCE(c.center_name, j.centre_booking) AS centre_booking,
                   j.withdrawl_status, j.stimulation_start_status, j.trigger_status, j.opu_status,
                   j.actual_opu_date, j.transfer_date
            FROM hms_patient_journey j
            LEFT JOIN hms_centers c ON j.centre_booking REGEXP '^[0-9]+$' AND c.center_number = CAST(j.centre_booking AS UNSIGNED)
            WHERE j.booking_date IS NOT NULL
              {ds_j()}
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


# ---- Direct Cancellation: approval by e-mail ------------------------------------------------------
# Request  -> mail to Administrator (cc Accountant) with Approve / Disapprove buttons; confirmation mail to the requester.
# Decision -> mail to the requester, cc Accountant.  The buttons open a small confirmation page served at
#             /api/cancel-decision (public, protected by a signed, expiring token).
from django.core import signing as _signing
from django.core.mail import EmailMultiAlternatives as _EmailMulti, get_connection as _get_conn
from django.utils.html import escape as _esc
from django.http import HttpResponse as _HttpResponse

CANCEL_SALT = 'india-ivf-cancel-decision'
CANCEL_LINK_MAX_AGE = 60 * 60 * 24 * 30      # links stay valid for 30 days


def _send_mail_raw(to, subject, text, html=None, cc=None):
    """Send one mail (optionally HTML + cc) through the saved SMTP settings. Never raises."""
    to = [e for e in dict.fromkeys(to or []) if e]
    cc = [e for e in dict.fromkeys(cc or []) if e and e not in to]
    if not to:
        return {'sent': 0, 'mode': _mail_mode(), 'note': 'No recipient email configured.'}
    try:
        cfg = _load_mail_config()
        if cfg['host']:
            conn = _smtp_connection(cfg)
            from_email = cfg['from_email'] or cfg['user']
        else:
            conn, from_email = None, dj_settings.DEFAULT_FROM_EMAIL
        msg = _EmailMulti(subject, text, from_email, to, cc=cc, connection=conn)
        if html:
            msg.attach_alternative(html, 'text/html')
        msg.send(fail_silently=False)
        return {'sent': len(to) + len(cc), 'mode': _mail_mode()}
    except Exception as e:
        return {'sent': 0, 'mode': _mail_mode(), 'error': str(e)}


def _cancel_info(invoice):
    ensure_aging_action_tables()
    with connection.cursor() as cursor:
        cursor.execute("""
            SELECT patient_id, patient_name, centre, pkg, outstanding, collected, reason, remarks, requested_by, requester_email
            FROM reports_cancelrequest WHERE invoice = %s
        """, [invoice])
        r = cursor.fetchone()
    if not r:
        return {'invoice': invoice, 'patient_id': '', 'name': '', 'centre': '', 'pkg': '', 'outstanding': 0.0, 'collected': 0.0,
                'reason': '', 'remarks': '', 'requested_by': '', 'requester_email': ''}
    return {'invoice': invoice, 'patient_id': r[0] or '', 'name': r[1] or '', 'centre': r[2] or '', 'pkg': r[3] or '',
            'outstanding': float(r[4] or 0), 'collected': float(r[5] or 0), 'reason': r[6] or '', 'remarks': r[7] or '',
            'requested_by': r[8] or '', 'requester_email': r[9] or ''}


def _cancel_rows(info):
    rows = [
        ('Patient', f"{info['name'] or '—'} ({info['patient_id'] or '—'})"),
        ('Invoice', info['invoice']),
        ('Centre', info['centre'] or '—'),
        ('Package', info['pkg'] or '—'),
        ('Amount collected', f"₹{info['collected']:,.0f}"),
        ('Outstanding', f"₹{info['outstanding']:,.0f}"),
        ('Cancellation reason', info['reason'] or '—'),
        ('Requested by', info['requested_by'] or '—'),
    ]
    if info['remarks']:
        rows.append(('Remarks', info['remarks']))
    return rows


def _rows_text(rows):
    return "\n".join(f"{k:<20}: {v}" for k, v in rows)


def _rows_html(rows):
    body = "".join(
        f"<tr><td style='padding:5px 14px 5px 0;color:#64748b;white-space:nowrap'>{_esc(k)}</td>"
        f"<td style='padding:5px 0;color:#0f172a;font-weight:600'>{_esc(v)}</td></tr>" for k, v in rows)
    return f"<table style='border-collapse:collapse;font-size:14px'>{body}</table>"


def _send_cancel_request_mails(request, data, ctx, invoice, patient_id, reason, remarks, requested_by):
    req_email = str(data.get('requester_email') or '').strip().lower()
    if '@' not in req_email:
        req_email = ''
    def _num(v):
        try:
            return float(v or 0)
        except (TypeError, ValueError):
            return 0.0
    with connection.cursor() as cursor:
        cursor.execute("""
            INSERT INTO reports_cancelrequest
                (invoice, patient_id, patient_name, centre, pkg, outstanding, collected, reason, remarks, requested_by, requester_email)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE patient_id = VALUES(patient_id), patient_name = VALUES(patient_name), centre = VALUES(centre),
                pkg = VALUES(pkg), outstanding = VALUES(outstanding), collected = VALUES(collected), reason = VALUES(reason),
                remarks = VALUES(remarks), requested_by = VALUES(requested_by), requester_email = VALUES(requester_email)
        """, [invoice, patient_id, str(ctx.get('name') or '')[:255], str(ctx.get('centre') or '')[:255], str(ctx.get('pkg') or '')[:255],
              _num(ctx.get('outstanding')), _num(ctx.get('collected')), reason, remarks[:500], requested_by[:150], req_email[:190]])
    info = _cancel_info(invoice)

    origin = str(data.get('origin') or '').strip().rstrip('/')
    if not origin.startswith(('http://', 'https://')):
        origin = request.build_absolute_uri('/').rstrip('/')
    token = _signing.dumps({'i': invoice}, salt=CANCEL_SALT)
    base = f"{origin}/api/cancel-decision?t={token}"
    approve_url, disapprove_url = base + "&a=approve", base + "&a=disapprove"

    lists = _get_notify_recipients()
    rows = _cancel_rows(info)
    subject = f"[India IVF HMS] Direct cancellation request — approval needed ({info['name'] or invoice})"
    text = (f"A direct cancellation request needs your approval.\n\n{_rows_text(rows)}\n\n"
            f"Approve    : {approve_url}\nDisapprove : {disapprove_url}\n\n— India IVF HMS")
    html = (
        "<div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px'>"
        "<h3 style='margin:0 0 6px;color:#9f1d52'>Direct cancellation request</h3>"
        "<p style='margin:0 0 12px;color:#475569'>This request needs your approval.</p>"
        f"{_rows_html(rows)}"
        "<p style='margin:18px 0 0'>"
        f"<a href='{_esc(approve_url)}' style='background:#15803d;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700;margin-right:10px;display:inline-block'>Approve</a>"
        f"<a href='{_esc(disapprove_url)}' style='background:#b91c1c;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700;display:inline-block'>Disapprove</a>"
        "</p><p style='color:#94a3b8;font-size:12px;margin-top:16px'>The buttons open a confirmation page — nothing changes until you confirm there.</p></div>")
    if lists['admin']:
        result = _send_mail_raw(lists['admin'], subject, text, html, cc=lists['accountant'])
    else:
        result = {'sent': 0, 'mode': _mail_mode(), 'note': 'No Admin email configured.'}

    if req_email:
        ack_text = (f"Your direct cancellation request has been sent to the Administrator for approval.\n\n{_rows_text(rows)}\n\n"
                    "You will get another mail when it is approved or disapproved.\n\n— India IVF HMS")
        ack_html = ("<div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px'>"
                    "<h3 style='margin:0 0 6px;color:#9f1d52'>Cancellation request submitted</h3>"
                    "<p style='color:#475569'>Your request has been sent to the Administrator for approval. "
                    "You will get another mail once it is approved or disapproved.</p>" + _rows_html(rows) + "</div>")
        ack = _send_mail_raw([req_email], f"[India IVF HMS] Cancellation request submitted ({info['name'] or invoice})", ack_text, ack_html)
        result['requesterMailed'] = bool(ack.get('sent'))
        if ack.get('error') and not result.get('error'):
            result['error'] = ack['error']
    else:
        result['requesterMailed'] = False
        result['requesterNote'] = 'Your login has no email, so no confirmation mail was sent to you.'
    return result


def _notify_cancel_decision(invoice, decision, refund, comment, decided_by, fallback=None):
    """Mail the requester (cc Accountant) about an approve / disapprove decision."""
    info = _cancel_info(invoice)
    if fallback:
        for k, v in fallback.items():
            if not info.get(k) and v:
                info[k] = v
    lists = _get_notify_recipients()
    accountants = lists['accountant']
    approved = decision == 'approved'
    word = 'APPROVED' if approved else 'DISAPPROVED'
    rows = _cancel_rows(info) + [('Decision', word), ('Decided by', decided_by or 'Administrator')]
    if approved and refund is not None:
        rows.append(('Refund amount', f"₹{refund:,.0f}"))
    if comment:
        rows.append(('Comment', comment))
    colour = '#15803d' if approved else '#b91c1c'
    subject = f"[India IVF HMS] Cancellation request {word} ({info['name'] or invoice})"
    text = f"Your direct cancellation request has been {word}.\n\n{_rows_text(rows)}\n\n— India IVF HMS"
    html = ("<div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px'>"
            f"<h3 style='margin:0 0 6px;color:{colour}'>Cancellation request {word}</h3>" + _rows_html(rows) + "</div>")
    to = [info['requester_email']] if info['requester_email'] else []
    if to:
        return _send_mail_raw(to, subject, text, html, cc=accountants)
    if accountants:
        return _send_mail_raw(accountants, subject, text, html)
    return {'sent': 0, 'mode': _mail_mode(), 'note': 'No requester / Accountant email available.'}


def _apply_cancel_decision(invoice, decision, refund, comment, decided_by):
    """Same outcome as the in-app 'Cancellation Review'. Returns (ok, message, new_state)."""
    ensure_aging_action_tables()
    with connection.cursor() as cursor:
        cursor.execute("SELECT state, patient_id, reason FROM reports_agingworkflow WHERE invoice = %s", [invoice])
        row = cursor.fetchone()
    if not row or row[0] != 'Cancellation Requested':
        return False, f"This request has already been decided ({row[0] if row else 'no pending request'}).", (row[0] if row else '')
    _state, patient_id, wf_reason = row
    if decision == 'approved':
        new_state = 'Cancellation Approved'
        log = f"Cancellation APPROVED — Refund ₹{refund:,.0f}"
        refund_val = refund
    else:
        new_state = 'Returned to Centre'
        log = "Cancellation NOT approved — returned to centre"
        refund_val = None
    if comment:
        log += f" — {comment}"
    with connection.cursor() as cursor:
        cursor.execute("""
            INSERT INTO reports_agingworkflow (invoice, patient_id, state, reason, refund_amount, comment)
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE state = VALUES(state), reason = VALUES(reason),
                                    refund_amount = VALUES(refund_amount), comment = VALUES(comment)
        """, [invoice, patient_id, new_state, wf_reason, refund_val, comment])
        cursor.execute("""
            INSERT INTO reports_agingaction (invoice, patient_id, action_type, to_value, log_line)
            VALUES (%s, %s, 'review_cancellation', '', %s)
        """, [invoice, patient_id, _fmt_log(decided_by, log)])
    _push_notif(invoice, patient_id, _fmt_log(decided_by, log))
    return True, new_state, new_state


_CANCEL_PAGE_CSS = (
    "body{font-family:Segoe UI,Arial,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#0f172a}"
    ".card{max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px}"
    "h2{margin:0 0 4px;color:#9f1d52}.sub{color:#64748b;font-size:13px;margin-bottom:16px}"
    "label{display:block;font-size:12px;font-weight:600;color:#475569;margin:10px 0 4px}"
    "input,textarea{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:8px;padding:9px 10px;font-size:14px}"
    "button{border:0;border-radius:8px;padding:10px 18px;font-weight:700;color:#fff;cursor:pointer;margin-top:12px;font-size:14px}"
    ".ok{background:#15803d}.no{background:#b91c1c}.box{border:1px solid #e2e8f0;border-radius:10px;padding:14px;margin-top:16px}"
    ".msg{padding:10px 12px;border-radius:8px;margin-bottom:12px;font-size:13px}.err{background:#fee2e2;color:#991b1b}.good{background:#dcfce7;color:#166534}"
)


def _cancel_page(inner, status=200):
    html = f"<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Cancellation request</title><style>{_CANCEL_PAGE_CSS}</style></head><body><div class='card'>{inner}</div></body></html>"
    return _HttpResponse(html, status=status, content_type='text/html; charset=utf-8')


@csrf_exempt
def cancel_decision(request):
    """Public (token-protected) confirmation page behind the Approve / Disapprove buttons in the mail."""
    token = request.POST.get('t') or request.GET.get('t') or ''
    try:
        payload = _signing.loads(token, salt=CANCEL_SALT, max_age=CANCEL_LINK_MAX_AGE)
        invoice = str(payload.get('i') or '')
    except _signing.BadSignature:
        return _cancel_page("<h2>Link not valid</h2><div class='sub'>This link is invalid or has expired. Please decide from the Aging page instead.</div>", 400)

    with connection.cursor() as cursor:
        ensure_aging_action_tables()
        cursor.execute("SELECT state FROM reports_agingworkflow WHERE invoice = %s", [invoice])
        r = cursor.fetchone()
    state = r[0] if r else ''
    info = _cancel_info(invoice)
    details = _rows_html(_cancel_rows(info))
    error = ''

    if request.method == 'POST':
        action = request.POST.get('action') or ''
        comment = (request.POST.get('comment') or '').strip()[:500]
        decided_by = 'Administrator (email link)'
        if action == 'approve':
            try:
                refund = float(request.POST.get('refund_amount') or 0)
                if refund < 0:
                    raise ValueError
            except ValueError:
                refund = None
                error = 'Enter a valid refund amount.'
            if refund is not None:
                ok, msg, new_state = _apply_cancel_decision(invoice, 'approved', refund, comment, decided_by)
                if not ok:
                    return _cancel_page(f"<h2>Already decided</h2><div class='sub'>{_esc(msg)}</div>{details}")
                _notify_cancel_decision(invoice, 'approved', refund, comment, decided_by)
                return _cancel_page(f"<h2>Cancellation approved</h2><div class='msg good'>Refund ₹{refund:,.0f}. The requester has been informed (Accountant in cc).</div>{details}")
        elif action == 'disapprove':
            ok, msg, new_state = _apply_cancel_decision(invoice, 'disapproved', None, comment, decided_by)
            if not ok:
                return _cancel_page(f"<h2>Already decided</h2><div class='sub'>{_esc(msg)}</div>{details}")
            _notify_cancel_decision(invoice, 'disapproved', None, comment, decided_by)
            return _cancel_page(f"<h2>Cancellation disapproved</h2><div class='msg good'>The requester has been informed (Accountant in cc).</div>{details}")
        else:
            error = 'Choose Approve or Disapprove.'

    if state != 'Cancellation Requested':
        return _cancel_page(f"<h2>No pending request</h2><div class='sub'>This request is not waiting for approval (current state: {_esc(state or 'none')}).</div>{details}")

    pre = request.GET.get('a') or ''
    err_html = f"<div class='msg err'>{_esc(error)}</div>" if error else ''
    default_refund = f"{info['collected']:.0f}"
    ok_style = " style='border-color:#15803d'" if pre == 'approve' else ''
    no_style = " style='border-color:#b91c1c'" if pre == 'disapprove' else ''
    tok = _esc(token)
    inner = (
        "<h2>Direct cancellation request</h2><div class='sub'>Please confirm your decision.</div>" + err_html + details +
        f"<div class='box'{ok_style}><b>Approve</b>"
        f"<form method='post'><input type='hidden' name='t' value='{tok}'><input type='hidden' name='action' value='approve'>"
        f"<label>Refund amount (₹)</label><input name='refund_amount' inputmode='decimal' value='{_esc(default_refund)}'>"
        "<label>Comment (optional)</label><textarea name='comment' rows='2'></textarea>"
        "<button class='ok' type='submit'>Confirm approval</button></form></div>"
        f"<div class='box'{no_style}><b>Disapprove</b>"
        f"<form method='post'><input type='hidden' name='t' value='{tok}'><input type='hidden' name='action' value='disapprove'>"
        "<label>Comment (optional)</label><textarea name='comment' rows='2'></textarea>"
        "<button class='no' type='submit'>Confirm disapproval</button></form></div>"
    )
    return _cancel_page(inner)


# ---- Stage-delay reasons: mail to the Administrator + reply back --------------------------------------
# A delay reason saved on the Journey page mails the Administrator (Reply button inside the mail). The
# Administrator replies from that mail page or from the Journey popup; the reply is mailed to whoever
# recorded the reason and shown in the popup.
DELAY_SALT = 'india-ivf-stage-delay-reply'
DELAY_LINK_MAX_AGE = 60 * 60 * 24 * 60


def _delay_row(patient_id, cycle_key, stage_key):
    ensure_stage_delay_table()
    with connection.cursor() as cursor:
        cursor.execute("""
            SELECT reason, remarks, ideal_date, actual_date, admin_reply, replied_by, replied_at,
                   requested_by, requester_email, patient_name
            FROM reports_stagedelay WHERE patient_id = %s AND cycle_key = %s AND stage_key = %s
        """, [patient_id, cycle_key, stage_key])
        r = cursor.fetchone()
    if not r:
        return None
    return {
        'patient_id': patient_id, 'cycle_key': cycle_key, 'stage_key': stage_key,
        'reason': r[0] or '', 'remarks': r[1] or '',
        'ideal': r[2].isoformat() if r[2] else '', 'actual': r[3].isoformat() if r[3] else '',
        'reply': r[4] or '', 'replied_by': r[5] or '', 'replied_at': r[6].strftime('%d-%b-%y %I:%M %p') if r[6] else '',
        'requested_by': r[7] or '', 'requester_email': r[8] or '', 'name': r[9] or '',
    }


def _delay_rows(info):
    rows = [
        ('Patient', f"{info['name'] or '—'} ({info['patient_id']})"),
        ('Cycle', info['cycle_key'] or '—'),
        ('Stage', info['stage_key']),
        ('Ideal date', info['ideal'] or '—'),
        ('Actual date', info['actual'] or 'Not done yet'),
        ('Delay reason', info['reason']),
    ]
    if info['remarks']:
        rows.append(('Remarks', info['remarks']))
    rows.append(('Reported by', info['requested_by'] or '—'))
    return rows


def _send_delay_mail(request, data, info):
    origin = str(data.get('origin') or '').strip().rstrip('/')
    if not origin.startswith(('http://', 'https://')):
        origin = request.build_absolute_uri('/').rstrip('/')
    token = _signing.dumps({'p': info['patient_id'], 'c': info['cycle_key'], 's': info['stage_key']}, salt=DELAY_SALT)
    reply_url = f"{origin}/api/stage-delay-reply?t={token}"
    rows = _delay_rows(info)
    subject = f"[India IVF HMS] Stage delayed — {info['stage_key']} ({info['name'] or info['patient_id']})"
    text = f"A stage has been marked delayed.\n\n{_rows_text(rows)}\n\nReply: {reply_url}\n\n— India IVF HMS"
    html = ("<div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px'>"
            "<h3 style='margin:0 0 6px;color:#9f1d52'>Stage delayed</h3>"
            "<p style='margin:0 0 12px;color:#475569'>The reason below was recorded for a delayed stage.</p>"
            f"{_rows_html(rows)}"
            f"<p style='margin:18px 0 0'><a href='{_esc(reply_url)}' style='background:#9f1d52;color:#fff;text-decoration:none;"
            "padding:10px 18px;border-radius:8px;font-weight:700;display:inline-block'>Reply</a></p></div>")
    lists = _get_notify_recipients()
    if not lists['admin']:
        return {'sent': 0, 'mode': _mail_mode(), 'note': 'No Admin email configured.'}
    return _send_mail_raw(lists['admin'], subject, text, html)


def _save_delay_reply(patient_id, cycle_key, stage_key, reply, by):
    info = _delay_row(patient_id, cycle_key, stage_key)
    if not info:
        return False, 'No delay reason has been recorded for this stage yet.', None
    with connection.cursor() as cursor:
        cursor.execute("""
            UPDATE reports_stagedelay SET admin_reply = %s, replied_by = %s, replied_at = %s
            WHERE patient_id = %s AND cycle_key = %s AND stage_key = %s
        """, [reply[:2000], by[:150], _log_now().replace(tzinfo=None), patient_id, cycle_key, stage_key])
    info = _delay_row(patient_id, cycle_key, stage_key)
    rows = _delay_rows(info) + [('Administrator reply', reply), ('Replied by', by or 'Administrator')]
    subject = f"[India IVF HMS] Administrator replied — {info['stage_key']} ({info['name'] or info['patient_id']})"
    text = f"The Administrator replied about a delayed stage.\n\n{_rows_text(rows)}\n\n— India IVF HMS"
    html = ("<div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px'>"
            "<h3 style='margin:0 0 6px;color:#9f1d52'>Administrator replied</h3>" + _rows_html(rows) + "</div>")
    if info['requester_email']:
        mail = _send_mail_raw([info['requester_email']], subject, text, html)
    else:
        mail = {'sent': 0, 'mode': _mail_mode(), 'note': 'The person who recorded this reason has no email saved, so no mail was sent.'}
    return True, 'Reply saved.', mail


@csrf_exempt
def stage_delay_reply(request):
    """Public (token-protected) page behind the Reply button in the delay mail."""
    token = request.POST.get('t') or request.GET.get('t') or ''
    try:
        p = _signing.loads(token, salt=DELAY_SALT, max_age=DELAY_LINK_MAX_AGE)
        patient_id, cycle_key, stage_key = str(p.get('p') or ''), str(p.get('c') or ''), str(p.get('s') or '')
    except _signing.BadSignature:
        return _cancel_page("<h2>Link not valid</h2><div class='sub'>This link is invalid or has expired.</div>", 400)
    info = _delay_row(patient_id, cycle_key, stage_key)
    if not info:
        return _cancel_page("<h2>Not found</h2><div class='sub'>This delay record no longer exists.</div>", 404)
    error = ''
    if request.method == 'POST':
        reply = (request.POST.get('reply') or '').strip()
        if not reply:
            error = 'Please write your reply.'
        else:
            ok, msg, _mail = _save_delay_reply(patient_id, cycle_key, stage_key, reply, 'Administrator (email link)')
            info = _delay_row(patient_id, cycle_key, stage_key)
            return _cancel_page(f"<h2>Reply sent</h2><div class='msg good'>Your reply was saved and mailed to {_esc(info['requested_by'] or 'the person who reported it')}.</div>{_rows_html(_delay_rows(info))}")
    prev = f"<div class='box'><b>Previous reply</b><div>{_esc(info['reply'])}</div></div>" if info['reply'] else ''
    err_html = f"<div class='msg err'>{_esc(error)}</div>" if error else ''
    inner = (
        "<h2>Stage delayed</h2><div class='sub'>Write your reply — it is mailed to the person who recorded the reason.</div>"
        + err_html + _rows_html(_delay_rows(info)) + prev +
        f"<form method='post'><input type='hidden' name='t' value='{_esc(token)}'>"
        "<label>Your reply</label><textarea name='reply' rows='4'></textarea>"
        "<button class='ok' type='submit'>Send reply</button></form>")
    return _cancel_page(inner)



# ---- Revert to Telecalling mail: two buttons (Return to branch / Cancellation request) --------------
# The Telecalling Team gets a mail with two buttons. Each opens a small public form (signed, expiring link):
#   Return to branch      -> date + comment; saved to the DB + history log, mailed back to whoever reverted it
#   Cancellation request  -> reason + remark; goes to the Administrator for approve / disapprove (existing flow)
TELE_SALT = 'india-ivf-tele-action'


def _send_revert_mail(request, data, ctx, invoice, patient_id, remarks, log_user, log_line):
    def _num(x):
        try:
            return float(x or 0)
        except (TypeError, ValueError):
            return 0.0
    req_email = str(data.get('requester_email') or '').strip().lower()
    if '@' not in req_email:
        req_email = ''
    origin = str(data.get('origin') or '').strip().rstrip('/')
    if not origin.startswith(('http://', 'https://')):
        origin = request.build_absolute_uri('/').rstrip('/')
    outstanding, collected = _num(ctx.get('outstanding')), _num(ctx.get('collected'))
    token = _signing.dumps({
        'i': invoice, 'p': patient_id, 'n': str(ctx.get('name') or '')[:120], 'c': str(ctx.get('centre') or '')[:120],
        'k': str(ctx.get('pkg') or '')[:120], 'o': outstanding, 'l': collected, 'e': req_email, 'u': log_user, 'g': origin,
    }, salt=TELE_SALT)
    base = f"{origin}/api/tele-action?t={token}"
    ret_url, can_url = base + "&a=return", base + "&a=cancel"
    rows = [
        ('Patient', f"{ctx.get('name') or '—'} ({patient_id or '—'})"),
        ('Invoice', invoice),
        ('Centre', ctx.get('centre') or '—'),
        ('Package', ctx.get('pkg') or '—'),
        ('Amount collected', f"₹{collected:,.0f}"),
        ('Outstanding', f"₹{outstanding:,.0f}"),
        ('Reverted by', log_user or '—'),
    ]
    if remarks:
        rows.append(('Remarks', remarks))
    subject = "[India IVF HMS] Patient reverted to Telecalling Team"
    text = (f"A patient has been reverted to the Telecalling Team.\n\n{_rows_text(rows)}\n\n"
            f"Return to branch     : {ret_url}\nCancellation request : {can_url}\n\n{log_line}\n\n— India IVF HMS")
    html = (
        "<div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px'>"
        "<h3 style='margin:0 0 6px;color:#9f1d52'>Patient reverted to Telecalling Team</h3>"
        "<p style='margin:0 0 12px;color:#475569'>Please choose what happens next.</p>"
        f"{_rows_html(rows)}"
        "<p style='margin:18px 0 0'>"
        f"<a href='{_esc(ret_url)}' style='background:#15803d;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700;margin-right:10px;display:inline-block'>Return to branch</a>"
        f"<a href='{_esc(can_url)}' style='background:#b91c1c;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700;display:inline-block'>Cancellation request</a>"
        "</p><p style='color:#94a3b8;font-size:12px;margin-top:16px'>The buttons open a form — nothing changes until you submit it.</p></div>")
    lists = _get_notify_recipients()
    if not lists['telecalling']:
        return {'sent': 0, 'mode': _mail_mode(), 'note': 'No Telecalling Team email configured.'}
    return _send_mail_raw(lists['telecalling'], subject, text, html)


def _tele_return_branch(p, ret_date, comment, who, email):
    ensure_aging_action_tables()
    invoice, patient_id = p['i'], p.get('p') or ''
    with connection.cursor() as cursor:
        cursor.execute("""
            INSERT INTO reports_agingworkflow (invoice, patient_id, state, reason, refund_amount, comment)
            VALUES (%s, %s, 'Returned to Branch', '', NULL, %s)
            ON DUPLICATE KEY UPDATE state = VALUES(state), reason = VALUES(reason),
                                    refund_amount = VALUES(refund_amount), comment = VALUES(comment)
        """, [invoice, patient_id, comment[:500]])
        cursor.execute("SELECT id FROM reports_agingfollowup WHERE invoice = %s", [invoice])
        if cursor.fetchone():
            cursor.execute("UPDATE reports_agingfollowup SET status = 'Active' WHERE invoice = %s", [invoice])
        else:
            cursor.execute("""
                INSERT INTO reports_agingfollowup (invoice, patient_id, status, remarks, referred_date, history_log)
                VALUES (%s, %s, 'Active', '', NULL, '')
            """, [invoice, patient_id])
        text = f"Return to branch — Date: {ret_date.strftime('%d-%b-%y')}"
        if comment:
            text += f" — {comment}"
        line = _fmt_log(who, text)
        cursor.execute("""
            INSERT INTO reports_agingaction (invoice, patient_id, action_type, to_value, log_line)
            VALUES (%s, %s, 'tele_return_branch', '', %s)
        """, [invoice, patient_id, line])
    _push_notif(invoice, patient_id, line)
    # mail back to whoever reverted the patient (Telecalling Team in cc)
    lists = _get_notify_recipients()
    to = [e for e in [p.get('e'), email] if e]
    rows = [
        ('Patient', f"{p.get('n') or '—'} ({patient_id or '—'})"),
        ('Invoice', invoice),
        ('Centre', p.get('c') or '—'),
        ('Package', p.get('k') or '—'),
        ('Returned on', ret_date.strftime('%d-%b-%Y')),
        ('Done by', who),
    ]
    if comment:
        rows.append(('Comment', comment))
    subject = "Re: [India IVF HMS] Patient reverted to Telecalling Team — returned to branch"
    mtext = f"The patient has been returned to the branch.\n\n{_rows_text(rows)}\n\n{line}\n\n— India IVF HMS"
    mhtml = ("<div style='font-family:Segoe UI,Arial,sans-serif;max-width:560px'>"
             "<h3 style='margin:0 0 6px;color:#15803d'>Patient returned to branch</h3>" + _rows_html(rows) + "</div>")
    if to:
        return _send_mail_raw(to, subject, mtext, mhtml, cc=lists['telecalling'])
    return _send_mail_raw(lists['telecalling'], subject, mtext, mhtml)


@csrf_exempt
def tele_action(request):
    """Public (token-protected) form behind the two buttons in the 'Revert to Telecalling' mail."""
    token = request.POST.get('t') or request.GET.get('t') or ''
    try:
        p = _signing.loads(token, salt=TELE_SALT, max_age=CANCEL_LINK_MAX_AGE)
        invoice = str(p.get('i') or '')
    except _signing.BadSignature:
        return _cancel_page("<h2>Link not valid</h2><div class='sub'>This link is invalid or has expired. Please use the Aging page instead.</div>", 400)
    ensure_aging_action_tables()
    with connection.cursor() as cursor:
        cursor.execute("SELECT state FROM reports_agingworkflow WHERE invoice = %s", [invoice])
        r = cursor.fetchone()
    state = r[0] if r else ''
    rows = [
        ('Patient', f"{p.get('n') or '—'} ({p.get('p') or '—'})"),
        ('Invoice', invoice),
        ('Centre', p.get('c') or '—'),
        ('Package', p.get('k') or '—'),
        ('Amount collected', f"₹{float(p.get('l') or 0):,.0f}"),
        ('Outstanding', f"₹{float(p.get('o') or 0):,.0f}"),
    ]
    details = _rows_html(rows)
    if state != 'With Telecalling':
        return _cancel_page(f"<h2>Already handled</h2><div class='sub'>This patient is no longer waiting with the Telecalling Team (current state: {_esc(state or 'none')}).</div>{details}")

    a = request.POST.get('a') or request.GET.get('a') or 'return'
    if a not in ('return', 'cancel'):
        a = 'return'
    error = ''
    name = (request.POST.get('name') or '').strip()[:80]
    email = (request.POST.get('email') or '').strip().lower()[:190]
    if email and not _re_log.match(r'^[^@\s]+@[^@\s]+\.[^@\s]+$', email):
        email = ''
    comment = (request.POST.get('comment') or '').strip()[:500]
    who = f"{name or 'Telecalling Team'} (email link)"

    if request.method == 'POST':
        if a == 'return':
            try:
                ret_date = _dt_log.strptime((request.POST.get('date') or '').strip(), '%Y-%m-%d').date()
            except ValueError:
                ret_date = None
                error = 'Enter a valid date.'
            if ret_date:
                res = _tele_return_branch(p, ret_date, comment, who, email)
                note = 'The branch has been informed by mail.' if res.get('sent') else 'Saved, but the mail could not be sent' + (f" ({_esc(res.get('error') or res.get('note') or '')})" if (res.get('error') or res.get('note')) else '.')
                return _cancel_page(f"<h2>Returned to branch</h2><div class='msg good'>Saved in the history log. {note}</div>{details}")
        else:
            reason = (request.POST.get('reason') or '').strip()
            if reason and reason not in AGING_CANCEL_REASONS:
                reason = ''
            if not comment:
                error = 'Please add a remark for the Administrator.'
            else:
                ctx = {'name': p.get('n'), 'centre': p.get('c'), 'pkg': p.get('k'), 'outstanding': p.get('o'), 'collected': p.get('l')}
                text = "Cancellation request (via Telecalling mail)"
                if reason:
                    text += f" — Reason: {reason}"
                text += f" — {comment}"
                line = _fmt_log(who, text)
                with connection.cursor() as cursor:
                    cursor.execute("""
                        INSERT INTO reports_agingworkflow (invoice, patient_id, state, reason, refund_amount, comment)
                        VALUES (%s, %s, 'Cancellation Requested', %s, NULL, %s)
                        ON DUPLICATE KEY UPDATE state = VALUES(state), reason = VALUES(reason),
                                                refund_amount = VALUES(refund_amount), comment = VALUES(comment)
                    """, [invoice, p.get('p') or '', reason, comment])
                    cursor.execute("""
                        INSERT INTO reports_agingaction (invoice, patient_id, action_type, to_value, log_line)
                        VALUES (%s, %s, 'tele_cancel_request', '', %s)
                    """, [invoice, p.get('p') or '', line])
                _push_notif(invoice, p.get('p') or '', line)
                res = _send_cancel_request_mails(request, {'origin': p.get('g') or '', 'requester_email': email or p.get('e') or ''},
                                                 ctx, invoice, p.get('p') or '', reason, comment, who)
                note = 'The Administrator has been mailed for approval.' if res.get('sent') else 'Saved, but the mail could not be sent' + (f" ({_esc(res.get('error') or res.get('note') or '')})" if (res.get('error') or res.get('note')) else '.')
                return _cancel_page(f"<h2>Cancellation request sent</h2><div class='msg good'>{note} You will be informed once it is approved or disapproved.</div>{details}")

    err_html = f"<div class='msg err'>{_esc(error)}</div>" if error else ''
    tok = _esc(token)
    today = timezone.localdate().strftime('%Y-%m-%d')
    switch = (f"<a href='?t={tok}&a=cancel' style='font-size:13px'>Cancellation request instead →</a>" if a == 'return'
              else f"<a href='?t={tok}&a=return' style='font-size:13px'>Return to branch instead →</a>")
    name_field = f"<label>Your name (optional)</label><input name='name' value='{_esc(name)}'>"
    if a == 'return':
        form = (
            f"<div class='box'><b>Return to branch</b><form method='post'><input type='hidden' name='t' value='{tok}'><input type='hidden' name='a' value='return'>"
            f"<label>Date</label><input type='date' name='date' value='{_esc(request.POST.get('date') or today)}' required>"
            f"<label>Comment</label><textarea name='comment' rows='3'>{_esc(comment)}</textarea>{name_field}"
            "<label>Your email (optional — to also get the confirmation)</label>"
            f"<input name='email' value='{_esc(email)}'>"
            "<button class='ok' type='submit'>Submit</button></form></div>")
    else:
        opts = "<option value=''>— Select —</option>" + "".join(
            f"<option value='{_esc(x)}'{' selected' if x == request.POST.get('reason') else ''}>{_esc(x)}</option>" for x in AGING_CANCEL_REASONS)
        form = (
            f"<div class='box'><b>Cancellation request</b><form method='post'><input type='hidden' name='t' value='{tok}'><input type='hidden' name='a' value='cancel'>"
            f"<label>Reason</label><select name='reason' style='width:100%;padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px'>{opts}</select>"
            f"<label>Remark (goes to the Administrator)</label><textarea name='comment' rows='3' required>{_esc(comment)}</textarea>{name_field}"
            "<label>Your email (optional — to get the decision mail)</label>"
            f"<input name='email' value='{_esc(email)}'>"
            "<button class='no' type='submit'>Send for approval</button></form></div>")
    return _cancel_page(f"<h2>Reverted to Telecalling Team</h2><div class='sub'>Choose what happens with this patient.</div>{err_html}{details}{form}<p>{switch}</p>")


# ---- In-app notifications for every new comment / history entry on the Aging page ---------------------
# Each new history line (action, follow-up, email-link decision) is also written to reports_agingnotif.
# The Aging page polls /api/aging_notifications/ and shows a bell + toast; clicking jumps to the patient's comment.
def _ensure_notif_table():
    with connection.cursor() as cursor:
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS reports_agingnotif (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice VARCHAR(100) NOT NULL,
                patient_id VARCHAR(50) NOT NULL DEFAULT '',
                text TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        """)


def _push_notif(invoice, patient_id, text):
    """Record a notification. Never raises — a failed notification must not break saving the comment."""
    try:
        _ensure_notif_table()
        with connection.cursor() as cursor:
            cursor.execute("INSERT INTO reports_agingnotif (invoice, patient_id, text) VALUES (%s, %s, %s)",
                           [invoice, patient_id or '', text or ''])
    except Exception:
        pass


@csrf_exempt
def aging_notifications(request):
    """GET -> {maxId, items: [{id, invoice, patientId, text, at}]} (newest first, last 25)."""
    try:
        _ensure_notif_table()
        with connection.cursor() as cursor:
            cursor.execute(
                "SELECT n.id, n.invoice, n.patient_id, n.text, n.created_at FROM reports_agingnotif n WHERE 1=1 "
                + ds_not("n.patient_id") + " ORDER BY n.id DESC LIMIT 25")
            rows = cursor.fetchall()
        items = [{'id': r[0], 'invoice': r[1], 'patientId': r[2], 'text': r[3] or '',
                  'at': r[4].strftime('%Y-%m-%dT%H:%M:%S') if r[4] else ''} for r in rows]
        return JsonResponse({'status': 'success', 'maxId': items[0]['id'] if items else 0, 'items': items})
    except Exception as e:
        return JsonResponse({'status': 'error', 'message': str(e)}, status=500)
