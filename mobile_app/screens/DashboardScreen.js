import React, { useState, useEffect, useCallback } from 'react';
import { 
  StyleSheet, 
  Text, 
  View, 
  ScrollView, 
  TouchableOpacity, 
  RefreshControl, 
  ActivityIndicator,
  Image,
  AppState 
} from 'react-native';
import * as SecureStore from 'expo-secure-store';
import axios from 'axios';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';

import { API_URL } from '../config';

const formatTimeOnly = (t) => {
  if (!t) return 'Pending';
  if (typeof t !== 'string') return 'Pending';
  const clean = t.trim();
  if (!clean || clean === 'null' || clean === 'undefined') return 'Pending';

  // 1. Check if already contains AM/PM
  const ampmMatch = clean.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$/i);
  if (ampmMatch) {
    let hours = parseInt(ampmMatch[1], 10);
    return `${hours}:${ampmMatch[2]} ${ampmMatch[3].toUpperCase()}`;
  }

  // 2. If ISO string with UTC indicator (Z or + or T) -> convert to local time via Date
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

  // 3. If SQL datetime (e.g. '2026-08-18 20:45:00')
  const sqlMatch = clean.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
  if (sqlMatch) {
    let hours = parseInt(sqlMatch[4], 10);
    const minutes = sqlMatch[5];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${hours}:${minutes} ${ampm}`;
  }

  // 4. Check 24-hour time only (e.g. "08:30:00" or "20:45")
  const timeOnlyMatch = clean.match(/^(\d{1,2}):(\d{2})/);
  if (timeOnlyMatch) {
    let hours = parseInt(timeOnlyMatch[1], 10);
    const minutes = timeOnlyMatch[2];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${hours}:${minutes} ${ampm}`;
  }

  return clean;
};

