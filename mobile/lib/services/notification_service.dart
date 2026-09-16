import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Top-level handler for background FCM messages (must be top-level function).
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // Background messages are automatically shown as system notifications
  // by the FCM SDK when a `notification` payload is present.
  debugPrint('FCM background message: ${message.messageId}');
}

class NotificationService {
  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();
  final _storage = const FlutterSecureStorage();
  bool _initialized = false;
  bool _firebaseReady = false;

  /// Stream of FCM tokens for callers that need to register with the API.
  final _tokenController = StreamController<String>.broadcast();
  Stream<String> get onTokenRefresh => _tokenController.stream;

  /// The latest known FCM token (may be null if Firebase is not configured).
  String? _fcmToken;
  String? get fcmToken => _fcmToken;

  Future<void> initialize() async {
    if (_initialized) return;

    try {
      const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
      const iosSettings = DarwinInitializationSettings(
        requestAlertPermission: true,
        requestBadgePermission: true,
        requestSoundPermission: true,
      );
      const initSettings = InitializationSettings(
        android: androidSettings,
        iOS: iosSettings,
      );

      await _localNotifications.initialize(
        initSettings,
        onDidReceiveNotificationResponse: _onNotificationTap,
      );

      _initialized = true;
    } catch (e) {
      debugPrint('Local notifications init failed: $e');
    }

    await _initFirebase();
  }

  Future<void> _initFirebase() async {
    try {
      await Firebase.initializeApp();
      _firebaseReady = true;

      final messaging = FirebaseMessaging.instance;

      // Request permission (iOS primarily; Android 13+ handled by permission_handler)
      await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        provisional: false,
      );

      // Get initial token
      _fcmToken = await messaging.getToken();
      if (_fcmToken != null) {
        await _storage.write(key: 'fcm_token', value: _fcmToken);
        _tokenController.add(_fcmToken!);
      }

      // Listen for token refreshes
      messaging.onTokenRefresh.listen((token) async {
        _fcmToken = token;
        await _storage.write(key: 'fcm_token', value: token);
        _tokenController.add(token);
      });

      // Register background handler
      FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);

      // Foreground messages — show as local notification
      FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

      // When user taps a notification that opened the app from terminated/background
      FirebaseMessaging.onMessageOpenedApp.listen(_handleMessageOpenedApp);

      // Check if app was opened from a terminated state via notification
      final initialMessage = await messaging.getInitialMessage();
      if (initialMessage != null) {
        _handleMessageOpenedApp(initialMessage);
      }

      debugPrint('FCM initialized. Token: ${_fcmToken?.substring(0, 20)}...');
    } catch (e) {
      debugPrint('Firebase init skipped (not configured): $e');
      // Firebase not configured — app continues with local notifications only.
      // This is normal for dev builds without google-services.json.
    }
  }

  void _handleForegroundMessage(RemoteMessage message) {
    final notification = message.notification;
    if (notification == null) return;

    showLocalNotification(
      id: message.hashCode,
      title: notification.title ?? 'CloudStore',
      body: notification.body ?? '',
      payload: message.data['route'] ?? message.data['order_uuid'] ?? '',
    );
  }

  void _handleMessageOpenedApp(RemoteMessage message) {
    // Navigation is handled by the app's GoRouter based on payload.
    // Store the route for the splash/home screen to pick up.
    final route = message.data['route'] ?? '';
    if (route.isNotEmpty) {
      debugPrint('FCM tap navigation: $route');
    }
  }

  void _onNotificationTap(NotificationResponse response) {
    // Payload-based navigation handled by GoRouter redirect logic
    debugPrint('Notification tapped: ${response.payload}');
  }

  Future<void> showLocalNotification({
    required int id,
    required String title,
    required String body,
    String? payload,
  }) async {
    const androidDetails = AndroidNotificationDetails(
      'cloudstore_orders',
      'Order Updates',
      channelDescription: 'Notifications about your orders',
      importance: Importance.high,
      priority: Priority.high,
      playSound: true,
    );

    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    const details = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _localNotifications.show(id, title, body, details, payload: payload);
  }

  Future<void> showOrderStatusNotification(String orderNumber, String status) async {
    final statusMessages = {
      'confirmed': 'Your order has been confirmed!',
      'accepted': 'Your order is being processed.',
      'preparing': 'Your order is being prepared.',
      'ready': 'Your order is ready!',
      'ready_for_pickup': 'Your order is ready for pickup!',
      'out_for_delivery': 'Your order is on its way!',
      'delivered': 'Your order has been delivered. Enjoy!',
      'cancelled': 'Your order has been cancelled.',
    };

    final message = statusMessages[status] ?? 'Order status updated to $status';

    await showLocalNotification(
      id: orderNumber.hashCode,
      title: 'Order $orderNumber',
      body: message,
      payload: 'order:$orderNumber',
    );
  }

  /// Subscribe to a topic (e.g., tenant-specific broadcasts).
  Future<void> subscribeTopic(String topic) async {
    if (!_firebaseReady) return;
    try {
      await FirebaseMessaging.instance.subscribeToTopic(topic);
    } catch (_) {}
  }

  /// Unsubscribe from a topic.
  Future<void> unsubscribeTopic(String topic) async {
    if (!_firebaseReady) return;
    try {
      await FirebaseMessaging.instance.unsubscribeFromTopic(topic);
    } catch (_) {}
  }
}
