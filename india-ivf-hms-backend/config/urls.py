"""
URL configuration for config project.

The `urlpatterns` list routes URLs to views. For more information please see:
    https://docs.djangoproject.com/en/6.0/topics/http/urls/
Examples:
Function views
    1. Add an import:  from my_app import views
    2. Add a URL to urlpatterns:  path('', views.home, name='home')
Class-based views
    1. Add an import:  from other_app.views import Home
    2. Add a URL to urlpatterns:  path('', Home.as_view(), name='home')
Including another URLconf
    1. Import the include() function: from django.urls import include, path
    2. Add a URL to urlpatterns:  path('blog/', include('blog.urls'))
"""
# config/urls.py
from django.contrib import admin
from django.urls import path
from reports import views

urlpatterns = [
    # Admin Interface
    path('admin/', admin.site.urls),

    # HTML Template Views
    path('', views.index, name='index'),
    path('login/', views.login_view, name='login'),
    path('logout/', views.logout_view, name='logout'),
    path('doctor/', views.doctor_view, name='doctor_view'),
    path('doctor-workspace/', views.doctor_workspace_view, name='doctor_workspace_view'),
    path('centre-head/', views.centre_head_view, name='centre_head_view'),
    path('financial-counsellor/', views.fc_view, name='fc_view'),
    path('accounts/', views.accounts_view, name='accounts_view'),
    path('management/', views.management_view, name='management_view'),
    path('procedure-billing/', views.procedure_billing_view, name='procedure_billing_view'),

    # API Endpoints (Next.js aur Frontend ke liye)
    path('api/login/', views.login_view, name='api_login'),
    path('api/logout/', views.logout_view, name='api_logout'),
    path('api/get_cnb_data/', views.get_cnb_data, name='get_cnb_data'),
    path('api/save_cnb_edits/', views.save_cnb_edits, name='save_cnb_edits'),
    path('api/get_procedure_billing_data/', views.get_procedure_billing_data, name='get_procedure_billing_data'),
    path('api/get_dynamic_booked_patients/', views.get_dynamic_booked_patients, name='get_dynamic_booked_patients'),
    path('api/get_patient_profile_detail/', views.get_patient_profile_detail, name='get_patient_profile_detail'),
    path('api/get_centre_comparison/', views.get_centre_comparison, name='get_centre_comparison'),
    path('api/get_aging_snapshot/', views.get_aging_snapshot, name='get_aging_snapshot'),
    path('api/save_aging_followup/', views.save_aging_followup, name='save_aging_followup'),
    path('api/get_prebook_data/', views.get_prebook_data, name='get_prebook_data'),
    path('api/get_red_triggers/', views.get_red_triggers, name='get_red_triggers'),
    path('api/get_dashboard_summary/', views.get_dashboard_summary, name='get_dashboard_summary'),
]
