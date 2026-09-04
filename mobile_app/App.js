import React, { useState, useEffect } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import * as SecureStore from 'expo-secure-store';
import { ActivityIndicator, View, Image, Text, Pressable, LogBox, StyleSheet } from 'react-native';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';

import { ToastProvider } from './context/ToastContext';

import * as SplashScreen from 'expo-splash-screen';

// Disable default dev LogBox banner over the UI and suppress console output
LogBox.ignoreAllLogs();
console.log = () => {};
console.info = () => {};
console.warn = () => {};
console.debug = () => {};

// Import Screens
import LoginScreen from './screens/LoginScreen';
import DashboardScreen from './screens/DashboardScreen';
import ScannerScreen from './screens/ScannerScreen';
import HistoryScreen from './screens/HistoryScreen';
import FinesScreen from './screens/FinesScreen';
import ProfileScreen from './screens/ProfileScreen';
import EventDetailsScreen from './screens/EventDetailsScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

// Bottom Tab Navigator for authenticated users
function MainTabs() {
  const insets = useSafeAreaInsets();
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;

          if (route.name === 'Home') {
            iconName = focused ? 'home' : 'home-outline';
          } else if (route.name === 'Scan') {
            iconName = focused ? 'qr-code' : 'qr-code-outline';
          } else if (route.name === 'History') {
            iconName = focused ? 'journal' : 'journal-outline';
          } else if (route.name === 'Fines') {
            iconName = focused ? 'receipt' : 'receipt-outline';
          } else if (route.name === 'Profile') {
            iconName = focused ? 'person' : 'person-outline';
          }

          return <Ionicons name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: '#35C4E8',
        tabBarInactiveTintColor: '#6B7A86',
        tabBarStyle: {
          backgroundColor: '#FFFFFF',
          borderTopWidth: 1,
          borderTopColor: '#DCE7ED',
          paddingBottom: Math.max(insets.bottom, 5),
          paddingTop: 5,
          height: 55 + Math.max(insets.bottom, 0),
        },
        headerStyle: {
          backgroundColor: '#063B5C',
          shadowColor: 'rgba(6, 59, 92, 0.05)',
          elevation: 3,
          borderBottomWidth: 0,
        },
        headerTitleAlign: 'left',
        headerTitle: () => (
          <View style={{ flexDirection: 'row', alignItems: 'center', paddingVertical: 2 }}>
            <Image 
              source={require('./assets/bsis-logo.png')} 
              style={{ width: 40, height: 40, resizeMode: 'contain', marginRight: 10 }} 
            />
            <View style={{ justifyContent: 'center' }}>
              <Text style={{ color: '#FFFFFF', fontWeight: 'bold', fontSize: 15, lineHeight: 19 }}>
                BSIS Student Attendance Monitoring System
              </Text>
              <Text style={{ color: '#35C4E8', fontSize: 12.5, fontWeight: '600', marginTop: 1 }}>
                Talibon Polytechnic College
              </Text>
            </View>
          </View>
        ),
        tabBarButton: (props) => (
          <Pressable 
            {...props} 
            android_ripple={{ color: 'transparent' }}
            style={({ pressed }) => [
              props.style,
              { opacity: pressed ? 0.7 : 1 }
            ]}
          />
        ),
      })}
    >
      <Tab.Screen 
        name="Home" 
        component={DashboardScreen} 
        options={{ tabBarLabel: 'Dashboard' }} 
      />
      <Tab.Screen 
        name="Scan" 
        component={ScannerScreen} 
        options={{ tabBarLabel: 'Scan', unmountOnBlur: true }} 
      />
      <Tab.Screen 
        name="History" 
        component={HistoryScreen} 
        options={{ tabBarLabel: 'History' }} 
      />
      <Tab.Screen 
        name="Fines" 
        component={FinesScreen} 
        options={{ tabBarLabel: 'Fines' }} 
      />
      <Tab.Screen 
        name="Profile" 
        component={ProfileScreen} 
        options={{ tabBarLabel: 'Profile' }} 
      />
    </Tab.Navigator>
  );
}

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [initialRoute, setInitialRoute] = useState('Login');

  useEffect(() => {
    // Dismiss native overlay immediately so custom branded screen displays
    SplashScreen.hideAsync().catch(() => {});

    // Check authentication and display official BSIS branded splash screen
    const prepareApp = async () => {
      try {
        const [token] = await Promise.all([
          SecureStore.getItemAsync('user_token'),
          new Promise(resolve => setTimeout(resolve, 1800)) // Smooth branded display
        ]);
        if (token) {
          setInitialRoute('MainTabs');
        }
      } catch (e) {
        console.error("Error reading token", e);
      } finally {
        setIsLoading(false);
      }
    };

    prepareApp();
  }, []);

  if (isLoading) {
    return (
      <View style={styles.splashContainer}>
        <Image 
          source={require('./assets/bsis-logo.png')} 
          style={styles.splashLogo} 
        />
        <Text style={styles.splashTitle}>Bachelor of Science in Information Systems</Text>
        <Text style={styles.splashSubtitle}>TALIBON POLYTECHNIC COLLEGE</Text>
        <ActivityIndicator size="small" color="#35C4E8" style={{ marginTop: 26 }} />
      </View>
    );
  }

  return (
    <SafeAreaProvider>
      <ToastProvider>
        <NavigationContainer>
          <Stack.Navigator initialRouteName={initialRoute} screenOptions={{ headerShown: false }}>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="MainTabs" component={MainTabs} />
            <Stack.Screen name="EventDetails" component={EventDetailsScreen} />
          </Stack.Navigator>
        </NavigationContainer>
      </ToastProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  splashContainer: {
    flex: 1,
    backgroundColor: '#063B5C',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 28,
  },
  splashLogo: {
    width: 140,
    height: 140,
    resizeMode: 'contain',
    marginBottom: 20,
  },
  splashTitle: {
    color: '#FFFFFF',
    fontSize: 19,
    fontWeight: 'bold',
    textAlign: 'center',
    letterSpacing: 0.3,
    lineHeight: 25,
  },
  splashSubtitle: {
    color: '#35C4E8',
    fontSize: 13,
    fontWeight: '700',
    marginTop: 8,
    textAlign: 'center',
    letterSpacing: 1.2,
    textTransform: 'uppercase',
  },
});
