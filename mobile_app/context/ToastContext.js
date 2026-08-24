import React, { createContext, useContext, useState, useRef, useCallback } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  Animated, 
  TouchableOpacity, 
  TouchableWithoutFeedback,
  Modal, 
  Dimensions, 
  Platform 
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';

const ToastContext = createContext({
  showToast: () => {},
  hideToast: () => {},
});

export const useToast = () => useContext(ToastContext);

export const ToastProvider = ({ children }) => {
  const [toast, setToast] = useState({
    visible: false,
    title: '',
    message: '',
    type: 'info', // 'success' | 'error' | 'info' | 'warning'
    metadata: null, // { eventTitle, sessionSlot, scanTime, status }
  });

  const scale = useRef(new Animated.Value(0.78)).current;
  const opacity = useRef(new Animated.Value(0)).current;

  const hideToast = useCallback(() => {
    Animated.parallel([
      Animated.timing(scale, {
        toValue: 0.85,
        duration: 200,
        useNativeDriver: true,
      }),
      Animated.timing(opacity, {
        toValue: 0,
        duration: 180,
        useNativeDriver: true,
      }),
    ]).start(() => {
      setToast(prev => ({ ...prev, visible: false }));
    });
  }, [scale, opacity]);

  const showToast = useCallback((message, type = 'info', duration = null, title = null, metadata = null) => {
    let defaultTitle = '';
    if (!title) {
      if (type === 'success') defaultTitle = 'Attendance Confirmed! 🎉';
      else if (type === 'error') defaultTitle = 'Scan Unsuccessful ⚠️';
      else if (type === 'warning') defaultTitle = 'Notice 📍';
      else defaultTitle = 'System Notification';
    } else {
      defaultTitle = title;
    }

    setToast({
      visible: true,
      title: defaultTitle,
      message,
      type,
      metadata,
    });

    scale.setValue(0.78);
    opacity.setValue(0);

    Animated.parallel([
      Animated.spring(scale, {
        toValue: 1,
        useNativeDriver: true,
        bounciness: 7,
        speed: 13,
      }),
      Animated.timing(opacity, {
        toValue: 1,
        duration: 200,
        useNativeDriver: true,
      }),
    ]).start();
  }, [scale, opacity]);

  const getToastConfig = (type) => {
    switch (type) {
      case 'success':
        return {
          icon: 'checkmark-circle',
          iconColor: '#168A63',
          iconBg: '#DCFCE7',
          accentColor: '#168A63',
          titleColor: '#065F46',
          btnColor: '#168A63',
        };
      case 'error':
        return {
          icon: 'alert-circle',
          iconColor: '#DC2626',
          iconBg: '#FEE2E2',
          accentColor: '#DC2626',
          titleColor: '#991B1B',
          btnColor: '#DC2626',
        };
      case 'warning':
        return {
          icon: 'warning',
          iconColor: '#D97706',
          iconBg: '#FEF3C7',
          accentColor: '#D97706',
          titleColor: '#92400E',
          btnColor: '#D97706',
        };
      case 'info':
      default:
        return {
          icon: 'information-circle',
          iconColor: '#0284C7',
          iconBg: '#E0F2FE',
          accentColor: '#0284C7',
          titleColor: '#0369A1',
          btnColor: '#0284C7',
        };
    }
  };

  const config = getToastConfig(toast.type);

  return (
    <ToastContext.Provider value={{ showToast, hideToast }}>
      {children}

      <Modal
        visible={toast.visible}
        transparent={true}
        animationType="none"
        statusBarTranslucent={true}
        onRequestClose={hideToast}
      >
        <View style={styles.backdropOverlay}>
          <TouchableWithoutFeedback onPress={hideToast}>
            <View style={styles.backdropTouchArea} />
          </TouchableWithoutFeedback>

          <Animated.View
            style={[
              styles.centerCard,
              {
                transform: [{ scale }],
                opacity,
              },
            ]}
          >
            {/* Centered Large Icon */}
            <View style={[styles.iconWrapper, { backgroundColor: config.iconBg }]}>
              <Ionicons name={config.icon} size={38} color={config.iconColor} />
            </View>

            {/* Title */}
            {Boolean(toast.title) && (
              <Text style={[styles.toastTitle, { color: config.titleColor }]}>
                {toast.title}
              </Text>
            )}

            {/* Metadata Attendance Receipt Card */}
            {toast.metadata ? (
              <View style={styles.receiptContainer}>
                {/* Event Name Tag */}
                <View style={styles.eventTag}>
                  <Ionicons name="calendar-outline" size={13} color="#0284C7" style={{ marginRight: 5 }} />
                  <Text style={styles.eventTagText} numberOfLines={2}>
                    {toast.metadata.eventTitle || 'Campus Event'}
                  </Text>
                </View>

                {/* Details Table */}
                <View style={styles.receiptBox}>
                  <View style={styles.receiptRow}>
                    <Text style={styles.receiptLabel}>Session Slot</Text>
                    <Text style={styles.receiptSlotHighlight}>
                      {toast.metadata.sessionSlot || 'Attendance'}
                    </Text>
                  </View>

                  <View style={styles.receiptDivider} />

                  <View style={styles.receiptRow}>
                    <Text style={styles.receiptLabel}>Scan Time</Text>
                    <Text style={styles.receiptValue}>
                      {toast.metadata.scanTime || 'Just Now'}
                    </Text>
                  </View>

                  {Boolean(toast.metadata.status) && (
                    <>
                      <View style={styles.receiptDivider} />
                      <View style={styles.receiptRow}>
                        <Text style={styles.receiptLabel}>Verification</Text>
                        <Text style={[
                          styles.receiptStatusText,
                          { color: String(toast.metadata.status).includes('Late') ? '#DC2626' : '#168A63' }
                        ]}>
                          {toast.metadata.status}
                        </Text>
                      </View>
                    </>
                  )}
                </View>
              </View>
            ) : (
              <Text style={styles.toastMessage}>
                {toast.message}
              </Text>
            )}

            {/* Confirm / Dismiss Button */}
            <TouchableOpacity 
              style={[styles.actionBtn, { backgroundColor: config.btnColor }]}
              onPress={hideToast}
              activeOpacity={0.88}
            >
              <Text style={styles.actionBtnText}>Okay, Got It</Text>
            </TouchableOpacity>
          </Animated.View>
        </View>
      </Modal>
    </ToastContext.Provider>
  );
};

