import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'app/app.dart';
import 'app/providers/bootstrap_provider.dart';
import 'app/providers/auth_provider.dart';
import 'app/providers/cart_provider.dart';
import 'app/providers/notification_provider.dart';
import 'app/providers/driver_provider.dart';
import 'app/providers/favourites_provider.dart';
import 'app/providers/location_provider.dart';
import 'config/app_config.dart';
import 'services/notification_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize notifications (includes Firebase if google-services.json exists)
  try {
    await NotificationService().initialize();
  } catch (e) {
    debugPrint('Notification init error: $e');
  }

  final app = MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => BootstrapProvider()),
      ChangeNotifierProvider(create: (_) => AuthProvider()),
      ChangeNotifierProvider(create: (_) => CartProvider()),
      ChangeNotifierProvider(create: (_) => NotificationProvider()),
      ChangeNotifierProvider(create: (_) => DriverProvider()),
      ChangeNotifierProvider(create: (_) => FavouritesProvider()),
      ChangeNotifierProvider(create: (_) => LocationProvider()),
    ],
    child: const _FcmTokenBridge(child: CloudStoreApp()),
  );

  // Sentry crash reporting — DSN is provided at build time.
  // If no DSN, Sentry is a no-op and the app runs normally.
  const sentryDsn = String.fromEnvironment('SENTRY_DSN', defaultValue: '');
  if (sentryDsn.isNotEmpty) {
    await SentryFlutter.init(
      (options) {
        options.dsn = sentryDsn;
        options.environment = const String.fromEnvironment('SENTRY_ENV', defaultValue: 'production');
        options.tracesSampleRate = 0.2;
        options.attachScreenshot = true;
        options.sendDefaultPii = false;
      },
      appRunner: () => runApp(app),
    );
  } else {
    runApp(app);
  }
}

/// Bridges FCM token refreshes to AuthProvider / DriverProvider.
class _FcmTokenBridge extends StatefulWidget {
  final Widget child;
  const _FcmTokenBridge({required this.child});

  @override
  State<_FcmTokenBridge> createState() => _FcmTokenBridgeState();
}

class _FcmTokenBridgeState extends State<_FcmTokenBridge> {
  StreamSubscription<String>? _sub;

  @override
  void initState() {
    super.initState();
    _sub = NotificationService().onTokenRefresh.listen((token) {
      // Forward to auth provider so it registers with the API
      context.read<AuthProvider>().saveFcmToken(token);
    });
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
