import React, { useState, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, RefreshControl, ActivityIndicator } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import axios from 'axios';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';

import { API_URL } from '../config';

const parseDateSafe = (dateString) => {
  if (!dateString) return null;
  try {
    const cleanStr = String(dateString).trim();
    if (cleanStr.includes('Z') || cleanStr.includes('+')) {
      const d = new Date(cleanStr);
      if (!isNaN(d.getTime())) return d;
    }
    const parts = cleanStr.split(/[- :T]/);
    if (parts.length >= 3) {
      const year = parseInt(parts[0], 10);
      const month = parseInt(parts[1], 10) - 1;
      const day = parseInt(parts[2], 10);
      const hour = parts[3] ? parseInt(parts[3], 10) : 0;
      const min = parts[4] ? parseInt(parts[4], 10) : 0;
      const sec = parts[5] ? parseInt(parts[5], 10) : 0;
      return new Date(year, month, day, hour, min, sec);
    }
    return new Date(cleanStr);
  } catch (e) {
    return null;
  }
};

const formatDisplayDate = (dateString) => {
  const d = parseDateSafe(dateString);
  if (!d || isNaN(d.getTime())) return dateString || 'N/A';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const month = months[d.getMonth()];
  const day = d.getDate();
  const year = d.getFullYear();
  let hours = d.getHours();
  const minutes = String(d.getMinutes()).padStart(2, '0');
  const ampm = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12 || 12;
  return `${month} ${day}, ${year} • ${hours}:${minutes} ${ampm}`;
};

