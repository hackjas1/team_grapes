import React, { useState, useEffect } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  ScrollView, 
  TouchableOpacity, 
  ActivityIndicator,
  RefreshControl 
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as SecureStore from 'expo-secure-store';
import axios from 'axios';
import { API_URL } from '../config';

export default function EventDetailsScreen({ route, navigation }) {
  const insets = useSafeAreaInsets();
  const initialEvent = route.params?.event || null;
  const eventId = route.params?.eventId || initialEvent?.id;

  const [event, setEvent] = useState(initialEvent);
  const [loading, setLoading] = useState(!initialEvent);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);

  const fetchEventDetails = async () => {
    if (!eventId) return;
    try {
      setError(null);
      const token = await SecureStore.getItemAsync('user_token');
      const res = await axios.get(`${API_URL}/events/${eventId}`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      if (res.data.success) {
        setEvent(res.data.data);
      }
    } catch (err) {
      console.log('Error fetching event details:', err.message);
      if (!event) {
        setError('Failed to load event details. Please check your connection.');
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    if (!event && eventId) {
      fetchEventDetails();
    }
  }, [eventId]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchEventDetails();
  };

  const parseDateSafe = (str) => {
    if (!str) return null;
    if (str.includes('T')) {
      return new Date(str.split('.')[0] + 'Z');
    }
    const parts = str.split(' ');
    if (parts.length < 2) {
      const timeParts = str.split(':');
      if (timeParts.length >= 2) {
        const d = new Date();
        d.setHours(timeParts[0], timeParts[1], timeParts[2] || 0);
        return d;
      }
      return new Date();
    }
    const [year, month, day] = parts[0].split('-');
    const [hour, min, sec] = parts[1].split(':');
    return new Date(year, month - 1, day, hour, min, sec || 0);
  };

  const formatDisplayDateTime = (dateStr) => {
    if (!dateStr) return 'N/A';
    const d = parseDateSafe(dateStr);
    if (!d || isNaN(d.getTime())) return dateStr;
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

  const formatSlotTime = (t) => {
    if (!t) return null;
    try {
      const d = parseDateSafe(t);
      if (!d || isNaN(d.getTime())) return t;
      let hours = d.getHours();
      const minutes = String(d.getMinutes()).padStart(2, '0');
      const ampm = hours >= 12 ? 'PM' : 'AM';
      hours = hours % 12 || 12;
      return `${hours}:${minutes} ${ampm}`;
    } catch (err) {
      return t;
    }
  };

  const renderWindow = (start, end, fallbackTime, fallbackLabel = 'Open from') => {
    const s = formatSlotTime(start);
    const en = formatSlotTime(end);
    if (s && en) {
      return (
        <Text style={styles.windowTimeText}>
          <Text style={styles.windowTimeHighlight}>{s}</Text> - <Text style={styles.windowTimeHighlight}>{en}</Text>
        </Text>
      );
    }
    if (s && !en) {
      return (
        <Text style={styles.windowTimeText}>
          Starts <Text style={styles.windowTimeHighlight}>{s}</Text>
        </Text>
      );
    }
    if (!s && en) {
      return (
        <Text style={styles.windowTimeText}>
          Until <Text style={styles.windowTimeHighlight}>{en}</Text>
        </Text>
      );
    }
    if (fallbackTime) {
      const fb = formatSlotTime(fallbackTime);
      if (fb) {
        return (
          <Text style={styles.windowTimeText}>
            {fallbackLabel} <Text style={styles.windowTimeHighlight}>{fb}</Text>
          </Text>
        );
      }
    }
    return <Text style={styles.windowTimeText}>Active Window Open</Text>;
  };

  if (loading) {
    return (
      <View style={[styles.container, styles.center]}>
        <ActivityIndicator size="large" color="#063B5C" />
        <Text style={styles.loadingText}>Loading event details...</Text>
      </View>
    );
  }

  if (error || !event) {
    return (
      <View style={[styles.container, styles.center, { padding: 24 }]}>
        <Ionicons name="alert-circle-outline" size={56} color="#DC2626" />
        <Text style={styles.errorTitle}>Unable to Load Event</Text>
        <Text style={styles.errorText}>{error || 'Event data not found.'}</Text>
        <TouchableOpacity style={styles.retryButton} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={18} color="#FFFFFF" style={{ marginRight: 6 }} />
          <Text style={styles.retryButtonText}>Go Back</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const isActive = event.status === 'active';
  const isCompleted = event.status === 'completed';
  const isUpcoming = event.status === 'upcoming' || (!isActive && !isCompleted);
  const isWhole = event.session_type === 'whole_day';

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={[styles.header, { paddingTop: Math.max(insets.top + 10, 44) }]}>
        <TouchableOpacity style={styles.backButton} onPress={() => navigation.goBack()} activeOpacity={0.7}>
          <Ionicons name="arrow-back" size={24} color="#FFF" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Event Information</Text>
        <View style={{ width: 24 }} />
      </View>

      <ScrollView 
        style={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={['#063B5C']} />
        }
      >
        {/* Status Badge Row */}
        <View style={styles.statusRow}>
          <View style={isActive ? styles.badgeActive : (isUpcoming ? styles.badgeUpcoming : styles.badgeCompleted)}>
            <Ionicons 
              name={isActive ? "radio" : (isUpcoming ? "hourglass-outline" : "checkmark-done-circle-outline")} 
              size={13} 
              color="#FFFFFF" 
            />
            <Text style={styles.badgeTextWhite}>
              {isActive ? 'ACTIVE SESSION' : (isUpcoming ? 'UPCOMING EVENT' : 'COMPLETED EVENT')}
            </Text>
          </View>
          
          <View style={styles.audienceBadge}>
            <Ionicons name="people" size={13} color="#FFF" style={{ marginRight: 4 }} />
            <Text style={styles.audienceBadgeText}>
              {event.target_audience_label || 'All BSIS Students'}
            </Text>
          </View>
        </View>

        {/* Title & Description */}
        <Text style={styles.title}>{event.title}</Text>
        <Text style={styles.description}>
          {event.description || 'No detailed description provided for this institutional event.'}
        </Text>

        <View style={styles.divider} />

        {/* Details List */}
        <View style={styles.detailRow}>
          <View style={[styles.iconContainer, { backgroundColor: '#FEE2E2' }]}>
            <Ionicons name="location" size={20} color="#DC2626" />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.detailLabel}>Venue Location</Text>
            <Text style={styles.detailValue}>{event.venue_name || 'Designated Campus Venue'}</Text>
            <Text style={styles.detailSub}>Perimeter: {event.allowed_radius_meters || 50} meters</Text>
          </View>
        </View>

        <View style={styles.detailRow}>
          <View style={[styles.iconContainer, { backgroundColor: '#E0F2FE' }]}>
            <Ionicons name="time" size={20} color="#0284C7" />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.detailLabel}>Event Schedule</Text>
            <Text style={styles.detailSub}>Starts: <Text style={styles.detailValue}>{formatDisplayDateTime(event.start_time)}</Text></Text>
            <Text style={styles.detailSub}>Ends: <Text style={styles.detailValue}>{formatDisplayDateTime(event.end_time)}</Text></Text>
          </View>
        </View>

        <View style={styles.detailRow}>
          <View style={[styles.iconContainer, { backgroundColor: '#FEF3C7' }]}>
            <Ionicons name="cash" size={20} color="#D97706" />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.detailLabel}>Absence / Missed Slot Fine</Text>
            <Text style={styles.detailValue}>
              {parseFloat(event.fine_amount) > 0 ? `₱${parseFloat(event.fine_amount).toFixed(2)}` : 'No Fine Assessment'}
            </Text>
          </View>
        </View>

        <View style={styles.detailRow}>
          <View style={[styles.iconContainer, { backgroundColor: '#E8F6F1' }]}>
            <Ionicons name="scan" size={20} color="#168A63" />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.detailLabel}>Attendance Scan Mode</Text>
            <Text style={styles.detailValue}>
              {isWhole ? 'Whole Day (4 Attendance Scans)' : 'Single Session (2 Attendance Scans)'}
            </Text>
          </View>
        </View>

        {/* Dynamic Scanning Windows */}
        <View style={styles.windowsContainer}>
          <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 12 }}>
            <Ionicons name="calendar-outline" size={16} color="#063B5C" style={{ marginRight: 6 }} />
            <Text style={styles.windowsHeader}>Scheduled Attendance Windows</Text>
          </View>
          
          <View style={styles.windowsGrid}>
            {isWhole ? (
              <>
                <View style={styles.windowBox}>
                  <Text style={styles.windowTitle}><Ionicons name="sunny" size={12} color="#F59E0B"/> AM Time-In</Text>
                  {renderWindow(event.am_checkin_start_time, event.am_checkin_end_time)}
                </View>
                <View style={styles.windowBox}>
                  <Text style={styles.windowTitle}><Ionicons name="sunny-outline" size={12} color="#F59E0B"/> AM Time-Out</Text>
                  {renderWindow(event.am_checkout_start_time, event.am_checkout_end_time)}
                </View>
                <View style={styles.windowBox}>
                  <Text style={styles.windowTitle}><Ionicons name="partly-sunny" size={12} color="#063B5C"/> PM Time-In</Text>
                  {renderWindow(event.pm_checkin_start_time, event.pm_checkin_end_time)}
                </View>
                <View style={styles.windowBox}>
                  <Text style={styles.windowTitle}><Ionicons name="moon" size={12} color="#063B5C"/> PM Time-Out</Text>
                  {renderWindow(event.pm_checkout_start_time, event.pm_checkout_end_time)}
                </View>
              </>
            ) : (
              <>
                <View style={styles.windowBox}>
                  <Text style={styles.windowTitle}><Ionicons name="sunny" size={12} color="#F59E0B"/> Morning Time-In</Text>
                  {renderWindow(
                    event.checkin_start_time || event.am_checkin_start_time,
                    event.checkin_end_time || event.am_checkin_end_time,
                    event.start_time,
                    'Starts at'
                  )}
                </View>
                <View style={styles.windowBox}>
                  <Text style={styles.windowTitle}><Ionicons name="moon" size={12} color="#063B5C"/> Afternoon Time-Out</Text>
                  {renderWindow(
                    event.checkout_start_time || event.pm_checkout_start_time,
                    event.checkout_end_time || event.pm_checkout_end_time,
                    event.end_time,
                    'Closes at'
                  )}
                </View>
              </>
            )}
          </View>
        </View>
        
        <View style={{ height: 120 }} />
      </ScrollView>

      {/* Fixed Action Button */}
      {isActive && (
        <View style={[styles.actionContainer, { paddingBottom: Math.max(insets.bottom, 20) }]}>
          <TouchableOpacity 
            style={styles.scanButton} 
            activeOpacity={0.85}
            onPress={() => {
              navigation.navigate('MainTabs', { screen: 'Scan' });
            }}
          >
            <Ionicons name="qr-code-outline" size={20} color="#FFF" style={{ marginRight: 8 }} />
            <Text style={styles.scanButtonText}>Open Live QR Scanner</Text>
          </TouchableOpacity>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F8FAFC',
  },
  center: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 14,
    color: '#64748B',
    fontWeight: '500',
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#0F172A',
    marginTop: 14,
    marginBottom: 6,
  },
  errorText: {
    fontSize: 14,
    color: '#64748B',
    textAlign: 'center',
    marginBottom: 20,
    lineHeight: 20,
  },
  retryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#063B5C',
    paddingHorizontal: 20,
    paddingVertical: 11,
    borderRadius: 10,
  },
  retryButtonText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: 'bold',
  },
  header: {
    backgroundColor: '#063B5C',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingBottom: 15,
    paddingHorizontal: 16,
    shadowColor: 'rgba(6, 59, 92, 0.15)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 4,
    elevation: 3,
  },
  backButton: {
    padding: 6,
  },
  headerTitle: {
    color: '#FFF',
    fontSize: 17,
    fontWeight: 'bold',
  },
  content: {
    flex: 1,
    padding: 18,
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 14,
  },
  badgeActive: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#0284C7',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
  },
  badgeUpcoming: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F97316',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
  },
  badgeCompleted: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#16A34A',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
  },
  badgeTextWhite: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: 'bold',
    marginLeft: 5,
  },
  audienceBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#063B5C',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
  },
  audienceBadgeText: {
    color: '#FFF',
    fontSize: 11,
    fontWeight: 'bold',
  },
  title: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#0F172A',
    marginBottom: 8,
    lineHeight: 28,
  },
  description: {
    fontSize: 14,
    color: '#64748B',
    lineHeight: 22,
    marginBottom: 18,
  },
  divider: {
    height: 1,
    backgroundColor: '#E2E8F0',
    marginBottom: 18,
  },
  detailRow: {
    flexDirection: 'row',
    marginBottom: 16,
    alignItems: 'flex-start',
  },
  iconContainer: {
    width: 40,
    height: 40,
    borderRadius: 10,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  detailLabel: {
    fontSize: 12,
    color: '#64748B',
    marginBottom: 2,
    fontWeight: '500',
  },
  detailValue: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#0F172A',
  },
  detailSub: {
    fontSize: 12,
    color: '#94A3B8',
    marginTop: 2,
  },
  actionContainer: {
    backgroundColor: '#FFF',
    padding: 16,
    borderTopWidth: 1,
    borderTopColor: '#E2E8F0',
  },
  scanButton: {
    backgroundColor: '#0284C7',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    borderRadius: 10,
    shadowColor: '#0284C7',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 6,
    elevation: 3,
  },
  scanButtonText: {
    color: '#FFF',
    fontSize: 15,
    fontWeight: 'bold',
  },
  windowsContainer: {
    marginTop: 6,
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    shadowColor: 'rgba(0, 0, 0, 0.03)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 4,
    elevation: 1,
  },
  windowsHeader: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#063B5C',
  },
  windowsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  windowBox: {
    width: '48%',
    backgroundColor: '#F8FAFC',
    borderRadius: 8,
    padding: 10,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  windowTitle: {
    fontSize: 11.5,
    fontWeight: 'bold',
    color: '#0F172A',
    marginBottom: 4,
  },
  windowTimeText: {
    fontSize: 11,
    color: '#64748B',
  },
  windowTimeHighlight: {
    color: '#063B5C',
    fontWeight: '700',
  }
});
