import React, { useState, useEffect, useCallback, useRef } from 'react';
import { 
  StyleSheet, 
  Text, 
  View, 
  Alert, 
  TouchableOpacity, 
  ScrollView, 
  ActivityIndicator,
  AppState,
  Vibration,
  Linking
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import * as Location from 'expo-location';
import * as Haptics from 'expo-haptics';
import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import * as Device from 'expo-device';
import { Ionicons } from '@expo/vector-icons';
import { useIsFocused, useFocusEffect } from '@react-navigation/native';

import { API_URL } from '../config';
import { useToast } from '../context/ToastContext';

// Haversine Distance Calculator (in meters)
function calculateDistanceMeters(lat1, lon1, lat2, lon2) {
  if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) return null;
  const R = 6371000;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return Math.round(R * c);
}

export default function ScannerScreen({ navigation }) {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);
  const [torch, setTorch] = useState(false);
  const [gpsEnabled, setGpsEnabled] = useState(false);
  const [locationPermission, setLocationPermission] = useState(null);
  const [activeEvent, setActiveEvent] = useState(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [userCoords, setUserCoords] = useState(null);
  const [currentDistance, setCurrentDistance] = useState(null);
  const [cameraReady, setCameraReady] = useState(false);
  const [cameraKey, setCameraKey] = useState(0);
  const [cameraFacing, setCameraFacing] = useState('back');
  const [isCameraActive, setIsCameraActive] = useState(true);
  const [isReloading, setIsReloading] = useState(false);

  const cameraReadyTimerRef = useRef(null);
  const activeEventRef = useRef(activeEvent);

  const isFocused = useIsFocused();
  const { showToast } = useToast();

  useEffect(() => {
    activeEventRef.current = activeEvent;
  }, [activeEvent]);

  // Haptic feedback engine
  const triggerHaptic = async (type = 'success') => {
    try {
      if (type === 'success') {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      } else if (type === 'error') {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      } else if (type === 'warning') {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
      } else {
        await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      }
    } catch (e) {
      if (type === 'success') Vibration.vibrate([0, 70, 50, 90]);
      else if (type === 'error') Vibration.vibrate([0, 100, 70, 120]);
      else Vibration.vibrate(50);
    }
  };

  // Request location permission interactively from the user
  const requestLocationAccess = async () => {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      const isGranted = status === 'granted';
      setLocationPermission(isGranted);
      
      const hasServices = await Location.hasServicesEnabledAsync();
      setGpsEnabled(hasServices);

      if (isGranted && hasServices) {
        let loc = await Location.getLastKnownPositionAsync();
        if (!loc || !loc.coords) {
          loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
        }
        if (loc && loc.coords) {
          setUserCoords(loc.coords);
          const currentEv = activeEventRef.current;
          if (currentEv && currentEv.latitude && currentEv.longitude) {
            const dist = calculateDistanceMeters(
              loc.coords.latitude,
              loc.coords.longitude,
              parseFloat(currentEv.latitude),
              parseFloat(currentEv.longitude)
            );
            setCurrentDistance(dist);
          }
        }
      }
    } catch (e) {
      console.log('Location permission request error:', e.message);
    }
  };

  // Check hardware GPS sensor state & permissions in real-time
  const checkHardwareStatus = async () => {
    try {
      const { status } = await Location.getForegroundPermissionsAsync();
      if (status === 'undetermined') {
        // Auto-prompt user for location permission
        await requestLocationAccess();
        return;
      }

      const isGranted = status === 'granted';
      setLocationPermission(isGranted);
      
      const hasServices = await Location.hasServicesEnabledAsync();
      setGpsEnabled(hasServices);

      if (isGranted && hasServices) {
        let loc = await Location.getLastKnownPositionAsync();
        if (!loc || !loc.coords) {
          loc = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
        }
        if (loc && loc.coords) {
          setUserCoords(loc.coords);
          const currentEv = activeEventRef.current;
          if (currentEv && currentEv.latitude && currentEv.longitude) {
            const dist = calculateDistanceMeters(
              loc.coords.latitude,
              loc.coords.longitude,
              parseFloat(currentEv.latitude),
              parseFloat(currentEv.longitude)
            );
            setCurrentDistance(dist);
          }
        }
      }
    } catch (e) {
      console.log('GPS polling check:', e.message);
    }
  };

  // Initial check on mount + AppState listener to release camera hardware on background/lock
  useEffect(() => {
    checkHardwareStatus();

    const subscription = AppState.addEventListener('change', nextAppState => {
      if (nextAppState === 'active') {
        checkHardwareStatus();
        // Remount fresh instance when user returns to the app
        setCameraKey(prev => prev + 1);
        setIsCameraActive(true);
      } else {
        // App backgrounded, minimized, or phone locked:
        // Release physical camera sensor immediately so OS power manager does not kill the stream
        setIsCameraActive(false);
        setCameraReady(false);
        setTorch(false);
      }
    });

    return () => subscription.remove();
  }, []);

  // Real-time live polling while scanner is focused + fallback camera ready timer
  useEffect(() => {
    let interval;

    if (isFocused) {
      setScanned(false);
      setIsProcessing(false);
      setIsCameraActive(true);
      checkHardwareStatus();

      // Clear any prior timer
      if (cameraReadyTimerRef.current) {
        clearTimeout(cameraReadyTimerRef.current);
      }
      
      // Auto-ready safety timer (if native onCameraReady doesn't fire within 600ms)
      cameraReadyTimerRef.current = setTimeout(() => {
        setCameraReady(true);
        setIsReloading(false);
      }, 600);

      interval = setInterval(() => {
        checkHardwareStatus();
      }, 2500);
    } else {
      if (cameraReadyTimerRef.current) {
        clearTimeout(cameraReadyTimerRef.current);
      }
      setIsCameraActive(false);
      setCameraReady(false);
      setIsReloading(false);
      setTorch(false);
    }

    return () => {
      if (interval) clearInterval(interval);
      if (cameraReadyTimerRef.current) clearTimeout(cameraReadyTimerRef.current);
    };
  }, [isFocused, cameraKey]);

  // Fast manual camera reset function if hardware stream stalls
  const reloadCamera = () => {
    if (isReloading) return;
    triggerHaptic('light');
    setIsReloading(true);
    setCameraReady(false);
    setIsCameraActive(false); // Unmount from DOM/Native tree so Android releases hardware

    if (cameraReadyTimerRef.current) {
      clearTimeout(cameraReadyTimerRef.current);
    }

    setTimeout(() => {
      setCameraKey(prev => prev + 1);
      setIsCameraActive(true); // Remount fresh
      
      // Auto-ready timer for reload
      cameraReadyTimerRef.current = setTimeout(() => {
        setCameraReady(true);
        setIsReloading(false);
      }, 600);
    }, 180);
  };

  // Flip camera (Back / Front) with clean hardware unmount delay to avoid sensor collision
  const toggleCameraFacing = () => {
    if (isReloading) return;
    triggerHaptic('light');
    setTorch(false);
    setIsReloading(true);
    setCameraReady(false);
    setIsCameraActive(false); // Unmount so the active sensor closes cleanly before opening the other

    if (cameraReadyTimerRef.current) {
      clearTimeout(cameraReadyTimerRef.current);
    }

    setTimeout(() => {
      setCameraFacing(prev => (prev === 'back' ? 'front' : 'back'));
      setCameraKey(prev => prev + 1);
      setIsCameraActive(true); // Remount the new sensor fresh
      
      cameraReadyTimerRef.current = setTimeout(() => {
        setCameraReady(true);
        setIsReloading(false);
      }, 600);
    }, 180);
  };

  // Fetch current active event for context banner
  const fetchCurrentEventContext = async () => {
    try {
      const token = await SecureStore.getItemAsync('user_token');
      if (!token) return;
      const res = await axios.get(`${API_URL}/events?status=active&per_page=1`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      if (res.data.success && res.data.data?.data?.length > 0) {
        const ev = res.data.data.data[0];
        setActiveEvent(ev);
        if (userCoords && ev.latitude && ev.longitude) {
          const dist = calculateDistanceMeters(
            userCoords.latitude,
            userCoords.longitude,
            parseFloat(ev.latitude),
            parseFloat(ev.longitude)
          );
          setCurrentDistance(dist);
        }
      } else {
        setActiveEvent(null);
        setCurrentDistance(null);
      }
    } catch (e) {
      console.log('Active event error:', e.message);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchCurrentEventContext();
      checkHardwareStatus();
    }, [])
  );

  if (!permission) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#063B5C" />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.permissionContainer}>
        <View style={styles.permIconCircle}>
          <Ionicons name="camera" size={48} color="#063B5C" />
        </View>
        <Text style={styles.permTitle}>Camera Access Required</Text>
        <Text style={styles.permSubtitle}>
          The attendance system requires camera permission to scan live dynamic event QR codes.
        </Text>
        <TouchableOpacity style={styles.primaryButton} onPress={requestPermission}>
          <Text style={styles.primaryButtonText}>Grant Camera Permission</Text>
        </TouchableOpacity>
        <TouchableOpacity 
          style={[styles.primaryButton, { backgroundColor: '#E2E8F0', marginTop: 12 }]} 
          onPress={() => Linking.openSettings()}
        >
          <Text style={[styles.primaryButtonText, { color: '#063B5C' }]}>Open Phone App Settings</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (locationPermission === false) {
    return (
      <View style={styles.permissionContainer}>
        <View style={styles.permIconCircle}>
          <Ionicons name="location" size={48} color="#063B5C" />
        </View>
        <Text style={styles.permTitle}>Location Access Required</Text>
        <Text style={styles.permSubtitle}>
          Location access is mandatory to verify that your attendance is physically recorded within the venue perimeter.
        </Text>
        <TouchableOpacity style={styles.primaryButton} onPress={requestLocationAccess}>
          <Text style={styles.primaryButtonText}>Grant Location Permission</Text>
        </TouchableOpacity>
        <TouchableOpacity 
          style={[styles.primaryButton, { backgroundColor: '#E2E8F0', marginTop: 12 }]} 
          onPress={() => Linking.openSettings()}
        >
          <Text style={[styles.primaryButtonText, { color: '#063B5C' }]}>Open Phone Settings</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const handleBarCodeScanned = async ({ type, data }) => {
    if (scanned || isProcessing) return;
    setScanned(true);
    setIsProcessing(true);

    try {
      // 1. Hardware GPS Sensor State Check
      const hasServices = await Location.hasServicesEnabledAsync();
      setGpsEnabled(hasServices);
      if (!hasServices) {
        await triggerHaptic('warning');
        showToast('GPS Disabled 📍 Please turn on Location in phone settings.', 'warning');
        setScanned(false);
        setIsProcessing(false);
        return;
      }

      // Fast tactile feedback on camera capture
      try {
        await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      } catch(e) {}

      // 2. Get Fast Native GPS Coordinates (Fast-path cached or balanced accuracy with safe timeout)
      let location = null;
      try {
        location = await Location.getLastKnownPositionAsync({ maxAge: 60000 });
      } catch(e) {}

      if (!location || !location.coords) {
        try {
          const gpsPromise = Location.getCurrentPositionAsync({
            accuracy: Location.Accuracy.Balanced,
          });
          const timeoutPromise = new Promise((_, reject) =>
            setTimeout(() => reject(new Error('GPS signal timeout')), 5000)
          );
          location = await Promise.race([gpsPromise, timeoutPromise]);
        } catch(gpsErr) {
          try {
            location = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Lowest });
          } catch(e) {}
        }
      }

      if (!location || !location.coords) {
        await triggerHaptic('error');
        showToast('Unable to detect GPS coordinates. Please ensure Location is enabled.', 'error', 5000, 'Location Error 📍');
        setScanned(false);
        setIsProcessing(false);
        return;
      }

      // 3. NATIVE ROOT / JAILBREAK INTEGRITY CHECK
      const isRooted = await Device.isRootedExperimentalAsync();
      if (isRooted) {
        await triggerHaptic('error');
        showToast('Rooted OS Detected 🚨 Scanning disabled for security.', 'error');
        setScanned(false);
        setIsProcessing(false);
        return;
      }

      // 4. THE ULTIMATE NATIVE FAKE GPS / MOCK LOCATION CHECK
      if (location.mocked) {
        await triggerHaptic('error');
        showToast('Fake GPS Detected 🚨 Please disable mock locations.', 'error');
        setScanned(false);
        setIsProcessing(false);
        return;
      }

      // 5. Retrieve Device Credential (Hardware Binding)
      const token = await SecureStore.getItemAsync('user_token');
      let deviceCredential = await SecureStore.getItemAsync('device_credential');
      
      if (!deviceCredential) {
        const userDataStr = await SecureStore.getItemAsync('user_data');
        if (userDataStr) {
          try {
            const parsed = JSON.parse(userDataStr);
            deviceCredential = parsed?.device_credential;
          } catch(e) {}
        }
      }

      if (!deviceCredential && token) {
        try {
          const meRes = await axios.get(`${API_URL}/auth/me`, {
            headers: { Authorization: `Bearer ${token}` }
          });
          if (meRes.data.success && meRes.data.data?.device_credential) {
            deviceCredential = meRes.data.data.device_credential;
            await SecureStore.setItemAsync('device_credential', deviceCredential);
          }
        } catch(e) {}
      }
      
      const payload = {
        qr_token: data,
        device_credential: deviceCredential || '',
        latitude: location.coords.latitude,
        longitude: location.coords.longitude,
        timestamp: new Date().getTime()
      };

      const response = await axios.post(`${API_URL}/attendance/scan`, payload, {
        headers: { Authorization: `Bearer ${token}` }
      });

      if (response.data.success) {
        await triggerHaptic('success');
        const d = response.data.data || {};
        
        let slotName = d.scan_type || d.slot || 'Attendance';
        if (slotName.toLowerCase() === 'am_in') slotName = 'AM Time-In';
        else if (slotName.toLowerCase() === 'am_out') slotName = 'AM Time-Out';
        else if (slotName.toLowerCase() === 'pm_in') slotName = 'PM Time-In';
        else if (slotName.toLowerCase() === 'pm_out') slotName = 'PM Time-Out';

        // Extract the exact scan timestamp for this specific transaction
        let timeStr = d.recorded_at || d.formatted_scan_time || '';
        if (!timeStr && d.attendance) {
          const a = d.attendance;
          let rawTime = null;
          if (d.slot === 'checkout' || slotName.toLowerCase().includes('out')) {
            rawTime = a.formatted_checkout_time || a.formatted_pm_out || a.formatted_am_out;
          } else if (d.slot === 'am_in') {
            rawTime = a.formatted_am_in || a.formatted_time;
          } else if (d.slot === 'am_out') {
            rawTime = a.formatted_am_out;
          } else if (d.slot === 'pm_in') {
            rawTime = a.formatted_pm_in;
          } else if (d.slot === 'pm_out') {
            rawTime = a.formatted_pm_out || a.formatted_checkout_time;
          } else {
            rawTime = a.formatted_time || a.formatted_am_in;
          }

          if (rawTime) {
            const ampmMatch = String(rawTime).match(/(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)/i);
            if (ampmMatch) {
              timeStr = `${parseInt(ampmMatch[1], 10)}:${ampmMatch[2]} ${ampmMatch[3].toUpperCase()}`;
            }
          }
        }
        if (!timeStr) {
          const now = new Date();
          let hours = now.getHours();
          const minutes = String(now.getMinutes()).padStart(2, '0');
          const ampm = hours >= 12 ? 'PM' : 'AM';
          hours = hours % 12 || 12;
          timeStr = `${hours}:${minutes} ${ampm}`;
        }

        const eventTitle = activeEvent?.title || d.event_title || d.event?.title || 'Campus Event';
        const isLate = d.slot_status === 'late';
        const statusText = isLate ? 'Verified (Late Penalty)' : 'Verified (On-Time)';

        showToast(
          `Recorded ${slotName} at ${timeStr} successfully!`,
          'success',
          null,
          'Attendance Confirmed! 🎉',
          {
            eventTitle: eventTitle,
            sessionSlot: slotName,
            scanTime: timeStr,
            status: statusText,
          }
        );
        setScanned(false);
        setIsProcessing(false);
        navigation.navigate('Home');
      }

    } catch (error) {
      await triggerHaptic('error');
      console.log('Attendance Scan Error:', error.response?.data || error.message);
      
      let msg = 'Failed to record attendance. Please try again.';
      if (error.response?.data) {
        const d = error.response.data;
        if (d.message) {
          msg = d.message;
        } else if (d.error) {
          msg = d.error;
        } else if (d.errors && typeof d.errors === 'object') {
          const firstKey = Object.keys(d.errors)[0];
          msg = Array.isArray(d.errors[firstKey]) ? d.errors[firstKey][0] : String(d.errors[firstKey]);
        }
      } else if (error.message && !error.message.includes('code 422')) {
        msg = error.message;
      }
      
      showToast(msg, 'error', 5000, 'Scan Unsuccessful ⚠️');
      setScanned(false);
      setIsProcessing(false);
    }
  };

  const allowedRadius = activeEvent?.allowed_radius_meters || 50;
  const isWithinGeofence = currentDistance != null ? currentDistance <= allowedRadius : true;

  return (
    <ScrollView 
      style={styles.container}
      contentContainerStyle={styles.scrollContent}
      showsVerticalScrollIndicator={false}
    >
      {/* Active Event Context Banner */}
      {activeEvent ? (
        <View style={styles.activeEventCard}>
          <View style={styles.liveHeader}>
            <View style={styles.liveIndicator}>
              <View style={styles.liveDot} />
              <Text style={styles.liveText}>LIVE SESSION</Text>
            </View>
            <Text style={styles.sessionTypeBadge}>
              {activeEvent.session_type === 'whole_day' ? '4 SCANS' : '2 SCANS'}
            </Text>
          </View>
          <Text style={styles.liveEventTitle}>{activeEvent.title}</Text>
          <View style={styles.venueRow}>
            <Ionicons name="location-sharp" size={14} color="#35C4E8" style={{ marginRight: 4 }} />
            <Text style={styles.venueText}>
              {activeEvent.venue_name} ({activeEvent.allowed_radius_meters}m Perimeter)
            </Text>
          </View>
        </View>
      ) : (
        <View style={styles.noActiveEventCard}>
          <Ionicons name="information-circle-outline" size={20} color="#64748B" style={{ marginRight: 8 }} />
          <Text style={styles.noActiveEventText}>No active attendance session currently running.</Text>
        </View>
      )}

      {/* Live Distance Proximity Radar Pill */}
      {activeEvent && (
        <View style={[
          styles.radarCard,
          currentDistance != null && (isWithinGeofence ? styles.radarCardInRange : styles.radarCardOutOfRange)
        ]}>
          <Ionicons 
            name={currentDistance == null ? "radio-outline" : (isWithinGeofence ? "navigate-circle" : "alert-circle")} 
            size={18} 
            color={currentDistance == null ? "#0284C7" : (isWithinGeofence ? "#16A34A" : "#DC2626")} 
            style={{ marginRight: 6 }} 
          />
          <Text style={[
            styles.radarText,
            currentDistance != null && (isWithinGeofence ? styles.radarTextInRange : styles.radarTextOutOfRange)
          ]}>
            {currentDistance == null 
              ? `Acquiring GPS Distance (${allowedRadius}m Perimeter)...`
              : (isWithinGeofence 
                  ? `Within Range: ${currentDistance}m away (Max ${allowedRadius}m) 🟢` 
                  : `Out of Range: ${currentDistance}m away (Must be within ${allowedRadius}m) 🔴`)}
          </Text>
        </View>
      )}

      {/* Hardware Status Real-Time Indicators */}
      <View style={styles.statusRow}>
        <View style={[styles.statusBadge, gpsEnabled ? styles.statusBadgeGreen : styles.statusBadgeRed]}>
          <Ionicons 
            name={gpsEnabled ? "checkmark-circle" : "close-circle"} 
            size={14} 
            color={gpsEnabled ? "#168A63" : "#DC2626"} 
            style={{ marginRight: 4 }} 
          />
          <Text style={[styles.statusBadgeText, { color: gpsEnabled ? "#168A63" : "#DC2626" }]}>
            {gpsEnabled ? "GPS Active" : "GPS Turned Off"}
          </Text>
        </View>

        <View style={[styles.statusBadge, (cameraReady && isCameraActive && !isReloading) ? styles.statusBadgeGreen : styles.statusBadgeBlue]}>
          <Ionicons 
            name={(cameraReady && isCameraActive && !isReloading) ? "checkmark-circle" : "hourglass-outline"} 
            size={14} 
            color={(cameraReady && isCameraActive && !isReloading) ? "#168A63" : "#0284C7"} 
            style={{ marginRight: 4 }} 
          />
          <Text style={[styles.statusBadgeText, { color: (cameraReady && isCameraActive && !isReloading) ? "#168A63" : "#0284C7" }]}>
            {(cameraReady && isCameraActive && !isReloading) ? "Camera Ready" : "Initializing"}
          </Text>
        </View>

        <View style={[styles.statusBadge, styles.statusBadgeBlue]}>
          <Ionicons name="shield-checkmark" size={14} color="#063B5C" style={{ marginRight: 4 }} />
          <Text style={[styles.statusBadgeText, { color: "#063B5C" }]}>Anti-Spoofing</Text>
        </View>
      </View>

      {/* Camera Viewport Container */}
      <View style={styles.scannerWrapper}>
        <View style={styles.cameraFrame}>
          {isFocused && isCameraActive && (
            <CameraView
              key={`camera-feed-${cameraKey}-${cameraFacing}`}
              style={[StyleSheet.absoluteFillObject, styles.cameraView]}
              facing={cameraFacing}
              enableTorch={cameraFacing === 'back' ? torch : false}
              onCameraReady={() => {
                if (cameraReadyTimerRef.current) {
                  clearTimeout(cameraReadyTimerRef.current);
                }
                setCameraReady(true);
                setIsReloading(false);
              }}
              onMountError={(err) => {
                if (cameraReadyTimerRef.current) {
                  clearTimeout(cameraReadyTimerRef.current);
                }
                console.warn('Camera Mount Error:', err);
                setIsReloading(false);
                setCameraReady(true);
                showToast('Camera error. Tap reload or switch camera.', 'error');
              }}
              onBarcodeScanned={scanned || isProcessing ? undefined : (result) => {
                setCameraReady(true);
                handleBarCodeScanned(result);
              }}
              barcodeScannerSettings={{
                barcodeTypes: ["qr"],
              }}
            />
          )}

          {/* Clean Unified Camera Loading Overlay */}
          {(!cameraReady || !isCameraActive || isReloading) && isFocused && (
            <View style={styles.cameraLoadingOverlay} pointerEvents="none">
              <ActivityIndicator size="large" color="#35C4E8" />
              <Text style={styles.cameraLoadingText}>Starting Camera Feed...</Text>
            </View>
          )}

          {/* Sleek Floating Verification Pill (Non-blocking) */}
          {isProcessing && (
            <View style={styles.processingPill}>
              <ActivityIndicator size="small" color="#35C4E8" style={{ marginRight: 8 }} />
              <Text style={styles.processingPillText}>Verifying GPS & Token...</Text>
            </View>
          )}

        </View>

        {/* Camera Action Buttons (Outside Viewfinder - No Blocking) */}
        <View style={styles.externalControlsBar}>
          <TouchableOpacity 
            style={[styles.externalControlBtn, isReloading && { opacity: 0.6 }]} 
            onPress={reloadCamera}
            disabled={isReloading}
            activeOpacity={0.8}
          >
            {isReloading ? (
              <ActivityIndicator size="small" color="#0284C7" />
            ) : (
              <Ionicons name="refresh" size={16} color="#0284C7" />
            )}
            <Text style={styles.externalControlText}>Reload</Text>
          </TouchableOpacity>

          <TouchableOpacity 
            style={styles.externalControlBtn} 
            onPress={toggleCameraFacing}
            activeOpacity={0.8}
          >
            <Ionicons name="camera-reverse-outline" size={17} color="#0284C7" />
            <Text style={styles.externalControlText}>Flip Camera</Text>
          </TouchableOpacity>

          <TouchableOpacity 
            style={[styles.externalControlBtn, torch && styles.externalControlBtnActive, cameraFacing !== 'back' && { opacity: 0.5 }]} 
            onPress={() => {
              if (cameraFacing !== 'back') {
                showToast('Flashlight is only supported on Back Camera', 'info');
                return;
              }
              triggerHaptic('light');
              setTorch(!torch);
            }}
            activeOpacity={0.8}
          >
            <Ionicons name={torch ? "flashlight" : "flashlight-outline"} size={16} color={torch ? "#FFF" : "#0284C7"} />
            <Text style={[styles.externalControlText, torch && { color: '#FFF' }]}>
              {torch ? 'Flash ON' : 'Flashlight'}
            </Text>
          </TouchableOpacity>
        </View>

        {/* Rescan Button */}
        {scanned && !isProcessing && (
          <TouchableOpacity 
            style={styles.rescanBtn} 
            onPress={() => {
              triggerHaptic('light');
              setScanned(false); 
              setIsProcessing(false); 
            }}
          >
            <Ionicons name="refresh-outline" size={20} color="#fff" style={{ marginRight: 8 }} />
            <Text style={styles.rescanText}>Tap to Scan Again</Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Instructions & QoL Tips */}
      <View style={styles.tipsCard}>
        <Text style={styles.tipsHeading}>
          <Ionicons name="bulb-outline" size={15} color="#063B5C" /> Scanning Instructions
        </Text>
        <View style={styles.tipRow}>
          <Ionicons name="checkmark-circle" size={14} color="#168A63" style={{ marginTop: 2, marginRight: 8 }} />
          <Text style={styles.tipText}>Hold your camera steady in front of the active projected QR code.</Text>
        </View>
        <View style={styles.tipRow}>
          <Ionicons name="checkmark-circle" size={14} color="#168A63" style={{ marginTop: 2, marginRight: 8 }} />
          <Text style={styles.tipText}>Keep phone GPS turned ON for instantaneous physical location verification.</Text>
        </View>
        <View style={styles.tipRow}>
          <Ionicons name="checkmark-circle" size={14} color="#168A63" style={{ marginTop: 2, marginRight: 8 }} />
          <Text style={styles.tipText}>
            Dynamic QR codes rotate continuously in real-time as configured by the event administrator for proxy prevention.
          </Text>
        </View>
      </View>

      {/* Camera Guide & Troubleshooting Card */}
      <View style={styles.troubleshootCard}>
        <Text style={styles.troubleshootTitle}>
          <Ionicons name="help-circle-outline" size={15} color="#063B5C" /> Camera Guide & Troubleshooting
        </Text>
        <Text style={styles.troubleshootDesc}>
          If the camera feed shows a black screen or stalls after prolonged use:
        </Text>
        <View style={styles.troubleshootTipRow}>
          <Ionicons name="ellipse" size={5} color="#0284C7" style={{ marginTop: 6, marginRight: 8 }} />
          <Text style={styles.troubleshootTipText}>
            Tap <Text style={{ fontWeight: 'bold' }}>Reset Camera</Text> below or the refresh icon in the viewfinder to reboot the camera feed.
          </Text>
        </View>
        <View style={styles.troubleshootTipRow}>
          <Ionicons name="ellipse" size={5} color="#0284C7" style={{ marginTop: 6, marginRight: 8 }} />
          <Text style={styles.troubleshootTipText}>
            Tap the camera flip icon to switch sensors (Front ⇄ Back) to force the system driver to re-bind.
          </Text>
        </View>
        <View style={styles.troubleshootTipRow}>
          <Ionicons name="ellipse" size={5} color="#0284C7" style={{ marginTop: 6, marginRight: 8 }} />
          <Text style={styles.troubleshootTipText}>
            If permissions were revoked, tap <Text style={{ fontWeight: 'bold' }}>App Settings</Text> to ensure Camera & Location access are allowed.
          </Text>
        </View>
        <View style={styles.troubleshootTipRow}>
          <Ionicons name="ellipse" size={5} color="#0284C7" style={{ marginTop: 6, marginRight: 8 }} />
          <Text style={styles.troubleshootTipText}>
            If none of the above works, <Text style={{ fontWeight: 'bold' }}>try restarting your phone (iOS / Android)</Text> to completely clear any stuck hardware locks.
          </Text>
        </View>
        <View style={styles.troubleshootActions}>
          <TouchableOpacity style={styles.troubleshootBtn} onPress={reloadCamera} activeOpacity={0.75}>
            <Ionicons name="refresh-circle" size={16} color="#0284C7" style={{ marginRight: 5 }} />
            <Text style={styles.troubleshootBtnText}>Reset Camera</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.troubleshootBtn} onPress={() => Linking.openSettings()} activeOpacity={0.75}>
            <Ionicons name="settings-outline" size={15} color="#0284C7" style={{ marginRight: 5 }} />
            <Text style={styles.troubleshootBtnText}>App Settings</Text>
          </TouchableOpacity>
        </View>
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
    alignItems: 'center',
    paddingBottom: 40,
  },
  centerContainer: {
    flex: 1,
    backgroundColor: '#F5F9FC',
    justifyContent: 'center',
    alignItems: 'center',
  },
  permissionContainer: {
    flex: 1,
    backgroundColor: '#F5F9FC',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  permIconCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#E6F4FE',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  permTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#063B5C',
    marginBottom: 8,
  },
  permSubtitle: {
    fontSize: 14,
    color: '#64748B',
    textAlign: 'center',
    marginBottom: 24,
    lineHeight: 20,
  },
  primaryButton: {
    backgroundColor: '#063B5C',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  primaryButtonText: {
    color: '#FFF',
    fontWeight: 'bold',
    fontSize: 14,
  },
  activeEventCard: {
    width: '100%',
    backgroundColor: '#063B5C',
    borderRadius: 14,
    padding: 16,
    marginBottom: 10,
    shadowColor: 'rgba(6, 59, 92, 0.2)',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 8,
    elevation: 4,
  },
  noActiveEventCard: {
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  noActiveEventText: {
    color: '#64748B',
    fontSize: 13,
    fontWeight: '500',
  },
  liveHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  liveIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#0284C7',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  liveDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: '#FFF',
    marginRight: 5,
  },
  liveText: {
    color: '#FFF',
    fontSize: 10,
    fontWeight: 'bold',
    letterSpacing: 0.5,
  },
  sessionTypeBadge: {
    color: '#35C4E8',
    fontSize: 11,
    fontWeight: 'bold',
  },
  liveEventTitle: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  venueRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  venueText: {
    color: '#BAE6FD',
    fontSize: 12.5,
  },
  radarCard: {
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F0F9FF',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 10,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#BAE6FD',
  },
  radarCardInRange: {
    backgroundColor: '#ECFDF5',
    borderColor: '#A7F3D0',
  },
  radarCardOutOfRange: {
    backgroundColor: '#FEF2F2',
    borderColor: '#FECACA',
  },
  radarText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#0369A1',
  },
  radarTextInRange: {
    color: '#065F46',
  },
  radarTextOutOfRange: {
    color: '#991B1B',
  },
  statusRow: {
    width: '100%',
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 6,
    marginBottom: 14,
  },
  statusBadge: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 6,
    paddingHorizontal: 6,
    borderRadius: 8,
    borderWidth: 1,
  },
  statusBadgeGreen: {
    backgroundColor: '#E8F6F1',
    borderColor: '#A7E2D0',
  },
  statusBadgeRed: {
    backgroundColor: '#FEF2F2',
    borderColor: '#FECACA',
  },
  statusBadgeBlue: {
    backgroundColor: '#E6F4FE',
    borderColor: '#BCE1FD',
  },
  statusBadgeText: {
    fontSize: 11,
    fontWeight: 'bold',
  },
  scannerWrapper: {
    width: '100%',
    alignItems: 'center',
    marginBottom: 16,
  },
  cameraFrame: {
    width: 290,
    height: 290,
    borderRadius: 20,
    overflow: 'hidden',
    position: 'relative',
    backgroundColor: '#061D2E',
    borderWidth: 2.5,
    borderColor: '#35C4E8',
    elevation: 6,
    shadowColor: '#35C4E8',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.35,
    shadowRadius: 8,
  },
  cameraView: {
    position: 'absolute',
    top: -48,
    left: 0,
    width: 290,
    height: 386,
  },
  cameraLoadingOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: '#061D2E',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 10,
  },
  cameraLoadingText: {
    color: '#35C4E8',
    marginTop: 12,
    fontSize: 12.5,
    fontWeight: '600',
  },
  reticleContainer: {
    ...StyleSheet.absoluteFillObject,
    padding: 16,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 2,
  },
  corner: {
    position: 'absolute',
    width: 24,
    height: 24,
    borderColor: '#35C4E8',
  },
  topLeft: {
    top: 14,
    left: 14,
    borderTopWidth: 4,
    borderLeftWidth: 4,
    borderTopLeftRadius: 6,
  },
  topRight: {
    top: 14,
    right: 14,
    borderTopWidth: 4,
    borderRightWidth: 4,
    borderTopRightRadius: 6,
  },
  bottomLeft: {
    bottom: 14,
    left: 14,
    borderBottomWidth: 4,
    borderLeftWidth: 4,
    borderBottomLeftRadius: 6,
  },
  bottomRight: {
    bottom: 14,
    right: 14,
    borderBottomWidth: 4,
    borderRightWidth: 4,
    borderBottomRightRadius: 6,
  },
  centerTargetCross: {
    width: 32,
    height: 32,
    justifyContent: 'center',
    alignItems: 'center',
  },
  crosshairH: {
    width: 14,
    height: 2,
    backgroundColor: 'rgba(53, 196, 232, 0.6)',
  },
  crosshairV: {
    position: 'absolute',
    width: 2,
    height: 14,
    backgroundColor: 'rgba(53, 196, 232, 0.6)',
  },
  processingPill: {
    position: 'absolute',
    bottom: 14,
    alignSelf: 'center',
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(6, 43, 68, 0.90)',
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: 25,
    borderWidth: 1.5,
    borderColor: '#35C4E8',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.35,
    shadowRadius: 5,
    elevation: 6,
    zIndex: 10,
  },
  processingPillText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: 'bold',
  },
  externalControlsBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    marginTop: 12,
  },
  externalControlBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#BAE6FD',
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 4,
    elevation: 2,
    gap: 4,
  },
  externalControlBtnActive: {
    backgroundColor: '#0284C7',
    borderColor: '#0284C7',
  },
  externalControlText: {
    fontSize: 11.5,
    fontWeight: 'bold',
    color: '#0284C7',
  },
  rescanBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#063B5C',
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 25,
    marginTop: 14,
    shadowColor: 'rgba(6, 59, 92, 0.3)',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 8,
    elevation: 4,
  },
  rescanText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: 'bold',
  },
  tipsCard: {
    width: '100%',
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 6,
    elevation: 2,
  },
  tipsHeading: {
    fontSize: 13.5,
    fontWeight: 'bold',
    color: '#063B5C',
    marginBottom: 10,
  },
  tipRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  tipText: {
    flex: 1,
    fontSize: 12,
    color: '#475569',
    lineHeight: 17,
  },
  troubleshootCard: {
    width: '100%',
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    marginTop: 12,
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 6,
    elevation: 2,
  },
  troubleshootTitle: {
    fontSize: 13.5,
    fontWeight: 'bold',
    color: '#063B5C',
    marginBottom: 6,
  },
  troubleshootDesc: {
    fontSize: 12,
    color: '#64748B',
    marginBottom: 8,
    lineHeight: 16,
  },
  troubleshootTipRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 6,
  },
  troubleshootTipText: {
    flex: 1,
    fontSize: 11.5,
    color: '#475569',
    lineHeight: 16,
  },
  troubleshootActions: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 8,
  },
  troubleshootBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F0F9FF',
    paddingVertical: 9,
    paddingHorizontal: 10,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#BAE6FD',
  },
  troubleshootBtnText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#0284C7',
  },
});