export default function FinesScreen({ navigation }) {
  const [summary, setSummary] = useState({ unpaid_fines: 0, total_fines: 0, paid_fines: 0, unpaid_count: 0 });
  const [finesList, setFinesList] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchFinesData = async () => {
    try {
      const token = await SecureStore.getItemAsync('user_token');
      if (!token) {
        navigation.replace('Login');
        return;
      }

      const headers = { Authorization: `Bearer ${token}` };

      // Get Profile to get ID
      const profileRes = await axios.get(`${API_URL}/auth/me`, { headers });
      if (profileRes.data.success) {
        const userId = profileRes.data.data.id;
        
        try {
          const finesRes = await axios.get(`${API_URL}/students/${userId}/fines`, { headers });
          if (finesRes.data.success && finesRes.data.data) {
             const data = finesRes.data.data;
             setSummary(data.summary || { unpaid_fines: 0, total_fines: 0, paid_fines: 0, unpaid_count: 0 });
             setFinesList(data.fines_history || []);
          }
        } catch(e) {
          console.error('Error fetching student fines:', e);
        }
      }

    } catch (error) {
      console.error('Fines Error:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchFinesData();
    }, [])
  );

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#063B5C" />
      </View>
    );
  }

  return (
    <ScrollView 
      style={styles.container}
      contentContainerStyle={{ paddingBottom: 40 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchFinesData(); }} tintColor="#063B5C" />}
    >
      <View style={styles.headerContainer}>
        <Ionicons name="receipt" size={32} color="#063B5C" />
        <Text style={styles.title}>Fines & Penalties</Text>
        <Text style={styles.subtitle}>Track your event penalties and clearance balances.</Text>
      </View>

      {/* Main Outstanding Fines Card */}
      <View style={styles.finesCard}>
        <View style={styles.finesHeader}>
          <Ionicons name="alert-circle" size={24} color="#DC2626" />
          <Text style={styles.finesTitle}>Unpaid Fines Balance</Text>
        </View>
        <Text style={styles.finesAmount}>₱ {Number(summary.unpaid_fines || 0).toFixed(2)}</Text>
        <Text style={styles.finesSubtitle}>
          {summary.unpaid_count > 0 
            ? `${summary.unpaid_count} event${summary.unpaid_count > 1 ? 's' : ''} with outstanding balance`
            : 'All attendance penalties are fully cleared'}
        </Text>
      </View>

      {/* Fines Breakdown Section */}
      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Penalties Breakdown</Text>
        <Text style={styles.sectionBadge}>{finesList.length} Records</Text>
      </View>

      {finesList.length === 0 ? (
        <View style={styles.emptyCard}>
          <Ionicons name="checkmark-circle-outline" size={48} color="#168A63" />
          <Text style={styles.emptyTitle}>No Fines Incurred</Text>
          <Text style={styles.emptySubtitle}>You have a clean attendance record with ₱0 outstanding balance.</Text>
        </View>
      ) : (
        finesList.map((item) => {
          const isPaid = !!item.fine_paid;
          const isWhole = item.event?.session_type === 'whole_day';
          const slotStatuses = item.slot_statuses || {};
          const missedSlots = Object.keys(slotStatuses).filter(k => slotStatuses[k] === 'missed');
          const lateSlots = Object.keys(slotStatuses).filter(k => slotStatuses[k] === 'late');

          let penaltyReason = '';
          if (missedSlots.length > 0) {
            penaltyReason += `${missedSlots.length} Missed Scan${missedSlots.length > 1 ? 's' : ''}`;
          }
          if (lateSlots.length > 0) {
            penaltyReason += (penaltyReason ? ' • ' : '') + `${lateSlots.length} Late Scan${lateSlots.length > 1 ? 's' : ''}`;
          }
          if (!penaltyReason) {
            penaltyReason = item.status === 'late' ? 'Late Attendance' : 'Non-Attendance';
          }

          const recordDate = item.pm_time_out || item.pm_time_in || item.am_time_out || item.am_time_in || item.checkout_time || item.scan_time || item.created_at;

          return (
            <View key={item.id} style={[styles.fineItemCard, isPaid ? styles.fineItemPaid : styles.fineItemUnpaid]}>
              <View style={styles.fineItemTop}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.fineEventTitle}>{item.event?.title || 'Campus Event'}</Text>
                  <Text style={styles.fineEventMeta}>
                    {isWhole ? 'Whole Day Event (4 Scans)' : 'Standard Event (2 Scans)'}
                  </Text>
                </View>
                <View style={isPaid ? styles.badgePaid : styles.badgeUnpaid}>
                  <Text style={isPaid ? styles.badgePaidText : styles.badgeUnpaidText}>
                    {isPaid ? 'PAID' : 'UNPAID'}
                  </Text>
                </View>
              </View>

              <View style={styles.fineItemDivider} />

              <View style={styles.fineItemBottom}>
                <View style={{ flex: 1 }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 4 }}>
                    <Ionicons name="warning-outline" size={14} color="#D97706" style={{ marginRight: 4 }} />
                    <Text style={styles.fineReasonText}>{penaltyReason}</Text>
                  </View>
                  <Text style={styles.fineDateText}>{formatDisplayDate(recordDate)}</Text>
                </View>

                <View style={{ alignItems: 'flex-end' }}>
                  <Text style={styles.fineItemAmountLabel}>Penalty Amount</Text>
                  <Text style={[styles.fineItemAmount, isPaid ? styles.amountPaid : styles.amountUnpaid]}>
                    ₱ {Number(item.fine_amount || 0).toFixed(2)}
                  </Text>
                </View>
              </View>
            </View>
          );
        })
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  centerContainer: {
    flex: 1,
    backgroundColor: '#F5F9FC',
    justifyContent: 'center',
    alignItems: 'center',
  },
  container: {
    flex: 1,
    backgroundColor: '#F5F9FC',
    padding: 16,
  },
  headerContainer: {
    marginTop: 15,
    marginBottom: 20,
    alignItems: 'center'
  },
  title: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#063B5C',
    marginTop: 8,
  },
  subtitle: {
    color: '#6B7A86',
    fontSize: 13,
    marginTop: 4,
    textAlign: 'center'
  },
  clearanceCard: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginBottom: 14,
    borderWidth: 1.5,
  },
  clearanceCardCleared: {
    backgroundColor: '#F0FDF4',
    borderColor: '#86EFAC',
  },
  clearanceCardHold: {
    backgroundColor: '#FEF2F2',
    borderColor: '#FECACA',
  },
  clearanceIconWrapper: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  clearanceIconCleared: {
    backgroundColor: '#DCFCE7',
  },
  clearanceIconHold: {
    backgroundColor: '#FEE2E2',
  },
  clearanceTextWrapper: {
    flex: 1,
  },
  clearanceTitle: {
    fontSize: 13,
    fontWeight: 'bold',
    letterSpacing: 0.2,
  },
  clearanceTitleCleared: {
    color: '#15803D',
  },
  clearanceTitleHold: {
    color: '#B91C1C',
  },
  clearanceSub: {
    fontSize: 11.5,
    color: '#475569',
    marginTop: 1,
  },
  finesCard: {
    borderRadius: 14,
    padding: 20,
    marginBottom: 24,
    borderWidth: 1,
    borderColor: '#FEE2E2',
    backgroundColor: '#FFF5F5',
    shadowColor: 'rgba(220, 38, 38, 0.08)',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 10,
    elevation: 3,
    alignItems: 'center',
  },
  finesHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  finesTitle: {
    color: '#991B1B',
    fontSize: 15,
    fontWeight: '700',
    marginLeft: 8,
  },
  finesAmount: {
    color: '#DC2626',
    fontSize: 32,
    fontWeight: 'bold',
    marginVertical: 4,
  },
  finesSubtitle: {
    color: '#7F1D1D',
    fontSize: 12,
    fontWeight: '500',
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
    paddingHorizontal: 4,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#063B5C',
  },
  sectionBadge: {
    fontSize: 12,
    color: '#6B7A86',
    fontWeight: '600',
  },
  emptyCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 30,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#168A63',
    marginTop: 10,
  },
  emptySubtitle: {
    fontSize: 13,
    color: '#6B7A86',
    textAlign: 'center',
    marginTop: 6,
    lineHeight: 18,
  },
  fineItemCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 6,
    elevation: 2,
  },
  fineItemUnpaid: {
    borderColor: '#FECACA',
    borderLeftWidth: 5,
    borderLeftColor: '#DC2626',
  },
  fineItemPaid: {
    borderColor: '#D1FAE5',
    borderLeftWidth: 5,
    borderLeftColor: '#168A63',
  },
  fineItemTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  fineEventTitle: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#0F172A',
  },
  fineEventMeta: {
    fontSize: 12,
    color: '#64748B',
    marginTop: 2,
  },
  badgeUnpaid: {
    backgroundColor: '#FEE2E2',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  badgeUnpaidText: {
    color: '#DC2626',
    fontSize: 11,
    fontWeight: 'bold',
  },
  badgePaid: {
    backgroundColor: '#D1FAE5',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  badgePaidText: {
    color: '#168A63',
    fontSize: 11,
    fontWeight: 'bold',
  },
  fineItemDivider: {
    height: 1,
    backgroundColor: '#F1F5F9',
    marginVertical: 12,
  },
  fineItemBottom: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
  },
  fineReasonText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#B45309',
  },
  fineDateText: {
    fontSize: 11,
    color: '#94A3B8',
  },
  fineItemAmountLabel: {
    fontSize: 10,
    color: '#94A3B8',
    textTransform: 'uppercase',
    fontWeight: '600',
  },
  fineItemAmount: {
    fontSize: 18,
    fontWeight: 'bold',
    marginTop: 2,
  },
  amountUnpaid: {
    color: '#DC2626',
  },
  amountPaid: {
    color: '#168A63',
  },
});
