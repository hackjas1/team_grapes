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

const formatTimeOnly = (dateString) => {
  if (!dateString || typeof dateString !== 'string') return null;
  const clean = dateString.trim();
  if (!clean || clean === 'null' || clean === 'undefined') return null;

  // 1. If already AM/PM
  const ampmMatch = clean.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$/i);
  if (ampmMatch) {
    return `${parseInt(ampmMatch[1], 10)}:${ampmMatch[2]} ${ampmMatch[3].toUpperCase()}`;
  }

  // 2. If ISO string with UTC indicator
  if (clean.includes('Z') || clean.includes('+') || clean.includes('T')) {
    try {
      const d = new Date(clean);
      if (!isNaN(d.getTime())) {
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${hours}:${minutes} ${ampm}`;
      }
    } catch(e) {}
  }

  // 3. If SQL datetime
  const sqlMatch = clean.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
  if (sqlMatch) {
    let hours = parseInt(sqlMatch[4], 10);
    const minutes = sqlMatch[5];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${hours}:${minutes} ${ampm}`;
  }

  return null;
};

const formatDisplayDate = (dateString) => {
  const d = parseDateSafe(dateString);
  if (!d || isNaN(d.getTime())) return dateString || 'N/A';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const month = months[d.getMonth()];
  const day = d.getDate();
  const year = d.getFullYear();
  return `${month} ${day}, ${year}`;
};

export default function HistoryScreen({ navigation }) {
  const [history, setHistory] = useState([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchHistory = async () => {
    try {
      const token = await SecureStore.getItemAsync('user_token');
      if (!token) {
        navigation.replace('Login');
        return;
      }

      const headers = { Authorization: `Bearer ${token}` };
      const res = await axios.get(`${API_URL}/attendance`, { headers });
      
      if (res.data.success) {
        const records = res.data.data?.data || res.data.data || [];
        setHistory(Array.isArray(records) ? records : []);
      }
    } catch (error) {
      console.error('History Fetch Error:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchHistory();
    }, [])
  );

  const renderSlotTime = (timeVal, slotKey, record) => {
    const formatted = formatTimeOnly(timeVal);
    if (formatted) {
      return <Text style={styles.slotTimeActive}>{formatted}</Text>;
    }

    const isEventActive = record.event?.status === 'active';
    const isExplicitlyMissed = record.slot_statuses?.[slotKey] === 'missed';

    if (isEventActive && !isExplicitlyMissed) {
      return <Text style={styles.slotTimePending}>Pending</Text>;
    }

    return <Text style={styles.slotTimeMissed}>Missed</Text>;
  };

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
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchHistory(); }} tintColor="#063B5C" />}
    >
      <View style={styles.headerContainer}>
        <Ionicons name="journal" size={32} color="#063B5C" />
        <Text style={styles.title}>Attendance Logs</Text>
        <Text style={styles.subtitle}>Full chronological history of all your event scans.</Text>
      </View>

      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Recorded Events</Text>
        <Text style={styles.sectionBadge}>{history.length} Entries</Text>
      </View>

      {history.length === 0 ? (
        <View style={styles.emptyCard}>
          <Ionicons name="document-text-outline" size={48} color="#94A3B8" />
          <Text style={styles.emptyTitle}>No Attendance Records Yet</Text>
          <Text style={styles.emptySubtitle}>Your scan logs and session timestamps will appear here after attending events.</Text>
        </View>
      ) : (
        history.map((record) => {
          const isWhole = record.event?.session_type === 'whole_day';
          const isActive = record.event?.status === 'active';
          const isLate = record.status === 'late' || (parseFloat(record.fine_amount) > 0);
          const eventDate = record.event?.start_time || record.created_at;

          let badgeStyle = styles.badgePresent;
          let badgeText = 'COMPLETED';

          if (isActive) {
            badgeStyle = styles.badgeActive;
            badgeText = 'ACTIVE SESSION';
          } else if (isLate) {
            badgeStyle = styles.badgeLate;
            badgeText = 'LATE / PENALTY';
          }

          return (
            <View key={record.id} style={styles.card}>
              <View style={styles.cardTop}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.eventTitle}>{record.event?.title || 'Campus Event'}</Text>
                  <Text style={styles.eventDateText}>{formatDisplayDate(eventDate)} • {record.event?.venue_name || 'Campus Venue'}</Text>
                </View>
                <View style={badgeStyle}>
                  <Text style={[styles.badgeText, isActive ? styles.badgeActiveText : (isLate ? styles.badgeLateText : styles.badgePresentText)]}>
                    {badgeText}
                  </Text>
                </View>
              </View>

              <View style={styles.divider} />

              {/* Slot Timestamps Grid */}
              <View style={styles.slotsGrid}>
                {isWhole ? (
                  <>
                    <View style={styles.slotBox}>
                      <Text style={styles.slotLabel}>AM IN</Text>
                      {renderSlotTime(record.am_time_in, 'am_in', record)}
                    </View>
                    <View style={styles.slotBox}>
                      <Text style={styles.slotLabel}>AM OUT</Text>
                      {renderSlotTime(record.am_time_out, 'am_out', record)}
                    </View>
                    <View style={styles.slotBox}>
                      <Text style={styles.slotLabel}>PM IN</Text>
                      {renderSlotTime(record.pm_time_in, 'pm_in', record)}
                    </View>
                    <View style={styles.slotBox}>
                      <Text style={styles.slotLabel}>PM OUT</Text>
                      {renderSlotTime(record.pm_time_out, 'pm_out', record)}
                    </View>
                  </>
                ) : (
                  <>
                    <View style={styles.slotBox}>
                      <Text style={styles.slotLabel}>MORNING IN</Text>
                      {renderSlotTime(record.am_time_in || record.scan_time, 'morning_in', record)}
                    </View>
                    <View style={styles.slotBox}>
                      <Text style={styles.slotLabel}>AFTERNOON OUT</Text>
                      {renderSlotTime(record.pm_time_out || record.checkout_time, 'afternoon_out', record)}
                    </View>
                  </>
                )}
              </View>

              {parseFloat(record.fine_amount) > 0 && (
                <View style={styles.fineNotice}>
                  <Ionicons name="receipt-outline" size={14} color="#DC2626" />
                  <Text style={styles.fineNoticeText}>
                    Incurred Fine: <Text style={{ fontWeight: 'bold' }}>₱{parseFloat(record.fine_amount).toFixed(2)}</Text> ({record.fine_paid ? 'Paid' : 'Unpaid'})
                  </Text>
                </View>
              )}
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
    fontSize: 20,
    fontWeight: 'bold',
    color: '#063B5C',
    marginTop: 8,
  },
  subtitle: {
    fontSize: 13,
    color: '#64748B',
    marginTop: 4,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#063B5C',
  },
  sectionBadge: {
    backgroundColor: '#E6F4FE',
    color: '#063B5C',
    fontSize: 11,
    fontWeight: 'bold',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
  },
  emptyCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 30,
    alignItems: 'center',
    marginTop: 10,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#063B5C',
    marginTop: 12,
  },
  emptySubtitle: {
    fontSize: 13,
    color: '#64748B',
    textAlign: 'center',
    marginTop: 6,
    lineHeight: 18,
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#EEF4F8',
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 6,
    elevation: 2,
  },
  cardTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  eventTitle: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#0F172A',
  },
  eventDateText: {
    fontSize: 12,
    color: '#64748B',
    marginTop: 2,
  },
  badgeText: {
    fontSize: 10,
    fontWeight: 'bold',
  },
  badgePresent: {
    backgroundColor: '#D1FAE5',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  badgePresentText: {
    color: '#168A63',
  },
  badgeActive: {
    backgroundColor: '#E0F2FE',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  badgeActiveText: {
    color: '#0284C7',
  },
  badgeLate: {
    backgroundColor: '#FEE2E2',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  badgeLateText: {
    color: '#DC2626',
  },
  divider: {
    height: 1,
    backgroundColor: '#F1F5F9',
    marginVertical: 12,
  },
  slotsGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  slotBox: {
    flex: 1,
    alignItems: 'center',
    backgroundColor: '#F8FAFC',
    paddingVertical: 8,
    paddingHorizontal: 4,
    marginHorizontal: 3,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  slotLabel: {
    fontSize: 10,
    fontWeight: 'bold',
    color: '#64748B',
    marginBottom: 3,
  },
  slotTimeActive: {
    fontSize: 11,
    fontWeight: 'bold',
    color: '#168A63',
  },
  slotTimePending: {
    fontSize: 11,
    fontWeight: '600',
    color: '#94A3B8',
  },
  slotTimeMissed: {
    fontSize: 11,
    fontWeight: '600',
    color: '#DC2626',
  },
  fineNotice: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FEF2F2',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
    marginTop: 12,
  },
  fineNoticeText: {
    fontSize: 12,
    color: '#991B1B',
    marginLeft: 6,
  },
});
