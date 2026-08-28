import React, { useState, useEffect } from 'react';
import { 
  View, 
  Text, 
  TextInput, 
  TouchableOpacity, 
  StyleSheet, 
  ActivityIndicator, 
  Alert, 
  KeyboardAvoidingView, 
  Platform, 
  Image, 
  Linking, 
  ScrollView,
  Modal 
} from 'react-native';
import * as SecureStore from 'expo-secure-store';
import axios from 'axios';
import * as Device from 'expo-device';
import * as LocalAuthentication from 'expo-local-authentication';
import * as Haptics from 'expo-haptics';
import { Ionicons } from '@expo/vector-icons';

import { API_URL } from '../config';

export default function LoginScreen({ navigation }) {
  const [studentId, setStudentId] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [isBiometricSupported, setIsBiometricSupported] = useState(false);
  const [biometricTypeLabel, setBiometricTypeLabel] = useState('Face Unlock / Fingerprint');
  const [hasFaceScan, setHasFaceScan] = useState(false);
  const [savedUser, setSavedUser] = useState(null);

  // Forgot / Reset Password Modal State
  const [forgotModalVisible, setForgotModalVisible] = useState(false);
  const [forgotStep, setForgotStep] = useState(1); // 1: Request Code, 2: Reset Password
  const [resetIdentifier, setResetIdentifier] = useState('');
  const [resetToken, setResetToken] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [resetLoading, setResetLoading] = useState(false);
  const [resetError, setResetError] = useState('');
  const [resetSuccess, setResetSuccess] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const hasHardware = await LocalAuthentication.hasHardwareAsync();
        const isEnrolled = await LocalAuthentication.isEnrolledAsync();
        const bioTypes = await LocalAuthentication.supportedAuthenticationTypesAsync();
        const bioEnabled = await SecureStore.getItemAsync('biometric_enabled');
        const savedLoginId = await SecureStore.getItemAsync('saved_login_id');
        const savedUserStr = (await SecureStore.getItemAsync('user_data')) || (await SecureStore.getItemAsync('biometric_user'));

        const supportsFace = bioTypes.includes(LocalAuthentication.AuthenticationType.FACIAL_RECOGNITION);
        const supportsFingerprint = bioTypes.includes(LocalAuthentication.AuthenticationType.FINGERPRINT);
        setHasFaceScan(supportsFace);

        if (supportsFace && supportsFingerprint) {
          setBiometricTypeLabel('Face Unlock / Fingerprint');
        } else if (supportsFace) {
          setBiometricTypeLabel('Face Unlock');
        } else {
          setBiometricTypeLabel('Fingerprint');
        }

        if (hasHardware && isEnrolled && (savedLoginId || savedUserStr) && bioEnabled !== 'false') {
          setIsBiometricSupported(true);
          try {
            if (savedUserStr) {
              const parsed = JSON.parse(savedUserStr);
              setSavedUser(parsed);
            }
            if (savedLoginId) {
              setStudentId(savedLoginId);
            }
          } catch(e) {}
        }
      } catch (e) {
        console.log('Biometric check error:', e);
      }
    })();
  }, []);

  const handleLogin = async () => {
    if (!studentId || !password) {
      Alert.alert('Missing Credentials', 'Please enter your Student ID / Email and Password.');
      return;
    }

    setLoading(true);
    try {
      // 1. Fetch or generate Device Credential for Anti-Spoofing binding
      let deviceCredential = await SecureStore.getItemAsync('device_credential');
      
      const payload = {
        login: studentId.trim(),
        password: password,
        device_name: Device.modelName || 'Student Mobile Phone',
        device_credential: deviceCredential
      };

      const response = await axios.post(`${API_URL}/auth/login`, payload, {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });

      if (response.data.success) {
        try {
          await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch(e) {}
        
        const { token, user } = response.data.data;
        
        // Save Auth Token, User Data, and Secure Credentials for Biometric Re-Auth
        await SecureStore.setItemAsync('user_token', token);
        await SecureStore.setItemAsync('user_data', JSON.stringify(user));
        await SecureStore.setItemAsync('saved_login_id', studentId.trim());
        await SecureStore.setItemAsync('saved_login_pw', password);
        await SecureStore.setItemAsync('biometric_enabled', 'true');
        
        // Save Hardware Binding Token
        if (user.device_credential) {
          await SecureStore.setItemAsync('device_credential', user.device_credential);
        }

        // Navigate to Dashboard
        navigation.replace('MainTabs');
      }
    } catch (error) {
      try {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      } catch(e) {}
      console.error('Login error:', error.response?.data || error.message);
      const msg = error.response?.data?.message || 'Failed to connect to the server. Please check your internet connection.';
      Alert.alert('Login Failed', msg);
    } finally {
      setLoading(false);
    }
  };

  const handleBiometricLogin = async () => {
    try {
      const result = await LocalAuthentication.authenticateAsync({
        promptMessage: `Unlock Attendance Portal for ${savedUser?.first_name || 'Student'}`,
        fallbackLabel: 'Use Password Instead',
        cancelLabel: 'Cancel',
      });

      if (result.success) {
        setLoading(true);
        try {
          await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } catch(e) {}

        const savedLoginId = await SecureStore.getItemAsync('saved_login_id');
        const savedLoginPw = await SecureStore.getItemAsync('saved_login_pw');
        let deviceCredential = await SecureStore.getItemAsync('device_credential');

        if (savedLoginId && savedLoginPw) {
          try {
            const res = await axios.post(`${API_URL}/auth/login`, {
              login: savedLoginId,
              password: savedLoginPw,
              device_name: Device.modelName || 'Student Mobile Phone',
              device_credential: deviceCredential
            }, {
              headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
              }
            });

            if (res.data.success) {
              const { token, user } = res.data.data;
              await SecureStore.setItemAsync('user_token', token);
              await SecureStore.setItemAsync('user_data', JSON.stringify(user));
              if (user.device_credential) {
                await SecureStore.setItemAsync('device_credential', user.device_credential);
              }
              navigation.replace('MainTabs');
              return;
            }
          } catch (apiErr) {
            console.log('Biometric re-auth error:', apiErr.response?.data || apiErr.message);
          }
        }

        // Fallback: check if existing active token exists
        const activeToken = await SecureStore.getItemAsync('user_token');
        if (activeToken) {
          navigation.replace('MainTabs');
        } else {
          Alert.alert('Session Expired', 'Please enter your password once to re-sync your biometric login.');
        }
      }
    } catch (e) {
      console.log('Biometric auth error:', e);
    } finally {
      setLoading(false);
    }
  };

  const openForgotModal = () => {
    setResetIdentifier(studentId.trim());
    setResetToken('');
    setNewPassword('');
    setConfirmPassword('');
    setResetError('');
    setResetSuccess('');
    setForgotStep(1);
    setForgotModalVisible(true);
  };

  const handleRequestResetToken = async () => {
    if (!resetIdentifier.trim()) {
      setResetError('Please enter your Student ID or Institutional Email.');
      return;
    }

    setResetLoading(true);
    setResetError('');
    setResetSuccess('');

    try {
      const res = await axios.post(`${API_URL}/auth/forgot-password`, {
        login: resetIdentifier.trim()
      }, {
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
      });

      try {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      } catch (e) {}

      setResetSuccess(res.data?.message || 'Password reset token dispatched to your email.');
      // Proceed to Step 2 to enter the code and set new password
      setForgotStep(2);
    } catch (err) {
      try {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      } catch (e) {}
      const msg = err.response?.data?.message || 'Failed to request reset token. Please try again.';
      setResetError(msg);
    } finally {
      setResetLoading(false);
    }
  };

  const validatePasswordStrength = (pass, confirm) => {
    const hasLen = pass.length >= 8;
    const hasLower = /[a-z]/.test(pass);
    const hasUpper = /[A-Z]/.test(pass);
    const hasNum = /[0-9]/.test(pass);
    const hasSym = /[^A-Za-z0-9]/.test(pass);
    const hasMatch = pass.length > 0 && pass === confirm;
    return hasLen && hasLower && hasUpper && hasNum && hasSym && hasMatch;
  };

  const handleExecutePasswordReset = async () => {
    if (!resetToken.trim()) {
      setResetError('Please enter the reset token received in your email.');
      return;
    }

    if (!newPassword || !confirmPassword) {
      setResetError('Please enter and confirm your new password.');
      return;
    }

    if (newPassword !== confirmPassword) {
      setResetError('Passwords do not match. Please verify.');
      return;
    }

    if (!validatePasswordStrength(newPassword, confirmPassword)) {
      setResetError('Password must be at least 8 characters and include uppercase, lowercase, numbers, and symbols.');
      return;
    }

    setResetLoading(true);
    setResetError('');
    setResetSuccess('');

    try {
      const res = await axios.post(`${API_URL}/auth/reset-password`, {
        login: resetIdentifier.trim(),
        token: resetToken.trim(),
        password: newPassword,
        password_confirmation: confirmPassword
      }, {
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
      });

      try {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      } catch (e) {}

      Alert.alert(
        'Password Reset Successful',
        'Your account password has been updated. Your device hardware binding remains active. You can now sign in with your new password.',
        [
          {
            text: 'Sign In Now',
            onPress: () => {
              setForgotModalVisible(false);
              setStudentId(resetIdentifier.trim());
              setPassword('');
            }
          }
        ]
      );
    } catch (err) {
      try {
        await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      } catch (e) {}
      const msg = err.response?.data?.message || 'Password reset failed. Please check your token or request a new one.';
      setResetError(msg);
    } finally {
      setResetLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView 
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView 
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.card}>
          {/* Institutional Logos Side-by-Side */}
          <View style={styles.logoContainer}>
            <Image 
              source={require('../assets/tpc-logo.png')} 
              style={styles.logo} 
            />
            <View style={styles.logoDivider} />
            <Image 
              source={require('../assets/bsis-logo.png')} 
              style={styles.logo} 
            />
          </View>

          {/* Department & Institution Branding Header */}
          <Text style={styles.deptHeader}>TALIBON POLYTECHNIC COLLEGE</Text>
          <Text style={styles.deptSubHeader}>BSIS DEPARTMENT</Text>
          
          <View style={styles.systemTagContainer}>
            <Text style={styles.systemTagText}>Student Attendance Portal</Text>
          </View>

          {/* Form Fields */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>Student ID or Institutional Email</Text>
            <View style={styles.inputWrapper}>
              <Ionicons name="person-outline" size={18} color="#64748B" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="e.g. 2024-00001 or name@tpc.edu.ph"
                placeholderTextColor="#94A3B8"
                value={studentId}
                onChangeText={setStudentId}
                autoCapitalize="none"
                autoCorrect={false}
              />
            </View>
          </View>

          <View style={styles.inputGroup}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
              <Text style={styles.label}>Password</Text>
              <TouchableOpacity onPress={openForgotModal} style={{ paddingVertical: 2 }}>
                <Text style={styles.forgotPasswordText}>Forgot Password?</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.inputWrapper}>
              <Ionicons name="lock-closed-outline" size={18} color="#64748B" style={styles.inputIcon} />
              <TextInput
                style={styles.input}
                placeholder="Enter your account password"
                placeholderTextColor="#94A3B8"
                secureTextEntry={!showPassword}
                value={password}
                onChangeText={setPassword}
                autoCapitalize="none"
                autoCorrect={false}
              />
              <TouchableOpacity 
                style={styles.eyeButton} 
                onPress={() => setShowPassword(!showPassword)}
              >
                <Ionicons 
                  name={showPassword ? "eye-off-outline" : "eye-outline"} 
                  size={18} 
                  color="#64748B" 
                />
              </TouchableOpacity>
            </View>
          </View>

          {/* Sign In Button */}
          <TouchableOpacity 
            style={[styles.button, loading && styles.buttonDisabled]} 
            onPress={handleLogin}
            disabled={loading}
            activeOpacity={0.85}
          >
            {loading ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'center' }}>
                <Text style={styles.buttonText}>Sign In to Portal</Text>
                <Ionicons name="arrow-forward" size={18} color="#FFFFFF" style={{ marginLeft: 8 }} />
              </View>
            )}
          </TouchableOpacity>

          {/* Biometric Quick Login Button */}
          {isBiometricSupported && (
            <TouchableOpacity 
              style={styles.biometricButton}
              onPress={handleBiometricLogin}
              activeOpacity={0.85}
            >
              <Ionicons 
                name={hasFaceScan ? "scan-outline" : "finger-print"} 
                size={20} 
                color="#063B5C" 
                style={{ marginRight: 8 }} 
              />
              <Text style={styles.biometricButtonText}>
                Quick Login with {biometricTypeLabel}
              </Text>
            </TouchableOpacity>
          )}

          {/* Institutional Links & Credentials Note */}
          <View style={styles.footerNoteContainer}>
            <View style={styles.secureBadge}>
              <Ionicons name="shield-checkmark" size={13} color="#168A63" style={{ marginRight: 4 }} />
              <Text style={styles.secureBadgeText}>Hardware Bound & Anti-Spoofing Protected</Text>
            </View>

            <View style={styles.linksContainer}>
              <TouchableOpacity 
                style={styles.linkItem}
                onPress={() => openExternalUrl('https://tpc.edu.ph/')}
              >
                <Ionicons name="globe-outline" size={14} color="#0284C7" style={{ marginRight: 4 }} />
                <Text style={styles.linkText}>Official TPC Website</Text>
              </TouchableOpacity>
              
              <Text style={styles.linkDot}>•</Text>

              <TouchableOpacity 
                style={styles.linkItem}
                onPress={() => openExternalUrl('https://www.facebook.com/TalibonPolytechnicCollege')}
              >
                <Ionicons name="logo-facebook" size={14} color="#0284C7" style={{ marginRight: 4 }} />
                <Text style={styles.linkText}>TPC Facebook Page</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </ScrollView>

      {/* Forgot / Reset Password Modal */}
      <Modal
        visible={forgotModalVisible}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setForgotModalVisible(false)}
      >
        <KeyboardAvoidingView 
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'} 
          style={styles.modalBackdrop}
        >
          <View style={styles.modalCard}>
            {/* Modal Header */}
            <View style={styles.modalHeader}>
              <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                <View style={styles.modalIconBox}>
                  <Ionicons name="key-outline" size={20} color="#063B5C" />
                </View>
                <View>
                  <Text style={styles.modalTitle}>
                    {forgotStep === 1 ? 'Reset Password' : 'Enter Reset Code'}
                  </Text>
                  <Text style={styles.modalSubtitle}>BSIS Student Portal</Text>
                </View>
              </View>
              <TouchableOpacity 
                onPress={() => setForgotModalVisible(false)}
                style={styles.modalCloseBtn}
              >
                <Ionicons name="close" size={20} color="#64748B" />
              </TouchableOpacity>
            </View>

            {/* Error / Success Banners */}
            {resetError ? (
              <View style={styles.modalAlertDanger}>
                <Ionicons name="alert-circle" size={16} color="#DC2626" style={{ marginRight: 6 }} />
                <Text style={styles.modalAlertDangerText}>{resetError}</Text>
              </View>
            ) : null}

            {resetSuccess ? (
              <View style={styles.modalAlertSuccess}>
                <Ionicons name="checkmark-circle" size={16} color="#16A34A" style={{ marginRight: 6 }} />
                <Text style={styles.modalAlertSuccessText}>{resetSuccess}</Text>
              </View>
            ) : null}

            {forgotStep === 1 ? (
              /* STEP 1: Enter Identifier to Request Reset Token */
              <View>
                <Text style={styles.modalHelperText}>
                  Enter your Student ID or institutional email. We will send a secure 60-minute password reset token to your registered inbox.
                </Text>

                <View style={styles.inputGroup}>
                  <Text style={styles.label}>Student ID or Email</Text>
                  <View style={styles.inputWrapper}>
                    <Ionicons name="mail-outline" size={18} color="#64748B" style={styles.inputIcon} />
                    <TextInput
                      style={styles.input}
                      placeholder="e.g. 2024-00001 or student@tpc.edu.ph"
                      placeholderTextColor="#94A3B8"
                      value={resetIdentifier}
                      onChangeText={setResetIdentifier}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                  </View>
                </View>

                <TouchableOpacity 
                  style={[styles.button, resetLoading && styles.buttonDisabled]}
                  onPress={handleRequestResetToken}
                  disabled={resetLoading}
                >
                  {resetLoading ? (
                    <ActivityIndicator color="#FFFFFF" />
                  ) : (
                    <Text style={styles.buttonText}>Send Reset Code</Text>
                  )}
                </TouchableOpacity>

                <TouchableOpacity 
                  style={styles.modalSwitchBtn}
                  onPress={() => setForgotStep(2)}
                >
                  <Text style={styles.modalSwitchBtnText}>Already have a reset code? Enter it here &rarr;</Text>
                </TouchableOpacity>
              </View>
            ) : (
              /* STEP 2: Enter Token and New Password */
              <ScrollView showsVerticalScrollIndicator={false} style={{ maxHeight: 380 }}>
                <Text style={styles.modalHelperText}>
                  Enter the reset token sent to your email, then choose a strong new password.
                </Text>

                {/* Reset Token Input */}
                <View style={styles.inputGroup}>
                  <Text style={styles.label}>Password Reset Token / Code</Text>
                  <View style={styles.inputWrapper}>
                    <Ionicons name="ticket-outline" size={18} color="#64748B" style={styles.inputIcon} />
                    <TextInput
                      style={styles.input}
                      placeholder="Paste your reset token from email"
                      placeholderTextColor="#94A3B8"
                      value={resetToken}
                      onChangeText={setResetToken}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                  </View>
                </View>

                {/* New Password */}
                <View style={styles.inputGroup}>
                  <Text style={styles.label}>New Password</Text>
                  <View style={styles.inputWrapper}>
                    <Ionicons name="lock-closed-outline" size={18} color="#64748B" style={styles.inputIcon} />
                    <TextInput
                      style={styles.input}
                      placeholder="At least 8 characters"
                      placeholderTextColor="#94A3B8"
                      secureTextEntry={!showNewPassword}
                      value={newPassword}
                      onChangeText={setNewPassword}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                    <TouchableOpacity 
                      style={styles.eyeButton} 
                      onPress={() => setShowNewPassword(!showNewPassword)}
                    >
                      <Ionicons 
                        name={showNewPassword ? "eye-off-outline" : "eye-outline"} 
                        size={18} 
                        color="#64748B" 
                      />
                    </TouchableOpacity>
                  </View>
                </View>

                {/* Confirm Password */}
                <View style={styles.inputGroup}>
                  <Text style={styles.label}>Confirm New Password</Text>
                  <View style={styles.inputWrapper}>
                    <Ionicons name="shield-checkmark-outline" size={18} color="#64748B" style={styles.inputIcon} />
                    <TextInput
                      style={styles.input}
                      placeholder="Re-type new password"
                      placeholderTextColor="#94A3B8"
                      secureTextEntry={!showConfirmPassword}
                      value={confirmPassword}
                      onChangeText={setConfirmPassword}
                      autoCapitalize="none"
                      autoCorrect={false}
                    />
                    <TouchableOpacity 
                      style={styles.eyeButton} 
                      onPress={() => setShowConfirmPassword(!showConfirmPassword)}
                    >
                      <Ionicons 
                        name={showConfirmPassword ? "eye-off-outline" : "eye-outline"} 
                        size={18} 
                        color="#64748B" 
                      />
                    </TouchableOpacity>
                  </View>
                </View>

                {/* Password Requirements Checklist */}
                <View style={styles.requirementsBox}>
                  <Text style={styles.requirementsTitle}>Password Requirements:</Text>
                  <Text style={[styles.requirementItem, newPassword.length >= 8 ? styles.reqMet : styles.reqUnmet]}>
                    {newPassword.length >= 8 ? '✓' : '•'} At least 8 characters
                  </Text>
                  <Text style={[styles.requirementItem, /[A-Z]/.test(newPassword) && /[a-z]/.test(newPassword) ? styles.reqMet : styles.reqUnmet]}>
                    {/[A-Z]/.test(newPassword) && /[a-z]/.test(newPassword) ? '✓' : '•'} Uppercase & lowercase letters
                  </Text>
                  <Text style={[styles.requirementItem, /[0-9]/.test(newPassword) && /[^A-Za-z0-9]/.test(newPassword) ? styles.reqMet : styles.reqUnmet]}>
                    {/[0-9]/.test(newPassword) && /[^A-Za-z0-9]/.test(newPassword) ? '✓' : '•'} Numbers & special symbols
                  </Text>
                  <Text style={[styles.requirementItem, newPassword && newPassword === confirmPassword ? styles.reqMet : styles.reqUnmet]}>
                    {newPassword && newPassword === confirmPassword ? '✓' : '•'} Passwords match
                  </Text>
                </View>

                <TouchableOpacity 
                  style={[styles.button, resetLoading && styles.buttonDisabled, { marginTop: 12 }]}
                  onPress={handleExecutePasswordReset}
                  disabled={resetLoading}
                >
                  {resetLoading ? (
                    <ActivityIndicator color="#FFFFFF" />
                  ) : (
                    <Text style={styles.buttonText}>Confirm & Set New Password</Text>
                  )}
                </TouchableOpacity>

                <TouchableOpacity 
                  style={styles.modalSwitchBtn}
                  onPress={() => setForgotStep(1)}
                >
                  <Text style={styles.modalSwitchBtnText}>&larr; Request a new code</Text>
                </TouchableOpacity>
              </ScrollView>
            )}
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#063B5C',
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 20,
    paddingVertical: 36,
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 18,
    elevation: 8,
  },
  logoContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  logo: {
    width: 60,
    height: 60,
    resizeMode: 'contain',
  },
  logoDivider: {
    width: 1.5,
    height: 36,
    backgroundColor: '#CBD5E1',
    marginHorizontal: 16,
  },
  deptHeader: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#063B5C',
    textAlign: 'center',
    letterSpacing: 0.8,
  },
  deptSubHeader: {
    fontSize: 11,
    fontWeight: 'bold',
    color: '#64748B',
    textAlign: 'center',
    letterSpacing: 0.5,
    marginTop: 2,
  },
  systemTagContainer: {
    backgroundColor: '#E6F4FE',
    alignSelf: 'center',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 20,
    marginTop: 8,
    marginBottom: 20,
  },
  systemTagText: {
    color: '#063B5C',
    fontSize: 11,
    fontWeight: '600',
  },
  inputGroup: {
    marginBottom: 16,
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: '#334155',
    marginBottom: 6,
  },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#CBD5E1',
    borderRadius: 10,
    backgroundColor: '#F8FAFC',
    paddingHorizontal: 12,
  },
  inputIcon: {
    marginRight: 8,
  },
  input: {
    flex: 1,
    paddingVertical: 11,
    fontSize: 14,
    color: '#0F172A',
  },
  eyeButton: {
    padding: 6,
  },
  button: {
    backgroundColor: '#063B5C',
    borderRadius: 10,
    paddingVertical: 13,
    alignItems: 'center',
    marginTop: 6,
    shadowColor: '#063B5C',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 6,
    elevation: 3,
  },
  buttonDisabled: {
    opacity: 0.7,
  },
  buttonText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: 'bold',
  },
  biometricButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F0F9FF',
    borderWidth: 1.5,
    borderColor: '#BAE6FD',
    borderRadius: 10,
    paddingVertical: 11,
    marginTop: 12,
  },
  biometricButtonText: {
    color: '#063B5C',
    fontSize: 13.5,
    fontWeight: 'bold',
  },
  footerNoteContainer: {
    marginTop: 20,
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: '#F1F5F9',
    paddingTop: 16,
  },
  secureBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#E8F6F1',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
    marginBottom: 12,
  },
  secureBadgeText: {
    color: '#168A63',
    fontSize: 10.5,
    fontWeight: '600',
  },
  forgotPasswordText: {
    color: '#0284C7',
    fontSize: 12,
    fontWeight: '600',
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(4, 37, 58, 0.75)',
    justifyContent: 'center',
    padding: 20,
  },
  modalCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 22,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.3,
    shadowRadius: 16,
    elevation: 10,
    maxHeight: '90%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#F1F5F9',
    paddingBottom: 14,
    marginBottom: 14,
  },
  modalIconBox: {
    width: 38,
    height: 38,
    borderRadius: 10,
    backgroundColor: '#E6F4FE',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },
  modalTitle: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#063B5C',
  },
  modalSubtitle: {
    fontSize: 11,
    color: '#64748B',
    fontWeight: '600',
  },
  modalCloseBtn: {
    padding: 6,
  },
  modalHelperText: {
    fontSize: 12.5,
    color: '#475569',
    lineHeight: 18,
    marginBottom: 16,
  },
  modalAlertDanger: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FEF2F2',
    borderColor: '#FECACA',
    borderWidth: 1,
    borderRadius: 8,
    padding: 10,
    marginBottom: 14,
  },
  modalAlertDangerText: {
    color: '#B91C1C',
    fontSize: 12,
    flex: 1,
    lineHeight: 16,
  },
  modalAlertSuccess: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F0FDF4',
    borderColor: '#BBF7D0',
    borderWidth: 1,
    borderRadius: 8,
    padding: 10,
    marginBottom: 14,
  },
  modalAlertSuccessText: {
    color: '#15803D',
    fontSize: 12,
    flex: 1,
    lineHeight: 16,
  },
  modalSwitchBtn: {
    marginTop: 14,
    alignItems: 'center',
    paddingVertical: 6,
  },
  modalSwitchBtnText: {
    fontSize: 12,
    color: '#0284C7',
    fontWeight: '600',
  },
  requirementsBox: {
    backgroundColor: '#F8FAFC',
    borderColor: '#E2E8F0',
    borderWidth: 1,
    borderRadius: 8,
    padding: 10,
    marginTop: 4,
    marginBottom: 6,
  },
  requirementsTitle: {
    fontSize: 11,
    fontWeight: 'bold',
    color: '#334155',
    marginBottom: 4,
  },
  requirementItem: {
    fontSize: 11,
    lineHeight: 16,
  },
  reqMet: {
    color: '#16A34A',
    fontWeight: '600',
  },
  reqUnmet: {
    color: '#94A3B8',
  },
  linksContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    flexWrap: 'wrap',
  },
  linkItem: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  linkText: {
    fontSize: 11.5,
    color: '#0284C7',
    fontWeight: '600',
  },
  linkDot: {
    marginHorizontal: 8,
    color: '#94A3B8',
    fontSize: 12,
  },
});
