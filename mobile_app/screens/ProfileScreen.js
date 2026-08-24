import React, { useState, useEffect, useCallback } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  ScrollView, 
  RefreshControl, 
  ActivityIndicator, 
  TouchableOpacity,
  Switch,
  Alert 
} from 'react-native';
import * as SecureStore from 'expo-secure-store';
import * as LocalAuthentication from 'expo-local-authentication';
import * as Haptics from 'expo-haptics';
import axios from 'axios';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';

import { API_URL } from '../config';

export default function ProfileScreen({ navigation }) {
  const [user, setUser] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [biometricSupported, setBiometricSupported] = useState(false);
  const [biometricType, setBiometricType] = useState('Biometrics');
  const [biometricEnabled, setBiometricEnabled] = useState(false);

  const fetchProfile = async () => {
    try {
      const token = await SecureStore.getItemAsync('user_token');
      if (!token) {
        navigation.replace('Login');
        return;
      }

      const headers = { Authorization: `Bearer ${token}` };
      const profileRes = await axios.get(`${API_URL}/auth/me`, { headers });
      if (profileRes.data.success) {
        setUser(profileRes.data.data);
      }

      // Check Biometric Sensor Hardware
      const hasHardware = await LocalAuthentication.hasHardwareAsync();
      const isEnrolled = await LocalAuthentication.isEnrolledAsync();
      const bioTypes = await LocalAuthentication.supportedAuthenticationTypesAsync();
      
      let typeLabel = 'Fingerprint / Face ID';
      if (bioTypes.includes(LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION)) {
        typeLabel = 'Face ID / Facial Recognition';
      } else if (bioTypes.includes(LocalAuthentication.AuthenticationType.FINGERPRINT)) {
        typeLabel = 'Fingerprint Sensor';
      }
      setBiometricType(typeLabel);

      const isBioOn = await SecureStore.getItemAsync('biometric_enabled');
      setBiometricSupported(hasHardware && isEnrolled);
      setBiometricEnabled(isBioOn === 'true');

    } catch (error) {
      console.error('Profile Error:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchProfile();
    }, [])
  );

  const toggleBiometrics = async (value) => {
    if (!biometricSupported) {
      Alert.alert(
        'Biometrics Unavailable',
        'Your phone does not have fingerprint or Face ID enrolled. Please register a fingerprint or face unlock in your phone settings first.'
      );
      return;
    }

    try {
      if (value) {
        const result = await LocalAuthentication.authenticateAsync({
          promptMessage: 'Authenticate to enable Quick Biometric Login',
          fallbackLabel: 'Use Passcode',
          cancelLabel: 'Cancel',
        });

        if (result.success) {
          try {
            await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
          } catch(e) {}
          
          const token = await SecureStore.getItemAsync('user_token');
          await SecureStore.setItemAsync('biometric_enabled', 'true');
          await SecureStore.setItemAsync('biometric_token', token || '');
          await SecureStore.setItemAsync('biometric_user', JSON.stringify(user || {}));
          setBiometricEnabled(true);
          Alert.alert('Success 🎉', 'Biometric Quick Login is now enabled! You can unlock the app using your Fingerprint or Face ID.');
        }
      } else {
        await SecureStore.deleteItemAsync('biometric_enabled');
        await SecureStore.deleteItemAsync('biometric_token');
        await SecureStore.deleteItemAsync('biometric_user');
        setBiometricEnabled(false);
        try {
          await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        } catch(e) {}
      }
    } catch (e) {
      console.log('Error toggling biometrics:', e);
    }
  };

  const handleLogout = () => {
    Alert.alert(
      'Confirm Logout 🔒',
      'Are you sure you want to log out of your BSIS Student Attendance Portal?',
      [
        {
          text: 'No, Cancel',
          style: 'cancel',
        },
        {
          text: 'Yes, Log Out',
          style: 'destructive',
          onPress: async () => {
            try {
              const token = await SecureStore.getItemAsync('user_token');
              if (token) {
                await axios.post(`${API_URL}/auth/logout`, {}, { headers: { Authorization: `Bearer ${token}` } });
              }
            } catch(e) {}
            
            // Delete active session but preserve biometric token if biometric is enabled
            await SecureStore.deleteItemAsync('user_token');
            navigation.replace('Login');
          }
        }
      ],
      { cancelable: true }
    );
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
      contentContainerStyle={styles.scrollContent}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); fetchProfile(); }} tintColor="#063B5C" />}
    >
      <View style={styles.profileHeader}>
        <View style={styles.avatar}>
          <Ionicons name="person" size={38} color="#FFFFFF" />
        </View>
        <Text style={styles.nameText}>{user?.full_name}</Text>
        <Text style={styles.emailText}>{user?.email || 'No email provided'}</Text>
        
        <View style={styles.badgeContainer}>
          <Ionicons name="shield-checkmark" size={12} color="#168A63" style={{ marginRight: 4 }} />
          <Text style={styles.badgeText}>Hardware Bound Device</Text>
        </View>
      </View>

      {/* Academic Info */}
      <View style={styles.infoCard}>
        <Text style={styles.sectionTitle}>Academic Information</Text>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Student ID</Text>
          <Text style={styles.infoValue}>{user?.student_number}</Text>
        </View>
        <View style={styles.divider} />
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Program</Text>
          <Text style={styles.infoValue}>Bachelor of Science in Information Systems</Text>
        </View>
        <View style={styles.divider} />
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Year & Section</Text>
          <Text style={styles.infoValue}>{user?.year_level} - {user?.section_block}</Text>
        </View>
      </View>

      {/* Security & Biometrics Section */}
      <View style={styles.infoCard}>
        <Text style={styles.sectionTitle}>Security & Quick Login</Text>
        
        <View style={styles.switchRow}>
          <View style={{ flex: 1, paddingRight: 10 }}>
            <View style={{ flexDirection: 'row', alignItems: 'center', marginBottom: 2 }}>
              <Ionicons name="finger-print" size={18} color="#063B5C" style={{ marginRight: 6 }} />
              <Text style={styles.switchLabel}>Biometric Quick Login</Text>
            </View>
            <Text style={styles.switchSub}>
              {biometricSupported 
                ? `Use ${biometricType} to unlock portal instantly`
                : 'No registered fingerprint or Face ID detected on this phone'}
            </Text>
          </View>
          <Switch
            value={biometricEnabled}
            onValueChange={toggleBiometrics}
            trackColor={{ false: '#CBD5E1', true: '#35C4E8' }}
            thumbColor={biometricEnabled ? '#063B5C' : '#F1F5F9'}
            disabled={!biometricSupported}
          />
        </View>

        <View style={styles.divider} />

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Hardware Binding</Text>
          <Text style={[styles.infoValue, { color: '#16A34A' }]}>Locked to this Device 🔒</Text>
        </View>
      </View>
      
      <TouchableOpacity onPress={handleLogout} style={styles.logoutBtn} activeOpacity={0.85}>
        <Ionicons name="log-out-outline" size={20} color="#DC2626" />
        <Text style={styles.logoutText}>Log Out Account</Text>
      </TouchableOpacity>
      
      <View style={{height: 40}} />
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
  profileHeader: {
    alignItems: 'center',
    backgroundColor: '#063B5C',
    borderRadius: 20,
    padding: 24,
    marginBottom: 16,
    shadowColor: 'rgba(6, 59, 92, 0.2)',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 10,
    elevation: 4,
  },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: 'rgba(255, 255, 255, 0.15)',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
    borderWidth: 2,
    borderColor: '#35C4E8',
  },
  nameText: {
    color: '#FFFFFF',
    fontSize: 18,
    fontWeight: 'bold',
    marginBottom: 4,
    textAlign: 'center',
  },
  emailText: {
    color: '#CBD5E1',
    fontSize: 13,
    marginBottom: 12,
    textAlign: 'center',
  },
  badgeContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F0FDF4',
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#BBF7D0',
  },
  badgeText: {
    color: '#168A63',
    fontSize: 11.5,
    fontWeight: '700',
  },
  infoCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 18,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    shadowColor: 'rgba(6, 59, 92, 0.04)',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 1,
    shadowRadius: 6,
    elevation: 2,
  },
  sectionTitle: {
    fontSize: 13.5,
    fontWeight: '800',
    color: '#063B5C',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 14,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
  },
  infoLabel: {
    color: '#64748B',
    fontSize: 13,
    fontWeight: '500',
    flex: 1,
  },
  infoValue: {
    color: '#0F172A',
    fontSize: 13,
    fontWeight: '700',
    textAlign: 'right',
    flex: 1.5,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
  },
  switchLabel: {
    fontSize: 13.5,
    fontWeight: '700',
    color: '#063B5C',
  },
  switchSub: {
    fontSize: 11.5,
    color: '#64748B',
    lineHeight: 16,
  },
  divider: {
    height: 1,
    backgroundColor: '#F1F5F9',
    marginVertical: 4,
  },
  logoutBtn: {
    backgroundColor: '#FEF2F2',
    borderWidth: 1.5,
    borderColor: '#FECACA',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 13,
    borderRadius: 12,
    marginTop: 6,
    gap: 8,
  },
  logoutText: {
    color: '#DC2626',
    fontWeight: '700',
    fontSize: 14,
  },
});