const formatDisplayDateTime = (dateStr) => {
  if (!dateStr || typeof dateStr !== 'string') return 'N/A';
  const clean = dateStr.trim();
  if (clean.includes('Z') || clean.includes('+') || clean.includes('T')) {
    try {
      const d = new Date(clean);
      if (!isNaN(d.getTime())) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[d.getMonth()];
        const day = d.getDate();
        const year = d.getFullYear();
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${month} ${day}, ${year} • ${hours}:${minutes} ${ampm}`;
      }
    } catch(e) {}
  }
  const isoOrSqlMatch = clean.match(/(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/);
  if (isoOrSqlMatch) {
    const year = parseInt(isoOrSqlMatch[1], 10);
    const monthIdx = parseInt(isoOrSqlMatch[2], 10) - 1;
    const day = parseInt(isoOrSqlMatch[3], 10);
    let hours = parseInt(isoOrSqlMatch[4], 10);
    const minutes = isoOrSqlMatch[5];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[monthIdx] || 'Jan'} ${day}, ${year} • ${hours}:${minutes} ${ampm}`;
  }
  return clean;
};

export default function DashboardScreen({ navigation }) {
  const [user, setUser] = useState(null);
  const [events, setEvents] = useState([]);
  const [attendanceRecord, setAttendanceRecord] = useState(null);
  const [unpaidFines, setUnpaidFines] = useState(0);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  // 1. Instant Storage Cache Hydration (0ms render on cold launch)
  const hydrateFromCache = async () => {
    try {
      const [cachedUserStr, cachedEventsStr, cachedAttStr] = await Promise.all([
        SecureStore.getItemAsync('user_data'),
        SecureStore.getItemAsync('cached_events'),
        SecureStore.getItemAsync('cached_attendance')
      ]);

      if (cachedUserStr) {
        const cachedUser = JSON.parse(cachedUserStr);
        if (cachedUser) setUser(cachedUser);
      }

      if (cachedEventsStr) {
        const cachedEv = JSON.parse(cachedEventsStr);
        if (Array.isArray(cachedEv) && cachedEv.length > 0) {
          setEvents(cachedEv);
        }
      }

      if (cachedAttStr) {
        const cachedAtt = JSON.parse(cachedAttStr);
        if (cachedAtt) setAttendanceRecord(cachedAtt);
      }

      if (cachedUserStr || cachedEventsStr) {
        setLoading(false);
      }
    } catch (e) {
      console.log('Cache hydration error:', e.message);
    }
  };

  const fetchDashboardData = async (isManualRefresh = false) => {
    if (isManualRefresh) setRefreshing(true);

    try {
      const token = await SecureStore.getItemAsync('user_token');
      if (!token) {
        navigation.replace('Login');
        return;
      }

      // Ensure cache is loaded first
      await hydrateFromCache();

      const headers = { Authorization: `Bearer ${token}` };

      // Parallel Concurrency: Fetch Profile, Events, and Attendance simultaneously
      const [profileSettled, eventsSettled, attendanceSettled] = await Promise.allSettled([
        axios.get(`${API_URL}/auth/me`, { headers, timeout: 10000 }),
        axios.get(`${API_URL}/events?per_page=15`, { headers, timeout: 10000 }),
        axios.get(`${API_URL}/attendance`, { headers, timeout: 10000 })
      ]);

      let loggedUser = null;
      if (profileSettled.status === 'fulfilled' && profileSettled.value?.data?.success) {
        loggedUser = profileSettled.value.data.data;
        setUser(loggedUser);
        SecureStore.setItemAsync('user_data', JSON.stringify(loggedUser));
      }

      let fetchedEvents = [];
      if (eventsSettled.status === 'fulfilled' && eventsSettled.value?.data?.success) {
        fetchedEvents = eventsSettled.value.data.data.data || eventsSettled.value.data.data || [];
        setEvents(fetchedEvents);
        SecureStore.setItemAsync('cached_events', JSON.stringify(fetchedEvents));
      }

      // Check Active Event Attendance Record
      if (attendanceSettled.status === 'fulfilled' && attendanceSettled.value?.data?.success) {
        const list = attendanceSettled.value.data.data.data || attendanceSettled.value.data.data || [];
        const currentEventsList = fetchedEvents.length > 0 ? fetchedEvents : events;
        const activeEvt = currentEventsList.find(e => e.status === 'active');
        if (activeEvt && Array.isArray(list)) {
          const record = list.find(r => r.event_id == activeEvt.id);
          setAttendanceRecord(record || null);
          if (record) {
            SecureStore.setItemAsync('cached_attendance', JSON.stringify(record));
          } else {
            SecureStore.deleteItemAsync('cached_attendance');
          }
        } else {
          setAttendanceRecord(null);
          SecureStore.deleteItemAsync('cached_attendance');
        }
      }

      // Fetch Fines in parallel
      const effectiveUserId = loggedUser?.id || (user?.id);
      if (effectiveUserId) {
        try {
          const finesRes = await axios.get(`${API_URL}/students/${effectiveUserId}/fines`, { headers, timeout: 10000 });
          if (finesRes.data.success && finesRes.data.data) {
            setUnpaidFines(parseFloat(finesRes.data.data.total_fines || 0));
          }
        } catch (e) {
          console.log('Fines summary error:', e.message);
        }
      }

    } catch (error) {
      console.error('Dashboard Error:', error);
      if (error.response?.status === 401) {
        await SecureStore.deleteItemAsync('user_token');
        navigation.replace('Login');
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  // Immediate Cache Hydration & AppState Foreground Resume Listener
  useEffect(() => {
    hydrateFromCache();
    fetchDashboardData();

    // Trigger auto-refresh whenever app is brought back to foreground
    const subscription = AppState.addEventListener('change', nextAppState => {
      if (nextAppState === 'active') {
        fetchDashboardData();
      }
    });

    return () => subscription.remove();
  }, []);

  // Screen Tab Focus Listener
  useFocusEffect(
    useCallback(() => {
      fetchDashboardData();
    }, [])
  );

  if (loading && events.length === 0) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#063B5C" />
      </View>
    );
  }

  const activeEvent = events.find(e => e.status === 'active');
  const isWholeDay = activeEvent?.session_type === 'whole_day';
  
  // Whole Day Variables
  const amIn = attendanceRecord?.am_time_in || attendanceRecord?.scan_time;
  const amOut = attendanceRecord?.am_time_out || attendanceRecord?.am_checkout_time;
  const pmIn = attendanceRecord?.pm_time_in;
  const pmOut = attendanceRecord?.pm_time_out || attendanceRecord?.checkout_time || attendanceRecord?.pm_checkout_time;

  // Half Day Variables
  const timeIn = attendanceRecord?.am_time_in || attendanceRecord?.scan_time || attendanceRecord?.time_in;
  const timeOut = attendanceRecord?.checkout_time || attendanceRecord?.pm_time_out || attendanceRecord?.pm_checkout_time;

  const isCleared = unpaidFines === 0;

  return (
    <ScrollView 
      style={styles.container}
      contentContainerStyle={styles.scrollContent}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchDashboardData(); }} tintColor="#063B5C" />}
    >
      {/* Header Profile Card */}
      <TouchableOpacity 
        style={styles.profileCard} 
        activeOpacity={0.85}
        onPress={() => navigation.navigate('Profile')}
      >
        <View style={styles.profileHeader}>
          <View style={styles.avatar}>
            <Ionicons name="person" size={26} color="#FFFFFF" />
          </View>
          <View style={styles.profileInfo}>
            <View style={{flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center'}}>
              <Text style={styles.nameText}>{user?.full_name}</Text>
              <Ionicons name="chevron-forward" size={16} color="#6B7A86" />
            </View>
            <Text style={styles.idText}>
              Student ID: <Text style={{fontWeight: 'bold', color: '#17212B'}}>{user?.student_number}</Text> • <Text style={{color: '#063B5C', fontWeight: '600'}}>{user?.year_level} - {user?.section_block}</Text>
            </Text>
            <View style={styles.badgeContainer}>
              <Ionicons name="shield-checkmark" size={11} color="#168A63" style={{ marginRight: 3 }} />
              <Text style={styles.badgeText}>Hardware Bound Device</Text>
            </View>
          </View>
        </View>
      </TouchableOpacity>

      {/* Quick Action Scan Banner */}
      <View style={styles.scanBannerCard}>
        <Text style={styles.scanBannerTitle}>Ready to Record Attendance?</Text>
        <Text style={styles.scanBannerSub}>Scan the active dynamic event QR code with GPS verification.</Text>
        <TouchableOpacity style={styles.scanButton} onPress={() => navigation.navigate('Scan')} activeOpacity={0.85}>
          <Ionicons name="qr-code-outline" size={18} color="#063B5C" style={{ marginRight: 6 }} />
          <Text style={styles.scanButtonText}>Open Scanner</Text>
        </TouchableOpacity>
      </View>

      {/* Today's Attendance Pass (Connected Step Progress) */}
      {activeEvent ? (
        <View style={styles.timelineCard}>
          <View style={styles.timelineHeader}>
            <View style={{ flex: 1 }}>
              <View style={{flexDirection: 'row', alignItems: 'center'}}>
                <Ionicons name="calendar-outline" size={16} color="#063B5C" />
                <Text style={styles.timelineTitle}> Today's Attendance Pass</Text>
              </View>
              <Text style={styles.timelineSubtitle} numberOfLines={1}>
                {activeEvent.title} • {activeEvent.venue_name}
              </Text>
            </View>
            <View style={styles.badgeInfo}>
              <Text style={styles.badgeInfoText}>{isWholeDay ? '4 SCANS' : '2 SCANS'}</Text>
            </View>
          </View>
          
          <View style={styles.timelineSteps}>
            {isWholeDay ? (
              <>
                {/* AM IN */}
                <View style={styles.timelineStep}>
                   <View style={[styles.stepDot, amIn ? styles.stepDotDone : styles.stepDotActive]}>
                      {amIn ? <Ionicons name="checkmark" size={13} color="#FFF" /> : <View style={styles.innerPulseDot} />}
                   </View>
                   <View style={styles.stepInfo}>
                      <Text style={[styles.stepLabel, amIn && styles.stepLabelDone]}>AM In</Text>
                      <Text style={amIn ? styles.stepTime : styles.stepTimePending}>{formatTimeOnly(amIn)}</Text>
                   </View>
                </View>
                
                <View style={[styles.stepLine, amIn ? styles.stepLineDone : styles.stepLinePending]} />
                
                {/* AM OUT */}
                <View style={styles.timelineStep}>
                   <View style={[styles.stepDot, amOut ? styles.stepDotDone : (amIn ? styles.stepDotActive : styles.stepDotPending)]}>
                      {amOut ? <Ionicons name="checkmark" size={13} color="#FFF" /> : (amIn && <View style={styles.innerPulseDot} />)}
                   </View>
                   <View style={styles.stepInfo}>
                      <Text style={[styles.stepLabel, amOut && styles.stepLabelDone]}>AM Out</Text>
                      <Text style={amOut ? styles.stepTime : styles.stepTimePending}>{formatTimeOnly(amOut)}</Text>
                   </View>
                </View>
                
                <View style={[styles.stepLine, amOut ? styles.stepLineDone : styles.stepLinePending]} />
                
                {/* PM IN */}
                <View style={styles.timelineStep}>
                   <View style={[styles.stepDot, pmIn ? styles.stepDotDone : (amOut ? styles.stepDotActive : styles.stepDotPending)]}>
                      {pmIn ? <Ionicons name="checkmark" size={13} color="#FFF" /> : (amOut && <View style={styles.innerPulseDot} />)}
                   </View>
                   <View style={styles.stepInfo}>
                      <Text style={[styles.stepLabel, pmIn && styles.stepLabelDone]}>PM In</Text>
                      <Text style={pmIn ? styles.stepTime : styles.stepTimePending}>{formatTimeOnly(pmIn)}</Text>
                   </View>
                </View>
                
                <View style={[styles.stepLine, pmIn ? styles.stepLineDone : styles.stepLinePending]} />
                
                {/* PM OUT */}
                <View style={styles.timelineStep}>
                   <View style={[styles.stepDot, pmOut ? styles.stepDotDone : (pmIn ? styles.stepDotActive : styles.stepDotPending)]}>
                      {pmOut ? <Ionicons name="checkmark" size={13} color="#FFF" /> : (pmIn && <View style={styles.innerPulseDot} />)}
                   </View>
                   <View style={styles.stepInfo}>
                      <Text style={[styles.stepLabel, pmOut && styles.stepLabelDone]}>PM Out</Text>
                      <Text style={pmOut ? styles.stepTime : styles.stepTimePending}>{formatTimeOnly(pmOut)}</Text>
                   </View>
                </View>
              </>
            ) : (
              <>
                {/* Morning Time-In */}
                <View style={styles.timelineStep}>
                   <View style={[styles.stepDot, timeIn ? styles.stepDotDone : styles.stepDotActive]}>
                      {timeIn ? <Ionicons name="checkmark" size={13} color="#FFF" /> : <View style={styles.innerPulseDot} />}
                   </View>
                   <View style={styles.stepInfo}>
                      <Text style={[styles.stepLabel, timeIn && styles.stepLabelDone]}>Time-In</Text>
                      <Text style={timeIn ? styles.stepTime : styles.stepTimePending}>{formatTimeOnly(timeIn)}</Text>
                   </View>
                </View>
                
                <View style={[styles.stepLine, timeIn ? styles.stepLineDone : styles.stepLinePending]} />
                
                {/* Afternoon Time-Out */}
                <View style={styles.timelineStep}>
                   <View style={[styles.stepDot, timeOut ? styles.stepDotDone : (timeIn ? styles.stepDotActive : styles.stepDotPending)]}>
                      {timeOut ? <Ionicons name="checkmark" size={13} color="#FFF" /> : (timeIn && <View style={styles.innerPulseDot} />)}
                   </View>
                   <View style={styles.stepInfo}>
                      <Text style={[styles.stepLabel, timeOut && styles.stepLabelDone]}>Time-Out</Text>
                      <Text style={timeOut ? styles.stepTime : styles.stepTimePending}>{formatTimeOnly(timeOut)}</Text>
                   </View>
                </View>
              </>
            )}
          </View>
        </View>
      ) : (
        /* Empty State Card when No Active Event */
        <View style={styles.emptyPassCard}>
          <View style={styles.emptyPassIconWrapper}>
            <Ionicons name="calendar-outline" size={24} color="#063B5C" />
          </View>
          <View style={styles.emptyPassTextWrapper}>
            <Text style={styles.emptyPassTitle}>No Active Attendance Session</Text>
            <Text style={styles.emptyPassSubtitle}>
              There are no active events requiring attendance right now. Today's pass will activate automatically once an event begins.
            </Text>
          </View>
        </View>
      )}

      {/* Recent & Upcoming Events List */}
      <View style={styles.eventsHeader}>
        <Ionicons name="layers-outline" size={18} color="#063B5C" style={{ marginRight: 6 }} />
        <Text style={styles.eventsTitle}>Events & Campus Activities</Text>
      </View>

      <View style={styles.eventList}>
        {events.length === 0 ? (
          <View style={styles.emptyEventCard}>
            <Text style={styles.emptyEventText}>No scheduled events found.</Text>
          </View>
        ) : (
          events.map((event) => {
            const isActive = event.status === 'active';
            const isUpcoming = event.status === 'upcoming' || event.status === 'draft';
            const audience = event.target_audience_label || 'All BSIS Students';
            const isAllAudience = !event.target_audience_label || event.target_audience_label === 'All BSIS Students';

            return (
              <TouchableOpacity 
                key={event.id} 
                style={[
                  styles.eventCard,
                  isActive && styles.eventCardActive,
                  isUpcoming && styles.eventCardUpcoming,
                  !isActive && !isUpcoming && styles.eventCardCompleted,
                ]}
                activeOpacity={0.85}
                onPress={() => navigation.navigate('EventDetails', { event, eventId: event.id })}
              >
                <View style={styles.eventCardHeader}>
                  <View style={isActive ? styles.badgeActive : (isUpcoming ? styles.badgeUpcoming : styles.badgeCompleted)}>
                    <Ionicons 
                      name={isActive ? "radio" : (isUpcoming ? "time-outline" : "checkmark-circle-outline")} 
                      size={11} 
                      color="#FFFFFF" 
                    />
                    <Text style={styles.badgeTextWhite}>
                      {isActive ? 'ACTIVE' : (isUpcoming ? 'UPCOMING' : 'COMPLETED')}
                    </Text>
                  </View>

                  <View style={[styles.audienceBadge, isAllAudience ? styles.audienceBadgeAll : styles.audienceBadgeSpecific]}>
                    <Ionicons name="people" size={10} color={isAllAudience ? "#475569" : "#0284C7"} style={{ marginRight: 3 }} />
                    <Text style={[styles.audienceBadgeText, isAllAudience ? styles.audienceTextAll : styles.audienceTextSpecific]} numberOfLines={1}>
                      {audience}
                    </Text>
                  </View>
                </View>

                <Text style={styles.eventTitle}>{event.title}</Text>
                {event.description ? (
                  <Text style={styles.eventDesc} numberOfLines={2}>{event.description}</Text>
                ) : null}

                <View style={styles.eventMetaRow}>
                  <View style={styles.eventMetaItem}>
                    <Ionicons name="location-outline" size={13} color="#6B7A86" />
                    <Text style={styles.eventMetaText} numberOfLines={1}>
                      {event.venue_name} ({event.allowed_radius_meters}m)
                    </Text>
                  </View>
                  <View style={styles.eventMetaItem}>
                    <Ionicons name="time-outline" size={13} color="#6B7A86" />
                    <Text style={styles.eventMetaText}>
                      {formatDisplayDateTime(event.start_time)}
                    </Text>
                  </View>
                </View>

                <View style={styles.cardFooter}>
                  <View style={styles.sessionBadge}>
                    <Text style={styles.sessionBadgeText}>
                      {event.session_type === 'whole_day' ? 'Whole Day (4 Scans)' : 'Single Session (2 Scans)'}
                    </Text>
                  </View>
                  <View style={{flexDirection: 'row', alignItems: 'center'}}>
                    <Text style={styles.viewDetailsText}>View Info</Text>
                    <Ionicons name="chevron-forward" size={13} color="#0284C7" />
                  </View>
                </View>
              </TouchableOpacity>
            );
          })
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F9FC',
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 40,
  },
  centerContainer: {
    flex: 1,
    backgroundColor: '#F5F9FC',
    justifyContent: 'center',
    alignItems: 'center',
  },
  profileCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#EEF4F8',
    shadowColor: 'rgba(6, 59, 92, 0.05)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 8,
    elevation: 2,
  },
  profileHeader: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  avatar: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#063B5C',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  profileInfo: {
    flex: 1,
  },
  nameText: {
    color: '#063B5C',
    fontSize: 16,
    fontWeight: 'bold',
  },
  idText: {
    color: '#6B7A86',
    fontSize: 12,
    marginTop: 2,
  },
  badgeContainer: {
    marginTop: 5,
    backgroundColor: '#E8F6F1',
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 50,
    flexDirection: 'row',
    alignItems: 'center',
  },
  badgeText: {
    color: '#168A63',
    fontSize: 11,
    fontWeight: 'bold',
  },
  clearanceCard: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 12,
    paddingVertical: 10,
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
    fontSize: 12.5,
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
    fontSize: 11,
    color: '#475569',
    marginTop: 1,
  },
  scanBannerCard: {
    backgroundColor: '#063B5C',
    borderRadius: 14,
    padding: 18,
    marginBottom: 16,
    alignItems: 'center',
    shadowColor: 'rgba(6, 59, 92, 0.15)',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 12,
    elevation: 4,
  },
  scanBannerTitle: {
    color: '#FFFFFF',
    fontSize: 17,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  scanBannerSub: {
    color: '#EEF4F8',
    fontSize: 12.5,
    textAlign: 'center',
    marginBottom: 14,
  },
  scanButton: {
    backgroundColor: '#35C4E8',
    paddingVertical: 11,
    paddingHorizontal: 22,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  scanButtonText: {
    color: '#063B5C',
    fontSize: 13.5,
    fontWeight: 'bold',
  },
  timelineCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 16,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#EEF4F8',
    shadowColor: 'rgba(6, 59, 92, 0.05)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 8,
    elevation: 2,
  },
  timelineHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#F0F4F8',
    paddingBottom: 10,
  },
  timelineTitle: {
    color: '#063B5C',
    fontSize: 14.5,
    fontWeight: 'bold',
  },
  timelineSubtitle: {
    color: '#64748B',
    fontSize: 11.5,
    marginTop: 2,
  },
  badgeInfo: {
    backgroundColor: '#E6F4FE',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  badgeInfoText: {
    color: '#063B5C',
    fontSize: 10.5,
    fontWeight: 'bold',
  },
  timelineSteps: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 6,
    paddingVertical: 6,
  },
  timelineStep: {
    alignItems: 'center',
    flex: 1,
  },
  stepDot: {
    width: 26,
    height: 26,
    borderRadius: 13,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 6,
  },
  stepDotDone: {
    backgroundColor: '#16A34A',
  },
  stepDotActive: {
    backgroundColor: '#E0F2FE',
    borderWidth: 2,
    borderColor: '#0284C7',
  },
  innerPulseDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#0284C7',
  },
  stepDotPending: {
    width: 26,
    height: 26,
    borderRadius: 13,
    borderWidth: 2,
    borderColor: '#CBD5E1',
    backgroundColor: '#F8FAFC',
    marginBottom: 6,
  },
  stepLine: {
    flex: 0.7,
    height: 3,
    marginBottom: 24,
  },
  stepLineDone: {
    backgroundColor: '#16A34A',
  },
  stepLinePending: {
    backgroundColor: '#E2E8F0',
  },
  stepInfo: {
    alignItems: 'center',
  },
  stepLabel: {
    fontSize: 11.5,
    fontWeight: 'bold',
    color: '#64748B',
  },
  stepLabelDone: {
    color: '#16A34A',
  },
  stepTime: {
    fontSize: 10.5,
    color: '#16A34A',
    fontWeight: 'bold',
    marginTop: 2,
  },
  stepTimePending: {
    fontSize: 10.5,
    color: '#94A3B8',
    fontWeight: '500',
    marginTop: 2,
  },
  emptyPassCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 16,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#EEF4F8',
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 6,
    elevation: 2,
  },
  emptyPassIconWrapper: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#E6F4FE',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  emptyPassTextWrapper: {
    flex: 1,
  },
  emptyPassTitle: {
    fontSize: 13.5,
    fontWeight: 'bold',
    color: '#063B5C',
    marginBottom: 3,
  },
  emptyPassSubtitle: {
    fontSize: 11.5,
    color: '#64748B',
    lineHeight: 16,
  },
  eventsHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    marginTop: 4,
  },
  eventsTitle: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#063B5C',
  },
  eventList: {
    gap: 10,
  },
  emptyEventCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 20,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  emptyEventText: {
    color: '#94A3B8',
    fontSize: 13,
  },
  eventCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 14,
    borderWidth: 1,
    borderColor: '#EEF4F8',
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 6,
    elevation: 2,
    borderLeftWidth: 4,
  },
  eventCardActive: {
    borderLeftColor: '#0284C7',
  },
  eventCardUpcoming: {
    borderLeftColor: '#F97316',
  },
  eventCardCompleted: {
    borderLeftColor: '#16A34A',
  },
  eventCardHeader: {
    flexDirection: 'row',
    marginBottom: 8,
  },
  badgeActive: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#0284C7',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 5,
  },
  badgeUpcoming: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F97316',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 5,
  },
  badgeCompleted: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#16A34A',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 5,
  },
  badgeTextWhite: {
    color: '#FFFFFF',
    fontSize: 10,
    fontWeight: 'bold',
    marginLeft: 3,
  },
  audienceBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 5,
    marginLeft: 6,
    maxWidth: 160,
  },
  audienceBadgeAll: {
    backgroundColor: '#F1F5F9',
  },
  audienceBadgeSpecific: {
    backgroundColor: '#E0F2FE',
  },
  audienceBadgeText: {
    fontSize: 10,
    fontWeight: 'bold',
  },
  audienceTextAll: {
    color: '#475569',
  },
  audienceTextSpecific: {
    color: '#0284C7',
  },
  eventTitle: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#042C46',
    flex: 1,
    marginRight: 6,
  },
  eventDesc: {
    fontSize: 12,
    color: '#6B7A86',
    marginBottom: 3,
  },
  eventMetaRow: {
    marginTop: 8,
    gap: 4,
  },
  eventMetaItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  eventMetaText: {
    fontSize: 11.5,
    color: '#6B7A86',
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 10,
    paddingTop: 8,
    borderTopWidth: 1,
    borderTopColor: '#F0F4F8',
  },
  sessionBadge: {
    backgroundColor: '#E6F4FE',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  sessionBadgeText: {
    color: '#063B5C',
    fontSize: 10.5,
    fontWeight: '600',
  },
  viewDetailsText: {
    fontSize: 11.5,
    fontWeight: 'bold',
    color: '#0284C7',
    marginRight: 2,
  },
});
