// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:cloudstore/app/app.dart';
import 'package:cloudstore/app/providers/bootstrap_provider.dart';
import 'package:cloudstore/app/providers/auth_provider.dart';
import 'package:cloudstore/app/providers/cart_provider.dart';
import 'package:cloudstore/app/providers/notification_provider.dart';
import 'package:cloudstore/app/providers/driver_provider.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('CloudStoreApp smoke test', (WidgetTester tester) async {
    final bootstrap = BootstrapProvider();
    bootstrap.setTenantData(<String, dynamic>{
      'tenant': <String, dynamic>{'id': 1, 'name': 'Test Store', 'business_type': 'retail'},
      'branding': <String, dynamic>{'primary_color': '#4CAF50'},
      'features': <String, dynamic>{},
    });

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<BootstrapProvider>.value(value: bootstrap),
          ChangeNotifierProvider(create: (_) => AuthProvider()),
          ChangeNotifierProvider(create: (_) => CartProvider()),
          ChangeNotifierProvider(create: (_) => NotificationProvider()),
          ChangeNotifierProvider(create: (_) => DriverProvider()),
        ],
        child: const CloudStoreApp(),
      ),
    );

    expect(find.byType(CloudStoreApp), findsOneWidget);
  });
}
