'use client';

import React, { useState, useEffect } from 'react';

export default function HmsReportDashboard() {
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  
  // Selected Patient Profile State
  const [selectedPatient, setSelectedPatient] = useState(null);
  const [profileLoading, setProfileLoading] = useState(false);

  // 1. Fetch Master Booked Patients List
  useEffect(() => {
    fetchBookedPatients();
  }, []);

  const fetchBookedPatients = async () => {
    try {
      setLoading(true);
      const res = await fetch('http://127.0.0.1:8000/api/get_dynamic_booked_patients/', {
        cache: 'no-store'
      });
      const data = await res.json();
      setPatients(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error("Failed to fetch patients:", err);
    } finally {
      setLoading(false);
    }
  };

  // 2. Fetch Detailed Profile & Procedures for Selected Patient
  const openPatientProfile = async (receiptNumber) => {
    try {
      setProfileLoading(true);
      const res = await fetch(`http://127.0.0.1:8000/api/get_patient_profile_detail/?receipt_number=${receiptNumber}`);
      const result = await res.json();
      if (result.status === 'success') {
        setSelectedPatient(result);
      } else {
        alert(result.message || "Failed to load profile");
      }
    } catch (err) {
      console.error("Failed to load profile details:", err);
    } finally {
      setProfileLoading(false);
    }
  };

  // Calculated Metrics
  const totalFees = patients.reduce((acc, curr) => acc + (curr.fees || 0), 0);
  const totalPaid = patients.reduce((acc, curr) => acc + (curr.total_payment_done || 0), 0);
  const totalPending = patients.reduce((acc, curr) => acc + (curr.pending_amount || 0), 0);

  // Filtered Rows
  const filteredPatients = patients.filter(p => 
    p.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    p.patient_id?.toString().includes(searchTerm) ||
    p.doctor_name?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div style={styles.container}>
      {/* Header Title */}
      <div style={styles.header}>
        <div>
          <h1 style={styles.title}>🏥 India IVF HMS - Clinical & Billing Reports</h1>
          <p style={styles.subtitle}>Real-time synchronization with Django Backend & MySQL Registry</p>
        </div>
        <button onClick={() => window.print()} style={styles.printBtn}>🖨️ Print Report</button>
      </div>

      {/* Top Summary Cards */}
      <div style={styles.metricsGrid}>
        <div style={{ ...styles.card, borderLeft: '5px solid #0070f3' }}>
          <span>Total Patients</span>
          <h2>{patients.length}</h2>
        </div>
        <div style={{ ...styles.card, borderLeft: '5px solid #10B981' }}>
          <span>Total Fees Billed</span>
          <h2>₹ {totalFees.toLocaleString('en-IN')}</h2>
        </div>
        <div style={{ ...styles.card, borderLeft: '5px solid #3B82F6' }}>
          <span>Total Received</span>
          <h2>₹ {totalPaid.toLocaleString('en-IN')}</h2>
        </div>
        <div style={{ ...styles.card, borderLeft: '5px solid #EF4444' }}>
          <span>Total Pending Balance</span>
          <h2>₹ {totalPending.toLocaleString('en-IN')}</h2>
        </div>
      </div>

      {/* Controls: Search Bar */}
      <div style={styles.controls}>
        <input 
          type="text" 
          placeholder="🔍 Search by Patient Name, Patient ID, or Doctor..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          style={styles.searchInput}
        />
      </div>

      {/* Master Patients Data Table */}
      {loading ? (
        <div style={styles.loader}>Loading HMS Patient Reports...</div>
      ) : (
        <div style={styles.tableWrapper}>
          <table style={styles.table}>
            <thead>
              <tr>
                <th>Patient ID a</th>
                <th>Wife Name</th>
                <th>Husband Name</th>
                <th>Receipt #</th>
                <th>Center</th>
                <th>Doctor</th>
                <th>Procedures Code</th>
                <th>Fees (₹)</th>
                <th>Paid (₹)</th>
                <th>Pending (₹)</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {filteredPatients.length > 0 ? filteredPatients.map((patient, idx) => (
                <tr key={idx}>
                  <td><strong>{patient.patient_id}</strong></td>
                  <td>{patient.name}</td>
                  <td>{patient.husband_name || '—'}</td>
                  <td><code>{patient.receipt_number}</code></td>
                  <td>{patient.center_name || '—'}</td>
                  <td>{patient.doctor_name || '—'}</td>
                  <td>{patient.code || '—'}</td>
                  <td>₹ {patient.fees}</td>
                  <td style={{ color: 'green', fontWeight: 'bold' }}>₹ {patient.total_payment_done}</td>
                  <td style={{ color: patient.pending_amount > 0 ? 'red' : 'inherit' }}>
                    ₹ {patient.pending_amount}
                  </td>
                  <td>
                    <button 
                      onClick={() => openPatientProfile(patient.receipt_number)}
                      style={styles.viewBtn}
                    >
                      View Profile
                    </button>
                  </td>
                </tr>
              )) : (
                <tr>
                  <td colSpan="11" style={{ textAlign: 'center', padding: '20px' }}>No records found.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* Patient Profile Detailed Modal */}
      {selectedPatient && (
        <div style={styles.modalOverlay}>
          <div style={styles.modalContent}>
            <div style={styles.modalHeader}>
              <h2>📋 Patient Profile & Procedure Breakdown</h2>
              <button onClick={() => setSelectedPatient(null)} style={styles.closeBtn}>✕</button>
            </div>

            {profileLoading ? (
              <p>Loading breakdown procedures...</p>
            ) : (
              <div>
                {/* Demographics Box */}
                <div style={styles.demoBox}>
                  <p><strong>Patient ID:</strong> {selectedPatient.data?.patient_id}</p>
                  <p><strong>Wife:</strong> {selectedPatient.data?.wife_name} ({selectedPatient.data?.wife_age || 'N/A'} yrs)</p>
                  <p><strong>Husband:</strong> {selectedPatient.data?.husband_name} ({selectedPatient.data?.husband_age || 'N/A'} yrs)</p>
                  <p><strong>Phone:</strong> {selectedPatient.data?.patient_phone || selectedPatient.data?.wife_phone || '—'}</p>
                </div>

                <h3>Procedures Executed ({selectedPatient.procedures?.length || 0})</h3>
                <table style={styles.subTable}>
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Category</th>
                      <th>Procedure Code</th>
                      <th>Procedure Name</th>
                      <th>Fees</th>
                      <th>Paid</th>
                      <th>Pending</th>
                    </tr>
                  </thead>
                  <tbody>
                    {selectedPatient.procedures?.map((proc, i) => (
                      <tr key={i}>
                        <td>{proc.on_date}</td>
                        <td>{proc.category}</td>
                        <td><code>{proc.code}</code></td>
                        <td>{proc.procedure_name}</td>
                        <td>₹ {proc.fees}</td>
                        <td>₹ {proc.payment_done}</td>
                        <td style={{ color: proc.pending > 0 ? 'red' : 'green' }}>₹ {proc.pending}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

// Inline Styling Object
const styles = {
  container: { padding: '2rem', fontFamily: 'system-ui, sans-serif', backgroundColor: '#f9fafb', minHeight: '100vh' },
  header: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem' },
  title: { margin: 0, fontSize: '1.5rem', color: '#111827' },
  subtitle: { margin: '4px 0 0 0', color: '#6b7280', fontSize: '0.875rem' },
  printBtn: { backgroundColor: '#111827', color: '#fff', border: 'none', padding: '10px 16px', borderRadius: '6px', cursor: 'pointer' },
  metricsGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1rem', marginBottom: '1.5rem' },
  card: { backgroundColor: '#fff', padding: '1.2rem', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' },
  controls: { marginBottom: '1rem' },
  searchInput: { width: '100%', padding: '12px 16px', borderRadius: '6px', border: '1px solid #d1d5db', fontSize: '1rem' },
  tableWrapper: { overflowX: 'auto', backgroundColor: '#fff', borderRadius: '8px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' },
  table: { width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '0.9rem' },
  viewBtn: { backgroundColor: '#0070f3', color: '#fff', border: 'none', padding: '6px 12px', borderRadius: '4px', cursor: 'pointer' },
  loader: { padding: '3rem', textAlign: 'center', fontSize: '1.2rem', color: '#6b7280' },
  modalOverlay: { position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', justifyContent: 'center', alignItems: 'center', zIndex: 1000 },
  modalContent: { backgroundColor: '#fff', padding: '2rem', borderRadius: '8px', width: '80%', maxWidth: '900px', maxHeight: '90vh', overflowY: 'auto' },
  modalHeader: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' },
  closeBtn: { border: 'none', background: 'none', fontSize: '1.5rem', cursor: 'pointer' },
  demoBox: { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '10px', backgroundColor: '#f3f4f6', padding: '1rem', borderRadius: '6px', marginBottom: '1rem' },
  subTable: { width: '100%', borderCollapse: 'collapse', marginTop: '10px' }
};