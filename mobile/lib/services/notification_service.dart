import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class NotificationService {
  static final NotificationService _instance = NotificationService._internal();
  factory NotificationService() => _instance;
  NotificationService._internal();

  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();
  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized) return;

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

    // Try Firebase initialization - gracefully handle if not configured
    await _initFirebase();
  }

  Future<void> _initFirebase() async {
    try {
      // Firebase requires google-services.json (Android) / GoogleService-Info.plist (iOS)
      // If not configured, FCM simply won't work but the app continues normally
      // Uncomment when Firebase is configured:
      // await Firebase.initializeApp();
      // final messaging = FirebaseMessaging.instance;
      // await messaging.requestPermission();
      // final token = await messaging.getToken();
      // print('FCM Token: $token');
      // FirebaseMessaging.onMessage.listen(_handleForegroundMessage);
      // FirebaseMessaging.onMessageOpenedApp.listen(_handleMessageOpenedApp);
    } catch (_) {
      // Firebase not configured - using local notifications only
    }
  }

  void _onNotificationTap(NotificationResponse response) {
    // Handle notification tap - navigate to relevant screen
    // This is handled by the app's navigation based on the payload
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
}
