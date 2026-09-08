import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/bootstrap_provider.dart';
import 'router.dart';

class CloudStoreApp extends StatelessWidget {
  const CloudStoreApp({super.key});

  @override
  Widget build(BuildContext context) {
    final bootstrap = context.watch<BootstrapProvider>();

    return MaterialApp.router(
      title: bootstrap.tenantName ?? 'CloudStore',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorSchemeSeed: bootstrap.primaryColor ?? Colors.blue,
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF8FAFC),
        appBarTheme: AppBarTheme(
          centerTitle: false,
          elevation: 0,
          scrolledUnderElevation: 1,
          backgroundColor: bootstrap.primaryColor ?? Colors.blue,
          foregroundColor: Colors.white,
          titleTextStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
        ),
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(16))),
        ),
        navigationBarTheme: NavigationBarThemeData(
          height: 72,
          labelTextStyle: WidgetStateProperty.all(const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        ),
      ),
      routerConfig: appRouter,
    );
  }
}