const styles = StyleSheet.create({
  backdropOverlay: {
    flex: 1,
    backgroundColor: 'rgba(6, 59, 92, 0.48)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 26,
  },
  backdropTouchArea: {
    ...StyleSheet.absoluteFillObject,
  },
  centerCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    paddingVertical: 24,
    paddingHorizontal: 22,
    width: '100%',
    maxWidth: 360,
    alignItems: 'center',
    shadowColor: 'rgba(0, 0, 0, 0.35)',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 1,
    shadowRadius: 28,
    elevation: 24,
  },
  iconWrapper: {
    width: 68,
    height: 68,
    borderRadius: 34,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  toastTitle: {
    fontSize: 18.5,
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: 12,
    letterSpacing: 0.2,
  },
  toastMessage: {
    color: '#334155',
    fontSize: 14.5,
    fontWeight: '500',
    lineHeight: 21,
    textAlign: 'center',
    marginBottom: 20,
    paddingHorizontal: 4,
  },
  receiptContainer: {
    width: '100%',
    marginBottom: 20,
  },
  eventTag: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#E0F2FE',
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 8,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#BAE6FD',
  },
  eventTagText: {
    color: '#0369A1',
    fontSize: 13,
    fontWeight: 'bold',
    textAlign: 'center',
  },
  receiptBox: {
    backgroundColor: '#F8FAFC',
    borderRadius: 14,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  receiptRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 4,
  },
  receiptDivider: {
    height: 1,
    backgroundColor: '#EEF2F6',
    marginVertical: 4,
  },
  receiptLabel: {
    fontSize: 12.5,
    color: '#64748B',
    fontWeight: '600',
  },
  receiptSlotHighlight: {
    fontSize: 13.5,
    fontWeight: 'bold',
    color: '#063B5C',
  },
  receiptValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#1E293B',
  },
  receiptStatusText: {
    fontSize: 12.5,
    fontWeight: 'bold',
  },
  actionBtn: {
    width: '100%',
    paddingVertical: 13,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: 'rgba(0, 0, 0, 0.14)',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 1,
    shadowRadius: 8,
    elevation: 4,
  },
  actionBtnText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: 'bold',
    letterSpacing: 0.3,
  },
});
