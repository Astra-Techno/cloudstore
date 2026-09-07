import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'app/app.dart';
import 'app/providers/bootstrap_provider.dart';
import 'app/providers/auth_provider.dart';
import 'app/providers/cart_provider.dart';
import 'app/providers/notification_provider.dart';
import 'app/providers/driver_provider.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => BootstrapProvider()),
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => CartProvider()),
        ChangeNotifierProvider(create: (_) => NotificationProvider()),
        ChangeNotifierProvider(create: (_) => DriverProvider()),
      ],
      child: const CloudStoreApp(),
    ),
  );
}
